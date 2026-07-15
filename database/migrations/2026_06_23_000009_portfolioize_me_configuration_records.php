<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private array $ownerPortfolioCache = [];

    public function up(): void
    {
        $portfolioIds = DB::table('myb_sectors')
            ->orderBy('created_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($portfolioIds)) {
            return;
        }

        $this->dropSystemWideUniqueConstraints();

        $methodologyMap = $this->portfolioizeTable('indicator_methodologies', $portfolioIds);
        $definitionMap = $this->portfolioizeTable(
            'indicator_definitions',
            $portfolioIds,
            function (array $row, string $portfolioId) use ($methodologyMap): array {
                $methodologyId = $row['methodology_id'] ?? null;
                if ($methodologyId && isset($methodologyMap[$methodologyId][$portfolioId])) {
                    $row['methodology_id'] = $methodologyMap[$methodologyId][$portfolioId];
                }

                return $row;
            }
        );
        $this->duplicateDefinitionVariables($definitionMap);

        $levelMap = $this->portfolioizeTable('me_indicator_levels', $portfolioIds);
        $frequencyMap = $this->portfolioizeTable('me_reporting_frequencies', $portfolioIds);
        $unitMap = $this->portfolioizeTable('me_indicator_units', $portfolioIds);

        $this->rewriteIndicatorReferences($levelMap, 'indicator_level_id');
        $this->rewriteIndicatorReferences($frequencyMap, 'frequency_of_reporting_id');
        $this->rewriteIndicatorReferences($unitMap, 'unit_id');
        $this->rewriteSurveyMethodologyReferences($methodologyMap);

        $this->addPortfolioUniqueConstraints();
    }

    public function down(): void
    {
        $this->dropPortfolioUniqueConstraints();
    }

    private function portfolioizeTable(string $tableName, array $portfolioIds, ?Closure $mutateRow = null): array
    {
        $map = [];
        $now = now();

        $records = DB::table($tableName)
            ->whereNull('portfolio_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($records as $record) {
            $originalId = (string) $record->id;
            $baseRow = (array) $record;

            foreach ($portfolioIds as $index => $portfolioId) {
                $row = $baseRow;
                $row['portfolio_id'] = $portfolioId;
                $row['updated_at'] = $now;

                if ($mutateRow) {
                    $row = $mutateRow($row, $portfolioId);
                }

                if ($index === 0) {
                    DB::table($tableName)
                        ->where('id', $originalId)
                        ->update([
                            'portfolio_id' => $portfolioId,
                            'updated_at' => $now,
                            ...$this->changedRelationshipColumns($tableName, $row),
                        ]);

                    $map[$originalId][$portfolioId] = $originalId;
                    continue;
                }

                $newId = (string) Str::orderedUuid();
                $row['id'] = $newId;
                $row['created_at'] = $row['created_at'] ?? $now;
                $row['updated_at'] = $now;

                DB::table($tableName)->insert($row);
                $map[$originalId][$portfolioId] = $newId;
            }
        }

        return $map;
    }

    private function changedRelationshipColumns(string $tableName, array $row): array
    {
        if ($tableName === 'indicator_definitions') {
            return ['methodology_id' => $row['methodology_id'] ?? null];
        }

        return [];
    }

    private function duplicateDefinitionVariables(array $definitionMap): void
    {
        $now = now();

        foreach ($definitionMap as $originalDefinitionId => $portfolioMap) {
            $variables = DB::table('indicator_definition_variables')
                ->where('indicator_definition_id', $originalDefinitionId)
                ->get();

            foreach ($portfolioMap as $newDefinitionId) {
                if ((string) $newDefinitionId === (string) $originalDefinitionId) {
                    continue;
                }

                foreach ($variables as $variable) {
                    $row = (array) $variable;
                    $row['id'] = (string) Str::orderedUuid();
                    $row['indicator_definition_id'] = $newDefinitionId;
                    $row['created_at'] = $row['created_at'] ?? $now;
                    $row['updated_at'] = $now;

                    DB::table('indicator_definition_variables')->insert($row);
                }
            }
        }
    }

    private function rewriteIndicatorReferences(array $recordMap, string $column): void
    {
        if (empty($recordMap)) {
            return;
        }

        $oldIds = array_keys($recordMap);

        DB::table('myb_indicators')
            ->whereIn($column, $oldIds)
            ->orderBy('id')
            ->select(['id', 'indicatorable_type', 'indicatorable_id', $column])
            ->chunkById(100, function ($indicators) use ($recordMap, $column): void {
                foreach ($indicators as $indicator) {
                    $portfolioId = $this->portfolioIdForIndicator($indicator);
                    $oldRecordId = (string) $indicator->{$column};
                    $newRecordId = $portfolioId ? ($recordMap[$oldRecordId][$portfolioId] ?? null) : null;

                    if ($newRecordId && (string) $newRecordId !== $oldRecordId) {
                        DB::table('myb_indicators')
                            ->where('id', $indicator->id)
                            ->update([$column => $newRecordId]);
                    }
                }
            });
    }

    private function rewriteSurveyMethodologyReferences(array $methodologyMap): void
    {
        if (empty($methodologyMap)) {
            return;
        }

        $this->rewriteSurveyTableMethodologies('me_indicator_survey_links', $methodologyMap);
        $this->rewriteSurveyTableMethodologies('me_indicator_survey_responses', $methodologyMap);
    }

    private function rewriteSurveyTableMethodologies(string $tableName, array $methodologyMap): void
    {
        DB::table($tableName)
            ->whereIn('methodology_id', array_keys($methodologyMap))
            ->orderBy('id')
            ->select(['id', 'indicator_id', 'methodology_id'])
            ->chunkById(100, function ($rows) use ($tableName, $methodologyMap): void {
                foreach ($rows as $row) {
                    $indicator = DB::table('myb_indicators')
                        ->where('id', $row->indicator_id)
                        ->first(['id', 'indicatorable_type', 'indicatorable_id']);
                    $portfolioId = $indicator ? $this->portfolioIdForIndicator($indicator) : null;
                    $oldMethodologyId = (string) $row->methodology_id;
                    $newMethodologyId = $portfolioId ? ($methodologyMap[$oldMethodologyId][$portfolioId] ?? null) : null;

                    if ($newMethodologyId && (string) $newMethodologyId !== $oldMethodologyId) {
                        DB::table($tableName)
                            ->where('id', $row->id)
                            ->update(['methodology_id' => $newMethodologyId]);
                    }
                }
            });
    }

    private function portfolioIdForIndicator(object $indicator): ?string
    {
        $type = (string) $indicator->indicatorable_type;
        $id = (string) $indicator->indicatorable_id;
        $cacheKey = $type . ':' . $id;

        if (array_key_exists($cacheKey, $this->ownerPortfolioCache)) {
            return $this->ownerPortfolioCache[$cacheKey];
        }

        $portfolioId = null;

        if (str_ends_with($type, '\\Sector') || $type === 'App\\Models\\Sector') {
            $portfolioId = DB::table('myb_sectors')->where('id', $id)->value('id');
        } elseif (str_ends_with($type, '\\Program') || $type === 'App\\Models\\Program') {
            $portfolioId = DB::table('myb_programs')->where('id', $id)->value('sector_id');
        } elseif (str_ends_with($type, '\\Project') || $type === 'App\\Models\\Project') {
            $portfolioId = DB::table('myb_projects as projects')
                ->join('myb_programs as programs', 'programs.id', '=', 'projects.program_id')
                ->where('projects.id', $id)
                ->value('programs.sector_id');
        } elseif (str_ends_with($type, '\\Activity') || $type === 'App\\Models\\Activity') {
            $portfolioId = DB::table('myb_activities as activities')
                ->join('myb_projects as projects', 'projects.id', '=', 'activities.project_id')
                ->join('myb_programs as programs', 'programs.id', '=', 'projects.program_id')
                ->where('activities.id', $id)
                ->value('programs.sector_id');
        } elseif (str_ends_with($type, '\\SubActivity') || $type === 'App\\Models\\SubActivity') {
            $portfolioId = DB::table('myb_sub_activities as sub_activities')
                ->join('myb_activities as activities', 'activities.id', '=', 'sub_activities.activity_id')
                ->join('myb_projects as projects', 'projects.id', '=', 'activities.project_id')
                ->join('myb_programs as programs', 'programs.id', '=', 'projects.program_id')
                ->where('sub_activities.id', $id)
                ->value('programs.sector_id');
        }

        return $this->ownerPortfolioCache[$cacheKey] = $portfolioId ? (string) $portfolioId : null;
    }

    private function dropSystemWideUniqueConstraints(): void
    {
        $this->dropUniqueIfExists('me_indicator_levels', 'me_indicator_levels_name_unique');
        $this->dropUniqueIfExists('me_reporting_frequencies', 'me_reporting_frequencies_name_unique');
        $this->dropUniqueIfExists('me_reporting_frequencies', 'me_reporting_frequencies_code_unique');
        $this->dropUniqueIfExists('me_indicator_units', 'me_indicator_units_name_unique');
    }

    private function addPortfolioUniqueConstraints(): void
    {
        $this->addUniqueIfMissing('me_indicator_levels', ['portfolio_id', 'name'], 'me_indicator_levels_portfolio_name_unique');
        $this->addUniqueIfMissing('me_reporting_frequencies', ['portfolio_id', 'name'], 'me_reporting_frequencies_portfolio_name_unique');
        $this->addUniqueIfMissing('me_reporting_frequencies', ['portfolio_id', 'code'], 'me_reporting_frequencies_portfolio_code_unique');
        $this->addUniqueIfMissing('me_indicator_units', ['portfolio_id', 'name'], 'me_indicator_units_portfolio_name_unique');
    }

    private function dropPortfolioUniqueConstraints(): void
    {
        $this->dropUniqueIfExists('me_indicator_levels', 'me_indicator_levels_portfolio_name_unique');
        $this->dropUniqueIfExists('me_reporting_frequencies', 'me_reporting_frequencies_portfolio_name_unique');
        $this->dropUniqueIfExists('me_reporting_frequencies', 'me_reporting_frequencies_portfolio_code_unique');
        $this->dropUniqueIfExists('me_indicator_units', 'me_indicator_units_portfolio_name_unique');
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
};
