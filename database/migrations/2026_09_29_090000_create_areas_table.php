<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the two areas already in use, via the query builder (not the
        // Eloquent model) so this migration never breaks if the Area model's
        // shape changes later. insertOrIgnore keeps this safe to run more
        // than once (e.g. across environments) without duplicating rows.
        DB::table('areas')->insertOrIgnore([
            ['name' => 'WWD', 'slug' => 'wwd', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'BUL', 'slug' => 'bul', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
