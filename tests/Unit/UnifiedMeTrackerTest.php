<?php

use App\Models\Indicator;
use App\Models\ConsortiumThinkTank;
use App\Models\MeIndicatorAchievement;
use App\Models\MeIndicatorAchievementDisaggregation;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportIndicatorResult;
use App\Models\ReportingFrequency;
use App\Services\MeConsolidatedReportingService;
use App\Support\IndicatorReportingSchedule;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

it('matches indicators only to their approved general reporting period type', function () {
    $cases = [
        ['month', 1, 'quarter', 'Q1', true],
        ['quarterly', 1, 'quarter', 'Q4', true],
        ['month', 6, 'semi_annual', 'H1', true],
        ['annual', 1, 'annual', 'ANNUAL', true],
        ['month', 6, 'quarter', 'Q2', false],
        ['annual', 1, 'semi_annual', 'H2', false],
    ];

    foreach ($cases as [$unit, $value, $periodType, $periodLabel, $expected]) {
        $indicator = new Indicator();
        $indicator->setRelation('frequency', new ReportingFrequency([
            'name' => 'Test', 'code' => 'TEST', 'interval_unit' => $unit, 'interval_value' => $value,
        ]));
        expect(IndicatorReportingSchedule::isDueInPeriod($indicator, $periodType, $periodLabel))->toBe($expected);
    }
});

it('consolidates organization results using the indicator-authorized rollup control', function () {
    $indicator = new Indicator([
        'indicator_code' => 'PDO 2',
        'name' => 'Policy-relevant research products generated',
        'organization_rollup_method' => 'weighted_average',
    ]);
    $indicator->id = '00000000-0000-0000-0000-000000000010';
    $reports = collect([
        ['tank' => '00000000-0000-0000-0000-000000000101', 'actual' => 80, 'numerator' => 8, 'denominator' => 10],
        ['tank' => '00000000-0000-0000-0000-000000000102', 'actual' => 50, 'numerator' => 5, 'denominator' => 10],
    ])->map(function (array $values, int $index) use ($indicator) {
        $report = new MePerformanceReport([
            'think_tank_member_id' => $values['tank'],
            'status' => 'approved',
        ]);
        $report->setRawAttributes($report->getAttributes() + [
            'approved_at' => \Illuminate\Support\Carbon::parse('2026-07-01 10:0'.$index.':00'),
        ], true);
        $report->id = '00000000-0000-0000-0000-00000000020'.$index;
        $result = new MePerformanceReportIndicatorResult([
            'indicator_id' => $indicator->id,
            'actual_value' => $values['actual'],
            'rollup_numerator' => $values['numerator'],
            'rollup_denominator' => $values['denominator'],
        ]);
        $result->setRelation('indicator', $indicator);
        $result->setRelation('report', $report);
        $result->setRelation('achievements', new EloquentCollection());
        $report->setRelation('indicatorResults', new EloquentCollection([$result]));

        return $report;
    });

    $row = app(MeConsolidatedReportingService::class)->build($reports)->first();
    expect($row['value'])->toBe(65.0)
        ->and($row['organization_count'])->toBe(2)
        ->and($row['rollup_method'])->toBe('weighted_average')
        ->and($row['duplicate_result_count'])->toBe(0);
});

it('suppresses overlapping approved results from the same organization and period', function () {
    $indicator = new Indicator([
        'indicator_code' => 'PDO 2',
        'name' => 'Policy-relevant research products generated',
        'organization_rollup_method' => 'sum',
    ]);
    $indicator->id = '00000000-0000-0000-0000-000000000010';
    $source = [
        ['report' => '00000000-0000-0000-0000-000000000201', 'tank' => '00000000-0000-0000-0000-000000000101', 'actual' => 4, 'approved' => '2026-07-01 09:00:00'],
        ['report' => '00000000-0000-0000-0000-000000000202', 'tank' => '00000000-0000-0000-0000-000000000101', 'actual' => 7, 'approved' => '2026-07-02 09:00:00'],
        ['report' => '00000000-0000-0000-0000-000000000203', 'tank' => '00000000-0000-0000-0000-000000000102', 'actual' => 3, 'approved' => '2026-07-01 09:00:00'],
    ];
    $reports = collect($source)->map(function (array $values, int $index) use ($indicator) {
        $report = new MePerformanceReport([
            'think_tank_member_id' => $values['tank'],
            'status' => 'approved',
        ]);
        $report->setRawAttributes($report->getAttributes() + [
            'approved_at' => \Illuminate\Support\Carbon::parse($values['approved']),
        ], true);
        $report->id = $values['report'];
        $result = new MePerformanceReportIndicatorResult([
            'indicator_id' => $indicator->id,
            'actual_value' => $values['actual'],
        ]);
        $result->id = '00000000-0000-0000-0000-00000000030'.$index;
        $result->setRelation('indicator', $indicator);
        $result->setRelation('achievements', new EloquentCollection());
        $report->setRelation('indicatorResults', new EloquentCollection([$result]));

        return $report;
    });

    $row = app(MeConsolidatedReportingService::class)->build($reports)->first();

    expect($row['value'])->toBe(10.0)
        ->and($row['organization_count'])->toBe(2)
        ->and($row['duplicate_result_count'])->toBe(1);
});

