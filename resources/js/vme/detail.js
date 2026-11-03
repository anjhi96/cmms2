// HTML for the VME detail panel. Pure functions (data in, string out) so they can
// be tested; every value coming from the server is escaped.

export const SOURCE_LABEL = {
    PM: 'PM',
    GREASING: 'Greasing',
    OIL_AUDIT: 'Oil Audit',
    OIL_AUDIT_ACTION: 'Oil Audit Action',
    MANUAL: 'Manual Activity',
};

export const PIC_STATUS = {
    WORKING: ['Sedang bekerja', 'bg-emerald-100 text-emerald-700'],
    IDLE: ['Menunggu (belum ada aktivitas)', 'bg-slate-100 text-slate-600'],
    INACTIVE: ['Tidak aktif hari ini', 'bg-amber-100 text-amber-800'],
};

export const AI_STATUS = {
    WORKING: ['Bekerja (menganalisis)', 'bg-emerald-100 text-emerald-700'],
    ALERT: ['Perlu perhatian', 'bg-red-100 text-red-700'],
    IDLE: ['Idle (memantau)', 'bg-slate-100 text-slate-600'],
};

export const PM_STATUS = {
    OPEN: 'Terjadwal',
    IN_PROGRESS: 'Berjalan',
    FINISHED: 'Selesai',
    FINISHED_ON_TIME: 'Selesai tepat waktu',
    MISSED: 'Terlewat',
};

export const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

/** "2026-09-01" -> "1 Sep 2026"; anything unusable -> "-". Works on a date or an ISO date-time. */
export function formatDate(value) {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value ?? ''));
    return m ? `${Number(m[3])} ${MONTHS[Number(m[2]) - 1]} ${m[1]}` : '-';
}

/** ISO date-time -> "08.15" in the browser's time zone; "-" when unusable. */
export function formatTime(value) {
    const d = new Date(value);
    return Number.isNaN(d.getTime()) ? '-' : d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
}

const row = (label, value) => (value === null || value === undefined || value === ''
    ? ''
    : `<div class="flex justify-between gap-3 py-0.5"><dt class="text-slate-500">${esc(label)}</dt><dd class="text-right font-medium text-slate-800">${value}</dd></div>`);

const section = (title, body) => `<section class="mt-4"><h4 class="text-xs font-semibold uppercase tracking-wide text-slate-500">${esc(title)}</h4>${body}</section>`;

const link = (l) => (l && l.url ? `<a href="${esc(l.url)}" class="mt-2 inline-flex rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">${esc(l.label)}</a>` : '');

const empty = (text) => `<p class="mt-1 text-xs text-slate-400">${esc(text)}</p>`;

function pmLine(pm) {
    if (!pm) return null;
    const status = PM_STATUS[pm.status] || pm.status;
    const when = pm.actual_date ? formatDate(pm.actual_date) : formatDate(pm.plan_date);
    return `<a class="text-blue-700 hover:underline" href="${esc(pm.link?.url || '#')}">${esc(pm.order_number || `PM #${pm.id}`)}</a> &middot; ${esc(when)} &middot; ${esc(status)}`
        + `${pm.overdue ? ' <span class="text-red-600">(lewat jadwal)</span>' : ''}${pm.pic ? ` &middot; ${esc(pm.pic)}` : ''}`;
}

