<?php

use App\Exports\MeConsolidationEngineExport;
use App\Exports\Sheets\MeConsolidationSummarySheet;
use App\Exports\Sheets\MeIndicatorConsolidationSheet;
use App\Exports\Sheets\MeProjectConsolidationSheet;
use App\Models\Indicator;
use App\Models\Program;
use App\Models\Project;
use App\Models\Sector;
use App\Services\AttpMelResultsService;
use App\Services\MeConsolidationEngineService;
use Illuminate\Support\Carbon;

it('wires the consolidation engine through its routes controller views exports and approved-results service', function () {
    $root = dirname(__DIR__, 2);
    $paths = [
        'controller' => $root.'/app/Http/Controllers/MeConsolidationEngineController.php',
        'service' => $root.'/app/Services/MeConsolidationEngineService.php',
        'workbook' => $root.'/app/Exports/MeConsolidationEngineExport.php',
        'summarySheet' => $root.'/app/Exports/Sheets/MeConsolidationSummarySheet.php',
        'indicatorSheet' => $root.'/app/Exports/Sheets/MeIndicatorConsolidationSheet.php',
        'projectSheet' => $root.'/app/Exports/Sheets/MeProjectConsolidationSheet.php',
        'index' => $root.'/resources/views/me/consolidation-engine/index.blade.php',
        'pdf' => $root.'/resources/views/me/consolidation-engine/pdf.blade.php',
    ];

    foreach ($paths as $path) {
        expect(file_exists($path))->toBeTrue("Expected consolidation-engine file [{$path}] to exist.");
    }

    $routes = file_get_contents($root.'/routes/web.php');
    $controller = file_get_contents($paths['controller']);
    $service = file_get_contents($paths['service']);
    $workbook = file_get_contents($paths['workbook']);
    $summarySheet = file_get_contents($paths['summarySheet']);
    $indicatorSheet = file_get_contents($paths['indicatorSheet']);
    $projectSheet = file_get_contents($paths['projectSheet']);
    $index = file_get_contents($paths['index']);
    $pdf = file_get_contents($paths['pdf']);

    expect($routes)
        ->toContain("Route::prefix('me/consolidation-engine')")
        ->toContain("->name('me.consolidation-engine.')")
        ->toContain("Route::get('/', 'index')->name('index')")
        ->toContain("Route::get('export/excel', 'excel')->name('excel')")
        ->toContain("Route::get('export/csv', 'csv')->name('csv')")
        ->toContain("Route::get('export/pdf', 'pdf')->name('pdf')")
        ->and($controller)
        ->toContain('use ScopesAssignedPortfolios;')
        ->toContain("view('me.consolidation-engine.index'")
        ->toContain("Pdf::loadView('me.consolidation-engine.pdf'")
        ->toContain('new MeConsolidationEngineExport(')
        ->toContain('new MeIndicatorConsolidationSheet(')
        ->toContain('new MeProjectConsolidationSheet(')
        ->toContain('permission:me.results.view|me.performance_reports.view|me.configuration.view|me.configuration.manage')
        ->toContain('permission:me.reports.export|me.performance_reports.view|me.configuration.manage')
        ->and($service)
        ->toContain('private readonly AttpMelResultsService $officialResults')
        ->toContain("'report_type' => 'results_framework'")
        ->toContain("'indicatorRows' => \$indicatorRows")
        ->toContain("'projectRows' => \$projectRows")
        ->toContain("'engineSummary' => [")
        ->toContain('Raw indicator actuals')
        ->toContain('capped at 100%')
        ->and($workbook)
        ->toContain('class MeConsolidationEngineExport')
        ->toContain('MeIndicatorConsolidationSheet')
        ->toContain('MeProjectConsolidationSheet')
        ->and($summarySheet)
        ->toContain('class MeConsolidationSummarySheet')
        ->toContain("return 'Summary & Scope';")
        ->toContain('Official Data Guardrail')
        ->toContain('Approved Contributions')
        ->and($indicatorSheet)
        ->toContain('class MeIndicatorConsolidationSheet')
        ->toContain('Indicator Code')
        ->toContain('Achievement %')
        ->and($projectSheet)
        ->toContain('class MeProjectConsolidationSheet')
        ->toContain('Reported Indicators')
        ->toContain('Average Achievement %')
        ->and($index)
        ->toContain('Consolidations Engine')
        ->toContain('name="level"')
        ->toContain('name="portfolio_id"')
        ->toContain('name="component_id"')
        ->toContain('name="indicator_id"')
        ->toContain('name="reporting_period_id"')
        ->toContain("route('budget.me.consolidation-engine.excel'")
        ->toContain("route('budget.me.consolidation-engine.csv'")
        ->toContain("route('budget.me.consolidation-engine.pdf'")
        ->and($pdf)
        ->toContain('Consolidations Engine')
        ->toContain('Approved results only');
});

