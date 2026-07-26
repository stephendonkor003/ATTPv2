<?php

use App\Http\Controllers\MasterDashboard;
use App\Services\ExecutionDashboardChartBuilder;

it('uses the approved budget as the execution envelope', function () {
    $controller = new MasterDashboard();
    $method = new ReflectionMethod($controller, 'preferDeclaredEnvelope');

    expect($method->invoke($controller, 50_000_000, 49_966_000))
        ->toBe(50_000_000.0);
});

it('falls back to scheduled allocations when no approved envelope is recorded', function () {
    $controller = new MasterDashboard();
    $method = new ReflectionMethod($controller, 'preferDeclaredEnvelope');

    expect($method->invoke($controller, 0, 49_966_000))
        ->toBe(49_966_000.0);
});

it('prevents a PDF with figures from a different dashboard snapshot', function () {
    $controller = new MasterDashboard();
    $method = new ReflectionMethod($controller, 'executionDashboardSnapshotMatches');
    $snapshot = hash('sha256', 'dashboard-figures');

    expect($method->invoke($controller, '', $snapshot))->toBeTrue()
        ->and($method->invoke($controller, $snapshot, $snapshot))->toBeTrue()
        ->and($method->invoke($controller, str_repeat('0', 64), $snapshot))->toBeFalse()
        ->and($method->invoke($controller, 'invalid', $snapshot))->toBeFalse();
});

it('includes the component breakdown in the shared web and PDF snapshot', function () {
    $builder = new ExecutionDashboardChartBuilder();
    $baseBreakdown = [
        [
            'component_id' => 'component-1',
            'sub_component_id' => 'sub-component-a',
            'level' => 'sub_component',
            'label' => 'Sub-component A',
            'allocation' => 1_000_000,
            'commitment' => 400_000,
            'disbursement' => 250_000,
            'remaining' => 600_000,
        ],
    ];

    $first = $builder->dataset(
        executionDashboardTestRows(),
        executionDashboardTestTotals(),
        executionDashboardTestRadarMetrics(),
        $baseBreakdown
    );
    $second = $builder->dataset(
        executionDashboardTestRows(),
        executionDashboardTestTotals(),
        executionDashboardTestRadarMetrics(),
        [
            [
                ...$baseBreakdown[0],
                'allocation' => 1_000_001,
                'remaining' => 600_001,
            ],
        ]
    );

    expect($first['component_breakdown'][0])
        ->toMatchArray([
            'level' => 'sub_component',
            'label' => 'Sub-component A',
            'allocation' => 1_000_000.0,
        ])
        ->and($first['snapshot_hash'])->not->toBe($second['snapshot_hash']);
});

it('builds printable SVG versions of every execution dashboard graph', function () {
    $builder = new ExecutionDashboardChartBuilder();
    $dataset = $builder->dataset(
        executionDashboardTestRows(),
        executionDashboardTestTotals(),
        executionDashboardTestRadarMetrics()
    );
    $charts = $builder->buildFromDataset($dataset);

    expect($charts)->toHaveKeys([
        'global_trend',
        'execution_mix',
        'rate_movement',
        'cumulative_momentum',
        'financial_profile',
        'variance_control',
        'quality_radar',
        'exposure_concentration',
    ]);

    foreach ($charts as $chart) {
        expect($chart)->toStartWith('data:image/svg+xml;base64,');
        $svg = base64_decode(substr($chart, strlen('data:image/svg+xml;base64,')));
        expect($svg)->toContain('<svg')->toContain('</svg>');
    }

    $executionMixSvg = base64_decode(substr(
        $charts['execution_mix'],
        strlen('data:image/svg+xml;base64,')
    ));

    expect($dataset['cumulative_allocation'])->toBe([10_000_000.0, 25_000_000.0])
        ->and($dataset['cumulative_commitment'])->toBe([4_000_000.0, 12_000_000.0])
        ->and($dataset['cumulative_disbursement'])->toBe([2_500_000.0, 7_500_000.0])
        ->and($dataset['cumulative_unpaid_commitments'])->toBe([1_500_000.0, 4_500_000.0])
        ->and($dataset['cumulative_global_remaining'])->toBe([46_000_000.0, 38_000_000.0])
        ->and($dataset['mix_trend'][2]['values'])->toBe([46_000_000.0, 38_000_000.0])
        ->and($executionMixSvg)->toContain('data-chart="execution-mix-line"')
        ->toContain('<polyline')
        ->toContain('Remaining Global Commitments')
        ->and($dataset['snapshot_hash'])->toHaveLength(64);
});

