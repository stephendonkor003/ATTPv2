<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (! Schema::hasColumn('myb_sectors', 'currency')) {
                $table->string('currency', 10)->default('USD')->after('status');
            }
        });

        DB::table('myb_sectors')
            ->whereNull('currency')
            ->orWhere('currency', '')
            ->update(['currency' => 'USD']);
    }

    public function down(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (Schema::hasColumn('myb_sectors', 'currency')) {
                $table->dropColumn('currency');
            }
        });
    }
};
