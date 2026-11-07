<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_pm_plans', function (Blueprint $table) {
            // {"<pm_schedule_id>": {"pic": "Asep", "previous": null}}: PICs this
            // plan wrote into pm_schedules, and what was there before, so a
            // machine taken out of the plan can get its old PIC back.
            $table->json('applied_pics')->nullable()->after('sent_at');
            $table->timestamp('pics_applied_at')->nullable()->after('applied_pics');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_pm_plans', function (Blueprint $table) {
            $table->dropColumn(['applied_pics', 'pics_applied_at']);
        });
    }
};
