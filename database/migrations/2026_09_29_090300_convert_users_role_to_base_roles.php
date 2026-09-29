<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Widens `users.role` from an enum/CHECK-constrained column to a plain
// string BEFORE writing the new base-role values. This ordering matters on
// BOTH MySQL (ENUM) and SQLite (CHECK constraint, via Laravel's enum() type)
// — writing a value outside the currently-declared set fails on either
// driver, so the column must be widened first in the same migration.
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 50)->default('GUEST')->change();
        });

        DB::table('users')->whereIn('role', ['KOORDINATOR WWD', 'KOORDINATOR BUL'])->update(['role' => 'KOORDINATOR']);
        DB::table('users')->whereIn('role', ['PIC WWD', 'PIC BUL'])->update(['role' => 'PIC']);
    }

    public function down(): void
    {
        // Re-derive the combined role string from area_id before the column
        // reverts to the restrictive enum, so no data is lost on rollback.
        $areaNamesById = DB::table('areas')->pluck('name', 'id');

        foreach (DB::table('users')->whereIn('role', ['KOORDINATOR', 'PIC'])->get(['id', 'role', 'area_id']) as $user) {
            $areaName = $areaNamesById[$user->area_id] ?? null;

            if ($areaName === null) {
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'role' => "{$user->role} {$areaName}",
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'ADMIN',
                'KOORDINATOR WWD',
                'KOORDINATOR BUL',
                'PIC WWD',
                'PIC BUL',
                'GUEST',
            ])->default('GUEST')->change();
        });
    }
};
