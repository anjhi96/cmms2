import { findRoute } from './logic.js';

// Character state machine, independent of Phaser so it can be unit tested:
//
//   IDLE ──plan: work──▶ WALKING ──arrive──▶ WORKING ──plan: idle──▶ WALKING_BACK ──arrive──▶ IDLE
//
// A plan with a `patrol` route (Oil Audit walks around the Area) loops
// WORKING (stay `patrolSeconds`) -> WALKING (to the next point) -> WORKING ...
// until the plan changes. The work animation only ever starts on arrival; while walking (to the
// machine or back to base) the animation is always 'walk'. Re-applying the
// same plan (every poll does that) is a no-op, so animations never restart.

export const STATE = Object.freeze({
    IDLE: 'IDLE',
    WALKING: 'WALKING',
    WORKING: 'WORKING',
    WALKING_BACK: 'WALKING_BACK',
});

const SPEED = 90; // px / second

export class CharacterMotion {
    /** `rng` (0..1) drives the random patrol choices; tests inject a seeded one. */
    constructor({ x, y, zone, rng }) {
        this.rng = rng || Math.random;
        this.x = x;
        this.y = y;
        this.zone = zone ?? null;
        this.path = [];
        this.state = STATE.IDLE;
        this.anim = 'idle';
        this.facing = 'right';
        this.plan = null;
        this.planKey = null;
        this.patrolIndex = 0;
        this.patrolTimer = 0;
        // Side-step offset and speed factor, both set by Crowd (crowd.js). Rendered
        // position = route position + offset; the offset decays to 0 when nobody is near.
        this.offX = 0;
        this.offY = 0;
        this.speedFactor = 1;
    }

    get renderX() { return this.x + this.offX; }

    get renderY() { return this.y + this.offY; }

    /** True while the rendered position may still be changing because of crowd side-stepping. */
    get offsetActive() {
        return this.offX !== 0 || this.offY !== 0;
    }

