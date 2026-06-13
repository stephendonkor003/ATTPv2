<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('myb_purchase_request_attachments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_request_id')
                ->constrained('myb_purchase_requests')
                ->cascadeOnDelete();
            $table->string('uploaded_by')->nullable();
            $table->string('document_type', 80)->nullable();
            $table->string('title')->nullable();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->default(0);
            $table->timestamps();

            $table->index('purchase_request_id');
            $table->index('uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('myb_purchase_request_attachments');
    }
};
