<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Receipt;
use App\Support\QrCode;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ReceiptPdfController extends Controller
{
    /** Renders the digital rasid. */
    public function show(Request $request, Receipt $receipt): Response
    {
        $user = $request->user();

        // A resident may download their own unit's receipt; management any.
        if (! $user->isSuperAdmin() && ! $user->can(Permission::PAYMENT_RECORD)) {
            abort_unless($user->units()->whereKey($receipt->unit_id)->exists(), 403);
        }

        $receipt->load(['payment.allocations.invoice', 'unit.block', 'society', 'issuedBy']);

        $pdf = Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'society' => $receipt->society,
            'qr' => QrCode::svg($receipt->verificationUrl(), 110),
        ])->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $receipt->receipt_number).'.pdf');
    }

    /**
     * Public verification. Reached by scanning the QR on a printed receipt, so
     * it deliberately exposes only enough to confirm the document is genuine.
     */
    public function verify(string $uuid): mixed
    {
        $receipt = Receipt::withoutGlobalScopes()
            ->with(['society', 'unit.block'])
            ->where('uuid', $uuid)
            ->first();

        return response()->view('pdf.receipt-verify', ['receipt' => $receipt], $receipt ? 200 : 404);
    }
}
