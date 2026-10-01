<?php

namespace App\Http\Controllers;

use App\Models\GatePass;
use Illuminate\Http\Response;

/**
 * Scanned by a guard at the gate. Unauthenticated by design: the token in the
 * QR is the credential, and the page shows only what the guard must check.
 */
class GatePassVerificationController extends Controller
{
    public function show(string $token): Response
    {
        $pass = GatePass::withoutGlobalScopes()
            ->with(['unit.block', 'society', 'requestedBy'])
            ->where('qr_token', $token)
            ->first();

        return response()->view('pdf.gate-pass-verify', ['pass' => $pass], $pass ? 200 : 404);
    }
}
