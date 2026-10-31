<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The warehouse file now carries the Month of the transaction instead of
     * a Timestamp, and there is no per-row duplicate key any more, so the
     * Timestamp+Order ledger is dropped. The per-row audit trail
     * (sparepart_injection_rows) stays; its column is renamed.
     */
    public function up(): void
    {
        Schema::dropIfExists('sparepart_injection_transactions');

        Schema::table('sparepart_injection_rows', function (Blueprint $table) {
            $table->renameColumn('timestamp', 'month');
        });
    }

    public function down(): void
    {
        Schema::table('sparepart_injection_rows', function (Blueprint $table) {
            $table->renameColumn('month', 'timestamp');
        });

        Schema::create('sparepart_injection_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('sparepart_injection_imports')->restrictOnDelete();
            $table->string('timestamp_key', 32);
            $table->string('order_key');
            $table->foreignId('pm_schedule_id')->constrained('pm_schedules')->cascadeOnDelete();
            $table->foreignId('sparepart_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['timestamp_key', 'order_key']);
        });
    }
};
