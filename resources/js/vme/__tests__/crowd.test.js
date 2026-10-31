import { describe, it, expect } from 'vitest';
import { CharacterMotion, STATE } from '../character.js';
import { Crowd, MIN_SPEED, SPACING, minSpacing, occupancy, spacing } from '../crowd.js';
import { planState, snapshotProps, diffProps } from '../logic.js';

// One Area column with a gate to a corridor, like the real map but tiny.
const layout = {
    waypoints: [
        { id: 'entry:area:1', x: 300, y: 300, zone: 'area:1' },
        { id: 'spine:area:1', x: 300, y: 250, zone: null },
        { id: 'entry:area:2', x: 900, y: 300, zone: 'area:2' },
        { id: 'spine:area:2', x: 900, y: 250, zone: null },
    ],
    edges: [['entry:area:1', 'spine:area:1'], ['entry:area:2', 'spine:area:2'], ['spine:area:1', 'spine:area:2']],
};

// What the server does for PICs sharing a destination (spreadSharedTargets): a 30 px fan, 4 per row.
const fan = (x, y, i, n) => ({ x: x + Math.round(((i % 4) - (Math.min(n, 4) - 1) / 2) * 30), y: y + Math.floor(i / 4) * 18 });

const idle = (x, y, zone = 'area:1') => ({ kind: 'idle', x, y, zone, anim: 'idle', face: 'right' });
const work = (x, y, zone = 'area:1', over = {}) => ({ kind: 'work', x, y, zone, anim: 'work_pm', face: 'left', ...over });

function at(x, y, zone = 'area:1') {
    const m = new CharacterMotion({ x, y, zone });
    m.spawn(idle(x, y, zone));
    return m;
}

/** Run the same loop the game runs: crowd side-step, then each character's own motion. */
function simulate(motions, seconds, { dt = 0.05, onStep } = {}) {
    const crowd = new Crowd();
    const busy = occupancy(motions);
    let worst = Infinity;
    for (let t = 0; t < seconds; t += dt) {
        crowd.step(motions, dt);
        for (const m of motions) if (m.active) m.update(dt, busy);
        const s = minSpacing(motions);
        worst = Math.min(worst, s);
        onStep?.(t, s);
    }
    return { worst, final: minSpacing(motions) };
}

describe('spacing', () => {
    it('is an elliptical distance (depth counts for less than width)', () => {
        expect(spacing({ x: 0, y: 0 }, { x: SPACING.rx, y: 0 })).toBeCloseTo(1);
        expect(spacing({ x: 0, y: 0 }, { x: 0, y: SPACING.ry })).toBeCloseTo(1);
        expect(spacing({ x: 0, y: 0 }, { x: 0, y: 0 })).toBe(0);
    });
});

describe('characters never share a spot', () => {
    it('two characters placed exactly on each other are pushed apart', () => {
        const a = at(500, 400);
        const b = at(500, 400);
        const { final } = simulate([a, b], 1.5);
        expect(final).toBeGreaterThan(0.85);
    });

    it('moves them without teleporting (smooth)', () => {
        const a = at(500, 400);
        const b = at(500, 400);
        const crowd = new Crowd();
        let last = a.renderX;
        let biggest = 0;
        for (let i = 0; i < 40; i++) {
            crowd.step([a, b], 0.05);
            biggest = Math.max(biggest, Math.abs(a.renderX - last));
            last = a.renderX;
        }
        expect(biggest).toBeLessThan(8); // px per 50 ms frame
    });

    it('several characters sent to one machine (server fan-out) arrive and stay apart', () => {
        const crowdOf = Array.from({ length: 6 }, (_, i) => at(100 + i * 40, 100));
        crowdOf.forEach((m, i) => { const t = fan(500, 400, i, 6); m.setPlan(work(t.x, t.y), layout); });
        const { worst, final } = simulate(crowdOf, 25);

        expect(final).toBeGreaterThan(0.9);
        expect(worst).toBeGreaterThan(0.5);
        crowdOf.forEach((m) => expect(m.state).toBe(STATE.WORKING));
    });

    it('even eight characters told to stand on the exact same point (no fan-out) end up apart', () => {
        const crowdOf = Array.from({ length: 8 }, (_, i) => at(100 + i * 40, 100));
        for (const m of crowdOf) m.setPlan(work(500, 400), layout);
        const { final } = simulate(crowdOf, 30);

        expect(final).toBeGreaterThan(0.7);
        for (const m of crowdOf) {
            expect(m.state).toBe(STATE.WORKING);
            expect(Math.hypot(m.renderX - 500, m.renderY - 400)).toBeLessThan(90); // still around the machine
        }
    });
});

