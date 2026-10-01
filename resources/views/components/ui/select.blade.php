@props([
    'label' => null,
    'name' => null,
    'options' => [],
    'placeholder' => null,
    'hint' => null,
    'error' => null,
    'required' => false,
    /*
     * Whether to offer a search box. Left null it decides for itself from how
     * many options there are, which is right nearly always; pass true or false
     * to overrule it.
     */
    'searchable' => null,
])

@php
    $id = $attributes->get('id') ?? $name ?? 'field-'.\Illuminate\Support\Str::random(6);
    $error ??= $name ? ($errors->first($name) ?: null) : null;

    /*
     * Width belongs to the field, not to the control inside it.
     *
     * A caller writing class="w-auto min-w-36" means "this filter should be as
     * wide as it needs to be". That class used to land on the <select> while
     * the wrapper stayed w-full, so in a flex toolbar the field still took the
     * whole row. Sizing classes are moved out to the wrapper, and everything
     * else stays on the control.
     */
    $classes = collect(explode(' ', (string) $attributes->get('class', '')))->filter();
    $sizing = $classes->filter(fn ($c) => preg_match('/^(?:[a-z]+:)?(?:w|min-w|max-w|flex|basis|grow|shrink|col-span)-/', $c));
    $rest = $classes->reject(fn ($c) => $sizing->contains($c));

    $control = $attributes->except(['class', 'id', 'name', 'required', 'searchable']);
@endphp

<div @class(['min-w-0', $sizing->isEmpty() ? 'w-full' : $sizing->implode(' ')])>
    @if ($label)
        <label for="{{ $id }}-button" class="mb-1.5 block text-sm font-medium">
            {{ $label }}
            @if ($required)<span class="text-[var(--color-critical)]" aria-hidden="true">*</span>@endif
        </label>
    @endif

    {{--
        The native select still holds the value, so wire:model, plain <option>
        markup and a no-JavaScript form submit all keep working. It is hidden
        from sight and from screen readers; the button below is the control
        people actually meet. See resources/js/ui/select.js.
    --}}
    <div
        x-data="customSelect({
            searchable: {{ $searchable === null ? 'null' : ($searchable ? 'true' : 'false') }},
            placeholder: @js($placeholder ?? 'Select'),
        })"
        x-on:keydown.escape.stop="close()"
        x-on:click.outside="close(false)"
        class="relative"
    >
        <select
            x-ref="native"
            id="{{ $id }}"
            @if ($name) name="{{ $name }}" @endif
            @if ($required) required aria-required="true" @endif
            {{ $control }}
            class="sr-only"
            tabindex="-1"
            aria-hidden="true"
        >
            @if ($placeholder)<option value="">{{ $placeholder }}</option>@endif

            @if (trim($slot) !== '')
                {{ $slot }}
            @else
                @foreach ($options as $value => $text)
                    <option value="{{ $value }}">{{ $text }}</option>
                @endforeach
            @endif
        </select>

        <button
            type="button"
            x-ref="trigger"
            id="{{ $id }}-button"
            role="combobox"
            aria-haspopup="listbox"
            :aria-expanded="open"
            :aria-controls="$id('select') + '-list'"
            :aria-activedescendant="open ? activeId : null"
            @if ($label) aria-labelledby="{{ $id }}-button" @endif
            @if ($attributes->get('aria-label')) aria-label="{{ $attributes->get('aria-label') }}" @endif
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            :disabled="$refs.native?.disabled"
            x-on:click="toggle()"
            x-on:keydown="onTriggerKey($event)"
            @class([
                'flex min-h-11 w-full items-center justify-between gap-2 rounded-xl border surface-raised',
                'py-2.5 pl-3.5 pr-3 text-left text-sm transition-colors',
                'disabled:cursor-not-allowed disabled:opacity-60',
                $error ? 'border-[var(--color-critical)]' : 'border-subtle hover:border-strong',
                $rest->implode(' '),
            ])
        >
            <span class="truncate" :class="isPlaceholder ? 'text-muted' : 'text-primary'" x-text="label"></span>
            <x-ui.icon name="chevron-down" class="size-4 shrink-0 text-muted transition-transform"
                x-bind:class="open && 'rotate-180'" />
        </button>

        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 -translate-y-1"
            x-transition:enter-end="opacity-100 translate-y-0"
            x-on:keydown="onListKey($event)"
            class="absolute z-50 mt-1 w-full min-w-48 overflow-hidden rounded-xl border border-subtle surface-raised shadow-[var(--shadow-overlay)]"
            style="display: none"
        >
            <template x-if="usesSearch">
                <div class="border-b border-subtle p-2">
                    <input
                        x-ref="search"
                        type="text"
                        x-model="query"
                        x-on:input="onSearch()"
                        placeholder="Search"
                        aria-label="Search the options"
                        class="w-full rounded-lg border border-subtle surface-inset px-3 py-2 text-sm text-primary placeholder:text-muted"
                    >
                </div>
            </template>

            <ul
                x-ref="list"
                role="listbox"
                :id="$id('select') + '-list'"
                @if ($label) aria-label="{{ $label }}" @endif
                class="scrollbar-slim max-h-60 overflow-y-auto p-1"
            >
                <template x-for="(option, i) in visible" :key="option.value + ':' + option.index">
                    <li
                        role="option"
                        :id="optionId(option)"
                        :aria-selected="option.value === value"
                        :aria-disabled="option.disabled"
                        :data-active="i === activeIndex"
                        x-on:click="choose(option)"
                        x-on:mousemove="activeIndex = i"
                        class="flex min-h-10 cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-sm"
                        :class="{
                            'surface-inset text-primary': i === activeIndex && option.value !== value,
                            'accent-soft-bg accent-text font-medium': option.value === value,
                            'text-secondary': i !== activeIndex && option.value !== value,
                            'cursor-not-allowed opacity-50': option.disabled,
                        }"
                    >
                        <span class="truncate" x-text="option.label || '{{ $placeholder ?? 'Any' }}'"></span>
                        <template x-if="option.value === value">
                            <x-ui.icon name="check" class="size-4 shrink-0" />
                        </template>
                    </li>
                </template>

                <li x-show="visible.length === 0" class="px-3 py-6 text-center text-sm text-muted">
                    Nothing matches that.
                </li>
            </ul>
        </div>
    </div>

    @if ($hint && ! $error)<p class="mt-1.5 text-xs text-muted">{{ $hint }}</p>@endif
    @if ($error)<p id="{{ $id }}-error" class="mt-1.5 text-xs font-medium text-[var(--color-critical)]">{{ $error }}</p>@endif
</div>
