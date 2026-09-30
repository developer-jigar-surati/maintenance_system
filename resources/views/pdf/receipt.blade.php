<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $receipt->receipt_number }}</title>
    <style>
        /* dompdf renders a fixed subset of CSS, so this stylesheet is
           deliberately plain: no flexbox, no custom properties. */
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .header { border-bottom: 2px solid #4338ca; padding-bottom: 12px; margin-bottom: 18px; }
        .society { font-size: 17px; font-weight: bold; color: #312e81; }
        .muted { color: #6b7280; }
        .title { text-align: right; }
        .title h1 { font-size: 15px; margin: 0 0 2px; letter-spacing: 1px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .label { color: #6b7280; width: 118px; }
        .amount-box { background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 6px;
                      padding: 12px 14px; margin: 16px 0; }
        .amount { font-size: 22px; font-weight: bold; color: #312e81; }
        .words { font-style: italic; color: #4b5563; font-size: 10px; margin-top: 3px; }
        .lines th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 10px;
                    text-transform: uppercase; color: #4b5563; border-bottom: 1px solid #d1d5db; }
        .lines td { padding: 6px 8px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .footer { margin-top: 26px; border-top: 1px solid #e5e7eb; padding-top: 10px;
                  font-size: 9px; color: #6b7280; }
        .cancelled { color: #b91c1c; border: 2px solid #b91c1c; padding: 4px 10px;
                     font-weight: bold; display: inline-block; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="society">{{ $society->name }}</div>
                    <div class="muted">
                        {{ collect([$society->address_line1, $society->address_line2, $society->city, $society->state, $society->postal_code])->filter()->implode(', ') }}
                    </div>
                    @if ($society->registration_number)
                        <div class="muted">Reg. No. {{ $society->registration_number }}</div>
                    @endif
                    @if ($society->gstin)
                        <div class="muted">GSTIN {{ $society->gstin }}</div>
                    @endif
                </td>
                <td class="title">
                    <h1>Receipt</h1>
                    <div class="muted">{{ $receipt->receipt_number }}</div>
                    @if ($receipt->is_cancelled)
                        <div style="margin-top:6px"><span class="cancelled">CANCELLED</span></div>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Received from</td>
            <td><strong>{{ $receipt->received_from }}</strong></td>
            <td class="label">Date</td>
            <td>{{ $receipt->issued_on->format('j F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Unit</td>
            <td>{{ $receipt->unit?->label ?? '–' }}</td>
            <td class="label">Mode</td>
            <td>{{ $receipt->payment?->methodLabel() }}</td>
        </tr>
        @if ($receipt->payment?->reference_number)
            <tr>
                <td class="label">Reference</td>
                <td colspan="3">{{ $receipt->payment->reference_number }}</td>
            </tr>
        @endif
    </table>

    <div class="amount-box">
        <table>
            <tr>
                <td>
                    <div class="muted" style="font-size:9px; text-transform:uppercase; letter-spacing:.5px">Amount received</div>
                    <div class="amount">{{ \App\Support\Money::format((float) $receipt->amount) }}</div>
                    <div class="words">{{ \App\Support\Money::inWords((float) $receipt->amount) }}</div>
                </td>
                <td class="right" style="width:120px">{!! $qr !!}</td>
            </tr>
        </table>
    </div>

    @if ($receipt->payment?->allocations?->isNotEmpty())
        <table class="lines">
            <thead>
                <tr>
                    <th>Applied against</th>
                    <th>Period</th>
                    <th class="right">Amount</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($receipt->payment->allocations as $allocation)
                    <tr>
                        <td>{{ $allocation->invoice?->invoice_number ?? '–' }}</td>
                        <td>{{ $allocation->invoice?->period_start?->format('M Y') ?? '–' }}</td>
                        <td class="right">{{ \App\Support\Money::format((float) $allocation->amount) }}</td>
                    </tr>
                @endforeach
                @if ((float) $receipt->payment->unallocated_amount > 0)
                    <tr>
                        <td colspan="2"><em>Retained as advance</em></td>
                        <td class="right">{{ \App\Support\Money::format((float) $receipt->payment->unallocated_amount) }}</td>
                    </tr>
                @endif
            </tbody>
        </table>
    @else
        <p class="muted">{{ $receipt->towards }}</p>
    @endif

    <div class="footer">
        <table>
            <tr>
                <td>
                    This is a computer-generated receipt and is valid without a signature.<br>
                    Scan the code above, or visit {{ $receipt->verificationUrl() }}, to verify it.
                </td>
                <td class="right">
                    @if ($receipt->issuedBy)
                        Issued by {{ $receipt->issuedBy->name }}<br>
                    @endif
                    {{ now()->format('j M Y, g:i A') }}
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
