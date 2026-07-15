<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discussion_topics', function (Blueprint $table): void {
            $table->json('related_links')->nullable();
            $table->json('materials')->nullable();
            $table->json('documents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('discussion_topics', function (Blueprint $table): void {
            $table->dropColumn(['related_links', 'materials', 'documents']);
        });
    }
};
