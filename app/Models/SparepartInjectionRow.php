<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SparepartInjectionRow extends Model
{
    public const PROCESSED = 'PROCESSED';

    public const SKIPPED = 'SKIPPED';

    public const FAILED = 'FAILED';

    protected $fillable = [
        'import_id', 'row_number', 'month', 'order_number', 'sap_number', 'qty', 'status', 'reason',
        'pm_schedule_id', 'sparepart_id',
    ];

    /** "2026-10" => "October 2026"; unparseable raw input is shown as typed. */
    public function monthLabel(): string
    {
        $date = \DateTime::createFromFormat('!Y-m', (string) $this->month);

        return $date ? $date->format('F Y') : (string) $this->month;
    }
}
