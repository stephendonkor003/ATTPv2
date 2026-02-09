<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            $table->date('milestone_date')->nullable()->after('milestone');
        });
    }

    public function down(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            $table->dropColumn('milestone_date');
        });
    }
};
