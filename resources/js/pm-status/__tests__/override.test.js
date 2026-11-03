// @vitest-environment jsdom
import { describe, it, expect, beforeEach } from 'vitest';
import { applyOverrides } from '../override.js';

const KEY = 'pm_status_board_overrides_v1';

const row = (id, status) => `
    <div data-pm-status-cell data-pm-status-id="${id}" data-pm-status-original="${status}">
        <span data-pm-status-badge class="${status === 'CLOSED' ? 'bg-green-100' : 'bg-orange-100'}">${status}</span>
        <span data-pm-status-override-tag class="hidden">(manual)</span>
    </div>`;

const page = (canOverride, rows) => {
    document.body.innerHTML = `<div ${canOverride ? 'data-pm-can-override' : ''}>${rows}</div>`;
};

const badge = (id) => document.querySelector(`[data-pm-status-id="${id}"] [data-pm-status-badge]`).textContent;
const tagHidden = (id) => document.querySelector(`[data-pm-status-id="${id}"] [data-pm-status-override-tag]`).classList.contains('hidden');
const stored = () => JSON.parse(localStorage.getItem(KEY) || '{}');

describe('pm status override', () => {
    beforeEach(() => localStorage.clear());

    it('applies an override for admins and shows the manual tag', () => {
        localStorage.setItem(KEY, JSON.stringify({ 7: { status: 'CLOSED', base: 'OPEN' } }));
        page(true, row(7, 'OPEN'));

        applyOverrides();

        expect(badge(7)).toBe('CLOSED');
        expect(tagHidden(7)).toBe(false);
    });

    it('never applies overrides for guests, even with a stored one', () => {
        localStorage.setItem(KEY, JSON.stringify({ 7: { status: 'CLOSED', base: 'OPEN' } }));
        page(false, row(7, 'OPEN'));

        applyOverrides();

        expect(badge(7)).toBe('OPEN');
        expect(tagHidden(7)).toBe(true);
        expect(stored()[7]).toBeDefined();
    });

    it('drops an override once the server status no longer matches its base', () => {
        localStorage.setItem(KEY, JSON.stringify({ 7: { status: 'CLOSED', base: 'OPEN' } }));
        page(true, row(7, 'CLOSED'));

        applyOverrides();

        expect(badge(7)).toBe('CLOSED');
        expect(tagHidden(7)).toBe(true);
        expect(stored()[7]).toBeUndefined();
    });

    it('applies overrides to rows appended later by passing their root', () => {
        localStorage.setItem(KEY, JSON.stringify({ 9: { status: 'CLOSED', base: 'OPEN' } }));
        page(true, row(7, 'OPEN'));

        const added = document.createElement('div');
        added.innerHTML = row(9, 'OPEN');
        document.body.append(added);

        applyOverrides(added);

        expect(badge(9)).toBe('CLOSED');
        expect(badge(7)).toBe('OPEN');
    });

    it('reads the legacy status-only format as an override of the opposite status', () => {
        localStorage.setItem(KEY, JSON.stringify({ 7: 'CLOSED' }));
        page(true, row(7, 'OPEN'));

        applyOverrides();

        expect(badge(7)).toBe('CLOSED');
    });
});
