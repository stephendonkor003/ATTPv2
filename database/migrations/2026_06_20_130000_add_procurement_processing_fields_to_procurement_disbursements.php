<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_disbursements', 'signed_documents')) {
                $table->json('signed_documents')->nullable()->after('notes');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'procurement_processing_status')) {
                $table->string('procurement_processing_status', 40)->nullable()->after('signed_documents')->index();
            }

            if (! Schema::hasColumn('procurement_disbursements', 'procurement_notified_at')) {
                $table->timestamp('procurement_notified_at')->nullable()->after('procurement_processing_status');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'goods_receipt_reference')) {
                $table->string('goods_receipt_reference')->nullable()->after('procurement_notified_at');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'goods_receipt_generated_at')) {
                $table->timestamp('goods_receipt_generated_at')->nullable()->after('goods_receipt_reference');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'goods_receipt_generated_by')) {
                $table->string('goods_receipt_generated_by')->nullable()->after('goods_receipt_generated_at');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'sap_52_series_reference')) {
                $table->string('sap_52_series_reference')->nullable()->after('goods_receipt_generated_by');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'sap_52_series_entered_at')) {
                $table->timestamp('sap_52_series_entered_at')->nullable()->after('sap_52_series_reference');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'sap_52_series_entered_by')) {
                $table->string('sap_52_series_entered_by')->nullable()->after('sap_52_series_entered_at');
            }

            if (! Schema::hasColumn('procurement_disbursements', 'procurement_processing_notes')) {
                $table->text('procurement_processing_notes')->nullable()->after('sap_52_series_entered_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            foreach ([
                'procurement_processing_notes',
                'sap_52_series_entered_by',
                'sap_52_series_entered_at',
                'sap_52_series_reference',
                'goods_receipt_generated_by',
                'goods_receipt_generated_at',
                'goods_receipt_reference',
                'procurement_notified_at',
                'procurement_processing_status',
                'signed_documents',
            ] as $column) {
                if (Schema::hasColumn('procurement_disbursements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
