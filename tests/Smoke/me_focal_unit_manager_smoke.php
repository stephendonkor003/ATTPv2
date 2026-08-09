<?php

use App\Http\Controllers\MeFocalUnitController;
use App\Models\ConsortiumThinkTank;
use App\Models\MeFocalUnitContact;
use App\Models\Role;
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
$role = Role::query()->whereNotIn('name', ['System Admin', 'Super Admin'])->orderBy('name')->firstOrFail();
$thinkTank = ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->firstOrFail();
$app['auth']->guard()->setUser($admin);
$controller = $app->make(MeFocalUnitController::class);
$suffix = Str::lower(Str::random(10));
$email = "focal-smoke-{$suffix}@example.test";
$secondaryEmail = "focal-secondary-{$suffix}@example.test";

$requestFor = function (string $method, string $uri, array $input = []) use ($app, $admin): Request {
    $request = Request::create($uri, $method, $input);
    $request->setUserResolver(fn () => $admin);
    $app->instance('request', $request);

    return $request;
};

$contactInput = static function (string $person, string $email, bool $primary = true) use ($thinkTank, $suffix): array {
    return [
        'consortium_name' => 'SMOKE '.$suffix,
        'think_tank_member_id' => $thinkTank->id,
        'think_tank_label' => 'SMK-'.Str::upper(substr($suffix, 0, 5)),
        'focal_person_name' => $person,
        'email' => $email,
        'is_primary' => $primary ? '1' : '0',
        'notes' => 'Transactional focal-unit control-centre verification.',
    ];
};

DB::beginTransaction();

try {
    $account = User::query()->create([
        'name' => 'Focal Smoke '.$suffix,
        'email' => $email,
        'password' => Str::random(40),
        'role_id' => $role->id,
        'user_type' => null,
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);

    $controller->store($requestFor('POST', '/budget/me/focal-units', $contactInput('Primary Smoke '.$suffix, $email)));
    $first = MeFocalUnitContact::query()->where('email', $email)->firstOrFail();
    $controller->store($requestFor('POST', '/budget/me/focal-units', $contactInput('Secondary Smoke '.$suffix, $secondaryEmail)));
    $second = MeFocalUnitContact::query()->where('email', $secondaryEmail)->firstOrFail();
    $first->refresh();
    if ($first->is_primary || ! $second->is_primary) {
        throw new RuntimeException('The single-primary responsibility rule was not enforced.');
    }

    $controller->linkAccount($requestFor(
        'POST',
        "/budget/me/focal-units/{$first->id}/link-account",
        ['user_id' => $account->id]
    ), $first);
    $first->refresh();
    $account->refresh();
    if ((string) $first->user_id !== (string) $account->id
        || $account->user_type !== 'think_tank'
        || (string) $account->think_tank_member_id !== (string) $thinkTank->id
        || $account->think_tank_access_level !== User::THINK_TANK_ACCESS_ME) {
        throw new RuntimeException('Formal M&E account linking did not assign the controlled organization and role.');
    }

    $blockedIdentityChange = false;
    try {
        $changed = $contactInput('Primary Smoke '.$suffix, "changed-{$suffix}@example.test", false);
        $controller->update($requestFor('PUT', "/budget/me/focal-units/{$first->id}", $changed), $first);
    } catch (ValidationException $exception) {
        $blockedIdentityChange = str_contains($exception->getMessage(), 'Unlink the platform account');
    }
    if (! $blockedIdentityChange) {
        throw new RuntimeException('A linked contact identity could be changed without unlinking the account.');
    }

    $view = $controller->index($requestFor(
        'GET',
        '/budget/me/focal-units?readiness=ready&q='.rawurlencode($suffix),
        ['readiness' => 'ready', 'q' => $suffix]
    ));
    $html = $view->with('errors', new ViewErrorBag)->render();
    if ($view->getData()['contacts']->total() !== 1
        || ! str_contains($html, $first->focal_person_name)
        || ! str_contains($html, 'focal-readiness-chart')
        || ! str_contains($html, 'Focal responsibility register')) {
        throw new RuntimeException('The filtered focal-unit control centre did not render the ready contact.');
    }

    $pdfResponse = $controller->pdf($requestFor(
        'GET',
        '/budget/me/focal-units/export/pdf?contact_id='.$first->id,
        ['contact_id' => $first->id]
    ));
    if ($pdfResponse->headers->get('content-type') !== 'application/pdf'
        || ! str_starts_with($pdfResponse->getContent(), '%PDF')) {
        throw new RuntimeException('The focal-unit control-sheet PDF was not generated.');
    }

    $controller->unlinkAccount($first);
    $first->refresh();
    if ($first->user_id !== null || ! User::query()->whereKey($account->id)->exists()) {
        throw new RuntimeException('Unlinking should preserve the platform account.');
    }
    $controller->destroy($first);
    $first->refresh();
    if ($first->is_active || $first->is_primary) {
        throw new RuntimeException('Archiving did not preserve the record in an inactive state.');
    }
    $controller->restore($first);
    if (! $first->fresh()->is_active) {
        throw new RuntimeException('Archived focal contact could not be restored.');
    }

    echo "ME_FOCAL_UNIT_MANAGER_OK\n";
} finally {
    DB::rollBack();
}
