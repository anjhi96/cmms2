<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Data-only migration. Runs BEFORE the role column is converted to base
// roles (see 2026_09_29_090300_convert_users_role_to_base_roles.php), while
// `users.role` still holds the old combined role+area strings — this is the
// only point at which both the old string and the area it encodes are still
// available in the same column.
return new class () extends Migration {
    private const KNOWN_ROLES = [
        'ADMIN',
        'KOORDINATOR WWD',
        'KOORDINATOR BUL',
        'PIC WWD',
        'PIC BUL',
        'GUEST',
    ];

    private const ROLE_TO_AREA_NAME = [
        'KOORDINATOR WWD' => 'WWD',
        'KOORDINATOR BUL' => 'BUL',
        'PIC WWD' => 'WWD',
        'PIC BUL' => 'BUL',
    ];

    public function up(): void
    {
        $unexpected = DB::table('users')
            ->whereNotIn('role', self::KNOWN_ROLES)
            ->pluck('role')
            ->unique();

        if ($unexpected->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot backfill users.area_id: unexpected role value(s) found: '
                .$unexpected->implode(', ')
                .'. Resolve these manually before re-running migrations.'
            );
        }

        $areaIdsByName = DB::table('areas')->pluck('id', 'name');

        foreach (self::ROLE_TO_AREA_NAME as $role => $areaName) {
            $areaId = $areaIdsByName[$areaName] ?? null;

            if ($areaId === null) {
                throw new RuntimeException("Cannot backfill users.area_id: area [{$areaName}] not found.");
            }

            DB::table('users')->where('role', $role)->update(['area_id' => $areaId]);
        }
    }

    public function down(): void
    {
        DB::table('users')->update(['area_id' => null]);
    }
};
