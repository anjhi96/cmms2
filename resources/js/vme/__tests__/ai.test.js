import { describe, it, expect } from 'vitest';
import { readFileSync } from 'node:fs';
import { CharacterMotion, STATE } from '../character.js';
import { Crowd, occupancy } from '../crowd.js';
import { aiPlan, withArea } from '../logic.js';
import { ANIMATIONS } from '../animations.js';
import { detailTarget, entitiesFrom, hitTest, isClick, sameSelection } from '../select.js';
import { renderAi, panelTitle } from '../detail.js';

// A small map like the real one: Office on top, a corridor, then Areas.
const zones = [
    { key: 'office', type: 'office', x: 24, y: 24, w: 400, h: 130 },
    { key: 'workshop', type: 'workshop', x: 440, y: 24, w: 400, h: 130 },
    { key: 'area:1', type: 'production', x: 24, y: 224, w: 300, h: 440 },
    { key: 'area:2', type: 'production', x: 324, y: 224, w: 300, h: 440 },
];
const SPINE_Y = 189;
const layout = {
    waypoints: [
        { id: 'entry:office', x: 224, y: 148, zone: 'office' }, { id: 'spine:office', x: 224, y: SPINE_Y, zone: null },
        { id: 'entry:workshop', x: 640, y: 148, zone: 'workshop' }, { id: 'spine:workshop', x: 640, y: SPINE_Y, zone: null },
        { id: 'entry:area:1', x: 174, y: 230, zone: 'area:1' }, { id: 'spine:area:1', x: 174, y: SPINE_Y, zone: null },
        { id: 'entry:area:2', x: 474, y: 230, zone: 'area:2' }, { id: 'spine:area:2', x: 474, y: SPINE_Y, zone: null },
    ],
    edges: [['entry:office', 'spine:office'], ['entry:workshop', 'spine:workshop'], ['entry:area:1', 'spine:area:1'], ['entry:area:2', 'spine:area:2'],
        ['spine:office', 'spine:area:1'], ['spine:area:1', 'spine:area:2'], ['spine:area:2', 'spine:workshop']],
};

const SPOTS = { production: [[0.2, 0.2], [0.5, 0.5], [0.75, 0.3]], workshop: [[0.5, 0.7]] };
const opts = (over = {}) => ({ zones, zoneTypes: ['production', 'workshop'], visitSpots: SPOTS, wanderMin: 3, wanderMax: 9, index: 0, ...over });
const bot = (over = {}) => ({ desk: { x: 84, y: 108 }, status: 'IDLE', visit_zones: null, ...over });

