<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;

/**
 * Final Sparepart Order Estimation list: exactly the quantities the user
 * sees on screen (mandatory rows incl. corrections, plus manual items).
 */
class SparepartPlanningExport implements FromArray
{
    /**
     * @param  array{machines_system: int, machines_final: int, rows: Collection}  $estimate
     */
    public function __construct(private string $machineType, private string $period, private array $estimate) {}

    public function array(): array
    {
        $rows = [[
            'Machine Type', 'PM Period', 'Machines (System)', 'Machines (Final)', 'Material Number', 'Description', 'Unit',
            'Source', 'Mandatory Qty / Machine', 'System Estimate Qty', 'Final Order Qty',
        ]];

        foreach ($this->estimate['rows'] as $row) {
            $rows[] = [
                $this->machineType,
                $this->period,
                $this->estimate['machines_system'],
                $this->estimate['machines_final'],
                $row['material_number'],
                $row['description'],
                $row['unit'],
                $row['source'],
                $row['per_machine'],
                $row['system_qty'],
                $row['order_qty'],
            ];
        }

        return $rows;
    }
}
