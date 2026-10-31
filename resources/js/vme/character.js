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
    constructor({ x, y, zone }) {
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
        if (key === this.planKey) return false;

        this.planKey = key;
        this.plan = plan;
        this.patrolIndex = 0;
        this.patrolTimer = 0;

        if (Math.hypot(plan.x - this.x, plan.y - this.y) < 0.5) {
            this.path = [];
            this.#arrive();
            return true;
        }

        this.path = findRoute(layout, { x: this.x, y: this.y, zone: this.zone }, plan);
        this.state = plan.kind === 'work' ? STATE.WALKING : STATE.WALKING_BACK;
        this.anim = 'walk';
        return true;
    }

    /** Place the character at its plan without walking (first appearance on screen). */
    spawn(plan) {
        this.x = plan.x;
        this.y = plan.y;
        this.zone = plan.zone ?? null;
        this.planKey = CharacterMotion.keyOf(plan);
        this.plan = plan;
        this.patrolIndex = 0;
        this.patrolTimer = 0;
        this.path = [];
        this.#arrive();
    }

    update(dt) {
        if (!this.path.length) {
            this.#patrol(dt);
            return;
        }

        let budget = SPEED * dt;
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

    /** While working a patrol, move on to the next stop once the dwell time is over. */
    #patrol(dt) {
        const route = this.plan?.patrol;
        if (this.state !== STATE.WORKING || !route || route.length < 2) return;

        this.patrolTimer += dt;
        if (this.patrolTimer < this.plan.patrolSeconds) return;

        this.patrolTimer = 0;
        this.patrolIndex = (this.patrolIndex + 1) % route.length;
        const next = route[this.patrolIndex];
        this.path = [{ x: next.x, y: next.y, zone: this.plan.zone }];
        this.state = STATE.WALKING;
        this.anim = 'walk';
    }

    #arrive() {
        this.zone = this.plan.zone ?? this.zone;

        if (this.plan.kind === 'work') {
            this.state = STATE.WORKING;
            this.anim = this.plan.anim;
            this.patrolTimer = 0;
            // On a patrol the character keeps facing the way it walked; otherwise it faces the machine.
            if (!this.plan.patrol) this.facing = this.plan.face === 'left' ? 'left' : 'right';
        } else {
            this.state = STATE.IDLE;
            this.anim = 'idle';
            this.facing = 'right';
        }
    }
}
