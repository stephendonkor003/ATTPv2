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
        // Indicator Levels table
        Schema::create('me_indicator_levels', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });

        // Reporting Frequencies table
        Schema::create('me_reporting_frequencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('code')->unique()->comment('Code for frequency (e.g., MONTHLY, QUARTERLY, ANNUAL)');
            $table->integer('frequency_in_days')->nullable()->comment('Number of days for this frequency');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });

        // Indicator Units table
        Schema::create('me_indicator_units', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->string('symbol')->nullable()->comment('Symbol for the unit (e.g., %, kg, items)');
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->uuid('created_by')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('me_indicator_units');
        Schema::dropIfExists('me_reporting_frequencies');
        Schema::dropIfExists('me_indicator_levels');
    }
};
