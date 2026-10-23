/**
 * FreeDOMS offline-first — generic queued-operation sender (Phase 1, Task
 * 5 extraction from Task 4's pmStart.js, so PM_SAVE can reuse the exact
 * same /api/sync plumbing instead of a second copy of it).
 *
 * Knows NOTHING about what a PM_START or PM_SAVE (or any other
 * transaction_type) actually means — it only sends one already-queued
 * operation's envelope to /api/sync (see Task 2 for the request/response
 * contract) and updates its queue status accordingly. Any
 * transaction-type-specific follow-up (clearing a local PM overlay,
 * deleting a draft, ...) is the caller's job — see pmStart.js and
 * pmSave.js, which each wrap this with their own post-success behavior.
 *
 * Deliberately NOT wired up to anything automatic — no setInterval, no
 * "online" event listener calls this (Task 4 section 17 / Task 5 section
 * unchanged). It exists so a later task's actual sync engine has one
 * shared thing to call for every transaction type.
 */

import { csrfToken } from './csrf.js';
import * as SyncQueue from './queue.js';
import { currentUserId } from './scope.js';

/**
 * @param {string} operationUuid
 * @returns {Promise<{entry: object, body: object}>} the queue entry (as it
 *   was before this call) and the /api/sync response body.
 */
export async function sendQueuedOperation(operationUuid) {
    const entry = await SyncQueue.getByUuid(operationUuid);

    if (!entry) {
        throw new Error(`No queued operation with operation_uuid ${operationUuid}.`);
    }

    await SyncQueue.markSyncing(operationUuid);

    let body;

    try {
        const response = await fetch('/api/sync', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken() ?? '',
            },
            body: JSON.stringify({
                operation_uuid: entry.operation_uuid,
                transaction_type: entry.transaction_type,
                payload: entry.payload,
                expected_state: entry.expected_state,
            }),
        });

        body = await response.json();
    } catch (error) {
        // Network failure DURING the sync attempt itself — the operation
        // must stay retryable, never be dropped. Tagged "network_error" (Task
        // 9A) purely so a queue-draining engine can tell this apart from a
        // business failure when deciding what is safe to auto-retry.
        await SyncQueue.markFailed(operationUuid, String(error?.message ?? error), { statusCode: 'network_error' });
        throw error;
    }

    switch (body.status) {
        case 'processed':
        case 'already_processed':
            await SyncQueue.markSynced(operationUuid, body);
            break;

        // A conflict is never force-resolved here — it is recorded so a
        // later task's UI can show and let the user resolve it.
        // duplicate_audit: the server definitively rejected an Oil Audit for
        // a machine that already has one that day. Retrying can never
        // succeed, so it is recorded as a conflict (never auto-retried, not
        // manually retryable) and its message is kept for the Sync UI.
        case 'conflict':
        case 'payload_mismatch':
        case 'duplicate_audit':
            await SyncQueue.markConflict(operationUuid, body);
            break;

        default:
            // validation_failed / forbidden / failed / in_progress / any
            // unexpected status — recorded as failed-but-retained, never
            // silently dropped. body.status is kept as last_error_status
            // (Task 9A) so a queue-draining engine can tell a transient
            // server failure ("failed"/"in_progress") apart from something
            // that needs a human to fix (validation_failed/forbidden)
            // without re-parsing the free-text error message.
            await SyncQueue.markFailed(operationUuid, body.message ?? body.status ?? 'Unknown sync error', { statusCode: body.status ?? null });
            break;
    }

    return { entry, body };
}

/**
 * Resolves a queued conflict by sending a REPLACEMENT operation (new
 * operation_uuid) and only then removing the old one. The old conflict
 * entry is the user's only copy of their local changes, so it is deleted
 * strictly after the server answers processed/already_processed; on any
 * other outcome (or a network error) it is left untouched. The temporary
 * replacement entry is dropped again on failure, so a failed attempt
 * never leaves a second issue behind — the user just retries from the
 * still-present conflict.
 *
 * @param {string} conflictUuid  the existing conflict operation
 * @param {{ payload: object, expectedState: object }} replacement
 * @param {(operationUuid: string) => Promise<object>} [send]  per-type
 *   processor (does overlay/draft cleanup too); defaults to the bare sender.
 * @returns {Promise<{ ok: boolean, body: object|null, error: string|null }>}
 */
export async function resolveConflictWithReplacement(conflictUuid, { payload, expectedState }, send) {
    const conflict = await SyncQueue.getByUuid(conflictUuid);

    if (!conflict || conflict.status !== SyncQueue.QueueStatus.CONFLICT) {
        throw new Error(`Operasi ${conflictUuid} bukan konflik yang bisa diselesaikan.`);
    }

    const sender = send ?? (async (uuid) => (await sendQueuedOperation(uuid)).body);
    const replacement = await SyncQueue.enqueue({
        transactionType: conflict.transaction_type,
        payload,
        expectedState,
        userId: conflict.user_id ?? currentUserId(),
    });

    let body = null;
    let error = null;

    try {
        body = await sender(replacement.operation_uuid);

        if (body?.status === 'processed' || body?.status === 'already_processed') {
            await SyncQueue.remove(conflictUuid);

            return { ok: true, body, error: null };
        }

        error = body?.message ?? body?.status ?? 'Unknown sync error';
    } catch (e) {
        error = String(e?.message ?? e);
    }

    await SyncQueue.remove(replacement.operation_uuid);

    return { ok: false, body, error };
}
