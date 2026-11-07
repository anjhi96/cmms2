<?php

namespace App\Services\WeeklyPlan;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Builds the production email body in the coordinator's existing format:
 *
 *   Planning PM WWD Hari Senin, 9 Nopember 2026:
 *   NDE: 20043 (0.225 ST) - ASEP
 *        20044 (0.225 ST) - JAENAL
 *
 * Rows of a day are grouped by machine family (first word of the type, see
 * MachineFamily); the label is written once
 * and the following rows are indented under it. Days without rows are left out.
 *
 * Every method takes plain rows (see WeeklyPmPlanItem::toRow()) so the same
 * code renders the live plan, a sent snapshot and the revision email.
 */
class EmailComposer
{
    public const GREETING = 'Dear All,';

    /**
     * @param  iterable<array>  $rows
     * @return array{text: string, html: string}
     */
    public function compose(string $area, iterable $rows): array
    {
        $days = $this->groupByDay($rows);

        if ($days->isEmpty()) {
            return ['text' => '', 'html' => ''];
        }

        return [
            'text' => self::GREETING."\n\n".$this->scheduleText($area, $days),
            'html' => '<p style="margin:0 0 12pt 0"><b>'.e(self::GREETING).'</b></p>'.$this->scheduleHtml($area, $days),
        ];
    }

    /** @param  Collection<string, Collection<int, array>>  $days */
    public function scheduleText(string $area, Collection $days): string
    {
        return $days->map(function (Collection $rows, string $day) use ($area) {
            $lines = [$this->heading($area, $day)];

            foreach ($this->typeLines($rows) as [$label, $entry]) {
                $lines[] = $label.$entry;
            }

            return implode("\n", $lines);
        })->implode("\n\n");
    }

    /** @param  Collection<string, Collection<int, array>>  $days */
    public function scheduleHtml(string $area, Collection $days): string
    {
        return $days->map(function (Collection $rows, string $day) use ($area) {
            $html = '<p style="margin:0"><b>'.e($this->heading($area, $day)).'</b></p>'
                .'<table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 12pt 0">';

            foreach ($this->typeLines($rows) as [$label, $entry]) {
                $html .= '<tr><td style="padding:0 4px 0 0;vertical-align:top;white-space:nowrap">'.e(trim($label)).'</td>'
                    .'<td style="padding:0">'.e($entry).'</td></tr>';
            }

            return $html.'</table>';
        })->implode('');
    }

    /** @return Collection<string, Collection<int, array>> keyed by Y-m-d, Monday first */
    public function groupByDay(iterable $rows): Collection
    {
        return collect($rows)
            ->values()
            ->sortBy(fn (array $row, int $index) => sprintf('%s-%06d', $row['day'], $index))
            ->groupBy('day')
            ->map(fn (Collection $dayRows) => $dayRows->values());
    }

    /**
     * "20043 (0.225 ST) - ASEP". The brackets stay even when the construction
     * is empty ("20043 () - ASEP"), so the coordinator can fill it in the
     * email itself. The realization summary passes false: there an empty
     * "()" next to "(rencana ...)" only adds noise.
     */
    public function entry(array $row, bool $keepEmptyBrackets = true): string
    {
        $note = trim((string) ($row['construction_note'] ?? ''));
        $entry = $row['machine_number'];

        if ($note !== '' || $keepEmptyBrackets) {
            $entry .= ' ('.$note.')';
        }

        if (filled($row['pic'] ?? null)) {
            $entry .= ' - '.mb_strtoupper(trim($row['pic']));
        }

        return $entry;
    }

    public function heading(string $area, string $day): string
    {
        return 'Planning PM '.$area.' Hari '.IndoDate::dayDate(CarbonImmutable::parse($day)).':';
    }

    /**
     * @param  Collection<int, array>  $rows
     * @return list<array{0: string, 1: string}> [type label or indent, entry]
     */
    private function typeLines(Collection $rows): array
    {
        $lines = [];

        foreach ($rows->groupBy(fn (array $row) => MachineFamily::of($row['machine_type'])) as $type => $typeRows) {
            $label = $type.': ';
            $indent = str_repeat(' ', mb_strlen($label));

            foreach ($typeRows->values() as $i => $row) {
                $lines[] = [$i === 0 ? $label : $indent, $this->entry($row)];
            }
        }

        return $lines;
    }
}
