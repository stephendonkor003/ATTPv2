<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const INDEX_NAME = 'discussion_posts_live_created_id_status_idx';

    public function up(): void
    {
        Schema::table('discussion_posts', function (Blueprint $table): void {
            // Put the requested order first so the unfiltered monitor can scan
            // newest-first without sorting the entire published/removed set.
            // Status remains covered and the existing status-first index still
            // serves aggregate/filter-only queries.
            $table->index(['created_at', 'id', 'status'], self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        Schema::table('discussion_posts', function (Blueprint $table): void {
            $table->dropIndex(self::INDEX_NAME);
        });
    }
};
