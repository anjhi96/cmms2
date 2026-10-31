<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sparepart Injection (warehouse issue -> PM Sparepart Usage).
     *
     * Additive only. pm_spareparts stays the single usage table; it just
     * gains two nullable columns marking rows that came from the warehouse
     * so PMScheduleSaveService can protect them. Existing rows keep NULLs.
     */
    public function up(): void
    {
        Schema::create('sparepart_injection_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Uploader's area name snapshot (null = ADMIN); drives who may view the history.
            $table->string('area')->nullable()->index();
            $table->string('filename');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('usage_added')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->timestamps();
        });

        Schema::create('sparepart_injection_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_id')->constrained('sparepart_injection_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('timestamp')->nullable();
            $table->string('order_number')->nullable();
            $table->string('sap_number')->nullable();
            $table->string('qty')->nullable();
            $table->string('status', 16); // PROCESSED | SKIPPED | FAILED
            $table->string('reason')->nullable();
            $table->foreignId('pm_schedule_id')->nullable()->constrained('pm_schedules')->nullOnDelete();
            $table->foreignId('sparepart_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['import_id', 'status']);
        });

        // Persistent ledger of warehouse transactions already applied. The
        // unique key is the duplicate guard (Timestamp + Order Number), so
        // it holds even if two uploads race.
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

        Schema::table('pm_spareparts', function (Blueprint $table) {
            $table->string('source', 20)->nullable()->after('qty')->index();
            $table->foreignId('injection_import_id')->nullable()->after('source')
                ->constrained('sparepart_injection_imports')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pm_spareparts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('injection_import_id');
            $table->dropColumn('source');
        });

        Schema::dropIfExists('sparepart_injection_transactions');
        Schema::dropIfExists('sparepart_injection_rows');
        Schema::dropIfExists('sparepart_injection_imports');
    }
};
