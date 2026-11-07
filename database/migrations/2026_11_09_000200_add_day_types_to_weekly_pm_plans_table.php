<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_pm_plans', function (Blueprint $table) {
            // {"Y-m-d": [{"type": "NDE", "count": 2}, ...]}: machine type mix
            // auto-fill follows on that day; the counts never exceed day_limits.
            $table->json('day_types')->nullable()->after('day_limits');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_pm_plans', function (Blueprint $table) {
            $table->dropColumn('day_types');
        });
    }
};
