import { describe, it, expect } from 'vitest';
import { planState, findRoute, isStale, nextDelaySec, diffMachines, snapshotProps, diffProps, initialView } from '../logic.js';

const layout = {
    waypoints: [
        { id: 'entry:office', x: 100, y: 200, zone: 'office' },
        { id: 'spine:office', x: 100, y: 250, zone: null },
        { id: 'entry:area:1', x: 100, y: 300, zone: 'area:1' },
        { id: 'spine:area:1', x: 100, y: 250, zone: null },
        { id: 'entry:area:2', x: 500, y: 300, zone: 'area:2' },
        { id: 'spine:area:2', x: 500, y: 250, zone: null },
    ],
    edges: [
        ['entry:office', 'spine:office'],
        ['entry:area:1', 'spine:area:1'],
        ['entry:area:2', 'spine:area:2'],
        ['spine:office', 'spine:area:1'],
        ['spine:area:1', 'spine:area:2'],
    ],
};

const pic = (over = {}) => ({
    status: 'IDLE', zone: 'area:1', base: { x: 10, y: 20 }, activity: null, target: null, ...over,
});

const working = pic({
    status: 'WORKING',
    activity: { animation_key: 'work_grease' },
    target: { x: 520, y: 340, zone: 'area:2' },
});

describe('planState', () => {
    it('is idle at base without an actual activity', () => {
        expect(planState(pic(), { human_movement: true })).toMatchObject({ kind: 'idle', x: 10, y: 20, anim: 'idle' });
    });

    it('goes to the target with the job-type animation when working', () => {
        expect(planState(working, { human_movement: true })).toMatchObject({ kind: 'work', x: 520, y: 340, anim: 'work_grease' });
    });

    it('stays at base when human movement is OFF', () => {
        expect(planState(working, { human_movement: false }).kind).toBe('idle');
    });

    it('never shows work for an inactive PIC or an activity without target', () => {
        expect(planState({ ...working, status: 'INACTIVE' }, { human_movement: true }).kind).toBe('idle');
        expect(planState({ ...working, target: null }, { human_movement: true }).kind).toBe('idle');
    });

    it('falls back to the generic inspection for an unknown or missing animation key', () => {
        expect(planState({ ...working, activity: { animation_key: 'dance' } }, { human_movement: true }).anim).toBe('work_inspect');
        expect(planState({ ...working, activity: {} }, { human_movement: true }).anim).toBe('work_inspect');
    });
});

describe('findRoute', () => {
    it('walks straight inside one zone', () => {
        const r = findRoute(layout, { x: 1, y: 1, zone: 'area:1' }, { x: 50, y: 60, zone: 'area:1' });
        expect(r).toEqual([{ x: 50, y: 60, zone: 'area:1' }]);
    });

    it('routes through the corridor between zones and ends at the target', () => {
        const r = findRoute(layout, { x: 1, y: 1, zone: 'office' }, { x: 520, y: 340, zone: 'area:2' });
        expect(r[0]).toMatchObject({ x: 100, y: 200, zone: 'office' });
        expect(r.at(-1)).toEqual({ x: 520, y: 340, zone: 'area:2' });
        expect(r.some((s) => s.y === 250)).toBe(true);
    });

    it('routes from the nearest node when the zone is unknown (mid-corridor)', () => {
        const r = findRoute(layout, { x: 300, y: 250, zone: null }, { x: 520, y: 340, zone: 'area:2' });
        expect(r.at(-1).zone).toBe('area:2');
    });
});

describe('staleness and backoff', () => {
    it('flags stale data', () => {
        expect(isStale(null, 1000, 30)).toBe(true);
        expect(isStale(0, 29_000, 30)).toBe(false);
        expect(isStale(0, 31_000, 30)).toBe(true);
    });

    it('backs off after failures, capped at 30s', () => {
        expect(nextDelaySec(8, 0)).toBe(8);
        expect(nextDelaySec(8, 1)).toBe(16);
        expect(nextDelaySec(8, 5)).toBe(30);
    });
});

