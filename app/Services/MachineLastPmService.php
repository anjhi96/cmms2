<?php

namespace App\Services;

use App\Models\Machine;
use App\Models\MachineLastPmOverride;
use App\Models\MachineLastPmOverrideLog;
use App\Models\PMSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Display-only Last PM override for Machine History. The system Last PM is
 * still read from the real PM transactions (same source Machine History uses:
 * latest pm_schedules.actual_date by machine_number); nothing here writes to
 * machines or pm_schedules.
 */
class MachineLastPmService
{
    public function systemLastPm(Machine $machine): ?Carbon
    {
        $date = PMSchedule::where('machine_number', $machine->machine_number)
            ->whereNotNull('actual_date')
            ->max('actual_date');

        return $date ? Carbon::parse($date) : null;
    }

    public function save(Machine $machine, string $date, string $reason, User $user): MachineLastPmOverride
    {
        return DB::transaction(function () use ($machine, $date, $reason, $user) {
            $existing = MachineLastPmOverride::where('machine_id', $machine->id)->lockForUpdate()->first();

            if ($existing) {
                $log = [
                    'action' => MachineLastPmOverrideLog::ACTION_UPDATE,
                    'previous_override_date' => $existing->override_date,
                    'previous_reason' => $existing->reason,
                ];
                $existing->update([
                    'override_date' => $date,
                    'reason' => $reason,
                    'updated_by' => $user->id,
                ]);
                $override = $existing;
            } else {
                $log = ['action' => MachineLastPmOverrideLog::ACTION_CREATE];
                $override = MachineLastPmOverride::create([
                    'machine_id' => $machine->id,
                    'override_date' => $date,
                    'reason' => $reason,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            MachineLastPmOverrideLog::create($log + [
                'machine_id' => $machine->id,
                'new_override_date' => $date,
                'new_reason' => $reason,
                'performed_by' => $user->id,
            ]);

            return $override;
        });
    }

    /** Returns false when the machine had no active override. */
    public function reset(Machine $machine, User $user): bool
    {
        return DB::transaction(function () use ($machine, $user) {
            $existing = MachineLastPmOverride::where('machine_id', $machine->id)->lockForUpdate()->first();

            if (! $existing) {
                return false;
            }

            MachineLastPmOverrideLog::create([
                'machine_id' => $machine->id,
                'previous_override_date' => $existing->override_date,
                'previous_reason' => $existing->reason,
                'action' => MachineLastPmOverrideLog::ACTION_RESET,
                'performed_by' => $user->id,
            ]);
            $existing->delete();

            return true;
        });
    }
}
