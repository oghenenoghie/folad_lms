<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class AuthController extends Controller
{
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

        // Role lookups are scoped by whatever team id is set on the registrar
        // (see SetPermissionsTeamId); at login there's been no authenticated
        // request yet to set it, so it has to happen explicitly here.
        app(PermissionRegistrar::class)->setPermissionsTeamId($user->school_id);

        return response()->json([
            'token' => $token,
            'user'  => [
                'id'        => $user->id,
                'name'      => $user->name,
                'email'     => $user->email,
                'school_id' => $user->school_id,
                'roles'     => $user->getRoleNames(),
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }
}
