<?php

namespace App\Services;

use App\Exceptions\ActivityReallocationException;
use App\Models\Activity;
use App\Models\Project;
use App\Models\SystemAuditLog;
use App\Support\IpGeo;
use Illuminate\Support\Collection;

class ActivityReallocationTracker
{
    public const ACTION = 'activity_reallocation_attempt';

    public function begin(Activity $activity, Project $targetProject, ?string $attemptId = null): SystemAuditLog
    {
        $attempt = $attemptId
            ? SystemAuditLog::query()->where('action', self::ACTION)->find($attemptId)
            : null;

        if ($attemptId && ! $attempt) {
            throw new ActivityReallocationException('The saved reallocation attempt could not be found. Start a new reallocation from the activity.');
        }

        if ($attempt) {
            $payload = (array) $attempt->payload;
            if ((string) ($payload['activity_id'] ?? '') !== (string) $activity->id) {
                throw new ActivityReallocationException('The saved reallocation attempt does not belong to this activity.');
            }
            if ((string) ($payload['target_project_id'] ?? '') !== (string) $targetProject->id) {
                throw new ActivityReallocationException('The saved reallocation target has changed. Start a new reallocation from the activity.');
            }
        } else {
            $attempt = new SystemAuditLog();
            $payload = [];
        }

        $request = request();
        $attemptCount = (int) ($payload['attempt_count'] ?? 0) + 1;
        $now = now();

        try {
            $country = IpGeo::countryForIp($request->ip());
        } catch (\Throwable $error) {
            report($error);
            $country = null;
        }

        $attempt->fill([
            'user_id' => auth()->id(),
            'module' => 'Budget',
            'action' => self::ACTION,
            'action_message' => 'Activity reallocation attempt started',
            'description' => 'Reallocation is in progress.',
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'country' => $country,
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'status_code' => 202,
            'payload' => array_merge($payload, [
                'status' => 'pending',
                'activity_id' => (string) $activity->id,
                'activity_name' => (string) $activity->name,
                'source_project_id' => (string) $activity->project_id,
                'source_project_name' => (string) ($activity->project?->name ?? ''),
                'target_project_id' => (string) $targetProject->id,
                'target_project_name' => (string) $targetProject->name,
                'amount' => round((float) $activity->allocations()->sum('amount'), 2),
                'attempt_count' => $attemptCount,
                'last_attempted_at' => $now->toIso8601String(),
                'error' => null,
            ]),
        ]);
        $attempt->save();

        return $attempt;
    }

