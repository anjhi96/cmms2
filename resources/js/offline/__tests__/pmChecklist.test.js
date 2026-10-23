// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import * as Queue from '../queue.js';
import * as Drafts from '../drafts.js';
import { getLocalPmChecklistOverlay } from '../masterData.js';
import {
    PmChecklistBlockedError,
    PmChecklistValidationError,
    buildChecklistsFromForm,
    buildResolvedOperation,
    diffChecklists,
    processQueuedOperation,
    saveOffline,
    validateChecklistsLocally,
} from '../pmChecklist.js';
import { resolveConflictWithReplacement } from '../sync.js';
import { resetTestDatabase } from './helpers.js';

beforeEach(async () => {
    await resetTestDatabase();
    document.head.innerHTML = '<meta name="csrf-token" content="test-token">';
});

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('buildChecklistsFromForm', () => {
    function makeChecklistForm(rows) {
        const form = document.createElement('form');

        rows.forEach((row, i) => {
            Object.entries(row).forEach(([field, value]) => {
                const input = document.createElement('input');

                input.name = `checklists[${i}][${field}]`;
                input.value = value;
                form.appendChild(input);
            });
        });

        return form;
    }

    it('produces a real array, one entry per checklist row, in order', () => {
        const form = makeChecklistForm([
            { machine_checklist_id: '1', clean: 'YES', check: 'NO', lubrication: 'NO', replace: 'NO', remarks: '' },
            { machine_checklist_id: '2', clean: 'NO', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: 'catatan' },
        ]);

        const checklists = buildChecklistsFromForm(form);

        expect(Array.isArray(checklists)).toBe(true);
        expect(checklists).toHaveLength(2);
        expect(checklists[0]).toEqual({ machine_checklist_id: '1', clean: 'YES', check: 'NO', lubrication: 'NO', replace: 'NO', remarks: '' });
        expect(checklists[1].remarks).toBe('catatan');
    });

    it('only includes the whitelisted checklist item fields', () => {
        const form = document.createElement('form');
        const extra = document.createElement('input');

        extra.name = 'checklists[0][unexpected_field]';
        extra.value = 'x';
        form.appendChild(extra);

        const idField = document.createElement('input');

        idField.name = 'checklists[0][machine_checklist_id]';
        idField.value = '5';
        form.appendChild(idField);

        const checklists = buildChecklistsFromForm(form);

        expect(checklists[0]).not.toHaveProperty('unexpected_field');
        expect(checklists[0].machine_checklist_id).toBe('5');
    });

    it('never includes PM_SAVE fields (order_number, measurements, ...) even if present on the page', () => {
        const form = makeChecklistForm([{ machine_checklist_id: '1', clean: 'YES', check: 'NO', lubrication: 'NO', replace: 'NO', remarks: '' }]);
        const stray = document.createElement('input');

        stray.name = 'order_number';
        stray.value = 'ORD-1';
        form.appendChild(stray);

        const checklists = buildChecklistsFromForm(form);

        expect(checklists).toHaveLength(1);
        expect(checklists[0]).not.toHaveProperty('order_number');
    });
});

describe('validateChecklistsLocally', () => {
    it('passes for a non-empty checklist array with valid ids', () => {
        expect(() => validateChecklistsLocally([{ machine_checklist_id: '1', clean: 'YES' }])).not.toThrow();
    });

    it('rejects an empty checklist array', () => {
        expect(() => validateChecklistsLocally([])).toThrow(PmChecklistValidationError);
    });

    it('rejects a row missing machine_checklist_id', () => {
        expect(() => validateChecklistsLocally([{ clean: 'YES' }])).toThrow(PmChecklistValidationError);
    });
});

