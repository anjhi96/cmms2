<?php

namespace App\Services\WeeklyPlan;

use Carbon\CarbonInterface;

/**
 * Day and month names as the coordinator writes them in the production email
 * ("Nopember", not "November").
 */
final class IndoDate
{
    private const DAYS = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu', 7 => 'Minggu'];

    private const MONTHS = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'Nopember', 12 => 'Desember',
    ];

    public static function dayName(CarbonInterface $date): string
    {
        return self::DAYS[$date->isoWeekday()];
    }

    /** "9 Nopember 2026" */
    public static function date(CarbonInterface $date): string
    {
        return $date->day.' '.self::MONTHS[$date->month].' '.$date->year;
    }

    /** "Senin, 9 Nopember 2026" */
    public static function dayDate(CarbonInterface $date): string
    {
        return self::dayName($date).', '.self::date($date);
    }

    /** "9 - 13 Nopember 2026", or "30 Nopember - 4 Desember 2026" across months. */
    public static function range(CarbonInterface $start, CarbonInterface $end): string
    {
        if ($start->month === $end->month && $start->year === $end->year) {
            return $start->day.' - '.self::date($end);
        }

        if ($start->year === $end->year) {
            return $start->day.' '.self::MONTHS[$start->month].' - '.self::date($end);
        }

        return self::date($start).' - '.self::date($end);
    }
}
