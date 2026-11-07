<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_pm_plans', function (Blueprint $table) {
            $table->id();
            $table->string('area');
            $table->date('week_start');
            $table->string('status')->default('DRAFT');
            // Rows as they were when the coordinator last marked the plan as
            // emailed; the revision email is the diff against this.
            $table->json('sent_snapshot')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['area', 'week_start']);
        });

        Schema::create('weekly_pm_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('weekly_pm_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pm_schedule_id')->nullable()->constrained('pm_schedules')->nullOnDelete();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            // Snapshots so an already-sent email stays reproducible even if
            // the PM Schedule row is edited or deleted later.
            $table->string('machine_number');
            $table->string('machine_type');
            $table->date('day');
            $table->string('pic')->nullable();
            $table->string('construction_note', 100)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['weekly_pm_plan_id', 'pm_schedule_id']);
            $table->index(['machine_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_pm_plan_items');
        Schema::dropIfExists('weekly_pm_plans');
    }
};
