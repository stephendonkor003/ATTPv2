<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            $table->string('milestone', 255)->nullable()->after('resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('myb_purchase_request_items', function (Blueprint $table) {
            $table->dropColumn('milestone');
        });
    }
};
