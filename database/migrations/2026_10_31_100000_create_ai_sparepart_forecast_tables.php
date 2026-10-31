<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI Sparepart Analyst — storage for forecast runs. Purely additive and
     * AI-only: nothing here is read by, or writes to, PM / usage tables. Rows
     * reference machines / spareparts but keep display snapshots (number,
     * description) so an old run stays readable after master data changes,
     * and so deleting a machine never silently rewrites an old analysis.
     */
    public function up(): void
    {
        Schema::create('ai_forecast_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 16)->index(); // RUNNING | COMPLETED | FAILED
            $table->string('algorithm_version', 32);
            $table->string('period_type', 8);       // WEEK | MONTH
            $table->date('period_start');
            $table->date('period_end');
            // Run scope (null = all).
            $table->string('scope_area')->nullable();
            $table->string('scope_machine_type')->nullable();
            // Input-data reference: history range + a fingerprint of the
            // exact usage rows read, so a result can be traced to its input.
            $table->date('history_from')->nullable();
            $table->date('history_to')->nullable();
            $table->string('data_fingerprint', 64)->nullable();
            $table->json('parameters');             // thresholds / window used
            $table->json('summary')->nullable();    // counts + data-quality block
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        // One row per machine in scope: sufficient or not, and why.
        Schema::create('ai_forecast_machines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_forecast_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('machine_id')->nullable()->index();
            $table->string('machine_number');
            $table->string('machine_type');
            $table->string('area');
            $table->string('status', 24);           // SUFFICIENT | INSUFFICIENT_DATA
            $table->unsignedInteger('finished_pm_count')->default(0);
            $table->unsignedInteger('pm_with_usage_count')->default(0);
            $table->unsignedInteger('excluded_pm_count')->default(0); // contain warehouse-injected rows
            $table->unsignedInteger('upcoming_pm_count')->default(0); // scheduled in the forecast period
            $table->date('first_pm_date')->nullable();
            $table->date('last_pm_date')->nullable();
            $table->string('confidence', 8)->nullable(); // HIGH | MEDIUM | LOW
            $table->string('note')->nullable();
            $table->timestamps();
            $table->index(['run_id', 'status']);
        });

        Schema::create('ai_forecast_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_forecast_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('machine_id')->nullable()->index();
            $table->string('machine_number');
            $table->string('machine_type');
            $table->string('area');
            $table->unsignedBigInteger('sparepart_id')->nullable()->index();
            $table->string('material_number');
            $table->string('description')->nullable();
            $table->string('unit')->nullable();
            // Historical analysis (window = the PMs actually used).
            $table->unsignedInteger('pm_considered');      // finished PMs in the window
            $table->unsignedInteger('usage_pm_count');     // of those, PMs using this part
            $table->unsignedInteger('total_qty');
            $table->decimal('avg_qty', 10, 2);             // per PM that used it
            $table->decimal('mean_interval_days', 10, 1)->nullable();
            $table->date('last_used_date')->nullable();
            $table->string('trend', 12);                    // INCREASING | DECREASING | STABLE | UNKNOWN
            // Forecast for the run period.
            $table->decimal('probability', 5, 4);           // P(used on a PM)
            $table->unsignedInteger('upcoming_pm_count');
            $table->decimal('expected_qty', 12, 2);
            $table->unsignedInteger('recommended_qty');     // 0 unless level = LIKELY
            $table->string('level', 8);                     // LIKELY | POSSIBLE | RARE
            $table->string('confidence', 8);
            $table->json('basis');                          // human-readable calculation basis
            $table->timestamps();
            $table->index(['run_id', 'level']);
            $table->index(['run_id', 'sparepart_id']);
        });

        Schema::create('ai_forecast_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_forecast_runs')->cascadeOnDelete();
            $table->string('status', 24);           // AVAILABLE | NOT_AVAILABLE
            $table->string('split_method');
            $table->date('train_from')->nullable();
            $table->date('train_to')->nullable();
            $table->date('test_from')->nullable();
            $table->date('test_to')->nullable();
            $table->unsignedInteger('train_usage_rows')->default(0);
            $table->unsignedInteger('train_pm_count')->default(0);
            $table->unsignedInteger('train_machine_count')->default(0);
            $table->unsignedInteger('train_sparepart_count')->default(0);
            $table->unsignedInteger('test_usage_rows')->default(0);
            $table->unsignedInteger('test_pm_count')->default(0);
            $table->unsignedInteger('test_machine_count')->default(0);
            $table->unsignedInteger('compared_items')->default(0);
            // Null unless status = AVAILABLE. Never a placeholder.
            $table->decimal('mae', 12, 4)->nullable();
            $table->decimal('wape', 8, 4)->nullable();
            $table->decimal('bias', 8, 4)->nullable();
            $table->json('notes')->nullable();
            $table->timestamps();
        });

        // Engine-generated (source = ENGINE) for now; a later Gemini step
        // may add rows with source = GEMINI. It never edits numeric results.
        Schema::create('ai_forecast_recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('run_id')->constrained('ai_forecast_runs')->cascadeOnDelete();
            $table->string('source', 12)->default('ENGINE');
            $table->string('type', 24);
            $table->string('severity', 8);          // INFO | WARNING
            $table->unsignedBigInteger('sparepart_id')->nullable();
            $table->unsignedBigInteger('machine_id')->nullable();
            $table->text('message');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_forecast_recommendations');
        Schema::dropIfExists('ai_forecast_evaluations');
        Schema::dropIfExists('ai_forecast_results');
        Schema::dropIfExists('ai_forecast_machines');
        Schema::dropIfExists('ai_forecast_runs');
    }
};
