import { describe, it, expect } from 'vitest';
import { HIT, detailUrl, entitiesFrom, hitTest, isClick, sameSelection } from '../select.js';
import { CharacterMotion } from '../character.js';

const pic = (id, x, y) => ({ type: 'pic', id, x, y });
const machine = (id, x, y) => ({ type: 'machine', id, x, y });

describe('isClick', () => {
    const down = { x: 100, y: 100, t: 1000, multi: false };

    it('a quick press and release in place is a click', () => {
        expect(isClick(down, { x: 102, y: 101, t: 1150 })).toBe(true);
    });

    it('a drag (pan) is not a click', () => {
        expect(isClick(down, { x: 140, y: 100, t: 1150 })).toBe(false);
    });

    it('a long press is not a click', () => {
        expect(isClick(down, { x: 100, y: 100, t: 2000 })).toBe(false);
    });

    it('a pinch (two fingers) is never a click', () => {
        expect(isClick({ ...down, multi: true }, { x: 100, y: 100, t: 1100 })).toBe(false);
    });

    it('missing events are not a click', () => {
        expect(isClick(null, { x: 1, y: 1, t: 1 })).toBe(false);
        expect(isClick(down, null)).toBe(false);
    });
});

describe('hitTest', () => {
    it('finds a PIC by its body (feet at the bottom centre)', () => {
        const e = [pic(7, 200, 300)];
        expect(hitTest(e, 200, 280)).toEqual({ type: 'pic', id: 7 }); // chest
        expect(hitTest(e, 200, 255)).toEqual({ type: 'pic', id: 7 }); // head
        expect(hitTest(e, 200 + HIT.pic.halfWidth, 290)).toEqual({ type: 'pic', id: 7 }); // edge
    });

    it('misses just outside the body', () => {
        const e = [pic(7, 200, 300)];
        expect(hitTest(e, 200, 300 + HIT.pic.down + 1)).toBeNull(); // below the feet
        expect(hitTest(e, 200, 300 - HIT.pic.up - 1)).toBeNull(); // above the head
        expect(hitTest(e, 200 + HIT.pic.halfWidth + 1, 290)).toBeNull(); // beside
        expect(hitTest([], 1, 1)).toBeNull();
    });

    it('finds a machine', () => {
        expect(hitTest([machine(3, 400, 500)], 405, 490)).toEqual({ type: 'machine', id: 3 });
    });

    it('the object drawn in front (larger y) wins when two overlap', () => {
        const e = [pic(1, 200, 300), pic(2, 210, 312)];
        expect(hitTest(e, 205, 300)).toEqual({ type: 'pic', id: 2 });
        expect(hitTest(e.reverse(), 205, 300)).toEqual({ type: 'pic', id: 2 }); // order of the list does not matter
    });

    it('a PIC standing at a machine is clicked as the PIC, the machine beside it as the machine', () => {
        // The game places the worker 34 px to the right of the machine.
        const e = [machine(9, 300, 400), pic(4, 334, 410)];
        expect(hitTest(e, 334, 395)).toEqual({ type: 'pic', id: 4 });
        expect(hitTest(e, 296, 392)).toEqual({ type: 'machine', id: 9 });
    });

    it('on an exact depth tie a PIC wins over a machine', () => {
        expect(hitTest([machine(1, 100, 100), pic(2, 100, 100)], 100, 90)).toEqual({ type: 'pic', id: 2 });
    });

    it('ignores unknown entity types', () => {
        expect(hitTest([{ type: 'tree', id: 1, x: 1, y: 1 }], 1, 1)).toBeNull();
    });
});

describe('entitiesFrom', () => {
    const motionAt = (x, y, offX = 0, offY = 0) => {
        const m = new CharacterMotion({ x, y, zone: 'a' });
        m.offX = offX;
        m.offY = offY;
        return { motion: m };
    };

    it('uses where a PIC is DRAWN (route position + crowd side-step)', () => {
        const chars = new Map([[5, motionAt(100, 200, 12, -4)]]);
        expect(entitiesFrom(chars, new Map())).toEqual([{ type: 'pic', id: 5, x: 112, y: 196 }]);
    });

    it('only machines count as clickable props (not group markers or oil stations)', () => {
        const props = new Map([
            ['m:10', { x: 1, y: 2 }],
            ['g:3', { x: 3, y: 4 }],
            ['o:2', { x: 5, y: 6 }],
        ]);
        expect(entitiesFrom(new Map(), props)).toEqual([{ type: 'machine', id: 10, x: 1, y: 2 }]);
    });

    it('an object that is no longer drawn (activity ended) cannot be hit', () => {
        const chars = new Map([[5, motionAt(100, 200)]]);
        const props = new Map([['m:10', { x: 300, y: 400 }]]);
        props.delete('m:10');
        expect(hitTest(entitiesFrom(chars, props), 300, 390)).toBeNull();
    });

    it('a click lands on the PIC that was pushed aside, not on the spot it was pushed from', () => {
        const chars = new Map([[5, motionAt(100, 200, 40, 0)]]);
        expect(hitTest(entitiesFrom(chars, new Map()), 100, 190)).toBeNull();
        expect(hitTest(entitiesFrom(chars, new Map()), 140, 190)).toEqual({ type: 'pic', id: 5 });
    });
});

describe('helpers', () => {
    it('detailUrl fills the id placeholder and encodes it', () => {
        expect(detailUrl('/vme/pics/__ID__', 12)).toBe('/vme/pics/12');
        expect(detailUrl('/vme/machines/__ID__', '../x')).toBe('/vme/machines/..%2Fx');
    });

    it('sameSelection compares type and id', () => {
        expect(sameSelection({ type: 'pic', id: 1 }, { type: 'pic', id: 1 })).toBe(true);
        expect(sameSelection({ type: 'pic', id: 1 }, { type: 'machine', id: 1 })).toBe(false);
        expect(sameSelection(null, { type: 'pic', id: 1 })).toBe(false);
    });
});
