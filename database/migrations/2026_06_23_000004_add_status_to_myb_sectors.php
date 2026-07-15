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
            if (!Schema::hasColumn('myb_sectors', 'status')) {
                $table->string('status', 20)->default('active')->after('description')->index();
            }
        });

        DB::table('myb_sectors')
            ->whereNull('status')
            ->update(['status' => 'active']);
    }

    public function down(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (Schema::hasColumn('myb_sectors', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
