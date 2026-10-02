<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('procurements')
            && ! Schema::hasColumn('procurements', 'portal_lock_version')) {
            Schema::table('procurements', function (Blueprint $table): void {
                $table->unsignedBigInteger('portal_lock_version')->default(1);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('procurements')
            && Schema::hasColumn('procurements', 'portal_lock_version')) {
            Schema::table('procurements', function (Blueprint $table): void {
                $table->dropColumn('portal_lock_version');
            });
        }
    }
};
