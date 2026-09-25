/**
 * Universal Kinetic Momentum Scrolling Engine
 * 
 * Provides smooth, physical momentum scrolling across the entire browser window
 * and any nested scrollable containers (e.g. sidebars, cyber terminals, dropdowns).
 * 
 * Key Features:
 * - Compounding velocity on rapid flicks (< 280ms)
 * - Instant directional braking on wheel reversal (zero floaty overshoot)
 * - Dynamic scroll container resolution (walks ancestor DOM tree to target active overflow)
 * - Monitor refresh rate independence (delta-time scaled friction)
 * - Precision trackpad passthrough (preserves native OS trackpad physics)
 * - Seamless coordination with Inertia.js route navigation
 */

export const DEFAULT_CONFIG = {
    baseStep: 10,             // Calm, controlled base displacement (~85-95px total glide per tick)
    flickThresholdMs: 240,    // Window to consider consecutive flicks for acceleration
    flickIncrement: 0.22,     // Gentle, progressive acceleration ramp (gradual build-up)
    maxMultiplier: 3.2,       // Maximum velocity multiplier
    friction: 0.89,           // Responsive deceleration decay factor
    minVelocity: 0.35,        // Threshold below which motion stops
};

/**
 * Determines whether an opposing wheel impulse should trigger an immediate brake.
 * 
 * @param {number} currentVelocity - Current running velocity in px/frame
 * @param {number} deltaY - New incoming wheel deltaY
 * @returns {boolean} True if the impulses have opposite signs
 */
export function shouldBrake(currentVelocity, deltaY) {
    if (Math.abs(currentVelocity) < 0.1 || Math.abs(deltaY) < 0.1) {
        return false;
    }
    return (currentVelocity > 0 && deltaY < 0) || (currentVelocity < 0 && deltaY > 0);
}

/**
 * Calculates the kinetic impulse and updated consecutive flick count.
 * 
 * @param {number} deltaY - Wheel delta
 * @param {number} timeSinceLastFlick - Milliseconds since previous flick
 * @param {number} currentFlicks - Previous consecutive flick counter
 * @param {object} [config=DEFAULT_CONFIG] - Physics tuning parameters
 * @returns {{ impulse: number, flicks: number, multiplier: number }}
 */
export function calculateKineticImpulse(deltaY, timeSinceLastFlick, currentFlicks, config = DEFAULT_CONFIG) {
    const isRapid = timeSinceLastFlick >= 0 && timeSinceLastFlick < config.flickThresholdMs;
    const newFlicks = isRapid ? Math.min(8, currentFlicks + 1) : 0;
    const multiplier = Math.min(config.maxMultiplier, 1 + newFlicks * config.flickIncrement);
    const impulse = Math.sign(deltaY) * config.baseStep * multiplier;

    return {
        impulse,
        flicks: newFlicks,
        multiplier,
    };
}

/**
 * Heuristic to detect whether a wheel event originates from a precision trackpad.
 * Precision trackpads emit continuous, fractional, low-delta events with deltaMode === 0.
 * 
 * @param {WheelEvent} event 
 * @returns {boolean}
 */
export function isPrecisionTrackpad(event) {
    if (event.deltaMode !== 0) {
        return false; // Line or Page mode is always a mouse wheel
    }

    const absY = Math.abs(event.deltaY);
    // Trackpads frequently emit small non-integers or sub-pixel deltas
    const isFractional = !Number.isInteger(event.deltaY) && absY > 0 && absY < 40;
    const isVerySmallDelta = absY > 0 && absY < 15;

    return isFractional || isVerySmallDelta;
}

/**
 * Checks if a given container has scroll headroom in the specified direction.
 * 
 * @param {Element|Window} target 
 * @param {number} deltaY - Positive for scrolling down, negative for scrolling up
 * @returns {boolean}
 */
export function canScrollInDirection(target, deltaY) {
    if (!target) return false;

    if (target === window) {
        const scrollY = window.scrollY || window.pageYOffset || document.documentElement.scrollTop || 0;
        const maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight - 1);

        if (deltaY > 0) {
            return scrollY < maxScroll;
        } else if (deltaY < 0) {
            return scrollY > 1;
        }
        return false;
    }

    if (target instanceof Element) {
        const maxScroll = Math.max(0, target.scrollHeight - target.clientHeight - 1);
        if (deltaY > 0) {
            return target.scrollTop < maxScroll;
        } else if (deltaY < 0) {
            return target.scrollTop > 1;
        }
    }

    return false;
}

/**
 * Walks up the DOM tree from the event target to find the nearest scrollable container
 * that has headroom in the desired direction. Falls back to window if none is found.
 * 
 * @param {Node|null} startNode 
 * @param {number} deltaY 
 * @returns {Element|Window}
 */
export function findScrollableTarget(startNode, deltaY) {
    if (!startNode || startNode === document || startNode === window) {
        return window;
    }

    let current = startNode instanceof Element ? startNode : startNode?.parentElement;

    while (current && current !== document.body && current !== document.documentElement) {
        try {
            const style = window.getComputedStyle(current);
            const overflowY = style.overflowY;

            if (overflowY === 'auto' || overflowY === 'scroll' || overflowY === 'overlay') {
                const hasScrollableHeight = current.scrollHeight > current.clientHeight + 1;
                if (hasScrollableHeight && canScrollInDirection(current, deltaY)) {
                    return current;
                }
            }
        } catch (e) {
            // In case of detached or shadow DOM nodes
            break;
        }

        current = current.parentElement;
    }

    // Default to the main window
    return window;
}

/**
 * Kinetic Momentum Scroll Controller
 */
