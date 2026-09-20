@props(['amount', 'currency' => null, 'signed' => false, 'tone' => null])

@php
    $value = (float) $amount;
    $symbol = $currency ?? config('app.currency_symbol', '₹');

    /* Indian digit grouping (1,23,456.00) rather than the Western grouping,
       because that is what residents here read on every other bill. */
    $formatted = \App\Support\Money::format($value, $symbol);

    $colour = match ($tone) {
        'auto' => $value < 0 ? 'text-[var(--color-positive)]' : ($value > 0 ? 'text-[var(--color-critical)]' : ''),
        'positive' => 'text-[var(--color-positive)]',
        'critical' => 'text-[var(--color-critical)]',
        default => '',
    };
@endphp

<span {{ $attributes->merge(['class' => 'numeric whitespace-nowrap '.$colour]) }}>
    @if ($signed && $value > 0)+@endif{{ $formatted }}
</span>
