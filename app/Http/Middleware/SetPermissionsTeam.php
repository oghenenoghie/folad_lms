<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * spatie/laravel-permission's "teams" feature is keyed by school_id (see
 * config/permission.php). Roles/permissions are meaningless until the
 * registrar knows which team to check against, so this must run before
 * anything calls hasRole()/can() — including policies.
 *
 * spatie's team_foreign_key column is NOT NULL (it's part of a composite
 * primary key on model_has_roles/model_has_permissions), so super_admin
 * (school_id = null) can't use null as its team id — it uses the sentinel
 * GLOBAL_TEAM_ID instead. This only scopes role/permission lookups; data
 * queries still use App\Support\Tenancy, where null correctly means
 * "no school filter".
 */
class SetPermissionsTeam
{
    public const GLOBAL_TEAM_ID = 0;

    public function handle(Request $request, Closure $next): Response
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId(
            $request->user()?->school_id ?? self::GLOBAL_TEAM_ID
        );

        return $next($request);
    }
}
