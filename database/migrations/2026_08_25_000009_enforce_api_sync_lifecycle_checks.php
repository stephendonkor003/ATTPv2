<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var array<string, string> */
    private const PAIRING_CHECKS = [
        'api_sync_pairings_status_chk' => "status IN ('pending', 'claimed', 'completed', 'revoked', 'expired', 'abandoned')",
        'api_sync_pairings_digest_chk' => "code_hash ~ '^[0-9a-f]{64}$' AND (claim_idempotency_hash IS NULL OR claim_idempotency_hash ~ '^[0-9a-f]{64}$') AND (claim_recovery_hash IS NULL OR claim_recovery_hash ~ '^[0-9a-f]{64}$') AND (token_hash IS NULL OR token_hash ~ '^[0-9a-f]{64}$')",
        'api_sync_pairings_expiry_chk' => 'token_expires_at IS NULL OR token_expires_at > code_expires_at',
        'api_sync_pairings_lifecycle_chk' => "(status <> 'pending' OR (claimed_at IS NULL AND snapshot_id IS NULL AND token_hash IS NULL AND token_expires_at IS NULL)) AND (status NOT IN ('claimed', 'completed') OR (claimed_at IS NOT NULL AND claim_idempotency_hash IS NOT NULL AND consumer_instance IS NOT NULL AND consumer_name IS NOT NULL AND snapshot_id IS NOT NULL AND snapshot_at IS NOT NULL AND token_expires_at IS NOT NULL)) AND (status <> 'claimed' OR token_hash IS NOT NULL) AND (status <> 'completed' OR (completed_at IS NOT NULL AND (token_hash IS NULL OR token_expires_at > completed_at))) AND (status <> 'revoked' OR (revoked_at IS NOT NULL AND token_hash IS NULL)) AND (status <> 'expired' OR token_hash IS NULL) AND (status <> 'abandoned' OR (abandoned_at IS NOT NULL AND token_hash IS NULL))",
    ];

    /** @var array<string, string> */
    private const INVITATION_CHECKS = [
        'api_sync_invitations_status_chk' => "status IN ('pending', 'approval_in_progress', 'activation_pending', 'activation_received', 'active', 'completed', 'declined', 'expired', 'revoked', 'failed')",
        'api_sync_invitations_digest_chk' => "credential_digest ~ '^[0-9a-f]{64}$' AND invitation_payload_hash ~ '^[0-9a-f]{64}$' AND (activation_payload_hash IS NULL OR activation_payload_hash ~ '^[0-9a-f]{64}$')",
        'api_sync_invitations_expiry_chk' => 'expires_at > issued_at AND credential_expires_at > expires_at',
        'api_sync_invitations_attempts_chk' => 'approval_attempts BETWEEN 0 AND 10',
        'api_sync_invitations_auth_tuple_chk' => '(authorization_id IS NULL AND authorization_receipt IS NULL AND authorization_verified_at IS NULL) OR (authorization_id IS NOT NULL AND authorization_receipt IS NOT NULL AND authorization_verified_at IS NOT NULL)',
        'api_sync_invitations_activation_tuple_chk' => '(activation_request_id IS NULL AND activation_nonce IS NULL AND activation_payload_hash IS NULL AND activation_received_at IS NULL) OR (activation_request_id IS NOT NULL AND activation_nonce IS NOT NULL AND activation_payload_hash IS NOT NULL AND activation_received_at IS NOT NULL)',
        'api_sync_invitations_receipt_tuple_chk' => '(confirmation_id IS NULL AND central_run_id IS NULL AND confirmation_receipt IS NULL AND receipt_verified_at IS NULL) OR (confirmation_id IS NOT NULL AND central_run_id IS NOT NULL AND confirmation_receipt IS NOT NULL AND receipt_verified_at IS NOT NULL)',
        'api_sync_invitations_lifecycle_chk' => "(status <> 'approval_in_progress' OR (approved_by IS NOT NULL AND confirmation_request_id IS NOT NULL AND confirmation_request_nonce IS NOT NULL)) AND (status <> 'activation_pending' OR (approved_by IS NOT NULL AND approved_at IS NOT NULL AND authorization_id IS NOT NULL)) AND (status NOT IN ('activation_received', 'active', 'completed') OR (approved_by IS NOT NULL AND approved_at IS NOT NULL AND activation_request_id IS NOT NULL)) AND (status <> 'completed' OR completed_at IS NOT NULL) AND (status <> 'declined' OR (declined_at IS NOT NULL AND declined_by IS NOT NULL)) AND (status <> 'revoked' OR revoked_at IS NOT NULL)",
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $this->addAndValidate('api_sync_pairings', self::PAIRING_CHECKS);
        $this->addAndValidate('api_sync_invitations', self::INVITATION_CHECKS);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::INVITATION_CHECKS) as $name) {
            DB::statement("ALTER TABLE api_sync_invitations DROP CONSTRAINT IF EXISTS {$name}");
        }
        foreach (array_keys(self::PAIRING_CHECKS) as $name) {
            DB::statement("ALTER TABLE api_sync_pairings DROP CONSTRAINT IF EXISTS {$name}");
        }
    }

    /** @param array<string, string> $checks */
    private function addAndValidate(string $table, array $checks): void
    {
        foreach ($checks as $name => $expression) {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression}) NOT VALID");
            DB::statement("ALTER TABLE {$table} VALIDATE CONSTRAINT {$name}");
        }
    }
};
