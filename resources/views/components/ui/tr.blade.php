@props(['href' => null])

<tr
    {{ $attributes->merge([
        'class' => 'block border-b border-subtle p-4 last:border-b-0 md:table-row md:border-0 md:p-0 '
            .'transition-colors hover:surface-sunken',
    ]) }}
    @if ($href)
        {{-- data-clickable-row carries the pointer cursor; see app.css. --}}
        data-clickable-row
        onclick="if (! event.target.closest('a,button,input,label,select')) window.location = '{{ $href }}'"
    @endif
>
    {{ $slot }}
</tr>
