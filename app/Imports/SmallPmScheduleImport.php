<?php

namespace App\Imports;

use App\Models\SmallPmRow;
use App\Models\SmallPmSchedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Same layout as GreasingScheduleImport, with the row in place of the group.
 * Row 1 is a header and is skipped:
 *   0: order_number (optional)
 *   1: plan_date    (Y-m-d)
 *   2: row          (must match a Small PM row name)
 *   3: cycle        (e.g. "26W")
 *   4: pic          (optional, PIC name in the row's area; unknown names are left empty)
 *   5: remarks      (optional)
 *
 * The tab is chosen on the page, not in the file. A koordinator can only
 * import rows of their own area. Duplicate: row + plan_date + order_number.
 */
class SmallPmScheduleImport implements ToCollection
{
    public function __construct(private readonly string $taskType, private readonly User $user) {}

    public function collection(Collection $rows)
    {
        $imported = 0;
        $duplicate = 0;
        $skipped = 0;

        $smallPmRows = SmallPmRow::query()
            ->when(! $this->user->isAdmin(), fn ($q) => $q->where('area_id', $this->user->area_id))
            ->get()
            ->keyBy(fn (SmallPmRow $r) => mb_strtolower(trim($r->name)));

        foreach ($rows->skip(1) as $row) {
            $orderNumber = trim((string) ($row[0] ?? ''));
            $planDateRaw = trim((string) ($row[1] ?? ''));
            $rowName = trim((string) ($row[2] ?? ''));
            $cycle = trim((string) ($row[3] ?? ''));
            $picName = trim((string) ($row[4] ?? ''));
            $remarks = trim((string) ($row[5] ?? ''));

            if ($orderNumber === '' && $planDateRaw === '' && $rowName === '' && $cycle === '' && $picName === '') {
                continue;
            }

            if (! preg_match('/^\d+W$/i', $cycle)) {
                $skipped++;

                continue;
            }

            try {
                $planDate = Carbon::createFromFormat('!Y-m-d', $planDateRaw);
            } catch (\Throwable) {
                $skipped++;

                continue;
            }

            $target = $smallPmRows->get(mb_strtolower($rowName));

            if (! $target) {
                $skipped++;

                continue;
            }

            if ($orderNumber !== '' && SmallPmSchedule::where('small_pm_row_id', $target->id)
                ->whereDate('plan_date', $planDate)
                ->where('order_number', $orderNumber)
                ->exists()) {
                $duplicate++;

                continue;
            }

            $picId = $picName === '' ? null : User::where('role', User::ROLE_PIC)
                ->where('area_id', $target->area_id)
                ->where('name', $picName)
                ->value('id');

            SmallPmSchedule::create([
                'small_pm_row_id' => $target->id,
                'task_type' => $this->taskType,
                'order_number' => $orderNumber !== '' ? $orderNumber : null,
                'cycle' => strtoupper($cycle),
                'plan_date' => $planDate->toDateString(),
                'due_date' => SmallPmSchedule::calculateDueDate($planDate)->toDateString(),
                'pic_user_id' => $picId,
                'status' => 'OPEN',
                'remarks' => $remarks !== '' ? $remarks : null,
            ]);

            $imported++;
        }

        session()->flash('small_pm_import_result', compact('imported', 'duplicate', 'skipped'));
    }
}
