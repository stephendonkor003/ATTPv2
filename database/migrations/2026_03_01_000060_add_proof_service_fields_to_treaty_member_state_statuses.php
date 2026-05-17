<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'myb_treaty_member_state_statuses';
    private const FK_SIGNED = 'fk_treaty_stat_signed_code_user';
    private const FK_RATIFIED = 'fk_treaty_stat_ratified_code_user';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'signed_service_code')) {
                $table->string('signed_service_code', 19)->nullable()->after('signed_notes');
            }
            if (!Schema::hasColumn(self::TABLE, 'signed_service_code_verified_at')) {
                $table->timestamp('signed_service_code_verified_at')->nullable()->after('signed_service_code');
            }
            if (!Schema::hasColumn(self::TABLE, 'signed_service_code_verified_by_user_id')) {
                $table->uuid('signed_service_code_verified_by_user_id')
                    ->nullable()
                    ->after('signed_service_code_verified_at');
            }

            if (!Schema::hasColumn(self::TABLE, 'ratified_service_code')) {
                $table->string('ratified_service_code', 19)->nullable()->after('ratified_notes');
            }
            if (!Schema::hasColumn(self::TABLE, 'ratified_service_code_verified_at')) {
                $table->timestamp('ratified_service_code_verified_at')->nullable()->after('ratified_service_code');
            }
            if (!Schema::hasColumn(self::TABLE, 'ratified_service_code_verified_by_user_id')) {
                $table->uuid('ratified_service_code_verified_by_user_id')
                    ->nullable()
                    ->after('ratified_service_code_verified_at');
            }
        });

        if (
            Schema::hasColumn(self::TABLE, 'signed_service_code_verified_by_user_id') &&
            empty($this->foreignKeyNamesForColumn(self::TABLE, 'signed_service_code_verified_by_user_id'))
        ) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('signed_service_code_verified_by_user_id', self::FK_SIGNED)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (
            Schema::hasColumn(self::TABLE, 'ratified_service_code_verified_by_user_id') &&
            empty($this->foreignKeyNamesForColumn(self::TABLE, 'ratified_service_code_verified_by_user_id'))
        ) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('ratified_service_code_verified_by_user_id', self::FK_RATIFIED)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeyNamesForColumn(self::TABLE, 'signed_service_code_verified_by_user_id') as $fkName) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        foreach ($this->foreignKeyNamesForColumn(self::TABLE, 'ratified_service_code_verified_by_user_id') as $fkName) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $columns = [];
            foreach ([
                'signed_service_code',
                'signed_service_code_verified_at',
                'signed_service_code_verified_by_user_id',
                'ratified_service_code',
                'ratified_service_code_verified_at',
                'ratified_service_code_verified_by_user_id',
            ] as $column) {
                if (Schema::hasColumn(self::TABLE, $column)) {
                    $columns[] = $column;
                }
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }

    /**
     * @return array<int, string>
     */
    private function foreignKeyNamesForColumn(string $table, string $column): array
    {
        if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
            return [];
        }

        $rows = DB::select(
            "SELECT tc.constraint_name
             FROM information_schema.table_constraints tc
             JOIN information_schema.key_column_usage kcu
               ON tc.constraint_name = kcu.constraint_name
              AND tc.table_schema = kcu.table_schema
             WHERE tc.constraint_type = 'FOREIGN KEY'
               AND tc.table_schema = current_schema()
               AND tc.table_name = ?
               AND kcu.column_name = ?",
            [$table, $column]
        );

        return collect($rows)
            ->map(function ($row) {
                return (string) ($row->constraint_name ?? '');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};
