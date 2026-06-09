<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_warehouse_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('active');
            $table->string('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('data_warehouse_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('category_id')
                ->nullable()
                ->constrained('data_warehouse_categories')
                ->nullOnDelete();
            $table->string('title');
            $table->string('source_name')->nullable();
            $table->string('reference_period')->nullable();
            $table->string('data_owner')->nullable();
            $table->json('tags')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('published');
            $table->string('created_by')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'status']);
            $table->index(['reference_period']);
        });

        Schema::create('data_warehouse_files', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('record_id')
                ->constrained('data_warehouse_records')
                ->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('original_name');
            $table->string('path');
            $table->string('disk', 30)->default('public');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('uploaded_by')->nullable();
            $table->timestamps();

            $table->index(['record_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_warehouse_files');
        Schema::dropIfExists('data_warehouse_records');
        Schema::dropIfExists('data_warehouse_categories');
    }
};
