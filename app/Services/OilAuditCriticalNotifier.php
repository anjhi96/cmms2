<?php

namespace App\Services;

use App\Jobs\SendOilAuditCriticalNotification;
use App\Models\EmailNotificationSetting;
use App\Models\OilAudit;
use App\Models\OilAuditNotification;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Raises at most ONE KRITIS email event per OilAudit. Called from
 * OilAuditCreateService, which both the online store() and the offline
 * OIL_AUDIT_CREATE sync handler share. Follow-ups never change `condition`,
 * so they are intentionally not a trigger.
 */
class OilAuditCriticalNotifier
{
    public function notify(OilAudit $audit): void
    {
        if ($audit->condition !== 'KRITIS') {
            return;
        }

        // Runs only after the OUTERMOST transaction commits (immediately when
        // none is open) and is discarded on rollback — so a failed/rolled-back
        // audit never produces an email, even inside the sync transaction.
        DB::afterCommit(function () use ($audit) {
            try {
                $this->raise($audit);
            } catch (Throwable $e) {
                // Notification trouble must never break an already-saved audit.
                Log::warning('Oil audit KRITIS notification could not be queued', [
                    'oil_audit_id' => $audit->id,
                    'error' => $e::class,
                ]);
            }
        });
    }

    private function raise(OilAudit $audit): void
    {
        $reason = EmailNotificationSetting::current()->notReadyReason();

        try {
            $notification = OilAuditNotification::create([
                'oil_audit_id' => $audit->id,
                'status' => $reason ? OilAuditNotification::SKIPPED : OilAuditNotification::PENDING,
                'last_error' => $reason,
            ]);
        } catch (UniqueConstraintViolationException) {
            return; // event already exists for this audit — never a second email
        }

        if (! $reason) {
            SendOilAuditCriticalNotification::dispatch($notification->id);
        }
    }
}
