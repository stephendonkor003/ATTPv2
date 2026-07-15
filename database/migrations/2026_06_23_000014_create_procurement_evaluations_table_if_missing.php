<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurement_evaluations')) {
            return;
        }

        Schema::create('procurement_evaluations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('procurement_id');
            $table->uuid('evaluation_id');
            $table->timestamps();

            $table->unique('procurement_id', 'procurement_evaluations_procurement_unique');
            $table->index('evaluation_id', 'procurement_evaluations_evaluation_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_evaluations');
    }
};
