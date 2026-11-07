<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Row master, the Small PM counterpart of `groups`. One row set is
        // shared by every Small PM tab. sort_order is the order a generated
        // cycle schedules the rows in.
        Schema::create('small_pm_rows', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('area_id')->nullable()->constrained('areas')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Same as machines.group_id: a machine sits in at most one row.
        Schema::table('machines', function (Blueprint $table) {
            $table->foreignId('small_pm_row_id')->nullable()->after('group_id')->constrained('small_pm_rows')->nullOnDelete();
        });

        // Schedules follow greasings: plan_date, due_date = plan_date + 14,
        // status from action_date vs due_date. task_type is the tab.
        Schema::create('small_pm_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('small_pm_row_id')->constrained('small_pm_rows')->cascadeOnDelete();
            $table->string('task_type', 40);
            $table->string('order_number')->nullable();
            $table->string('cycle', 10);
            $table->date('plan_date');
            $table->date('due_date');
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('action_date')->nullable();
            $table->timestamp('start_time')->nullable();
            $table->string('status', 20)->default('OPEN');
            $table->text('remarks')->nullable();
            // The schedule whose execution created this one (next cycle). Unique so saving an execution twice never plans two.
            $table->foreignId('generated_from_id')->nullable()->unique()->constrained('small_pm_schedules')->nullOnDelete();
            $table->timestamps();

            $table->index(['task_type', 'plan_date']);
            $table->index(['pic_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('small_pm_schedules');

        Schema::table('machines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('small_pm_row_id');
        });

        Schema::dropIfExists('small_pm_rows');
    }
};
