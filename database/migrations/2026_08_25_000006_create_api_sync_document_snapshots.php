<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'api_sync.documents.view' => 'View immutable synchronization document progress and held-document issues',
    ];

    public function up(): void
    {
        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->string('document_snapshot_status', 32)->default('not_requested')->index();
            $table->timestamp('document_snapshot_started_at')->nullable();
            $table->timestamp('document_snapshot_materialized_at')->nullable()->index();
            $table->timestamp('document_snapshot_purged_at')->nullable()->index();
            $table->string('document_snapshot_failure_reason', 255)->nullable();
            $table->unsignedInteger('document_discovered_count')->default(0);
            $table->unsignedInteger('document_ready_count')->default(0);
            $table->unsignedInteger('document_held_count')->default(0);
            $table->unsignedBigInteger('document_snapshot_bytes')->default(0);
        });

        Schema::create('api_sync_snapshot_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pairing_id')->constrained('api_sync_pairings')->cascadeOnDelete();
            $table->uuid('snapshot_id');
            $table->unsignedInteger('sequence');
            $table->string('source_type', 64);
            $table->uuid('source_document_id');
            $table->uuid('source_version_id')->nullable();
            $table->char('source_key', 64);
            $table->char('source_revision', 64);
            $table->string('category', 64);
            $table->string('classification', 32)->default('restricted');
            $table->string('title', 255);
            $table->string('display_filename', 255)->nullable();
            $table->string('detected_mime', 150)->nullable();
            $table->unsignedBigInteger('byte_size')->nullable();
            $table->char('sha256', 64)->nullable();
            $table->uuid('portfolio_external_id')->nullable();
            $table->json('project_external_ids')->nullable();
            $table->string('parent_type', 64)->nullable();
            $table->uuid('parent_external_id')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->string('storage_disk', 32)->nullable();
            $table->string('storage_path', 500)->nullable();
            $table->string('state', 24)->default('held');
            $table->string('hold_code', 80)->nullable();
            $table->string('hold_message', 500)->nullable();
            $table->timestamp('copied_at')->nullable();
            $table->timestamp('purged_at')->nullable();
            $table->timestamps();

            $table->unique(['snapshot_id', 'sequence'], 'api_sync_document_snapshot_sequence_uq');
            $table->unique(['snapshot_id', 'source_key'], 'api_sync_document_snapshot_source_uq');
            $table->index(['pairing_id', 'state', 'sequence'], 'api_sync_document_pairing_state_idx');
            $table->index(['snapshot_id', 'state'], 'api_sync_document_snapshot_state_idx');
            $table->index(['source_type', 'source_document_id'], 'api_sync_document_source_idx');
        });

        Schema::create('api_sync_snapshot_document_issues', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pairing_id')->constrained('api_sync_pairings')->cascadeOnDelete();
            $table->foreignUuid('document_id')->nullable()->constrained('api_sync_snapshot_documents')->nullOnDelete();
            $table->uuid('snapshot_id');
            $table->string('source_type', 64)->nullable();
            $table->uuid('source_document_id')->nullable();
            $table->uuid('source_version_id')->nullable();
            $table->string('code', 80)->index();
            $table->string('message', 500);
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['pairing_id', 'created_at'], 'api_sync_document_issue_pairing_idx');
            $table->index(['snapshot_id', 'code'], 'api_sync_document_issue_snapshot_idx');
        });

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE api_sync_pairings ADD CONSTRAINT api_sync_document_snapshot_status_chk CHECK (document_snapshot_status IN ('not_requested','pending','building','ready','failed','purge_pending','purged'))");
            DB::statement('ALTER TABLE api_sync_pairings ADD CONSTRAINT api_sync_document_snapshot_counts_chk CHECK (document_discovered_count >= document_ready_count + document_held_count AND document_snapshot_bytes <= 2147483648)');
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_state_chk CHECK (state IN ('preparing','ready','held','purged'))");
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_source_chk CHECK (source_type IN ('performance_report_document','mission_report_document','knowledge_repository_version'))");
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_source_key_chk CHECK (source_key ~ '^[0-9a-f]{64}$')");
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_source_revision_chk CHECK (source_revision ~ '^[0-9a-f]{64}$')");
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_sha_chk CHECK (sha256 IS NULL OR sha256 ~ '^[0-9a-f]{64}$')");
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_classification_chk CHECK (classification = 'restricted')");
            DB::statement('ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_size_chk CHECK (byte_size IS NULL OR (byte_size > 0 AND byte_size <= 20971520))');
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_path_chk CHECK (storage_path IS NULL OR (storage_path LIKE 'api-sync/v2-document-snapshots/%' AND position('://' in storage_path) = 0 AND position(chr(92) in storage_path) = 0 AND left(storage_path, 1) <> '/' AND storage_path !~ '^[A-Za-z]:' AND storage_path !~ '(^|/)\.\.(/|$)'))");
            DB::statement("ALTER TABLE api_sync_snapshot_documents ADD CONSTRAINT api_sync_snapshot_documents_ready_chk CHECK ((state = 'preparing' AND storage_disk IS NULL AND storage_path IS NULL AND sha256 IS NULL) OR (state = 'ready' AND storage_disk IS NOT NULL AND storage_path IS NOT NULL AND display_filename IS NOT NULL AND detected_mime IS NOT NULL AND byte_size IS NOT NULL AND sha256 IS NOT NULL AND copied_at IS NOT NULL AND hold_code IS NULL AND hold_message IS NULL) OR (state = 'held' AND storage_disk IS NULL AND storage_path IS NULL AND hold_code IS NOT NULL AND hold_message IS NOT NULL) OR (state = 'purged' AND storage_disk IS NULL AND storage_path IS NULL AND purged_at IS NOT NULL))");

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION api_sync_document_issues_immutable()
RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'API synchronization document issues are append-only';
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER api_sync_document_issues_immutable_trigger
BEFORE UPDATE OR DELETE ON api_sync_snapshot_document_issues
FOR EACH ROW EXECUTE FUNCTION api_sync_document_issues_immutable();
SQL);

            DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION api_sync_snapshot_documents_transition_guard()
