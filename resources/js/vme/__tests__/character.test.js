import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { CharacterMotion, STATE } from '../character.js';
import { ANIMATIONS, WORK_ANIM_KEYS, FALLBACK_WORK, resolveAnimKey } from '../animations.js';
import { TOOLS, drawFigure } from '../sprites.js';
import { planState } from '../logic.js';

const layout = {
    waypoints: [
        { id: 'entry:area:1', x: 100, y: 300, zone: 'area:1' },
        { id: 'spine:area:1', x: 100, y: 250, zone: null },
        { id: 'entry:area:2', x: 500, y: 300, zone: 'area:2' },
        { id: 'spine:area:2', x: 500, y: 250, zone: null },
    ],
    edges: [['entry:area:1', 'spine:area:1'], ['entry:area:2', 'spine:area:2'], ['spine:area:1', 'spine:area:2']],
};

const base = { kind: 'idle', x: 100, y: 330, zone: 'area:1', anim: 'idle', face: 'right' };
const work = (over = {}) => ({ kind: 'work', x: 520, y: 340, zone: 'area:2', anim: 'work_pm', face: 'left', ...over });

const tick = (m, seconds, step = 0.05) => { for (let t = 0; t < seconds; t += step) m.update(step); };

function spawnedAtBase() {
    const m = new CharacterMotion(base);
    m.spawn(base);
    return m;
}

describe('character state machine', () => {
    it('starts idle at base', () => {
        const m = spawnedAtBase();
        expect(m.state).toBe(STATE.IDLE);
        expect(m.anim).toBe('idle');
    });

    it('walks first and starts the work animation only after arriving', () => {
        const m = spawnedAtBase();
        expect(m.setPlan(work(), layout)).toBe(true);

        const seen = new Set();
        for (let i = 0; i < 400 && m.path.length; i++) {
            m.update(0.05);
            if (m.path.length) seen.add(`${m.state}/${m.anim}`);
        }

        expect([...seen]).toEqual([`${STATE.WALKING}/walk`]);
        expect(m.state).toBe(STATE.WORKING);
        expect(m.anim).toBe('work_pm');
        expect([m.x, m.y]).toEqual([520, 340]);
        expect(m.facing).toBe('left'); // faces the machine on arrival
    });

    it('faces the direction it walks', () => {
        const m = spawnedAtBase();
        m.setPlan(work({ x: 40, y: 330, zone: 'area:1', face: 'right' }), layout);
        m.update(0.1);
        expect(m.facing).toBe('left');
    });

    it('does not restart or touch anything when the same plan arrives every poll', () => {
        const m = spawnedAtBase();
        m.setPlan(work(), layout);
        tick(m, 20);
        const snapshot = { x: m.x, y: m.y, state: m.state, anim: m.anim, facing: m.facing };

        for (let poll = 0; poll < 10; poll++) {
            expect(m.setPlan(work(), layout)).toBe(false);
            tick(m, 8);
        }

        expect({ x: m.x, y: m.y, state: m.state, anim: m.anim, facing: m.facing }).toEqual(snapshot);
    });

    it('swaps the animation in place when the job type changes at the same machine', () => {
        const m = spawnedAtBase();
        m.setPlan(work(), layout);
        tick(m, 20);

        expect(m.setPlan(work({ anim: 'work_clean' }), layout)).toBe(true);
        expect(m.path).toEqual([]);
        expect(m.state).toBe(STATE.WORKING);
        expect(m.anim).toBe('work_clean');
    });

    it('walks to the new place when the location changes, without working on the way', () => {
        const m = spawnedAtBase();
        m.setPlan(work(), layout);
        tick(m, 20);

        m.setPlan(work({ x: 120, y: 360, zone: 'area:1', anim: 'work_grease' }), layout);
        expect(m.state).toBe(STATE.WALKING);
        expect(m.anim).toBe('walk');
        tick(m, 30);
        expect(m.state).toBe(STATE.WORKING);
        expect(m.anim).toBe('work_grease');
    });

    it('walks back to base and goes idle when the activity ends', () => {
        const m = spawnedAtBase();
        m.setPlan(work(), layout);
        tick(m, 20);

        m.setPlan(base, layout);
        expect(m.state).toBe(STATE.WALKING_BACK);
        expect(m.anim).toBe('walk');

        tick(m, 30);
        expect(m.state).toBe(STATE.IDLE);
        expect(m.anim).toBe('idle');
        expect([m.x, m.y]).toEqual([100, 330]);
    });

    it('a PIC first seen while already working appears at the machine, working', () => {
        const m = new CharacterMotion(work());
        m.spawn(work());
        expect(m.state).toBe(STATE.WORKING);
        expect(m.anim).toBe('work_pm');
        expect(m.path).toEqual([]);
    });
});

