import { isStale, nextDelaySec } from './logic.js';

const root = document.getElementById('vme-root');

if (root) {
    boot(root).catch((err) => {
        console.error('[VME] failed to start', err);
        setRender(root, 'error', 'gagal', 'Gagal memulai VME: ' + (err?.message || err));
        showBanner('VME gagal dimuat. FreeDOMS tidak terpengaruh; coba muat ulang halaman.');
    });
}

const el = (id) => document.getElementById(id);

/** Rendering status chip + root data-state; never leaves a blank canvas unexplained. */
function setRender(root, state, text, detail) {
    root.dataset.state = state;
    const chip = el('vme-render');
    if (chip) {
        chip.textContent = 'Peta: ' + text;
        const cls = { loading: 'bg-slate-100 text-slate-600', ready: 'bg-emerald-100 text-emerald-700', error: 'bg-red-100 text-red-700' }[state];
        chip.className = 'rounded-full px-3 py-1 font-medium ' + cls;
        chip.title = detail || 'Status rendering peta';
    }
    const overlay = el('vme-overlay');
    if (overlay) {
        if (state === 'error') {
            overlay.innerHTML = '<div class="text-base font-semibold">Peta VME tidak dapat ditampilkan</div>'
                + '<div class="max-w-md text-slate-300">' + esc(detail || 'Terjadi kesalahan saat menggambar peta.') + '</div>'
                + '<div class="max-w-md text-xs text-slate-400">Daftar Aktivitas Aktual di samping tetap akurat. Detail teknis ada di konsol browser (awalan [VME]).</div>'
                + '<button type="button" id="vme-reload" class="mt-1 rounded-lg bg-white/10 px-3 py-1 text-xs hover:bg-white/20">Muat ulang</button>';
            overlay.classList.remove('hidden');
            overlay.classList.add('flex');
            el('vme-reload')?.addEventListener('click', () => location.reload());
        } else {
            overlay.classList.add('hidden');
            overlay.classList.remove('flex');
        }
    }
}

function showBanner(text) {
    const b = el('vme-banner');
    if (!b) return;
    b.textContent = text || '';
    b.classList.toggle('hidden', !text);
}

/** GET JSON with ETag + timeout. Resolves { status, data?, etag? }; never throws. */
async function getJson(url, etag, timeoutMs = 6000) {
    const ctl = new AbortController();
    const timer = setTimeout(() => ctl.abort(), timeoutMs);
    try {
        const res = await fetch(url, {
            signal: ctl.signal,
            credentials: 'same-origin',
            // A redirect means the session expired (login page); don't follow it.
            redirect: 'manual',
            headers: { Accept: 'application/json', ...(etag ? { 'If-None-Match': etag } : {}) },
        });
        if (res.type === 'opaqueredirect') return { status: 401 };
        if (res.status === 304) return { status: 304 };
        if (!res.ok) return { status: res.status };
        return { status: 200, data: await res.json(), etag: res.headers.get('ETag') };
    } catch (e) {
        return { status: 0, error: e };
    } finally {
        clearTimeout(timer);
    }
}

