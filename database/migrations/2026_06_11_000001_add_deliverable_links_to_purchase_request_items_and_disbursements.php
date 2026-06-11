<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_purchase_request_items', 'deliverable_id')) {
                $table->foreignUuid('deliverable_id')
                    ->nullable()
                    ->after('resource_id')
                    ->constrained('procurement_deliverables')
                    ->nullOnDelete();

                $table->index('deliverable_id');
            }
        });

        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_disbursements', 'deliverable_id')) {
                $table->foreignUuid('deliverable_id')
                    ->nullable()
                    ->after('purchase_order_id')
                    ->constrained('procurement_deliverables')
                    ->nullOnDelete();

                $table->index('deliverable_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_disbursements', 'deliverable_id')) {
                $table->dropForeign(['deliverable_id']);
                $table->dropIndex(['deliverable_id']);
                $table->dropColumn('deliverable_id');
            }
        });

        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('myb_purchase_request_items', 'deliverable_id')) {
                $table->dropForeign(['deliverable_id']);
                $table->dropIndex(['deliverable_id']);
                $table->dropColumn('deliverable_id');
            }
        });
    }
};