it('keeps the spreadsheet and CSV export contracts stable', function () {
    $export = new MeConsolidationEngineExport(
        [
            'indicator_count' => 0,
            'reporting_organizations' => collect(),
        ],
        collect(),
        collect(),
        ['level' => 'indicator'],
        'All authorized approved results'
    );

    $sheets = $export->sheets();

    expect($sheets)->toHaveCount(3)
        ->and($sheets[0])->toBeInstanceOf(MeConsolidationSummarySheet::class)
        ->and($sheets[0]->title())->toBe('Summary & Scope')
        ->and($sheets[1])->toBeInstanceOf(MeProjectConsolidationSheet::class)
        ->and($sheets[1]->title())->toBe('Project Consolidation')
        ->and($sheets[1]->headings())->toHaveCount(34)
        ->and($sheets[1]->headings())->toContain(
            'Project / Results Area Code',
            'Average Achievement %',
            'Contributor Organizations',
            'Source Result IDs',
            'Contribution Provenance',
            'Calculation Note'
        )
        ->and($sheets[2])->toBeInstanceOf(MeIndicatorConsolidationSheet::class)
        ->and($sheets[2]->title())->toBe('Indicator Consolidation')
        ->and($sheets[2]->headings())->toHaveCount(43)
        ->and($sheets[2]->headings())->toContain(
            'Indicator Code',
            'Organization Roll-up',
            'Consolidated Actual',
            'Achievement %',
            'Source Result IDs',
            'Contribution Provenance',
            'Calculation Note'
        );
});

it('builds project scorecards without adding unlike indicator actuals', function () {
    $portfolio = new Sector(['name' => 'Economic Development Portfolio']);
    $portfolio->id = '00000000-0000-0000-0000-000000000001';

    $program = new Program([
        'program_id' => 'PROG-ATTP',
        'name' => 'Africa Think Tank Platform Project',
    ]);
    $program->id = '00000000-0000-0000-0000-000000000002';
    $program->setRelation('sector', $portfolio);

    $project = new Project([
        'program_id' => $program->id,
        'project_id' => 'PROG-ATTP-02',
        'name' => 'Strengthen policy research quality and uptake',
    ]);
    $project->id = '00000000-0000-0000-0000-000000000003';
    $project->setRelation('program', $program);

    $achievedIndicator = new Indicator([
        'indicator_code' => 'INTC2.3',
        'name' => 'Policy engagement events',
        'project_component_id' => $project->id,
    ]);
    $achievedIndicator->id = '00000000-0000-0000-0000-000000000011';
    $achievedIndicator->setRelation('projectComponent', $project);

    $attentionIndicator = new Indicator([
        'indicator_code' => 'INTC2.8',
        'name' => 'Female staff receiving mentoring',
        'project_component_id' => $project->id,
    ]);
    $attentionIndicator->id = '00000000-0000-0000-0000-000000000012';
    $attentionIndicator->setRelation('projectComponent', $project);

    $pdoIndicator = new Indicator([
        'indicator_code' => 'PDO 1',
        'name' => 'Platform established and operational',
        'results_level' => 'pdo',
    ]);
    $pdoIndicator->id = '00000000-0000-0000-0000-000000000013';
    $pdoIndicator->setRelation('projectComponent', null);

    $rows = collect([
        projectScoreSourceRow($achievedIndicator, [
            'actual' => 320,
            'achievement_percent' => 120,
            'result_count' => 2,
            'expected_organizations' => 2,
            'reported_organizations' => 2,
            'classification' => ['code' => 'achieved'],
            'reporting_organizations' => ['Policy Centre Alpha', 'Policy Centre Beta'],
            'evidence_count' => 2,
            'verified_evidence_count' => 1,
            'achievement_count' => 3,
            'beneficiary_count' => 10,
            'female_beneficiaries' => 6,
            'male_beneficiaries' => 4,
            'latest_approved_at' => Carbon::parse('2026-08-20 10:00:00'),
        ]),
        projectScoreSourceRow($attentionIndicator, [
            'actual' => 55.5,
            'achievement_percent' => 50,
            'result_count' => 1,
            'expected_organizations' => 2,
            'reported_organizations' => 1,
            'classification' => ['code' => 'needs_attention'],
            'reporting_organizations' => ['Policy Centre Alpha'],
            'evidence_count' => 1,
            'verified_evidence_count' => 1,
            'achievement_count' => 1,
            'beneficiary_count' => 5,
            'female_beneficiaries' => 5,
            'male_beneficiaries' => 0,
            'latest_approved_at' => Carbon::parse('2026-08-21 11:00:00'),
        ]),
        projectScoreSourceRow($pdoIndicator, [
            'actual' => 'Platform governance charter approved',
            'achievement_percent' => null,
            'result_count' => 0,
            'expected_organizations' => 1,
            'reported_organizations' => 0,
            'classification' => ['code' => 'not_rated'],
        ]),
    ]);

    $service = new MeConsolidationEngineService(app(AttpMelResultsService::class));
    $projectRows = $service->buildProjectRows($rows);
    $component = $projectRows->firstWhere('key', $project->id);
    $pdo = $projectRows->firstWhere('key', 'pdo');

    expect($projectRows)->toHaveCount(2)
        ->and($projectRows->first()['key'])->toBe('pdo')
        ->and($component)->not->toBeNull()
        ->and($component['code'])->toBe('PROG-ATTP-02')
        ->and($component['program'])->toBe('Africa Think Tank Platform Project')
        ->and($component['portfolio'])->toBe('Economic Development Portfolio')
        ->and($component['indicator_count'])->toBe(2)
        ->and($component['reported_indicator_count'])->toBe(2)
        ->and($component['rated_indicator_count'])->toBe(2)
        ->and($component['average_achievement'])->toBe(75.0)
        ->and($component['reporting_completeness'])->toBe(75.0)
        ->and($component['approved_contribution_count'])->toBe(3)
        ->and($component['organization_count'])->toBe(2)
        ->and($component['organizations']->all())->toBe(['Policy Centre Alpha', 'Policy Centre Beta'])
        ->and($component['evidence_count'])->toBe(3)
        ->and($component['verified_evidence_count'])->toBe(2)
        ->and($component['evidence_verification_rate'])->toBe(66.7)
        ->and($component['achievement_count'])->toBe(4)
        ->and($component['beneficiary_count'])->toBe(15)
        ->and($component['female_beneficiaries'])->toBe(11)
        ->and($component['male_beneficiaries'])->toBe(4)
        ->and($component['status']['code'])->toBe('mixed')
        ->and($component['performance_mix']->all())->toBe(['achieved' => 1, 'needs_attention' => 1])
        ->and($component['latest_approved_at']->equalTo(Carbon::parse('2026-08-21 11:00:00')))->toBeTrue()
        ->and($component['calculation_note'])->toContain('capped at 100%')
        ->and($component['calculation_note'])->toContain('never added across unlike units')
        ->and(array_key_exists('actual', $component))->toBeFalse()
        ->and(array_key_exists('target_value', $component))->toBeFalse()
        ->and($component['indicator_rows']->pluck('actual')->all())->toBe([320, 55.5])
        ->and($pdo)->not->toBeNull()
        ->and($pdo['code'])->toBe('PDO')
        ->and($pdo['indicator_count'])->toBe(1)
        ->and($pdo['status']['code'])->toBe('not_rated');
});

