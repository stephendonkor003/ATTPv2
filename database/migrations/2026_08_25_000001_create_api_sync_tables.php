<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_sync_pairings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('code_hash', 64)->unique();
            $table->string('status', 24)->default('pending')->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('code_expires_at')->index();
            $table->timestamp('claimed_at')->nullable();
            $table->char('claim_idempotency_hash', 64)->nullable()->unique();
            $table->string('consumer_instance', 120)->nullable()->index();
            $table->string('consumer_name', 160)->nullable();
            $table->uuid('snapshot_id')->nullable()->unique();
            $table->timestamp('snapshot_at')->nullable();
            $table->char('token_hash', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->unsignedBigInteger('request_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUuid('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoke_reason', 255)->nullable();
            $table->timestamps();

            $table->index(['status', 'code_expires_at']);
            $table->index(['status', 'token_expires_at']);
        });

        Schema::create('api_sync_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pairing_id')->nullable()->constrained('api_sync_pairings')->nullOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 80)->index();
            $table->string('message', 500);
            $table->string('dataset', 80)->nullable()->index();
            $table->unsignedInteger('record_count')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['pairing_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_events');
        Schema::dropIfExists('api_sync_pairings');
    }
};
