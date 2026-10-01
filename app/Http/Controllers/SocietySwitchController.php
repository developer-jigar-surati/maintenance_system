<?php

namespace App\Http\Controllers;

use App\Models\Society;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Switches which society the user is acting in.
 *
 * Membership is re-checked here rather than trusting the posted id, since the
 * whole tenancy boundary rests on current_society_id being one the user holds.
 */
class SocietySwitchController extends Controller
{
    public function __invoke(Request $request, Society $society): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user->isSuperAdmin() || $user->belongsToSociety($society), 403);

        $user->switchTo($society);

        return redirect()
            ->route('dashboard')
            ->with('status', "Now viewing {$society->name}.");
    }
}
