<?php

namespace Database\Seeders;

use App\Models\MeIndicatorAchievement;
use App\Models\Program;
use App\Services\AttpMelFrameworkInstaller;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Destructively replaces the controlled ATTP MEL framework and its reporting
 * workflow. Uploaded files are not deleted from storage, but their ATTP form,
 * submission, report, and evidence database records are removed by cascade.
 */
class AttpMelCleanSeeder extends Seeder
{
    private const REQUIRED_TABLES = [
        'me_frameworks',
        'myb_indicators',
        'me_indicator_reference_sheets',
        'me_indicator_targets',
        'me_indicator_calculation_rules',
        'me_performance_thresholds',
        'me_disaggregation_dimensions',
        'me_disaggregation_options',
        'me_indicator_disaggregation_requirements',
        'me_data_entry_forms',
        'me_data_entry_form_sections',
        'me_data_entry_form_fields',
        'me_data_entry_form_indicators',
        'me_reporting_periods',
        'me_data_collections',
        'me_data_collection_assignments',
        'me_data_submissions',
        'me_performance_reports',
        'me_performance_report_indicator_results',
        'me_indicator_achievements',
        'me_repository_folders',
        'me_repository_folder_indicators',
        'me_repository_document_links',
        'me_reporting_notification_logs',
    ];

    public function run(): void
    {
        $this->assertRequiredTablesExist();
        $this->command?->warn(
            'Replacing all controlled ATTP MEL framework, reporting-form, period, collection, assignment, submission, and report records.'
        );

        $summary = DB::transaction(function (): array {
            $this->purgeControlledDataset();
            $this->call(AttpMelFrameworkSeeder::class);

            return $this->validateCleanInstall();
        });

        $this->command?->info(sprintf(
            'Clean ATTP MEL install complete: %d indicators, %d targets, %d disaggregation requirements, %d forms, %d periods, %d collections, and %d assignments.',
            $summary['indicators'],
            $summary['targets'],
            $summary['requirements'],
            $summary['forms'],
            $summary['periods'],
            $summary['collections'],
            $summary['assignments'],
        ));
    }

    private function assertRequiredTablesExist(): void
    {
        $missing = collect(self::REQUIRED_TABLES)
            ->reject(fn (string $table): bool => Schema::hasTable($table))
            ->values();

        if ($missing->isNotEmpty()) {
            throw new RuntimeException(
                'Run all database migrations before the ATTP MEL clean seeder. Missing tables: '.$missing->implode(', ')
            );
        }
    }