describe('animation registry', () => {
    it('lists exactly the animations config/vme.php allows the server to send', () => {
        const php = readFileSync(new URL('../../../../config/vme.php', import.meta.url), 'utf8');
        const block = php.match(/'animations'\s*=>\s*\[([^\]]*)\]/s)[1];
        const configured = [...block.matchAll(/'([a-z_]+)'/g)].map((m) => m[1]);

        expect([...configured].sort()).toEqual([...WORK_ANIM_KEYS].sort());
    });

    it('falls back to the generic inspection for unknown keys', () => {
        expect(resolveAnimKey('nope')).toBe(FALLBACK_WORK);
        expect(resolveAnimKey(undefined)).toBe(FALLBACK_WORK);
        expect(resolveAnimKey('work_grease')).toBe('work_grease');
    });

    it('has a drawable tool for every tool a pose uses', () => {
        for (const [name, a] of Object.entries(ANIMATIONS)) {
            for (let f = 0; f < a.frames; f++) {
                const tool = a.pose(f).tool;
                if (tool) expect(TOOLS[tool.type], `${name} frame ${f} uses unknown tool ${tool.type}`).toBeTypeOf('function');
            }
        }
    });

    it('every work animation moves (frames differ) and looks different from every other', () => {
        const pal = { skin: '#f1c27d', hair: '#2b2118', shirt: '#1e6fd0', pants: '#2f3a52' };
        const render = (name, f) => {
            const calls = [];
            drawFigure({ set fillStyle(v) { calls.push(v); }, fillRect: (...a) => calls.push(a.join(',')) }, 0, pal, ANIMATIONS[name].pose(f));
            return calls.join('|');
        };
        const frames = (name) => Array.from({ length: ANIMATIONS[name].frames }, (_, f) => render(name, f));

        for (const name of WORK_ANIM_KEYS) {
            expect(new Set(frames(name)).size, `${name} does not animate`).toBeGreaterThan(1);
        }

        const sheets = WORK_ANIM_KEYS.map((n) => frames(n).join('#'));
        expect(new Set(sheets).size).toBe(WORK_ANIM_KEYS.length);

        // and every work animation differs from simply walking/standing
        expect(new Set([...WORK_ANIM_KEYS.flatMap(frames), ...frames('idle')]).size).toBeGreaterThan(WORK_ANIM_KEYS.length);
    });

    it('planState picks the registered animation and the machine-facing direction', () => {
        const pic = { status: 'WORKING', zone: 'area:1', base: { x: 1, y: 2 },
            activity: { animation_key: 'work_oil_sample' }, target: { x: 5, y: 6, zone: 'area:1', face: 'left' } };
        expect(planState(pic, { human_movement: true })).toMatchObject({ kind: 'work', anim: 'work_oil_sample', face: 'left' });
    });
});

describe('patrol (Oil Audit walks around the Area)', () => {
    const route = [{ x: 150, y: 330 }, { x: 250, y: 330 }, { x: 250, y: 400 }];
    const patrol = (over = {}) => ({ kind: 'work', x: 150, y: 330, zone: 'area:1', anim: 'work_oil_sample', face: 'right', patrol: route, patrolSeconds: 3, ...over });

    function patrolling(over = {}) {
        const m = new CharacterMotion({ x: 150, y: 330, zone: 'area:1' });
        m.spawn(patrol(over));
        return m;
    }

    it('stays and works for the interval, then walks to the next stop and works again', () => {
        const m = patrolling();
        expect(m.state).toBe(STATE.WORKING);
        expect(m.active).toBe(true);

        tick(m, 2.9);
        expect(m.state).toBe(STATE.WORKING);
        expect([m.x, m.y]).toEqual([150, 330]);

        tick(m, 0.2); // 3 s are up
        expect(m.state).toBe(STATE.WALKING);
        expect(m.anim).toBe('walk');

        tick(m, 3);
        expect(m.state).toBe(STATE.WORKING);
        expect(m.anim).toBe('work_oil_sample');
        expect([m.x, m.y]).toEqual([250, 330]);
    });

    it('loops through every stop and back to the first', () => {
        const m = patrolling();
        const stops = [];
        let was = m.state;
        for (let t = 0; t < 60; t += 0.05) {
            m.update(0.05);
            if (m.state === STATE.WORKING && was === STATE.WALKING) stops.push(`${Math.round(m.x)},${Math.round(m.y)}`);
            was = m.state;
        }
        expect(stops.slice(0, 6)).toEqual(['250,330', '250,400', '150,330', '250,330', '250,400', '150,330']);
    });

    it('follows the configured interval', () => {
        const m = patrolling({ patrolSeconds: 1 });
        tick(m, 1.1);
        expect(m.state).toBe(STATE.WALKING);
    });

    it('re-applying the same plan on every poll does not reset the patrol', () => {
        const m = patrolling();
        tick(m, 2);
        expect(m.setPlan(patrol(), layout)).toBe(false);
        tick(m, 1.2);
        expect(m.state).toBe(STATE.WALKING); // the 2 s already elapsed still count
    });

    it('stops patrolling and walks back to base when the activity ends', () => {
        const m = patrolling();
        tick(m, 3.2);
        m.setPlan(base, layout);
        expect(m.state).toBe(STATE.WALKING_BACK);
        tick(m, 30);
        expect(m.state).toBe(STATE.IDLE);
        expect(m.active).toBe(false);
        expect([m.x, m.y]).toEqual([100, 330]);
    });

    it('a plan without a route never wanders', () => {
        const m = new CharacterMotion(work());
        m.spawn(work());
        expect(m.active).toBe(false);
        tick(m, 30);
        expect([m.x, m.y]).toEqual([520, 340]);
    });

    it('planState carries the route and the configured interval', () => {
        const pic = { status: 'WORKING', zone: 'area:1', base: { x: 1, y: 2 }, activity: { animation_key: 'work_oil_sample' },
            target: { x: 150, y: 330, zone: 'area:1', face: 'right', patrol: route } };
        expect(planState(pic, { human_movement: true }, { patrolSeconds: 5 })).toMatchObject({ patrol: route, patrolSeconds: 5 });
        expect(planState({ ...pic, target: { ...pic.target, patrol: undefined } }, { human_movement: true }).patrol).toBeUndefined();
    });
});