function hhmm(iso) {
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? '-' : d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

function esc(s) {
    return String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
}

function renderList(snapshot) {
    const list = el('vme-list');
    const working = snapshot.pics.filter((p) => p.status === 'WORKING' && p.activity);
    const ended = snapshot.pics.filter((p) => p.status === 'IDLE' && p.last_ended);

    const rows = working.map((p) => `
        <li class="rounded-xl border border-slate-200 p-2">
            <div class="font-medium text-slate-800">${esc(p.name)}</div>
            <div class="text-xs text-slate-600">${esc(p.activity.label)}${p.activity.location ? ' &middot; ' + esc(p.activity.location) : ''}</div>
            <div class="text-xs text-slate-400">sejak ${hhmm(p.activity.started_at)}</div>
        </li>`);

    for (const p of ended) {
        const closed = p.last_ended.reason === 'CLOSED_BY_MONITOR';
        rows.push(`
        <li class="rounded-xl border border-dashed border-slate-200 p-2 text-xs text-slate-500">
            ${esc(p.name)} &mdash; ${closed ? 'ditutup dari monitor (pekerjaan belum tentu selesai)' : 'selesai'} (${esc(p.last_ended.source)}) ${hhmm(p.last_ended.at)}
        </li>`);
    }

    list.innerHTML = rows.join('');
    el('vme-empty').classList.toggle('hidden', rows.length > 0);
}

async function boot(root) {
    let configRes = await getJson(root.dataset.configUrl, null);
    if (configRes.status === 404) {
        showBanner('VME sedang dinonaktifkan.');
        el('vme-sync').textContent = 'VME OFF';
        return;
    }
    if (configRes.status !== 200) throw new Error('config HTTP ' + configRes.status);

    let config = configRes.data;
    let configEtag = configRes.etag;

    setRender(root, 'loading', 'memuat…');

    let world;
    try {
        const { createWorld } = await import('./world.js');
        world = createWorld(root, config, {
            onStatus({ state, message, renderer, characters, machines }) {
                if (state === 'ready') {
                    setRender(root, 'ready', `siap (${renderer})`, `Renderer ${renderer}`);
                } else {
                    setRender(root, 'error', 'gagal', message);
                }
            },
        });
    } catch (err) {
        console.error('[VME] could not load the renderer', err);
        setRender(root, 'error', 'gagal', 'Pustaka peta (Phaser) gagal dimuat: ' + (err?.message || err));
        throw err;
    }

    // Debug aid: window.__vme.stats() / .chars() / .machineIds() in the console.
    window.__vme = world;

    // The scene should be up within a few seconds; if not, say so instead of staying dark.
    setTimeout(() => {
        if (root.dataset.state === 'loading') {
            setRender(root, 'error', 'tidak merespons', 'Peta belum selesai dibangun setelah 10 detik. Kemungkinan WebGL/Canvas browser tidak tersedia atau pustaka peta terblokir.');
        }
    }, 10000);

    el('vme-zoom-in')?.addEventListener('click', () => world.zoomIn());
    el('vme-zoom-out')?.addEventListener('click', () => world.zoomOut());
    el('vme-zoom-fit')?.addEventListener('click', () => world.fit());
    if (window.ResizeObserver) new ResizeObserver(() => world.refresh()).observe(root);

    let snapEtag = null;
    let lastOk = null;
    let failures = 0;
    let latest = null;
    let polls = 0;
    let timer = null;

    function paintHud() {
        const chip = el('vme-sync');
        const stale = isStale(lastOk, Date.now(), config.stale_after_seconds);
        let text; let cls;

        if (!config.features.activity_sync) {
            text = 'Sinkronisasi OFF'; cls = 'bg-slate-100 text-slate-600';
        } else if (stale) {
            text = 'Data tidak segar'; cls = 'bg-red-100 text-red-700';
        } else {
            text = 'Tersinkron'; cls = 'bg-emerald-100 text-emerald-700';
        }

        chip.textContent = text;
        chip.className = 'rounded-full px-3 py-1 font-medium ' + cls;
        el('vme-updated').textContent = lastOk
            ? 'Diperbarui ' + new Date(lastOk).toLocaleTimeString('id-ID')
            : 'Belum ada data';

        if (stale && lastOk !== null) {
            showBanner('Koneksi ke FreeDOMS terganggu. Menampilkan data terakhir; mencoba lagi otomatis.');
        }
    }

    async function refreshConfig() {
        const r = await getJson(root.dataset.configUrl, configEtag);
        if (r.status === 200) {
            config = r.data;
            configEtag = r.etag;
            world.setLayout(config.layout);
        }
    }

    async function poll() {
        const r = await getJson(root.dataset.snapshotUrl, snapEtag);

        if (r.status === 200) {
            snapEtag = r.etag;
            latest = r.data;
            lastOk = Date.now();
            failures = 0;
            showBanner('');
            world.applySnapshot(latest);
            renderList(latest);
        } else if (r.status === 304) {
            lastOk = Date.now();
            failures = 0;
            showBanner('');
        } else if (r.status === 404) {
            showBanner('VME dinonaktifkan oleh admin.');
            failures = 3;
        } else if (r.status === 401 || r.status === 403 || r.status === 419) {
            showBanner('Sesi berakhir atau tidak berwenang. Silakan login ulang.');
            failures = 4;
        } else {
            failures += 1;
            showBanner('Gagal memuat data VME. Menampilkan data terakhir; mencoba lagi otomatis.');
        }

        polls += 1;
        if (polls % 6 === 0) await refreshConfig();
        paintHud();
        schedule();
    }

    function schedule() {
        clearTimeout(timer);
        if (document.hidden) return;
        timer = setTimeout(poll, nextDelaySec(config.poll_seconds, failures) * 1000);
    }

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { clearTimeout(timer); poll(); }
    });
    setInterval(paintHud, 5000);

    await poll();
}
