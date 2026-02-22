<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('me_indicator_data_source_sync_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('indicator_id')->constrained('myb_indicators')->cascadeOnDelete();
            $table->string('source_type', 80)->nullable();
            $table->text('source_value')->nullable();
            $table->string('status', 30)->default('failed');
            $table->text('message')->nullable();
            $table->unsignedInteger('synced_rows')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->uuid('synced_by')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['indicator_id', 'synced_at']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('me_indicator_data_source_sync_logs');
    }
};

