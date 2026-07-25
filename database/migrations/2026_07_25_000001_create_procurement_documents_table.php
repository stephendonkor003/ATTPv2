<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('procurement_id')
                ->constrained('procurements')
                ->cascadeOnDelete();
            $table->string('document_name');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('mime_type', 150)->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['procurement_id', 'created_at'], 'procurement_documents_procurement_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_documents');
    }
};
