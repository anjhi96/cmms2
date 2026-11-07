<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_pm_plans', function (Blueprint $table) {
            // {"Y-m-d": 0..6}: how many machines auto-fill may put on that day.
            $table->json('day_limits')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_pm_plans', function (Blueprint $table) {
            $table->dropColumn('day_limits');
        });
    }
};
