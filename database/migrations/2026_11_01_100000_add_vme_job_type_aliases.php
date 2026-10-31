<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * A Manual Activity keeps its own text, but should still be recognised as
     * the kind of work it names ("PM", "Action Temuan Oli", "Cleaning" ...).
     * Adds more aliases (and a few generic job types) so those names map to
     * a job type — and therefore an animation — instead of "manual".
     *
     * Existing job types only gain aliases; their animation is not touched.
     * Aliases that already exist are skipped.
     *
     * @var array<string, array{0: string, 1: string, 2: list<string>}> key => [name, animation, aliases]
     */
    private array $types = [
        'pm' => ['PM', 'work_pm', ['pm', 'p m', 'pm mesin', 'preventive maintenance']],
        'oil_audit' => ['Oil Audit', 'work_oil_sample', ['oil audit', 'audit oli', 'audit oil', 'oil sampling', 'sampling oli', 'ambil sampel oli']],
        'oil_audit_action' => ['Action Audit Oli', 'work_oil_action', ['action temuan oli', 'temuan oli', 'tindak lanjut oli', 'action audit oli', 'action oil audit', 'action oil']],
        'greasing' => ['Greasing', 'work_grease', ['greasing', 'greassing', 'grassing', 'grease', 'pelumasan']],
        'cleaning' => ['Cleaning', 'work_clean', ['cleaning', 'clean', 'pembersihan', 'membersihkan', 'bersih bersih']],
        'assembling' => ['Assembling', 'work_tool', ['assembling', 'assembly', 'assemble', 'perakitan', 'rakit']],
        'repair' => ['Repair', 'work_tool', ['repair', 'perbaikan', 'perbaiki', 'tightening', 'kencangkan']],
        'inspection' => ['Inspection', 'work_inspect', ['inspection', 'inspeksi', 'pengecekan', 'checking', 'cek mesin']],
        'action_air_leaked' => ['Action Air Leaked', 'work_tool', ['kebocoran angin', 'bocor angin']],
        'measurement' => ['Measurement', 'work_measure', ['ukur', 'mengukur']],
    ];

    public function up(): void
    {
        $now = now();

        foreach ($this->types as $key => [$name, $animation, $aliases]) {
            $id = DB::table('job_types')->where('key', $key)->value('id');

            $id ??= DB::table('job_types')->insertGetId([
                'key' => $key, 'name' => $name, 'animation_key' => $animation, 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);

            foreach ($aliases as $alias) {
                if (! DB::table('job_type_aliases')->where('alias', $alias)->exists()) {
                    DB::table('job_type_aliases')->insert(['job_type_id' => $id, 'alias' => $alias, 'created_at' => $now, 'updated_at' => $now]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->types as $key => [, , $aliases]) {
            DB::table('job_type_aliases')->whereIn('alias', $aliases)
                ->whereIn('job_type_id', DB::table('job_types')->where('key', $key)->pluck('id'))->delete();
        }

        // Only the job types this migration created (and that are now unused).
        foreach (['cleaning', 'assembling', 'repair', 'inspection'] as $key) {
            $id = DB::table('job_types')->where('key', $key)->value('id');
            if ($id && ! DB::table('job_type_aliases')->where('job_type_id', $id)->exists()) {
                DB::table('job_types')->where('id', $id)->delete();
            }
        }
    }
};
