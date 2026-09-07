<?php

namespace App\Http\Middleware;

use App\Models\AppUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to a signed-in, active employee (AppUser). Runs after
 * `auth:sanctum`. A deactivated employee's token stops working here even though
 * it was never revoked.
 */
class EnsureAppUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof AppUser, 403, 'This route requires an employee session.');
        abort_unless($user->is_active, 403, 'Your access to this app has been deactivated.');

        return $next($request);
    }
}
