<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attp_think_tank_budget_lines', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('consortium_id')
                ->constrained('attp_consortia')
                ->restrictOnDelete();
            $table->foreignUuid('think_tank_member_id')
                ->constrained('attp_consortium_think_tanks')
                ->restrictOnDelete();
            $table->foreignUuid('parent_id')->nullable();
            $table->foreignUuid('fund_allocation_id')
                ->nullable()
                ->constrained('attp_fund_allocations')
                ->restrictOnDelete();
            $table->foreignUuid('procurement_item_id')
                ->nullable()
                ->constrained('attp_think_tank_procurement_items')
                ->restrictOnDelete();
            $table->string('code', 80);
            $table->string('name');
            $table->text('description')->nullable();
            // Procurement plans use either a calendar label (2026) or an
            // implementation-year label (2026/27). Accounting reports map
            // both labels to their leading calendar year.
            $table->string('fiscal_year', 7);
            $table->string('currency', 3)->default('USD');
            $table->decimal('amount', 18, 2);
            $table->string('status', 30)->default('active');
            $table->unsignedBigInteger('portal_lock_version')->default(1);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['think_tank_member_id', 'fiscal_year', 'code'],
                'attp_tt_budget_member_year_code_uq'
            );
            // A procurement plan item may be assigned to only one internal
            // budget line, otherwise commitment/spend roll-ups double count it.
            $table->unique(
                ['think_tank_member_id', 'procurement_item_id'],
                'attp_tt_budget_member_proc_item_uq'
            );
            $table->index(
                ['think_tank_member_id', 'fiscal_year', 'status'],
                'attp_tt_budget_member_year_status_idx'
            );
            $table->index(
                ['parent_id', 'status'],
                'attp_tt_budget_parent_status_idx'
            );
        });

        // PostgreSQL creates the primary key at the end of CREATE TABLE.
        // Add the self-reference only after that statement has completed so
        // the referenced id is already unique.
        Schema::table('attp_think_tank_budget_lines', function (Blueprint $table): void {
            $table->foreign('parent_id', 'attp_tt_budget_parent_fk')
                ->references('id')
                ->on('attp_think_tank_budget_lines')
                ->restrictOnDelete();
        });

        Schema::table('attp_disbursement_requests', function (Blueprint $table): void {
            $table->string('portal_idempotency_key', 100)->nullable();
            $table->string('portal_idempotency_fingerprint', 64)->nullable();
            $table->unsignedBigInteger('portal_lock_version')->default(1);
            $table->unique(
                ['think_tank_member_id', 'portal_idempotency_key'],
                'attp_disb_req_member_idempotency_uq'
            );
        });

        Schema::table('attp_fund_allocations', function (Blueprint $table): void {
            $table->uuid('source_purchase_order_id')->nullable();
            $table->unique(
                'source_purchase_order_id',
                'attp_fund_alloc_source_po_uq'
            );
            $table->foreign('source_purchase_order_id', 'attp_fund_alloc_source_po_fk')
                ->references('id')
                ->on('procurement_purchase_orders')
                ->restrictOnDelete();
        });

        Schema::table('procurement_disbursements', function (Blueprint $table): void {
            $table->string('secretariat_idempotency_key', 100)->nullable();
            $table->string('secretariat_idempotency_fingerprint', 64)->nullable();
            $table->unique(
                'secretariat_idempotency_key',
                'proc_disb_secretariat_idempotency_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table): void {
            $table->dropUnique('proc_disb_secretariat_idempotency_uq');
            $table->dropColumn([
                'secretariat_idempotency_key',
                'secretariat_idempotency_fingerprint',
            ]);
        });

        Schema::table('attp_fund_allocations', function (Blueprint $table): void {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                $table->dropForeign(['source_purchase_order_id']);
            } else {
                $table->dropForeign('attp_fund_alloc_source_po_fk');
            }
            $table->dropUnique('attp_fund_alloc_source_po_uq');
            $table->dropColumn('source_purchase_order_id');
        });

        Schema::table('attp_disbursement_requests', function (Blueprint $table): void {
            $table->dropUnique('attp_disb_req_member_idempotency_uq');
            $table->dropColumn([
                'portal_idempotency_key',
                'portal_idempotency_fingerprint',
                'portal_lock_version',
            ]);
        });

        Schema::dropIfExists('attp_think_tank_budget_lines');
    }
};
