<?php

namespace App\Imports;

use App\Models\Sparepart;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;

/**
 * Bulk-upserts rows via chunked Sparepart::upsert() instead of one
 * updateOrCreate() per row (2 queries/row). A large CSV (thousands of rows)
 * was hitting PHP's max_execution_time with the old row-by-row approach;
 * batching keeps the whole import to a handful of queries.
 */
class SparepartsImport implements ToCollection
{
    private const CHUNK_SIZE = 500;

    public function collection(Collection $rows)
    {
        $skippedCount = 0;
        $data = [];

        foreach ($rows->skip(1) as $row) {
            $row = $row instanceof \Illuminate\Support\Collection
                ? $row->toArray()
                : (array) $row;

            $materialNumber = trim((string) ($row[0] ?? ''));

            if ($materialNumber === '') {
                $skippedCount++;

                continue;
            }

            $status = strtoupper(trim((string) ($row[9] ?? 'ACTIVE')));

            if (!in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
                $status = 'ACTIVE';
            }

            $stock = is_numeric($row[4] ?? null) ? (float) $row[4] : 0;
            $rop = is_numeric($row[6] ?? null) ? (int) $row[6] : 0;
            $price = is_numeric($row[8] ?? null) ? (float) $row[8] : 0;

            // last row wins if the same material_number repeats in the file
            $data[$materialNumber] = [
                'material_number' => $materialNumber,
                'location'        => trim((string) ($row[1] ?? '')) ?: null,
                'description'     => trim((string) ($row[2] ?? '')) ?: null,
                'remarks'         => trim((string) ($row[3] ?? '')) ?: null,
                'stock'           => $stock,
                'unit'            => trim((string) ($row[5] ?? '')) ?: null,
                'rop'             => $rop,
                'mrp_type'        => trim((string) ($row[7] ?? '')) ?: null,
                'price'           => $price,
                'status'          => $status,
                'machine_type'    => trim((string) ($row[10] ?? '')) ?: null,
                'segment'         => trim((string) ($row[11] ?? '')) ?: null,
                'pdt'             => trim((string) ($row[12] ?? '')) ?: null,
            ];
        }

        $existing = [];
        foreach (array_chunk(array_keys($data), self::CHUNK_SIZE) as $numbers) {
            $existing += Sparepart::query()
                ->whereIn('material_number', $numbers)
                ->pluck('material_number')
                ->flip()
                ->all();
        }

        $importedCount = 0;
        $duplicateCount = 0;

        foreach (array_chunk($data, self::CHUNK_SIZE, true) as $chunk) {
            foreach (array_keys($chunk) as $materialNumber) {
                isset($existing[$materialNumber]) ? $duplicateCount++ : $importedCount++;
            }

            Sparepart::upsert(
                array_values($chunk),
                ['material_number'],
                ['location', 'description', 'remarks', 'stock', 'unit', 'rop', 'mrp_type', 'price', 'status', 'machine_type', 'segment', 'pdt']
            );
        }

        session()->flash('spareparts_import_result', [
            'imported' => $importedCount,
            'duplicate' => $duplicateCount,
            'skipped' => $skippedCount,
        ]);
    }
}