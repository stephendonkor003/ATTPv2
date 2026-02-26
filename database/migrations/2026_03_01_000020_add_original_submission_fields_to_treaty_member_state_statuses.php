<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'myb_treaty_member_state_statuses';
    private const FK_ORIGINAL_SUBMITTED_BY = 'fk_treaty_stat_orig_sub_user';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            if (!Schema::hasColumn(self::TABLE, 'is_original_submitted')) {
                $table->boolean('is_original_submitted')->default(false)->after('is_ratified');
            }
            if (!Schema::hasColumn(self::TABLE, 'original_submitted_at')) {
                $table->timestamp('original_submitted_at')->nullable()->after('ratified_at');
            }
            if (!Schema::hasColumn(self::TABLE, 'original_submitted_by_user_id')) {
                $table->uuid('original_submitted_by_user_id')->nullable()->after('ratified_by_user_id');
            }
            if (!Schema::hasColumn(self::TABLE, 'original_document_path')) {
                $table->string('original_document_path')->nullable()->after('ratified_document_path');
            }
            if (!Schema::hasColumn(self::TABLE, 'original_document_name')) {
                $table->string('original_document_name')->nullable()->after('ratified_document_name');
            }
            if (!Schema::hasColumn(self::TABLE, 'original_notes')) {
                $table->text('original_notes')->nullable()->after('ratified_notes');
            }
        });

        if (
            Schema::hasColumn(self::TABLE, 'original_submitted_by_user_id') &&
            empty($this->foreignKeyNamesForColumn(self::TABLE, 'original_submitted_by_user_id'))
        ) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->foreign('original_submitted_by_user_id', self::FK_ORIGINAL_SUBMITTED_BY)
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->foreignKeyNamesForColumn(self::TABLE, 'original_submitted_by_user_id') as $fkName) {
            Schema::table(self::TABLE, function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $columns = [];
            foreach ([
                'is_original_submitted',
                'original_submitted_at',
                'original_submitted_by_user_id',
                'original_document_path',
                'original_document_name',
                'original_notes',
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
            'SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ?
               AND TABLE_NAME = ?
               AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [DB::getDatabaseName(), $table, $column]
        );

        return collect($rows)
            ->map(function ($row) {
                return (string) ($row->CONSTRAINT_NAME ?? '');
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
};
