<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\SystemAuditLog;
use App\Support\IpGeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use App\Models\UserLoginOtp;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }


public function boot()
{
    // Align paginator HTML with the app's Bootstrap UI.
    Paginator::useBootstrapFive();

    Gate::before(function ($user, $ability) {
        return $user->hasPermission($ability);
    });

    Event::listen(Login::class, function (Login $event) {
        $country = IpGeo::countryForIp(request()->ip());

        SystemAuditLog::create([
            'user_id' => $event->user->id,
            'action' => 'login',
            'description' => 'User logged in',
            'method' => 'POST',
            'url' => request()->fullUrl(),
            'route_name' => optional(request()->route())->getName(),
            'ip_address' => request()->ip(),
            'country' => $country,
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
            'status_code' => 200,
            'payload' => ['email' => $event->user->email],
        ]);
    });

    Event::listen(Logout::class, function (Logout $event) {
        $country = IpGeo::countryForIp(request()->ip());

        SystemAuditLog::create([
            'user_id' => optional($event->user)->id,
            'action' => 'logout',
            'description' => 'User logged out',
            'method' => 'POST',
            'url' => request()->fullUrl(),
            'route_name' => optional(request()->route())->getName(),
            'ip_address' => request()->ip(),
            'country' => $country,
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
            'status_code' => 200,
            'payload' => ['email' => optional($event->user)->email],
        ]);
    });

    Event::listen(Failed::class, function (Failed $event) {
        $email = $event->credentials['email'] ?? null;

        $country = IpGeo::countryForIp(request()->ip());

        SystemAuditLog::create([
            'user_id' => optional($event->user)->id,
            'action' => 'login_failed',
            'description' => 'Failed login attempt',
            'method' => 'POST',
            'url' => request()->fullUrl(),
            'route_name' => optional(request()->route())->getName(),
            'ip_address' => request()->ip(),
            'country' => $country,
            'user_agent' => substr((string) request()->userAgent(), 0, 1000),
            'status_code' => 401,
            'payload' => ['email' => $email],
        ]);
    });

    Event::listen('eloquent.created: *', function ($eventName, array $data) {
        $this->logModelEvent('created', $data);
    });

    Event::listen('eloquent.updated: *', function ($eventName, array $data) {
        $this->logModelEvent('updated', $data);
    });

    Event::listen('eloquent.deleted: *', function ($eventName, array $data) {
        $this->logModelEvent('deleted', $data);
    });
}

private function logModelEvent(string $type, array $data): void
{
    $model = $data[0] ?? null;
    if (!$model instanceof Model) {
        return;
    }

    if ($model instanceof SystemAuditLog) {
        return;
    }

    // Avoid logging OTP records (contains sensitive verification material).
    if ($model instanceof UserLoginOtp) {
        return;
    }

    $changes = [];
    if ($type === 'updated') {
        $changes = $model->getChanges();
    }

    $original = [];
    if ($type === 'updated') {
        $original = array_intersect_key($model->getOriginal(), $changes);
    }

    $request = app()->runningInConsole() ? null : request();
    $country = $request ? IpGeo::countryForIp($request->ip()) : null;

    $module = $this->resolveAuditModule($model);
    $actionMessage = $this->buildActionMessage($type, $model);

    $payload = [
        'model' => get_class($model),
        'table' => $model->getTable(),
        'id' => $model->getKey(),
    ];

    if ($type === 'updated') {
        // Store only the diff for updates to reduce log volume.
        $payload['changes'] = $this->redactAuditPayload($changes);
        $payload['original'] = $this->redactAuditPayload($original);
    } else {
        $payload['attributes'] = $this->redactAuditPayload($model->getAttributes());
    }

    SystemAuditLog::create([
        'user_id' => optional($request?->user())->id,
        'module' => $module,
        'action' => "model_{$type}",
        'action_message' => $actionMessage,
        'description' => class_basename($model) . " {$type}",
        'method' => $request?->method(),
        'url' => $request?->fullUrl(),
        'route_name' => $request?->route()?->getName(),
        'ip_address' => $request?->ip(),
        'country' => $country,
        'user_agent' => $request?->userAgent() ? substr((string) $request->userAgent(), 0, 1000) : null,
        'status_code' => $request ? 200 : null,
        'payload' => $payload,
    ]);
}

/**
 * Remove secrets/credentials from audit payloads before persisting them.
 *
 * Note: This is not a substitute for careful auditing scope; it's a safety net.
 */
private function redactAuditPayload(array $data): array
{
    $redactKeys = [
        'password',
        'remember_token',
        'current_password',
        'password_confirmation',
        'otp_code',
        'token',
        'secret',
        'api_token',
    ];

    foreach ($redactKeys as $key) {
        if (array_key_exists($key, $data)) {
            $data[$key] = '[REDACTED]';
        }
    }

    return $data;
}

private function resolveAuditModule(Model $model): string
{
    $class = class_basename($model);

    $map = [
        // Budget
        'Sector' => 'budget',
        'Program' => 'budget',
        'Project' => 'budget',
        'Activity' => 'budget',
        'SubActivity' => 'budget',

        // Users & Security
        'User' => 'user',
        'Role' => 'user',
        'Permission' => 'user',

        // Procurement
        'Procurement' => 'procurement',
        'DynamicForm' => 'procurement',
        'FormSubmission' => 'procurement',
        'ProcurementSubmissionScreening' => 'procurement',

        // Prescreening
        'PrescreeningTemplate' => 'prescreening',
        'PrescreeningCriterion' => 'prescreening',
        'PrescreeningResult' => 'prescreening',

        // Evaluations
        'Evaluation' => 'evaluations',
        'EvaluationAssignment' => 'evaluations',
        'EvaluationSubmission' => 'evaluations',

        // HR
        'HrVacancy' => 'hr',
        'HrApplicant' => 'hr',
        'HrEmployee' => 'hr',

        // Finance
        'FinanceCommitment' => 'finance',
        'FinanceExecution' => 'finance',
        'FinanceResource' => 'finance',
        'FinanceResourceCategory' => 'finance',
        'FinanceResourceItem' => 'finance',

        // Communications
        'MemberStateCommunication' => 'communications',
        'MemberStateCommunicationAttachment' => 'communications',

        // Think Tank Management
        'Consortium' => 'think_tank_management',
        'ConsortiumThinkTank' => 'think_tank_management',
        'ConsortiumFundAllocation' => 'think_tank_management',
        'ConsortiumDisbursementRequest' => 'think_tank_management',
        'ConsortiumActivityReport' => 'think_tank_management',
        'ThinkTankResearchOutput' => 'think_tank_management',
        'ThinkTankProcurementPlan' => 'think_tank_management',
    ];

    return $map[$class] ?? 'system';
}

private function buildActionMessage(string $type, Model $model): string
{
    $label = method_exists($model, 'getAttribute')
        ? ($model->getAttribute('title')
            ?? $model->getAttribute('name')
            ?? $model->getAttribute('reference_no')
            ?? $model->getAttribute('email')
            ?? $model->getKey())
        : $model->getKey();

    $modelName = strtolower(str_replace('_', ' ', class_basename($model)));

    if ($type === 'created') {
        return "Created a new {$modelName}: {$label}";
    }

    if ($type === 'updated') {
        return "Updated {$modelName}: {$label}";
    }

    if ($type === 'deleted') {
        return "Deleted {$modelName}: {$label}";
    }

    return ucfirst($type) . " {$modelName}: {$label}";
}

}