    public function fail(SystemAuditLog $attempt, string $message): void
    {
        $payload = (array) $attempt->payload;
        $attempt->update([
            'action_message' => 'Activity reallocation failed',
            'description' => $message,
            'status_code' => 422,
            'payload' => array_merge($payload, [
                'status' => 'failed',
                'error' => $message,
                'failed_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    public function succeed(SystemAuditLog $attempt, string $message): void
    {
        $payload = (array) $attempt->payload;
        $attempt->update([
            'action_message' => 'Activity reallocation succeeded',
            'description' => $message,
            'status_code' => 200,
            'payload' => array_merge($payload, [
                'status' => 'succeeded',
                'error' => null,
                'completed_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->supersedeOtherOpenAttempts(
            (string) ($payload['activity_id'] ?? ''),
            (string) $attempt->id
        );
    }

    /**
     * Return failed/interrupted attempts and older integrity problems that are
     * actionable from the Sub-Activities screen.
     */
    public function issuesFor(Collection $activities, Collection $projects): Collection
    {
        $activitiesById = $activities->keyBy(fn (Activity $activity) => (string) $activity->id);
        $projectsById = $projects->keyBy(fn (Project $project) => (string) $project->id);
        $allowedActivityIds = $activitiesById->keys()->all();
        $projectDeficits = $this->projectDeficits($projects);

        $openAttempts = SystemAuditLog::query()
            ->where('action', self::ACTION)
            ->whereIn('status_code', [202, 422])
            ->latest('created_at')
            ->get()
            ->filter(function (SystemAuditLog $attempt) use ($allowedActivityIds) {
                return in_array((string) data_get($attempt->payload, 'activity_id'), $allowedActivityIds, true);
            });

        $issues = collect();
        $openKeys = [];

        foreach ($openAttempts as $attempt) {
            $payload = (array) $attempt->payload;
            $activity = $activitiesById->get((string) ($payload['activity_id'] ?? ''));
            $targetProject = $projectsById->get((string) ($payload['target_project_id'] ?? ''));
            if (! $activity || ! $targetProject) {
                continue;
            }

            $atTarget = (string) $activity->project_id === (string) $targetProject->id;
            $integrityReasons = $atTarget
                ? $this->activityIntegrityReasons(
                    $activity,
                    $targetProject,
                    $projectDeficits[(string) $targetProject->id] ?? []
                )
                : [];

            if ($atTarget && $integrityReasons === []) {
                $this->succeed($attempt, 'The activity is already complete in the requested component.');
                continue;
            }

            $key = (string) $activity->id . '|' . (string) $targetProject->id;
            if (isset($openKeys[$key])) {
                continue;
            }
            $openKeys[$key] = true;

            $issues->push([
                'key' => 'attempt-' . $attempt->id,
                'attempt_id' => (string) $attempt->id,
                'activity' => $activity,
                'source_project' => $projectsById->get((string) ($payload['source_project_id'] ?? '')) ?? $activity->project,
                'target_project' => $targetProject,
                'amount' => (float) ($payload['amount'] ?? $activity->allocations->sum('amount')),
                'status' => ($payload['status'] ?? null) === 'pending' ? 'Interrupted' : 'Failed',
                'reason' => $integrityReasons !== []
                    ? implode(' ', $integrityReasons)
                    : (string) ($payload['error'] ?? 'The activity is still in its source component.'),
                'attempt_count' => max(1, (int) ($payload['attempt_count'] ?? 1)),
                'last_attempted_at' => $payload['last_attempted_at'] ?? $attempt->created_at,
                'repair' => $atTarget,
            ]);
        }

        $legacyRequests = $this->legacyRequestAttempts($allowedActivityIds);
        foreach ($legacyRequests as $legacyRequest) {
            $activity = $activitiesById->get($legacyRequest['activity_id']);
            $targetProject = $projectsById->get($legacyRequest['target_project_id']);
            if (! $activity || ! $targetProject) {
                continue;
            }

            $key = (string) $activity->id . '|' . (string) $targetProject->id;
            if (isset($openKeys[$key]) || (string) $activity->project_id === (string) $targetProject->id) {
                continue;
            }
            $openKeys[$key] = true;

            $issues->push([
                'key' => 'legacy-request-' . $legacyRequest['log']->id,
                'attempt_id' => null,
                'activity' => $activity,
                'source_project' => $activity->project,
                'target_project' => $targetProject,
                'amount' => (float) $activity->allocations->sum('amount'),
                'status' => 'Needs retry',
                'reason' => 'The last recorded reallocation request did not move the activity to its selected target component.',
                'attempt_count' => $legacyRequest['attempt_count'],
                'last_attempted_at' => $legacyRequest['log']->created_at,
                'repair' => false,
            ]);
        }

        $reallocationEvidence = $this->reallocatedActivityIds(
            $allowedActivityIds,
            $openAttempts,
            $legacyRequests
        );
        foreach ($activities as $activity) {
            $project = $projectsById->get((string) $activity->project_id);
            if (! $project) {
                continue;
            }

            $directReasons = $this->activityIntegrityReasons($activity, $project, []);
            $deficitReasons = isset($reallocationEvidence[(string) $activity->id])
                ? ($projectDeficits[(string) $project->id] ?? [])
                : [];
            $reasons = array_values(array_unique(array_merge($directReasons, $deficitReasons)));
            $key = (string) $activity->id . '|' . (string) $project->id;

            if ($reasons === [] || isset($openKeys[$key])) {
                continue;
            }

            $issues->push([
                'key' => 'detected-' . $activity->id,
                'attempt_id' => null,
                'activity' => $activity,
                'source_project' => $project,
                'target_project' => $project,
                'amount' => (float) $activity->allocations->sum('amount'),
                'status' => 'Needs repair',
                'reason' => implode(' ', $reasons),
                'attempt_count' => null,
                'last_attempted_at' => null,
                'repair' => true,
            ]);
        }

        return $issues->values();
    }

    private function projectDeficits(Collection $projects): array
    {
        $deficits = [];

        foreach ($projects as $project) {
            $componentByYear = $project->allocations
                ->groupBy(fn ($allocation) => (int) $allocation->year)
                ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));
            $activitiesByYear = $project->activities
                ->flatMap->allocations
                ->groupBy(fn ($allocation) => (int) $allocation->year)
                ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2));

            $yearMessages = [];
            foreach ($activitiesByYear as $year => $amount) {
                $available = (float) ($componentByYear[(int) $year] ?? 0);
                if ($amount > $available + 0.01) {
                    $yearMessages[] = $year . ' is short by ' . number_format($amount - $available, 2) . '.';
                }
            }

            if ($yearMessages !== []) {
                $deficits[(string) $project->id] = [
                    'The target component yearly envelope does not cover all of its activities: ' . implode(' ', $yearMessages),
                ];
            }
        }

        return $deficits;
    }

    private function activityIntegrityReasons(Activity $activity, Project $project, array $projectDeficitReasons): array
    {
        $reasons = [];
        $projectNodeId = (string) ($project->governance_node_id ?? '');

        if ((string) ($activity->governance_node_id ?? '') !== $projectNodeId) {
            $reasons[] = 'The activity governance assignment does not match its component.';
        }

        $misalignedSubActivities = $activity->subActivities
            ->filter(fn ($subActivity) => (string) ($subActivity->governance_node_id ?? '') !== $projectNodeId)
            ->count();
        if ($misalignedSubActivities > 0) {
            $reasons[] = $misalignedSubActivities . ' sub-activit' . ($misalignedSubActivities === 1 ? 'y is' : 'ies are') . ' still assigned to the previous governance node.';
        }

        $projectYears = collect($project->years())->map(fn ($year) => (int) $year);
        $outsideYears = $activity->allocations
            ->filter(fn ($allocation) => (float) $allocation->amount > 0)
            ->pluck('year')
            ->map(fn ($year) => (int) $year)
            ->diff($projectYears)
            ->sort()
            ->values();
        if ($outsideYears->isNotEmpty()) {
            $reasons[] = 'Its allocation contains year(s) outside the component period: ' . $outsideYears->implode(', ') . '.';
        }

        return array_values(array_unique(array_merge($reasons, $projectDeficitReasons)));
    }

    private function reallocatedActivityIds(
        array $allowedActivityIds,
        Collection $openAttempts,
        Collection $legacyRequests
    ): array
    {
        $ids = [];

        foreach ($openAttempts as $attempt) {
            $id = (string) data_get($attempt->payload, 'activity_id');
            if ($id !== '') {
                $ids[$id] = true;
            }
        }

        foreach ($legacyRequests as $legacyRequest) {
            $ids[$legacyRequest['activity_id']] = true;
        }

        $historicalLogs = SystemAuditLog::query()
            ->where('route_name', 'budget.activities.reallocate')
            ->where('action', 'model_updated')
            ->latest('created_at')
            ->get(['payload']);

        foreach ($historicalLogs as $log) {
            $id = (string) data_get($log->payload, 'id');
            if (in_array($id, $allowedActivityIds, true)) {
                $ids[$id] = true;
            }
        }

        return $ids;
    }

    private function legacyRequestAttempts(array $allowedActivityIds): Collection
    {
        $attempts = collect();

        $requestLogs = SystemAuditLog::query()
            ->where('action', 'request')
            ->where('route_name', 'budget.activities.reallocate')
            ->latest('created_at')
            ->get();

        foreach ($requestLogs as $log) {
            $path = (string) parse_url((string) $log->url, PHP_URL_PATH);
            if (! preg_match('~/budget/activities/([^/]+)/reallocate$~', rtrim($path, '/'), $matches)) {
                continue;
            }

            $activityId = (string) ($matches[1] ?? '');
            $targetProjectId = (string) data_get($log->payload, 'project_id');
            if (
                $activityId === ''
                || $targetProjectId === ''
                || ! in_array($activityId, $allowedActivityIds, true)
            ) {
                continue;
            }

            if (! $attempts->has($activityId)) {
                $attempts->put($activityId, [
                    'activity_id' => $activityId,
                    'target_project_id' => $targetProjectId,
                    'attempt_count' => 1,
                    'log' => $log,
                ]);
                continue;
            }

            $existing = $attempts->get($activityId);
            $existing['attempt_count']++;
            $attempts->put($activityId, $existing);
        }

        return $attempts->values();
    }

    private function supersedeOtherOpenAttempts(string $activityId, string $completedAttemptId): void
    {
        if ($activityId === '') {
            return;
        }

        SystemAuditLog::query()
            ->where('action', self::ACTION)
            ->whereIn('status_code', [202, 422])
            ->where('id', '!=', $completedAttemptId)
            ->get()
            ->filter(fn (SystemAuditLog $attempt) => (string) data_get($attempt->payload, 'activity_id') === $activityId)
            ->each(function (SystemAuditLog $attempt) {
                $attempt->update([
                    'action_message' => 'Activity reallocation superseded',
                    'description' => 'A later reallocation for this activity succeeded.',
                    'status_code' => 409,
                    'payload' => array_merge((array) $attempt->payload, [
                        'status' => 'superseded',
                        'superseded_at' => now()->toIso8601String(),
                    ]),
                ]);
            });
    }
}
