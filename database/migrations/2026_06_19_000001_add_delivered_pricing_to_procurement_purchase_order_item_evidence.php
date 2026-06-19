<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'delivered_unit_price')) {
                $table->decimal('delivered_unit_price', 15, 2)->nullable()->after('deliverable_date');
            }

            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'delivered_quantity')) {
                $table->decimal('delivered_quantity', 15, 2)->nullable()->after('delivered_unit_price');
            }

            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'delivered_amount')) {
                $table->decimal('delivered_amount', 15, 2)->nullable()->after('delivered_quantity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            foreach (['delivered_amount', 'delivered_quantity', 'delivered_unit_price'] as $column) {
                if (Schema::hasColumn('procurement_purchase_order_item_evidence', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