it('preserves qualitative milestone results with their reporting organization', function () {
    $indicator = new Indicator([
        'indicator_code' => 'INT C1.1',
        'name' => 'Platform establishment milestone',
        'value_type' => 'milestone',
        'organization_rollup_method' => 'non_additive',
    ]);
    $indicator->id = '00000000-0000-0000-0000-000000000040';
    $reports = collect([
        ['tank' => '00000000-0000-0000-0000-000000000141', 'name' => 'Policy Centre Alpha', 'actual' => 'Governance charter approved'],
        ['tank' => '00000000-0000-0000-0000-000000000142', 'name' => 'Policy Centre Beta', 'actual' => 'Secretariat established'],
    ])->map(function (array $values, int $index) use ($indicator) {
        $report = new MePerformanceReport(['think_tank_member_id' => $values['tank'], 'status' => 'approved']);
        $report->id = '00000000-0000-0000-0000-00000000024'.$index;
        $report->setRelation('thinkTank', new ConsortiumThinkTank(['name' => $values['name']]));
        $result = new MePerformanceReportIndicatorResult([
            'indicator_id' => $indicator->id,
            'actual_text' => $values['actual'],
        ]);
        $result->setRelation('indicator', $indicator);
        $result->setRelation('achievements', new EloquentCollection());
        $report->setRelation('indicatorResults', new EloquentCollection([$result]));

        return $report;
    });

    $row = app(MeConsolidatedReportingService::class)->build($reports)->first();

    expect($row['value'])->toBeNull()
        ->and($row['reported_value_count'])->toBe(2)
        ->and($row['qualitative_values']->pluck('organization')->all())->toBe([
            'Policy Centre Alpha',
            'Policy Centre Beta',
        ])
        ->and($row['qualitative_values']->pluck('value')->all())->toBe([
            'Governance charter approved',
            'Secretariat established',
        ]);
});

it('does not consolidate unrelated indicators from a report when disaggregation filters are active', function () {
    $femaleIndicator = new Indicator([
        'indicator_code' => 'PDO 1',
        'name' => 'Female beneficiary indicator',
        'organization_rollup_method' => 'sum',
    ]);
    $femaleIndicator->id = '00000000-0000-0000-0000-000000000051';
    $maleIndicator = new Indicator([
        'indicator_code' => 'PDO 2',
        'name' => 'Male beneficiary indicator',
        'organization_rollup_method' => 'sum',
    ]);
    $maleIndicator->id = '00000000-0000-0000-0000-000000000052';
    $report = new MePerformanceReport([
        'think_tank_member_id' => '00000000-0000-0000-0000-000000000151',
        'status' => 'approved',
    ]);
    $report->setRelation('thinkTank', new ConsortiumThinkTank(['name' => 'Gender Evidence Centre']));

    $resultFor = function (Indicator $indicator, string $gender, int $beneficiaries): MePerformanceReportIndicatorResult {
        $breakdown = new MeIndicatorAchievementDisaggregation([
            'gender' => $gender,
            'beneficiary_count' => $beneficiaries,
        ]);
        $achievement = new MeIndicatorAchievement(['title' => $gender.' achievement']);
        $achievement->setRelation('breakdowns', new EloquentCollection([$breakdown]));
        $result = new MePerformanceReportIndicatorResult([
            'indicator_id' => $indicator->id,
            'actual_value' => $beneficiaries,
        ]);
        $result->setRelation('indicator', $indicator);
        $result->setRelation('achievements', new EloquentCollection([$achievement]));

        return $result;
    };
    $report->setRelation('indicatorResults', new EloquentCollection([
        $resultFor($femaleIndicator, 'female', 7),
        $resultFor($maleIndicator, 'male', 11),
    ]));

    $rows = app(MeConsolidatedReportingService::class)->build(collect([$report]), ['gender' => 'female']);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['indicator']->indicator_code)->toBe('PDO 1')
        ->and($rows->first()['beneficiary_count'])->toBe(7)
        ->and($rows->first()['gender']->get('female'))->toBe(7);
});

it('exposes the unified tracker, repository, matrix, focal and consolidated workflows', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $migration = file_get_contents($root.'/database/migrations/2026_08_02_000001_create_unified_attp_me_tracking.php');
    $tracker = file_get_contents($root.'/resources/views/me/performance-reports/partials/achievement-tracker.blade.php');

    expect($routes)
        ->toContain("Route::prefix('me/matrices')")
        ->toContain("Route::prefix('me/focal-units')")
        ->toContain("Route::prefix('me/consolidated-reports')")
        ->and($migration)
        ->toContain('me_indicator_achievements')
        ->toContain('me_indicator_achievement_disaggregations')
        ->toContain('me_repository_document_versions')
        ->toContain('me_focal_unit_contacts')
        ->and($tracker)
        ->toContain('Unified achievement and beneficiary tracker')
        ->toContain('ATTP priority thematic area(s)')
        ->toContain('Stakeholder category');
});
