<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('myb_program_fundings', 'description')) {
            Schema::table('myb_program_fundings', function (Blueprint $table) {
                $table->text('description')->nullable()->after('program_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('myb_program_fundings', 'description')) {
            Schema::table('myb_program_fundings', function (Blueprint $table) {
                $table->dropColumn('description');
            });
        }
    }
};
