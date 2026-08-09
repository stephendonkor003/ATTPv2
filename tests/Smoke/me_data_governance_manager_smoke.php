<?php

use App\Http\Controllers\MeDataGovernanceController;
use App\Models\MeDataGovernanceAction;
use App\Models\MeDataGovernanceControl;
use App\Models\Sector;
use App\Models\SystemAuditLog;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$admin = User::query()->whereHas('role', fn ($query) => $query->where('name', 'System Admin'))->firstOrFail();
$portfolio = Sector::query()->orderBy('name')->firstOrFail();
$app['auth']->guard()->setUser($admin);
$controller = $app->make(MeDataGovernanceController::class);
$suffix = Str::upper(Str::random(9));
$code = 'DG-SMOKE-'.$suffix;

$requestFor = function (string $method, string $uri, array $input = []) use ($app, $admin): Request {
    $request = Request::create($uri, $method, $input);
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);

    return $request;
};

$controlInput = static function (string $version) use ($admin, $portfolio, $code, $suffix): array {
    return [
        'control_code' => $code,
        'title' => 'Governance smoke control '.$suffix,
        'governance_domain' => 'data_quality',
        'instrument_type' => 'control',
        'version' => $version,
        'scope_type' => 'portfolio',
        'portfolio_id' => $portfolio->id,
        'think_tank_member_id' => null,
        'owner_user_id' => $admin->id,
        'steward_user_id' => $admin->id,
        'data_classification' => 'confidential',
        'risk_rating' => 'high',
        'implementation_status' => 'in_progress',
        'review_frequency' => 'annual',
        'effective_date' => '2026-08-01',
        'next_review_date' => '2027-08-01',
        'description' => 'Transactional data-governance control-centre verification.',
        'requirements' => 'The owner shall review the control, retain evidence, resolve findings, and record annual approval.',
        'evidence_notes' => 'Smoke test evidence remains transactional.',
        'evidence_repository_item_id' => null,
    ];
};

$actionInput = static function (string $title) use ($admin): array {
    return [
        'action_type' => 'remediation',
        'title' => $title,
        'description' => 'Complete the control test and retain its signed evidence.',
        'priority' => 'high',
        'status' => 'open',
        'owner_user_id' => $admin->id,
        'due_date' => '2026-08-08',
    ];
};

DB::beginTransaction();

