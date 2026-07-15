<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addPortfolioColumn('me_indicator_levels', 'id');
        $this->addPortfolioColumn('me_reporting_frequencies', 'id');
        $this->addPortfolioColumn('me_indicator_units', 'id');
        $this->addPortfolioColumn('indicator_methodologies', 'id');
        $this->addPortfolioColumn('indicator_definitions', 'id');
    }

    public function down(): void
    {
        foreach ([
            'indicator_definitions',
            'indicator_methodologies',
            'me_indicator_units',
            'me_reporting_frequencies',
            'me_indicator_levels',
        ] as $tableName) {
            if (! Schema::hasColumn($tableName, 'portfolio_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropForeign(['portfolio_id']);
                $table->dropColumn('portfolio_id');
            });
        }
    }

    private function addPortfolioColumn(string $tableName, string $afterColumn): void
    {
        if (Schema::hasColumn($tableName, 'portfolio_id')) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($afterColumn) {
            $table->foreignUuid('portfolio_id')
                ->nullable()
                ->after($afterColumn)
                ->constrained('myb_sectors')
                ->nullOnDelete();
        });
    }
};
