// @vitest-environment jsdom
import { describe, it, expect, vi } from 'vitest';
import { moveCard, buildPayload, copyRich, escapeHtml, resetCards } from '../board.js';

const days = ['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'].map((date) => ({ date }));

const card = (id, day = null, extra = {}) => ({
    pm_schedule_id: id,
    machine_number: `M-${id}`,
    machine_type: 'NDE',
    day,
    pic: null,
    construction_note: null,
    ...extra,
});

describe('moveCard', () => {
    it('moves a pool card onto a day and keeps its prefilled PIC and note', () => {
        const state = { items: [], pool: [card(1, null, { pic: 'Asep', construction_note: '0.225 ST' }), card(2)] };

        const next = moveCard(state, '1', '2026-11-09');

        expect(next.pool.map((c) => c.pm_schedule_id)).toEqual([2]);
        expect(next.items).toEqual([expect.objectContaining({ pm_schedule_id: 1, day: '2026-11-09', pic: 'Asep', construction_note: '0.225 ST' })]);
        expect(state.items).toEqual([]);
    });

    it('inserts at the dropped position inside a day', () => {
        const state = { items: [card(1, '2026-11-09'), card(2, '2026-11-09')], pool: [card(3)] };

        const next = moveCard(state, '3', '2026-11-09', 1);

        expect(buildPayload({ ...next, days }).items.map((i) => i.pm_schedule_id)).toEqual([1, 3, 2]);
    });

    it('sends a card back to the pool', () => {
        const state = { items: [card(1, '2026-11-10')], pool: [] };

        const next = moveCard(state, '1', null);

        expect(next.items).toEqual([]);
        expect(next.pool[0].day).toBeNull();
    });
});

it('puts a card sent back to the pool at its due-date position', () => {
    const state = {
        items: [card(1, '2026-11-09', { due_date: '2026-10-15' })],
        pool: [card(2, null, { due_date: '2026-10-01' }), card(3, null, { due_date: '2026-11-20' })],
    };

    expect(moveCard(state, '1', null).pool.map((c) => c.pm_schedule_id)).toEqual([2, 1, 3]);
});

describe('buildPayload', () => {
    it('orders by weekday and blanks empty PIC/note', () => {
        const state = {
            days,
            items: [card(1, '2026-11-11', { pic: '' }), card(2, '2026-11-09', { construction_note: '0.32' })],
        };

        expect(buildPayload(state).items).toEqual([
            { pm_schedule_id: 2, day: '2026-11-09', pic: null, construction_note: '0.32' },
            { pm_schedule_id: 1, day: '2026-11-11', pic: null, construction_note: null },
        ]);
    });
});

describe('copyRich', () => {
    it('writes html and plain text through the Clipboard API when available', async () => {
        const write = vi.fn().mockResolvedValue();
        class ClipboardItem { constructor(data) { this.data = data; } }

        const ok = await copyRich('<b>x</b>', 'x', { navigator: { clipboard: { write } }, ClipboardItem, isSecureContext: true });

        expect(ok).toBe(true);
        expect(Object.keys(write.mock.calls[0][0][0].data)).toEqual(['text/html', 'text/plain']);
    });

    it('falls back to a selection copy on plain http', async () => {
        document.execCommand = vi.fn().mockReturnValue(true);

        const ok = await copyRich('<b>x</b>', 'x', { navigator: {}, document, getSelection: () => window.getSelection(), isSecureContext: false });

        expect(ok).toBe(true);
        expect(document.execCommand).toHaveBeenCalledWith('copy');
        expect(document.body.innerHTML).toBe('');
    });
});

it('escapes html', () => {
    expect(escapeHtml('<a href="x">\'&')).toBe('&lt;a href=&quot;x&quot;&gt;&#39;&amp;');
});

describe('resetCards', () => {
    it('returns every placed card to the pool sorted by due date, keeping PIC and note', () => {
        const state = {
            items: [card(1, '2026-11-09', { due_date: '2026-11-05', pic: 'Asep', construction_note: '0.225 ST' })],
            pool: [card(2, null, { due_date: '2026-10-01' }), card(3, null, { due_date: '2026-11-20' })],
        };

        const next = resetCards(state);

        expect(next.items).toEqual([]);
        expect(next.pool.map((c) => c.pm_schedule_id)).toEqual([2, 1, 3]);
        expect(next.pool[1]).toEqual(expect.objectContaining({ day: null, pic: 'Asep', construction_note: '0.225 ST' }));
        expect(buildPayload({ ...next, days }).items).toEqual([]);
    });
});
