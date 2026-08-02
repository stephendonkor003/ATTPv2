<?php

it('reserves grievance classification for the secured officer workflow', function () {
    $root = dirname(__DIR__, 2);
    $submissionView = file_get_contents($root.'/resources/views/grm/submissions/_form.blade.php');
    $caseView = file_get_contents($root.'/resources/views/grm/logs/show.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/GrmController.php');

    expect($submissionView)
        ->not->toContain('name="level_id"')
        ->not->toContain('grmLevelSelect')
        ->toContain('until a grievance officer reviews and classifies the case');

    expect($controller)
        ->toContain("'level_id' => ['prohibited']")
        ->toContain("'level_id' => ['required', 'uuid', 'exists:grm_levels,id']")
        ->toContain("'classified'");

    expect($caseView)
        ->toContain('Grievance Level / Category')
        ->toContain('name="level_id"')
        ->toContain('Assigned by the grievance officer after reviewing the submission.');
});
