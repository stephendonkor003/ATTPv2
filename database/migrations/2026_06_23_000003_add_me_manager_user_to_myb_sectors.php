<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_sectors', 'me_manager_user_id')) {
                $table->foreignUuid('me_manager_user_id')
                    ->nullable()
                    ->after('ttl_email')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (Schema::hasColumn('myb_sectors', 'me_manager_user_id')) {
                $table->dropForeign(['me_manager_user_id']);
                $table->dropColumn('me_manager_user_id');
            }
        });
    }
};
