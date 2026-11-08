<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ganti Filter Panel: one row per machine whose blower filter was
        // found dead. Closed (COMPLETED) once the action is filled in, like
        // greasing_findings.
        Schema::create('small_pm_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('small_pm_schedule_id')->constrained('small_pm_schedules')->cascadeOnDelete();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->string('status', 20)->default('OPEN');
            $table->text('action')->nullable();
            $table->date('action_date')->nullable();
            $table->timestamps();

            $table->unique(['small_pm_schedule_id', 'machine_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('small_pm_findings');
    }
};
