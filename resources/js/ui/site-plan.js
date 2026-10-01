/**
 * Arranging the site plan by dragging.
 *
 * Typing four numbers per building is precise and works with a keyboard, but
 * it is a poor way to say "that block is over there". Dragging is the natural
 * way, so it is the default, with the numbers kept alongside rather than
 * replaced: a pointer is not available to everyone, and a drag cannot put a
 * building at exactly 40%.
 *
 * The hard part is the 3D view. The plot is tilted and turned, so a pointer
 * moving right across the screen is not moving right across the ground. The
 * screen delta is projected back through the scene's own rotation, which makes
 * the building follow the pointer whatever angle the site is seen from.
 */

const MIN_SIZE = 6;
const NUDGE = 1;
const NUDGE_FAST = 5;

/**
 * Screen movement to ground movement.
 *
 * The scene applies rotateX(tilt) then rotateZ(spin) then scale(zoom). For a
 * point lying on the ground, that flattens to:
 *
 *   screenX = zoom * ( x*cos(spin) - y*sin(spin) )
 *   screenY = zoom * cos(tilt) * ( x*sin(spin) + y*cos(spin) )
 *
 * so undoing it is a divide by the scale and the foreshortening, then a turn
 * back by the spin.
 */
export function screenToGround(dx, dy, { spin = 0, tilt = 0, zoom = 1 } = {}) {
    const spinRad = (spin * Math.PI) / 180;
    const tiltRad = (tilt * Math.PI) / 180;

    // Near 90 degrees the ground is edge on: vertical movement stops meaning
    // anything, so it is ignored rather than amplified to infinity.
    const squash = Math.cos(tiltRad);
    const safeSquash = Math.abs(squash) < 0.12 ? 0.12 : squash;

    const u = dx / zoom;
    const v = dy / (zoom * safeSquash);

    return {
        x: u * Math.cos(spinRad) + v * Math.sin(spinRad),
        y: -u * Math.sin(spinRad) + v * Math.cos(spinRad),
    };
}

const clamp = (value, low, high) => Math.min(high, Math.max(low, value));

export default function sitePlanArranger(initial = {}) {
    return {
        /** Positions by block id, as percentages of the plot. */
        positions: initial.positions ?? {},

        /** The scene angles, shared with the view so a drag can undo them. */
        spin: initial.spin ?? 0,
        tilt: initial.tilt ?? 58,
        zoom: initial.zoom ?? 0.95,

        dragging: null,
        announcement: '',

        turn(by) {
            this.spin = (this.spin + by) % 360;
        },

        resetView() {
            this.spin = 0;
            this.tilt = initial.tilt ?? 58;
            this.zoom = initial.zoom ?? 0.95;
        },

        /** The plot in screen pixels, which percentages are measured against. */
        plotSize() {
            const plot = this.$refs.plot;

            return plot
                ? { width: plot.clientWidth, height: plot.clientHeight }
                : { width: 1, height: 1 };
        },

        start(event, id, mode = 'move') {
            const position = this.positions[id];

            if (!position) {
                return;
            }

            event.preventDefault();
            event.target.setPointerCapture?.(event.pointerId);

            this.dragging = {
                id,
                mode,
                pointerX: event.clientX,
                pointerY: event.clientY,
                originX: Number(position.x),
                originY: Number(position.y),
                originW: Number(position.width),
                originH: Number(position.height),
            };
        },

        move(event) {
            const drag = this.dragging;

            if (!drag) {
                return;
            }

            event.preventDefault();

            const { width, height } = this.plotSize();
            const ground = screenToGround(
                event.clientX - drag.pointerX,
                event.clientY - drag.pointerY,
                { spin: this.spin, tilt: this.tilt, zoom: this.zoom },
            );

            // Pixels to percentages of the plot.
            const dx = (ground.x / width) * 100;
            const dy = (ground.y / height) * 100;

            const position = this.positions[drag.id];

            if (drag.mode === 'resize') {
                position.width = Math.round(clamp(drag.originW + dx, MIN_SIZE, 100 - drag.originX));
                position.height = Math.round(clamp(drag.originH + dy, MIN_SIZE, 100 - drag.originY));

                return;
            }

            // Clamped so a building can never be dragged off the plot.
            position.x = Math.round(clamp(drag.originX + dx, 0, 100 - drag.originW));
            position.y = Math.round(clamp(drag.originY + dy, 0, 100 - drag.originH));
        },

        end() {
            const drag = this.dragging;

            this.dragging = null;

            if (drag) {
                this.say(drag.id);
            }
        },

        /**
         * Keyboard arranging.
         *
         * Arrow keys move, shift moves further, and holding alt resizes. A
         * plan that can only be arranged with a mouse is a plan half the
         * committee cannot arrange.
         */
        nudge(event, id) {
            const keys = ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'];

            if (!keys.includes(event.key)) {
                return;
            }

            const position = this.positions[id];

            if (!position) {
                return;
            }

            event.preventDefault();

            const step = event.shiftKey ? NUDGE_FAST : NUDGE;
            const dx = (event.key === 'ArrowRight' ? step : 0) - (event.key === 'ArrowLeft' ? step : 0);
            const dy = (event.key === 'ArrowDown' ? step : 0) - (event.key === 'ArrowUp' ? step : 0);

            if (event.altKey) {
                position.width = clamp(Number(position.width) + dx, MIN_SIZE, 100 - Number(position.x));
                position.height = clamp(Number(position.height) + dy, MIN_SIZE, 100 - Number(position.y));
            } else {
                position.x = clamp(Number(position.x) + dx, 0, 100 - Number(position.width));
                position.y = clamp(Number(position.y) + dy, 0, 100 - Number(position.height));
            }

            this.say(id);
        },

        /** Read the new position out, since the move itself is silent. */
        say(id) {
            const position = this.positions[id];

            if (position) {
                this.announcement =
                    `Moved to ${Math.round(position.x)} percent across, `
                    + `${Math.round(position.y)} percent down, `
                    + `${Math.round(position.width)} by ${Math.round(position.height)} percent.`;
            }
        },

        styleFor(id, fallback) {
            const position = this.positions[id] ?? fallback;

            return `left:${position.x}%; top:${position.y}%; width:${position.width}%; height:${position.height}%`;
        },
    };
}
