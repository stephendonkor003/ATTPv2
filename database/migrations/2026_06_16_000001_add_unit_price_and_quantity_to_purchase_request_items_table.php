<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_purchase_request_items', 'unit_price')) {
                $table->decimal('unit_price', 15, 2)->default(0)->after('deliverable_id');
            }

            if (! Schema::hasColumn('myb_purchase_request_items', 'quantity')) {
                $table->decimal('quantity', 15, 2)->default(1)->after('unit_price');
            }
        });

        DB::table('myb_purchase_request_items')
            ->where(function ($query) {
                $query->whereNull('unit_price')->orWhere('unit_price', 0);
            })
            ->update(['unit_price' => DB::raw('amount')]);

        DB::table('myb_purchase_request_items')
            ->where(function ($query) {
                $query->whereNull('quantity')->orWhere('quantity', 0);
            })
            ->update(['quantity' => 1]);
    }

    public function down(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            if (Schema::hasColumn('myb_purchase_request_items', 'quantity')) {
                $table->dropColumn('quantity');
            }

            if (Schema::hasColumn('myb_purchase_request_items', 'unit_price')) {
                $table->dropColumn('unit_price');
            }
        });
    }
};