describe('passing each other', () => {
    it('two characters walking head-on along one line step aside and do not pass through each other', () => {
        const a = at(100, 400);
        const b = at(600, 400);
        a.setPlan(work(600, 400), layout);
        b.setPlan(work(100, 400), layout);

        const { worst } = simulate([a, b], 12);

        expect(worst).toBeGreaterThan(0.6);
        expect([Math.round(a.x), Math.round(b.x)]).toEqual([600, 100]); // both made it
        expect(a.state).toBe(STATE.WORKING);
        expect(b.state).toBe(STATE.WORKING);
    });

    it('a faster character queues behind a slower one instead of walking through it, without stopping', () => {
        const slow = at(300, 400);
        const fast = at(100, 400);
        slow.setPlan(work(800, 400), layout);
        fast.setPlan(work(800, 400), layout);
        slow.speedFactor = 0.5; // crowd recomputes this each step; start slow to force a catch-up

        let minFactor = 1;
        const { worst } = simulate([slow, fast], 30, { onStep: () => { minFactor = Math.min(minFactor, fast.speedFactor); } });

        expect(worst).toBeGreaterThan(0.6);
        expect(minFactor).toBeGreaterThanOrEqual(MIN_SPEED); // eases off, never freezes
        expect(fast.state).toBe(STATE.WORKING); // and still arrives
    });

    it('characters walking through the same corridor gate keep their distance', () => {
        const group = Array.from({ length: 6 }, (_, i) => at(100 + i * 40, 420, 'area:1'));
        group.forEach((m, i) => { const t = fan(900, 420, i, 6); m.setPlan(work(t.x, t.y, 'area:2', { anim: 'work_clean' }), layout); });

        const { worst } = simulate(group, 60);

        expect(worst).toBeGreaterThan(0.5);
        expect(group.every((m) => m.state === STATE.WORKING)).toBe(true);
    });
});

describe('leaving and returning to base', () => {
    it('characters returning to neighbouring bases do not collide and end exactly on their base', () => {
        const bases = [[100, 100], [146, 100], [192, 100], [238, 100]];
        const people = bases.map(([x, y]) => at(x, y));
        people.forEach((m, i) => { const t = fan(500, 400, i, 4); m.setPlan(work(t.x, t.y), layout); }); // all go to one machine...
        simulate(people, 20);
        people.forEach((m, i) => m.setPlan(idle(...bases[i]), layout)); // ...then home again

        const { worst } = simulate(people, 30);

        expect(worst).toBeGreaterThan(0.6);
        people.forEach((m, i) => {
            expect(m.state).toBe(STATE.IDLE);
            expect([Math.round(m.renderX), Math.round(m.renderY)]).toEqual(bases[i]); // offset has decayed to 0
        });
    });
});

