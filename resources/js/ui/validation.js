/**
 * Validation in the browser.
 *
 * This exists to shorten the loop, not to replace the server. Every rule here
 * is also enforced server side, because anything a browser checks can be
 * turned off. What it buys is that a typo is caught while the field is still
 * under the cursor rather than after a round trip that scrolls the page and
 * loses the user's place.
 *
 * Rules come from the field's own HTML, so there is one definition rather than
 * two that drift: required, type, minlength, maxlength, min, max, step,
 * pattern. Anything beyond that is declared with data attributes.
 *
 * Messages are written out rather than taken from the browser, because the
 * browser's are vague ("Please match the requested format") and differ between
 * Chrome, Firefox and Safari.
 */

const FIELDS = 'input:not([type=hidden]):not([type=submit]):not([type=button]), select, textarea';

const LABELS = new WeakMap();

/** What to call a field in a message, preferring its visible label. */
function labelFor(field) {
    if (LABELS.has(field)) {
        return LABELS.get(field);
    }

    const byFor = field.id ? document.querySelector(`label[for="${CSS.escape(field.id)}"]`) : null;
    const wrapping = field.closest('label');
    const text = (byFor || wrapping)?.textContent?.replace(/\*/g, '').trim();
    const name = text || field.getAttribute('aria-label') || field.getAttribute('placeholder') || 'This field';

    LABELS.set(field, name);

    return name;
}

function isEmpty(field) {
    if (field.type === 'checkbox' || field.type === 'radio') {
        return !field.checked;
    }

    return String(field.value ?? '').trim() === '';
}

/*
 * Deliberately permissive. The only way to know an address works is to send to
 * it, so this rejects what cannot possibly be right rather than trying to
 * enforce RFC 5322.
 */
const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;

/** Indian mobile numbers, with or without country code and separators. */
const PHONE = /^(?:\+?91[\s-]?)?[6-9]\d{9}$/;

function ruleMessage(field) {
    const label = labelFor(field);
    const value = String(field.value ?? '').trim();

    if (field.hasAttribute('required') && isEmpty(field)) {
        if (field.tagName === 'SELECT') {
            return `Choose ${label.toLowerCase()}.`;
        }

        // A label that is already a question reads badly with anything
        // appended to it: "What is the problem? is needed."
        if (label.endsWith('?')) {
            return 'Please answer this.';
        }

        return `${label} is needed.`;
    }

    // Everything past here only applies once something has been typed.
    if (value === '') {
        return null;
    }

    if (field.type === 'email' && !EMAIL.test(value)) {
        return 'That does not look like an email address.';
    }

    if (field.dataset.rule === 'phone' && !PHONE.test(value.replace(/[\s-]/g, ''))) {
        return 'Enter a 10 digit mobile number.';
    }

    const min = field.getAttribute('minlength');

    if (min && value.length < Number(min)) {
        return `${label} needs at least ${min} characters. It has ${value.length}.`;
    }

    const max = field.getAttribute('maxlength');

    if (max && value.length > Number(max)) {
        return `${label} can be at most ${max} characters. It has ${value.length}.`;
    }

    if (field.type === 'number' || field.type === 'range') {
        if (Number.isNaN(Number(value))) {
            return `${label} has to be a number.`;
        }

        const low = field.getAttribute('min');
        const high = field.getAttribute('max');

        if (low !== null && Number(value) < Number(low)) {
            return `${label} cannot be less than ${low}.`;
        }

        if (high !== null && Number(value) > Number(high)) {
            return `${label} cannot be more than ${high}.`;
        }
    }

    if (field.type === 'date' || field.type === 'datetime-local') {
        const low = field.getAttribute('min');
        const high = field.getAttribute('max');

        if (low && value < low) {
            return `${label} cannot be before ${low}.`;
        }

        if (high && value > high) {
            return `${label} cannot be after ${high}.`;
        }
    }

    const pattern = field.getAttribute('pattern');

    if (pattern && !new RegExp(`^(?:${pattern})$`).test(value)) {
        return field.dataset.patternMessage || `${label} is not in the right format.`;
    }

    // Two fields that have to agree, such as a password and its confirmation.
    const matches = field.dataset.match;

    if (matches) {
        const other = document.querySelector(matches);

        if (other && other.value !== field.value) {
            return `${label} does not match ${labelFor(other).toLowerCase()}.`;
        }
    }

    return null;
}