/** Small seeded generator so the "random" behaviour is reproducible in tests. */
function seeded(seed) {
    let a = seed >>> 0;
    return () => {
        a = (a + 0x6d2b79f5) >>> 0;
        let t = a;
        t = Math.imul(t ^ (t >>> 15), t | 1);
        t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function spawned(plan, seed = 1) {
    const m = new CharacterMotion({ x: plan.x, y: plan.y, zone: plan.zone, rng: seeded(seed) });
    m.spawn(plan);
    m.setPlan(plan, layout);
    return m;
}

function run(motions, seconds, dt = 0.05) {
    const crowd = new Crowd();
    const busy = occupancy(motions);
    for (let t = 0; t < seconds; t += dt) {
        crowd.step(motions, dt);
        motions.forEach((m) => m.active && m.update(dt, busy));
    }
}

const inside = (p, z) => p.x >= z.x && p.x <= z.x + z.w && p.y >= z.y && p.y <= z.y + z.h;

describe('aiPlan', () => {
    it('has the desk (home) and open spots INSIDE the production Areas and the Workshop', () => {
        const plan = aiPlan(bot(), opts());
        const desks = plan.patrol.filter((s) => s.home);
        const visits = plan.patrol.filter((s) => !s.home);

        expect(desks).toHaveLength(1);
        expect(desks[0].zone).toBe('office');
        expect(visits).toHaveLength(3 + 3 + 1); // 3 spots in each of 2 Areas + 1 in the Workshop
        for (const v of visits) {
            const zone = zones.find((z) => z.key === v.zone);
            expect(zone, `unknown zone ${v.zone}`).toBeDefined();
            expect(inside(v, zone), `spot (${v.x},${v.y}) is not inside ${v.zone}`).toBe(true);
            expect(v.anim).toBe('idle');
        }
        expect(new Set(visits.map((v) => v.zone))).toEqual(new Set(['area:1', 'area:2', 'workshop']));
    });

    it('is a random wander with a random stay between the configured min and max', () => {
        const plan = aiPlan(bot(), opts({ wanderMin: 2, wanderMax: 7 }));
        expect(plan.patrolMode).toBe('random');
        expect(plan.dwell).toEqual({ min: 2, max: 7 });
        expect(aiPlan(bot(), opts({ wanderMin: 0, wanderMax: 0 })).dwell.min).toBeGreaterThanOrEqual(1);
        expect(aiPlan(bot(), opts({ wanderMin: 5, wanderMax: 2 })).dwell.max).toBeGreaterThanOrEqual(5); // max is never below min
    });

    it('never visits the Office, and only the allowed zone types', () => {
        expect(aiPlan(bot(), opts()).patrol.some((s) => s.zone === 'office' && !s.home)).toBe(false);
        const productionOnly = aiPlan(bot(), opts({ zoneTypes: ['production'] }));
        expect(productionOnly.patrol.some((s) => s.zone === 'workshop')).toBe(false);
        expect(aiPlan(bot(), opts({ zoneTypes: [] })).patrol).toBeUndefined(); // nowhere to go: stays at the desk
    });

    it('a per-Area Koordinator only visits its own Area', () => {
        const plan = aiPlan(bot({ visit_zones: ['area:2'] }), opts());
        const visited = new Set(plan.patrol.filter((s) => !s.home).map((s) => s.zone));
        expect(visited).toEqual(new Set(['area:2']));
    });

    it('looks the same whatever the status (it shows in the label and panel, not in a work pose)', () => {
        for (const status of ['WORKING', 'IDLE', 'ALERT']) expect(aiPlan(bot({ status }), opts()).patrol[0].anim).toBe('idle');
    });
});

describe('an AI character on the map', () => {
    it('stays a random time at the desk before the first trip, and a different time for each character', () => {
        const plan = aiPlan(bot(), opts({ wanderMin: 3, wanderMax: 9 }));
        const departures = [];

        for (let seed = 1; seed <= 30; seed++) {
            const m = spawned({ ...plan, seed: 0 }, seed); // same stagger seed: only the RNG differs
            let t = 0;
            while (m.state !== STATE.WALKING && t < 30) { m.update(0.05); t += 0.05; }
            departures.push(t);
        }

        for (const t of departures) { expect(t).toBeGreaterThanOrEqual(3 - 0.06); expect(t).toBeLessThanOrEqual(9 + 0.06); }
        expect(new Set(departures.map((t) => t.toFixed(1))).size).toBeGreaterThan(15); // not in step
        expect(Math.max(...departures) - Math.min(...departures)).toBeGreaterThan(3);
    });

    it('walks into the Area (through the Office door and the Area gate) and stands on an open spot there', () => {
        const plan = aiPlan(bot({ visit_zones: ['area:1'] }), opts());
        const m = spawned(plan, 7);
        const seen = [];
        let last = '';

        for (let t = 0; t < 120; t += 0.05) {
            m.update(0.05);
            const key = `${m.state}/${m.anim}/${m.zone}`;
            if (key !== last) { seen.push(key); last = key; }
        }

        const log = seen.join('\n');
        expect(log).toContain(`${STATE.WALKING}/walk/`);
        expect(log).toContain(`${STATE.WORKING}/idle/area:1`); // standing inside the Area
        expect(log).toContain(`${STATE.WORKING}/idle/office`); // and back at the desk at times
        expect(seen.every((l) => !/work_/.test(l))).toBe(true); // never a work pose
    });

    it('the walk into an Area goes through the Office entry and the Area gate', () => {
        const plan = aiPlan(bot({ visit_zones: ['area:1'] }), opts());
        const m = spawned(plan, 3);
        m.patrolTimer = 100;
        m.update(0.05);

        expect(m.path[0]).toMatchObject({ x: 224, y: 148 }); // Office entry
        expect(m.path.some((p) => p.x === 174 && p.y === 230)).toBe(true); // the Area's gate
        const last = m.path.at(-1);
        expect(inside(last, zones.find((z) => z.key === 'area:1'))).toBe(true);
    });

    it('wanders over time: several distinct spots in several zones, and usually goes back to the desk in between', () => {
        const plan = aiPlan(bot(), opts({ wanderMin: 1, wanderMax: 2 }));
        const m = spawned(plan, 11);
        const spots = new Set();
        const zonesSeen = new Set();
        let spotToDesk = 0;
        let spotToSpot = 0;
        let prev = 'home';

        for (let t = 0; t < 3000; t += 0.1) {
            m.update(0.1);
            if (m.state !== STATE.WORKING) continue;
            const here = plan.patrol[m.patrolIndex];
            const kind = here.home ? 'home' : 'spot';
            if (kind === 'spot') { spots.add(`${here.x},${here.y}`); zonesSeen.add(here.zone); }
            if (kind !== prev && !m.path.length) {
                if (prev === 'spot' && kind === 'home') spotToDesk++;
                prev = kind;
            } else if (kind === 'spot' && prev === 'spot' && m.patrolTimer === 0 && !m.path.length) {
                spotToSpot++;
            }
        }

        expect(spots.size).toBeGreaterThanOrEqual(5);
        expect(zonesSeen.size).toBeGreaterThanOrEqual(2);
        expect(spotToDesk).toBeGreaterThan(20);
        expect(spotToDesk).toBeGreaterThan(spotToSpot); // mostly returns to the desk
    });

    it('re-applying the same plan every poll, or a status change, does not restart the trip', () => {
        const plan = aiPlan(bot(), opts());
        const m = spawned(plan, 5);
        m.update(1.5);
        const snap = [m.x, m.y, m.state, m.patrolTimer, m.patrolIndex];

        for (let i = 0; i < 10; i++) expect(m.setPlan(aiPlan(bot(), opts()), layout)).toBe(false);
        expect(m.setPlan(aiPlan(bot({ status: 'WORKING' }), opts()), layout)).toBe(false);
        expect([m.x, m.y, m.state, m.patrolTimer, m.patrolIndex]).toEqual(snap);
    });

    it('skips a spot another character stands on, and waits when every spot is taken', () => {
        const plan = aiPlan(bot({ visit_zones: ['area:1'] }), opts());
        const spots = plan.patrol.map((s, i) => [s, i]).filter(([s]) => !s.home);
        const m = spawned(plan, 9);
        m.patrolTimer = 100;

        const takenFirst = (self, x, y) => x === spots[0][0].x && y === spots[0][0].y;
        m.update(0.05, takenFirst);
        const target = m.path.at(-1);
        expect([target.x, target.y]).not.toEqual([spots[0][0].x, spots[0][0].y]);
        expect(m.state).toBe(STATE.WALKING);

        const w = spawned(plan, 9);
        w.patrolTimer = 100;
        w.update(0.05, () => true);
        expect(w.state).toBe(STATE.WORKING);
        expect(w.path).toEqual([]);
        w.update(0.05, () => false);
        expect(w.state).toBe(STATE.WALKING);
    });

    it('several AI characters wandering at once never pile up and are never in step', () => {
        const names = [0, 1, 2, 3, 4];
        const bots = names.map((i) => spawned(aiPlan(bot({ desk: { x: 74 + i * 70, y: 100 } }), opts({ index: i })), 100 + i));
        const startTimes = [];
        let minD = Infinity;
        const crowd = new Crowd();
        const busy = occupancy(bots);
        const left = new Set();

        for (let t = 0; t < 180; t += 0.05) {
            crowd.step(bots, 0.05);
            bots.forEach((m, i) => {
                if (m.active) m.update(0.05, busy);
                if (m.state === STATE.WALKING && !left.has(i)) { left.add(i); startTimes.push(+t.toFixed(1)); }
            });
            for (let i = 0; i < bots.length; i++) for (let j = i + 1; j < bots.length; j++) {
                minD = Math.min(minD, Math.hypot((bots[i].renderX - bots[j].renderX) / 26, (bots[i].renderY - bots[j].renderY) / 14));
            }
        }

        expect(new Set(startTimes).size).toBe(startTimes.length); // each left the desk at its own moment
        expect(minD).toBeGreaterThan(0.25);
        expect(bots.every((m) => m.state === STATE.WORKING || m.state === STATE.WALKING)).toBe(true);
    });

    it('stays clear of PICs working in the Area it visits', () => {
        const robot = spawned(aiPlan(bot({ visit_zones: ['area:1'] }), opts()), 21);
        const pics = [0, 1, 2].map((i) => {
            const m = new CharacterMotion({ x: 100 + i * 60, y: 380, zone: 'area:1' });
            m.spawn({ kind: 'work', x: 100 + i * 60, y: 380, zone: 'area:1', anim: 'work_pm', face: 'left' });
            return m;
        });
        run([robot, ...pics], 90);
        const all = [robot, ...pics];
        for (let i = 0; i < all.length; i++) for (let j = i + 1; j < all.length; j++) {
            expect(Math.hypot((all[i].renderX - all[j].renderX) / 26, (all[i].renderY - all[j].renderY) / 14)).toBeGreaterThan(0.3);
        }
    });
});

describe('Area filter helper', () => {
    it('adds ?area= (or &area=) only when an Area is chosen', () => {
        expect(withArea('/vme/snapshot', '')).toBe('/vme/snapshot');
        expect(withArea('/vme/snapshot', null)).toBe('/vme/snapshot');
        expect(withArea('/vme/snapshot', 'wwd')).toBe('/vme/snapshot?area=wwd');
        expect(withArea('/vme/snapshot?x=1', 'a b')).toBe('/vme/snapshot?x=1&area=a%20b');
    });
});

describe('AI character looks', () => {
    const php = readFileSync(new URL('../../../../config/vme.php', import.meta.url), 'utf8');

    it('use the human sprite system: only animations that exist for PICs', () => {
        const plan = aiPlan(bot(), opts());
        const used = new Set([plan.anim, plan.walkAnim, ...plan.patrol.map((s) => s.anim)]);
        for (const anim of used) expect(ANIMATIONS[anim], `${anim} is not a human animation`).toBeDefined();
        expect([...used].some((a) => a.startsWith('work_'))).toBe(false); // they never do physical work
    });

    it('every configured look exists, and all of them are different', () => {
        const aiAvatars = [...php.match(/'ai_avatars' => \[(.*?)\n    \],/s)[1].matchAll(/'(ai_[a-z_0-9]+)' => \[([^\]]*)\]/g)];
        const looks = Object.fromEntries(aiAvatars.map((m) => [m[1], m[2]]));
        const characters = php.slice(php.indexOf("'characters' => ["));
        const single = [...characters.matchAll(/'avatar' => '([a-z_0-9]+)'/g)].map((m) => m[1]);
        const lists = [...characters.matchAll(/'avatars' => \[([^\]]*)\]/g)].flatMap((m) => [...m[1].matchAll(/'([a-z_0-9]+)'/g)].map((x) => x[1]));
        const used = [...single, ...lists];

        expect(used.length).toBeGreaterThanOrEqual(6); // supervisor + sparepart + 4 coordinator looks
        for (const key of used) expect(looks[key], `avatar ${key} is not defined in ai_avatars`).toBeDefined();
        expect(new Set(used.map((k) => looks[k])).size).toBe(used.length);
    });

    it('the names carry no "AI" prefix', () => {
        const names = [...php.matchAll(/'name' => '([^']+)',/g)].map((m) => m[1]);
        expect(names).toEqual(expect.arrayContaining(['Supervisor', 'Koordinator', 'Sparepart Analyst']));
        expect(names.some((n) => /^AI\b/i.test(n))).toBe(false);
    });
});

