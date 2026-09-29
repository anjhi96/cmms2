<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Groups previously had no area column at all — area was guessed at runtime
// from the group's name (Group::inferredArea(), now removed). This adds a
// real FK and backfills it with the exact same case-insensitive name match,
// via the query builder so the migration doesn't depend on the model.
// Groups that don't match WWD or BUL stay area_id = null (NOT defaulted) —
// Greasing's "exclude the opposite area" visibility rule depends on
// null-area groups staying visible to everyone.
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('area_id')
                ->nullable()
                ->after('name')
                ->constrained('areas')
                ->nullOnDelete();
        });

        $areaIdsByName = DB::table('areas')->pluck('id', 'name');

        foreach (DB::table('groups')->get(['id', 'name']) as $group) {
            $upperName = strtoupper($group->name);

            $areaName = match (true) {
                str_contains($upperName, 'WWD') => 'WWD',
                str_contains($upperName, 'BUL') => 'BUL',
                default => null,
            };

            if ($areaName === null) {
                continue;
            }

            DB::table('groups')->where('id', $group->id)->update([
                'area_id' => $areaIdsByName[$areaName] ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('area_id');
        });
    }
};
