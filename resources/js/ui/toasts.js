/**
 * The toast stack.
 *
 * Registered as an Alpine data component rather than written inline, because
 * the timing rules are the whole point and they do not belong in an attribute.
 *
 * Three rules:
 *   - How long a toast lives depends on how much there is to read, with a
 *     floor and a ceiling. A fixed five seconds cuts off a long sentence and
 *     leaves "Saved." sitting there.
 *   - It pauses while the pointer is over the stack or focus is inside it, so
 *     it cannot disappear while it is being read or reached for.
 *   - A failure stays until it is dismissed. Something went wrong is not a
 *     thing to miss because you looked away.
 */

const TICK_MS = 120;
const MIN_LIFE_MS = 3200;
const MAX_LIFE_MS = 12000;
const WORDS_PER_MINUTE = 190;
const MAX_VISIBLE = 4;

/*
 * One glyph per tone, as path data rather than markup.
 *
 * An <svg> cannot hold a <template>: the parser does not create a template
 * element inside the SVG namespace, so Alpine's x-if finds no content to
 * clone and throws. Binding the path data avoids the question entirely.
 */
const ICONS = {
    positive: 'M4.5 12.75l6 6 9-13.5',
    critical: 'M6 18L18 6M6 6l12 12',
    caution: 'M12 9v4m0 4h.01M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z',
    info: 'M12 16v-4m0-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z',
};

/** Roughly how long the text takes to read, clamped to something sensible. */
export function lifeFor(text = '') {
    const words = String(text).trim().split(/\s+/).filter(Boolean).length;
    const reading = (words / WORDS_PER_MINUTE) * 60_000;

    // A second and a half of noticing it at all, before any reading.
    return Math.min(MAX_LIFE_MS, Math.max(MIN_LIFE_MS, 1500 + reading));
}

export default function toastStack() {
    return {
        toasts: [],
        nextId: 1,

        iconFor(tone) {
            return ICONS[tone] ?? ICONS.info;
        },

        push(detail = {}) {
            const message = detail.message ?? detail.text ?? '';

            if (!message) {
                return;
            }

            const tone = ['positive', 'critical', 'caution', 'info'].includes(detail.tone)
                ? detail.tone
                : 'info';

            // A failure waits to be dismissed rather than timing out.
            const sticky = detail.sticky ?? tone === 'critical';
            const life = detail.duration ?? lifeFor(`${message} ${detail.detail ?? ''}`);

            const toast = {
                id: this.nextId++,
                message,
                detail: detail.detail ?? null,
                tone,
                sticky,
                life,
                elapsed: 0,
                remaining: 100,
                paused: false,
                visible: true,
                timer: null,
            };

            this.toasts.push(toast);
            this.announce(toast);

            // Older messages give way rather than filling the screen.
            while (this.toasts.length > MAX_VISIBLE) {
                this.dismiss(this.toasts[0]);
            }

            if (!sticky) {
                this.run(toast);
            }
        },

        run(toast) {
            toast.timer = setInterval(() => {
                if (toast.paused) {
                    return;
                }

                toast.elapsed += TICK_MS;
                toast.remaining = Math.max(0, 100 - (toast.elapsed / toast.life) * 100);

                if (toast.elapsed >= toast.life) {
                    this.dismiss(toast);
                }
            }, TICK_MS);
        },

        hold(toast) {
            toast.paused = true;
        },

        release(toast) {
            toast.paused = false;
        },

        dismiss(toast) {
            if (toast.timer) {
                clearInterval(toast.timer);
                toast.timer = null;
            }

            toast.visible = false;

            // Left in place for the length of the leave transition, then cut.
            setTimeout(() => {
                this.toasts = this.toasts.filter((t) => t.id !== toast.id);
            }, 200);
        },

        /**
         * Screen readers are told separately from the visual stack, because a
         * live region that also animates announces itself twice.
         */
        announce(toast) {
            const region = toast.tone === 'critical' ? this.$refs.urgentRegion : this.$refs.politeRegion;

            if (!region) {
                return;
            }

            const line = document.createElement('p');
            line.textContent = toast.detail ? `${toast.message}. ${toast.detail}` : toast.message;
            region.appendChild(line);

            setTimeout(() => line.remove(), 1000);
        },
    };
}
