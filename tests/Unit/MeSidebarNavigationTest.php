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
