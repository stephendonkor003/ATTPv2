<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discussion_topic_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('topic_id')->constrained('discussion_topics')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->constrained('discussion_participants')->cascadeOnDelete();
            $table->timestamp('joined_at');
            $table->timestamp('last_seen_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['topic_id', 'participant_id'], 'discussion_topic_participant_unique');
            $table->index(['topic_id', 'joined_at']);
        });

        // ATTP discussions use post-moderation: valid contributions are public
        // immediately and moderators remove only content that violates the rules.
        DB::table('discussion_topics')->update(['requires_moderation' => false]);
        DB::table('discussion_posts')->where('status', 'pending')->update(['status' => 'published']);
        DB::table('discussion_posts')->where('status', 'rejected')->update(['status' => 'removed']);

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

        Schema::table('discussion_topics', function (Blueprint $table): void {
            $table->boolean('requires_moderation')->default(false)->change();
        });

        Schema::table('discussion_posts', function (Blueprint $table): void {
            $table->string('status', 24)->default('published')->change();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discussion_topic_participants');

        Schema::table('discussion_topics', function (Blueprint $table): void {
            $table->boolean('requires_moderation')->default(true)->change();
        });

        Schema::table('discussion_posts', function (Blueprint $table): void {
            $table->string('status', 24)->default('pending')->change();
        });
    }
};
