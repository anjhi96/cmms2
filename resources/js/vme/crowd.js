// Lightweight crowd control for the VME: characters keep following their own
// route (CharacterMotion); a small, smoothed "side-step" offset keeps them apart.
// No physics engine.
//
// Every step the offsets are RE-DERIVED from where the characters are on their
// routes (never from where they were drawn), so there is no feedback loop to
// oscillate:
//
//  1. Lanes: a walker with somebody right ahead steps toward its own right
//     (head-on walkers therefore pass on opposite sides) and eases off, never
//     below MIN_SPEED, so nobody stops for good and two characters cannot deadlock.
//  2. Relaxation: any pair still closer than the spacing ellipse is pushed apart,
//     half each, over a few iterations (handles crowds on one spot).
//  3. The result is the target offset; the drawn offset slides toward it at a
//     limited speed (smooth, never a teleport) and decays to 0 when the crowd thins,
//     so a character ends exactly on its destination.

// Feet-point ellipse: characters are ~32 px wide but only ~14 px apart in depth
// before they look like the same spot.
export const SPACING = Object.freeze({ rx: 26, ry: 14 });

const ITERATIONS = 24; // upper bound; stops as soon as nothing overlaps
const STILL_WEIGHT = 0.3; // a standing character yields 30% as much as a walking one
const SMOOTH = 12; // 1/s — how quickly the drawn offset follows its target
const MAX_SLIDE = 220; // px/s — a quick shuffle, never a teleport
// A crowd of eight on one spot needs a ~50 px radius; the server already fans out
// shared destinations, so this is only the safety net.
const MAX_OFFSET = { moving: 48, still: 64 };
const LOOKAHEAD = 60; // px: how far ahead a walker looks (head-on closing speed reaches 180 px/s)
const LANE = 18; // px: how close to my line counts as "in front of me"
const SIDESTEP = 26; // px of sideways nudge when somebody is right ahead
export const MIN_SPEED = 0.35;

/** Normalised (elliptical) distance between two characters; < 1 means they overlap. */
export function spacing(a, b) {
    return Math.hypot((a.x - b.x) / SPACING.rx, (a.y - b.y) / SPACING.ry);
}

