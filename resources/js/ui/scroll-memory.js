/**
 * Keeping a scrolled region where it was.
 *
 * Livewire's navigate swaps the whole page body, so a sidebar scrolled down to
 * Administration jumps back to Overview the moment you click something in it.
 * The item you wanted is then off screen, which is precisely the wrong place
 * for it to be right after you chose it.
 *
 * The position is remembered per region and restored once the new page is in
 * the DOM. Session storage rather than a variable, so it also survives a real
 * page load: signing in, or anything that is not a navigate.
 */

const KEY = 'scroll-memory';
const REGIONS = '[data-scroll-memory]';

function read() {
    try {
        return JSON.parse(sessionStorage.getItem(KEY) || '{}');
    } catch {
        // Private browsing can deny storage. Losing the position is a far
        // smaller problem than throwing on every navigation.
        return {};
    }
}

function write(state) {
    try {
        sessionStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        /* the position simply will not persist */
    }
}

export function rememberScroll() {
    const state = read();

    document.querySelectorAll(REGIONS).forEach((region) => {
        const name = region.dataset.scrollMemory;

        if (name) {
            state[name] = region.scrollTop;
        }
    });

    write(state);
}

export function restoreScroll() {
    const state = read();

    document.querySelectorAll(REGIONS).forEach((region) => {
        const name = region.dataset.scrollMemory;
        const top = state[name];

        if (!name || !top) {
            return;
        }

        // Set twice: once now, and once after layout, because the region can
        // still be growing as the new page's markup settles and a scrollTop
        // set against a short element is silently clamped to nothing.
        region.scrollTop = top;
        requestAnimationFrame(() => {
            region.scrollTop = top;
        });
    });
}

export function installScrollMemory() {
    document.addEventListener('livewire:navigate', rememberScroll);
    document.addEventListener('livewire:navigated', restoreScroll);

    // A full page load, rather than a navigate.
    window.addEventListener('beforeunload', rememberScroll);
    document.addEventListener('DOMContentLoaded', restoreScroll);
    restoreScroll();
}
