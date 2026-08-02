<?php

use App\Services\FundingToThinkTanksAllocationReconciler;

function fundingAllocationSnapshot(array $overrides = []): array
{
    return array_replace_recursive([
        'project_by_year' => [2024 => 0, 2025 => 150_000, 2026 => 12_198_500, 2027 => 11_738_500, 2028 => 11_413_000],
        'project_activity_by_year' => [2024 => 0, 2025 => 150_000, 2026 => 12_198_500, 2027 => 11_738_500, 2028 => 5_970_000],
        'project_remaining_by_year' => [2024 => 0, 2025 => 0, 2026 => 0, 2027 => 0, 2028 => 5_443_000],
        'project_2028' => 11_413_000,
        'project_activity_2028' => 5_970_000,
        'project_2028_remaining' => 5_443_000,
        'parent_by_year' => [2024 => 0, 2025 => 0, 2026 => 10_258_500, 2027 => 10_248_500, 2028 => 5_000_000],
        'children_by_year' => [2024 => 0, 2025 => 24_524_800, 2026 => 580_000, 2027 => 570_000, 2028 => 130_000],
        'target_by_year' => [2024 => 0, 2025 => 24_500_000, 2026 => 0, 2027 => 0, 2028 => 0],
        'sibling_by_year' => [2024 => 0, 2025 => 24_800, 2026 => 0, 2027 => 0, 2028 => 0],
        'other_children_by_year' => [2024 => 0, 2025 => 0, 2026 => 580_000, 2027 => 570_000, 2028 => 130_000],
        'parent_total' => 25_507_000,
        'children_total' => 25_804_800,
        'target_total' => 24_500_000,
        'sibling_total' => 24_800,
    ], $overrides);
}

it('recognizes the audited legacy child state as ready', function () {
    $service = new FundingToThinkTanksAllocationReconciler;

    expect($service->classifySnapshot(fundingAllocationSnapshot()))->toBe('ready')
        ->and($service->classifySnapshot(fundingAllocationSnapshot([
            'target_by_year' => [2025 => 24_499_999],
        ])))->toBe('blocked');
});

it('accepts a compatible larger parent envelope from the server', function () {
    $service = new FundingToThinkTanksAllocationReconciler;
    $serverSnapshot = fundingAllocationSnapshot([
        'project_activity_by_year' => [2028 => 7_250_000],
        'project_remaining_by_year' => [2028 => 4_163_000],
        'project_activity_2028' => 7_250_000,
        'project_2028_remaining' => 4_163_000,
        'parent_by_year' => [2028 => 6_280_000],
        'parent_total' => 26_787_000,
    ]);

    expect($service->classifySnapshot($serverSnapshot))->toBe('ready');
});

it('blocks the repair when the resulting activity would exceed a project year', function () {
    $service = new FundingToThinkTanksAllocationReconciler;
    $unsafeSnapshot = fundingAllocationSnapshot([
        'project_by_year' => [2028 => 6_100_000],
        'project_2028' => 6_100_000,
    ]);

    expect($service->classifySnapshot($unsafeSnapshot))->toBe('blocked');
});

it('recognizes the fully balanced schedule as complete and idempotent', function () {
    $service = new FundingToThinkTanksAllocationReconciler;
    $snapshot = fundingAllocationSnapshot([
        'project_activity_2028' => 6_267_800,
        'project_2028_remaining' => 5_145_200,
        'project_activity_by_year' => [2028 => 6_267_800],
        'project_remaining_by_year' => [2028 => 5_145_200],
        'parent_by_year' => [2024 => 0, 2025 => 0, 2026 => 10_258_500, 2027 => 10_248_500, 2028 => 5_297_800],
        'children_by_year' => [2024 => 0, 2025 => 0, 2026 => 10_258_500, 2027 => 10_248_500, 2028 => 5_297_800],
        'target_by_year' => [2024 => 0, 2025 => 0, 2026 => 9_678_500, 2027 => 9_678_500, 2028 => 5_143_000],
        'sibling_by_year' => [2024 => 0, 2025 => 0, 2026 => 0, 2027 => 0, 2028 => 24_800],
        'parent_total' => 25_804_800,
        'children_total' => 25_804_800,
    ]);

    expect($service->classifySnapshot($snapshot))->toBe('complete');
});

it('renders a protected one-click reconciliation control', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $view = file_get_contents($root.'/resources/views/budget/subactivities/edit.blade.php');

    expect($routes)
        ->toContain('->middleware(\'permission:subactivities.edit\')')
        ->toContain('->name(\'subactivities.reconcile-funding-allocation\')');
    expect($view)
        ->toContain('@csrf')
        ->toContain('Automatically Spread USD 24,500,000');
});