it('aggregates within each organization before applying the organization roll-up', function () {
    $indicator = new Indicator([
        'value_type' => 'number',
        'aggregation_method' => 'sum',
        'organization_rollup_method' => 'average',
    ]);
    $results = collect([
        organizationResult('organization-a', 10, '2026-03-31'),
        organizationResult('organization-a', 20, '2026-06-30'),
        organizationResult('organization-b', 5, '2026-03-31'),
        organizationResult('organization-b', 5, '2026-06-30'),
    ]);
    $method = new ReflectionMethod(app(AttpMelResultsService::class), 'consolidateAcrossOrganizations');

    expect($method->invoke(app(AttpMelResultsService::class), $indicator, $results))->toBe(20.0);
});

/** @param array<string, mixed> $overrides */
function projectScoreSourceRow(Indicator $indicator, array $overrides = []): array
{
    return array_replace([
        'indicator' => $indicator,
        'actual' => null,
        'achievement_percent' => null,
        'result_count' => 0,
        'expected_organizations' => 0,
        'reported_organizations' => 0,
        'classification' => ['code' => 'not_rated'],
        'reporting_organizations' => [],
        'evidence_count' => 0,
        'verified_evidence_count' => 0,
        'achievement_count' => 0,
        'beneficiary_count' => 0,
        'female_beneficiaries' => 0,
        'male_beneficiaries' => 0,
        'latest_approved_at' => null,
    ], $overrides);
}

function organizationResult(string $organizationId, float $value, string $periodEnd): \App\Models\IndicatorResult
{
    $result = new \App\Models\IndicatorResult;
    $result->setRawAttributes([
        'think_tank_member_id' => $organizationId,
        'actual_value' => $value,
        'period_end' => $periodEnd,
        'approved_at' => $periodEnd.' 10:00:00',
    ], true);

    return $result;
}