    /** Patrolling characters begin with a staggered dwell so several auditors do not move in step. */
    #resetPatrol(plan) {
        this.patrolIndex = 0;
        this.patrolTimer = -((plan.seed ?? 0) % 3) * 0.6;
        this.currentDwell = this.#drawDwell();
    }

    /** How long to stay at the next stop: random within plan.dwell, else the fixed patrolSeconds. */
    #drawDwell() {
        const d = this.plan?.dwell;
        return d ? d.min + this.rng() * (d.max - d.min) : this.plan?.patrolSeconds;
    }

    /** True while the character needs update() calls: walking, or patrolling between stops. */
    get active() {
        return this.path.length > 0 || (this.state === STATE.WORKING && (this.plan?.patrol?.length ?? 0) > 1);
    }

    static keyOf(plan) {
        return `${Math.round(plan.x)},${Math.round(plan.y)},${plan.anim}`;
    }

    /**
     * Apply what the character should be doing now (from planState()).
     * Returns true when something changed; false (and nothing touched) when
     * the plan is the one already in force.
     */
    setPlan(plan, layout) {
        const key = CharacterMotion.keyOf(plan);
        this.layout = layout;

        if (key === this.planKey) {
            // Same destination: keep position, route and patrol timer, but take the fresh plan
            // (e.g. a stop's animation changed with the character's status).
            this.plan = plan;
            if (this.state === STATE.WORKING && !this.path.length) {
                const anim = this.#workAnim();
                if (anim !== this.anim) {
                    this.anim = anim;
                    return true;
                }
            }
            return false;
        }

        this.planKey = key;
        this.plan = plan;
        this.#resetPatrol(plan);

        if (Math.hypot(plan.x - this.x, plan.y - this.y) < 0.5) {
            this.path = [];
            this.#arrive();
            return true;
        }

        try {
            this.path = findRoute(layout, { x: this.x, y: this.y, zone: this.zone }, plan);
        } catch {
            // No usable route (bad or missing layout): walk straight there rather than get stuck.
            this.path = [];
        }
        if (!this.path.length) this.path = [{ x: plan.x, y: plan.y, zone: plan.zone ?? null }];
        this.state = plan.kind === 'work' ? STATE.WALKING : STATE.WALKING_BACK;
        this.anim = plan.walkAnim ?? 'walk';
        return true;
    }

    /** Place the character at its plan without walking (first appearance on screen). */
    spawn(plan) {
        this.x = plan.x;
        this.y = plan.y;
        this.zone = plan.zone ?? null;
        this.planKey = CharacterMotion.keyOf(plan);
        this.plan = plan;
        this.#resetPatrol(plan);
        this.path = [];
        this.#arrive();
    }

    /**
     * @param {number} dt seconds
     * @param {((self: CharacterMotion, x: number, y: number) => boolean)|null} busy optional: true when a spot is taken
     */
    update(dt, busy = null) {
        if (!this.path.length) {
            // Never leave a character "walking" with nowhere to go.
            if (this.plan && (this.state === STATE.WALKING || this.state === STATE.WALKING_BACK)) this.#arrive();
            else this.#patrol(dt, busy);
            return;
        }

        let budget = SPEED * this.speedFactor * dt;
        while (budget > 0 && this.path.length) {
            const next = this.path[0];
            const dx = next.x - this.x;
            const dy = next.y - this.y;
            const dist = Math.hypot(dx, dy);

            if (dx > 0.01) this.facing = 'right';
            else if (dx < -0.01) this.facing = 'left';

            if (dist <= budget) {
                this.x = next.x;
                this.y = next.y;
                this.zone = next.zone;
                budget -= dist;
                this.path.shift();
            } else {
                this.x += (dx / dist) * budget;
                this.y += (dy / dist) * budget;
                budget = 0;
            }
        }

        if (!this.path.length) this.#arrive();
    }

    /** While working a patrol, move on to the next free stop once the dwell time is over. */
    #patrol(dt, busy) {
        const route = this.plan?.patrol;
        if (this.state !== STATE.WORKING || !route || route.length < 2) return;

        this.patrolTimer += dt;
        if (this.patrolTimer < (this.currentDwell ?? this.plan.patrolSeconds)) return;

        // Next stop that nobody stands on or is heading to; if every candidate is taken, wait here
        // and look again next frame (the timer stays elapsed).
        const chosen = this.plan.patrolMode === 'random' ? this.#randomStop(busy) : this.#nextStop(busy);
        if (chosen === null) return;

        this.patrolTimer = 0;
        this.patrolIndex = chosen;
        const next = route[chosen];
        const zone = next.zone !== undefined ? next.zone : this.plan.zone;

        // Stops in another zone (an AI character leaving its desk) are routed through the corridor;
        // stops in the same zone are a straight walk, as before.
        let path = [];
        try {
            path = findRoute(this.layout, { x: this.x, y: this.y, zone: this.zone }, { x: next.x, y: next.y, zone });
        } catch {
            path = [];
        }
        this.path = path.length ? path : [{ x: next.x, y: next.y, zone }];
        this.state = STATE.WALKING;
        this.anim = this.plan.walkAnim ?? 'walk';
    }

    /** The next stop in loop order that is free, or null. */
    #nextStop(busy) {
        const route = this.plan.patrol;
        for (let step = 1; step < route.length; step++) {
            const idx = (this.patrolIndex + step) % route.length;
            if (!busy || !busy(this, route[idx].x, route[idx].y)) return idx;
        }
        return null;
    }

    /**
     * Random wandering: from a desk (a `home` stop) go to a random visit spot; from a spot return
     * to a desk most of the time (65%), otherwise hop to another random spot. Taken spots are skipped.
     */
    #randomStop(busy) {
        const route = this.plan.patrol;
        const shuffle = (list) => {
            const a = [...list];
            for (let i = a.length - 1; i > 0; i--) {
                const j = Math.floor(this.rng() * (i + 1));
                [a[i], a[j]] = [a[j], a[i]];
            }
            return a;
        };
        const homes = route.map((s, i) => (s.home ? i : -1)).filter((i) => i >= 0);
        const spots = route.map((s, i) => (s.home ? -1 : i)).filter((i) => i >= 0 && i !== this.patrolIndex);
        const here = route[this.patrolIndex];

        const order = here?.home
            ? shuffle(spots)
            : this.rng() < 0.65 ? [...shuffle(homes), ...shuffle(spots)] : [...shuffle(spots), ...shuffle(homes)];

        for (const idx of order) {
            if (!busy || !busy(this, route[idx].x, route[idx].y)) return idx;
        }
        return null;
    }

    /** The work animation here: the current patrol stop's own one if it has any, else the plan's. */
    #workAnim() {
        return this.plan.patrol?.[this.patrolIndex]?.anim ?? this.plan.anim;
    }

    #arrive() {
        const stop = this.plan.patrol?.[this.patrolIndex];
        const zone = stop && stop.zone !== undefined ? stop.zone : this.plan.zone;
        if (zone !== undefined) this.zone = zone; // null is a real zone: the corridor

        if (this.plan.kind === 'work') {
            this.state = STATE.WORKING;
            this.anim = this.#workAnim();
            this.patrolTimer = this.patrolTimer < 0 ? this.patrolTimer : 0; // keep a stagger set by #resetPatrol
            if (this.state === STATE.WORKING && this.path.length === 0 && this.plan.dwell) this.currentDwell = this.#drawDwell(); // a fresh random stay at every stop
            // On a patrol the character keeps facing the way it walked; otherwise it faces the machine.
            if (!this.plan.patrol) this.facing = this.plan.face === 'left' ? 'left' : 'right';
        } else {
            this.state = STATE.IDLE;
            this.anim = 'idle';
            this.facing = 'right';
        }
    }
}
