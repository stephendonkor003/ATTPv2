<?php

use App\Exports\MeIndicatorReportExport;
use App\Exports\Sheets\MeIndicatorConsolidationSheet;
use App\Exports\Sheets\MeIndicatorReportContributionSheet;
use App\Exports\Sheets\MeIndicatorReportEvidenceSheet;
use App\Exports\Sheets\MeIndicatorReportProfileSheet;
use App\Exports\Sheets\MeIndicatorReportSummarySheet;
use App\Models\Indicator;
use App\Models\Program;
use App\Models\Project;
use App\Models\Sector;
use App\Services\AttpMelResultsService;
use App\Services\MeConsolidationEngineService;
use App\Services\MeIndicatorReportService;
use Illuminate\Support\Carbon;

it('wires the indicator report through routes controller service views and exports', function () {
    $root = dirname(__DIR__, 2);
    $paths = [
        'controller' => $root.'/app/Http/Controllers/MeIndicatorReportController.php',
        'service' => $root.'/app/Services/MeIndicatorReportService.php',
        'workbook' => $root.'/app/Exports/MeIndicatorReportExport.php',
        'summarySheet' => $root.'/app/Exports/Sheets/MeIndicatorReportSummarySheet.php',
        'profileSheet' => $root.'/app/Exports/Sheets/MeIndicatorReportProfileSheet.php',
        'contributionSheet' => $root.'/app/Exports/Sheets/MeIndicatorReportContributionSheet.php',
        'evidenceSheet' => $root.'/app/Exports/Sheets/MeIndicatorReportEvidenceSheet.php',
        'index' => $root.'/resources/views/me/indicator-reports/index.blade.php',
        'pdf' => $root.'/resources/views/me/indicator-reports/pdf.blade.php',
    ];

    foreach ($paths as $path) {
        expect(file_exists($path))->toBeTrue("Expected indicator-report file [{$path}] to exist.");
    }

    $routes = file_get_contents($root.'/routes/web.php');
    $controller = file_get_contents($paths['controller']);
    $service = file_get_contents($paths['service']);
    $workbook = file_get_contents($paths['workbook']);
    $index = file_get_contents($paths['index']);
    $pdf = file_get_contents($paths['pdf']);

    expect($routes)
        ->toContain("Route::prefix('me/indicator-reports')")
        ->toContain("->name('me.indicator-reports.')")
        ->toContain("Route::get('/', 'index')->name('index')")
        ->toContain("Route::get('export/excel', 'excel')->name('excel')")
        ->toContain("Route::get('export/csv', 'csv')->name('csv')")
        ->toContain("Route::get('export/pdf', 'pdf')->name('pdf')")
        ->and($controller)
        ->toContain('use ScopesAssignedPortfolios;')
        ->toContain("view('me.indicator-reports.index'")
        ->toContain("Pdf::loadView('me.indicator-reports.pdf'")
        ->toContain('new MeIndicatorReportExport(')
        ->toContain('new MeIndicatorReportContributionSheet(')
        ->toContain('new MeIndicatorConsolidationSheet(')
        ->toContain("'individual' => 'Individual indicator report'")
        ->toContain("'consolidated' => 'Consolidated indicator report'")
        ->toContain('permission:me.results.view|me.performance_reports.view|me.configuration.view|me.configuration.manage')
        ->toContain('permission:me.reports.export|me.performance_reports.view|me.configuration.manage')
        ->and($service)
        ->toContain('private readonly MeConsolidationEngineService $consolidationEngine')
        ->toContain("\$mode === 'individual'")
        ->toContain("'selectedIndicatorRow' => \$selectedIndicatorRow")
        ->toContain("'contributionRows' => \$contributionRows")
        ->toContain("'evidenceRows' => \$evidenceRows")
        ->toContain("'reportSummary' => \$summary")
        ->and($workbook)
        ->toContain('class MeIndicatorReportExport')
        ->toContain("in_array(\$mode, ['individual', 'consolidated'], true)")
        ->toContain('MeIndicatorReportSummarySheet')
        ->toContain('MeIndicatorReportProfileSheet')
        ->toContain('MeIndicatorConsolidationSheet')
        ->toContain('MeIndicatorReportContributionSheet')
        ->toContain('MeIndicatorReportEvidenceSheet')
        ->and($index)
        ->toContain('M&amp;E Indicator Reports')
        ->toContain('Approved-only indicator reporting is active')
        ->toContain('Individual indicator report')
        ->toContain('Consolidated indicator report')
        ->toContain('Indicator profile and governance')
        ->toContain('Indicator methodology and calculation')
        ->toContain('Approved source contributions')
        ->toContain('Consolidated indicator register')
        ->toContain('Evidence and data-quality disclosures')
        ->toContain('name="mode"')
        ->toContain('name="portfolio_id"')
        ->toContain('name="component_id"')
        ->toContain('name="indicator_id"')
        ->toContain('name="reporting_period_id"')
        ->toContain("route('budget.me.indicator-reports.excel'")
        ->toContain("route('budget.me.indicator-reports.csv'")
        ->toContain("route('budget.me.indicator-reports.pdf'")
        ->and($pdf)
        ->toContain('Indicator Report')
        ->toContain('Official approved-results output');
});

