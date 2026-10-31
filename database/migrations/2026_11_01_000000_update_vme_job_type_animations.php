<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * VME-04: dedicated work animations for the fixed job types. Only rows
     * still on the original seeded animation are changed, so an animation an
     * admin has already customised is left alone.
     *
     * @var array<string, array{0: string, 1: string}> job type key => [old, new]
     */
    private array $changes = [
        'pm' => ['work_tool', 'work_pm'],
        'oil_audit' => ['work_inspect', 'work_oil_sample'],
        'oil_audit_action' => ['work_tool', 'work_oil_action'],
        'manual' => ['work_tool', 'work_inspect'], // generic inspection when no alias matched
    ];

    public function up(): void
    {
        foreach ($this->changes as $key => [$old, $new]) {
            DB::table('job_types')->where('key', $key)->where('animation_key', $old)->update(['animation_key' => $new]);
        }
    }

    public function down(): void
    {
        foreach ($this->changes as $key => [$old, $new]) {
            DB::table('job_types')->where('key', $key)->where('animation_key', $new)->update(['animation_key' => $old]);
        }
    }
};
