<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->string('snapshot_status', 24)->nullable()->index();
            $table->timestamp('snapshot_started_at')->nullable();
            $table->timestamp('snapshot_materialized_at')->nullable()->index();
            $table->timestamp('snapshot_failed_at')->nullable();
            $table->timestamp('snapshot_purged_at')->nullable()->index();
            $table->string('snapshot_failure_reason', 255)->nullable();
            $table->unsignedBigInteger('snapshot_record_count')->default(0);
            $table->unsignedBigInteger('snapshot_bytes')->default(0);
        });

        Schema::create('api_sync_snapshot_datasets', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('pairing_id')->constrained('api_sync_pairings')->cascadeOnDelete();
            $table->uuid('snapshot_id');
            $table->string('dataset', 64);
            $table->unsignedSmallInteger('sort_order');
            $table->string('status', 16)->default('pending');
            $table->unsignedBigInteger('record_count')->default(0);
            $table->unsignedBigInteger('payload_bytes')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['snapshot_id', 'dataset']);
            $table->index(['pairing_id', 'sort_order']);
        });

        Schema::create('api_sync_snapshot_records', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignUuid('pairing_id')->constrained('api_sync_pairings')->cascadeOnDelete();
            $table->uuid('snapshot_id');
            $table->string('dataset', 64);
            $table->unsignedBigInteger('sequence');
            $table->string('source_id', 255);
            $table->char('checksum', 64);
            $table->char('payload_hash', 64);
            $table->json('payload');
            $table->unsignedInteger('payload_bytes');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['snapshot_id', 'dataset', 'sequence']);
            $table->unique(['snapshot_id', 'dataset', 'source_id']);
            $table->index(['pairing_id', 'dataset']);
        });

        // A single locked row serializes capacity reservations even when two
        // different pairing codes are claimed concurrently.
        Schema::create('api_sync_snapshot_capacity_locks', function (Blueprint $table): void {
            $table->string('scope', 32)->primary();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::table('api_sync_snapshot_capacity_locks')->insert([
            'scope' => 'provider',
            'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('api_sync_snapshot_capacity_locks');
        Schema::dropIfExists('api_sync_snapshot_records');
        Schema::dropIfExists('api_sync_snapshot_datasets');

        Schema::table('api_sync_pairings', function (Blueprint $table): void {
            $table->dropIndex(['snapshot_status']);
            $table->dropIndex(['snapshot_materialized_at']);
            $table->dropIndex(['snapshot_purged_at']);
            $table->dropColumn([
                'snapshot_status',
                'snapshot_started_at',
                'snapshot_materialized_at',
                'snapshot_failed_at',
                'snapshot_purged_at',
                'snapshot_failure_reason',
                'snapshot_record_count',
                'snapshot_bytes',
            ]);
        });
    }
};