try {
    $controller->store($requestFor('POST', '/budget/me/rebuild/data-governance-framework/controls', $controlInput('1.0')));
    $first = MeDataGovernanceControl::query()->where('control_code', $code)->where('version', '1.0')->firstOrFail();
    if ($first->status !== 'draft' || $first->scope_type !== 'portfolio') {
        throw new RuntimeException('The controlled governance draft was not created correctly.');
    }

    $controller->storeAction($requestFor(
        'POST',
        "/budget/me/rebuild/data-governance-framework/controls/{$first->id}/actions",
        $actionInput('Initial remediation '.$suffix)
    ), $first);
    $initialAction = MeDataGovernanceAction::query()->where('control_id', $first->id)->firstOrFail();

    $view = $controller->index($requestFor(
        'GET',
        '/budget/me/rebuild/data-governance-framework?risk=high&q='.$suffix,
        ['risk' => 'high', 'q' => $suffix]
    ));
    $html = $view->with('errors', new ViewErrorBag)->render();
    if ($view->getData()['controls']->total() !== 1
        || ! str_contains($html, $code)
        || ! str_contains($html, 'dg-domain-chart')
        || ! str_contains($html, 'Remediation and review action queue')) {
        throw new RuntimeException('The filtered governance control centre did not render correctly.');
    }

    $controller->submitReview($requestFor('POST', "/budget/me/rebuild/data-governance-framework/controls/{$first->id}/submit-review"), $first);
    $first->refresh();
    $controller->approve($requestFor('POST', "/budget/me/rebuild/data-governance-framework/controls/{$first->id}/approve"), $first);
    $first->refresh();
    if ($first->status !== 'approved' || ! $first->approved_at) {
        throw new RuntimeException('Governance approval did not create an authoritative version.');
    }

    $immutable = false;
    try {
        $changed = $controlInput('1.0');
        $changed['title'] = 'Improper approved edit';
        $controller->update($requestFor('PUT', "/budget/me/rebuild/data-governance-framework/controls/{$first->id}", $changed), $first);
    } catch (ValidationException $exception) {
        $immutable = str_contains($exception->getMessage(), 'immutable');
    }
    if (! $immutable) {
        throw new RuntimeException('An approved governance instrument could be edited in place.');
    }

    $controller->resolveAction($requestFor(
        'POST',
        "/budget/me/rebuild/data-governance-framework/actions/{$initialAction->id}/resolve",
        ['resolution_status' => 'resolved', 'resolution_notes' => 'Control testing was completed and signed evidence was retained.']
    ), $initialAction);

    $controller->newVersion($requestFor('POST', "/budget/me/rebuild/data-governance-framework/controls/{$first->id}/new-version"), $first);
    $second = MeDataGovernanceControl::query()->where('control_code', $code)->where('version', '2.0')->firstOrFail();
    $controller->update($requestFor('PUT', "/budget/me/rebuild/data-governance-framework/controls/{$second->id}", $controlInput('2.0')), $second);
    $second->refresh();

    $controller->storeAction($requestFor(
        'POST',
        "/budget/me/rebuild/data-governance-framework/controls/{$first->id}/actions",
        $actionInput('Predecessor open action '.$suffix)
    ), $first);
    $predecessorAction = MeDataGovernanceAction::query()->where('control_id', $first->id)->where('status', 'open')->latest()->firstOrFail();
    $controller->submitReview($requestFor('POST', "/budget/me/rebuild/data-governance-framework/controls/{$second->id}/submit-review"), $second);
    $second->refresh();

    $blockedReplacement = false;
    try {
        $controller->approve($requestFor('POST', "/budget/me/rebuild/data-governance-framework/controls/{$second->id}/approve"), $second);
    } catch (ValidationException $exception) {
        $blockedReplacement = str_contains($exception->getMessage(), 'currently approved version');
    }
    if (! $blockedReplacement) {
        throw new RuntimeException('A new version replaced an approved predecessor with unresolved actions.');
    }

    $controller->resolveAction($requestFor(
        'POST',
        "/budget/me/rebuild/data-governance-framework/actions/{$predecessorAction->id}/resolve",
        ['resolution_status' => 'risk_accepted', 'resolution_notes' => 'Management reviewed and formally accepted the documented residual risk.']
    ), $predecessorAction);
    $controller->approve($requestFor('POST', "/budget/me/rebuild/data-governance-framework/controls/{$second->id}/approve"), $second);
    $first->refresh();
    $second->refresh();
    if ($first->status !== 'retired' || $second->status !== 'approved') {
        throw new RuntimeException('Approving the successor did not retire the previous authoritative version.');
    }

    $pdf = $controller->pdf($requestFor(
        'GET',
        '/budget/me/rebuild/data-governance-framework/export/pdf?control_id='.$second->id,
        ['control_id' => $second->id]
    ));
    if ($pdf->headers->get('content-type') !== 'application/pdf' || ! str_starts_with($pdf->getContent(), '%PDF')) {
        throw new RuntimeException('The governance control-sheet PDF was not generated.');
    }

    $csv = $controller->csv($requestFor(
        'GET',
        '/budget/me/rebuild/data-governance-framework/export/csv?q='.$code,
        ['q' => $code, 'lifecycle' => 'all']
    ));
    ob_start();
    $csv->sendContent();
    $csvContent = (string) ob_get_clean();
    if (! str_contains($csvContent, $code) || ! str_contains($csvContent, 'Control code')) {
        throw new RuntimeException('The filtered governance CSV was not generated.');
    }

    if (! SystemAuditLog::query()->where('module', 'me_data_governance')->where('payload->control_id', $second->id)->exists()) {
        throw new RuntimeException('Governance lifecycle changes were not written to the system audit trail.');
    }

    echo "ME_DATA_GOVERNANCE_MANAGER_OK\n";
} finally {
    DB::rollBack();
}
