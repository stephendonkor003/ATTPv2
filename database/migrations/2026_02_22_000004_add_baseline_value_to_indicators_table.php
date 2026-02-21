<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_indicators', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_indicators', 'baseline_value')) {
                $table->decimal('baseline_value', 20, 4)->nullable()->after('baseline_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_indicators', function (Blueprint $table) {
            if (Schema::hasColumn('myb_indicators', 'baseline_value')) {
                $table->dropColumn('baseline_value');
            }
        });
    }
};