describe('saveOffline', () => {
    const checklists = [
        { machine_checklist_id: '1', clean: 'YES', check: 'NO', lubrication: 'NO', replace: 'NO', remarks: '' },
        { machine_checklist_id: '2', clean: 'NO', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: '' },
    ];

    it('makes NO network request', async () => {
        const fetchSpy = vi.fn();

        vi.stubGlobal('fetch', fetchSpy);

        await saveOffline({ pmScheduleId: 1, checklists, expectedStatus: 'IN_PROGRESS' });

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('enqueues exactly ONE PM_CHECKLIST_SAVE operation for the whole checklist, not one per row', async () => {
        const record = await saveOffline({ pmScheduleId: 42, checklists, expectedStatus: 'IN_PROGRESS' });

        expect(record.transaction_type).toBe('PM_CHECKLIST_SAVE');
        expect(record.payload.pm_schedule_id).toBe(42);
        expect(record.payload.checklists).toHaveLength(2);
        expect(record.expected_state).toEqual({ status: 'IN_PROGRESS' });

        const all = await Queue.listAll();

        expect(all).toHaveLength(1);
    });

    it('throws PmChecklistValidationError and enqueues nothing for an empty checklist', async () => {
        await expect(saveOffline({ pmScheduleId: 1, checklists: [], expectedStatus: 'IN_PROGRESS' }))
            .rejects.toBeInstanceOf(PmChecklistValidationError);

        expect(await Queue.listAll()).toHaveLength(0);
    });

    it('stores a local "checklist saved offline" overlay, separate from the PM_SAVE overlay', async () => {
        await saveOffline({ pmScheduleId: 42, checklists, expectedStatus: 'IN_PROGRESS' });

        const overlay = await getLocalPmChecklistOverlay(42);

        expect(overlay.local_checklist_status).toBe('saved_offline');
        expect(overlay.pending_checklist_operation_uuid).toBeTruthy();
        expect(overlay).not.toHaveProperty('local_save_status');
    });

    it('blocks a second offline checklist Save for a DIFFERENT PM while one is still pending', async () => {
        await saveOffline({ pmScheduleId: 1, checklists, expectedStatus: 'IN_PROGRESS' });

        await expect(saveOffline({ pmScheduleId: 2, checklists, expectedStatus: 'IN_PROGRESS' }))
            .rejects.toBeInstanceOf(PmChecklistBlockedError);

        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('does not block once the earlier PM_CHECKLIST_SAVE is no longer pending/syncing', async () => {
        const first = await saveOffline({ pmScheduleId: 1, checklists, expectedStatus: 'IN_PROGRESS' });

        await Queue.markSynced(first.operation_uuid);

        await expect(saveOffline({ pmScheduleId: 2, checklists, expectedStatus: 'IN_PROGRESS' })).resolves.toBeTruthy();
    });

    it('survives a simulated page reload (connection reset) — queue durability', async () => {
        const record = await saveOffline({ pmScheduleId: 42, checklists, expectedStatus: 'IN_PROGRESS' });

        const { _resetConnectionForTests } = await import('../db.js');

        _resetConnectionForTests();

        const stillThere = await Queue.getByUuid(record.operation_uuid);

        expect(stillThere.payload.checklists).toHaveLength(2);
    });
});

describe('processQueuedOperation', () => {
    const checklists = [{ machine_checklist_id: '1', clean: 'YES', check: 'NO', lubrication: 'NO', replace: 'NO', remarks: '' }];

    it('on "processed": marks synced, clears the local overlay, and deletes the matching draft', async () => {
        await Drafts.saveDraft({ draftType: 'PM_CHECKLIST', referenceId: 42, payload: { checklists }, userId: null });
        const record = await saveOffline({ pmScheduleId: 42, checklists, expectedStatus: 'IN_PROGRESS' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed', subject_id: 42 }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.SYNCED);
        expect(await getLocalPmChecklistOverlay(42)).toBeNull();
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'PM_CHECKLIST', referenceId: 42 })).toBeUndefined();
    });

    it('on "conflict": marks conflict, keeps the overlay AND the draft (never force-resolved)', async () => {
        await Drafts.saveDraft({ draftType: 'PM_CHECKLIST', referenceId: 42, payload: { checklists }, userId: null });
        const record = await saveOffline({ pmScheduleId: 42, checklists, expectedStatus: 'IN_PROGRESS' });

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'conflict', message: 'Server state has changed.' }) }));

        await processQueuedOperation(record.operation_uuid);

        expect((await Queue.getByUuid(record.operation_uuid)).status).toBe(Queue.QueueStatus.CONFLICT);
        expect(await getLocalPmChecklistOverlay(42)).not.toBeNull();
        expect(await Drafts.getDraftByReference({ userId: null, draftType: 'PM_CHECKLIST', referenceId: 42 })).not.toBeUndefined();
    });

    it('sends the exact envelope shape to /api/sync', async () => {
        const record = await saveOffline({ pmScheduleId: 7, checklists, expectedStatus: 'IN_PROGRESS' });
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) });

        vi.stubGlobal('fetch', fetchSpy);

        await processQueuedOperation(record.operation_uuid);

        const sentBody = JSON.parse(fetchSpy.mock.calls[0][1].body);

        expect(sentBody).toEqual({
            operation_uuid: record.operation_uuid,
            transaction_type: 'PM_CHECKLIST_SAVE',
            payload: record.payload,
            expected_state: record.expected_state,
        });
    });
});

