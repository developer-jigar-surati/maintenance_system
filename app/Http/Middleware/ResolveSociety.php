<?php

namespace App\Http\Middleware;

use App\Support\SocietyContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Puts the signed-in user's active society into scope for the request.
 *
 * Everything downstream -- the society global scope on models, the permission
 * team id, date and currency formatting -- reads from what this sets. Without
 * it a query would either see every society's rows or none.
 */
class ResolveSociety
{
    public function __construct(private SocietyContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $society = $user->currentSociety;

        // A user with no active society yet -- freshly invited, or a platform
        // operator -- falls back to their first membership.
        if ($society === null) {
            $society = $user->activeSocieties()->first();

            if ($society !== null) {
                $user->forceFill(['current_society_id' => $society->id])->saveQuietly();
            }
        }

        if ($society === null) {
            // Super admins legitimately operate outside any one society.
            if ($user->isSuperAdmin()) {
                return $next($request);
            }

            abort(403, 'Your account is not linked to a society yet. Please ask your committee for an invitation.');
        }

        // Guard against a stale current_society_id left behind by a removed
        // membership, which would otherwise expose another society's data.
        if (! $user->isSuperAdmin() && ! $user->belongsToSociety($society)) {
            abort(403, 'You no longer have access to this society.');
        }

        $this->context->set($society);

        // Roles are stored per society; without this the user appears to have
        // no permissions at all.
        setPermissionsTeamId($society->id);

        App::setLocale($society->locale ?: config('app.locale'));
        date_default_timezone_set($society->timezone ?: config('app.timezone'));

        config([
            'app.currency' => $society->currency ?: config('app.currency'),
        ]);

        view()->share('currentSociety', $society);

        return $next($request);
    }
}
