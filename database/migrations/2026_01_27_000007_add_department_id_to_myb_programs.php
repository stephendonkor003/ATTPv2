<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('myb_programs', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_programs', 'department_id')) {
                $table->foreignUuid('department_id')->nullable()->after('sector_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_programs', function (Blueprint $table) {
            if (Schema::hasColumn('myb_programs', 'department_id')) {
                $table->dropColumn('department_id');
            }
        });
    }
};
