<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * VME job type for the Action Predictive activity. Reuses the existing
     * "work_inspect" animation (no new asset). Aliases only matter for Manual
     * Activity names; Action Predictive itself maps through
     * JobTypeResolver::SOURCE_KEYS. Existing rows and aliases are never touched.
     */
    private const KEY = 'predictive';

    private const ALIASES = [
        'action predictive', 'predictive', 'action predictive vibration', 'action predictive air leaked',
    ];

    public function up(): void
    {
        $now = now();

        $id = DB::table('job_types')->where('key', self::KEY)->value('id');

        $id ??= DB::table('job_types')->insertGetId([
            'key' => self::KEY, 'name' => 'Action Predictive', 'animation_key' => 'work_inspect', 'is_active' => true,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach (self::ALIASES as $alias) {
            if (! DB::table('job_type_aliases')->where('alias', $alias)->exists()) {
                DB::table('job_type_aliases')->insert(['job_type_id' => $id, 'alias' => $alias, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $id = DB::table('job_types')->where('key', self::KEY)->value('id');

        if ($id) {
            DB::table('job_type_aliases')->where('job_type_id', $id)->delete();
            DB::table('job_types')->where('id', $id)->delete();
        }
    }
};
