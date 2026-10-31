// Procedural pixel-art sprites. No external image assets: each avatar preset
// (config/vme.php) is drawn once onto a canvas sprite sheet (1 logical pixel =
// 1 canvas pixel) and scaled up with nearest-neighbour filtering.
//
// What each animation looks like is data in animations.js (poses); this file
// only knows how to draw a pose and the tools. Characters face right in the
// sheet; the game flips the sprite to face left.

import { ALL_KEYS, ANIMATIONS } from './animations.js';

export const FW = 24; // frame width: body (16 px) plus room for tools on both sides
export const FH = 24;
const BODY_X = 4;

function shade(hex, amount) {
    const n = parseInt(hex.slice(1), 16);
    const c = (v) => Math.max(0, Math.min(255, v + amount));
    return '#' + [(n >> 16) & 255, (n >> 8) & 255, n & 255].map((v) => c(v).toString(16).padStart(2, '0')).join('');
}

/** Two-pixel-thick line from the shoulder to the hand. */
function line(px, x0, y0, x1, y1, color) {
    const steps = Math.max(Math.abs(x1 - x0), Math.abs(y1 - y0), 1);
    for (let i = 0; i <= steps; i++) {
        px(Math.round(x0 + ((x1 - x0) * i) / steps), Math.round(y0 + ((y1 - y0) * i) / steps), 2, 2, color);
    }
}

// Tool renderers: (px, hx, hy, params) with (hx, hy) = the hand holding the tool.
export const TOOLS = {
    wrench(px, hx, hy, { angle = 0 }) {
        const steel = '#c5ced8';
        const dark = '#7d8894';
        if (angle === 0) {
            px(hx, hy - 7, 1, 8, dark);
            px(hx - 1, hy - 9, 3, 2, steel);
        } else if (angle === 1) {
            for (let i = 0; i < 6; i++) px(hx + i, hy - i, 1, 1, dark);
            px(hx + 5, hy - 8, 3, 2, steel);
        } else {
            px(hx, hy, 6, 1, dark);
            px(hx + 5, hy - 1, 2, 3, steel);
        }
    },
    panelDoor(px, hx, hy, { open }) {
        px(hx + 2, hy - 6, 2, 12, '#6b7580');
        if (open) {
            px(hx + 4, hy - 6, 4, 12, '#8d9aa6');
            px(hx + 5, hy - 4, 2, 1, '#3ddc84');
            px(hx + 5, hy - 1, 2, 1, '#f5c242');
        } else {
            px(hx + 4, hy - 6, 1, 12, '#8d9aa6');
        }
    },
    vial(px, hx, hy, { fill }) {
        px(hx + 1, hy - 4, 3, 6, '#dff3ff');
        px(hx + 1, hy - 5, 3, 1, '#9aa5b1');
        if (fill) px(hx + 2, hy - 1, 1, 3, '#d98a1c');
    },
    clipboard(px, hx, hy, { pen }) {
        px(hx, hy - 6, 6, 8, '#8a6239');
        px(hx + 1, hy - 5, 4, 6, '#fbfbf5');
        px(hx + 1, hy - 4, 3, 1, '#9aa5b1');
        px(hx + 1, hy - 2, 4, 1, '#9aa5b1');
        px(hx + 2, hy - 7, 2, 1, '#c5ced8');
        // pen of the writing hand scribbles across the sheet
        px(hx + 1 + (pen % 3), hy - 2 + (pen % 2), 1, 1, '#1b3a8a');
    },
    greaseGun(px, hx, hy, { f }) {
        px(hx, hy - 1, 4, 3, '#d35400');                  // barrel
        px(hx + 4, hy, 2, 1, '#777777');                  // nozzle
        px(hx + (f === 1 ? 1 : 0), hy + 2, 2, 3, '#4a4a4a'); // pump handle squeezes on frame 1
        if (f === 2) px(hx + 6, hy, 2, 2, '#e6c200');     // grease appears
        if (f === 3) { px(hx + 6, hy + 2, 2, 2, '#e6c200'); px(hx + 6, hy + 5, 1, 1, '#e6c200'); }
    },
    cloth(px, hx, hy, { f }) {
        px(hx - 1, hy - 2, 4, 5, f % 2 ? '#f2eecf' : '#e8e1a0');
        px(hx, hy - 3, 2, 1, '#cfc88a');
    },
    lens(px, hx, hy, { f }) {
        const dx = [0, 1, 2, 1][f];
        px(hx + dx, hy - 3, 5, 1, '#cfe8ff');
        px(hx + dx, hy + 1, 5, 1, '#cfe8ff');
        px(hx + dx, hy - 2, 1, 3, '#cfe8ff');
        px(hx + dx + 4, hy - 2, 1, 3, '#cfe8ff');
        px(hx + dx + 1, hy - 2, 3, 3, 'rgba(160,215,255,0.45)');
        px(hx - 1, hy, 2, 4, '#7a5a3a');
        if (f === 2) px(hx + dx + 2, hy - 2, 1, 1, '#ffffff');
    },
    meter(px, hx, hy, { blink }) {
        px(hx, hy - 3, 5, 5, '#2b3138');
        px(hx + 1, hy - 2, 3, 2, blink ? '#9fe8ff' : '#4aa8d8');
        px(hx + 1, hy + 1, 1, 1, '#e0a526');
    },
    panel(px, hx, hy, { lift }) {
        px(hx - 4, hy - 6, 8, 7, '#8d9aa6');
        px(hx - 3, hy - 5, 6, 5, '#a9b4bf');
        px(hx - 3, hy - 4, 1, 1, '#4a5560');
        px(hx + 2, hy - 4, 1, 1, '#4a5560');
        px(hx - 3, hy - 1, 1, 1, '#4a5560');
        px(hx + 2, hy - 1, 1, 1, '#4a5560');
    },
};

