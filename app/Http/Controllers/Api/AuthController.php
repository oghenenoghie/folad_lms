<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
    /**
     * Serves two kinds of client from one endpoint: a bearer token for
     * non-browser callers (tests, Postman, a future mobile app), and a
     * session cookie for the Next.js SPA (Sanctum SPA auth -- the frontend
     * never reads the token field, it relies on the Set-Cookie this
     * triggers via Auth::login() + session regeneration). Harmless to do
     * both on every login; a bearer client just ignores the cookie.
     */
    public function login(LoginRequest $request)
    {
        // users.email is globally unique (not tenant-scoped) precisely so this
        // lookup works before we know which school the caller belongs to.
        $user = User::where('email', $request->string('email'))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['This account has been deactivated.'],
            ]);
        }

        $token = $user->createToken($request->input('device_name', 'api'))->plainTextToken;

        // Only requests Sanctum recognised as "from the frontend" (Origin/
        // Referer matching SANCTUM_STATEFUL_DOMAINS) get StartSession piped
        // in -- see EnsureFrontendRequestsAreStateful::frontendMiddleware().
        // A bearer client (tests, Postman, a mobile app) never does, and
        // $request->session() throws if called without it.
        if ($request->hasSession()) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
        }

        // Role lookups are scoped by whatever team id is set on the registrar
        // (see SetPermissionsTeamId); at login there's been no authenticated
        // request yet to set it, so it has to happen explicitly here.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id);

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'school_id' => $user->school_id,
                'roles' => $user->getRoleNames(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        // Bearer clients authenticate via a PersonalAccessToken and have one
        // to revoke; cookie clients authenticate via the 'web' session guard
        // and have none -- currentAccessToken() is null for the latter.
        if ($request->user()->currentAccessToken() instanceof PersonalAccessToken) {
            $request->user()->currentAccessToken()->delete();
        }

        if ($request->hasSession() && Auth::guard('web')->check()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->noContent();
    }
}
