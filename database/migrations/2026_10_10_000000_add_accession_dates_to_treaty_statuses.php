<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'myb_treaty_member_state_statuses';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'is_acceded')) {
                $table->boolean('is_acceded')->default(false)->after('is_ratified');
            }
            if (!Schema::hasColumn(self::TABLE, 'acceded_at')) {
                $table->timestamp('acceded_at')->nullable()->after('ratified_at');
            }
            if (!Schema::hasColumn(self::TABLE, 'instrument_deposited_at')) {
                $table->timestamp('instrument_deposited_at')->nullable()->after('acceded_at');
            }
            if (!Schema::hasColumn(self::TABLE, 'official_status_as_of')) {
                $table->date('official_status_as_of')->nullable()->after('instrument_deposited_at');
            }
            if (!Schema::hasColumn(self::TABLE, 'official_status_source_url')) {
                $table->string('official_status_source_url', 2048)->nullable()->after('official_status_as_of');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            foreach (['is_acceded', 'acceded_at', 'instrument_deposited_at', 'official_status_as_of', 'official_status_source_url'] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