it('keeps approved-only and authorized indicator source safeguards in the reporting pipeline', function () {
    $root = dirname(__DIR__, 2);
    $officialResults = file_get_contents($root.'/app/Services/AttpMelResultsService.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/MeIndicatorReportController.php');

    expect($officialResults)
        ->toContain('->approved()')
        ->toContain("'targets' => fn (\$q) => \$q->where('approval_status', 'approved')")
        ->toContain("array_key_exists('indicator_ids', \$filters)")
        ->toContain('$query->whereKey($indicatorIds)')
        ->toContain('$this->sourceIndicators($indicator, $filters)')
        ->toContain("->where('framework_id', \$indicator->framework_id)")
        ->and($controller)
        ->toContain('$this->applyAssignedPortfolioScopeToIndicators($scope, $request->user())')
        ->toContain("\$scope->orWhereIn('project_component_id', \$projects->pluck('id'))")
        ->toContain("\$serviceFilters['indicator_ids'] = \$indicators->pluck('id')->all()")
        ->toContain("! \$indicators->contains('id', \$filters['indicator_id'])")
        ->toContain("! \$periods->contains('id', \$filters['reporting_period_id'])")
        ->toContain("if (\$filters['mode'] === 'individual')")
        ->toContain("\$serviceFilters['performance_status'] = null")
        ->toContain("abort(422, 'Choose an indicator before downloading an individual indicator report.')");
});

it('flattens approved contributions and evidence with stable provenance keys', function () {
    $portfolio = new Sector(['name' => 'Economic Transformation Portfolio']);
    $portfolio->id = '00000000-0000-0000-0000-000000000101';

    $program = new Program(['name' => 'Africa Think Tank Platform']);
    $program->id = '00000000-0000-0000-0000-000000000102';
    $program->setRelation('sector', $portfolio);

    $project = new Project([
        'program_id' => $program->id,
        'project_id' => 'ATTP-C2',
        'name' => 'Policy research quality and uptake',
    ]);
    $project->id = '00000000-0000-0000-0000-000000000103';
    $project->setRelation('program', $program);

    $indicator = new Indicator([
        'indicator_code' => 'INTC2.3',
        'name' => 'Policy engagement events',
        'project_component_id' => $project->id,
        'results_level' => 'intermediate_results',
        'value_type' => 'number',
    ]);
    $indicator->id = '00000000-0000-0000-0000-000000000104';
    $indicator->setRelation('projectComponent', $project);

    $approvedAt = Carbon::parse('2026-08-24 09:30:00');
    $indicatorRows = collect([
        indicatorReportSourceRow($indicator, [
            'source_contributions' => [
                [
                    'id' => '00000000-0000-0000-0000-000000000201',
                    'organization' => 'Policy Centre Alpha',
                    'country' => 'Ghana',
                    'period' => '2026 Q1',
                    'actual' => 12,
                    'rollup_numerator' => null,
                    'rollup_denominator' => null,
                    'data_source' => 'Approved quarterly performance report',
                    'evidence_count' => 2,
                    'verified_evidence_count' => 2,
                    'achievement_count' => 1,
                    'approved_at' => $approvedAt,
                    'evidence_links' => [
                        [
                            'key' => 'performance-report:document-1',
                            'source' => 'Performance report',
                            'title' => 'Signed event register',
                            'status' => 'validated',
                            'verified' => true,
                        ],
                        [
                            'key' => 'performance-report:document-1',
                            'source' => 'Performance report',
                            'title' => 'Signed event register',
                            'status' => 'validated',
                            'verified' => true,
                        ],
                    ],
                ],
                [
                    'id' => '00000000-0000-0000-0000-000000000202',
                    'organization' => 'Policy Centre Beta',
                    'country' => 'Kenya',
                    'period' => '2026 Q1',
                    'actual' => 8,
                    'rollup_numerator' => null,
                    'rollup_denominator' => null,
                    'data_source' => 'Approved structured submission',
                    'evidence_count' => 1,
                    'verified_evidence_count' => 0,
                    'achievement_count' => 1,
                    'approved_at' => $approvedAt->copy()->addHour(),
                    'evidence_links' => [[
                        'key' => 'submission:evidence-2',
                        'source' => 'Structured submission',
                        'title' => 'Submission workbook',
                        'status' => 'pending',
                        'verified' => false,
                    ]],
                ],
            ],
        ]),
    ]);

    $service = new MeIndicatorReportService(
        new MeConsolidationEngineService(app(AttpMelResultsService::class))
    );
    $contributions = $service->contributionRows($indicatorRows);
    $evidence = $service->evidenceRows($contributions);

    expect($contributions)->toHaveCount(2)
        ->and($contributions->first())->toMatchArray([
            'indicator_id' => $indicator->id,
            'indicator_code' => 'INTC2.3',
            'indicator_name' => 'Policy engagement events',
            'project_component_id' => $project->id,
            'project_component_code' => 'ATTP-C2',
            'project_component_name' => 'Policy research quality and uptake',
            'source_result_id' => '00000000-0000-0000-0000-000000000201',
            'organization' => 'Policy Centre Alpha',
            'actual' => 12,
        ])
        ->and($contributions->pluck('source_result_id')->all())->toBe([
            '00000000-0000-0000-0000-000000000201',
            '00000000-0000-0000-0000-000000000202',
        ])
        ->and($evidence)->toHaveCount(2)
        ->and($evidence->first())->toMatchArray([
            'indicator_id' => $indicator->id,
            'source_result_id' => '00000000-0000-0000-0000-000000000201',
            'evidence_key' => 'performance-report:document-1',
            'evidence_source' => 'Performance report',
            'title' => 'Signed event register',
            'status' => 'validated',
            'verified' => true,
        ])
        ->and($evidence->last())->toMatchArray([
            'source_result_id' => '00000000-0000-0000-0000-000000000202',
            'evidence_key' => 'submission:evidence-2',
            'evidence_source' => 'Structured submission',
            'verified' => false,
        ]);
});

