<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_au_member_states', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_au_member_states', 'flag_path')) {
                $table->string('flag_path')->nullable()->after('code_alpha2');
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_au_member_states', function (Blueprint $table) {
            if (Schema::hasColumn('myb_au_member_states', 'flag_path')) {
                $table->dropColumn('flag_path');
            }
        });
    }
};