it('places an enlarged execution mix below the primary line graph on web and PDF', function () {
    $webTemplate = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/finance/execution/dashboard.blade.php'
    );
    $pdfTemplate = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/finance/execution/dashboard_pdf.blade.php'
    );
    $mixChartStart = strpos($webTemplate, "makeChart('executionMixChart'");
    $mixChartEnd = strpos($webTemplate, "makeChart('executionRateChart'", $mixChartStart);
    $mixChartScript = substr($webTemplate, $mixChartStart, $mixChartEnd - $mixChartStart);

    expect($webTemplate)
        ->toContain('execution-panel span-12 execution-panel--primary')
        ->toContain('execution-panel span-12 execution-panel--mix')
        ->toContain('.execution-panel--mix .execution-chart')
        ->and(strpos($webTemplate, '<canvas id="executionLineChart"></canvas>'))
        ->toBeLessThan(strpos($webTemplate, '<canvas id="executionMixChart"></canvas>'));

    expect($pdfTemplate)
        ->toContain('.chart-image--large')
        ->toContain('class="chart-image chart-image--large"')
        ->toContain('chart-card chart-card--mix')
        ->and(strpos($pdfTemplate, "\$charts['global_trend']"))
        ->toBeLessThan(strpos($pdfTemplate, "\$charts['execution_mix']"));

    expect($mixChartScript)
        ->toContain("type: 'line'")
        ->toContain('datasets: mixTrend.map')
        ->not->toContain("type: 'doughnut'");
});

it('shows selected component global performance before its sub-component breakdown', function () {
    $webTemplate = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/finance/execution/dashboard.blade.php'
    );
    $pdfTemplate = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/finance/execution/dashboard_pdf.blade.php'
    );

    foreach ([$webTemplate, $pdfTemplate] as $template) {
        expect($template)
            ->toContain("\$componentBreakdownLevel ?? 'component'")
            ->toContain('Selected Component - Global Execution Performance')
            ->toContain('Sub-component Execution Performance Breakdown')
            ->toContain("\$breakdownColumnLabel")
            ->toContain('Selected component and all sub-components');
    }

    expect(strpos($webTemplate, '<h5 class="execution-panel-title">{{ $globalBreakdownTitle }}</h5>'))
        ->toBeLessThan(strpos($webTemplate, '<h5 class="execution-panel-title">{{ $breakdownTitle }}</h5>'))
        ->and(strpos($pdfTemplate, '<div class="section-title">{{ $globalBreakdownTitle }}</div>'))
        ->toBeLessThan(strpos($pdfTemplate, '<div class="section-title">{{ $breakdownTitle }}</div>'));
});

it('uses a native downloader compatible PDF request with resilient status tracking', function () {
    $template = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/finance/execution/dashboard.blade.php'
    );

    expect($template)
        ->toContain('id="executionPdfModal"')
        ->toContain('id="executionPdfDownloadFrame"')
        ->toContain('aria-live="polite"')
        ->toContain('const pdfReadingSteps = [')
        ->toContain("Accept: 'application/json'")
        ->toContain("downloadUrl.searchParams.set('download_token', downloadToken);")
        ->toContain('pdfDownloadFrame.src = downloadUrl.toString();')
        ->toContain('pollPdfStatus(statusUrl, downloadToken)')
        ->toContain('The download request is still active. Reconnecting without cancelling it')
        ->not->toContain('await response.blob()')
        ->not->toContain('URL.createObjectURL')
        ->toContain('hidePdfModal();');

    expect(strpos($template, 'pdfDownloadFrame.src = downloadUrl.toString();'))
        ->toBeLessThan(strrpos($template, '() => pollPdfStatus(statusUrl, downloadToken)'));
});

function executionDashboardTestRows()
{
    return collect([
        [
            'year' => 2025,
            'allocation' => 10_000_000,
            'commitment' => 4_000_000,
            'disbursement' => 2_500_000,
        ],
        [
            'year' => 2026,
            'allocation' => 15_000_000,
            'commitment' => 8_000_000,
            'disbursement' => 5_000_000,
        ],
    ]);
}

function executionDashboardTestTotals(): array
{
    return [
        'allocation' => 50_000_000,
        'commitment' => 12_000_000,
        'disbursement' => 7_500_000,
        'remaining' => 38_000_000,
    ];
}

function executionDashboardTestRadarMetrics(): array
{
    return [
        'budget_utilization' => 24,
        'timeliness' => 40,
        'consistency' => 88,
        'coverage' => 40,
        'risk_exposure' => 100,
    ];
}
