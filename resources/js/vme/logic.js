// VME client logic that has no Phaser/DOM dependency, so it can be unit
// tested in Node. The server decides WHERE a PIC should be (snapshot
// `target` / `base`); this module only decides how to get there.

import { WORK_ANIM_KEYS, resolveAnimKey } from './animations.js';

export const WORK_ANIMS = WORK_ANIM_KEYS;

/**
 * What a character should be doing right now.
 *
 *  - Not working, INACTIVE, or human_movement OFF  -> idle at base.
 *  - Working with a resolved target                -> at target, work animation.
 *
 * Never invents work: without a snapshot activity the result is always idle.
 */
export function planState(pic, features = {}, options = {}) {
    const idle = { kind: 'idle', x: pic.base.x, y: pic.base.y, zone: pic.zone, anim: 'idle', face: 'right' };

    if (!features.human_movement) return idle;
    if (pic.status !== 'WORKING' || !pic.activity || !pic.target) return idle;

    // An unknown or missing animation key falls back to the generic inspection.
    const anim = resolveAnimKey(pic.activity.animation_key);

    const plan = { kind: 'work', x: pic.target.x, y: pic.target.y, zone: pic.target.zone, anim, face: pic.target.face === 'left' ? 'left' : 'right' };

    // Work done by walking around (e.g. Oil Audit): hop between the route's points every few seconds.
    if (Array.isArray(pic.target.patrol) && pic.target.patrol.length > 1) {
        plan.patrol = pic.target.patrol;
        plan.patrolSeconds = Math.max(1, Number(options.patrolSeconds) || 3);
    }

    return plan;
}

export function buildGraph(layout) {
    const nodes = new Map(layout.waypoints.map((w) => [w.id, w]));
    const adj = new Map([...nodes.keys()].map((id) => [id, []]));

    for (const [a, b] of layout.edges) {
        if (!nodes.has(a) || !nodes.has(b)) continue;
        const na = nodes.get(a);
        const nb = nodes.get(b);
        const w = Math.hypot(na.x - nb.x, na.y - nb.y);
        adj.get(a).push({ to: b, w });
        adj.get(b).push({ to: a, w });
    }

    return { nodes, adj };
}

function entryOf(graph, zone) {
    if (zone == null) return null;
    for (const node of graph.nodes.values()) {
        if (node.zone === zone) return node;
    }
    return null;
}

function nearestNode(graph, pos) {
    let best = null;
    let bestD = Infinity;
    for (const node of graph.nodes.values()) {
        const d = Math.hypot(node.x - pos.x, node.y - pos.y);
        if (d < bestD) { best = node; bestD = d; }
    }
    return best;
}

function dijkstra(graph, from, to) {
    const dist = new Map([[from, 0]]);
    const prev = new Map();
    const open = new Set([from]);

    while (open.size) {
        let u = null;
        for (const id of open) if (u === null || dist.get(id) < dist.get(u)) u = id;
        open.delete(u);
        if (u === to) break;
        for (const { to: v, w } of graph.adj.get(u) || []) {
            const nd = dist.get(u) + w;
            if (nd < (dist.has(v) ? dist.get(v) : Infinity)) {
                dist.set(v, nd);
                prev.set(v, u);
                open.add(v);
            }
        }
    }

    if (!dist.has(to)) return null;
    const path = [to];
    while (path[0] !== from) path.unshift(prev.get(path[0]));
    return path;
}

/**
 * Walk route from `from` ({x,y,zone|null}) to `to` ({x,y,zone}).
 * Returns steps [{x, y, zone}] — `zone` is the zone the character is in once
 * the step is reached (null while in the corridor). Same zone: walk straight.
 */
export function findRoute(layout, from, to) {
    const dest = { x: to.x, y: to.y, zone: to.zone ?? null };

    if (from.zone != null && from.zone === to.zone) return [dest];

    const graph = buildGraph(layout);
    const start = entryOf(graph, from.zone) || nearestNode(graph, from);
    const end = entryOf(graph, to.zone) || nearestNode(graph, to);

    if (!start || !end) return [dest];

    const ids = dijkstra(graph, start.id, end.id);
    if (!ids) return [dest];

    const steps = ids.map((id) => {
        const n = graph.nodes.get(id);
        return { x: n.x, y: n.y, zone: n.zone ?? null };
    });

    return [...steps, dest];
}