RETURNS trigger AS $$
DECLARE
    pairing_state varchar(32);
BEGIN
    IF OLD.state = 'purged' THEN
        RAISE EXCEPTION 'Purged API synchronization documents are immutable';
    END IF;

    IF OLD.state = 'preparing' AND NEW.state NOT IN ('ready', 'held', 'purged') THEN
        RAISE EXCEPTION 'Invalid API synchronization document preparation transition';
    END IF;

    IF OLD.state IN ('ready', 'held') THEN
        IF NEW.state = 'purged' THEN
            IF (to_jsonb(NEW) - ARRAY['state','storage_disk','storage_path','purged_at','updated_at'])
                <> (to_jsonb(OLD) - ARRAY['state','storage_disk','storage_path','purged_at','updated_at']) THEN
                RAISE EXCEPTION 'Immutable API synchronization document metadata cannot change during purge';
            END IF;
        ELSIF OLD.state = 'ready' AND NEW.state = 'held' AND NEW.hold_code = 'snapshot_build_failed' THEN
            SELECT document_snapshot_status INTO pairing_state FROM api_sync_pairings WHERE id = OLD.pairing_id;
            IF pairing_state NOT IN ('building', 'failed') THEN
                RAISE EXCEPTION 'A published API synchronization document can only transition to purged';
            END IF;
            IF (to_jsonb(NEW) - ARRAY['state','storage_disk','storage_path','hold_code','hold_message','updated_at'])
                <> (to_jsonb(OLD) - ARRAY['state','storage_disk','storage_path','hold_code','hold_message','updated_at']) THEN
                RAISE EXCEPTION 'Immutable API synchronization document metadata cannot change after staging';
            END IF;
        ELSE
            RAISE EXCEPTION 'A staged API synchronization document can only transition to purged';
        END IF;
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER api_sync_snapshot_documents_transition_guard_trigger
BEFORE UPDATE ON api_sync_snapshot_documents
FOR EACH ROW EXECUTE FUNCTION api_sync_snapshot_documents_transition_guard();
SQL);
        }

        $now = now();
        foreach (self::PERMISSIONS as $name => $description) {
            $existing = DB::table('permissions')->where('name', $name)->first();
            DB::table('permissions')->updateOrInsert(
                ['name' => $name],
                [
                    'id' => $existing?->id ?: (string) Str::uuid(),
                    'module' => 'API Sync',
                    'description' => $description,
                    'created_at' => $existing?->created_at ?: $now,
                    'updated_at' => $now,
                ],
            );
        }

        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('name', ['API Sync Administrator', 'System Admin'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('role_permission')->updateOrInsert([
                    'role_id' => $roleId,
                    'permission_id' => $permissionId,
                ]);
            }
        }
    }

    public function down(): void
    {
        $permissionIds = DB::table('permissions')->whereIn('name', array_keys(self::PERMISSIONS))->pluck('id');
        DB::table('role_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('user_permission')->whereIn('permission_id', $permissionIds)->delete();
        DB::table('permissions')->whereIn('id', $permissionIds)->delete();

        Schema::dropIfExists('api_sync_snapshot_document_issues');
        Schema::dropIfExists('api_sync_snapshot_documents');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS api_sync_document_issues_immutable()');
            DB::statement('DROP FUNCTION IF EXISTS api_sync_snapshot_documents_transition_guard()');
        }

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE api_sync_pairings DROP CONSTRAINT IF EXISTS api_sync_document_snapshot_status_chk');
            DB::statement('ALTER TABLE api_sync_pairings DROP CONSTRAINT IF EXISTS api_sync_document_snapshot_counts_chk');
        }

        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->dropIndex(['document_snapshot_status']);
            $table->dropIndex(['document_snapshot_materialized_at']);
            $table->dropIndex(['document_snapshot_purged_at']);
            $table->dropColumn([
                'document_snapshot_status',
                'document_snapshot_started_at',
                'document_snapshot_materialized_at',
                'document_snapshot_purged_at',
                'document_snapshot_failure_reason',
                'document_discovered_count',
                'document_ready_count',
                'document_held_count',
                'document_snapshot_bytes',
            ]);
        });
    }
};
