import Phaser from 'phaser';
import { createAvatarTextures, createWorldTextures } from './sprites.js';
import { CharacterMotion } from './character.js';
import { Crowd, minSpacing, occupancy } from './crowd.js';
import { diffProps, initialView, planState, snapshotProps } from './logic.js';
import { entitiesFrom, hitTest, isClick, sameSelection } from './select.js';

const TEXT = { fontFamily: 'monospace', fontSize: '10px', color: '#ffffff', stroke: '#000000', strokeThickness: 3, resolution: 3 };
// Names/activity get a dark pill so they stay readable on any floor colour.
const NAME = { fontFamily: 'monospace', fontSize: '11px', fontStyle: 'bold', color: '#ffffff', backgroundColor: 'rgba(15,23,42,0.78)', padding: { x: 3, y: 1 }, align: 'center', resolution: 3 };
const MIN_ZOOM_FLOOR = 0.15;
const MAX_ZOOM = 4;

/**
 * Builds the Phaser game. `layout` and `avatars` come from /vme/config; the
 * returned controller applies snapshots from /vme/snapshot. All positions
 * (bases, targets) are decided server-side — this only draws and animates.
 */
export function createWorld(parent, config, hooks = {}) {
    let scene = null;
    // 'initial' = the close-up start view, 'all' = whole map (FIT), 'user' = zoomed/panned by hand.
    let view = 'initial';
    let focused = false;
    let fitZoom = 1;
    const report = (state, extra = {}) => hooks.onStatus?.({ state, ...extra });
    let pending = null;
    let layout = config.layout;
    const chars = new Map();
    const crowd = new Crowd();
    // The clicked PIC / machine ({ type: 'pic'|'machine', id }), highlighted with a ring.
    let selection = null;
    let selectionGfx = null;
    // Props currently drawn, keyed 'm:<machine id>' / 'g:<group id>' / 'o:<area id>'.
    // Only machines, group markers and Oil Audit stations an active PIC is using
    // exist here; they are removed as soon as nobody uses them.
    const machines = new Map();
    let statics = null;

    const controller = {
        applySnapshot(snapshot) {
            if (!scene) { pending = snapshot; return; }
            reconcile(snapshot);
        },
        setLayout(next) {
            layout = next;
            if (scene) drawStatics();
        },
        zoomIn() { zoomBy(1.25); },
        zoomOut() { zoomBy(1 / 1.25); },
        fit() { view = 'all'; fitCamera(); },
        refresh() {
            if (!scene) return;
            game.scale.refresh();
            applyView();
        },
        stats() {
            return {
                ready: !!scene,
                renderer: game.renderer?.type === Phaser.WEBGL ? 'WebGL' : 'Canvas',
                canvas: game.canvas ? { w: game.canvas.width, h: game.canvas.height } : null,
                characters: chars.size,
                machines: machines.size,
                objects: scene ? scene.children.length : 0,
                zoom: scene ? scene.cameras.main.zoom : null,
                view,
            };
        },
        chars() {
            return [...chars.entries()].map(([id, c]) => ({
                id, x: Math.round(c.motion.x), y: Math.round(c.motion.y), rx: Math.round(c.motion.renderX), ry: Math.round(c.motion.renderY), moving: c.motion.path.length > 0,
                state: c.motion.state, anim: c.motion.anim, facing: c.motion.facing, zone: c.motion.zone, plays: c.plays || 0,
                patrol: c.motion.plan?.patrol?.length || 0, patrolTimer: +c.motion.patrolTimer.toFixed(1), active: c.motion.active,
            }));
        },
        /** Highlight (or clear) an object from outside, e.g. when a list row is clicked. */
        select(next) { setSelection(next, false); },
        clearSelection() { setSelection(null, false); },
        /** Screen (CSS px, canvas-relative) position of a drawn object, for tests and tooling. */
        screenPos(type, id) {
            const at = positionOf({ type, id });
            if (!at || !scene) return null;
            const cam = scene.cameras.main;
            return { x: (at.x - cam.worldView.x) * cam.zoom, y: (at.y - 14 - cam.worldView.y) * cam.zoom };
        },
        spacing() { return minSpacing([...chars.values()].map((c) => c.motion)); },
        machineIds() { return [...machines.keys()].filter((k) => k.startsWith('m:')).map((k) => Number(k.slice(2))); },
        propKeys() { return [...machines.keys()]; },
        destroy() { game.destroy(true); },
    };

    function zoomBy(factor) {
        if (!scene) return;
        view = 'user';
        const cam = scene.cameras.main;
        cam.setZoom(Math.max(fitZoom * 0.9, Math.min(MAX_ZOOM, cam.zoom * factor)));
        updateBounds();
    }

    /**
     * Camera bounds = the world, padded just enough that a world smaller than the
     * viewport can be centred (otherwise Phaser pins it to the top-left corner).
     */
    function updateBounds() {
        const cam = scene.cameras.main;
        const padX = Math.max(0, (cam.width / cam.zoom - layout.world.width) / 2);
        const padY = Math.max(0, (cam.height / cam.zoom - layout.world.height) / 2);
        cam.setBounds(-padX, -padY, layout.world.width + padX * 2, layout.world.height + padY * 2);
    }

    function fitCamera() {
        const cam = scene.cameras.main;
        const { width, height } = scene.scale;
        fitZoom = Math.max(MIN_ZOOM_FLOOR, Math.min(width / layout.world.width, height / layout.world.height));
        cam.setZoom(fitZoom);
        updateBounds();
        cam.centerOn(layout.world.width / 2, layout.world.height / 2);
    }

    /** Close-up start view, centred on the busiest zone (see initialView in logic.js). */
    function applyInitialView() {
        const cam = scene.cameras.main;
        const { width, height } = scene.scale;
        const workers = [...chars.values()]
            .filter((c) => c.motion.state === 'WORKING')
            .map((c) => ({ x: c.motion.x, y: c.motion.y, zone: c.motion.zone }));
        const v = initialView({ world: layout.world, viewport: { width, height }, workers });

        fitZoom = Math.max(MIN_ZOOM_FLOOR, v.fit);
        cam.setZoom(v.zoom);
        updateBounds();
        cam.centerOn(v.cx, v.cy);
    }

    function applyView() {
        if (!scene) return;
        if (view === 'initial') applyInitialView();
        else if (view === 'all') fitCamera();
    }

    /** Where an object is drawn (feet), or null when it is not on the map any more. */
    function positionOf(sel) {
        if (sel.type === 'pic') {
            const c = chars.get(sel.id);
            return c ? { x: c.motion.renderX, y: c.motion.renderY } : null;
        }
        const m = machines.get(`m:${sel.id}`);
        return m ? { x: m.x, y: m.y } : null;
    }

    function setSelection(next, notify = true) {
        if (sameSelection(selection, next)) return;
        selection = next;
        if (!next) selectionGfx?.clear();
        if (notify) hooks.onSelect?.(next);
    }

    function drawSelection() {
        if (!selectionGfx) return;
        selectionGfx.clear();
        if (!selection) return;

        const at = positionOf(selection);
        if (!at) {
            // The thing that was selected left the map (activity ended): drop the ring, tell the panel.
            const gone = selection;
            selection = null;
            hooks.onSelectionLost?.(gone);
            return;
        }

        const pic = selection.type === 'pic';
        selectionGfx.lineStyle(2, 0xfacc15, 1);
        selectionGfx.strokeEllipse(at.x, at.y - (pic ? 2 : 10), pic ? 40 : 40, pic ? 14 : 22);
        selectionGfx.setDepth(at.y + 1);
    }

    function setupCameraControls() {
        const input = scene.input;
        input.addPointer(1);
        const cam = scene.cameras.main;
        updateBounds();
        selectionGfx = scene.add.graphics();

        input.on('wheel', (_p, _o, _dx, dy) => zoomBy(dy < 0 ? 1.15 : 1 / 1.15));

        let pinch = null;
        let down = null;
        const hitAt = (p) => {
            const w = cam.getWorldPoint(p.x, p.y);
            return hitTest(entitiesFrom(chars, machines), w.x, w.y);
        };

        input.on('pointerdown', (p) => {
            down = { x: p.x, y: p.y, t: performance.now(), multi: input.pointer1.isDown && input.pointer2.isDown };
        });

        input.on('pointermove', (p) => {
            const [a, b] = [input.pointer1, input.pointer2];
            if (a.isDown && b.isDown) {
                if (down) down.multi = true;
                const d = Math.hypot(a.x - b.x, a.y - b.y);
                if (pinch) zoomBy(d / pinch);
                pinch = d;
                return;
            }
            pinch = null;

            if (p.isDown && down) {
                // A tiny jitter while clicking is not a pan.
                if (Math.hypot(p.x - down.x, p.y - down.y) <= 4) return;
                view = 'user';
                cam.scrollX -= (p.x - p.prevPosition.x) / cam.zoom;
                cam.scrollY -= (p.y - p.prevPosition.y) / cam.zoom;
            } else if (!p.isDown && game.canvas) {
                // Pointing hand over anything clickable.
                game.canvas.style.cursor = hitAt(p) ? 'pointer' : 'default';
            }
        });

        input.on('pointerup', (p) => {
            pinch = null;
            const click = isClick(down, { x: p.x, y: p.y, t: performance.now() });
            down = null;
            if (!click) return;

            const hit = hitAt(p);
            if (hit) setSelection(hit);
        });
    }

    function drawStatics() {
        statics?.destroy(true);
        statics = scene.add.container(0, 0).setDepth(0);

        const add = (obj, depth) => { obj.setDepth(depth); statics.add(obj); return obj; };
        const { world, zones } = layout;
        const floors = { office: 'floor_office', workshop: 'floor_workshop', production: 'floor_production' };

        // Ground under everything, so no part of the world is an empty dark void.
        add(scene.add.tileSprite(0, 0, world.width, world.height, 'floor_ground').setOrigin(0, 0), 0);

        // corridor
        const office = zones.find((z) => z.type === 'office');
        const prodTop = Math.min(...zones.filter((z) => z.type === 'production').map((z) => z.y), office.y + office.h + 70);
        add(scene.add.tileSprite(0, office.y + office.h, world.width, prodTop - (office.y + office.h), 'floor_corridor').setOrigin(0, 0), 1);

        // Production band spans the full width; Area columns are drawn on top of it,
        // so room reserved for Areas added later already looks like factory floor.
        const prodZones = zones.filter((z) => z.type === 'production');
        if (prodZones.length) {
            const top = prodZones[0].y;
            const h = prodZones[0].h;
            add(scene.add.tileSprite(0, top, world.width, h, 'floor_production').setOrigin(0, 0).setAlpha(0.55), 1);
        }

        for (const z of zones) {
            add(scene.add.tileSprite(z.x, z.y, z.w, z.h, floors[z.type]).setOrigin(0, 0), 1);
            const g = scene.add.graphics();
            g.lineStyle(3, 0x2b3138, 1).strokeRect(z.x, z.y, z.w, z.h);
            add(g, 2);
            add(scene.add.text(z.x + 8, z.y + 6, z.label.toUpperCase(), { ...NAME, fontSize: '13px' }), 9990);
        }

        // Office: a compact lobby. Only the info board — no desks (the map is for PICs and AI agents).
        const o = office;
        add(scene.add.image(o.x + o.w - 50, o.y + 56, 'board').setOrigin(0.5, 1), o.y + 56);
        add(scene.add.text(o.x + o.w - 50, o.y + 60, 'INFO', { ...TEXT, fontSize: '9px' }).setOrigin(0.5, 0), 9990);

        // Workshop furniture
        const w = zones.find((z) => z.type === 'workshop');
        add(scene.add.image(w.x + 70, w.y + 66, 'bench').setOrigin(0.5, 1), w.y + 66);
        add(scene.add.image(w.x + 160, w.y + 66, 'bench').setOrigin(0.5, 1), w.y + 66);
        add(scene.add.image(w.x + w.w - 50, w.y + 76, 'rack').setOrigin(0.5, 1), w.y + 76);
        add(scene.add.text(w.x + w.w - 50, w.y + 80, 'SPAREPART', { ...TEXT, fontSize: '9px' }).setOrigin(0.5, 0), 9990);
    }

    function makeChar(pic, plan) {
        const shadow = scene.add.image(0, 0, 'shadow').setOrigin(0.5, 0.5).setScale(1.6);
        const sprite = scene.add.sprite(0, 0, 'char_' + pic.avatar_key, 0).setOrigin(0.5, 1).setScale(2);
        const label = scene.add.text(0, -52, '', NAME).setOrigin(0.5, 1);
        const box = scene.add.container(plan.x, plan.y, [shadow, sprite, label]);
        // Phaser objects only draw; where the character is and what it does lives in `motion`.
        return { box, sprite, label, motion: new CharacterMotion(plan), avatar: pic.avatar_key, shownAnim: null, shownFacing: null };
    }

    /** Push the motion state to the sprite, touching Phaser only when something changed. */
    function render(ch) {
        const m = ch.motion;
        // Rendered position = route position + the crowd side-step (see crowd.js).
        const x = m.renderX;
        const y = m.renderY;
        if (x !== ch.shownX || y !== ch.shownY) {
            ch.shownX = x;
            ch.shownY = y;
            ch.box.setPosition(x, y).setDepth(y);
        }

        const flip = m.facing === 'left';
        if (flip !== ch.shownFlip) { ch.shownFlip = flip; ch.sprite.setFlipX(flip); }

        // play() only on a real change: calling it every poll would restart the animation.
        if (m.anim !== ch.shownAnim) {
            ch.shownAnim = m.anim;
            ch.plays = (ch.plays || 0) + 1; // debug: counts real animation (re)starts
            ch.sprite.play(`${ch.avatar}:${m.anim}`);
        }
    }

    const PROP = {
        machine: { texture: () => 'machine', text: (p) => p.label, color: () => '#ffffff', dy: 2 },
        group: { texture: () => 'groupmark', text: (p) => p.label, color: () => '#ffffff', dy: 3 },
        oil: { texture: () => 'oil', text: (p) => p.label, color: () => '#ffffff', dy: 3 },
    };

    function reconcileProps(snapshot) {
        const { add, update, remove } = diffProps(machines, snapshotProps(snapshot));

        for (const p of add) {
            const k = PROP[p.kind];
            const image = scene.add.image(p.x, p.y, k.texture(p)).setOrigin(0.5, 1).setDepth(p.y);
            const label = scene.add.text(p.x, p.y + k.dy, k.text(p), { ...TEXT, fontSize: '8px', color: k.color(p) })
                .setOrigin(0.5, 0).setDepth(9990);
            machines.set(p.key, { ...p, image, text: label });
        }

        for (const p of update) {
            const o = machines.get(p.key);
            const k = PROP[p.kind];
            o.image.setTexture(k.texture(p)).setPosition(p.x, p.y).setDepth(p.y);
            o.text.setText(k.text(p)).setPosition(p.x, p.y + k.dy).setColor(k.color(p));
            Object.assign(o, { x: p.x, y: p.y, label: p.label });
        }

        for (const key of remove) {
            const o = machines.get(key);
            o.image.destroy();
            o.text.destroy();
            machines.delete(key);
        }
    }

    function reconcile(snapshot) {
        const features = snapshot.features || {};
        reconcileProps(snapshot);
        const seen = new Set();

        for (const pic of snapshot.pics) {
            seen.add(pic.user_id);
            try {
                reconcilePic(pic, features);
            } catch (err) {
                // One bad record must never stop the others from being drawn.
                console.error('[VME] could not draw PIC', pic.user_id, err);
            }
        }

        for (const [id, ch] of chars) {
            if (!seen.has(id)) { ch.box.destroy(true); chars.delete(id); }
        }

        // The first snapshot decides where the start view looks (unless the user already moved it).
        if (!focused && chars.size) {
            focused = true;
            if (view === 'initial') applyInitialView();
        }
    }

    function reconcilePic(pic, features) {
        const plan = planState(pic, features, { patrolSeconds: config.patrol_seconds });
        let ch = chars.get(pic.user_id);

        if (!ch) {
            ch = makeChar(pic, plan);
            ch.motion.spawn(plan); // first appearance: already where it should be, no walk-in
            chars.set(pic.user_id, ch);
            render(ch);
        } else if (ch.avatar !== pic.avatar_key) {
            ch.avatar = pic.avatar_key;
            ch.sprite.setTexture('char_' + pic.avatar_key);
            ch.shownAnim = null; // re-apply the animation with the new avatar's frames
        }

        // "Andi Pratama" -> "Andi P." so two PICs with the same first name stay distinguishable.
        const parts = (pic.name || '').trim().split(/\s+/);
        const first_name = parts.length > 1 ? `${parts[0]} ${parts[parts.length - 1][0]}.` : parts[0];
        const text = pic.activity && (plan.kind === 'work' || !features.human_movement)
            ? `${first_name}\n${pic.activity.label}`
            : first_name;
        if (text !== ch.text) { ch.text = text; ch.label.setText(text); }
        const alpha = pic.status === 'INACTIVE' ? 0.45 : 1;
        if (alpha !== ch.alpha) { ch.alpha = alpha; ch.box.setAlpha(alpha); }

        // Same plan as last poll => nothing changes and the animation keeps running.
        if (ch.motion.setPlan(plan, layout)) render(ch);
    }

    let lastTick = null;

    function update(time) {
        // Real elapsed time, not Phaser's `delta`: Phaser caps and smooths delta when the
        // frame rate drops, which would slow walking and the patrol timer on a weak device.
        // Capped at 1 s so a backgrounded tab does not make characters jump on return.
        const dt = lastTick === null ? 0 : Math.min(1, Math.max(0, (time - lastTick) / 1000));
        lastTick = time;
        // Long frames (slow device, tab coming back) are simulated in small steps so
        // walking, patrol timers and crowd side-stepping stay stable and consistent.
        const list = [...chars.values()];
        const motions = list.map((c) => c.motion);
        const busy = occupancy(motions);
        for (let left = dt; left > 1e-6; ) {
            const h = Math.min(left, 0.05);
            left -= h;
            try {
                crowd.step(motions, h);
            } catch (err) {
                console.error('[VME] crowd error', err);
                for (const m of motions) { m.offX = 0; m.offY = 0; m.speedFactor = 1; }
            }
            for (const ch of list) {
                try {
                    if (ch.motion.active) ch.motion.update(h, busy);
                } catch (err) {
                    // An exception here would stop Phaser's whole loop (blank canvas); drop the path instead.
                    console.error('[VME] movement error', err);
                    ch.motion.path = [];
                }
            }
        }

        for (const ch of list) render(ch);
        drawSelection();
    }

    const size = () => ({ w: Math.max(parent.clientWidth, 320), h: Math.max(parent.clientHeight, 240) });
    const initial = size();

    const game = new Phaser.Game({
        type: Phaser.AUTO,
        parent,
        width: initial.w,
        height: initial.h,
        backgroundColor: '#1b2128',
        // The canvas fills the container (RESIZE); the camera, not the canvas, shows the world.
        scale: { mode: Phaser.Scale.RESIZE, parent },
        render: { pixelArt: true, antialias: false, roundPixels: true },
        input: { touch: { capture: true } },
        scene: {
            create() {
                try {
                    scene = this;
                    createWorldTextures(scene);
                    createAvatarTextures(scene, config.avatars);
                    drawStatics();
                    setupCameraControls();
                    applyInitialView();
                    scene.scale.on('resize', applyView);
                    if (pending) { reconcile(pending); pending = null; }
                    report('ready', controller.stats());
                } catch (err) {
                    console.error('[VME] scene creation failed', err);
                    report('error', { message: 'Peta gagal dibangun: ' + (err?.message || err) });
                }
            },
            update,
        },
    });

    // WebGL context loss (GPU reset, driver issue) leaves a dead canvas: say so.
    game.events.once('ready', () => {
        game.canvas?.addEventListener('webglcontextlost', (e) => {
            e.preventDefault();
            console.error('[VME] WebGL context lost');
            report('error', { message: 'Konteks grafis browser hilang. Muat ulang halaman.' });
        });
    });

    return controller;
}
