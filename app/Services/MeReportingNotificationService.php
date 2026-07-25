<?php

namespace App\Services;

use App\Models\MeMissionReport;
use App\Models\MePerformanceReport;
use App\Models\MeReportingNotificationLog;
use App\Models\User;
use App\Notifications\MeReportingNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MeReportingNotificationService
{
    public function performanceLifecycle(MePerformanceReport $report, string $event): void
    {
        $report->loadMissing(['createdBy:id,name', 'thinkTank:id,name']);
        $labels = [
            'submitted' => ['Performance report submitted', 'A performance report is awaiting Secretariat/M&E review.', 'info'],
            'returned' => ['Performance report returned', 'The report requires revision. Open the review notes and address all outstanding actions.', 'warning'],
            'approved' => ['Performance report approved', 'The Secretariat/M&E Officer approved the performance report.', 'success'],
            'archived' => ['Performance report archived', 'The finalized report is now retained as a historical record.', 'secondary'],
        ];
        [$title, $message, $severity] = $labels[$event];
        $recipients = $event === 'submitted' ? $this->reviewers('me.performance_reports.review') : $this->authorsFor($report);

        $this->notify($recipients, $event, $report, [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'url' => $event !== 'submitted' && $report->think_tank_member_id
                ? route('think-tank.performance-reports.edit', $report)
                : route('budget.me.performance-reports.edit', $report),
            'category' => 'performance_report',
        ]);
    }

    public function missionLifecycle(MeMissionReport $report, string $event): void
    {
        $labels = [
            'submitted' => ['Mission report submitted', 'A standardized mission report is awaiting review.', 'info'],
            'returned' => ['Mission report returned', 'The mission report requires revision and has outstanding corrective action.', 'warning'],
            'approved' => ['Mission report approved', 'The mission report has been approved.', 'success'],
            'archived' => ['Mission report archived', 'The finalized mission report is now a historical record.', 'secondary'],
        ];
        [$title, $message, $severity] = $labels[$event];
        $recipients = $event === 'submitted' ? $this->reviewers('me.mission_reports.review') : $this->authorsFor($report);
        $this->notify($recipients, $event, $report, [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'url' => route('budget.me.mission-reports.edit', $report),
            'category' => 'mission_report',
        ]);
    }

    public function reminder(Model $subject, string $event, array $payload, Collection $recipients): void
    {
        $this->notify($recipients, $event, $subject, $payload, true);
    }

    public function reviewers(string $permission): Collection
    {
        return User::query()
            ->where(function ($query) use ($permission): void {
                $query->whereHas('role', fn ($role) => $role->where('name', 'System Admin'))
                    ->orWhereHas('role.permissions', fn ($permissions) => $permissions->where('name', $permission))
                    ->orWhereHas('permissions', fn ($permissions) => $permissions->where('name', $permission));
            })
            ->where('is_disabled', false)
            ->get()
            ->unique('id')
            ->values();
    }

    public function authorsFor(Model $subject): Collection
    {
        $ids = collect([$subject->created_by ?? null, $subject->submitted_by ?? null])->filter()->unique();
        if ($subject->think_tank_member_id ?? null) {
            $thinkTankIds = User::query()
                ->where('think_tank_member_id', $subject->think_tank_member_id)
                ->pluck('id');
            $ids = $ids->merge($thinkTankIds);
        }

        return User::query()->whereIn('id', $ids->unique())->get();
    }

    private function notify(
        Collection $recipients,
        string $event,
        Model $subject,
        array $payload,
        bool $dailyDedupe = false
    ): void {
        foreach ($recipients as $user) {
            if ($dailyDedupe && MeReportingNotificationLog::query()
                ->where('user_id', $user->id)
                ->where('event_key', $event)
                ->where('subject_type', $subject::class)
                ->where('subject_id', $subject->getKey())
                ->whereDate('notification_date', today())
                ->exists()) {
                continue;
            }

            try {
                $user->notify(new MeReportingNotification($payload + [
                    'event' => $event,
                    'subject_type' => $subject::class,
                    'subject_id' => (string) $subject->getKey(),
                    'occurred_at' => now()->toIso8601String(),
                ]));
            } catch (\Throwable $exception) {
                report($exception);
                continue;
            }

            if ($dailyDedupe) {
                MeReportingNotificationLog::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'event_key' => $event,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'notification_date' => today(),
                ]);
            }
        }
    }
}
