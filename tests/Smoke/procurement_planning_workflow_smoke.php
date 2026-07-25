<?php

use App\Models\BudgetCommitment;
use App\Models\GovernanceNode;
use App\Models\ProcurementMethodPlanned;
use App\Models\ProcurementPlan;
use App\Models\ProcurementProgramPlan;
use App\Models\SubActivity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

$admin = User::query()->where('user_type', 'admin')->firstOrFail();
$method = ProcurementMethodPlanned::query()->where('is_active', true)->firstOrFail();

$eligible = BudgetCommitment::query()
    ->where('allocation_level', 'sub_activity')
    ->where('status', BudgetCommitment::STATUS_APPROVED)
    ->whereNotNull('allocation_id')
    ->pluck('allocation_id')
    ->unique()
    ->map(fn ($id) => SubActivity::with('activity.project')->find($id))
    ->filter()
    ->first(function (SubActivity $subActivity) {
        $activityNodeId = $subActivity->activity?->governance_node_id
            ?? $subActivity->activity?->project?->governance_node_id;
        $subActivityNodeId = $subActivity->governance_node_id ?? $activityNodeId;

        return $activityNodeId && (string) $activityNodeId === (string) $subActivityNodeId;
    });

if (! $eligible || ! $eligible->activity) {
    fwrite(STDERR, "No approved committed sub-activity with a consistent portfolio is available.\n");
    exit(1);
}

$governanceNodeId = (string) (
    $eligible->activity->governance_node_id
    ?? $eligible->activity->project?->governance_node_id
);
$otherNode = GovernanceNode::query()
    ->where('id', '<>', $governanceNodeId)
    ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
    ->first();

$session = $app['session.store'];
$session->start();
Auth::login($admin);
$token = bin2hex(random_bytes(20));
$session->put('_token', $token);
$session->save();

$dispatch = function (string $uri, string $method = 'GET', array $data = []) use ($app, $session) {
    $request = Request::create($uri, $method, $data);
    $request->setLaravelSession($session);

    return $app->make(HttpKernel::class)->handle($request);
};

$assertStatus = function ($response, int $expected, string $context): void {
    if ($response->getStatusCode() !== $expected) {
        fwrite(STDERR, "{$context}: expected HTTP {$expected}, got {$response->getStatusCode()}.\n");
        fwrite(STDERR, substr((string) $response->getContent(), 0, 1600) . "\n");
        throw new RuntimeException($context);
    }
};

DB::beginTransaction();
$name = null;
$failure = null;