export class Crowd {
    /**
     * @param {Array<import('./character.js').CharacterMotion>} motions
     * @param {number} dt seconds (the caller subdivides long frames)
     */
    step(motions, dt) {
        const n = motions.length;
        if (!n) return;

        // Where each character is on its route, and its intended lane.
        const px = new Array(n);
        const py = new Array(n);
        const slow = new Array(n).fill(1);

        for (let i = 0; i < n; i++) {
            const me = motions[i];
            let hx = 0;
            let hy = 0;
            if (me.path.length) {
                const next = me.path[0];
                const len = Math.hypot(next.x - me.x, next.y - me.y);
                if (len > 1e-6) { hx = (next.x - me.x) / len; hy = (next.y - me.y) / len; }
            }

            let side = 0;
            if (hx || hy) {
                for (let j = 0; j < n; j++) {
                    if (i === j) continue;
                    // Other characters measured from the route position, like me.
                    const ax = motions[j].x - me.x;
                    const ay = motions[j].y - me.y;
                    if (Math.abs(ax) > LOOKAHEAD || Math.abs(ay) > LOOKAHEAD) continue;
                    const ahead = ax * hx + ay * hy;
                    const lateral = Math.abs(ax * hy - ay * hx);
                    if (ahead > 0 && ahead < LOOKAHEAD && lateral < LANE) {
                        slow[i] = Math.min(slow[i], Math.max(MIN_SPEED, (ahead - 12) / 36)); // eases off from ~48 px, to 35% at 24 px
                        side = Math.max(side, 1 - ahead / LOOKAHEAD);
                    }
                }
            }

            // Right of the heading in screen coordinates (y down) is (-hy, hx).
            px[i] = me.x + -hy * side * SIDESTEP;
            py[i] = me.y + hx * side * SIDESTEP;
        }

        // Characters on (almost) the same spot would otherwise be split along a single
        // line; seed them on a small spiral so the relaxation spreads them in 2-D.
        for (let i = 0; i < n; i++) {
            let k = 0;
            for (let j = 0; j < i; j++) {
                if (Math.abs(px[i] - px[j]) < 2 && Math.abs(py[i] - py[j]) < 2) k++;
            }
            if (k) {
                const angle = k * 2.399963; // golden angle
                const radius = 6 * Math.sqrt(k);
                px[i] += Math.cos(angle) * radius;
                py[i] += Math.sin(angle) * radius * (SPACING.ry / SPACING.rx);
            }
        }

        // Relax overlaps: push each overlapping pair apart, half each.
        for (let it = 0; it < ITERATIONS; it++) {
            let moved = false;
            for (let i = 0; i < n; i++) {
                for (let j = i + 1; j < n; j++) {
                    const dx = px[i] - px[j];
                    const dy = py[i] - py[j];
                    if (Math.abs(dx) >= SPACING.rx || Math.abs(dy) >= SPACING.ry) continue;

                    const ex = dx / SPACING.rx;
                    const ey = dy / SPACING.ry;
                    const d = Math.hypot(ex, ey);
                    if (d >= 1) continue;

                    let ux;
                    let uy;
                    if (d < 1e-3) {
                        // Exactly on top of each other: split along a direction both agree on.
                        const a = ((i * 31 + j * 17) % 360) * (Math.PI / 180);
                        ux = Math.cos(a);
                        uy = Math.sin(a);
                    } else {
                        ux = ex / d;
                        uy = ey / d;
                    }

                    // Whoever is walking gives way more than whoever is standing still.
                    const wi = motions[i].path.length ? 1 : STILL_WEIGHT;
                    const wj = motions[j].path.length ? 1 : STILL_WEIGHT;
                    const si = (1 - d) * (wj / (wi + wj));
                    const sj = (1 - d) * (wi / (wi + wj));
                    px[i] += ux * si * SPACING.rx;
                    py[i] += uy * si * SPACING.ry;
                    px[j] -= ux * sj * SPACING.rx;
                    py[j] -= uy * sj * SPACING.ry;
                    moved = true;
                }
            }
            if (!moved) break;
        }

        // Slide the drawn offsets toward the targets.
        const smooth = Math.min(1, dt * SMOOTH);
        const cap = MAX_SLIDE * dt;

        for (let i = 0; i < n; i++) {
            const me = motions[i];
            let tx = px[i] - me.x;
            let ty = py[i] - me.y;
            const max = me.path.length ? MAX_OFFSET.moving : MAX_OFFSET.still;
            const mag = Math.hypot(tx, ty);
            if (mag > max) { tx = (tx / mag) * max; ty = (ty / mag) * max; }

            me.offX += Math.max(-cap, Math.min(cap, (tx - me.offX) * smooth));
            me.offY += Math.max(-cap, Math.min(cap, (ty - me.offY) * smooth));
            if (tx === 0 && Math.abs(me.offX) < 0.01) me.offX = 0;
            if (ty === 0 && Math.abs(me.offY) < 0.01) me.offY = 0;
            me.speedFactor = slow[i];
        }
    }
}

/** Smallest normalised distance between any two characters (drawn positions); Infinity for < 2. */
export function minSpacing(motions) {
    let min = Infinity;
    for (let i = 0; i < motions.length; i++) {
        for (let j = i + 1; j < motions.length; j++) {
            min = Math.min(min, spacing({ x: motions[i].renderX, y: motions[i].renderY }, { x: motions[j].renderX, y: motions[j].renderY }));
        }
    }
    return min;
}

/**
 * "Is this spot taken?" for patrol planning: another character stands there, or its
 * current walk ends there. Used so two auditors do not choose the same next stop.
 */
export function occupancy(motions, radius = 22) {
    return (self, x, y) => motions.some((o) => {
        if (o === self) return false;
        const end = o.path.length ? o.path[o.path.length - 1] : o;
        return Math.hypot(end.x - x, end.y - y) < radius;
    });
}
