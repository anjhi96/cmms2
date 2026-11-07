<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

/**
 * Template for Predictive Problem > Import. Category is VIBRATION or
 * AIR_LEAKED, Area is an active area name. One row per Finding; repeat the
 * Problem to give it several Findings. Finding may be empty to create only the
 * Problem.
 */
class PredictiveProblemFindingTemplateExport implements FromArray, ShouldAutoSize
{
    public function array(): array
    {
        return [
            ['Category', 'Area', 'Problem', 'Finding'],
            ['VIBRATION', 'WWD', 'Bearing aus', 'Sisi motor'],
            ['VIBRATION', 'WWD', 'Bearing aus', 'Sisi gearbox'],
            ['VIBRATION', 'WWD', 'Baut kendor', 'Dudukan motor'],
            ['AIR_LEAKED', 'WWD', 'Selang bocor', 'Sambungan quick coupler'],
        ];
    }
}
