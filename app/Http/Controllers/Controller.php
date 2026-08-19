<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

// Extends the legacy routing Controller (rather than the bare Laravel 11+
// skeleton class) so authorizeResource()'s constructor-time middleware()
// call keeps working — the new HasMiddleware interface is per-controller
// boilerplate we don't need for a straightforward policy-gated API.
abstract class Controller extends BaseController
{
    use AuthorizesRequests;
}
