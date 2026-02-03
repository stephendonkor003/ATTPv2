<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // AU Member States (55 countries)
        Schema::create('myb_au_member_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code', 3)->nullable()->comment('ISO 3166-1 alpha-3 code');
            $table->string('code_alpha2', 2)->nullable()->comment('ISO 3166-1 alpha-2 code');
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index('is_active');
        });

        // AU Regional Economic Communities (RECs) - 8 blocks
        Schema::create('myb_au_regional_blocks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('abbreviation', 20)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique('name');
            $table->index('is_active');
        });

        // Agenda 2063 Aspirations (7 aspirations)
        Schema::create('myb_au_aspirations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('number')->comment('Aspiration number 1-7');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('number');
            $table->index('is_active');
        });

        // Agenda 2063 Goals (20 goals linked to aspirations)
        Schema::create('myb_au_goals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('aspiration_id')
                ->constrained('myb_au_aspirations')
                ->onDelete('cascade');
            $table->unsignedTinyInteger('number')->comment('Goal number 1-20');
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('number');
            $table->index(['aspiration_id', 'is_active']);
        });

        // AU Flagship Projects (12 flagship projects)
        Schema::create('myb_au_flagship_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('number')->comment('Flagship project number 1-12');
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('number');
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('myb_au_flagship_projects');
        Schema::dropIfExists('myb_au_goals');
        Schema::dropIfExists('myb_au_aspirations');
        Schema::dropIfExists('myb_au_regional_blocks');
        Schema::dropIfExists('myb_au_member_states');
    }
};