it('keeps individual and consolidated workbook contracts stable', function () {
    $summary = [
        'indicator_count' => 0,
        'reported_indicator_count' => 0,
        'approved_contribution_count' => 0,
        'reporting_organizations' => collect(),
    ];
    $arguments = [$summary, collect(), collect(), collect(), ['project_year' => 1], 'Authorized scope'];

    $individual = (new MeIndicatorReportExport('individual', ...$arguments))->sheets();
    $consolidated = (new MeIndicatorReportExport('consolidated', ...$arguments))->sheets();

    expect($individual)->toHaveCount(4)
        ->and($individual[0])->toBeInstanceOf(MeIndicatorReportSummarySheet::class)
        ->and($individual[0]->title())->toBe('Summary & Scope')
        ->and($individual[1])->toBeInstanceOf(MeIndicatorReportProfileSheet::class)
        ->and($individual[1]->title())->toBe('Indicator Profile')
        ->and($individual[1]->headings())->toHaveCount(51)
        ->and($individual[1]->headings())->toContain(
            'Indicator Code',
            'IRS Definition',
            'Approved Consolidated Actual',
            'Reporting Completeness %',
            'Calculation Note'
        )
        ->and($individual[2])->toBeInstanceOf(MeIndicatorReportContributionSheet::class)
        ->and($individual[2]->title())->toBe('Approved Contributions')
        ->and($individual[2]->headings())->toHaveCount(30)
        ->and($individual[2]->headings())->toContain(
            'Source Result ID',
            'Contributor Organization',
            'Approved Target',
            'Consolidated Actual',
            'Roll-up Numerator',
            'Approved At',
            'Calculation Note'
        )
        ->and($individual[3])->toBeInstanceOf(MeIndicatorReportEvidenceSheet::class)
        ->and($individual[3]->title())->toBe('Evidence Links')
        ->and($individual[3]->headings())->toHaveCount(14)
        ->and($individual[3]->headings())->toContain(
            'Project Component Code',
            'Contributor Country',
            'Evidence Key',
            'Evidence Source',
            'Validation Status',
            'Verified',
            'Result Approved At'
        )
        ->and($consolidated)->toHaveCount(4)
        ->and($consolidated[0])->toBeInstanceOf(MeIndicatorReportSummarySheet::class)
        ->and($consolidated[1])->toBeInstanceOf(MeIndicatorConsolidationSheet::class)
        ->and($consolidated[1]->title())->toBe('Indicator Consolidation')
        ->and($consolidated[1]->headings())->toHaveCount(43)
        ->and($consolidated[2])->toBeInstanceOf(MeIndicatorReportContributionSheet::class)
        ->and($consolidated[3])->toBeInstanceOf(MeIndicatorReportEvidenceSheet::class);

    expect(fn () => new MeIndicatorReportExport('project', ...$arguments))
        ->toThrow(InvalidArgumentException::class);
});

/** @param array<string, mixed> $overrides */
function indicatorReportSourceRow(Indicator $indicator, array $overrides = []): array
{
    return array_replace([
        'indicator' => $indicator,
        'baseline' => 0,
        'target_value' => 20.0,
        'target_text' => null,
        'actual' => 20.0,
        'unit_label' => 'events',
        'achievement_percent' => 100.0,
        'classification' => ['code' => 'achieved', 'label' => 'Achieved', 'color' => '#15935d'],
        'result_count' => 2,
        'reported_organizations' => 2,
        'expected_organizations' => 2,
        'reporting_completeness' => 100.0,
        'source_contributions' => [],
        'evidence_count' => 0,
        'verified_evidence_count' => 0,
        'achievement_count' => 0,
        'beneficiary_count' => 0,
        'female_beneficiaries' => 0,
        'male_beneficiaries' => 0,
        'latest_approved_at' => null,
        'calculation_note' => 'Calculated from approved indicator results only.',
    ], $overrides);
}
