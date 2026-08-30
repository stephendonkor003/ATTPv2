<?php

use App\Models\Evaluation;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Support\EvaluationSectionHierarchy;
use Illuminate\Support\Collection;

it('presents the domain hierarchy with zero-based visual depth and one-based tier labels', function () {
    $root = (new EvaluationSection)->forceFill(['id' => 'root']);
    $root->setHierarchyContext(1, '1');

    $subSection = (new EvaluationSection)->forceFill(['id' => 'sub']);
    $subSection->setHierarchyContext(2, '1.1');

    $deepest = (new EvaluationSection)->forceFill(['id' => 'deepest']);
    $deepest->setHierarchyContext(4, '1.1.1.1');

    $evaluation = new class extends Evaluation
    {
        public Collection $outline;

        public function flattenedSections(): Collection
        {
            return $this->outline;
        }
    };
    $evaluation->outline = collect([$root, $subSection, $deepest]);

    $outline = EvaluationSectionHierarchy::flattened($evaluation);

    expect($outline->pluck('depth')->all())->toBe([0, 1, 3])
        ->and($outline->pluck('number')->all())->toBe(['1', '1.1', '1.1.1.1'])
        ->and($outline->pluck('label')->all())->toBe([
            'Section',
            'Sub-Section',
            'Sub-Sub-Sub Section',
        ]);
});

it('assigns a stable zero-based root identity to every flattened branch', function () {
    $rootA = (new EvaluationSection)->forceFill(['id' => 'root-a']);
    $rootA->setHierarchyContext(1, '1');

    $childA = (new EvaluationSection)->forceFill(['id' => 'child-a']);
    $childA->setHierarchyContext(2, '1.1');

    $rootB = (new EvaluationSection)->forceFill(['id' => 'root-b']);
    $rootB->setHierarchyContext(1, '2');

    $childB = (new EvaluationSection)->forceFill(['id' => 'child-b']);
    $childB->setHierarchyContext(3, '2.1.1');

    $evaluation = new class extends Evaluation
    {
        public Collection $outline;

        public function flattenedSections(): Collection
        {
            return $this->outline;
        }
    };
    $evaluation->outline = collect([$rootA, $childA, $rootB, $childB]);

    $outline = EvaluationSectionHierarchy::flattened($evaluation);

    expect($outline->pluck('root_index')->all())->toBe([0, 0, 1, 1])
        ->and($outline->pluck('root_section_id')->all())->toBe([
            'root-a',
            'root-a',
            'root-b',
            'root-b',
        ]);
});

it('calculates numeric section subtotals across subtree criteria without affecting overall semantics', function () {
    $criterionA = (new EvaluationCriteria)->forceFill(['id' => 'criterion-a']);
    $criterionB = (new EvaluationCriteria)->forceFill(['id' => 'criterion-b']);

    $section = new class extends EvaluationSection
    {
        public Collection $subtree;

        public function subtreeCriteria(): Collection
        {
            return $this->subtree;
        }
    };
    $section->subtree = collect([$criterionA, $criterionB]);

    $submission = new EvaluationSubmission;
    $submission->setRelation('criteriaScores', collect([
        (new EvaluationCriteriaScore)->forceFill([
            'evaluation_criteria_id' => 'criterion-a',
            'score' => 12.25,
        ]),
        (new EvaluationCriteriaScore)->forceFill([
            'evaluation_criteria_id' => 'criterion-b',
            'score' => 7.5,
        ]),
        (new EvaluationCriteriaScore)->forceFill([
            'evaluation_criteria_id' => 'outside-subtree',
            'score' => 99,
        ]),
    ]));

    expect(EvaluationSectionHierarchy::numericSubtotal($submission, $section))
        ->toBe(19.75);
});

it('reports categorical subtree distributions and never treats unanswered values as decision zero', function () {
    $criterionA = (new EvaluationCriteria)->forceFill(['id' => 'criterion-a']);
    $criterionB = (new EvaluationCriteria)->forceFill(['id' => 'criterion-b']);
    $criterionC = (new EvaluationCriteria)->forceFill(['id' => 'criterion-c']);

    $section = new class extends EvaluationSection
    {
        public Collection $subtree;

        public function subtreeCriteria(): Collection
        {
            return $this->subtree;
        }
    };
    $section->subtree = collect([$criterionA, $criterionB, $criterionC]);

    $evaluation = new Evaluation(['type' => Evaluation::TYPE_EOI]);
    $submission = new EvaluationSubmission;
    $submission->setRelation('evaluation', $evaluation);
    $submission->setRelation('criteriaScores', collect([
        (new EvaluationCriteriaScore)->forceFill([
            'evaluation_criteria_id' => 'criterion-a',
            'decision' => 2,
        ]),
        (new EvaluationCriteriaScore)->forceFill([
            'evaluation_criteria_id' => 'criterion-b',
            'decision' => 0,
        ]),
        (new EvaluationCriteriaScore)->forceFill([
            'evaluation_criteria_id' => 'criterion-c',
            'decision' => null,
        ]),
    ]));

    expect(EvaluationSectionHierarchy::decisionDistribution($submission, $section))
        ->toBe([
            'Qualified' => 1,
            'Average Qualified' => 0,
            'Not Qualified' => 1,
        ]);
});