describe('clicking a robot', () => {
    const botEntry = (key, x, y) => ({ motion: spawned({ kind: 'work', x, y, zone: 'office', anim: 'idle', face: 'right' }, 1) });

    it('a click on a robot selects it (type ai, id = character key)', () => {
        const bots = new Map([['supervisor', botEntry('supervisor', 84, 120)]]);
        expect(hitTest(entitiesFrom(new Map(), new Map(), bots), 84, 100)).toEqual({ type: 'ai', id: 'supervisor' });
        expect(hitTest(entitiesFrom(new Map(), new Map(), bots), 300, 100)).toBeNull();
    });

    it('is picked as a robot when it stands in front of a PIC', () => {
        const chars = new Map([[3, { motion: spawned({ kind: 'idle', x: 100, y: 118, zone: 'office', anim: 'idle', face: 'right' }, 1) }]]);
        const bots = new Map([['coordinator', botEntry('coordinator', 100, 126)]]);
        expect(hitTest(entitiesFrom(chars, new Map(), bots), 100, 110)).toEqual({ type: 'ai', id: 'coordinator' });
    });

    it('opens the AI panel: the click resolves to the AI endpoint', () => {
        const down = { x: 50, y: 50, t: 0, multi: false };
        expect(isClick(down, { x: 51, y: 50, t: 120 })).toBe(true);

        const sel = hitTest(entitiesFrom(new Map(), new Map(), new Map([['sparepart_analyst', botEntry('sparepart_analyst', 240, 120)]])), 240, 105);
        expect(detailTarget(sel, { pic: '/vme/pics/__ID__', machine: '/vme/machines/__ID__', ai: '/vme/ai/__ID__' }))
            .toEqual({ kind: 'ai', url: '/vme/ai/sparepart_analyst' });
        expect(sameSelection(sel, { type: 'ai', id: 'sparepart_analyst' })).toBe(true);
        expect(detailTarget({ type: 'tree', id: 1 }, { ai: '/x' })).toBeNull();
    });
});

