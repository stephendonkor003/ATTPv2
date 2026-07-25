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

    public const REVERT_ACTION = 'activity_reallocation_revert_attempt';

    public function begin(Activity $activity, Project $targetProject, ?string $attemptId = null): SystemAuditLog
    {
        return $this->beginAttempt(
            $activity,
            $targetProject,
            $attemptId,
            self::ACTION,
            'Activity reallocation attempt started',
            'Reallocation is in progress.'
        );
    }

    /**
     * Record a new, server-resolved attempt to return an activity to the root
     * of its current reallocation chain.
     */
    public function beginRevert(
        Activity $activity,
        Project $targetProject,
        Collection $reallocationAttempts
    ): SystemAuditLog {
        $latestAttempt = $reallocationAttempts->first();
        if (! $latestAttempt instanceof SystemAuditLog) {
            throw new ActivityReallocationException('The original reallocation record could not be found.');
        }

        return $this->beginAttempt(
            $activity,
            $targetProject,
            null,
            self::REVERT_ACTION,
            'Activity reallocation revert started',
            'Reverting reallocation is in progress.',
            [
                'operation' => 'revert',
                'reverts_reallocation_attempt_ids' => $reallocationAttempts
                    ->pluck('id')
                    ->map(fn ($id) => (string) $id)
                    ->values()
                    ->all(),
                'reverts_latest_reallocation_attempt_id' => (string) $latestAttempt->id,
            ]
        );
    }

    /**
     * Record a new, validated move back to the activity's immediately
     * preceding component. Unlike an exact revert, this also supports older
     * successful moves that pre-date reallocation snapshots.
     */
    public function beginReturnToPrevious(
        Activity $activity,
        Project $targetProject,
        SystemAuditLog $previousAttempt
    ): SystemAuditLog {
        return $this->beginAttempt(
            $activity,
            $targetProject,
            null,
            self::ACTION,
            'Activity return to previous component started',
            'Returning the activity to its previous component is in progress.',
            [
                'operation' => 'return_to_previous',
                'returns_reallocation_attempt_id' => (string) $previousAttempt->id,
            ]
        );
    }

    /**
     * Record completion of a legacy/incomplete move whose activity
     * relationship is already at the destination but whose budget envelope
     * still needs to be transferred from the verified source.
     */
    public function beginCompleteToCurrent(
        Activity $activity,
        Project $sourceProject,
        Project $targetProject,
        SystemAuditLog $previousAttempt
    ): SystemAuditLog {
        return $this->beginAttempt(
            $activity,
            $targetProject,
            null,
            self::ACTION,
            'Activity reallocation completion started',
            'Completing the relationship and budget-envelope move.',
            [
                'operation' => 'complete_to_current',
                'source_project_id' => (string) $sourceProject->id,
                'source_project_name' => (string) $sourceProject->name,
                'completes_reallocation_attempt_id' => (string) $previousAttempt->id,
            ]
        );
    }

    /**
     * Persist the locked, pre-move state required for an exact relationship
     * restore. This is called inside the budget-move transaction.
     */
    public function captureSnapshot(SystemAuditLog $attempt, array $snapshot): void
    {
        if ($attempt->action !== self::ACTION) {
            throw new ActivityReallocationException('Only a reallocation attempt can store a reversion snapshot.');
        }

        $payload = (array) $attempt->payload;
        if (! empty($payload['reallocation_snapshot'])) {
            return;
        }

        $attempt->update([
            'payload' => array_merge($payload, [
                'reallocation_snapshot' => $snapshot,
                'snapshot_captured_at' => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Return a server-validated reallocation chain for a submitted revert
     * control. The latest attempt ID protects against stale forms.
     *
     * @return array{attempt: SystemAuditLog, attempts: Collection, source_project_id: string}|null
     */
    public function resolveRevertableReallocation(Activity $activity, string $attemptId): array
    {
        $reallocation = $this->revertableReallocationFor($activity);

        if (! $reallocation || (string) $reallocation['attempt']->id !== $attemptId) {
            throw new ActivityReallocationException(
                'This reallocation can no longer be reverted. Refresh the page and try again.'
            );
        }

        return $reallocation;
    }

    /**
     * Provide revert controls only for activities with an intact, successful
     * reallocation chain whose root is a different available project.
     */
    public function revertableReallocationsFor(Collection $activities, Collection $projects): Collection
    {
        $activitiesById = $activities->keyBy(fn (Activity $activity) => (string) $activity->id);
        $projectsById = $projects->keyBy(fn (Project $project) => (string) $project->id);
        $attemptsByActivity = $this->successfulUnrevertedAttemptsFor(
            $activitiesById->keys()->all()
        );

        $reallocations = collect();
        foreach ($activitiesById as $activityId => $activity) {
            $reallocation = $this->revertableReallocationFromAttempts(
                $activity,
                $attemptsByActivity->get($activityId, collect())
            );

            if (! $reallocation) {
                continue;
            }

            $sourceProject = $projectsById->get($reallocation['source_project_id']);
            if (! $sourceProject) {
                continue;
            }

            $reallocation['source_project'] = $sourceProject;
            $reallocations->put($activityId, $reallocation);
        }

        return $reallocations;
    }

    /**
     * Provide the immediately preceding component for successful moves,
     * including legacy attempts that do not have an exact reversion snapshot.
     */
    public function previousReallocationsFor(Collection $activities, Collection $projects): Collection
    {
        $activitiesById = $activities->keyBy(fn (Activity $activity) => (string) $activity->id);
        $projectsById = $projects->keyBy(fn (Project $project) => (string) $project->id);
        $attemptsByActivity = $this->successfulUnreturnedAttemptsFor(
            $activitiesById->keys()->all()
        );

        $reallocations = collect();
        foreach ($activitiesById as $activityId => $activity) {
            $reallocation = $this->previousReallocationFromAttempts(
                $activity,
                $attemptsByActivity->get($activityId, collect())
            );

            if (! $reallocation) {
                continue;
            }

            $sourceProject = $projectsById->get($reallocation['source_project_id']);
            if (! $sourceProject) {
                continue;
            }

            $reallocation['source_project'] = $sourceProject;
            $reallocations->put($activityId, $reallocation);
        }

        return $reallocations;
    }

    /**
     * Resolve the server-side evidence submitted by a return control and
     * reject stale or tampered forms.
     */
    public function resolvePreviousReallocation(Activity $activity, string $attemptId): array
    {
        $attemptsByActivity = $this->successfulUnreturnedAttemptsFor([(string) $activity->id]);
        $reallocation = $this->previousReallocationFromAttempts(
            $activity,
            $attemptsByActivity->get((string) $activity->id, collect())
        );

        if (! $reallocation || (string) $reallocation['attempt']->id !== $attemptId) {
            throw new ActivityReallocationException(
                'The previous component can no longer be verified. Refresh the page and try again.'
            );
        }

        return $reallocation;
    }

    /**
     * Provide incomplete legacy moves that can be safely completed at the
     * activity's current destination. Snapshot-backed moves already include
     * their budget transfer and are deliberately excluded.
     */
    public function completableReallocationsFor(Collection $activities, Collection $projects): Collection
    {
        $activitiesById = $activities->keyBy(fn (Activity $activity) => (string) $activity->id);
        $projectsById = $projects->keyBy(fn (Project $project) => (string) $project->id);
        $attemptsByActivity = $this->successfulUnreturnedAttemptsFor(
            $activitiesById->keys()->all()
        );

        $reallocations = collect();
        foreach ($activitiesById as $activityId => $activity) {
            $reallocation = $this->completableReallocationFromAttempts(
                $activity,
                $attemptsByActivity->get($activityId, collect())
            );

            if (! $reallocation) {
                continue;
            }

            $sourceProject = $projectsById->get($reallocation['source_project_id']);
            $targetProject = $projectsById->get((string) $activity->project_id);
            if (! $sourceProject || ! $targetProject) {
                continue;
            }

            $reallocation['source_project'] = $sourceProject;
            $reallocation['target_project'] = $targetProject;
            $reallocations->put($activityId, $reallocation);
        }

        return $reallocations;
    }

    public function resolveCompletableReallocation(Activity $activity, string $attemptId): array
    {
        $attemptsByActivity = $this->successfulUnreturnedAttemptsFor([(string) $activity->id]);
        $reallocation = $this->completableReallocationFromAttempts(
            $activity,
            $attemptsByActivity->get((string) $activity->id, collect())
        );

        if (! $reallocation || (string) $reallocation['attempt']->id !== $attemptId) {
            throw new ActivityReallocationException(
                'This incomplete move can no longer be verified. Refresh the page and try again.'
            );
        }

        return $reallocation;
    }

    public function markReverted(Collection $reallocationAttempts, SystemAuditLog $revertAttempt): void
    {
        $reallocationAttempts
            ->filter(fn ($attempt) => $attempt instanceof SystemAuditLog && $attempt->action === self::ACTION)
            ->each(function (SystemAuditLog $attempt) use ($revertAttempt) {
                $attempt->update([
                    'action_message' => 'Activity reallocation reverted',
                    'description' => 'The activity was returned to its original project.',
                    'payload' => array_merge((array) $attempt->payload, [
                        'reverted_at' => now()->toIso8601String(),
                        'reverted_by_attempt_id' => (string) $revertAttempt->id,
                    ]),
                ]);
            });
    }

    public function markEnvelopeCompleted(
        SystemAuditLog $previousAttempt,
        SystemAuditLog $completionAttempt
    ): void {
        $previousAttempt->update([
            'payload' => array_merge((array) $previousAttempt->payload, [
                'envelope_completed_at' => now()->toIso8601String(),
                'envelope_completed_by_attempt_id' => (string) $completionAttempt->id,
            ]),
        ]);
    }

    private function beginAttempt(
        Activity $activity,
        Project $targetProject,
        ?string $attemptId,
        string $action,
        string $startedActionMessage,
        string $description,
        array $extraPayload = []
    ): SystemAuditLog {
        $attempt = $attemptId
            ? SystemAuditLog::query()->where('action', $action)->find($attemptId)
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

            $recordedSourceProjectId = (string) ($payload['source_project_id'] ?? '');
            if (
                ($payload['status'] ?? null) !== 'succeeded'
                && $recordedSourceProjectId !== ''
                && $recordedSourceProjectId !== (string) $activity->project_id
                && (string) $targetProject->id !== (string) $activity->project_id
            ) {
                throw new ActivityReallocationException('The activity has moved since this saved attempt. Start a new reallocation from the activity.');
            }

            if (($payload['status'] ?? null) !== 'succeeded') {
                unset($payload['reallocation_snapshot'], $payload['snapshot_captured_at']);
            }
        } else {
            $attempt = new SystemAuditLog;
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
            'action' => $action,
            'action_message' => $startedActionMessage,
            'description' => $description,
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route_name' => $request->route()?->getName(),
            'ip_address' => $request->ip(),
            'country' => $country,
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
            'status_code' => 202,
            'payload' => array_merge(
                $payload,
                [
                    'status' => 'pending',
                    'activity_id' => (string) $activity->id,
                    'activity_name' => (string) $activity->name,
                    'source_project_id' => (string) ($payload['source_project_id'] ?? $activity->project_id),
                    'source_project_name' => (string) ($payload['source_project_name'] ?? ($activity->project?->name ?? '')),
                    'target_project_id' => (string) ($payload['target_project_id'] ?? $targetProject->id),
                    'target_project_name' => (string) ($payload['target_project_name'] ?? $targetProject->name),
                    'amount' => round((float) $activity->allocations()->sum('amount'), 2),
                    'attempt_count' => $attemptCount,
                    'last_attempted_at' => $now->toIso8601String(),
                    'error' => null,
                ],
                $extraPayload
            ),
        ]);
        $attempt->save();

        return $attempt;
    }

    public function fail(SystemAuditLog $attempt, string $message): void
    {
        $payload = (array) $attempt->payload;
        $attempt->update([
            'action_message' => $this->attemptLabel($attempt) . ' failed',
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
            'action_message' => $this->attemptLabel($attempt) . ' succeeded',
            'description' => $message,
            'status_code' => 200,
            'payload' => array_merge($payload, [
                'status' => 'succeeded',
                'error' => null,
                'completed_at' => now()->toIso8601String(),
            ]),
        ]);

        if ($attempt->action === self::ACTION) {
            $this->supersedeOtherOpenAttempts(
                (string) ($payload['activity_id'] ?? ''),
                (string) $attempt->id
            );
        }
    }

    /**
     * @return array{attempt: SystemAuditLog, attempts: Collection, source_project_id: string}|null
     */
    private function revertableReallocationFor(Activity $activity): ?array
    {
        $attemptsByActivity = $this->successfulUnrevertedAttemptsFor([(string) $activity->id]);

        return $this->revertableReallocationFromAttempts(
            $activity,
            $attemptsByActivity->get((string) $activity->id, collect())
        );
    }

    /**
     * @param  Collection<int, SystemAuditLog>  $attempts
     * @return array{attempt: SystemAuditLog, attempts: Collection, source_project_id: string}|null
     */
    private function revertableReallocationFromAttempts(Activity $activity, Collection $attempts): ?array
    {
        $attempts = $attempts->values();
        $currentProjectId = (string) $activity->project_id;
        $latestIndex = $attempts->search(function (SystemAuditLog $attempt) use ($currentProjectId) {
            return (string) data_get($attempt->payload, 'target_project_id') === $currentProjectId;
        });

        if ($latestIndex === false) {
            return null;
        }

        $chain = collect();
        $sourceProjectId = '';

        for ($index = $latestIndex; $index < $attempts->count(); $index++) {
            /** @var SystemAuditLog $attempt */
            $attempt = $attempts->get($index);
            $payload = (array) $attempt->payload;

            if ($index === $latestIndex) {
                $chain->push($attempt);
                $sourceProjectId = (string) ($payload['source_project_id'] ?? '');

                continue;
            }

            if ((string) ($payload['target_project_id'] ?? '') !== $sourceProjectId) {
                continue;
            }

            $chain->push($attempt);
            $sourceProjectId = (string) ($payload['source_project_id'] ?? '');
        }

        if ($chain->isEmpty() || $sourceProjectId === '' || $sourceProjectId === $currentProjectId) {
            return null;
        }

        $originalSnapshot = (array) data_get($chain->last()->payload, 'reallocation_snapshot');
        $currentSnapshot = (array) data_get($chain->first()->payload, 'reallocation_snapshot');
        if ($originalSnapshot === [] || $currentSnapshot === []) {
            return null;
        }

        return [
            'attempt' => $chain->first(),
            'attempts' => $chain,
            'source_project_id' => $sourceProjectId,
            'restore_state' => [
                'original' => $originalSnapshot,
                'current' => $currentSnapshot,
            ],
        ];
    }

    /**
     * Get successful regular reallocation attempts, newest first, keyed by
     * activity. Reverted chains are deliberately excluded.
     */
    private function successfulUnrevertedAttemptsFor(array $activityIds): Collection
    {
        if ($activityIds === []) {
            return collect();
        }

        $allowedActivityIds = array_map('strval', $activityIds);

        return SystemAuditLog::query()
            ->where('action', self::ACTION)
            ->where('status_code', 200)
            ->latest('created_at')
            ->get()
            ->filter(function (SystemAuditLog $attempt) use ($allowedActivityIds) {
                $payload = (array) $attempt->payload;

                return ($payload['status'] ?? null) === 'succeeded'
                    && empty($payload['reverted_at'])
                    && ! empty($payload['reallocation_snapshot'])
                    && in_array((string) ($payload['activity_id'] ?? ''), $allowedActivityIds, true);
            })
            ->groupBy(fn (SystemAuditLog $attempt) => (string) data_get($attempt->payload, 'activity_id'));
    }

    /**
     * Get successful moves, newest first, without requiring a modern
     * reallocation snapshot.
     */
    private function successfulUnreturnedAttemptsFor(array $activityIds): Collection
    {
        if ($activityIds === []) {
            return collect();
        }

        $allowedActivityIds = array_map('strval', $activityIds);

        return SystemAuditLog::query()
            ->where('action', self::ACTION)
            ->where('status_code', 200)
            ->latest('created_at')
            ->get()
            ->filter(function (SystemAuditLog $attempt) use ($allowedActivityIds) {
                $payload = (array) $attempt->payload;

                return ($payload['status'] ?? null) === 'succeeded'
                    && empty($payload['reverted_at'])
                    && in_array((string) ($payload['activity_id'] ?? ''), $allowedActivityIds, true);
            })
            ->groupBy(fn (SystemAuditLog $attempt) => (string) data_get($attempt->payload, 'activity_id'));
    }

    /**
     * Return the newest successful move that placed the activity in its
     * current component.
     *
     * @param  Collection<int, SystemAuditLog>  $attempts
     * @return array{attempt: SystemAuditLog, source_project_id: string}|null
     */
    private function previousReallocationFromAttempts(Activity $activity, Collection $attempts): ?array
    {
        $currentProjectId = (string) $activity->project_id;

        $attempt = $attempts->first(function (SystemAuditLog $attempt) use ($currentProjectId) {
            $payload = (array) $attempt->payload;
            $sourceProjectId = (string) ($payload['source_project_id'] ?? '');

            return ($payload['status'] ?? null) === 'succeeded'
                && empty($payload['reverted_at'])
                && (string) ($payload['target_project_id'] ?? '') === $currentProjectId
                && $sourceProjectId !== ''
                && $sourceProjectId !== $currentProjectId;
        });

        if (! $attempt) {
            return null;
        }

        return [
            'attempt' => $attempt,
            'source_project_id' => (string) data_get($attempt->payload, 'source_project_id'),
        ];
    }

    /**
     * Return a legacy/incomplete move only when its latest successful evidence
     * has no transaction snapshot and has not already had its envelope
     * completed by a later repair.
     *
     * @param  Collection<int, SystemAuditLog>  $attempts
     * @return array{attempt: SystemAuditLog, source_project_id: string}|null
     */
    private function completableReallocationFromAttempts(Activity $activity, Collection $attempts): ?array
    {
        $reallocation = $this->previousReallocationFromAttempts($activity, $attempts);
        if (! $reallocation) {
            return null;
        }

        $payload = (array) $reallocation['attempt']->payload;
        if (
            ! empty($payload['reallocation_snapshot'])
            || ! empty($payload['envelope_completed_at'])
            || ($payload['operation'] ?? null) === 'complete_to_current'
        ) {
            return null;
        }

        return $reallocation;
    }

    private function attemptLabel(SystemAuditLog $attempt): string
    {
        if ($attempt->action === self::REVERT_ACTION) {
            return 'Activity reallocation revert';
        }

        return match (data_get($attempt->payload, 'operation')) {
            'return_to_previous' => 'Activity return to previous component',
            'complete_to_current' => 'Activity reallocation completion',
            default => 'Activity reallocation',
        };
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

        $incompleteEnvelopeActivityIds = $this->activitiesWithIncompleteEnvelopeTransfers(
            $activities,
            $this->successfulUnreturnedAttemptsFor($allowedActivityIds)
        );
        foreach ($activities as $activity) {
            $project = $projectsById->get((string) $activity->project_id);
            if (! $project) {
                continue;
            }

            $directReasons = $this->activityIntegrityReasons($activity, $project, []);
            $deficitReasons = isset($incompleteEnvelopeActivityIds[(string) $activity->id])
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

    /**
     * A component deficit is actionable against an activity only when that
     * activity has a successful legacy move whose budget-envelope transfer is
     * still incomplete. Historical reallocation logs alone are not sufficient:
     * otherwise every already-repaired activity in the same component is
     * repeatedly reported for one shared component deficit.
     */
    private function activitiesWithIncompleteEnvelopeTransfers(
        Collection $activities,
        Collection $attemptsByActivity
    ): array
    {
        $ids = [];

        foreach ($activities as $activity) {
            $activityId = (string) $activity->id;
            $attempts = $attemptsByActivity->get($activityId, collect());

            if ($this->completableReallocationFromAttempts($activity, $attempts)) {
                $ids[$activityId] = true;
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