/** The element a message is written into, created on first use. */
function messageSlot(field) {
    const id = `${field.id || field.name || 'field'}-error`;
    let slot = document.getElementById(id);

    if (slot) {
        return slot;
    }

    slot = document.createElement('p');
    slot.id = id;
    slot.className = 'mt-1.5 text-xs font-medium text-[var(--color-critical)]';
    slot.dataset.clientError = 'true';

    /*
     * Directly after the field, or after its immediate wrapper where the
     * control has one for an icon or a chevron.
     *
     * Only the immediate parent counts. Walking up for the nearest positioned
     * ancestor finds the modal panel instead, and the message ends up outside
     * the dialog it belongs to.
     */
    const parent = field.parentElement;
    const anchor = parent?.classList.contains('relative') ? parent : field;

    anchor.insertAdjacentElement('afterend', slot);

    return slot;
}

function showError(field, message) {
    const slot = messageSlot(field);

    slot.textContent = message;
    slot.hidden = false;

    field.setAttribute('aria-invalid', 'true');
    field.classList.add('border-[var(--color-critical)]');
    field.classList.remove('border-subtle');

    const described = (field.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean);

    if (!described.includes(slot.id)) {
        field.setAttribute('aria-describedby', [...described, slot.id].join(' '));
    }
}

function clearError(field) {
    const slot = document.getElementById(`${field.id || field.name || 'field'}-error`);

    // A message from the server is not ours to clear; it goes when the
    // component re-renders.
    if (slot?.dataset.clientError === 'true') {
        slot.textContent = '';
        slot.hidden = true;
    }

    field.removeAttribute('aria-invalid');
    field.classList.remove('border-[var(--color-critical)]');

    if (!field.classList.contains('border-strong')) {
        field.classList.add('border-subtle');
    }
}

export function checkField(field) {
    if (field.disabled || field.readOnly || field.type === 'hidden') {
        return true;
    }

    const message = ruleMessage(field);

    if (message) {
        showError(field, message);

        return false;
    }

    clearError(field);

    return true;
}

export function checkForm(form) {
    const fields = [...form.querySelectorAll(FIELDS)];
    let firstBad = null;

    fields.forEach((field) => {
        if (!checkField(field) && !firstBad) {
            firstBad = field;
        }
    });

    if (firstBad) {
        // Focus rather than only scroll: the point is to carry on typing.
        firstBad.focus({ preventScroll: true });
        firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
    }

    return firstBad === null;
}

/**
 * Hand validation over from the browser.
 *
 * A required field that is empty makes the browser refuse to fire `submit` at
 * all, and show its own bubble instead: unstyled, worded differently in every
 * browser, gone the moment you look away, and invisible to a screen reader
 * that is not watching for it. Nothing downstream ever runs.
 *
 * Turning it off is done here rather than in the markup on purpose. If this
 * script fails to load, the forms keep the browser's own checks rather than
 * having none, and the server validates either way.
 */
function takeOverNativeValidation(root = document) {
    root.querySelectorAll?.('form[data-validate]:not([novalidate])')
        .forEach((form) => {
            form.noValidate = true;
        });
}

/**
 * Installed once, for the whole document, so a form added later by Livewire is
 * covered without re-binding anything.
 */
export function installValidation() {
    takeOverNativeValidation();

    document.addEventListener('DOMContentLoaded', () => takeOverNativeValidation());
    document.addEventListener('livewire:navigated', () => takeOverNativeValidation());

    // Livewire swaps markup in without a page load, so new forms are caught
    // as they arrive rather than only at startup.
    new MutationObserver((records) => {
        for (const record of records) {
            for (const node of record.addedNodes) {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    takeOverNativeValidation(node);

                    if (node.matches?.('form[data-validate]')) {
                        node.noValidate = true;
                    }
                }
            }
        }
    }).observe(document.documentElement, { childList: true, subtree: true });

    // Checked on the way out of a field, not on every keystroke: telling
    // somebody their email is wrong while they are still typing it is noise.
    document.addEventListener(
        'focusout',
        (event) => {
            const field = event.target;

            if (field.matches?.(FIELDS) && field.closest('form[data-validate]')) {
                checkField(field);
            }
        },
        true,
    );

    // Once a field has been marked wrong, correct it as they fix it.
    document.addEventListener('input', (event) => {
        const field = event.target;

        if (field.matches?.(FIELDS)
            && field.getAttribute('aria-invalid') === 'true'
            && field.closest('form[data-validate]')) {
            checkField(field);
        }
    });

    document.addEventListener(
        'submit',
        (event) => {
            const form = event.target;

            if (!form.matches?.('form[data-validate]')) {
                return;
            }

            if (!checkForm(form)) {
                // Stopped in the capture phase, so Livewire never sees a
                // submit it would have to reject a moment later.
                event.preventDefault();
                event.stopPropagation();
                event.stopImmediatePropagation();

                window.dispatchEvent(new CustomEvent('notify', {
                    detail: {
                        message: 'Some details need fixing.',
                        detail: 'The first one is highlighted below.',
                        tone: 'caution',
                    },
                }));
            }
        },
        true,
    );
}
