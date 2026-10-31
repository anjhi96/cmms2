// Click and hit-testing for the VME map, free of Phaser/DOM so it can be unit tested.
//
// Characters and machines are not Phaser "interactive" objects: the click is
// resolved here, against where things are DRAWN (feet position, origin at the
// bottom centre). That way overlapping objects, camera zoom/pan and the crowd
// side-step all behave the same, and the topmost object always wins.

/** Hit boxes in world pixels, relative to the feet point (x, y) of the object. */
export const HIT = Object.freeze({
    pic: { halfWidth: 18, up: 50, down: 6 }, // 24x24 sprite at scale 2, body ~32 px wide
    machine: { halfWidth: 16, up: 26, down: 6 }, // 28x24 image
});

/** A pointer release is a click (not a pan or a pinch) when it barely moved and was quick. */
export function isClick(down, up, { maxMove = 6, maxMs = 500 } = {}) {
    if (!down || !up || down.multi) return false;
    return Math.hypot(up.x - down.x, up.y - down.y) <= maxMove && up.t - down.t <= maxMs;
}

/**
 * The object under a world point.
 *
 * @param {Array<{type: 'pic'|'machine', id: number, x: number, y: number}>} entities
 * @returns {{type: string, id: number}|null} the one drawn in front (larger y); a PIC wins a tie with a machine
 */
export function hitTest(entities, x, y) {
    let best = null;

    for (const e of entities) {
        const box = HIT[e.type];
        if (!box) continue;
        if (x < e.x - box.halfWidth || x > e.x + box.halfWidth) continue;
        if (y < e.y - box.up || y > e.y + box.down) continue;

        const front = !best || e.y > best.y || (e.y === best.y && e.type === 'pic' && best.type !== 'pic');
        if (front) best = e;
    }

    return best ? { type: best.type, id: best.id } : null;
}

/** Entities for hitTest(): drawn characters (rendered position) and the dynamic machine props. */
export function entitiesFrom(chars, props) {
    const out = [];

    for (const [id, c] of chars) out.push({ type: 'pic', id, x: c.motion.renderX, y: c.motion.renderY });

    for (const [key, p] of props) {
        if (key.startsWith('m:')) out.push({ type: 'machine', id: Number(key.slice(2)), x: p.x, y: p.y });
    }

    return out;
}

/** Fill the `__ID__` placeholder of a route template. */
export function detailUrl(template, id) {
    return template.replace('__ID__', encodeURIComponent(String(id)));
}

/** Same selection (type + id)? */
export function sameSelection(a, b) {
    return !!a && !!b && a.type === b.type && a.id === b.id;
}
