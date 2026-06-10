<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procurement_deliverables', function (Blueprint $table) {
            // 'one_time' | 'daily' | 'weekly' | 'monthly' | 'quarterly' | 'yearly'
            $table->string('frequency')->default('one_time')->after('sequence');
        });
    }

    public function down(): void
    {
        Schema::table('procurement_deliverables', function (Blueprint $table) {
            $table->dropColumn('frequency');
        });
    }
};
