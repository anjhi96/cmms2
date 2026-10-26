<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat backup database dan source code (menu Backup & Data Protection).
     * Satu baris per percobaan backup; file itu sendiri disimpan di storage privat.
     */
    public function up(): void
    {
        Schema::create('backup_runs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 20);
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('error_message')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('download_started_at')->nullable();
            $table->foreignId('downloaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('file_removed_at')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backup_runs');
    }
};