/** Panel body for a clicked PIC. */
export function renderPic(d) {
    const [statusText, statusClass] = PIC_STATUS[d.pic.status] || [d.pic.status, 'bg-slate-100 text-slate-600'];
    let html = `<div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium ${statusClass}">${esc(statusText)}</span>`
        + `${d.pic.area ? `<span class="text-xs text-slate-500">Area ${esc(d.pic.area)}</span>` : '<span class="text-xs text-slate-400">Tanpa area</span>'}</div>`;

    if (d.pic.unavailable_reason) {
        html += `<p class="mt-2 text-xs text-amber-800">Alasan: ${esc(d.pic.unavailable_reason)}</p>`;
    }

    if (!d.sync_enabled) {
        html += section('Aktivitas', empty('Sinkronisasi aktivitas sedang OFF, jadi aktivitas PIC tidak dibaca.'));
        return html;
    }

    if (d.activity) {
        const a = d.activity;
        let body = `<dl class="mt-1 text-sm">${row('Sumber', esc(SOURCE_LABEL[a.source] || a.source))}${row('Aktivitas', esc(a.label))}${row('Lokasi', a.location ? esc(a.location) : null)}`
            + `${row('Mulai', esc(formatTime(a.started_at)))}${row('ID record', a.record_id ? esc(a.record_id) : null)}</dl>`;

        if (a.record) {
            const r = a.record;
            body += `<dl class="mt-2 rounded-lg bg-slate-50 p-2 text-xs">${row('No. order', r.order_number ? esc(r.order_number) : null)}${row('Mesin', r.machine_number ? esc(r.machine_number) : null)}`
                + `${row('Group', r.group ? esc(r.group) : null)}${row('Siklus', r.cycle ? esc(r.cycle) : null)}${row('Rencana', r.plan_date ? esc(formatDate(r.plan_date)) : null)}`
                + `${row('Jatuh tempo', r.due_date ? esc(formatDate(r.due_date)) : null)}${row('Status', r.status ? esc(PM_STATUS[r.status] || r.status) : null)}${row('Nama', r.name ? esc(r.name) : null)}</dl>`;
        } else if (a.source === 'OIL_AUDIT' || a.source === 'OIL_AUDIT_ACTION') {
            body += empty('Oil Audit dicatat sebagai penanda harian pada PIC, bukan sebagai record tersendiri.');
        }

        html += section('Aktivitas saat ini', body + link(a.link));
    } else {
        let body = empty('Tidak ada aktivitas aktif.');
        if (d.last_ended) {
            const closed = d.last_ended.reason === 'CLOSED_BY_MONITOR';
            body += `<p class="mt-1 text-xs text-slate-600">Terakhir: ${esc(SOURCE_LABEL[d.last_ended.source] || d.last_ended.source)} ${esc(formatTime(d.last_ended.at))} &mdash; `
                + `${closed ? 'ditutup dari monitor (pekerjaan belum tentu selesai)' : 'selesai'}</p>`;
        }
        html += section('Aktivitas saat ini', body);
    }

    if (d.today?.length) {
        const items = d.today.map((t) => `<li class="py-0.5 text-xs text-slate-600">${esc(formatTime(t.started_at))} &middot; ${esc(SOURCE_LABEL[t.source] || t.source)} &middot; ${esc(t.label)}${t.location ? ` (${esc(t.location)})` : ''}`
            + `${t.status === 'CLOSED_BY_MONITOR' ? ' <span class="text-slate-400">&mdash; ditutup dari monitor</span>' : ''}</li>`).join('');
        html += section('Aktivitas hari ini', `<ul class="mt-1">${items}</ul>`);
    }

    return html;
}

