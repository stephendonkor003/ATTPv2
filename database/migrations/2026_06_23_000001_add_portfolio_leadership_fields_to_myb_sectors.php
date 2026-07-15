<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            if (!Schema::hasColumn('myb_sectors', 'ttl_name')) {
                $table->string('ttl_name')->nullable()->after('governance_node_id');
            }

            if (!Schema::hasColumn('myb_sectors', 'ttl_email')) {
                $table->string('ttl_email')->nullable()->after('ttl_name');
            }

            if (!Schema::hasColumn('myb_sectors', 'me_manager_name')) {
                $table->string('me_manager_name')->nullable()->after('ttl_email');
            }

            if (!Schema::hasColumn('myb_sectors', 'me_manager_email')) {
                $table->string('me_manager_email')->nullable()->after('me_manager_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('myb_sectors', function (Blueprint $table) {
            foreach (['ttl_name', 'ttl_email', 'me_manager_name', 'me_manager_email'] as $column) {
                if (Schema::hasColumn('myb_sectors', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
