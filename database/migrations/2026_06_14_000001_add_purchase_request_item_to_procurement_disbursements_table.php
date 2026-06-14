<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_disbursements', 'purchase_request_item_id')) {
                $table->foreignUuid('purchase_request_item_id')
                    ->nullable()
                    ->after('purchase_order_id')
                    ->constrained('myb_purchase_request_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_disbursements', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_disbursements', 'purchase_request_item_id')) {
                $table->dropForeign(['purchase_request_item_id']);
                $table->dropColumn('purchase_request_item_id');
            }
        });
    }
};
