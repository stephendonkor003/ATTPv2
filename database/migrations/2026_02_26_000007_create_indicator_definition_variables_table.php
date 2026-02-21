<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('indicator_definition_variables', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('indicator_definition_id');
            $table->string('name');
            $table->string('color', 32)->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('indicator_definition_id')
                ->references('id')->on('indicator_definitions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('indicator_definition_variables');
    }
};
