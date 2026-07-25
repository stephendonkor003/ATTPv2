<?php

use App\Support\ProcurementReviewAssignees;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

DB::beginTransaction();

try {
    $suffix = strtolower(Str::random(12));
    $userIds = [
        'internal' => (string) Str::uuid(),
        'vendor' => (string) Str::uuid(),
        'think_tank' => (string) Str::uuid(),
    ];

    foreach ($userIds as $type => $id) {
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Assignment eligibility '.$type,
            'email' => "assignment-eligibility-{$type}-{$suffix}@example.test",
            'user_type' => $type === 'internal' ? null : $type,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $eligibleIds = ProcurementReviewAssignees::query()
        ->whereIn('id', array_values($userIds))
        ->pluck('id')
        ->map(fn ($id) => (string) $id)
        ->all();

    if ($eligibleIds !== [$userIds['internal']]) {
        throw new RuntimeException('The assignment dropdown query did not exclude vendor and think tank accounts.');
    }

    $assignmentRules = [
        'user_id' => [
            'required',
            'uuid',
            ProcurementReviewAssignees::existsRule(),
        ],
    ];

    if (Validator::make(['user_id' => $userIds['internal']], $assignmentRules)->fails()) {
        throw new RuntimeException('An internal user was incorrectly rejected as a procurement reviewer.');
    }

    foreach (['vendor', 'think_tank'] as $ineligibleType) {
        if (Validator::make(['user_id' => $userIds[$ineligibleType]], $assignmentRules)->passes()) {
            throw new RuntimeException("A {$ineligibleType} account bypassed assignment validation.");
        }
    }

    echo "PROCUREMENT_REVIEW_ASSIGNMENT_ELIGIBILITY_OK\n";
} finally {
    DB::rollBack();
}