describe('oil audit patrol with several auditors', () => {
    const route = [[200, 400], [330, 380], [330, 480], [200, 500], [120, 450], [160, 360], [260, 440]]
        .map(([x, y]) => ({ x, y }));
    const rotate = (shift) => [...route.slice(shift), ...route.slice(0, shift)];

    function auditor(seed) {
        const r = rotate((seed * 3) % route.length); // same rule as VmeLayout::patrolPoints
        const m = at(r[0].x, r[0].y);
        m.setPlan({ kind: 'work', seed, x: r[0].x, y: r[0].y, zone: 'area:1', anim: 'work_oil_sample', face: 'right', patrol: r, patrolSeconds: 3 }, layout);
        return m;
    }

    it('four auditors keep patrolling and never pile up', () => {
        const people = [1, 2, 3, 4].map(auditor);
        const stops = new Set();
        let low = 0;
        let total = 0;
        const { worst } = simulate(people, 90, { onStep: (_, s) => {
            total++;
            if (s < 0.5) low++;
            people.forEach((m) => stops.add(`${Math.round(m.x)},${Math.round(m.y)}`));
        } });

        expect(worst).toBeGreaterThan(0.25); // brief hand-over overlaps only
        expect(low / total).toBeLessThan(0.05); // under 5% of frames have two characters closer than half the spacing
        expect(stops.size).toBeGreaterThan(route.length); // they really move around the route
        expect(people.every((m) => m.state === STATE.WORKING || m.state === STATE.WALKING)).toBe(true);
    });

    it('two auditors that happen to share the same start are never stacked for long', () => {
        const a = auditor(7);
        const b = auditor(7 + 7); // same rotation: the worst case
        let stackedSteps = 0;
        let total = 0;
        simulate([a, b], 90, { onStep: (_, s) => { total++; if (s < 0.5) stackedSteps++; } });
        expect(stackedSteps / total).toBeLessThan(0.05); // overlapping <5% of the time
        expect([a, b].every((m) => m.state === STATE.WORKING || m.state === STATE.WALKING)).toBe(true);
    });
});

describe('patrol stop reservation', () => {
    const route = [{ x: 200, y: 400 }, { x: 330, y: 400 }, { x: 330, y: 480 }, { x: 200, y: 480 }].map((p) => ({ ...p }));
    const plan = (seed, r) => ({ kind: 'work', seed, x: r[0].x, y: r[0].y, zone: 'area:1', anim: 'work_oil_sample', face: 'right', patrol: r, patrolSeconds: 3 });

    it('an auditor does not step onto a stop another auditor stands on or is walking to', () => {
        const a = at(200, 400); a.setPlan(plan(0, route), layout);
        const b = at(330, 400); b.setPlan(plan(1, [route[1], route[2], route[3], route[0]]), layout);
        const busy = occupancy([a, b]);

        // b stands on stop 1: a (at stop 0) must not pick it as its next stop, but skip to stop 2.
        expect(busy(a, route[1].x, route[1].y)).toBe(true);
        expect(busy(a, route[2].x, route[2].y)).toBe(false);
        a.patrolTimer = 3.1;
        a.update(0.05, busy);
        expect(a.path[0]).toMatchObject({ x: 330, y: 480 });
    });

    it('waits in place when every other stop is taken, then moves on when one frees up', () => {
        const a = at(200, 400); a.setPlan(plan(0, route), layout);
        a.patrolTimer = 3.1;
        a.update(0.05, () => true); // everything busy
        expect(a.state).toBe(STATE.WORKING);
        expect(a.path).toEqual([]);
        a.update(0.05, () => false);
        expect(a.state).toBe(STATE.WALKING);
    });

    it('several auditors on one loop never share a stop', () => {
        const people = [0, 1, 2].map((i) => { const r = [...route.slice(i), ...route.slice(0, i)]; const m = at(r[0].x, r[0].y); m.setPlan(plan(i, r), layout); return m; });
        let sameStop = 0;
        simulate(people, 120, { onStep: () => {
            const spots = people.filter((m) => !m.path.length).map((m) => `${Math.round(m.x)},${Math.round(m.y)}`);
            if (new Set(spots).size !== spots.length) sameStop++;
        } });
        expect(sameStop).toBe(0);
    });
});

