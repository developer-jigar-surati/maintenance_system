/**
 * The dropdown.
 *
 * A native select can be styled shut but not open: the popup is drawn by the
 * operating system, so it arrives as a plain grey list in the middle of a
 * dark, rounded interface, and there is nothing CSS can do about it.
 *
 * So the list is ours, and the native select stays underneath as the thing
 * that actually holds the value. That matters for three reasons: every
 * existing wire:model keeps working untouched, the options can still be
 * written as plain <option> markup, and a form submits correctly even if this
 * script never runs.
 *
 * The visible control is the accessible one, following the combobox pattern:
 * it owns the label, the expanded state and the active option, while the
 * native select is taken out of the accessibility tree so nothing is
 * announced twice.
 */

/** Below this many options a search box is more in the way than help. */
const SEARCH_THRESHOLD = 8;

/** How long a run of keystrokes counts as one word when jumping to an option. */
const TYPE_AHEAD_MS = 700;

export default function customSelect(config = {}) {
    return {
        open: false,
        options: [],
        activeIndex: -1,
        query: '',
        typed: '',
        typedAt: 0,
        searchable: config.searchable ?? null,
        placeholder: config.placeholder ?? 'Select',

        init() {
            this.read();

            // Livewire can replace the options underneath us: a category list
            // that depends on the society, a unit list that depends on the
            // block. Watching the native select keeps the two in step.
            new MutationObserver(() => this.read()).observe(this.$refs.native, {
                childList: true,
                subtree: true,
                characterData: true,
            });

            // And it can change the value without touching the markup.
            this.$refs.native.addEventListener('change', () => this.read());
        },

        /** Take the options and the current value from the native select. */
        read() {
            this.options = [...this.$refs.native.options].map((option, index) => ({
                index,
                value: option.value,
                label: option.textContent.trim(),
                disabled: option.disabled,
            }));
        },

        get value() {
            return this.$refs.native?.value ?? '';
        },

        get selected() {
            return this.options.find((option) => option.value === this.value) ?? null;
        },

        /** What the closed control reads. */
        get label() {
            const selected = this.selected;

            // An empty value with a label of its own is a real choice, such as
            // "All statuses", not an absence.
            if (selected && (selected.value !== '' || selected.label !== '')) {
                return selected.label;
            }

            return this.placeholder;
        },

        get isPlaceholder() {
            return this.selected === null || this.selected.label === '';
        },

        get usesSearch() {
            return this.searchable ?? this.options.length >= SEARCH_THRESHOLD;
        },

        get visible() {
            if (!this.usesSearch || this.query.trim() === '') {
                return this.options;
            }

            const needle = this.query.trim().toLowerCase();

            return this.options.filter((option) => option.label.toLowerCase().includes(needle));
        },

        optionId(option) {
            return `${this.$id('select')}-option-${option.index}`;
        },

        get activeId() {
            const active = this.visible[this.activeIndex];

            return active ? this.optionId(active) : null;
        },

        // --- opening and closing ------------------------------------------

        toggle() {
            this.open ? this.close() : this.show();
        },

        show() {
            if (this.$refs.native.disabled) {
                return;
            }

            this.open = true;
            this.query = '';

            // Open on whatever is currently chosen, so the arrow keys carry on
            // from there rather than from the top of the list.
            const selected = this.selected;
            this.activeIndex = selected ? this.visible.findIndex((o) => o.value === selected.value) : 0;

            this.$nextTick(() => {
                if (this.usesSearch) {
                    this.$refs.search?.focus();
                }

                this.scrollToActive();
            });
        },

        close(refocus = true) {
            if (!this.open) {
                return;
            }

            this.open = false;
            this.query = '';

            if (refocus) {
                this.$refs.trigger?.focus();
            }
        },

        // --- choosing -------------------------------------------------------

        choose(option) {
            if (!option || option.disabled) {
                return;
            }

            const native = this.$refs.native;

            native.value = option.value;

            // Both events, because Livewire listens for input and plenty of
            // other code listens for change.
            native.dispatchEvent(new Event('input', { bubbles: true }));
            native.dispatchEvent(new Event('change', { bubbles: true }));

            this.close();
        },

        // --- keyboard -------------------------------------------------------

        onTriggerKey(event) {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(event.key)) {
                event.preventDefault();
                this.show();

                return;
            }

            // Typing a letter on the closed control jumps to a match, which is
            // what a native select does and what people expect.
            if (event.key.length === 1 && !event.metaKey && !event.ctrlKey && !event.altKey) {
                const match = this.matchTyped(event.key);

                if (match) {
                    event.preventDefault();
                    this.choose(match);
                }
            }
        },

        onListKey(event) {
            switch (event.key) {
                case 'ArrowDown':
                    event.preventDefault();
                    this.moveBy(1);
                    break;
                case 'ArrowUp':
                    event.preventDefault();
                    this.moveBy(-1);
                    break;
                case 'Home':
                    event.preventDefault();
                    this.moveTo(0);
                    break;
                case 'End':
                    event.preventDefault();
                    this.moveTo(this.visible.length - 1);
                    break;
                case 'Enter':
                    event.preventDefault();
                    this.choose(this.visible[this.activeIndex]);
                    break;
                case 'Escape':
                    event.preventDefault();
                    this.close();
                    break;
                case 'Tab':
                    // Leaving the control commits nothing and gets out of the way.
                    this.close(false);
                    break;
                default:
                    if (!this.usesSearch && event.key.length === 1) {
                        const match = this.matchTyped(event.key);

                        if (match) {
                            event.preventDefault();
                            this.moveTo(this.visible.indexOf(match));
                        }
                    }
            }
        },

        /** Type ahead: a run of letters within a moment counts as one word. */
        matchTyped(key) {
            const now = Date.now();

            this.typed = now - this.typedAt > TYPE_AHEAD_MS ? key : this.typed + key;
            this.typedAt = now;

            const needle = this.typed.toLowerCase();

            return this.options.find(
                (option) => !option.disabled && option.label.toLowerCase().startsWith(needle),
            );
        },

        moveBy(step) {
            const count = this.visible.length;

            if (count === 0) {
                return;
            }

            let next = this.activeIndex;

            // Step past anything that cannot be chosen, and wrap round.
            for (let i = 0; i < count; i++) {
                next = (next + step + count) % count;

                if (!this.visible[next].disabled) {
                    break;
                }
            }

            this.moveTo(next);
        },

        moveTo(index) {
            this.activeIndex = index;
            this.scrollToActive();
        },

        scrollToActive() {
            this.$nextTick(() => {
                this.$refs.list
                    ?.querySelector('[data-active="true"]')
                    ?.scrollIntoView({ block: 'nearest' });
            });
        },

        onSearch() {
            // The first match becomes active, so Enter picks the obvious one.
            this.activeIndex = this.visible.findIndex((option) => !option.disabled);
        },
    };
}
