<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assistant_submissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference_no')->unique();
            $table->string('kind', 30);
            $table->string('status', 30)->default('pending')->index();
            $table->uuid('created_by')->index();
            $table->uuid('governance_node_id')->nullable()->index();
            $table->json('payload');
            $table->json('summary');
            $table->json('documents');
            $table->uuid('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->json('published_ids')->nullable();
            $table->string('notification_status')->default('queued');
            $table->json('notification_recipients')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assistant_submissions');
    }
};