describe('stability', () => {
    it('re-applying the same plans every poll changes nothing', () => {
        const people = Array.from({ length: 5 }, (_, i) => at(100 + i * 46, 100));
        const plans = people.map((_, i) => work(500 + i * 40, 400));
        people.forEach((m, i) => m.setPlan(plans[i], layout));
        simulate(people, 8);

        const before = people.map((m) => [m.x, m.y, m.state, m.anim, m.patrolTimer, m.path.length]);
        for (let poll = 0; poll < 10; poll++) {
            people.forEach((m, i) => expect(m.setPlan({ ...plans[i] }, layout)).toBe(false));
        }
        expect(people.map((m) => [m.x, m.y, m.state, m.anim, m.patrolTimer, m.path.length])).toEqual(before);
    });

    it('a real change of target is applied: the character goes to the new place', () => {
        const people = [at(100, 100), at(146, 100)];
        people[0].setPlan(work(500, 400), layout);
        simulate(people, 15);
        people[0].setPlan(work(700, 450, 'area:1', { anim: 'work_grease' }), layout);
        simulate(people, 15);

        expect(people[0].state).toBe(STATE.WORKING);
        expect(people[0].anim).toBe('work_grease');
        expect(Math.round(people[0].x)).toBe(700);
    });

    it('a missing or broken layout falls back to a straight walk instead of throwing or sticking', () => {
        for (const bad of [null, undefined, {}, { waypoints: [], edges: [] }]) {
            const m = at(100, 100);
            expect(() => m.setPlan(work(900, 400, 'area:2'), bad)).not.toThrow();
            simulate([m], 20);
            expect(m.state).toBe(STATE.WORKING);
            expect([Math.round(m.x), Math.round(m.y)]).toEqual([900, 400]);
        }
    });

    it('never stays WALKING without a path', () => {
        const m = at(100, 100);
        m.state = STATE.WALKING; // corrupted state
        m.path = [];
        m.update(0.05);
        expect([STATE.WORKING, STATE.IDLE]).toContain(m.state);
    });

    it('survives long frames (a slow device or a tab coming back): same outcome as small steps', () => {
        const run = (frame) => {
            const people = [at(100, 400), at(600, 400)];
            people[0].setPlan(work(600, 400), layout);
            people[1].setPlan(work(100, 400), layout);
            const crowd = new Crowd();
            for (let t = 0; t < 20; t += frame) {
                for (let left = frame; left > 1e-6; ) { // the game's own subdivision
                    const h = Math.min(left, 0.05);
                    left -= h;
                    crowd.step(people, h);
                    people.forEach((m) => m.active && m.update(h));
                }
            }
            return people.map((m) => [Math.round(m.x), Math.round(m.y), m.state]);
        };
        expect(run(1)).toEqual(run(0.05));
    });

    it('stays finite and apart with 60 characters moving at once', () => {
        const people = Array.from({ length: 60 }, (_, i) => at(60 + (i % 10) * 46, 60 + Math.floor(i / 10) * 46));
        people.forEach((m, i) => m.setPlan(work(500 + (i % 6) * 60, 380 + Math.floor(i / 6) * 40, 'area:1', { anim: 'work_tool' }), layout));

        const { worst, final } = simulate(people, 40);

        expect(people.every((m) => Number.isFinite(m.renderX) && Number.isFinite(m.renderY))).toBe(true);
        expect(people.every((m) => m.state === STATE.WORKING)).toBe(true);
        expect(final).toBeGreaterThan(0.7);
        expect(worst).toBeGreaterThan(0.1); // crowded start, but never stacked on one point
    });
});

describe('dynamic props are unaffected', () => {
    it('machines appear only for active work and are removed afterwards', () => {
        const current = new Map();
        const snap = (ids) => ({ machines: ids.map((id) => ({ id, number: `M${id}`, x: id, y: id })) });

        let d = diffProps(current, snapshotProps(snap([1, 2])));
        d.add.forEach((p) => current.set(p.key, p));
        expect([...current.keys()]).toEqual(['m:1', 'm:2']);

        d = diffProps(current, snapshotProps(snap([2])));
        expect(d.remove).toEqual(['m:1']);
        expect(planState({ status: 'IDLE', zone: 'a', base: { x: 1, y: 2 } }, { human_movement: true }).kind).toBe('idle');
    });
});