    private function purgeControlledDataset(): void
    {
        $program = Program::query()->where('program_id', 'PROG00001')->first();
        if (! $program) {
            throw new RuntimeException('ATTP program PROG00001 was not found. Run the ATTP programme seeders first.');
        }

        $frameworkIds = DB::table('me_frameworks')
            ->where('code', AttpMelFrameworkInstaller::FRAMEWORK_CODE)
            ->pluck('id');
        $indicatorIds = DB::table('myb_indicators')
            ->where(function ($query) use ($frameworkIds, $program): void {
                $query->whereIn('indicator_code', AttpMelFrameworkInstaller::INDICATOR_CODES)
                    ->orWhere(function ($ownerQuery) use ($program): void {
                        $ownerQuery->where('indicatorable_type', Program::class)
                            ->where('indicatorable_id', $program->id);
                    });
                if ($frameworkIds->isNotEmpty()) {
                    $query->orWhereIn('framework_id', $frameworkIds->all());
                }
            })
            ->pluck('id');

        $formIds = DB::table('me_data_entry_forms')
            ->whereIn('code', AttpMelThinkTankReportingSeeder::FORM_CODES)
            ->when($indicatorIds->isNotEmpty(), fn ($query) => $query
                ->orWhereIn('indicator_id', $indicatorIds->all())
                ->orWhereIn('id', DB::table('me_data_entry_form_indicators')
                    ->whereIn('indicator_id', $indicatorIds->all())
                    ->pluck('form_id')
                    ->all()))
            ->pluck('id');
        $periodIds = DB::table('me_reporting_periods')
            ->where('code', 'like', 'ATTP-MEL-%')
            ->pluck('id');
        $collectionIds = collect();
        if ($formIds->isNotEmpty() || $periodIds->isNotEmpty()) {
            $collectionIds = DB::table('me_data_collections')
                ->where(function ($query) use ($formIds, $periodIds): void {
                    if ($formIds->isNotEmpty()) {
                        $query->whereIn('form_id', $formIds->all());
                    }
                    if ($periodIds->isNotEmpty()) {
                        $method = $formIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('reporting_period_id', $periodIds->all());
                    }
                })
                ->pluck('id');
        }
        $assignmentIds = $collectionIds->isEmpty()
            ? collect()
            : DB::table('me_data_collection_assignments')->whereIn('collection_id', $collectionIds->all())->pluck('id');

        $reportIds = collect();
        if ($formIds->isNotEmpty()) {
            $reportIds = $reportIds->merge(
                DB::table('me_performance_reports')->whereIn('form_id', $formIds->all())->pluck('id')
            );
        }
        if ($periodIds->isNotEmpty()) {
            $reportIds = $reportIds->merge(
                DB::table('me_performance_reports')->whereIn('reporting_period_id', $periodIds->all())->pluck('id')
            );
        }
        if ($assignmentIds->isNotEmpty()) {
            $reportIds = $reportIds->merge(
                DB::table('me_performance_reports')->whereIn('assignment_id', $assignmentIds->all())->pluck('id')
            );
        }
        if ($indicatorIds->isNotEmpty()) {
            $reportIds = $reportIds
                ->merge(DB::table('me_performance_report_indicator_results')
                    ->whereIn('indicator_id', $indicatorIds->all())
                    ->pluck('report_id'))
                ->merge(DB::table('me_indicator_achievements')
                    ->whereIn('indicator_id', $indicatorIds->all())
                    ->pluck('report_id'));
        }
        $reportIds = $reportIds->filter()->unique()->values();

        if ($reportIds->isNotEmpty()) {
            $achievementIds = DB::table('me_indicator_achievements')
                ->whereIn('report_id', $reportIds->all())
                ->pluck('id');
            if ($achievementIds->isNotEmpty()) {
                DB::table('me_repository_document_links')
                    ->whereIn('linkable_type', [MeIndicatorAchievement::class, 'MeIndicatorAchievement'])
                    ->whereIn('linkable_id', $achievementIds->all())
                    ->delete();
            }
            DB::table('me_performance_reports')->whereIn('id', $reportIds->all())->delete();
        }

        if ($formIds->isNotEmpty()) {
            DB::table('me_data_entry_forms')->whereIn('id', $formIds->all())->delete();
        }
        if ($periodIds->isNotEmpty()) {
            DB::table('me_reporting_periods')->whereIn('id', $periodIds->all())->delete();
        }

        if ($frameworkIds->isNotEmpty()) {
            foreach (['me_indicator_reference_sheets', 'me_indicator_targets', 'me_indicator_calculation_rules'] as $table) {
                DB::table($table)->whereIn('framework_id', $frameworkIds->all())->delete();
            }
        }
        if ($indicatorIds->isNotEmpty()) {
            DB::table('myb_indicators')->whereIn('id', $indicatorIds->all())->delete();
        }
        if ($frameworkIds->isNotEmpty()) {
            DB::table('me_frameworks')->whereIn('id', $frameworkIds->all())->delete();
        }

        $dimensionIds = DB::table('me_disaggregation_dimensions')
            ->whereIn('code', AttpMelThinkTankReportingSeeder::DIMENSION_CODES)
            ->pluck('id');
        if ($dimensionIds->isNotEmpty()) {
            DB::table('me_disaggregation_options')->whereIn('dimension_id', $dimensionIds->all())->delete();
        }

        DB::table('me_reporting_notification_logs')->delete();
    }

