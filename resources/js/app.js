import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

import confirmDialog, { installConfirmInterceptor } from './ui/confirm';
import sitePlanArranger from './ui/site-plan';
import toastStack from './ui/toasts';
import { installValidation } from './ui/validation';

/*
 * Livewire ships its own Alpine build. Registering a second instance breaks
 * both, so Alpine is only started here when Livewire is absent -- which is the
 * case on the print and public verification pages.
 */
if (!window.Livewire) {
    window.Alpine = Alpine;
    Alpine.start();
}

window.Chart = Chart;

/*
 * Shared interface behaviour.
 *
 * Registered against whichever Alpine is running -- Livewire's own, or the
 * standalone one above -- by waiting for alpine:init, which both fire.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('toastStack', toastStack);
    window.Alpine.data('confirmDialog', confirmDialog);
    window.Alpine.data('sitePlanArranger', sitePlanArranger);
});

/*
 * These are document level and survive Livewire swapping the page out, so they
 * are installed once rather than re-bound per component.
 */
installConfirmInterceptor();
installValidation();

/**
 * Theme handling.
 *
 * The stored preference is applied by an inline script in the document head so
 * there is no flash of the wrong theme; this module only handles switching and
 * keeping in step with the OS while the user is on "system".
 */
const THEME_KEY = 'theme-preference';

function systemTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function storedPreference() {
    try {
        return localStorage.getItem(THEME_KEY) || 'system';
    } catch {
        // Private browsing can deny storage; fall back rather than throwing.
        return 'system';
    }
}

function applyTheme(preference) {
    const resolved = preference === 'system' ? systemTheme() : preference;
    document.documentElement.setAttribute('data-theme', resolved);
    document.documentElement.style.colorScheme = resolved;
}

window.setThemePreference = (preference) => {
    try {
        localStorage.setItem(THEME_KEY, preference);
    } catch {
        /* preference simply will not persist */
    }
    applyTheme(preference);
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { preference } }));
};

window.getThemePreference = storedPreference;

window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (storedPreference() === 'system') {
        applyTheme('system');
    }
});

applyTheme(storedPreference());
