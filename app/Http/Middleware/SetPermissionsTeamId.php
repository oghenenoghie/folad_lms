<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * spatie/laravel-permission "teams" are keyed on school_id (see config/permission.php).
 * Role/permission lookups are scoped to whatever team id is set on the registrar, so
 * every authenticated request must set it from the current user before any hasRole()/
 * can() check runs — a super_admin (school_id === null) gets no team constraint.
 */
class SetPermissionsTeamId
{
    public function handle(Request $request, Closure $next): Response
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($request->user()?->school_id);

        return $next($request);
    }
}
