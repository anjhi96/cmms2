<?php

namespace App\Services\WeeklyPlan;

use App\Models\WeeklyPmPlan;
use Carbon\CarbonImmutable;

/**
 * Compares the plan with what was last emailed (sent_snapshot) and writes a
 * short revision email: only the changed machines, followed by the full
 * updated schedule so production does not have to merge two emails.
 */
class RevisionComposer
{
    public function __construct(private readonly EmailComposer $email) {}

    /** @return list<array{kind: string, text: string}> */
    public function changes(WeeklyPmPlan $plan): array
    {
        if ($plan->sent_snapshot === null) {
            return [];
        }

        $before = $this->keyed($plan->sent_snapshot);
        $after = $this->keyed($plan->items->map->toRow()->all());
        $changes = [];

        foreach ($after as $key => $row) {
            $old = $before[$key] ?? null;
            $machine = MachineFamily::of($row['machine_type']).' '.$row['machine_number'];

            if ($old === null) {
                $changes[] = ['kind' => 'added', 'text' => 'Tambah: '.MachineFamily::of($row['machine_type']).' '.$this->email->entry($row).', Hari '.$this->day($row['day'])];

                continue;
            }

            if ($old['day'] !== $row['day']) {
                $changes[] = ['kind' => 'moved', 'text' => 'Pindah: '.$machine.' dari Hari '.$this->day($old['day']).' ke Hari '.$this->day($row['day'])];
            }

            if ($this->norm($old['pic']) !== $this->norm($row['pic'])) {
                $changes[] = ['kind' => 'pic', 'text' => 'Ganti PIC: '.$machine.' (Hari '.$this->day($row['day']).'): '.$this->value($old['pic'], true).' menjadi '.$this->value($row['pic'], true)];
            }

            if ($this->norm($old['construction_note']) !== $this->norm($row['construction_note'])) {
                $changes[] = ['kind' => 'note', 'text' => 'Ganti konstruksi: '.$machine.': '.$this->value($old['construction_note']).' menjadi '.$this->value($row['construction_note'])];
            }
        }

        foreach ($before as $key => $old) {
            if (! isset($after[$key])) {
                $changes[] = ['kind' => 'removed', 'text' => 'Batal: '.MachineFamily::of($old['machine_type']).' '.$old['machine_number'].', Hari '.$this->day($old['day'])];
            }
        }

        return $changes;
    }

    /** @return array{text: string, html: string, count: int} */
    public function compose(WeeklyPmPlan $plan): array
    {
        $changes = $this->changes($plan);

        if ($changes === []) {
            return ['text' => '', 'html' => '', 'count' => 0];
        }

        $title = 'Revisi Planning PM '.$plan->area.' minggu '.IndoDate::range($plan->week_start, $plan->weekEnd()).':';
        $days = $this->email->groupByDay($plan->items->map->toRow());

        $text = EmailComposer::GREETING."\n\n".$title."\n"
            .collect($changes)->map(fn ($c) => '- '.$c['text'])->implode("\n");

        $html = '<p style="margin:0 0 12pt 0"><b>'.e(EmailComposer::GREETING).'</b></p>'
            .'<p style="margin:0"><b>'.e($title).'</b></p>'
            .'<ul style="margin:0 0 12pt 0">'.collect($changes)->map(fn ($c) => '<li>'.e($c['text']).'</li>')->implode('').'</ul>';

        if ($days->isNotEmpty()) {
            $text .= "\n\nJadwal terbaru:\n\n".$this->email->scheduleText($plan->area, $days);
            $html .= '<p style="margin:0 0 12pt 0"><b>Jadwal terbaru:</b></p>'.$this->email->scheduleHtml($plan->area, $days);
        }

        return ['text' => $text, 'html' => $html, 'count' => count($changes)];
    }

    /** @return array<string, array> */
    private function keyed(array $rows): array
    {
        $keyed = [];

        foreach ($rows as $row) {
            $keyed[$row['pm_schedule_id'] !== null ? 'pm:'.$row['pm_schedule_id'] : 'm:'.$row['machine_number']] = $row;
        }

        return $keyed;
    }

    private function day(string $date): string
    {
        return IndoDate::dayDate(CarbonImmutable::parse($date));
    }

    private function norm(?string $value): string
    {
        return mb_strtoupper(trim((string) $value));
    }

    private function value(?string $value, bool $upper = false): string
    {
        if (blank($value)) {
            return 'kosong';
        }

        return $upper ? mb_strtoupper(trim($value)) : trim($value);
    }
}
