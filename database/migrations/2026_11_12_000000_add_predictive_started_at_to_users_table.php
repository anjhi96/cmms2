<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-user, per-day start marker for the Action Predictive menu, same
     * shape as oil_audit_action_started_at: one nullable timestamp, no
     * activity table. The Start prompt shows while Predictive is not the PIC's
     * current activity, and Claim / Action require it to be.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('predictive_started_at')->nullable()->after('oil_audit_action_started_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('predictive_started_at');
        });
    }
};
