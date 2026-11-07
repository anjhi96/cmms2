<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // area / machine_number / machine_type are copied from the Machine
        // master when the WO is created (same approach as oil_audits), so the
        // list can be scoped and filtered by plain string columns without a
        // join. machine_id keeps the link back to the master row.
        Schema::create('predictive_work_orders', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('order_number')->unique();
            $table->foreignId('machine_id')->nullable()->constrained()->nullOnDelete();
            $table->string('machine_number');
            $table->string('area');
            $table->string('machine_type')->nullable();
            $table->date('wo_date');
            $table->text('problem_description');
            $table->date('due_date');
            $table->string('status', 10)->default('OPEN');
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['category', 'status']);
            $table->index(['area', 'category']);
            $table->index('wo_date');
            $table->index('due_date');
            $table->index('machine_number');
            $table->index('machine_type');
            $table->index('pic_user_id');
        });

        Schema::create('predictive_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('predictive_work_orders')->cascadeOnDelete();
            $table->unsignedInteger('action_number');
            $table->string('finding');
            $table->string('problem');
            $table->text('remarks')->nullable();
            $table->foreignId('pic_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('pic_name');
            $table->timestamp('actioned_at');
            $table->timestamps();

            $table->unique(['work_order_id', 'action_number']);
            $table->index('pic_user_id');
            $table->index('actioned_at');
        });

        Schema::create('predictive_reopens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_order_id')->constrained('predictive_work_orders')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('user_name');
            $table->timestamp('reopened_at');
            $table->timestamps();

            $table->index(['work_order_id', 'reopened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predictive_reopens');
        Schema::dropIfExists('predictive_actions');
        Schema::dropIfExists('predictive_work_orders');
    }
};
