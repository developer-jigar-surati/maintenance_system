<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class InvoicePdfController extends Controller
{
    public function show(Request $request, Invoice $invoice): Response
    {
        $user = $request->user();

        if (! $user->isSuperAdmin() && ! $user->can(Permission::BILLING_MANAGE) && ! $user->can(Permission::PAYMENT_RECORD)) {
            abort_unless($user->units()->whereKey($invoice->unit_id)->exists(), 403);
        }

        $invoice->load(['lines.chargeHead', 'unit.block', 'unit.residents.user', 'society']);

        $pdf = Pdf::loadView('pdf.invoice', [
            'invoice' => $invoice,
            'society' => $invoice->society,
            'billTo' => $invoice->unit?->billingContact()?->user,
        ])->setPaper('a4');

        return $pdf->stream(str_replace('/', '-', $invoice->invoice_number).'.pdf');
    }
}
