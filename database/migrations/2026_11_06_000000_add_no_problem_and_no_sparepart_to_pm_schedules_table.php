<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The PIC's explicit confirmation that a PM found no problem / used no
     * sparepart, so an empty list is no longer ambiguous with "not filled yet".
     */
    public function up(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->boolean('no_problem')->default(false)->after('gearbox_problem');
            $table->boolean('no_sparepart')->default(false)->after('no_problem');
        });
    }

    public function down(): void
    {
        Schema::table('pm_schedules', function (Blueprint $table) {
            $table->dropColumn(['no_problem', 'no_sparepart']);
        });
    }
};
