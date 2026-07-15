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
        Schema::create('discussion_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('display_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('country', 120)->nullable();
            $table->string('organization')->nullable();
            $table->text('bio')->nullable();
            $table->string('status', 24)->default('active')->index();
            $table->timestamp('terms_accepted_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->text('blocked_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('discussion_participant_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('participant_id')->constrained('discussion_participants')->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->string('name')->default('forum-browser');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('discussion_themes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('icon', 40)->default('message-circle');
            $table->string('color', 20)->default('#006B3F');
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('discussion_topics', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('theme_id')->nullable()->constrained('discussion_themes')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('summary');
            $table->longText('body')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('requires_moderation')->default(true);
            $table->boolean('allow_replies')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at', 'closes_at']);
        });

        Schema::create('discussion_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('topic_id')->constrained('discussion_topics')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->constrained('discussion_participants')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable();
            $table->longText('body');
            $table->string('status', 24)->default('pending')->index();
            $table->foreignUuid('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->text('moderation_reason')->nullable();
            $table->timestamps();

            $table->index(['topic_id', 'status', 'created_at']);
        });

        // PostgreSQL needs the self-referenced table primary key to exist first.
        Schema::table('discussion_posts', function (Blueprint $table): void {
            $table->foreign('parent_id')->references('id')->on('discussion_posts')->nullOnDelete();
        });

        Schema::create('discussion_reactions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('post_id')->constrained('discussion_posts')->cascadeOnDelete();
            $table->foreignUuid('participant_id')->constrained('discussion_participants')->cascadeOnDelete();
            $table->string('type', 24)->default('like');
            $table->timestamps();

            $table->unique(['post_id', 'participant_id', 'type'], 'discussion_reaction_unique');
        });

        Schema::create('discussion_moderation_actions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('moderator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type', 40);
            $table->uuid('subject_id');
            $table->string('action', 40);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });

        $this->provisionPermissions();
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($this->permissions()))
            ->pluck('id');

        if (Schema::hasTable('role_permission')) {
            DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        }

        if (Schema::hasTable('user_permission')) {
            DB::table('user_permission')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('discussion_moderation_actions');
        Schema::dropIfExists('discussion_reactions');
        Schema::dropIfExists('discussion_posts');
        Schema::dropIfExists('discussion_topics');
        Schema::dropIfExists('discussion_themes');
        Schema::dropIfExists('discussion_participant_tokens');
        Schema::dropIfExists('discussion_participants');
    }

    /**
     * @return array<string, string>
     */
    private function permissions(): array
    {
        return [
            'discussions.view' => 'View discussion forum administration dashboards and discussions',
            'discussions.create' => 'Create public discussions',
            'discussions.manage' => 'Edit, publish, close, and archive public discussions',
            'discussions.thematic_areas.manage' => 'Create and manage discussion thematic areas',
            'discussions.participants.manage' => 'View participants and block or restore forum participation',
            'discussions.moderate' => 'Review and moderate participant contributions',
        ];
    }

    private function provisionPermissions(): void
    {
        $now = now();

        foreach ($this->permissions() as $name => $description) {
            $existing = DB::table('permissions')->where('name', $name)->first();

            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $existing?->id ?: (string) Str::uuid(),
                    'module' => 'Discussions',
                    'description' => $description,
                    'created_at' => $existing?->created_at ?: $now,
                    'updated_at' => $now,
                ]
            );
        }

        if (! Schema::hasTable('role_permission')) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('name', array_keys($this->permissions()))
            ->pluck('id');

        DB::table('roles')
            ->whereIn('name', ['System Admin', 'Super Admin', 'Communication Officer', 'Communications Officer'])
            ->pluck('id')
            ->each(function (string $roleId) use ($permissionIds): void {
                foreach ($permissionIds as $permissionId) {
                    DB::table('role_permission')->updateOrInsert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionId,
                    ]);
                }
            });
    }
};
