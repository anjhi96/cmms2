<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sparepart Planning — mandatory sparepart configuration per machine
     * type. Purely additive: it never touches spareparts, pm_spareparts or
     * any existing PM data, and spareparts.machine_type keeps its existing
     * meaning (it is NOT a substitute for this table).
     */
    public function up(): void
    {
        Schema::create('mandatory_spareparts', function (Blueprint $table) {
            $table->id();
            $table->string('machine_type');
            $table->foreignId('sparepart_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('qty');
            $table->timestamps();

            $table->unique(['machine_type', 'sparepart_id']);
            $table->index('machine_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mandatory_spareparts');
    }
};