try {
    $response = $dispatch('/procurement/structure');
    $assertStatus($response, 200, 'Procurement structure page');

    $name = 'Procurement workflow smoke ' . bin2hex(random_bytes(4));
    $response = $dispatch('/procurement/structure', 'POST', [
        '_token' => $token,
        'governance_node_id' => $governanceNodeId,
        'name' => $name,
        'description' => 'Transactional procurement workflow verification.',
        'start_date' => '2026-08-01',
        'end_date' => '2027-07-31',
        'is_active' => '1',
    ]);
    $assertStatus($response, 302, 'Procurement structure creation');

    $programPlan = ProcurementProgramPlan::query()->where('name', $name)->first();
    if (! $programPlan || (string) $programPlan->governance_node_id !== $governanceNodeId) {
        throw new RuntimeException('The procurement plan sheet was not saved with its portfolio.');
    }

    $response = $dispatch("/procurement/plans/create?program_plan_id={$programPlan->id}");
    $assertStatus($response, 200, 'Procurement item create page');
    $createHtml = (string) $response->getContent();
    foreach (['name="is_code_auto_generated"', 'name="program_plan_id"', 'name="currency"', 'name="fiscal_year"'] as $needle) {
        if (! str_contains($createHtml, $needle)) {
            throw new RuntimeException("Procurement item create page is missing {$needle}.");
        }
    }
    if (! preg_match('/value="ET-AUC-\d{6}-CS-CQS"/', $createHtml)) {
        throw new RuntimeException('The procurement item create page did not receive a generated code.');
    }

    $code = 'ET-AUC-' . random_int(100000, 999999) . '-CS-CQS';
    $payload = [
        '_token' => $token,
        'procurement_code' => $code,
        'is_code_auto_generated' => '1',
        'title' => 'Workflow smoke procurement item',
        'activity_id' => $eligible->activity_id,
        'sub_activity_id' => $eligible->id,
        'method_planned_id' => $method->id,
        'program_plan_id' => $programPlan->id,
        'is_launched' => '0',
        'estimated_start_date' => '2026-08-01',
        'estimated_budget' => '125000.50',
        'currency' => 'usd',
        'fiscal_year' => '2026',
    ];

    $response = $dispatch('/procurement/plans', 'POST', $payload);
    $assertStatus($response, 302, 'Procurement item creation');

    $plan = ProcurementPlan::query()->where('procurement_code', $code)->first();
    if (! $plan) {
        throw new RuntimeException('The procurement plan item was not saved.');
    }

    $expectedEndDate = Carbon::parse('2026-08-01')
        ->addDays((int) $method->method_target_days)
        ->format('Y-m-d');
    if (
        (string) $plan->program_plan_id !== (string) $programPlan->id
        || (string) $plan->governance_node_id !== $governanceNodeId
        || $plan->estimated_end_date?->format('Y-m-d') !== $expectedEndDate
        || $plan->currency !== 'USD'
        || ! $plan->is_code_auto_generated
    ) {
        throw new RuntimeException('The procurement plan item relationships or calculated fields are incorrect.');
    }

    $response = $dispatch("/procurement/plans/{$plan->id}/edit");
    $assertStatus($response, 200, 'Procurement item edit page');
    if (! str_contains((string) $response->getContent(), 'name="program_plan_id"')) {
        throw new RuntimeException('The procurement item edit page is missing its required plan sheet selector.');
    }

    $payload['_method'] = 'PUT';
    $payload['title'] = 'Updated workflow smoke procurement item';
    $payload['is_launched'] = '1';
    $response = $dispatch("/procurement/plans/{$plan->id}", 'POST', $payload);
    $assertStatus($response, 302, 'Procurement item update');

    $plan->refresh();
    if ($plan->title !== $payload['title'] || ! $plan->is_launched || ! $plan->launched_at) {
        throw new RuntimeException('The procurement plan item update was not persisted.');
    }

    $response = $dispatch("/procurement/structure/{$programPlan->id}", 'POST', [
        '_token' => $token,
        '_method' => 'PUT',
        'governance_node_id' => $governanceNodeId,
        'name' => $name . ' updated',
        'description' => 'Updated transactional procurement workflow verification.',
        'start_date' => '2026-08-01',
        'end_date' => '2027-07-31',
        'is_active' => '0',
    ]);
    $assertStatus($response, 302, 'Procurement structure update');

    $programPlan->refresh();
    if ($programPlan->name !== $name . ' updated' || $programPlan->is_active) {
        throw new RuntimeException('The procurement plan sheet edit was not persisted.');
    }

    if ($otherNode) {
        $response = $dispatch("/procurement/structure/{$programPlan->id}", 'POST', [
            '_token' => $token,
            '_method' => 'PUT',
            'governance_node_id' => $otherNode->id,
            'name' => $programPlan->name,
            'description' => $programPlan->description,
            'start_date' => '2026-08-01',
            'end_date' => '2027-07-31',
            'is_active' => '0',
        ]);
        $assertStatus($response, 302, 'Populated procurement structure portfolio protection');

        $programPlan->refresh();
        if ((string) $programPlan->governance_node_id !== $governanceNodeId) {
            throw new RuntimeException('A populated procurement plan sheet was moved to an incompatible portfolio.');
        }
    }

} catch (Throwable $e) {
    $failure = $e;
} finally {
    DB::rollBack();
}

if ($failure) {
    fwrite(STDERR, $failure->getMessage() . "\n");
    exit(1);
}

if ($name && ProcurementProgramPlan::query()->where('name', $name)->exists()) {
    fwrite(STDERR, "The procurement workflow test record was not rolled back.\n");
    exit(1);
}

echo "PROCUREMENT_PLANNING_WORKFLOW_OK\n";
