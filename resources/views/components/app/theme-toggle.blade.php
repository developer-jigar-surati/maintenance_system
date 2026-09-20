{{--
    Cycles light -> dark -> system. The label is announced on change so screen
    reader users hear which theme is now active.
--}}
<div
    x-data="{
        preference: 'system',
        init() { this.preference = window.getThemePreference(); },
        cycle() {
            const order = ['light', 'dark', 'system'];
            this.preference = order[(order.indexOf(this.preference) + 1) % order.length];
            window.setThemePreference(this.preference);
        },
    }"
>
    <button
        type="button"
        @click="cycle()"
        class="rounded-xl border border-subtle p-2 text-secondary hover:surface-inset hover:text-primary"
        :aria-label="'Theme: ' + preference + '. Activate to change.'"
    >
        <x-ui.icon name="sun" class="size-5" x-show="preference === 'light'" />
        <x-ui.icon name="moon" class="size-5" x-show="preference === 'dark'" style="display:none" />
        <x-ui.icon name="cog" class="size-5" x-show="preference === 'system'" style="display:none" />
    </button>
    <span class="sr-only" aria-live="polite" x-text="'Theme set to ' + preference"></span>
</div>
