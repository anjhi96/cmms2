<?php

namespace App\Services\WeeklyPlan;

/**
 * The coordinator plans and emails by machine family, the first word of the
 * machine type: "NDE SC 2003/2007", "NDE SW" and "NDE SW MONO" are all NDE.
 * machines.machine_type itself is left untouched.
 */
final class MachineFamily
{
    public static function of(?string $machineType): string
    {
        $words = preg_split('/\s+/', trim((string) $machineType), 2);

        return mb_strtoupper($words[0] ?? '');
    }
}
