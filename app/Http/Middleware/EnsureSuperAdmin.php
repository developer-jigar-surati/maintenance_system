<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Restricts a route to platform operators.
 *
 * Distinct from the permission middleware: super admin is a platform-level
 * flag held outside any society, so it cannot be expressed as a society-scoped
 * permission.
 */
class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        abort_unless($user->isSuperAdmin(), 403, 'This area is restricted to platform operators.');

        return $next($request);
    }
}
