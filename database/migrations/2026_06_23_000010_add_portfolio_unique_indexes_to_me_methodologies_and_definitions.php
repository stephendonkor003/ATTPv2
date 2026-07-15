<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addUniqueIfMissing('indicator_methodologies', ['portfolio_id', 'name'], 'indicator_methodologies_portfolio_name_unique');
        $this->addUniqueIfMissing('indicator_definitions', ['portfolio_id', 'name'], 'indicator_definitions_portfolio_name_unique');
        $this->addUniqueIfMissing('indicator_definitions', ['portfolio_id', 'code'], 'indicator_definitions_portfolio_code_unique');
    }

    public function down(): void
    {
        $this->dropUniqueIfExists('indicator_definitions', 'indicator_definitions_portfolio_code_unique');
        $this->dropUniqueIfExists('indicator_definitions', 'indicator_definitions_portfolio_name_unique');
        $this->dropUniqueIfExists('indicator_methodologies', 'indicator_methodologies_portfolio_name_unique');
    }

    private function addUniqueIfMissing(string $tableName, array $columns, string $constraintName): void
    {
        try {
            $exists = DB::table('pg_indexes')
                ->where('tablename', $tableName)
                ->where('indexname', $constraintName)
                ->exists();
        } catch (Throwable) {
            $exists = false;
        }

        if ($exists) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($columns, $constraintName) {
            $table->unique($columns, $constraintName);
        });
    }

    private function dropUniqueIfExists(string $tableName, string $constraintName): void
    {
        try {
            Schema::table($tableName, function (Blueprint $table) use ($constraintName) {
                $table->dropUnique($constraintName);
            });
        } catch (Throwable) {
            // Constraint may already be absent in environments that were partially migrated.
        }
    }
};
