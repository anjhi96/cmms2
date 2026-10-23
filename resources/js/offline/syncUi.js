/**
 * FreeDOMS offline-first — global Sync Now / status widget (Phase 1, Task
 * 9A).
 *
 * Registers a single Alpine store (`$store.sync`) the topbar partial
 * (resources/views/partials/sync-status.blade.php) binds to, following the
 * same `document.addEventListener('alpine:init', ...)` pattern the sidebar
 * store already uses (resources/views/partials/sidebar.blade.php). This
 * file only reads from/calls into syncEngine.js — it holds no queue-
 * processing or business logic of its own (section 5/17: one engine, one
 * source of truth; the widget is just a view over it).
 */
import * as SyncQueue from './queue.js';
import * as SyncEngine from './syncEngine.js';
import { resolveConflictWithReplacement } from './sync.js';
import * as PmChecklist from './pmChecklist.js';
import { clearLocalPmChecklistOverlay, getLocalPmChecklistOverlay } from './masterData.js';

const STATUS_LABELS = {
    offline: 'Offline',
    syncing: 'Syncing...',
    failed: 'Sync issue',
    conflict: 'Conflict',
    pending: 'Pending',
    synced: 'Synced',
};

function emptyResolver() {
    return { open: false, loading: false, submitting: false, confirmDiscard: false, error: null, operation: null, server: null, diffs: [], choices: {} };
}

document.addEventListener('alpine:init', () => {
    Alpine.store('sync', {
        status: 'offline',
        summary: { pending: 0, syncing: 0, failed: 0, conflict: 0, synced: 0 },
        running: false,
        panelOpen: false,
        failedOperations: [],
        conflictOperations: [],
        unsubscribe: null,

        init() {
            this.refresh();
            this.unsubscribe = SyncEngine.subscribeToSyncChanges(() => this.refresh());
        },

        async refresh() {
            const [status, summary, failedOperations, conflictOperations] = await Promise.all([
                SyncEngine.getSyncStatus(),
                SyncEngine.getSyncSummary(),
                SyncQueue.listByStatus(SyncQueue.QueueStatus.FAILED),
                SyncQueue.listByStatus(SyncQueue.QueueStatus.CONFLICT),
            ]);

            this.status = status;
            this.summary = summary;
            this.running = SyncEngine.isSyncRunning();
            this.failedOperations = failedOperations;
            this.conflictOperations = conflictOperations;
        },

        get label() {
            const issues = this.summary.failed + this.summary.conflict;

            if (issues > 0 && this.status !== 'syncing') {
                return `${issues} sync issue${issues > 1 ? 's' : ''}`;
            }

            const waiting = this.summary.pending + this.summary.syncing;

            if (this.status === 'pending' && waiting > 0) {
                return `${waiting} pending`;
            }

            return STATUS_LABELS[this.status] ?? this.status;
        },

        async syncNow() {
            await SyncEngine.syncPendingOperations();
        },

        async retry(operationUuid) {
            try {
                await SyncEngine.retryOperation(operationUuid);
            } catch (error) {
                console.warn('[offline/syncUi] retry failed', error);
            } finally {
                await this.refresh();
            }
        },

        isManuallyRetryable(operation) {
            return SyncEngine.isManuallyRetryable(operation);
        },

        // --- Conflict resolution (PM Checklist only) -------------------
        resolver: emptyResolver(),

        canResolve(operation) {
            return operation.transaction_type === PmChecklist.TRANSACTION_TYPE;
        },

        async startResolve(operation) {
            this.resolver = { ...emptyResolver(), open: true, loading: true, operation };

            try {
                const response = await fetch(`/api/sync/pm-schedules/${operation.payload.pm_schedule_id}/checklist-state`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(response.status === 403
                        ? 'Anda tidak memiliki akses ke PM Schedule ini.'
                        : 'Data terbaru dari server tidak dapat diambil.');
                }

                const server = await response.json();

                this.resolver.server = server;
                this.resolver.diffs = PmChecklist.diffChecklists(operation.payload.checklists, server.checklists);
            } catch (error) {
                this.resolver.error = error instanceof TypeError
                    ? 'Tidak dapat terhubung ke server. Coba lagi saat online.'
                    : String(error?.message ?? error);
            } finally {
                this.resolver.loading = false;
            }
        },

        closeResolve() {
            this.resolver = emptyResolver();
        },

        choiceKey(diff) {
            return `${diff.machine_checklist_id}:${diff.field}`;
        },

        setChoice(diff, side) {
            this.resolver.choices = { ...this.resolver.choices, [this.choiceKey(diff)]: side };
        },

        applyAll(side) {
            const choices = {};

            this.resolver.diffs.forEach((diff) => {
                choices[this.choiceKey(diff)] = side;
            });
            this.resolver.choices = choices;
        },

        get canSubmitResolve() {
            const r = this.resolver;

            return !!r.server && !r.submitting && r.diffs.every((diff) => r.choices[this.choiceKey(diff)]);
        },

        itemLabel(id) {
            return this.resolver.server?.labels?.[id] ?? `Item #${id}`;
        },

        async submitResolve() {
            const r = this.resolver;

            if (!this.canSubmitResolve) {
                return;
            }

            r.submitting = true;
            r.error = null;

            try {
                const { payload, expectedState } = PmChecklist.buildResolvedOperation({
                    localChecklists: r.operation.payload.checklists,
                    server: r.server,
                    diffs: r.diffs,
                    choices: r.choices,
                });
                const result = await resolveConflictWithReplacement(
                    r.operation.operation_uuid,
                    { payload: { ...payload, pm_schedule_id: r.operation.payload.pm_schedule_id }, expectedState },
                    SyncEngine.processorFor(r.operation.transaction_type)
                );

                if (result.ok) {
                    this.closeResolve();
                } else {
                    // Old conflict is still queued; the server may have
                    // changed again, so the user re-opens to re-compare.
                    r.error = result.error;
                }
            } catch (error) {
                r.error = String(error?.message ?? error);
            } finally {
                r.submitting = false;
                await this.refresh();
            }
        },

        // Discarding local changes is a separate, explicit step: the panel
        // first shows exactly what would be thrown away (confirmDiscard).
        askDiscard() {
            this.resolver.confirmDiscard = true;
        },

        async discardLocal() {
            const r = this.resolver;
            const uuid = r.operation.operation_uuid;

            try {
                await SyncQueue.remove(uuid);

                const overlay = await getLocalPmChecklistOverlay(r.operation.payload.pm_schedule_id).catch(() => null);

                if (overlay?.pending_checklist_operation_uuid === uuid) {
                    await clearLocalPmChecklistOverlay(r.operation.payload.pm_schedule_id);
                }

                this.closeResolve();
            } catch (error) {
                r.error = String(error?.message ?? error);
            } finally {
                await this.refresh();
            }
        },

        labelFor(transactionType) {
            return SyncEngine.TRANSACTION_LABELS[transactionType] ?? transactionType;
        },
    });
});
