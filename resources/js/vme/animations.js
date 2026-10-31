// Single registry of every character animation. Pure data (no Phaser, no DOM)
// so it is shared by the sprite generator, the movement logic and the tests.
//
// To add an animation: add one entry here (frames, rate, pose) and, if it uses
// a new tool, one renderer in sprites.js TOOLS. Then point a job type at it
// (job_types.animation_key) and list the key in config/vme.php 'animations'.
// Nothing in the character controller changes.
//
// A pose describes ONE frame of a character facing right, in body coordinates
// (the body is 16 px wide; x grows to the right, y grows down):
//   bob      body drops by n px (crouch / breathing)
//   headDy   head offset
//   legs     { l, r } leg lift in px, or `crouch`
//   front    hand of the arm closest to the machine [x, y]
//   back     hand of the other arm [x, y] (omit = arm hangs down)
//   tool     { type, hand: 'front' | 'back', ...params } — drawn at that hand
//   fx       extra pixels drawn after everything [{ x, y, w, h, c }]

const down = { front: [13, 17], back: [2, 17] };

export const ANIMATIONS = {
    idle: {
        frames: 2, rate: 2, label: 'Diam',
        pose: (f) => ({ bob: f, ...down }),
    },

    walk: {
        frames: 4, rate: 8, label: 'Berjalan',
        pose: (f) => ({
            bob: f % 2,
            legs: { l: [0, 2, 0, -2][f], r: [0, -2, 0, 2][f] },
            front: [13, 16 + [0, 1, 0, -1][f]],
            back: [2, 16 - [0, 1, 0, -1][f]],
        }),
    },

    // Generic hammering / tightening.
    work_tool: {
        frames: 3, rate: 5, label: 'Memakai perkakas',
        pose: (f) => ({ bob: f === 1 ? 1 : 0, front: [14, [9, 12, 9][f]], back: [2, 17], tool: { type: 'wrench', hand: 'front', angle: 0 } }),
    },

    // PM: turn a wrench on the machine, then open and check the panel.
    work_pm: {
        frames: 6, rate: 5, label: 'PM: memutar kunci dan memeriksa panel',
        pose: (f) => {
            if (f < 4) {
                return { bob: f % 2, front: [13, 11], back: [10, 12], tool: { type: 'wrench', hand: 'front', angle: [0, 1, 2, 1][f] } };
            }
            return { front: [14, 10 + (f === 5 ? 1 : 0)], back: [2, 17], tool: { type: 'panelDoor', hand: 'front', open: f === 5 } };
        },
    },

    // Oil Audit: crouch, dip a sample tube in the machine, raise it and read it against the light.
    work_oil_sample: {
        frames: 6, rate: 3, label: 'Oil Audit: mengambil dan memeriksa sampel oli',
        pose: (f) => {
            const fill = f >= 2;
            return [
                { bob: 3, legs: { crouch: true }, front: [15, 17], back: [9, 17], tool: { type: 'vial', hand: 'front', fill: false } },
                { bob: 3, legs: { crouch: true }, front: [16, 19], back: [9, 17], tool: { type: 'vial', hand: 'front', fill: false } },
                { bob: 3, legs: { crouch: true }, front: [15, 16], back: [9, 17], tool: { type: 'vial', hand: 'front', fill } },
                { bob: 0, front: [13, 11], back: [2, 17], tool: { type: 'vial', hand: 'front', fill } },
                { bob: 0, headDy: -1, front: [13, 5], back: [2, 17], tool: { type: 'vial', hand: 'front', fill }, fx: [{ x: 15, y: 2, w: 1, h: 1, c: '#fff6c9' }] },
                { bob: 0, headDy: -1, front: [13, 5], back: [2, 17], tool: { type: 'vial', hand: 'front', fill } },
            ][f];
        },
    },

    // Action Audit Oli: look at the machine, write the finding on a clipboard.
    work_oil_action: {
        frames: 4, rate: 3, label: 'Action Audit Oli: memeriksa dan mencatat',
        pose: (f) => ({
            bob: 0,
            headDy: [0, 1, 1, 0][f],
            front: [[11, 12], [12, 13], [11, 14], [11, 12]][f],
            back: [8, 13],
            tool: { type: 'clipboard', hand: 'back', pen: f },
        }),
    },

    work_grease: {
        frames: 4, rate: 4, label: 'Greasing: grease gun',
        pose: (f) => ({ bob: 0, front: [12, 12], back: [9, 13], tool: { type: 'greaseGun', hand: 'front', f } }),
    },

    work_clean: {
        frames: 4, rate: 6, label: 'Cleaning: mengelap',
        pose: (f) => {
            const y = [8, 12, 16, 12][f];
            return {
                bob: 0, legs: { l: 0, r: 0 }, front: [14, y], back: [2, 17],
                tool: { type: 'cloth', hand: 'front', f },
                fx: f === 3 ? [{ x: 17, y: 10, w: 1, h: 1, c: '#ffffff' }, { x: 19, y: 13, w: 1, h: 1, c: '#ffffff' }] : [],
            };
        },
    },

    // Generic inspection (also the fallback for Manual Activity without a mapping).
    work_inspect: {
        frames: 4, rate: 3, label: 'Inspeksi umum',
        pose: (f) => ({
            bob: 0, headDy: 0, front: [14, 9], back: [2, 17],
            tool: { type: 'lens', hand: 'front', f },
        }),
    },

    work_measure: {
        frames: 4, rate: 3, label: 'Pengukuran',
        pose: (f) => ({
            bob: 0, front: [15, 13 + (f % 2 ? 1 : 0)], back: [8, 14],
            tool: { type: 'meter', hand: 'back', blink: f % 2, probeHand: 'front' },
        }),
    },

    work_panel: {
        frames: 4, rate: 4, label: 'Mengganti filter / blower panel',
        pose: (f) => {
            const lift = [0, -2, -4, -2][f];
            return { bob: 0, front: [14, 9 + lift], back: [9, 9 + lift], tool: { type: 'panel', hand: 'front', lift } };
        },
    },
};

export const FALLBACK_WORK = 'work_inspect';

export const ALL_KEYS = Object.keys(ANIMATIONS);

export const WORK_ANIM_KEYS = ALL_KEYS.filter((k) => k.startsWith('work_'));

/** A work animation that exists, otherwise the generic inspection. */
export function resolveAnimKey(key) {
    return WORK_ANIM_KEYS.includes(key) ? key : FALLBACK_WORK;
}
