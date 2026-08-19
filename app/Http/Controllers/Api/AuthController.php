<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\SetPermissionsTeam;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sanctum SPA (cookie) auth. The frontend must GET /sanctum/csrf-cookie
 * before POSTing here, and send every request with credentials: 'include'.
 */
class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        if (! Auth::attempt($credentials, remember: true)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $user = $request->user();

        if (! $user->is_active) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'This account has been deactivated.',
            ]);
        }

        $request->session()->regenerate();

        // The 'team' middleware only runs on the authenticated route group,
        // so this route (outside it) must set spatie's team context itself
        // before reading roles off the freshly-authenticated user.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id ?? SetPermissionsTeam::GLOBAL_TEAM_ID);

        return new UserResource($user->load(['staff', 'student', 'guardian']));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load(['staff', 'student', 'guardian']));
    }
}
