<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'myb_treaties';

    public function up(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'overview')) {
                $table->longText('overview')->nullable()->after('description');
            }
            if (!Schema::hasColumn(self::TABLE, 'key_provisions')) {
                $table->longText('key_provisions')->nullable()->after('overview');
            }
            if (!Schema::hasColumn(self::TABLE, 'implementation_framework')) {
                $table->longText('implementation_framework')->nullable()->after('key_provisions');
            }
            if (!Schema::hasColumn(self::TABLE, 'monitoring_and_reporting')) {
                $table->longText('monitoring_and_reporting')->nullable()->after('implementation_framework');
            }
            if (!Schema::hasColumn(self::TABLE, 'read_more_url')) {
                $table->string('read_more_url', 2048)->nullable()->after('monitoring_and_reporting');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $columns = [
                'overview',
                'key_provisions',
                'implementation_framework',
                'monitoring_and_reporting',
                'read_more_url',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
