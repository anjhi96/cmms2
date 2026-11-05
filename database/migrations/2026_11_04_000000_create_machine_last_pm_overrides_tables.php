<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Display-only Last PM correction for Machine History. Kept in its own tables
 * so machines / pm_schedules (and everything computed from them) are never
 * touched. One active override per machine (unique machine_id); the log table
 * is append-only and survives edits and resets.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('machine_last_pm_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->unique()->constrained('machines')->cascadeOnDelete();
            $table->date('override_date');
            $table->text('reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('machine_last_pm_override_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->constrained('machines')->cascadeOnDelete();
            $table->date('previous_override_date')->nullable();
            $table->date('new_override_date')->nullable();
            $table->text('previous_reason')->nullable();
            $table->text('new_reason')->nullable();
            $table->string('action', 10); // CREATE, UPDATE, RESET
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['machine_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('machine_last_pm_override_logs');
        Schema::dropIfExists('machine_last_pm_overrides');
    }
};
