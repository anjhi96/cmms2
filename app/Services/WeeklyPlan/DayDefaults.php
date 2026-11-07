<?php

namespace App\Services\WeeklyPlan;

use App\Models\WeeklyPmPlan;
use Carbon\CarbonImmutable;

final class DayDefaults
{
    /**
     * Starting limits and type mix of a week, from config/weekly_plan.php: used
     * for a new week and by "Kembalikan ke default".
     *
     * @return array{day_limits: array<string, int>, day_types: array<string, list<array{type: string, count: int}>>}
     */
    public static function for(string $area, CarbonImmutable $monday): array
    {
        $days = config("weekly_plan.days.$area");
        $fallback = config('weekly_plan.fallback', ['limit' => WeeklyPmPlan::DEFAULT_DAY_LIMIT, 'types' => []]);
        $limits = [];
        $types = [];

        foreach (range(0, 4) as $i) {
            $date = $monday->addDays($i)->toDateString();
            $day = $days[$i] ?? $fallback;
            $limits[$date] = min(WeeklyPmPlan::MAX_DAY_LIMIT, max(0, (int) ($day['limit'] ?? WeeklyPmPlan::DEFAULT_DAY_LIMIT)));

            if (! empty($day['types'])) {
                $types[$date] = array_map(fn ($row) => ['type' => MachineFamily::of($row['type']), 'count' => (int) $row['count']], $day['types']);
            }
        }

        return ['day_limits' => $limits, 'day_types' => $types];
    }
}
