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
        if (!Schema::hasTable('world_bank_topics')) {
            Schema::create('world_bank_topics', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->unsignedInteger('wb_topic_id')->unique();
                $table->string('name');
                $table->text('source_note')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('world_bank_indicators')) {
            Schema::create('world_bank_indicators', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('wb_indicator_id', 80)->unique();
                $table->string('name');
                $table->string('unit')->nullable();
                $table->text('source_note')->nullable();
                $table->text('source_organization')->nullable();
                $table->string('source_id', 20)->nullable();
                $table->string('source_name')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('world_bank_indicator_topic')) {
            Schema::create('world_bank_indicator_topic', function (Blueprint $table) {
                $table->uuid('world_bank_indicator_id');
                $table->uuid('world_bank_topic_id');

                $table->foreign('world_bank_indicator_id')
                    ->name('wb_ind_topic_indicator_fk')
                    ->references('id')
                    ->on('world_bank_indicators')
                    ->cascadeOnDelete();

                $table->foreign('world_bank_topic_id')
                    ->name('wb_ind_topic_topic_fk')
                    ->references('id')
                    ->on('world_bank_topics')
                    ->cascadeOnDelete();

                $table->primary(['world_bank_indicator_id', 'world_bank_topic_id'], 'wb_indicator_topic_primary');
            });
        }

        if (!Schema::hasTable('world_bank_countries')) {
            Schema::create('world_bank_countries', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('wb_country_id', 8)->unique();
                $table->string('iso2_code', 2)->nullable()->index();
                $table->string('name');
                $table->string('region')->nullable();
                $table->string('admin_region')->nullable();
                $table->string('income_level')->nullable();
                $table->string('lending_type')->nullable();
                $table->string('capital_city')->nullable();
                $table->string('longitude')->nullable();
                $table->string('latitude')->nullable();
                $table->string('continent')->nullable()->index();
                $table->boolean('is_aggregate')->default(false)->index();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('world_bank_indicator_observations')) {
            Schema::create('world_bank_indicator_observations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('world_bank_indicator_id');
                $table->string('country_iso2', 2)->index();
                $table->string('country_name')->nullable();
                $table->unsignedSmallInteger('year')->index();
                $table->decimal('value', 20, 6)->nullable();
                $table->unsignedTinyInteger('decimal_places')->nullable();
                $table->string('observation_status', 20)->nullable();
                $table->timestamp('fetched_at')->nullable();
                $table->json('raw_payload')->nullable();
                $table->timestamps();

                $table->foreign('world_bank_indicator_id')
                    ->name('wb_observations_indicator_fk')
                    ->references('id')
                    ->on('world_bank_indicators')
                    ->cascadeOnDelete();

                $table->unique(
                    ['world_bank_indicator_id', 'country_iso2', 'year'],
                    'wb_observation_indicator_country_year_unique'
                );
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('world_bank_indicator_observations');
        Schema::dropIfExists('world_bank_countries');
        Schema::dropIfExists('world_bank_indicator_topic');
        Schema::dropIfExists('world_bank_indicators');
        Schema::dropIfExists('world_bank_topics');
    }
};
