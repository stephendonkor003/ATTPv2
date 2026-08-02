<?php

it('renders the call for proposals through the shared public experience', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/applicants/create.blade.php');
    $layout = file_get_contents($root.'/resources/views/layouts/public.blade.php');

    expect($view)
        ->toContain("@extends('layouts.public')")
        ->toContain('Strengthening African policy research and regional collaboration')
        ->toContain('cfp-status-badge')
        ->toContain('Submissions are closed')
        ->toContain('data-stage="1"')
        ->toContain('data-stage="2"')
        ->toContain('@csrf')
        ->toContain("route('applicants.store')")
        ->toContain('name="focus_areas[]"')
        ->toContain('name="covered_countries[]"')
        ->not->toContain('code.jquery.com')
        ->not->toContain('select2')
        ->not->toContain('cdn.jsdelivr.net')
        ->not->toContain('â€™')
        ->not->toContain('ðŸ');

    expect($layout)
        ->toContain("request()->routeIs('public.grievances.*') ? 'active' : ''");
});

it('does not load the think tank directory while submissions are closed', function () {
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/ApplicantController.php');

    expect($controller)
        ->toContain('$submissionsOpen = self::CALL_FOR_PROPOSAL_SUBMISSIONS_OPEN;')
        ->toContain('$thinkTanks = $submissionsOpen')
        ->toContain(': collect();');
});