describe('conflict resolution (per-field merge)', () => {
    const local = [
        { machine_checklist_id: '1', clean: 'YES', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: 'oli' },
        { machine_checklist_id: '2', clean: 'NO', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: '' },
    ];
    const server = {
        status: 'IN_PROGRESS',
        checklists: {
            1: { clean: 'NO', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: 'oli' },
            2: { clean: 'NO', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: null },
            3: { clean: 'YES', check: 'YES', lubrication: 'NO', replace: 'NO', remarks: 'hanya server' },
        },
    };

    it('saveOffline sends per-item expected checklists when provided', async () => {
        const expectedChecklists = { 1: server.checklists[1] };
        const record = await saveOffline({ pmScheduleId: 5, checklists: local, expectedStatus: 'IN_PROGRESS', expectedChecklists });

        expect(record.expected_state).toEqual({ status: 'IN_PROGRESS', checklists: expectedChecklists });
    });

    it('diffChecklists returns only fields that differ (blank remark equals null)', () => {
        expect(diffChecklists(local, server.checklists)).toEqual([
            { machine_checklist_id: '1', field: 'clean', local: 'YES', server: 'NO' },
        ]);
    });

    it('buildResolvedOperation applies choices, keeps server-only items, and uses server state as expected_state', () => {
        const diffs = diffChecklists(local, server.checklists);

        const pickServer = buildResolvedOperation({ localChecklists: local, server, diffs, choices: { '1:clean': 'server' } });
        const pickLocal = buildResolvedOperation({ localChecklists: local, server, diffs, choices: { '1:clean': 'local' } });

        expect(pickServer.payload.checklists.find((i) => i.machine_checklist_id === 1).clean).toBe('NO');
        expect(pickLocal.payload.checklists.find((i) => i.machine_checklist_id === 1).clean).toBe('YES');
        expect(pickLocal.payload.checklists.map((i) => i.machine_checklist_id)).toEqual([1, 2, 3]);
        expect(pickLocal.payload.checklists.find((i) => i.machine_checklist_id === 3).remarks).toBe('hanya server');
        expect(pickLocal.expectedState.status).toBe('IN_PROGRESS');
        expect(pickLocal.expectedState.checklists['3'].remarks).toBe('hanya server');
    });

    it('buildResolvedOperation refuses to build while a difference is undecided', () => {
        const diffs = diffChecklists(local, server.checklists);

        expect(() => buildResolvedOperation({ localChecklists: local, server, diffs, choices: {} })).toThrow();
    });
});

describe('resolveConflictWithReplacement', () => {
    const checklists = [{ machine_checklist_id: '1', clean: 'YES', check: 'NO', lubrication: 'NO', replace: 'NO', remarks: '' }];
    const replacement = { payload: { pm_schedule_id: 42, checklists }, expectedState: { status: 'IN_PROGRESS', checklists: {} } };

    async function makeConflict() {
        const record = await saveOffline({ pmScheduleId: 42, checklists, expectedStatus: 'IN_PROGRESS' });

        await Queue.markConflict(record.operation_uuid, { status: 'conflict' });

        return record.operation_uuid;
    }

    it('keeps the old conflict when the resend fails, and drops the temporary replacement', async () => {
        const oldUuid = await makeConflict();

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'conflict', message: 'Berubah lagi' }) }));

        const result = await resolveConflictWithReplacement(oldUuid, replacement, processQueuedOperation);

        expect(result.ok).toBe(false);
        expect(result.error).toBe('Berubah lagi');
        expect((await Queue.getByUuid(oldUuid)).status).toBe(Queue.QueueStatus.CONFLICT);
        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('keeps the old conflict when the network throws', async () => {
        const oldUuid = await makeConflict();

        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        const result = await resolveConflictWithReplacement(oldUuid, replacement, processQueuedOperation);

        expect(result.ok).toBe(false);
        expect(await Queue.getByUuid(oldUuid)).toBeDefined();
        expect(await Queue.listAll()).toHaveLength(1);
    });

    it('removes the old conflict only after the replacement is processed, sending a new uuid', async () => {
        const oldUuid = await makeConflict();
        const fetchSpy = vi.fn().mockResolvedValue({ json: async () => ({ status: 'processed' }) });

        vi.stubGlobal('fetch', fetchSpy);

        const result = await resolveConflictWithReplacement(oldUuid, replacement, processQueuedOperation);
        const sent = JSON.parse(fetchSpy.mock.calls[0][1].body);

        expect(result.ok).toBe(true);
        expect(sent.operation_uuid).not.toBe(oldUuid);
        expect(sent.expected_state).toEqual(replacement.expectedState);
        expect(await Queue.getByUuid(oldUuid)).toBeUndefined();
        expect(await getLocalPmChecklistOverlay(42)).toBeNull();
        expect(await Queue.listByStatus(Queue.QueueStatus.CONFLICT)).toHaveLength(0);
    });

    it('treats already_processed as success too', async () => {
        const oldUuid = await makeConflict();

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ status: 'already_processed' }) }));

        expect((await resolveConflictWithReplacement(oldUuid, replacement, processQueuedOperation)).ok).toBe(true);
        expect(await Queue.getByUuid(oldUuid)).toBeUndefined();
    });
});
