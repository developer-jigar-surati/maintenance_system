<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $invoice->invoice_number }}</title>
    <style>
        @page { margin: 28px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .header { border-bottom: 2px solid #4338ca; padding-bottom: 12px; margin-bottom: 16px; }
        .society { font-size: 17px; font-weight: bold; color: #312e81; }
        .muted { color: #6b7280; }
        .title { text-align: right; }
        .title h1 { font-size: 15px; margin: 0 0 2px; letter-spacing: 1px; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 0; vertical-align: top; }
        .label { color: #6b7280; width: 108px; }
        .lines { margin-top: 16px; }
        .lines th { background: #f3f4f6; text-align: left; padding: 7px 8px; font-size: 10px;
                    text-transform: uppercase; color: #4b5563; border-bottom: 1px solid #d1d5db; }
        .lines td { padding: 7px 8px; border-bottom: 1px solid #e5e7eb; }
        .right { text-align: right; }
        .totals { margin-top: 12px; width: 48%; margin-left: 52%; }
        .totals td { padding: 4px 8px; }
        .grand { border-top: 2px solid #312e81; font-weight: bold; font-size: 13px; color: #312e81; }
        .overdue { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
                   padding: 8px 12px; border-radius: 6px; margin-top: 14px; }
        .footer { margin-top: 24px; border-top: 1px solid #e5e7eb; padding-top: 10px;
                  font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="society">{{ $society->name }}</div>
                    <div class="muted">
                        {{ collect([$society->address_line1, $society->city, $society->state, $society->postal_code])->filter()->implode(', ') }}
                    </div>
                    @if ($society->gstin)<div class="muted">GSTIN {{ $society->gstin }}</div>@endif
                </td>
                <td class="title">
                    <h1>{{ $society->gst_enabled ? 'Tax Invoice' : 'Invoice' }}</h1>
                    <div class="muted">{{ $invoice->invoice_number }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="meta">
        <tr>
            <td class="label">Billed to</td>
            <td>
                <strong>{{ $billTo?->name ?? 'Unit owner' }}</strong><br>
                {{ $invoice->unit?->label }}
            </td>
            <td class="label">Invoice date</td>
            <td>{{ $invoice->issue_date->format('j F Y') }}</td>
        </tr>
        <tr>
            <td class="label">Period</td>
            <td>
                @if ($invoice->period_start)
                    {{ $invoice->period_start->format('j M Y') }} – {{ $invoice->period_end?->format('j M Y') }}
                @else
                    —
                @endif
            </td>
            <td class="label">Due date</td>
            <td><strong>{{ $invoice->due_date->format('j F Y') }}</strong></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Particulars</th>
                <th class="right">Qty</th>
                <th class="right">Rate</th>
                <th class="right">Amount</th>
                @if ($invoice->tax_total > 0)<th class="right">Tax</th>@endif
                <th class="right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>{{ $line->description }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->quantity, 2), '0'), '.') }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format((float) $line->rate, 4), '0'), '.') }}</td>
                    <td class="right">{{ \App\Support\Money::format((float) $line->amount) }}</td>
                    @if ($invoice->tax_total > 0)
                        <td class="right">{{ \App\Support\Money::format((float) $line->tax_amount) }}</td>
                    @endif
                    <td class="right">{{ \App\Support\Money::format((float) $line->line_total) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="muted">Subtotal</td>
            <td class="right">{{ \App\Support\Money::format((float) $invoice->subtotal) }}</td>
        </tr>
        @if ($invoice->tax_total > 0)
            <tr>
                <td class="muted">Tax</td>
                <td class="right">{{ \App\Support\Money::format((float) $invoice->tax_total) }}</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total payable</td>
            <td class="right">{{ \App\Support\Money::format((float) $invoice->total) }}</td>
        </tr>
        @if ($invoice->amount_paid > 0)
            <tr>
                <td class="muted">Paid</td>
                <td class="right">{{ \App\Support\Money::format((float) $invoice->amount_paid) }}</td>
            </tr>
            <tr>
                <td><strong>Balance due</strong></td>
                <td class="right"><strong>{{ \App\Support\Money::format((float) $invoice->balance) }}</strong></td>
            </tr>
        @endif
    </table>

    @if ((float) $invoice->arrears_amount > 0)
        <div class="overdue">
            <strong>Earlier dues outstanding:</strong>
            {{ \App\Support\Money::format((float) $invoice->arrears_amount) }}.
            Please clear these along with this bill.
        </div>
    @endif

    @if ($invoice->notes)
        <p class="muted" style="margin-top:14px">{{ $invoice->notes }}</p>
    @endif

    <div class="footer">
        Amount in words: {{ \App\Support\Money::inWords((float) $invoice->total) }}.<br>
        This is a computer-generated invoice and is valid without a signature.
    </div>
</body>
</html>
