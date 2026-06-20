<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'vendor_submission_status')) {
                $table->string('vendor_submission_status', 40)->nullable()->after('documents')->index();
            }

            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'vendor_submitted_at')) {
                $table->timestamp('vendor_submitted_at')->nullable()->after('vendor_submission_status');
            }

            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'vendor_resubmission_requested_at')) {
                $table->timestamp('vendor_resubmission_requested_at')->nullable()->after('vendor_submitted_at');
            }

            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'vendor_resubmission_requested_by')) {
                $table->string('vendor_resubmission_requested_by')->nullable()->after('vendor_resubmission_requested_at');
            }

            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'vendor_resubmission_note')) {
                $table->text('vendor_resubmission_note')->nullable()->after('vendor_resubmission_requested_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            foreach ([
                'vendor_resubmission_note',
                'vendor_resubmission_requested_by',
                'vendor_resubmission_requested_at',
                'vendor_submitted_at',
                'vendor_submission_status',
            ] as $column) {
                if (Schema::hasColumn('procurement_purchase_order_item_evidence', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
