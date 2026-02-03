<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_requests', function (Blueprint $table) {
            $table->date('commitment_date')->nullable()->after('start_year');
            $table->date('delivery_date')->nullable()->after('commitment_date');
        });
    }

    public function down(): void
    {
        Schema::table('myb_purchase_requests', function (Blueprint $table) {
            $table->dropColumn('delivery_date');
            $table->dropColumn('commitment_date');
        });
    }
};

