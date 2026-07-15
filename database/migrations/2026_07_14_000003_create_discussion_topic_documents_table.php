<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_topic_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('topic_id')->constrained('discussion_topics')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->string('type', 40)->default('document');
            $table->string('file_name');
            $table->string('storage_path')->unique();
            $table->string('mime_type', 150);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['topic_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_topic_documents');
    }
};
