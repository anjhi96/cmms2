<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Singleton row holding the Admin-managed Gmail sender + recipient list.
        Schema::create('email_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('sender_email')->nullable();
            // Encrypted by the model's 'encrypted' cast; never stored in plaintext.
            $table->text('sender_password')->nullable();
            $table->json('recipients')->nullable();
            $table->timestamp('last_test_at')->nullable();
            $table->boolean('last_test_ok')->nullable();
            $table->string('last_test_message', 500)->nullable();
            $table->timestamps();
        });

        // One row per KRITIS oil audit. The unique oil_audit_id IS the
        // idempotency guard: a replayed request/sync/retry cannot create a
        // second notification event for the same audit.
        Schema::create('oil_audit_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oil_audit_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending|sending|sent|failed|skipped
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_error', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oil_audit_notifications');
        Schema::dropIfExists('email_notification_settings');
    }
};
