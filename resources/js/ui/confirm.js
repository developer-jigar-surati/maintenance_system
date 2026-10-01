/**
 * Confirmation before something destructive.
 *
 * The browser's own confirm() is a modal that steals the whole window, cannot
 * be styled, cannot say what will actually be lost, and on some platforms
 * offers a "prevent this page from creating more dialogs" checkbox that
 * silently disables every later confirmation. It is the wrong tool for
 * "delete this, it cannot be undone".
 *
 * A control opts in by carrying data-confirm. The click is caught in the
 * capture phase, before Livewire or Alpine see it, and only released once the
 * dialog comes back with a yes.
 *
 *   <button wire:click="destroy(4)"
 *           data-confirm="Delete this charge head?"
 *           data-confirm-detail="Bills already raised keep their lines."
 *           data-confirm-action="Delete it"
 *           data-confirm-tone="danger">Delete</button>
 */

const RELEASED = 'data-confirm-released';

function detailsFrom(el) {
    return {
        title: el.getAttribute('data-confirm') || 'Are you sure?',
        detail: el.getAttribute('data-confirm-detail') || null,
        confirmLabel: el.getAttribute('data-confirm-action') || 'Confirm',
        cancelLabel: el.getAttribute('data-confirm-cancel') || 'Cancel',
        tone: el.getAttribute('data-confirm-tone') || 'danger',
    };
}

export function installConfirmInterceptor() {
    document.addEventListener(
        'click',
        (event) => {
            const el = event.target.closest('[data-confirm]');

            if (!el) {
                return;
            }

            // The second click, the one we fired ourselves after a yes.
            if (el.hasAttribute(RELEASED)) {
                el.removeAttribute(RELEASED);

                return;
            }

            // Nothing else gets to see this click until the question is answered.
            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            window.dispatchEvent(
                new CustomEvent('confirm-request', {
                    detail: {
                        ...detailsFrom(el),
                        onConfirm() {
                            el.setAttribute(RELEASED, 'true');
                            el.click();
                        },
                    },
                }),
            );
        },
        true,
    );

    // A form can be destructive too: same rules, on submit.
    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!form.matches?.('[data-confirm]') || form.hasAttribute(RELEASED)) {
                form.removeAttribute?.(RELEASED);

                return;
            }

            event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            window.dispatchEvent(
                new CustomEvent('confirm-request', {
                    detail: {
                        ...detailsFrom(form),
                        onConfirm() {
                            form.setAttribute(RELEASED, 'true');
                            form.requestSubmit();
                        },
                    },
                }),
            );
        },
        true,
    );
}

/** The dialog itself. One instance, in the layout. */
export default function confirmDialog() {
    return {
        open: false,
        title: '',
        detail: null,
        confirmLabel: 'Confirm',
        cancelLabel: 'Cancel',
        tone: 'danger',
        pending: null,
        returnFocusTo: null,

        show(detail) {
            this.title = detail.title;
            this.detail = detail.detail;
            this.confirmLabel = detail.confirmLabel;
            this.cancelLabel = detail.cancelLabel;
            this.tone = detail.tone;
            this.pending = detail.onConfirm;

            // Where to put focus back, so a keyboard user is not dropped at
            // the top of the document after answering.
            this.returnFocusTo = document.activeElement;
            this.open = true;

            this.$nextTick(() => this.$refs.cancel?.focus());
        },

        confirm() {
            const run = this.pending;

            this.close();

            // After the dialog is gone, so the action's own toast is not
            // announced underneath a dialog that is still closing.
            setTimeout(() => run?.(), 0);
        },

        close() {
            this.open = false;
            this.pending = null;

            const target = this.returnFocusTo;
            this.returnFocusTo = null;

            if (target?.isConnected) {
                setTimeout(() => target.focus?.(), 0);
            }
        },
    };
}