describe('AI panel', () => {
    const data = (over = {}) => ({
        type: 'ai', character: { key: 'sparepart_analyst', name: 'AI Sparepart Analyst', role: 'Memantau hasil forecast', source: 'internal' },
        status: 'WORKING', summary: 'Forecast #12 sedang berjalan sejak 09:10.', details: [{ label: 'Run', value: '#12' }],
        updated_at: '2026-10-31T09:12:00+07:00', source_ref: { type: 'ai_forecast_runs', id: 12 },
        link: { label: 'Buka kumpulan hasil AI', url: '/maintenance-intelligence/vme/ai-runs?character=sparepart_analyst' }, ...over,
    });

    it('shows role, status, last analysis time, summary, details and the link to the data page', () => {
        const html = renderAi(data());
        for (const text of ['Memantau hasil forecast', 'Bekerja (menganalisis)', 'Analisis terakhir', 'Forecast #12 sedang berjalan', 'Run', 'ai_forecast_runs #12', 'Karakter AI hanya memantau', 'internal']) {
            expect(html).toContain(text);
        }
        expect(html).toContain('href="/maintenance-intelligence/vme/ai-runs?character=sparepart_analyst"');
        expect(panelTitle('ai', data())).toBe('AI Sparepart Analyst');
    });

    it('labels the alert state and hides the summary from restricted viewers', () => {
        expect(renderAi(data({ status: 'ALERT' }))).toContain('Perlu perhatian');
        const restricted = renderAi(data({ restricted: true, summary: null, details: [], link: null, source_ref: null }));
        expect(restricted).toContain('hanya tampil untuk peran dengan akses semua area');
        expect(restricted).not.toContain('Forecast #12');
        expect(restricted).not.toContain('<a ');
    });

    it('escapes whatever the analysis wrote', () => {
        expect(renderAi(data({ summary: '<img src=x onerror=1>' }))).not.toContain('<img');
    });
});
