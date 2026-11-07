<?php

namespace App\Services\WeeklyPlan;

use App\Models\PMSchedule;
use App\Models\WeeklyPmPlan;
use App\Models\WeeklyPmPlanItem;

/**
 * Last week's plan of the same area compared with what the PM Schedule says
 * actually happened (status + actual_date). Returns null when there was no
 * plan last week, so the page shows nothing instead of an invented zero.
 */
class RealizationService
{
    public function __construct(private readonly EmailComposer $email) {}

    public function previousPlan(WeeklyPmPlan $plan): ?WeeklyPmPlan
    {
        return WeeklyPmPlan::query()
            ->where('area', $plan->area)
            ->whereDate('week_start', $plan->week_start->subWeek()->toDateString())
            ->with('items.pmSchedule')
            ->first();
    }

    /**
     * @return array{week: string, total: int, on_day: int, other_day: list<string>, open: list<string>, text: string, html: string}|null
     */
    public function summary(WeeklyPmPlan $plan): ?array
    {
        $previous = $this->previousPlan($plan);

        if (! $previous || $previous->items->isEmpty()) {
            return null;
        }

        $onDay = 0;
        $otherDay = [];
        $open = [];

        foreach ($previous->items as $item) {
            /** @var WeeklyPmPlanItem $item */
            $schedule = $item->pmSchedule;
            $row = $item->toRow();
            $label = MachineFamily::of($row['machine_type']).': '.$this->email->entry($row, false);
            $planned = IndoDate::dayDate($item->day);

            if ($schedule && in_array($schedule->status, PMSchedule::DONE_STATUSES, true)) {
                $actual = PlanBoardService::date($schedule->actual_date);

                if ($actual === null || $actual->isSameDay($item->day)) {
                    $onDay++;
                } else {
                    $otherDay[] = $label.' (rencana '.$planned.', selesai '.IndoDate::dayDate($actual).')';
                }

                continue;
            }

            $open[] = $label.' (rencana '.$planned.')';
        }

        $total = $previous->items->count();
        $done = $onDay + count($otherDay);
        $week = IndoDate::range($previous->week_start, $previous->weekEnd());
        $title = 'Realisasi Planning PM '.$plan->area.' minggu '.$week.': '.$done.' dari '.$total.' mesin selesai.';

        $sections = array_filter([
            'Selesai di hari lain:' => $otherDay,
            'Belum selesai:' => $open,
        ]);

        $text = $title;
        $html = '<p style="margin:0 0 12pt 0"><b>'.e($title).'</b></p>';

        foreach ($sections as $heading => $lines) {
            $text .= "\n\n".$heading."\n".implode("\n", $lines);
            $html .= '<p style="margin:0"><b>'.e($heading).'</b></p><p style="margin:0 0 12pt 0">'
                .implode('<br>', array_map('e', $lines)).'</p>';
        }

        return [
            'week' => $week,
            'total' => $total,
            'on_day' => $onDay,
            'other_day' => $otherDay,
            'open' => $open,
            'text' => $text,
            'html' => $html,
        ];
    }
}
