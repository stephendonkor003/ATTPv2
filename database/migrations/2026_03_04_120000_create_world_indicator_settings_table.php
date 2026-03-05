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
        Schema::create('world_indicator_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('page_title')->default('World Indicators / Performance');
            $table->text('page_intro')->nullable();
            $table->boolean('is_public_enabled')->default(true);
            $table->json('enabled_regions')->nullable();
            $table->string('default_region')->nullable();
            $table->boolean('imf_source_enabled')->default(true);
            $table->boolean('world_bank_source_enabled')->default(true);
            $table->string('imf_api_base_url')->nullable();
            $table->string('world_bank_api_base_url')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('world_indicator_settings');
    }
};
