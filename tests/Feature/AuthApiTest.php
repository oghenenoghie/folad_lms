<?php

namespace Tests\Feature;

use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthApiTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(School $school, array $overrides = []): User
    {
        return User::create(array_merge([
            'school_id' => $school->id,
            'name' => 'Jane Admin',
            'email' => 'jane@example.com',
            'password' => Hash::make('correct-password'),
        ], $overrides));
    }

    public function test_a_user_can_log_in_with_correct_credentials_and_receives_their_roles(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $user = $this->makeUser($school);

        app(PermissionRegistrar::class)->setPermissionsTeamId($school->id);
        $user->assignRole(Role::firstOrCreate(['name' => 'school_admin', 'guard_name' => 'web', 'school_id' => $school->id]));

        $response = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);

        $response->assertOk();
        $response->assertJsonPath('user.email', 'jane@example.com');
        $response->assertJsonPath('user.roles.0', 'school_admin');
        $this->assertNotEmpty($response->json('token'));
    }

    public function test_login_fails_with_wrong_password(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $this->makeUser($school);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(422);
    }

    public function test_login_fails_for_unknown_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'nobody@example.com',
            'password' => 'whatever',
        ])->assertStatus(422);
    }

    public function test_login_fails_for_a_deactivated_account(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $this->makeUser($school, ['is_active' => false]);

        $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ])->assertStatus(422);
    }

    public function test_the_issued_token_authenticates_subsequent_requests(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $this->makeUser($school);

        $login = $this->postJson('/api/login', [
            'email' => 'jane@example.com',
            'password' => 'correct-password',
        ]);
        $token = $login->json('token');

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    /**
     * The Next.js frontend never sends an Authorization header -- it relies
     * entirely on Sanctum's SPA cookie flow (CSRF cookie, then a
     * same-origin-looking login, then subsequent requests authenticated by
     * the session cookie alone). Drives that exact sequence rather than
     * using actingAs(), since the whole point is proving the cookie/CSRF/
     * CORS wiring (config/cors.php, SANCTUM_STATEFUL_DOMAINS, and
     * AuthController's hasSession() branch) actually produces a working
     * session -- actingAs() would bypass all of that and prove nothing.
     */
    public function test_a_browser_client_can_authenticate_via_session_cookie_alone(): void
    {
        config(['sanctum.stateful' => ['localhost:3000']]);

        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $this->makeUser($school);

        $csrf = $this->withHeader('Origin', 'http://localhost:3000')->get('/sanctum/csrf-cookie');
        $csrf->assertNoContent();

        $xsrfCookie = collect($csrf->headers->getCookies())->first(fn ($cookie) => $cookie->getName() === 'XSRF-TOKEN');
        $this->assertNotNull($xsrfCookie, 'Expected /sanctum/csrf-cookie to set an XSRF-TOKEN cookie.');

        $login = $this->withHeader('Origin', 'http://localhost:3000')
            ->withCookie('XSRF-TOKEN', $xsrfCookie->getValue())
            ->withHeader('X-XSRF-TOKEN', urldecode($xsrfCookie->getValue()))
            ->postJson('/api/login', ['email' => 'jane@example.com', 'password' => 'correct-password']);

        $login->assertOk();

        $sessionCookie = collect($login->headers->getCookies())
            ->first(fn ($cookie) => str_contains($cookie->getName(), 'session'));
        $this->assertNotNull($sessionCookie, 'Expected login to set a session cookie for a stateful-origin request.');

        // No Authorization header anywhere below -- the session cookie alone
        // has to carry authentication, exactly like the real frontend.
        $this->withHeader('Origin', 'http://localhost:3000')
            ->withCookie($sessionCookie->getName(), $sessionCookie->getValue())
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('data.email', 'jane@example.com');
    }

    public function test_logout_revokes_the_token(): void
    {
        $school = School::create(['name' => 'School A', 'code' => 'school-a']);
        $user = $this->makeUser($school);
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/logout')
            ->assertNoContent();

        $this->assertDatabaseCount('personal_access_tokens', 0);

        // Laravel's RequestGuard (what "sanctum" resolves to) caches its
        // resolved user for the guard instance's lifetime, and that instance
        // survives across simulated requests within one test method -- so
        // without this, the next call would see the token-holder cached from
        // the request above rather than re-evaluating the (now-deleted) token.
        // Not an issue in real usage, where every request is a fresh process.
        $this->app['auth']->forgetGuards();

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/user')
            ->assertUnauthorized();
    }
}
