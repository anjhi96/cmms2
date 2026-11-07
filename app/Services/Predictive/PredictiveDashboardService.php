<?php

namespace App\Services\Predictive;

use App\Models\PredictiveWorkOrder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Every figure on the Predictive dashboard comes from one filtered WO set
 * (see workOrders()), so the overview, Finding summary and Problem summary
 * can never disagree about which WOs they cover. All counting is done by the
 * database; nothing here loads WO or action rows into memory.
 *
 * Filter meanings:
 *  - period: WO date (wo_date) between from/to, inclusive.
 *  - category / area / machine type: columns of the WO.
 *  - PIC: WOs the PIC currently holds a claim on, or has saved an Action
 *    for. A closed WO has no claim anymore, so the Action author is the only
 *    lasting link between a PIC and a WO.
 * Finding and Problem counts use every Action of the matching WOs, including
 * the ones saved after a Reopen.
 */
class PredictiveDashboardService
{
    public const TOP_LIMIT = 10;

    /**
     * @param  array{date_from?: ?string, date_to?: ?string, category?: ?string, area?: ?string, machine_type?: ?string, pic?: int|string|null}  $filters
     */
    public function workOrders(User $user, array $filters): Builder
    {
        $query = PredictiveWorkOrder::query()->visibleToUser($user, $this->clean($filters['area'] ?? null));

        if ($from = $this->date($filters['date_from'] ?? null)) {
            $query->whereDate('predictive_work_orders.wo_date', '>=', $from);
        }

        if ($to = $this->date($filters['date_to'] ?? null)) {
            $query->whereDate('predictive_work_orders.wo_date', '<=', $to);
        }

        if (($category = $this->clean($filters['category'] ?? null)) && isset(PredictiveWorkOrder::CATEGORIES[$category])) {
            $query->where('predictive_work_orders.category', $category);
        }

        if ($type = $this->clean($filters['machine_type'] ?? null)) {
            $query->where('predictive_work_orders.machine_type', $type);
        }

        $pic = $filters['pic'] ?? null;
        if ($pic !== null && $pic !== '' && ctype_digit((string) $pic)) {
            $picId = (int) $pic;
            $query->where(function (Builder $q) use ($picId) {
                $q->where('predictive_work_orders.pic_user_id', $picId)
                    ->orWhereHas('actions', fn (Builder $a) => $a->where('pic_user_id', $picId));
            });
        }

        return $query;
    }

    /**
     * @return array{total: int, open: int, closed: int, closing_rate: float}
     */
    public function overview(Builder $workOrders): array
    {
        $row = (clone $workOrders)
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN predictive_work_orders.status = 'OPEN' THEN 1 ELSE 0 END), 0) as open_count")
            ->selectRaw("COALESCE(SUM(CASE WHEN predictive_work_orders.status = 'CLOSED' THEN 1 ELSE 0 END), 0) as closed_count")
            ->first();

        $total = (int) $row->total;
        $closed = (int) $row->closed_count;

        return [
            'total' => $total,
            'open' => (int) $row->open_count,
            'closed' => $closed,
            'closing_rate' => $total > 0 ? round($closed / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * Each Finding row counts once, whatever Problem or Action it sits under.
     *
     * @return Collection<int, object{label: string, total: int}>
     */
    public function findingSummary(Builder $workOrders, int $limit = self::TOP_LIMIT): Collection
    {
        $query = DB::table('predictive_action_findings as f')
            ->join('predictive_action_problems as p', 'p.id', '=', 'f.problem_id')
            ->join('predictive_actions as a', 'a.id', '=', 'p.action_id')
            ->whereIn('a.work_order_id', (clone $workOrders)->select('predictive_work_orders.id')->toBase());

        return $this->rank($query, 'f.finding', $limit);
    }

    /**
     * Each Problem row counts once per Action it was recorded in.
     *
     * @return Collection<int, object{label: string, total: int}>
     */
    public function problemSummary(Builder $workOrders, int $limit = self::TOP_LIMIT): Collection
    {
        $query = DB::table('predictive_action_problems as p')
            ->join('predictive_actions as a', 'a.id', '=', 'p.action_id')
            ->whereIn('a.work_order_id', (clone $workOrders)->select('predictive_work_orders.id')->toBase());

        return $this->rank($query, 'p.problem', $limit);
    }

    /**
     * Grouped on LOWER(TRIM(x)) so "Bearing aus" and " bearing AUS " count as
     * one entry; the label shown is the alphabetically first spelling.
     */
    private function rank(\Illuminate\Database\Query\Builder $query, string $column, int $limit): Collection
    {
        $key = "LOWER(TRIM({$column}))";

        return $query
            ->selectRaw("MIN(TRIM({$column})) as label")
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw($key)
            ->orderByDesc('total')
            ->orderByRaw($key)
            ->limit($limit)
            ->get();
    }

    private function clean(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function date(mixed $value): ?string
    {
        $value = $this->clean($value);
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}
