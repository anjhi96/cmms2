<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Virtual Maintenance Environment (VME) — foundation tables.
     *
     * Purely additive: nothing here is read by any operational module
     * (PM / Greasing / Oil Audit / Today Activity decisions). Every VME
     * feature switch defaults to OFF simply because no vme_settings row
     * exists after this migration.
     */
    public function up(): void
    {
        Schema::create('vme_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('value');
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('job_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('animation_key')->default('work_tool');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // alias is stored normalized (lowercase, single spaces) — see JobType::normalize().
        Schema::create('job_type_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_type_id')->constrained()->cascadeOnDelete();
            $table->string('alias')->unique();
            $table->timestamps();
        });

        // Visual coordinates, relative to the machine's Area rectangle on the
        // VME map. NOT physical coordinates.
        Schema::create('vme_machine_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('machine_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('x');
            $table->unsignedSmallInteger('y');
            $table->timestamps();
        });

        Schema::create('vme_character_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('avatar_key');
            $table->timestamps();
        });

        $now = now();

        $types = [
            ['pm', 'PM', 'work_tool', ['preventive maintenance']],
            ['oil_audit', 'Oil Audit', 'work_inspect', ['audit oli']],
            ['oil_audit_action', 'Action Audit Oli', 'work_tool', ['action oil audit', 'action audit oli']],
            ['greasing', 'Greasing', 'work_grease', ['greasing']],
            ['manual', 'Manual Activity', 'work_tool', []],
            ['action_air_leaked', 'Action Air Leaked', 'work_tool', ['air leaked', 'air leak', 'action air leaked']],
            ['action_pdm_gearbox', 'Action PDM Gearbox', 'work_inspect', ['pdm gearbox', 'action pdm gearbox']],
            ['cleaning_motor_grilles', 'Cleaning Motor Grilles', 'work_clean', ['motor grilles', 'cleaning motor grilles', 'cleaning grilles']],
            ['change_panel_filter', 'Change Panel Filter', 'work_panel', ['panel filter', 'change panel filter']],
            ['change_panel_blower', 'Change Panel Blower', 'work_panel', ['panel blower', 'change panel blower']],
            ['measurement', 'Measurement', 'work_measure', ['measurement', 'pengukuran']],
        ];

        foreach ($types as [$key, $name, $animation, $aliases]) {
            $id = DB::table('job_types')->insertGetId([
                'key' => $key,
                'name' => $name,
                'animation_key' => $animation,
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($aliases as $alias) {
                DB::table('job_type_aliases')->insert([
                    'job_type_id' => $id,
                    'alias' => $alias,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('vme_character_profiles');
        Schema::dropIfExists('vme_machine_positions');
        Schema::dropIfExists('job_type_aliases');
        Schema::dropIfExists('job_types');
        Schema::dropIfExists('vme_settings');
    }
};
