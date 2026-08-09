<?php

it('provides a guided responsive think tank indicator reporting dashboard', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/think-tank/me-dashboard.blade.php');

    expect($view)
        ->toContain('Submit indicator data with confidence')
        ->toContain('How to submit indicator data')
        ->toContain('Open the assigned indicator')
        ->toContain('Gather data and evidence')
        ->toContain('Complete and save the form')
        ->toContain('Review and submit')
        ->toContain('Start indicator submission')
        ->toContain('Before you submit')
        ->toContain('Complete disaggregation')
        ->toContain('Attach verifiable evidence')
        ->toContain('Avoid double counting')
        ->toContain('What each status means')
        ->toContain('Target vs approved actual')
        ->toContain("route('think-tank.me-data.index')")
        ->toContain("route('think-tank.reporting-notifications.index')")
        ->toContain('overflow-wrap: anywhere;')
        ->toContain('table-layout: fixed;')
        ->toContain('content: attr(data-label);')
        ->not->toContain('Â')
        ->not->toContain('â€”');
});
