@php $items = \App\Support\Navigation::primary(); @endphp

@if ($items->isNotEmpty())
    {{-- Phone-only tab bar, padded for the home indicator on notched devices. --}}
    <nav
        class="fixed inset-x-0 bottom-0 z-20 border-t border-subtle surface-raised/95 backdrop-blur lg:hidden"
        style="padding-bottom: env(safe-area-inset-bottom)"
        aria-label="Primary"
    >
        <ul class="grid" style="grid-template-columns: repeat({{ $items->count() }}, minmax(0, 1fr))">
            @foreach ($items as $item)
                <li>
                    <a
                        href="{{ route($item['route']) }}"
                        @if ($item['active']) aria-current="page" @endif
                        class="flex flex-col items-center gap-1 px-2 py-2.5 text-[0.6875rem] font-medium
                            {{ $item['active'] ? 'accent-text' : 'text-muted' }}"
                    >
                        <x-ui.icon :name="$item['icon']" class="size-5" />
                        {{ $item['label'] }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>
    {{-- Spacer so the tab bar never covers the last row of content. --}}
    <div class="h-16 lg:hidden" aria-hidden="true"></div>
@endif
