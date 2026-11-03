/**
 * PM Status Board — status override toggle (view-only).
 *
 * Lets an admin/koordinator flip a row's displayed status between OPEN and
 * CLOSED. Purely cosmetic: the override lives only in this browser's
 * localStorage, keyed by PM schedule id, and is never sent to the server —
 * PMStatusBoardController keeps computing the real OPEN/CLOSED value from
 * PMSchedule.status on every request.
 *
 * Each stored override remembers the server status it was made against
 * (`base`). When a row is rendered with a different server status — i.e. the
 * real data changed since, typically because the PM got closed — the override
 * is stale and is dropped instead of applied.
 *
 * Overrides only apply when the page marks itself with
 * [data-pm-can-override] (set by the controller's $canOverrideStatus, i.e.
 * admin/koordinator). Without it this script does nothing, so a guest never
 * sees an overridden status, even if this browser still has stored ones.
 *
 * Rows can be appended after load (see load-month.js), so everything works
 * per root element and clicks are delegated from the document.
 */

const STORAGE_KEY = 'pm_status_board_overrides_v1';

const opposite = (status) => (status === 'CLOSED' ? 'OPEN' : 'CLOSED');

function canOverride() {
    return typeof document !== 'undefined' && !!document.querySelector('[data-pm-can-override]');
}

function readOverrides() {
    try {
        const raw = JSON.parse(localStorage.getItem(STORAGE_KEY)) || {};
        const overrides = {};

        Object.entries(raw).forEach(([id, value]) => {
            if (typeof value === 'string') {
                // Legacy format (status only): an override always flips the
                // server status, so the base is the opposite.
                overrides[id] = { status: value, base: opposite(value) };
            } else if (value && value.status && value.base) {
                overrides[id] = value;
            }
        });

        return overrides;
    } catch (e) {
        return {};
    }
}

function writeOverrides(overrides) {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(overrides));
    } catch (e) {
        // localStorage unavailable (private mode, quota, ...) — override
        // still applies for this page view, it just won't persist/reload.
    }
}

function applyStatus(cell, status, isOverridden) {
    const badge = cell.querySelector('[data-pm-status-badge]');
    const tag = cell.querySelector('[data-pm-status-override-tag]');

    if (badge) {
        badge.textContent = status;
        badge.classList.toggle('bg-green-100', status === 'CLOSED');
        badge.classList.toggle('text-green-800', status === 'CLOSED');
        badge.classList.toggle('bg-orange-100', status === 'OPEN');
        badge.classList.toggle('text-orange-800', status === 'OPEN');
    }

    if (tag) {
        tag.classList.toggle('hidden', !isOverridden);
    }
}

/**
 * Apply stored overrides to every status cell under `root`, dropping the
 * ones that no longer match the server status.
 */
function applyOverrides(root = document) {
    if (!canOverride()) {
        return;
    }

    const overrides = readOverrides();
    let changed = false;

    root.querySelectorAll('[data-pm-status-cell]').forEach((cell) => {
        const id = cell.dataset.pmStatusId;
        const entry = overrides[id];

        if (!entry) {
            return;
        }

        if (entry.base !== cell.dataset.pmStatusOriginal) {
            delete overrides[id];
            changed = true;
            return;
        }

        applyStatus(cell, entry.status, true);
    });

    if (changed) {
        writeOverrides(overrides);
    }
}

function toggleOverride(button) {
    const cell = button.closest('[data-pm-status-cell]');
    if (!cell) {
        return;
    }

    const id = cell.dataset.pmStatusId;
    const original = cell.dataset.pmStatusOriginal;
    const badge = cell.querySelector('[data-pm-status-badge]');
    const current = badge ? badge.textContent.trim() : original;
    const next = opposite(current);

    const overrides = readOverrides();

    if (next === original) {
        delete overrides[id];
    } else {
        overrides[id] = { status: next, base: original };
    }

    writeOverrides(overrides);

    // Desktop table + mobile card carry the same schedule id.
    document.querySelectorAll(`[data-pm-status-cell][data-pm-status-id="${id}"]`).forEach((c) => {
        applyStatus(c, next, next !== original);
    });
}

function initPmStatusOverride() {
    if (!canOverride()) {
        return;
    }

    applyOverrides(document);

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-pm-status-toggle]');
        if (button) {
            toggleOverride(button);
        }
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initPmStatusOverride);
    } else {
        initPmStatusOverride();
    }
}

export { initPmStatusOverride, applyOverrides };
