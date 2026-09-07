<?php

namespace App\Http\Middleware;

use App\Models\AppUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps employee (AppUser) tokens out of the platform API. `auth:sanctum`
 * resolves any token in `personal_access_tokens` regardless of its owner type,
 * so the `/api/v1` routes that aren't already gated by `tenant`/`admin` need
 * this explicit check.
 */
class EnsurePlatformAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if($request->user() instanceof AppUser, 403, 'This route is not available to employee accounts.');

        return $next($request);
    }
}
