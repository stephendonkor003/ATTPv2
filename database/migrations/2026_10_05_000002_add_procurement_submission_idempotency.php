<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_orders', function (Blueprint $table): void {
            $table->string('submission_idempotency_key', 100)->nullable();
            $table->char('submission_idempotency_fingerprint', 64)->nullable();
            $table->unique('submission_idempotency_key', 'proc_po_submission_idempotency_uq');
        });

        Schema::create('procurement_purchase_order_submission_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key', 100)->unique('proc_po_claim_idempotency_uq');
            $table->char('fingerprint', 64);
            $table->foreignUuid('result_purchase_order_id')
                ->nullable()
                ->constrained('procurement_purchase_orders')
                ->nullOnDelete();
            $table->string('status', 16)->default('processing');
            $table->timestamps();
        });

        Schema::create('procurement_disbursement_submission_batches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('purchase_order_id')
                ->nullable()
                ->constrained('procurement_purchase_orders')
                ->nullOnDelete();
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('operation', 16);
            $table->string('idempotency_key', 100)->unique('proc_disb_batch_idempotency_uq');
            $table->char('fingerprint', 64);
            $table->json('result_payment_ids')->nullable();
            $table->string('status', 16)->default('processing');
            $table->timestamps();

            $table->index(
                ['purchase_order_id', 'operation', 'created_at'],
                'proc_disb_batch_po_operation_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('procurement_disbursement_submission_batches');
        Schema::dropIfExists('procurement_purchase_order_submission_claims');

        Schema::table('procurement_purchase_orders', function (Blueprint $table): void {
            $table->dropUnique('proc_po_submission_idempotency_uq');
            $table->dropColumn([
                'submission_idempotency_key',
                'submission_idempotency_fingerprint',
            ]);
        });
    }
};