/** Draw one pose of a character facing right at x offset `ox` of the sheet. */
export function drawFigure(ctx, ox, pal, pose) {
    const px = (x, y, w, h, color) => {
        ctx.fillStyle = color;
        ctx.fillRect(ox + BODY_X + x, y, w, h);
    };

    const bob = pose.bob || 0;
    const headDy = pose.headDy || 0;
    const legs = pose.legs || {};
    const lift = (v) => (typeof v === 'number' ? Math.max(v, 0) : 0);
    const dark = shade(pal.pants, -25);
    const legTop = 16 + bob;

    // legs + shoes (feet stay on the ground line; a crouch shortens the legs)
    for (const [x, l] of [[5, lift(legs.l)], [8, lift(legs.r)]]) {
        px(x, legTop, 3, Math.max(2, 22 - legTop - l), pal.pants);
        px(x, 22 - l, 3, 2, dark);
    }

    // back arm (behind the torso), then torso, then front arm
    const back = pose.back || REST.back;
    const front = pose.front || REST.front;
    line(px, 3, 10 + bob, back[0], back[1] + bob, shade(pal.shirt, -25));
    px(back[0], back[1] + bob, 2, 2, pal.skin);

    px(4, 9 + bob, 8, 7, pal.shirt);
    px(4, 15 + bob, 8, 1, shade(pal.shirt, -40));

    // tool in the back hand is drawn before the front arm so the front arm overlaps it
    if (pose.tool && pose.tool.hand === 'back') TOOLS[pose.tool.type]?.(px, back[0], back[1] + bob, pose.tool);
    if (pose.tool?.type === 'meter' && pose.tool.probeHand === 'front') {
        px(front[0] - 1, front[1] + bob, 4, 1, '#9aa5b1');
        px(front[0] + 3, front[1] + bob, 2, 1, '#e0a526');
    }

    line(px, 12, 10 + bob, front[0], front[1] + bob, pal.shirt);
    px(front[0], front[1] + bob, 2, 2, pal.skin);
    if (pose.tool && pose.tool.hand === 'front') TOOLS[pose.tool.type]?.(px, front[0], front[1] + bob, pose.tool);

    // head: skin, hair, eyes (looks toward the machine = to the right)
    px(5, 3 + bob + headDy, 6, 6, pal.skin);
    px(5, 2 + bob + headDy, 6, 2, pal.hair);
    px(4, 3 + bob + headDy, 1, 3, pal.hair);
    px(11, 3 + bob + headDy, 1, 3, pal.hair);
    px(7, 5 + bob + headDy, 1, 1, '#222222');
    px(10, 5 + bob + headDy, 1, 1, '#222222');

    for (const f of pose.fx || []) px(f.x, f.y, f.w, f.h, f.c);
}

/** Sheet layout: frameIndex.map.get('walk') -> [firstFrame, count, rate]. */
export const frameIndex = (() => {
    const map = new Map();
    let i = 0;
    for (const key of ALL_KEYS) {
        const a = ANIMATIONS[key];
        map.set(key, [i, a.frames, a.rate]);
        i += a.frames;
    }
    return { map, total: i };
})();

const REST = { front: [13, 17], back: [2, 17] };

export function createAvatarTextures(scene, avatars) {
    for (const [key, pal] of Object.entries(avatars)) {
        const texKey = 'char_' + key;
        if (scene.textures.exists(texKey)) continue;

        const canvas = document.createElement('canvas');
        canvas.width = FW * frameIndex.total;
        canvas.height = FH;
        const ctx = canvas.getContext('2d');

        for (const name of ALL_KEYS) {
            const [start, count] = frameIndex.map.get(name);
            for (let f = 0; f < count; f++) drawFigure(ctx, (start + f) * FW, pal, ANIMATIONS[name].pose(f));
        }

        const tex = scene.textures.addCanvas(texKey, canvas);
        for (let i = 0; i < frameIndex.total; i++) tex.add(i, 0, i * FW, 0, FW, FH);

        for (const name of ALL_KEYS) {
            const [start, count, rate] = frameIndex.map.get(name);
            scene.anims.create({
                key: `${key}:${name}`,
                frames: Array.from({ length: count }, (_, f) => ({ key: texKey, frame: start + f })),
                frameRate: rate,
                repeat: -1,
            });
        }
    }
}