    /** @return array{indicators:int,targets:int,requirements:int,forms:int,periods:int,collections:int,assignments:int} */
    private function validateCleanInstall(): array
    {
        $errors = collect();
        $program = Program::query()->where('program_id', 'PROG00001')->firstOrFail();
        $portfolioId = (string) $program->sector_id;
        $frameworks = DB::table('me_frameworks')
            ->where('code', AttpMelFrameworkInstaller::FRAMEWORK_CODE)
            ->get();
        $framework = $frameworks->firstWhere('is_current', true);
        $this->expect($errors, $frameworks->count() === 1, 'exactly one ATTP-RF framework version');
        $this->expect($errors, $framework && $framework->status === 'active', 'one active current ATTP-RF framework');

        $indicatorQuery = DB::table('myb_indicators')
            ->whereIn('indicator_code', AttpMelFrameworkInstaller::INDICATOR_CODES);
        $indicatorIds = (clone $indicatorQuery)->pluck('id');
        $indicatorCount = $indicatorIds->count();
        $this->expect(
            $errors,
            $indicatorCount === count(AttpMelFrameworkInstaller::INDICATOR_CODES),
            count(AttpMelFrameworkInstaller::INDICATOR_CODES).' official indicators'
        );
        if ($framework) {
            $this->expect(
                $errors,
                (clone $indicatorQuery)->where('framework_id', $framework->id)->count() === $indicatorCount,
                'every indicator linked to the current framework'
            );
        }
        $this->expect(
            $errors,
            (clone $indicatorQuery)->where(function ($query): void {
                $query->whereNull('unit_id')
                    ->orWhereNull('frequency_of_reporting_id')
                    ->orWhereNull('responsible_user_id')
                    ->orWhereNull('means_of_verification_folder_id')
                    ->orWhereNull('data_collection_method');
            })->doesntExist(),
            'complete measurement, schedule, owner, collection, and evidence configuration for every indicator'
        );

        $targetCount = $indicatorIds->isEmpty()
            ? 0
            : DB::table('me_indicator_targets')->whereIn('indicator_id', $indicatorIds->all())->count();
        $this->expect($errors, $targetCount === 90, '90 clean targets (18 setup plus 72 official period targets)');
        $this->expect(
            $errors,
            DB::table('me_indicator_reference_sheets')->whereIn('indicator_id', $indicatorIds->all())->count() === 18,
            '18 approved Indicator Reference Sheets'
        );
        $this->expect(
            $errors,
            DB::table('me_indicator_calculation_rules')->whereIn('indicator_id', $indicatorIds->all())->count() === 4,
            'four system-calculation rules'
        );
        $this->expect(
            $errors,
            $framework && DB::table('me_performance_thresholds')->where('framework_id', $framework->id)->count() === 4,
            'four performance thresholds'
        );

        $dimensionCount = DB::table('me_disaggregation_dimensions')
            ->whereIn('code', AttpMelThinkTankReportingSeeder::DIMENSION_CODES)
            ->distinct('code')
            ->count('code');
        $this->expect(
            $errors,
            $dimensionCount === count(AttpMelThinkTankReportingSeeder::DIMENSION_CODES),
            count(AttpMelThinkTankReportingSeeder::DIMENSION_CODES).' required disaggregation dimensions'
        );
        $requirementCount = $indicatorIds->isEmpty()
            ? 0
            : DB::table('me_indicator_disaggregation_requirements')
                ->whereIn('indicator_id', $indicatorIds->all())
                ->count();
        $this->expect($errors, $requirementCount === 24, '24 indicator disaggregation requirements');

        $formIds = DB::table('me_data_entry_forms')
            ->whereIn('code', AttpMelThinkTankReportingSeeder::FORM_CODES)
            ->pluck('id');
        $formCount = $formIds->count();
        $this->expect($errors, $formCount === count(AttpMelThinkTankReportingSeeder::FORM_CODES), 'seven published Think Tank forms');
        $this->expect(
            $errors,
            DB::table('me_data_entry_forms')
                ->whereIn('id', $formIds->all())
                ->where(function ($query) use ($portfolioId): void {
                    $query->where('portfolio_id', '<>', $portfolioId)
                        ->orWhereNull('portfolio_id')
                        ->orWhereNull('responsible_user_id')
                        ->orWhereNull('indicator_id')
                        ->orWhereNull('project_component_id')
                        ->orWhere('status', '<>', 'published');
                })
                ->doesntExist(),
            'portfolio-scoped, assigned, published forms'
        );
        $this->expect(
            $errors,
            DB::table('me_data_entry_form_sections')->whereIn('form_id', $formIds->all())->count() === 21,
            'three sections for each reporting form'
        );
        $this->expect(
            $errors,
            DB::table('me_data_entry_form_fields')
                ->whereIn('form_id', $formIds->all())
                ->where('field_type', 'file')
                ->count() === 7,
            'one required evidence-upload field for each reporting form'
        );

        $periodIds = DB::table('me_reporting_periods')
            ->where('code', 'like', 'ATTP-MEL-%')
            ->pluck('id');
        $periodCount = $periodIds->count();
        if (CarbonImmutable::today()->lessThanOrEqualTo(CarbonImmutable::parse('2028-08-30'))) {
            $this->expect($errors, $periodCount > 0, 'at least one planned reporting period before project close');
        }
        $this->expect(
            $errors,
            DB::table('me_reporting_periods')
                ->whereIn('id', $periodIds->all())
                ->where(function ($query) use ($portfolioId): void {
                    $query->where('portfolio_id', '<>', $portfolioId)->orWhereNull('portfolio_id');
                })
                ->doesntExist(),
            'portfolio-scoped reporting periods'
        );

        $collectionQuery = DB::table('me_data_collections')
            ->whereIn('form_id', $formIds->all())
            ->whereIn('reporting_period_id', $periodIds->all());
        $collectionIds = (clone $collectionQuery)->pluck('id');
        $collectionCount = $collectionIds->count();
        $this->expect($errors, $collectionCount === $formCount * $periodCount, 'one collection per form and reporting period');

        $activeThinkTanks = DB::table('attp_consortium_think_tanks')->where('status', 'active')->count();
        $assignmentCount = $collectionIds->isEmpty()
            ? 0
            : DB::table('me_data_collection_assignments')->whereIn('collection_id', $collectionIds->all())->count();
        $this->expect(
            $errors,
            $assignmentCount === $collectionCount * $activeThinkTanks,
            'every collection assigned to every active Think Tank'
        );

        $this->expect(
            $errors,
            DB::table('me_repository_folder_indicators')->whereIn('indicator_id', $indicatorIds->all())->count() === 18,
            'one controlled evidence folder linked to each indicator'
        );

        if ($errors->isNotEmpty()) {
            throw new RuntimeException('ATTP MEL clean install validation failed: '.$errors->implode('; ').'.');
        }

        return [
            'indicators' => $indicatorCount,
            'targets' => $targetCount,
            'requirements' => $requirementCount,
            'forms' => $formCount,
            'periods' => $periodCount,
            'collections' => $collectionCount,
            'assignments' => $assignmentCount,
        ];
    }

    private function expect(Collection $errors, bool $condition, string $expectation): void
    {
        if (! $condition) {
            $errors->push($expectation);
        }
    }
}
