@props([
    'headers' => [],
    'empty' => 'Nothing to show yet.',
    'emptyIcon' => 'folder',
    'isEmpty' => false,
    'caption' => null,
])

{{--
    One markup, two presentations.

    From `md` up this is an ordinary semantic table. Below that the header row
    is hidden and each row becomes a stacked card, with every cell captioned by
    its own `label` attribute -- which is what makes a twelve-column financial
    table readable on a phone without shipping a second template.
--}}
<div {{ $attributes->merge(['class' => 'surface-card overflow-hidden']) }}>
    @isset($toolbar)
        <div class="flex flex-wrap items-center gap-3 border-b border-subtle px-4 py-3 sm:px-5">
            {{ $toolbar }}
        </div>
    @endisset

    @if ($isEmpty)
        <x-ui.empty-state :icon="$emptyIcon" :title="$empty">
            @isset($emptyAction){{ $emptyAction }}@endisset
        </x-ui.empty-state>
    @else
        <div class="scrollbar-slim md:overflow-x-auto">
            <table class="w-full border-collapse text-sm">
                @if ($caption)
                    <caption class="sr-only">{{ $caption }}</caption>
                @endif

                <thead class="hidden md:table-header-group">
                    <tr class="surface-sunken text-left">
                        @foreach ($headers as $header)
                            @php
                                $label = is_array($header) ? ($header['label'] ?? '') : $header;
                                $align = is_array($header) ? ($header['align'] ?? 'left') : 'left';
                            @endphp
                            <th
                                scope="col"
                                class="whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted
                                    {{ $align === 'right' ? 'text-right' : ($align === 'center' ? 'text-center' : 'text-left') }}"
                            >
                                @if ($label === '')
                                    <span class="sr-only">Actions</span>
                                @else
                                    {{ $label }}
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>

                <tbody class="block md:table-row-group md:divide-y md:divide-[var(--border-subtle)]">
                    {{ $slot }}
                </tbody>

                @isset($foot)
                    <tfoot class="block border-t-2 border-strong surface-sunken md:table-footer-group">
                        {{ $foot }}
                    </tfoot>
                @endisset
            </table>
        </div>
    @endif

    @isset($footer)
        <div class="border-t border-subtle px-4 py-3 sm:px-5">{{ $footer }}</div>
    @endisset
</div>
