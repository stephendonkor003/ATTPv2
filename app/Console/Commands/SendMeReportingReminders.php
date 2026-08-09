<?php

namespace App\Console\Commands;

use App\Models\MeDataCollectionAssignment;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\MeMissionReport;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportDocument;
use App\Services\MeReportingNotificationService;
use Illuminate\Console\Command;

class SendMeReportingReminders extends Command
{
    protected $signature = 'me:send-reporting-reminders';

    protected $description = 'Generate M&E reporting deadlines, corrective-action and MOV validation reminders';

    public function handle(MeReportingNotificationService $notifications): int
    {
        $reviewers = $notifications->reviewers('me.performance_reports.review');
        MeDataCollectionAssignment::query()
            ->whereDoesntHave('performanceReports')
            ->whereHas('collection', fn ($query) => $query
                ->where('status', 'open')
                ->whereNotNull('due_at')
                ->whereDate('due_at', '<=', today()->addDays(7)))
            ->with(['collection:id,form_id,due_at', 'collection.form:id,code,title'])
            ->each(function (MeDataCollectionAssignment $assignment) use ($notifications): void {
                $due = $assignment->collection?->due_at;
                $overdue = $due && $due->isPast();
                $notifications->reminder($assignment, $overdue ? 'assignment_report_overdue' : 'assignment_deadline_upcoming', [
                    'title' => $overdue ? 'Assigned report overdue' : 'Assigned reporting deadline approaching',
                    'message' => ($assignment->collection?->form?->title ?: 'An assigned reporting form')
                        .($overdue ? ' was due ' : ' is due ').optional($due)->format('d M Y').'.',
                    'severity' => $overdue ? 'danger' : 'warning',
                    'portal_url' => route('think-tank.performance-reports.index'),
                    'category' => 'deadline',
                ], $notifications->authorsFor($assignment));
            });

        MePerformanceReport::query()
            ->where('status', MePerformanceReport::STATUS_DRAFT)
            ->whereHas('assignment.collection', fn ($query) => $query->whereNotNull('due_at')->whereDate('due_at', '<=', today()->addDays(7)))
            ->with(['assignment.collection', 'createdBy:id,name'])
            ->each(function (MePerformanceReport $report) use ($notifications): void {
                $due = $report->assignment?->collection?->due_at;
                $overdue = $due && $due->isPast();
                $notifications->reminder($report, $overdue ? 'report_overdue' : 'deadline_upcoming', [
                    'title' => $overdue ? 'Performance report overdue' : 'Reporting deadline approaching',
                    'message' => ($overdue ? 'This report was due ' : 'This report is due ').optional($due)->format('d M Y').'.',
                    'severity' => $overdue ? 'danger' : 'warning',
                    'admin_url' => route('budget.me.performance-reports.edit', $report),
                    'portal_url' => $report->think_tank_member_id
                        ? route('think-tank.performance-reports.edit', $report)
                        : null,
                    'category' => 'deadline',
                ], $notifications->authorsFor($report));
            });

        MePerformanceReport::query()
            ->where('status', MePerformanceReport::STATUS_DRAFT)
            ->whereNotNull('review_notes')
            ->each(fn (MePerformanceReport $report) => $notifications->reminder($report, 'corrective_action_outstanding', [
                'title' => 'Report corrections outstanding',
                'message' => 'A returned performance report still requires correction and resubmission.',
                'severity' => 'warning',
                'admin_url' => route('budget.me.performance-reports.edit', $report),
                'portal_url' => $report->think_tank_member_id
                    ? route('think-tank.performance-reports.edit', $report)
                    : null,
                'category' => 'corrective_action',
            ], $notifications->authorsFor($report)));

        MeMissionReport::query()
            ->whereNotIn('status', [MeMissionReport::STATUS_ARCHIVED])
            ->whereNotNull('corrective_actions')
            ->whereNotNull('action_due_at')
            ->whereDate('action_due_at', '<=', today()->addDays(7))
            ->each(fn (MeMissionReport $report) => $notifications->reminder($report, 'mission_corrective_action', [
                'title' => $report->action_due_at?->isPast() ? 'Mission corrective action overdue' : 'Mission corrective action due soon',
                'message' => 'Follow-up action is due '.$report->action_due_at?->format('d M Y').'.',
                'severity' => $report->action_due_at?->isPast() ? 'danger' : 'warning',
                'admin_url' => route('budget.me.mission-reports.edit', $report),
                'category' => 'corrective_action',
            ], $notifications->authorsFor($report)));

        MePerformanceReportDocument::query()
            ->where('validation_status', 'pending')
            ->with('report')
            ->each(fn (MePerformanceReportDocument $document) => $notifications->reminder($document, 'mov_validation_required', [
                'title' => 'Means of Verification requires validation',
                'message' => $document->document_name.' is awaiting Secretariat validation.',
                'severity' => 'info',
                'admin_url' => route('budget.me.performance-reports.edit', $document->report),
                'category' => 'mov_validation',
            ], $reviewers));

        MeKnowledgeEvidenceItem::query()
            ->where('document_type', 'means_of_verification')
            ->where('validation_status', 'pending')
            ->each(fn (MeKnowledgeEvidenceItem $evidence) => $notifications->reminder($evidence, 'repository_mov_validation_required', [
                'title' => 'Repository MOV requires validation',
                'message' => $evidence->title.' is awaiting validation.',
                'severity' => 'info',
                'admin_url' => route('budget.me.rebuild.knowledge-repository', ['q' => $evidence->title]),
                'category' => 'mov_validation',
            ], $reviewers));

        $this->info('M&E reporting reminders generated.');

        return self::SUCCESS;
    }
}
