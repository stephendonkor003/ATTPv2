<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const PERMISSIONS = [
        'api_sync.invitations.approve' => 'Approve incoming AU-PReMIS synchronization invitations',
        'api_sync.invitations.decline' => 'Decline incoming AU-PReMIS synchronization invitations',
        'api_sync.invitations.revoke' => 'Revoke approved AU-PReMIS synchronization invitations',
    ];

    public function up(): void
    {
        Schema::create('api_sync_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('protocol_version', 12)->default('2.0');
            $table->string('central_instance_id', 120)->index();
            $table->string('central_name', 160);
            $table->string('central_origin', 512);
            $table->string('target_origin', 512);
            $table->string('confirmation_url', 1_500);
            $table->json('requested_datasets');
            $table->json('requested_scopes');
            $table->char('credential_digest', 64)->unique();
            $table->string('signature_key_id', 120);
            $table->uuid('invitation_nonce')->unique();
            $table->char('invitation_payload_hash', 64);
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('issued_at');
            $table->timestamp('expires_at')->index();
            $table->timestamp('credential_expires_at')->index();
            $table->timestamp('received_at');
            $table->unsignedSmallInteger('approval_attempts')->default(0);
            $table->timestamp('last_approval_attempt_at')->nullable();
            $table->uuid('confirmation_request_id')->nullable()->unique();
            $table->uuid('confirmation_request_nonce')->nullable()->unique();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('activation_received_at')->nullable();
            $table->uuid('activation_request_id')->nullable()->unique();
            $table->uuid('activation_nonce')->nullable()->unique();
            $table->char('activation_payload_hash', 64)->nullable();
            $table->uuid('confirmation_id')->nullable()->unique();
            $table->string('central_run_id', 120)->nullable()->index();
            $table->text('confirmation_receipt')->nullable();
            $table->timestamp('receipt_verified_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->foreignUuid('declined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['central_instance_id', 'status', 'created_at'], 'api_sync_invitations_central_status_idx');
            $table->index(['status', 'expires_at'], 'api_sync_invitations_expiry_idx');
        });

        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->foreignUuid('inbound_invitation_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('api_sync_invitations')
                ->nullOnDelete();
        });

        Schema::create('api_sync_invitation_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('invitation_id')->nullable()->constrained('api_sync_invitations')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 100)->index();
            $table->string('message', 500);
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['invitation_id', 'created_at'], 'api_sync_invitation_events_history_idx');
        });

        Schema::create('api_sync_v2_nonces', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('central_instance_id', 120);
            $table->uuid('nonce')->unique();
            $table->uuid('request_id')->index();
            $table->string('purpose', 32);
            $table->char('payload_hash', 64);
            $table->timestamp('seen_at');
            $table->timestamp('expires_at')->index();

            $table->index(['central_instance_id', 'purpose', 'seen_at'], 'api_sync_v2_nonces_scope_idx');
        });

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

        Schema::dropIfExists('api_sync_v2_nonces');
        Schema::dropIfExists('api_sync_invitation_events');
        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('inbound_invitation_id');
        });
        Schema::dropIfExists('api_sync_invitations');
    }
};
