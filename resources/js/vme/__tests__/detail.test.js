import { describe, it, expect } from 'vitest';
import { PM_STATUS, esc, formatDate, panelTitle, renderMachine, renderPic } from '../detail.js';

const pm = (over = {}) => ({ id: 5, order_number: 'ORD-1', plan_date: '2026-10-01', actual_date: '2026-10-02', status: 'FINISHED', pic: 'BUDI', link: { url: '/machine-history/10001/detail/5' }, ...over });

const picData = (over = {}) => ({
    type: 'pic', sync_enabled: true,
    pic: { id: 1, name: 'Andi', area: 'WWD', status: 'WORKING', unavailable_reason: null },
    activity: { source: 'PM', label: 'PM', location: '10001', status: 'ACTIVE', started_at: '2026-10-31T08:00:00+07:00', record_id: 5, record: { order_number: 'ORD-1', machine_number: '10001', status: 'IN_PROGRESS', plan_date: '2026-10-31', due_date: '2026-11-14' }, link: { label: 'Lihat riwayat PM', url: '/machine-history/10001/detail/5' } },
    last_ended: null, today: [],
    ...over,
});

const machineData = (over = {}) => ({
    type: 'machine',
    machine: { id: 1, number: '10001', type: 'NDE', description: 'Pompa', group: 'WWD 01', area: 'WWD', status: 'ACTIVE', criticality: 'A', install_date: '2020-05-01', pm_cycle: '4 week', link: { label: 'Buka riwayat mesin', url: '/machine-history/10001' } },
    pm: { running: pm({ status: 'IN_PROGRESS' }), next: pm({ status: 'OPEN', actual_date: null }), next_estimated: '2026-11-01', last: pm() },
    history: { pm_total: 4, pm_finished: 2, pm_missed: 1, pm_open: 1, recent: [pm()], last_oil_audit: { audited_at: '2026-10-01T09:00:00+07:00', condition: 'GOOD', audited_by: 'X' } },
    spareparts: { mandatory: [{ material_number: 'MAT-1', description: 'Bearing', qty: 2, stock: 12, unit: 'PCS' }], used: [{ material_number: 'MAT-2', description: 'Seal', unit: 'SET', total_qty: 3, pm_count: 2, last_used: '2026-09-01' }] },
    ...over,
});

describe('formatting', () => {
    it('formats dates in Indonesian and survives bad input', () => {
        expect(formatDate('2026-09-01')).toBe('1 Sep 2026');
        expect(formatDate('2026-12-25T10:00:00+07:00')).toBe('25 Des 2026');
        expect(formatDate(null)).toBe('-');
        expect(formatDate('nonsense')).toBe('-');
    });

    it('escapes everything that came from the server', () => {
        expect(esc('<img src=x onerror=alert(1)> & "q"')).toBe('&lt;img src=x onerror=alert(1)&gt; &amp; &quot;q&quot;');
        expect(esc(null)).toBe('');
    });

    it('has an Indonesian label for every PM status', () => {
        for (const s of ['OPEN', 'IN_PROGRESS', 'FINISHED', 'FINISHED_ON_TIME', 'MISSED']) expect(PM_STATUS[s]).toBeTruthy();
    });
});

describe('PIC panel', () => {
    it('shows the status, area and the real activity with its record and link', () => {
        const html = renderPic(picData());
        expect(html).toContain('Sedang bekerja');
        expect(html).toContain('Area WWD');
        expect(html).toContain('Sumber');
        expect(html).toContain('ORD-1');
        expect(html).toContain('10001');
        expect(html).toContain('href="/machine-history/10001/detail/5"');
        expect(html).toContain('Lihat riwayat PM');
    });

    it('never injects markup from a name or label', () => {
        const html = renderPic(picData({ activity: { ...picData().activity, label: '<script>x()</script>', record: null, link: null } }));
        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;script&gt;');
    });

    it('explains an idle PIC and how the last activity ended (monitor close vs finished)', () => {
        const closed = renderPic(picData({ pic: { ...picData().pic, status: 'IDLE' }, activity: null, last_ended: { source: 'PM', reason: 'CLOSED_BY_MONITOR', at: '2026-10-31T09:00:00+07:00' } }));
        expect(closed).toContain('Tidak ada aktivitas aktif.');
        expect(closed).toContain('ditutup dari monitor');
        const done = renderPic(picData({ activity: null, last_ended: { source: 'PM', reason: 'FINISHED', at: '2026-10-31T09:00:00+07:00' } }));
        expect(done).toContain('selesai');
        expect(done).not.toContain('ditutup dari monitor');
    });

    it('says so when activity sync is OFF instead of pretending the PIC is idle', () => {
        const html = renderPic(picData({ sync_enabled: false, activity: null }));
        expect(html).toContain('Sinkronisasi aktivitas sedang OFF');
        expect(html).not.toContain('Tidak ada aktivitas aktif.');
    });

    it('shows the unavailable reason and the list of today', () => {
        const html = renderPic(picData({ pic: { ...picData().pic, status: 'INACTIVE', unavailable_reason: 'Cuti' }, activity: null,
            today: [{ source: 'MANUAL', label: 'ASSEMBLING', location: 'WORKSHOP', started_at: '2026-10-31T08:00:00+07:00', status: 'CLOSED_BY_MONITOR' }] }));
        expect(html).toContain('Tidak aktif hari ini');
        expect(html).toContain('Alasan: Cuti');
        expect(html).toContain('Aktivitas hari ini');
        expect(html).toContain('ASSEMBLING');
    });

    it('explains Oil Audit having no record of its own', () => {
        const html = renderPic(picData({ activity: { ...picData().activity, source: 'OIL_AUDIT', record: null, link: null } }));
        expect(html).toContain('penanda harian');
    });
});

describe('machine panel', () => {
    it('shows machine data, PM status, history and both sparepart lists', () => {
        const html = renderMachine(machineData());
        for (const text of ['NDE', 'Pompa', 'WWD 01', 'Kritikalitas', '4 week', 'Sedang berjalan', 'Berikutnya (terjadwal)', 'PM terakhir', 'Perkiraan berikutnya', '4 PM tercatat', 'Oil Audit terakhir', 'MAT-1', 'stok 12', 'MAT-2', 'total 3 SET di 2 PM']) {
            expect(html).toContain(text);
        }
        expect(html).toContain('href="/machine-history/10001"');
    });

    it('is calm about a machine without any history or spareparts', () => {
        const html = renderMachine(machineData({
            pm: { running: null, next: null, next_estimated: null, last: null },
            history: { pm_total: 0, pm_finished: 0, pm_missed: 0, pm_open: 0, recent: [], last_oil_audit: null },
            spareparts: { mandatory: [], used: [] },
            machine: { ...machineData().machine, description: null, group: null, criticality: null, install_date: null, pm_cycle: null },
        }));
        expect(html).toContain('0 PM tercatat');
        expect(html).toContain('Belum ada sparepart wajib');
        expect(html).toContain('Belum ada sparepart yang tercatat dipakai');
        expect(html).not.toContain('undefined');
        expect(html).not.toContain('null');
    });

    it('marks an overdue next PM', () => {
        const html = renderMachine(machineData({ pm: { ...machineData().pm, next: pm({ status: 'OPEN', actual_date: null, overdue: true }) } }));
        expect(html).toContain('lewat jadwal');
        expect(renderMachine(machineData())).not.toContain('lewat jadwal');
    });

    it('titles the panel', () => {
        expect(panelTitle('pic', picData())).toBe('Andi');
        expect(panelTitle('machine', machineData())).toBe('Mesin 10001');
    });
});