/** Data is stale when no successful poll (200 or 304) happened for `staleAfterSec`. */
export function isStale(lastOkMs, nowMs, staleAfterSec) {
    if (lastOkMs == null) return true;
    return (nowMs - lastOkMs) / 1000 > staleAfterSec;
}

/** Polling delay: normal interval, doubling per consecutive failure, capped at 30 s. */
export function nextDelaySec(pollSec, failures) {
    if (failures <= 0) return pollSec;
    return Math.min(30, pollSec * 2 ** Math.min(failures, 4));
}

/**
 * Dynamic machine rendering: compare the machine objects currently drawn
 * (Map keyed by machine id) with the machines in the new snapshot.
 *
 * Returns what to create, what to update (moved or renumbered) and which ids to remove. Unchanged machines are
 * absent from the result, so a poll with no change touches no object.
 */
export function diffMachines(current, next) {
    const add = [];
    const update = [];
    const seen = new Set();

    for (const m of next || []) {
        seen.add(m.id);
        const have = current.get(m.id);
        if (!have) {
            add.push(m);
        } else if (have.x !== m.x || have.y !== m.y || have.number !== m.number) {
            update.push(m);
        }
    }

    const remove = [...current.keys()].filter((id) => !seen.has(id));

    return { add, update, remove };
}

/**
 * Dynamic props (machines, group markers, Oil Audit stations) are all drawn
 * only while a PIC uses them. Turn a snapshot into one keyed list.
 */
export function snapshotProps(snapshot) {
    const props = [];
    for (const m of snapshot.machines || []) {
        props.push({ key: `m:${m.id}`, kind: 'machine', label: m.number, x: m.x, y: m.y });
    }
    for (const g of snapshot.groups || []) {
        props.push({ key: `g:${g.id}`, kind: 'group', label: g.name, x: g.x, y: g.y });
    }
    for (const o of snapshot.oil_stations || []) {
        props.push({ key: `o:${o.area_id}`, kind: 'oil', label: 'OIL AUDIT', x: o.x, y: o.y });
    }
    return props;
}

/** Same as diffMachines, for any keyed props: only changed ones appear in add/update. */
export function diffProps(current, next) {
    const add = [];
    const update = [];
    const seen = new Set();

    for (const p of next || []) {
        seen.add(p.key);
        const have = current.get(p.key);
        if (!have) {
            add.push(p);
        } else if (have.x !== p.x || have.y !== p.y || have.label !== p.label) {
            update.push(p);
        }
    }

    return { add, update, remove: [...current.keys()].filter((k) => !seen.has(k)) };
}

/**
 * First view of the map. Showing the whole (wide) world makes everything tiny,
 * so start closer — at a readable zoom — and look at where the work is:
 * the zone with the most working characters (centred on them), otherwise the
 * top-left corner (Office, Workshop and the first Areas).
 *
 * @param {{ world: {width: number, height: number}, viewport: {width: number, height: number}, workers?: Array<{x: number, y: number, zone: string|null}> }} input
 * @returns {{ zoom: number, cx: number, cy: number, fit: number }}
 */
export function initialView({ world, viewport, workers = [] }) {
    const fit = Math.min(viewport.width / world.width, viewport.height / world.height);
    const readable = Math.max(0.55, Math.min(1, viewport.width / 1150));
    const zoom = Math.max(fit, readable);

    if (!workers.length) {
        return { zoom, fit, cx: viewport.width / zoom / 2, cy: viewport.height / zoom / 2 };
    }

    const byZone = new Map();
    for (const w of workers) {
        const key = w.zone ?? '';
        byZone.set(key, [...(byZone.get(key) || []), w]);
    }
    const crowd = [...byZone.values()].sort((a, b) => b.length - a.length)[0];

    return {
        zoom,
        fit,
        cx: crowd.reduce((n, w) => n + w.x, 0) / crowd.length,
        cy: crowd.reduce((n, w) => n + w.y, 0) / crowd.length,
    };
}
