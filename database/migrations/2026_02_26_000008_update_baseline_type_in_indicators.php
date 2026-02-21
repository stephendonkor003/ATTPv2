<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // widen baseline_type to simple string to allow "week"
        if (Schema::hasTable('myb_indicators')) {
            Schema::table('myb_indicators', function (Blueprint $table) {
                if (Schema::hasColumn('myb_indicators', 'baseline_type')) {
                    $table->string('baseline_type', 20)->default('year')->change();
                }
            });
        }
    }

    public function down(): void
    {
        // revert to enum if needed
        if (Schema::hasTable('myb_indicators')) {
            Schema::table('myb_indicators', function (Blueprint $table) {
                if (Schema::hasColumn('myb_indicators', 'baseline_type')) {
                    $table->enum('baseline_type', ['year','month','quarter','day'])->default('year')->change();
                }
            });
        }
    }
};