describe('diffMachines', () => {
    const m = (id, over = {}) => ({ id, number: 'M' + id, x: 1, y: 2, ...over });

    it('creates only the machines in use and nothing when idle', () => {
        expect(diffMachines(new Map(), [])).toEqual({ add: [], update: [], remove: [] });
        expect(diffMachines(new Map(), [m(1), m(2)]).add.map((x) => x.id)).toEqual([1, 2]);
    });

    it('does not touch unchanged machines on a repeat poll', () => {
        const current = new Map([[1, m(1)], [2, m(2)]]);
        expect(diffMachines(current, [m(1), m(2)])).toEqual({ add: [], update: [], remove: [] });
    });

    it('updates only the machine whose position or flag changed', () => {
        const current = new Map([[1, m(1)], [2, m(2)]]);
        const d = diffMachines(current, [m(1), m(2, { x: 50 })]);
        expect(d.update.map((x) => x.id)).toEqual([2]);
        expect(d.add).toEqual([]);
        expect(diffMachines(current, [m(1, { number: 'M1b' }), m(2)]).update.map((x) => x.id)).toEqual([1]);
    });

    it('removes machines no longer in use', () => {
        const current = new Map([[1, m(1)], [2, m(2)]]);
        expect(diffMachines(current, [m(2)]).remove).toEqual([1]);
        expect(diffMachines(current, undefined).remove).toEqual([1, 2]);
    });
});

describe('dynamic props (machines, groups, oil stations)', () => {
    const snap = (over = {}) => ({
        machines: [{ id: 1, number: '10001', x: 1, y: 2 }],
        groups: [{ id: 5, name: 'WWD 01', x: 3, y: 4 }],
        oil_stations: [{ area_id: 2, x: 5, y: 6 }],
        ...over,
    });

    it('draws groups and the oil station only while they are in the snapshot', () => {
        expect(snapshotProps(snap()).map((p) => p.key)).toEqual(['m:1', 'g:5', 'o:2']);
        expect(snapshotProps(snap({ groups: [], oil_stations: [] })).map((p) => p.key)).toEqual(['m:1']);
        expect(snapshotProps({})).toEqual([]);
    });

    it('removes a group marker and the oil station once the activity is gone', () => {
        const current = new Map(snapshotProps(snap()).map((p) => [p.key, p]));
        const d = diffProps(current, snapshotProps(snap({ groups: [], oil_stations: [] })));
        expect(d.remove.sort()).toEqual(['g:5', 'o:2']);
        expect(d.add).toEqual([]);
        expect(d.update).toEqual([]);
    });

    it('leaves everything untouched when nothing changed', () => {
        const current = new Map(snapshotProps(snap()).map((p) => [p.key, p]));
        expect(diffProps(current, snapshotProps(snap()))).toEqual({ add: [], update: [], remove: [] });
    });
});

describe('initialView', () => {
    const world = { width: 2400, height: 970 };
    const viewport = { width: 1066, height: 600 };

    it('starts closer than the whole map (which would be tiny)', () => {
        const v = initialView({ world, viewport });
        expect(v.fit).toBeLessThan(0.5);
        expect(v.zoom).toBeGreaterThan(0.85);
        expect(v.zoom).toBeLessThanOrEqual(1);
    });

    it('with no workers, starts at the top-left corner', () => {
        const v = initialView({ world, viewport });
        expect(v.cx).toBeCloseTo(viewport.width / v.zoom / 2);
        expect(v.cy).toBeCloseTo(viewport.height / v.zoom / 2);
    });

    it('centres on the zone with the most workers', () => {
        const workers = [
            { x: 100, y: 300, zone: 'area:1' },
            { x: 1700, y: 500, zone: 'area:5' }, { x: 1900, y: 700, zone: 'area:5' }, { x: 1800, y: 600, zone: 'area:5' },
        ];
        const v = initialView({ world, viewport, workers });
        expect(v.cx).toBeCloseTo(1800);
        expect(v.cy).toBeCloseTo(600);
    });

    it('never zooms out past the whole map on a small world, and stays readable on phones', () => {
        const small = initialView({ world: { width: 500, height: 300 }, viewport });
        expect(small.zoom).toBeCloseTo(small.fit);
        expect(initialView({ world, viewport: { width: 390, height: 500 } }).zoom).toBeGreaterThanOrEqual(0.55);
    });
});
