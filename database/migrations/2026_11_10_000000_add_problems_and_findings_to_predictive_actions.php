<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// One Action now carries several Problems, each with several Findings (same
// shape as the Oil Audit follow-up). The single finding/problem columns on
// predictive_actions stay for history but are no longer written: existing
// rows are copied into the new tables first, so nothing is lost.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_action_problems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('action_id')->constrained('predictive_actions')->cascadeOnDelete();
            $table->string('problem');
            $table->timestamps();

            $table->index('action_id');
        });

        Schema::create('predictive_action_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_id')->constrained('predictive_action_problems')->cascadeOnDelete();
            $table->string('finding');
            $table->timestamps();

            $table->index('problem_id');
        });

        Schema::table('predictive_actions', function (Blueprint $table) {
            $table->string('finding')->nullable()->change();
            $table->string('problem')->nullable()->change();
        });

        DB::table('predictive_actions')->whereNotNull('problem')->orderBy('id')->each(function ($action) {
            $problemId = DB::table('predictive_action_problems')->insertGetId([
                'action_id' => $action->id,
                'problem' => $action->problem,
                'created_at' => $action->created_at,
                'updated_at' => $action->updated_at,
            ]);

            if ($action->finding !== null && $action->finding !== '') {
                DB::table('predictive_action_findings')->insert([
                    'problem_id' => $problemId,
                    'finding' => $action->finding,
                    'created_at' => $action->created_at,
                    'updated_at' => $action->updated_at,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_action_findings');
        Schema::dropIfExists('predictive_action_problems');
    }
};