it('wires hierarchy subtotals into evaluator, read-only, panel PDF and report presentations', function () {
    $root = dirname(__DIR__, 2);
    $views = [
        'resources/views/evaluations/submit.blade.php',
        'resources/views/evaluations/view.blade.php',
        'resources/views/evaluations/partials/template-preview.blade.php',
        'resources/views/evaluations/pdf/template.blade.php',
        'resources/views/evaluations/panel/pdf/single.blade.php',
        'resources/views/evaluations/panel/pdf/bulk.blade.php',
        'resources/views/reports/evaluations/submission.blade.php',
        'resources/views/reports/evaluations/pdf/submission.blade.php',
    ];

    foreach ($views as $view) {
        expect(file_get_contents($root.'/'.$view))
            ->toContain('EvaluationSectionHierarchy');
    }

    $submitView = file_get_contents($root.'/resources/views/evaluations/submit.blade.php');
    $sectionScoreModel = file_get_contents($root.'/app/Models/EvaluationSectionScore.php');

    $viewTemplate = file_get_contents($root.'/resources/views/evaluations/view.blade.php');
    $hierarchyTheme = file_get_contents($root.'/resources/views/evaluations/partials/hierarchy-theme.blade.php');

    expect($submitView)
        ->toContain('data-subtree-sections')
        ->toContain('data-question-total')
        ->toContain('data-section-completion')
        ->toContain('data-decision-value')
        ->toContain('data-overall-decision-value')
        ->toContain('EOI categories are summarised as counts only; no numeric rank is calculated.')
        ->toContain('@if ($section->show_subtotal && $isNumeric)')
        ->toContain('@elseif ($section->show_subtotal && $isCategorical)')
        ->toContain('@if ($section->criteria->isNotEmpty())')
        ->toContain('inputs.forEach')
        ->not->toContain('overall += sectionTotal')
        ->and($viewTemplate)
        ->toContain('branchQuestionCount')
        ->toContain('branchCompletion')
        ->toContain('EOI categories are shown as counts only; no numeric rank is calculated.')
        ->toContain('@if ($section->show_subtotal && $isNumeric)')
        ->toContain('@elseif ($section->show_subtotal && $isCategorical)')
        ->toContain('hierarchy-tone-{{ $node[\'root_index\'] % 8 }}')
        ->and($submitView)
        ->toContain('hierarchy-tone-{{ $node[\'root_index\'] % 8 }}')
        ->and($hierarchyTheme)
        ->toContain('.hierarchy-tone-0')
        ->toContain('.hierarchy-tone-7')
        ->toContain('--section-color')
        ->toContain('--section-soft')
        ->toContain('--section-deep')
        ->and($sectionScoreModel)
        ->toContain('subtreeCriteria()')
        ->toContain('ancestors()');

    foreach ([
        'resources/views/evaluations/view.blade.php',
        'resources/views/evaluations/panel/pdf/single.blade.php',
        'resources/views/evaluations/panel/pdf/bulk.blade.php',
        'resources/views/reports/evaluations/submission.blade.php',
        'resources/views/reports/evaluations/pdf/submission.blade.php',
    ] as $readOnlyView) {
        expect(file_get_contents($root.'/'.$readOnlyView))
            ->toContain('@if ($section->criteria->isNotEmpty())');
    }
});

it('carries numeric per-question evaluator responses into every detailed presentation', function () {
    $root = dirname(__DIR__, 2);
    $views = [
        'resources/views/evaluations/view.blade.php' => '$score?->comment',
        'resources/views/evaluations/panel/pdf/single.blade.php' => '$cs->comment',
        'resources/views/evaluations/panel/pdf/bulk.blade.php' => '$cs->comment',
        'resources/views/reports/evaluations/submission.blade.php' => '$criteriaScore->comment',
        'resources/views/reports/evaluations/pdf/submission.blade.php' => '$criteriaScore->comment',
    ];

    foreach ($views as $view => $commentExpression) {
        expect(file_get_contents($root.'/'.$view))
            ->toContain('Evaluator response')
            ->toContain($commentExpression);
    }
});
