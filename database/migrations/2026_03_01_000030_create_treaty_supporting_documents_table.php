<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('myb_treaty_supporting_documents')) {
            Schema::create('myb_treaty_supporting_documents', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('treaty_id')
                    ->constrained('myb_treaties')
                    ->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->string('document_type')->nullable();
                $table->string('file_path');
                $table->string('file_name');
                $table->foreignUuid('uploaded_by')->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();

                $table->index('treaty_id');
                $table->index('document_type');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_treaty_supporting_documents');
    }
};
