<?php

use App\Models\EvaluationCriteria;
use App\Models\EvaluationSection;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

function hierarchySection(string $id, int $depth, string $outline, array $criteria = []): EvaluationSection
{
    $section = new EvaluationSection([
        'name' => "Section {$id}",
        'show_subtotal' => true,
        'sort_order' => 1,
    ]);
    $section->id = $id;
    $section->setHierarchyContext($depth, $outline);
    $section->setRelation('criteria', new EloquentCollection(array_map(
        fn (float $maxScore): EvaluationCriteria => new EvaluationCriteria(['max_score' => $maxScore]),
        $criteria
    )));
    $section->setRelation('childrenRecursive', new EloquentCollection);

    return $section;
}

it('models an ordered four-tier hierarchy and recursive subtotals without double-counting nodes', function () {
    $root = hierarchySection('root', 1, '1', [10]);
    $child = hierarchySection('child', 2, '1.1', [5]);
    $grandchild = hierarchySection('grandchild', 3, '1.1.1', [3]);
    $greatGrandchild = hierarchySection('great-grandchild', 4, '1.1.1.1', [2]);

    $root->setRelation('childrenRecursive', new EloquentCollection([$child]));
    $child->setRelation('childrenRecursive', new EloquentCollection([$grandchild]));
    $grandchild->setRelation('childrenRecursive', new EloquentCollection([$greatGrandchild]));

    expect(EvaluationSection::MAX_DEPTH)->toBe(4)
        ->and($root->depth)->toBe(1)
        ->and($root->level_label)->toBe('Section')
        ->and($greatGrandchild->level_label)->toBe('Sub-Sub-Sub Section')
        ->and($greatGrandchild->outline_number)->toBe('1.1.1.1')
        ->and($root->descendants()->pluck('id')->all())->toBe([
            'child',
            'grandchild',
            'great-grandchild',
        ])
        ->and($root->subtreeIds()->all())->toBe([
            'root',
            'child',
            'grandchild',
            'great-grandchild',
        ])
        ->and($root->subtreeHeight())->toBe(4)
        ->and($root->subtotalMaxScore())->toBe(20.0)
        ->and($root->containsDescendant($greatGrandchild))->toBeTrue()
        ->and($root->isLeaf())->toBeFalse()
        ->and($greatGrandchild->isLeaf())->toBeTrue();
});

it('casts the optional subtotal preference for safe form handling', function () {
    $enabled = new EvaluationSection(['show_subtotal' => '1', 'sort_order' => '2']);
    $disabled = new EvaluationSection(['show_subtotal' => '0']);

    expect($enabled->show_subtotal)->toBeTrue()
        ->and($disabled->show_subtotal)->toBeFalse()
        ->and($enabled->sort_order)->toBe(2);
});

it('enforces the hierarchy contract in migration, model, and controller sources', function () {
    $root = dirname(__DIR__, 2);
    $migration = file_get_contents(
        $root.'/database/migrations/2026_08_15_000001_add_hierarchy_to_evaluation_sections.php'
    );
    $model = file_get_contents($root.'/app/Models/EvaluationSection.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationSectionController.php');

    expect($migration)
        ->toContain("foreignUuid('parent_section_id')")
        ->toContain("boolean('show_subtotal')->default(true)")
        ->toContain("unsignedInteger('sort_order')->default(0)")
        ->and($model)
        ->toContain('MAX_DEPTH = 4')
        ->toContain('function childrenRecursive(')
        ->toContain('function subtreeCriteria(')
        ->toContain('EvaluationCriteriaScore::query()')
        ->toContain('EvaluationSectionScore::query()')
        ->and($controller)
        ->toContain('assertValidMove(')
        ->toContain('containsDescendant(')
        ->toContain('normalizeSiblingOrder(')
        ->toContain('DB::transaction(');
});
