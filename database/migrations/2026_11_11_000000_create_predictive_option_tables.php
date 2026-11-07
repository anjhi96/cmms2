<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Standardised Problem / Finding choices for Predictive Actions. A Problem
// belongs to one category (Vibration / Air Leaked) and one area; a Finding
// belongs to one Problem. Actions keep the chosen name as a text snapshot
// (the existing problem / finding columns) next to the option id, so
// renaming an option updates its history in one statement and older
// free-text actions (option id NULL) keep working.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predictive_problem_options', function (Blueprint $table) {
            $table->id();
            $table->string('category', 20);
            $table->string('area');
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['category', 'area', 'name']);
        });

        Schema::create('predictive_finding_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('problem_option_id')->constrained('predictive_problem_options')->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['problem_option_id', 'name']);
        });

        Schema::table('predictive_action_problems', function (Blueprint $table) {
            $table->foreignId('problem_option_id')->nullable()->after('action_id')
                ->constrained('predictive_problem_options')->nullOnDelete();
        });

        Schema::table('predictive_action_findings', function (Blueprint $table) {
            $table->foreignId('finding_option_id')->nullable()->after('problem_id')
                ->constrained('predictive_finding_options')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('predictive_action_findings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finding_option_id');
        });
        Schema::table('predictive_action_problems', function (Blueprint $table) {
            $table->dropConstrainedForeignId('problem_option_id');
        });
        Schema::dropIfExists('predictive_finding_options');
        Schema::dropIfExists('predictive_problem_options');
    }
};
