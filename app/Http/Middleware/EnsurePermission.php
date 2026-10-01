<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission gate.
 *
 * Super admins pass through unconditionally; everyone else must hold at least
 * one of the listed permissions in the society they are currently acting in.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return $next($request);
            }
        }

        abort(403, 'You do not have permission to view this page.');
    }
}
