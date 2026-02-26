<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'member_state_id')) {
                $table->foreignUuid('member_state_id')
                    ->nullable()
                    ->after('governance_node_id')
                    ->constrained('myb_au_member_states')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'member_state_id')) {
                $table->dropForeign(['member_state_id']);
                $table->dropColumn('member_state_id');
            }
        });
    }
};
