<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            if (! Schema::hasColumn('procurement_purchase_order_item_evidence', 'deliverable_date')) {
                $table->date('deliverable_date')->nullable()->after('is_met');
            }
        });
    }

    public function down(): void
    {
        Schema::table('procurement_purchase_order_item_evidence', function (Blueprint $table) {
            if (Schema::hasColumn('procurement_purchase_order_item_evidence', 'deliverable_date')) {
                $table->dropColumn('deliverable_date');
            }
        });
    }
};
