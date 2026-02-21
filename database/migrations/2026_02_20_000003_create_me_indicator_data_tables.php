<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_indicator_targets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->enum('period_type', ['year', 'quarter', 'month', 'custom'])->default('year');
            $table->string('period_label')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('target_value', 20, 4)->nullable();
            $table->foreignUuid('unit_id')->nullable()->constrained('me_indicator_units')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->index(['indicator_id', 'period_start', 'period_type']);
        });

        Schema::create('me_indicator_results', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->enum('period_type', ['year', 'quarter', 'month', 'custom'])->default('year');
            $table->string('period_label')->nullable();
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('actual_value', 20, 4)->nullable();
            $table->foreignUuid('unit_id')->nullable()->constrained('me_indicator_units')->nullOnDelete();
            $table->text('data_source')->nullable();
            $table->text('method')->nullable();
            $table->text('notes')->nullable();
            $table->uuid('collected_by')->nullable();
            $table->timestamp('collected_at')->nullable();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->index(['indicator_id', 'period_start', 'period_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicator_results');
        Schema::dropIfExists('me_indicator_targets');
    }
};