function canvasTexture(scene, key, w, h, draw) {
    if (scene.textures.exists(key)) return;
    const canvas = document.createElement('canvas');
    canvas.width = w;
    canvas.height = h;
    draw(canvas.getContext('2d'), w, h);
    scene.textures.addCanvas(key, canvas);
}

/** Floor tiles, machine, props. */
export function createWorldTextures(scene) {
    const tile = (key, base, line) => canvasTexture(scene, key, 32, 32, (c) => {
        c.fillStyle = base; c.fillRect(0, 0, 32, 32);
        c.fillStyle = line; c.fillRect(0, 0, 32, 1); c.fillRect(0, 0, 1, 32);
        c.fillRect(16, 16, 1, 1);
    });
    tile('floor_office', '#c9b48a', '#b8a177');
    tile('floor_workshop', '#8d949b', '#7c838a');
    tile('floor_production', '#aeb6bd', '#9fa8b0');
    tile('floor_corridor', '#d8dde1', '#c8ced3');
    tile('floor_ground', '#39424b', '#333b44');

    canvasTexture(scene, 'machine', 28, 24, (c) => {
        c.fillStyle = '#2d4f7c'; c.fillRect(0, 2, 28, 22);
        c.fillStyle = '#5b8fd1'; c.fillRect(2, 4, 24, 18);
        c.fillStyle = '#2b3138'; c.fillRect(5, 7, 12, 8);
        c.fillStyle = '#3ddc84'; c.fillRect(20, 7, 4, 4);
        c.fillStyle = '#f5c242'; c.fillRect(20, 13, 4, 3);
        c.fillStyle = '#2d4f7c'; c.fillRect(4, 0, 6, 3);
    });

    canvasTexture(scene, 'desk', 40, 22, (c) => {
        c.fillStyle = '#6b4a2b'; c.fillRect(0, 4, 40, 18);
        c.fillStyle = '#8a6239'; c.fillRect(1, 5, 38, 7);
        c.fillStyle = '#2b3138'; c.fillRect(14, 0, 12, 6);
        c.fillStyle = '#7fd1ff'; c.fillRect(16, 1, 8, 3);
    });
    canvasTexture(scene, 'board', 56, 32, (c) => {
        c.fillStyle = '#5a4a32'; c.fillRect(0, 0, 56, 32);
        c.fillStyle = '#f3f0e6'; c.fillRect(2, 2, 52, 28);
        c.fillStyle = '#4a90d9'; c.fillRect(6, 6, 18, 3); c.fillRect(6, 13, 30, 2); c.fillRect(6, 19, 24, 2);
        c.fillStyle = '#e07a1f'; c.fillRect(40, 6, 10, 8);
    });
    canvasTexture(scene, 'bench', 56, 24, (c) => {
        c.fillStyle = '#4b5560'; c.fillRect(0, 6, 56, 18);
        c.fillStyle = '#9aa5b1'; c.fillRect(1, 7, 54, 6);
        c.fillStyle = '#d35400'; c.fillRect(8, 2, 8, 6);
        c.fillStyle = '#c5ced8'; c.fillRect(30, 3, 14, 3);
    });
    canvasTexture(scene, 'rack', 44, 40, (c) => {
        c.fillStyle = '#3d4852'; c.fillRect(0, 0, 44, 40);
        for (let r = 0; r < 3; r++) {
            c.fillStyle = '#9aa5b1'; c.fillRect(2, 12 + r * 12, 40, 2);
            c.fillStyle = ['#c0392b', '#2e9a55', '#d9b81f', '#1e6fd0'][r]; c.fillRect(5, 4 + r * 12, 10, 8); c.fillRect(20, 5 + r * 12, 8, 7);
        }
    });
    canvasTexture(scene, 'oil', 22, 28, (c) => {
        c.fillStyle = '#1f2933'; c.fillRect(2, 2, 18, 24);
        c.fillStyle = '#e0a526'; c.fillRect(4, 8, 14, 10);
        c.fillStyle = '#1f2933'; c.fillRect(9, 11, 4, 4);
    });
    canvasTexture(scene, 'groupmark', 22, 20, (c) => {
        c.fillStyle = '#d35400'; c.fillRect(3, 4, 16, 14);
        c.fillStyle = '#f5c242'; c.fillRect(6, 7, 10, 4);
        c.fillStyle = '#777'; c.fillRect(9, 0, 4, 5);
    });
    canvasTexture(scene, 'shadow', 20, 8, (c) => {
        c.fillStyle = 'rgba(0,0,0,0.28)';
        c.fillRect(3, 1, 14, 6); c.fillRect(1, 2, 18, 4);
    });
}
