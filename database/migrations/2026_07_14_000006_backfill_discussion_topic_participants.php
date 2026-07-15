<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('discussion_topic_participants')) {
            return;
        }

        $now = now();
        DB::table('discussion_posts')
            ->where('status', 'published')
            ->select(['topic_id', 'participant_id'])
            ->selectRaw('MIN(created_at) AS joined_at')
            ->selectRaw('MAX(created_at) AS last_seen_at')
            ->groupBy('topic_id', 'participant_id')
            ->orderBy('topic_id')
            ->get()
            ->each(function (object $postGroup) use ($now): void {
                DB::table('discussion_topic_participants')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'topic_id' => $postGroup->topic_id,
                    'participant_id' => $postGroup->participant_id,
                    'joined_at' => $postGroup->joined_at ?: $now,
                    'last_seen_at' => $postGroup->last_seen_at ?: $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        // Backfilled rows are valid participation records and should not be
        // deleted if this data-only migration is rolled back.
    }
};
