<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->decimal('extra_target', 20, 4)->nullable()->after('life_of_programme_target');
        });
    }

    public function down(): void
    {
        Schema::table('myb_indicators', function (Blueprint $table): void {
            $table->dropColumn('extra_target');
        });
    }
};
