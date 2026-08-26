<?php

use App\Models\Permission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

function evaluatorCapabilityUser(bool $hasAssignments, ?string $userType = null): User
{
    $user = new class extends User
    {
        public bool $assignmentCapability = false;

        public function hasEvaluationAssignments(): bool
        {
            return $this->assignmentCapability;
        }
    };

    $user->assignmentCapability = $hasAssignments;
    $user->forceFill(['user_type' => $userType]);
    $user->setRelation('role', null);
    $user->setRelation('permissions', new EloquentCollection);

    return $user;
}

it('defines evaluation work as a user-owned has-many relationship', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/User.php');

    expect($source)
        ->toContain('public function evaluationAssignments(): HasMany')
        ->toContain("return \$this->hasMany(EvaluationAssignment::class, 'user_id');");
});

it('adds assignment ownership as evaluator capability without removing configured grants', function () {
    $assigned = evaluatorCapabilityUser(true);
    $unassigned = evaluatorCapabilityUser(false);

    $storedPermission = (new Permission)->forceFill(['name' => 'evaluations.evaluate']);
    $unassigned->setRelation('permissions', new EloquentCollection([$storedPermission]));

    expect($assigned->hasPermission('evaluations.evaluate'))->toBeTrue()
        ->and($unassigned->hasPermission('evaluations.evaluate'))->toBeTrue()
        ->and(evaluatorCapabilityUser(false)->hasPermission('evaluations.evaluate'))->toBeFalse();
});

it('keeps administrator evaluator access independent of assignments', function () {
    $administrator = evaluatorCapabilityUser(false, 'admin');

    expect($administrator->hasPermission('evaluations.evaluate'))->toBeTrue();
});

it('scopes assignment-derived capability away from soft-deleted procurements', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Models/User.php');

    expect($source)
        ->toContain('function hasEvaluationAssignments(): bool')
        ->toContain("->whereHas('procurement'")
        ->toContain("->whereNull('procurements.deleted_at')")
        ->toContain("if (\$permission === 'evaluations.evaluate' && \$this->hasEvaluationAssignments())")
        ->toContain('return true;');
});
