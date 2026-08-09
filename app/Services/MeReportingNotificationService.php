<?php

namespace App\Services;

use App\Models\MeDataCollection;
use App\Models\MeDataSubmission;
use App\Models\MeMissionReport;
use App\Models\MePerformanceReport;
use App\Models\MeReportingNotificationLog;
use App\Models\MeReportingPeriod;
use App\Models\User;
use App\Notifications\MeReportingNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MeReportingNotificationService
{
    public function submissionLifecycle(MeDataSubmission $submission, string $event): void
    {
        $submission->loadMissing([
            'assignment.thinkTank:id,name',
            'assignment.collection.form:id,title',
            'assignment.collection.reportingPeriod:id,label',
        ]);
        $labels = [
            'submitted' => ['M&E submission received', 'A Think Tank M&E submission is ready for Secretariat review.', 'info'],
            'resubmitted' => ['M&E submission resubmitted', 'The Think Tank has submitted a corrected version for review.', 'info'],
            'under_review' => ['M&E submission under review', 'The Secretariat has started reviewing this submission.', 'info'],
            'returned' => ['M&E submission returned', 'Corrections are required. Review the comments and submit a revised version.', 'warning'],
            'verified' => ['M&E submission verified', 'The submitted evidence and result values have been verified.', 'info'],
            'approved' => ['M&E submission approved', 'The Secretariat approved this submission for official consolidation.', 'success'],
            'rejected' => ['M&E submission rejected', 'The Secretariat rejected this submission. Review the decision comments.', 'danger'],
        ];
        if (! isset($labels[$event])) {
            return;
        }
        [$title, $message, $severity] = $labels[$event];
        $recipients = in_array($event, ['submitted', 'resubmitted'], true)
            ? $this->reviewers('me.data_entry.manage')
            : $this->authorsFor($submission);

        $this->notify($recipients, $event, $submission, [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'admin_url' => route('budget.me.submission-reviews.show', $submission),
            'portal_url' => route('think-tank.me-data.show', $submission->assignment_id),
            'category' => 'me_submission',
        ]);
    }

    public function periodOpened(MeReportingPeriod $period): void
    {
        $recipients = User::query()
            ->where('user_type', 'think_tank')
            ->whereIn('think_tank_access_level', [User::THINK_TANK_ACCESS_ADMIN, User::THINK_TANK_ACCESS_ME])
            ->where('is_disabled', false)
            ->get();
        $this->notify($recipients, 'reporting_period_opened', $period, [
            'title' => 'M&E reporting period opened',
            'message' => $period->label.' is open for M&E reporting'
                .($period->submission_deadline ? ' until '.$period->submission_deadline->format('d M Y') : '').'.',
            'severity' => 'info',
            'portal_url' => route('think-tank.me-data.index'),
            'category' => 'reporting_period',
        ]);
    }

    /**
     * Notify each assigned think tank through its active M&E portal accounts.
     * Every recipient receives a direct link to that think tank's assigned form.
     *
     * @return array{recipients: int, sent: int, already_notified: int, failed: int, unreachable_think_tanks: int}
     */
    public function collectionPublished(MeDataCollection $collection): array
    {
        $collection->loadMissing([
            'form.indicator:id,indicator_code,name',
            'reportingPeriod:id,label',
            'assignments.thinkTank.portalUser',
            'assignments.thinkTank.portalUsers',
        ]);

        $result = [
            'recipients' => 0,
            'sent' => 0,
            'already_notified' => 0,
            'failed' => 0,
            'unreachable_think_tanks' => 0,
        ];
        $form = $collection->form;
        $indicator = $form?->indicator;
        $indicatorLabel = trim(($indicator?->indicator_code ? $indicator->indicator_code.' - ' : '').($indicator?->name ?: ''));
        $deadline = $collection->due_at?->format('d M Y, H:i');

        foreach ($collection->assignments as $assignment) {
            $thinkTank = $assignment->thinkTank;
            $recipients = collect([$thinkTank?->portalUser])
                ->merge($thinkTank?->portalUsers ?? collect())
                ->filter(fn ($user): bool => $user instanceof User)
                ->filter(fn (User $user): bool => $user->user_type === 'think_tank'
                    && ! $user->is_disabled
                    && ($user->can('think_tank.me.view') || $user->can('think_tank.me.submit')))
                ->unique('id')
                ->values();

            if ($recipients->isEmpty()) {
                $result['unreachable_think_tanks']++;

                continue;
            }

            $result['recipients'] += $recipients->count();
            $delivery = $this->notify($recipients, 'collection_published', $collection, [
                'title' => 'New M&E data collection assigned',
                'message' => ($indicatorLabel ?: ($form?->title ?: 'An M&E collection form'))
                    .' is ready for '.$thinkTank->name
                    .($collection->reportingPeriod?->label ? ' for '.$collection->reportingPeriod->label : '')
                    .($deadline ? '. Submit by '.$deadline : '').'.',
                'severity' => 'info',
                'admin_url' => route('budget.me.rebuild.data-entry', [
                    'tab' => 'submissions',
                    'q' => $form?->code,
                ]),
                'portal_url' => route('think-tank.me-data.show', $assignment),
                'category' => 'me_collection',
            ], true);

            $result['sent'] += $delivery['sent'];
            $result['already_notified'] += $delivery['already_notified'];
            $result['failed'] += $delivery['failed'];
        }

        return $result;
    }

    public function performanceLifecycle(MePerformanceReport $report, string $event): void
    {
        $report->loadMissing(['createdBy:id,name', 'thinkTank:id,name']);
        $labels = [
            'submitted' => ['Performance report submitted', 'A performance report is awaiting Secretariat/M&E review.', 'info'],
            'returned' => ['Performance report returned', 'The report requires revision. Open the review notes and address all outstanding actions.', 'warning'],
            'verified' => ['Performance report verified', 'The M&E Officer verified the evidence and calculations. The report is awaiting final approval.', 'info'],
            'approved' => ['Performance report approved', 'The Secretariat/M&E Officer approved the performance report.', 'success'],
            'archived' => ['Performance report archived', 'The finalized report is now retained as a historical record.', 'secondary'],
        ];
        if (! isset($labels[$event])) {
            return;
        }
        [$title, $message, $severity] = $labels[$event];
        $recipients = in_array($event, ['submitted', 'verified'], true)
            ? $this->reviewers('me.performance_reports.review')
            : $this->authorsFor($report);

        $this->notify($recipients, $event, $report, [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'admin_url' => route('budget.me.performance-reports.edit', $report),
            'portal_url' => $report->think_tank_member_id
                ? route('think-tank.performance-reports.edit', $report)
                : null,
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
        if (! isset($labels[$event])) {
            return;
        }
        [$title, $message, $severity] = $labels[$event];
        $recipients = $event === 'submitted' ? $this->reviewers('me.mission_reports.review') : $this->authorsFor($report);
        $this->notify($recipients, $event, $report, [
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'admin_url' => route('budget.me.mission-reports.edit', $report),
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
        $thinkTankMemberId = $subject->think_tank_member_id
            ?? $subject->assignment?->think_tank_member_id
            ?? null;
        if ($thinkTankMemberId) {
            $thinkTankIds = User::query()
                ->where('think_tank_member_id', $thinkTankMemberId)
                ->pluck('id');
            $ids = $ids->merge($thinkTankIds);
        }

        return User::query()->whereIn('id', $ids->unique())->get();
    }

    /** @return array{sent: int, already_notified: int, failed: int} */
    private function notify(
        Collection $recipients,
        string $event,
        Model $subject,
        array $payload,
        bool $dailyDedupe = false
    ): array {
        $result = ['sent' => 0, 'already_notified' => 0, 'failed' => 0];

        foreach ($recipients as $user) {
            $dedupeLog = null;
            if ($dailyDedupe) {
                $dedupeLog = MeReportingNotificationLog::query()->firstOrCreate([
                    'user_id' => $user->id,
                    'event_key' => $event,
                    'subject_type' => $subject::class,
                    'subject_id' => $subject->getKey(),
                    'notification_date' => today(),
                ]);
                if (! $dedupeLog->wasRecentlyCreated) {
                    $result['already_notified']++;

                    continue;
                }
            }

            try {
                $recipientPayload = $this->payloadForRecipient($payload, $user);
                $user->notify(new MeReportingNotification($recipientPayload + [
                    'event' => $event,
                    'subject_type' => $subject::class,
                    'subject_id' => (string) $subject->getKey(),
                    'occurred_at' => now()->toIso8601String(),
                ]));
                $result['sent']++;
            } catch (\Throwable $exception) {
                $dedupeLog?->delete();
                report($exception);
                $result['failed']++;

                continue;
            }
        }

        return $result;
    }

    private function payloadForRecipient(array $payload, User $user): array
    {
        $adminUrl = $payload['admin_url'] ?? null;
        $portalUrl = $payload['portal_url'] ?? null;
        $isThinkTankRecipient = $user->user_type === 'think_tank'
            && ! $user->isAdmin()
            && ! $user->isSuperAdmin();
        $url = $isThinkTankRecipient ? ($portalUrl ?: $adminUrl) : ($adminUrl ?: $portalUrl);

        unset($payload['admin_url'], $payload['portal_url']);
        if (filled($url)) {
            $payload['url'] = $url;
        } else {
            unset($payload['url']);
        }

        return $payload;
    }
}