/** Panel body for a clicked machine. */
export function renderMachine(d) {
    const m = d.machine;
    let html = `<dl class="text-sm">${row('Tipe', esc(m.type))}${row('Deskripsi', m.description ? esc(m.description) : null)}${row('Group', m.group ? esc(m.group) : null)}${row('Area', esc(m.area))}`
        + `${row('Status', esc(m.status))}${row('Kritikalitas', m.criticality ? esc(m.criticality) : null)}${row('Dipasang', m.install_date ? esc(formatDate(m.install_date)) : null)}${row('Siklus PM', m.pm_cycle ? esc(m.pm_cycle) : null)}</dl>${link(m.link)}`;

    const pm = d.pm;
    const pmRows = [['Sedang berjalan', pm.running], ['Berikutnya (terjadwal)', pm.next], ['PM terakhir', pm.last]]
        .map(([label, v]) => `<div class="py-0.5 text-xs"><span class="text-slate-500">${label}:</span> ${v ? pmLine(v) : '<span class="text-slate-400">-</span>'}</div>`).join('');
    const est = pm.next_estimated ? `<div class="py-0.5 text-xs"><span class="text-slate-500">Perkiraan berikutnya (siklus):</span> ${esc(formatDate(pm.next_estimated))}</div>` : '';
    html += section('Status PM', pmRows + est);

    const h = d.history;
    let hist = `<p class="mt-1 text-xs text-slate-600">${esc(h.pm_total)} PM tercatat: ${esc(h.pm_finished)} selesai, ${esc(h.pm_open)} berjalan/terjadwal, ${esc(h.pm_missed)} terlewat.</p>`;
    if (h.recent?.length) hist += `<ul class="mt-1">${h.recent.map((p) => `<li class="py-0.5 text-xs text-slate-600">${pmLine(p)}</li>`).join('')}</ul>`;
    if (h.last_oil_audit) hist += `<p class="mt-1 text-xs text-slate-600">Oil Audit terakhir: ${esc(formatDate(h.last_oil_audit.audited_at))} &middot; kondisi ${esc(h.last_oil_audit.condition)}</p>`;
    html += section('Histori', hist);

    const sp = d.spareparts;
    const mandatory = sp.mandatory.length
        ? `<ul class="mt-1">${sp.mandatory.map((s) => `<li class="py-0.5 text-xs text-slate-600">${esc(s.material_number)} &middot; ${esc(s.description)} &middot; ${esc(s.qty)} ${esc(s.unit || '')} <span class="text-slate-400">(stok ${s.stock === null ? '-' : esc(s.stock)})</span></li>`).join('')}</ul>`
        : empty('Belum ada sparepart wajib untuk tipe mesin ini.');
    const used = sp.used.length
        ? `<ul class="mt-1">${sp.used.map((s) => `<li class="py-0.5 text-xs text-slate-600">${esc(s.material_number)} &middot; ${esc(s.description)} &middot; total ${esc(s.total_qty)} ${esc(s.unit || '')} di ${esc(s.pm_count)} PM <span class="text-slate-400">(terakhir ${esc(formatDate(s.last_used))})</span></li>`).join('')}</ul>`
        : empty('Belum ada sparepart yang tercatat dipakai di PM mesin ini.');
    html += section('Sparepart wajib (tipe mesin)', mandatory) + section('Sparepart dipakai di PM', used);

    return html;
}

/** Panel body for a clicked AI character (it only watches). */
export function renderAi(d) {
    const [statusText, statusClass] = AI_STATUS[d.status] || [d.status, 'bg-slate-100 text-slate-600'];
    let html = `<p class="text-xs text-slate-500">${esc(d.character.role)}</p>`
        + `<div class="mt-2 flex flex-wrap items-center gap-2"><span class="rounded-full px-2.5 py-0.5 text-xs font-medium ${statusClass}">${esc(statusText)}</span>`
        + `<span class="text-xs text-slate-500">Analisis terakhir ${d.updated_at ? esc(formatTime(d.updated_at)) : '-'}</span></div>`;

    if (d.restricted) {
        html += section('Ringkasan', empty('Ringkasan analisis AI mencakup seluruh area, jadi hanya tampil untuk peran dengan akses semua area.'));
    } else {
        html += section('Ringkasan hasil terakhir', `<p class="mt-1 text-sm text-slate-700">${esc(d.summary)}</p>`);

        if (d.details?.length) {
            html += section('Rincian', `<dl class="mt-1 text-sm">${d.details.map((r) => row(r.label, esc(r.value))).join('')}</dl>`);
        }
    }

    if (d.source_ref) {
        html += `<p class="mt-3 text-xs text-slate-500">Sumber data: ${esc(d.source_ref.type)} #${esc(d.source_ref.id)}</p>`;
    }

    html += `<p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">Karakter AI hanya memantau. Ia tidak mengerjakan atau mengubah data FreeDOMS. Sumber analisis: ${esc(d.character.source)}.</p>`;

    return html + link(d.link);
}

export function panelTitle(kind, d) {
    if (kind === 'ai') return d.character.name;
    return kind === 'pic' ? d.pic.name : `Mesin ${d.machine.number}`;
}
