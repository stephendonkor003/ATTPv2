<?php

it('replaces the mission reports tab with bi-annual site visits', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/partials/sidebar.blade.php');

    expect($sidebar)
        ->not->toContain("route('budget.me.mission-reports.index')")
        ->not->toContain('Mission Reports')
        ->toContain("route('biannual-site-visits.index')")
        ->toContain('Bi-Annual Site Visits')
        ->toContain("'biannual_site_visits.view'");

    expect(substr_count($sidebar, "route('biannual-site-visits.index')"))->toBe(1);
});

it('exposes the filterable Think Tank M&E report under Monitoring & Evaluation', function () {
    $root = dirname(__DIR__, 2);
    $sidebar = file_get_contents($root.'/resources/views/layouts/partials/sidebar.blade.php');
    $report = file_get_contents($root.'/resources/views/me/consolidated-reports/index.blade.php');

    expect($sidebar)
        ->toContain("route('budget.me.consolidated-reports.index')")
        ->toContain("request()->routeIs('budget.me.consolidated-reports.*')")
        ->toContain('Think Tank M&amp;E Reports')
        ->and($report)
        ->toContain('<h1>Think Tank M&amp;E Reports</h1>')
        ->toContain('<details class="cr-panel cr-filter" open>')
        ->toContain('Find a Think Tank report')
        ->toContain('name="think_tank_id"')
        ->toContain('>View reports</button>');
});

it('places the indicator report between the Consolidations Engine and data entry', function () {
    $sidebar = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/partials/sidebar.blade.php');
    $indicatorPosition = strpos($sidebar, "route('budget.me.indicators.index')");
    $enginePosition = strpos($sidebar, "route('budget.me.consolidation-engine.index')");
    $reportPosition = strpos($sidebar, "route('budget.me.indicator-reports.index')");
    $dataEntryPosition = strpos($sidebar, "route('budget.me.rebuild.data-entry')");

    expect($sidebar)
        ->toContain("route('budget.me.consolidation-engine.index')")
        ->toContain("request()->routeIs('budget.me.consolidation-engine.*')")
        ->toContain('Consolidations Engine')
        ->toContain("route('budget.me.indicator-reports.index')")
        ->toContain("request()->routeIs('budget.me.indicator-reports.*')")
        ->toContain('Indicator Report')
        ->and(substr_count($sidebar, "route('budget.me.consolidation-engine.index')"))->toBe(1)
        ->and(substr_count($sidebar, "route('budget.me.indicator-reports.index')"))->toBe(1)
        ->and($indicatorPosition)->not->toBeFalse()
        ->and($enginePosition)->not->toBeFalse()
        ->and($reportPosition)->not->toBeFalse()
        ->and($dataEntryPosition)->not->toBeFalse()
        ->and($indicatorPosition)->toBeLessThan($enginePosition)
        ->and($enginePosition)->toBeLessThan($reportPosition)
        ->and($reportPosition)->toBeLessThan($dataEntryPosition);
});
