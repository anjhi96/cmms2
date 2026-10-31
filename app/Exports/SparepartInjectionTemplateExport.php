<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class SparepartInjectionTemplateExport implements FromArray
{
    public function array(): array
    {
        return [
            ['Month', 'SAP Number', 'Qty', 'Order Number'],
            ['October', 'MAT-0001', 2, 'ORD-0001'],
        ];
    }
}
