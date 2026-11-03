<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * VME-07: every output of an AI character, kept so it can be reviewed (and later used as
     * training / test data). Written only by the AI character feature; read-only everywhere else.
     * A row is added when the output CHANGES, not on every poll; `checked_at` records the last time
     * the analysis was re-run.
     */
    public function up(): void
    {
        Schema::create('ai_character_runs', function (Blueprint $table) {
            $table->id();
            $table->string('character_key', 40);
            $table->string('status', 12);               // WORKING | ALERT | IDLE
            $table->text('summary');
            $table->json('input')->nullable();           // compact description of what was read
            $table->json('result')->nullable();          // detail lines of the outcome
            $table->string('result_hash', 40);
            $table->string('source', 16)->default('internal'); // internal now, gemini later
            $table->string('source_ref_type', 40)->nullable(); // e.g. ai_forecast_runs
            $table->unsignedBigInteger('source_ref_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->index(['character_key', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_character_runs');
    }
};