class KineticScrollEngine {
    constructor(config = {}) {
        this.config = { ...DEFAULT_CONFIG, ...config };
        this.velocity = 0;
        this.activeTarget = null;
        this.lastFlickTime = 0;
        this.consecutiveFlicks = 0;
        this.rafId = null;
        this.lastFrameTime = 0;
        this.isRunning = false;
        this.isInitialized = false;

        this.onWheel = this.onWheel.bind(this);
        this.onFrame = this.onFrame.bind(this);
        this.onUserInterrupt = this.onUserInterrupt.bind(this);
    }

    init() {
        if (this.isInitialized || typeof window === 'undefined') return;

        window.addEventListener('wheel', this.onWheel, { passive: false });
        window.addEventListener('pointerdown', this.onUserInterrupt, { passive: true });
        window.addEventListener('keydown', this.onKeyDown.bind(this), { passive: true });
        window.addEventListener('blur', this.reset.bind(this));

        this.isInitialized = true;
    }

    destroy() {
        if (!this.isInitialized) return;

        this.reset();
        window.removeEventListener('wheel', this.onWheel);
        window.removeEventListener('pointerdown', this.onUserInterrupt);
        window.removeEventListener('blur', this.reset.bind(this));

        this.isInitialized = false;
    }

    reset() {
        if (this.rafId) {
            cancelAnimationFrame(this.rafId);
            this.rafId = null;
        }
        this.velocity = 0;
        this.activeTarget = null;
        this.lastFlickTime = 0;
        this.consecutiveFlicks = 0;
        this.isRunning = false;
    }

    onUserInterrupt() {
        // If the user clicks, drags, or touches, immediately stop running momentum
        if (this.isRunning) {
            this.reset();
        }
    }

    onKeyDown(e) {
        // Common navigation keys that scroll natively
        const navKeys = ['PageUp', 'PageDown', 'Home', 'End', 'ArrowUp', 'ArrowDown', ' '];
        if (navKeys.includes(e.key) && this.isRunning) {
            this.reset();
        }
    }

    onWheel(e) {
        // If event default was already prevented (e.g. horizontal nav scrolling, canvas zoom)
        if (e.defaultPrevented) {
            return;
        }

        // Allow zoom (Ctrl + wheel) and browser special chords
        if (e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        // Exclude elements that opt out or handle independent horizontal panning / canvas zoom
        if (e.target?.closest && e.target.closest('[data-no-kinetic], nav, canvas')) {
            return;
        }

        // If predominantly horizontal, allow native horizontal scroll
        if (Math.abs(e.deltaX) > Math.abs(e.deltaY)) {
            return;
        }

        // Bypass precision trackpads to preserve native hardware momentum
        if (isPrecisionTrackpad(e)) {
            return;
        }

        // Find the active scroll container under the cursor
        const target = findScrollableTarget(e.target, e.deltaY);
        if (!target) return;

        // If target cannot scroll in this direction and window also can't, do not intercept
        if (!canScrollInDirection(target, e.deltaY)) {
            return;
        }

        // Intercept native rigid 100px jump
        e.preventDefault();

        const now = performance.now();
        const timeSinceLastFlick = this.lastFlickTime ? (now - this.lastFlickTime) : -1;

        // Instant directional brake
        if (shouldBrake(this.velocity, e.deltaY)) {
            this.velocity = 0;
            this.consecutiveFlicks = 0;
        }

        // Calculate kinetic impulse with velocity compounding
        const { impulse, flicks } = calculateKineticImpulse(
            e.deltaY,
            timeSinceLastFlick,
            this.consecutiveFlicks,
            this.config
        );

        this.consecutiveFlicks = flicks;
        this.lastFlickTime = now;
        this.velocity += impulse;
        this.activeTarget = target;

        // Start requestAnimationFrame loop if not currently animating
        if (!this.isRunning) {
            this.isRunning = true;
            this.lastFrameTime = performance.now();
            this.rafId = requestAnimationFrame(this.onFrame);
        }
    }

    onFrame(currentTime) {
        if (!this.isRunning) return;

        // Calculate delta time for monitor refresh rate independence (60Hz / 120Hz / 144Hz)
        const dt = Math.min(32, Math.max(4, currentTime - this.lastFrameTime));
        this.lastFrameTime = currentTime;
        const frameRatio = dt / 16.667;

        // Apply frame decay friction
        this.velocity *= Math.pow(this.config.friction, frameRatio);

        // Stop condition when velocity drops below minimum threshold
        if (Math.abs(this.velocity) < this.config.minVelocity) {
            this.reset();
            return;
        }

        // Apply movement to target
        const step = this.velocity * frameRatio;
        const target = this.activeTarget;

        if (target === window) {
            window.scrollBy(0, step);
            // Check boundary hit
            if (!canScrollInDirection(window, this.velocity)) {
                this.reset();
                return;
            }
        } else if (target && target.isConnected) {
            target.scrollTop += step;
            // Check container boundary hit
            if (!canScrollInDirection(target, this.velocity)) {
                this.reset();
                return;
            }
        } else {
            // Target was unmounted or removed from DOM
            this.reset();
            return;
        }

        this.rafId = requestAnimationFrame(this.onFrame);
    }
}

// Singleton instance
let globalKineticEngine = null;

export function initKineticScroll(config = {}) {
    if (typeof window === 'undefined') return null;
    if (!globalKineticEngine) {
        globalKineticEngine = new KineticScrollEngine(config);
        globalKineticEngine.init();
    }
    return globalKineticEngine;
}

export function getKineticScroll() {
    return globalKineticEngine;
}

export default KineticScrollEngine;
