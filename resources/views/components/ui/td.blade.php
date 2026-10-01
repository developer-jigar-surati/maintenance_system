@props([
    'label' => null,
    'align' => 'left',
    /* Cells that only repeat what the mobile card already shows, e.g. the unit
       number used as the card's own heading. */
    'hideOnMobile' => false,
    'primary' => false,
])

@php
    $alignment = match ($align) {
        'right' => 'md:text-right',
        'center' => 'md:text-center',
        default => 'md:text-left',
    };
@endphp

<td
    @class([
        'md:table-cell md:px-4 md:py-3 md:align-middle',
        'hidden md:table-cell' => $hideOnMobile,
        'flex items-baseline justify-between gap-3 py-1.5 md:py-3' => ! $hideOnMobile,
        $alignment,
    ])
>
    @unless ($hideOnMobile)
        @if ($label)
            {{-- Caption shown only in the stacked mobile layout. --}}
            <span class="shrink-0 text-xs font-medium text-muted md:hidden" aria-hidden="true">{{ $label }}</span>
        @endif
    @endunless

    {{--
        On mobile the value sits opposite its label, so it is right-aligned.
        From `md` up it must defer to the column's own alignment on the <td>.
        Written as an arbitrary property because Tailwind's `text-inherit`
        is a colour utility, not an alignment one, and silently left every
        column right-aligned.
    --}}
    <span @class([
        'min-w-0 md:block',
        'text-right md:[text-align:inherit]' => ! $hideOnMobile,
        'font-semibold' => $primary,
    ])>{{ $slot }}</span>
</td>
