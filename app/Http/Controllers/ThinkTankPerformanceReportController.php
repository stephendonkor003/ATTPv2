<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MePerformanceReport;
use App\Models\MePerformanceReportDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ThinkTankPerformanceReportController extends MePerformanceReportController
{
    public function __construct()
    {
        // Think-tank authentication, area access, and permissions are applied
        // explicitly to the portal route group.
    }

    public function index(Request $request): View
    {
        $member = $this->member($request);
        $status = trim((string) $request->query('status'));
        $search = trim((string) $request->query('q'));
        $baseQuery = MePerformanceReport::query()->where('think_tank_member_id', $member->id);

        $summary = [
            MePerformanceReport::STATUS_DRAFT => (clone $baseQuery)->where('status', MePerformanceReport::STATUS_DRAFT)->count(),
            MePerformanceReport::STATUS_SUBMITTED => (clone $baseQuery)->where('status', MePerformanceReport::STATUS_SUBMITTED)->count(),
            MePerformanceReport::STATUS_REVIEWED => (clone $baseQuery)->where('status', MePerformanceReport::STATUS_REVIEWED)->count(),
            MePerformanceReport::STATUS_VERIFIED => (clone $baseQuery)->where('status', MePerformanceReport::STATUS_VERIFIED)->count(),
            MePerformanceReport::STATUS_APPROVED => (clone $baseQuery)->where('status', MePerformanceReport::STATUS_APPROVED)->count(),
            MePerformanceReport::STATUS_ARCHIVED => (clone $baseQuery)->where('status', MePerformanceReport::STATUS_ARCHIVED)->count(),
        ];

        $reports = $baseQuery
            ->with([
                'form:id,code,title',
                'projectComponent:id,project_id,name',
                'responsibleDirectorate:id,name,code',
                'reviewedBy:id,name',
                'archivedBy:id,name',
            ])
            ->withCount(['indicatorResults', 'documents'])
            ->when(
                in_array($status, array_keys($summary), true),
                fn ($query) => $query->where('status', $status)
            )
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($searchQuery) use ($term): void {
                    $searchQuery->whereHas('form', fn ($formQuery) => $formQuery
                        ->where('title', 'like', $term)
                        ->orWhere('code', 'like', $term))
                        ->orWhereHas('projectComponent', fn ($componentQuery) => $componentQuery
                            ->where('name', 'like', $term));
                });
            })
            ->orderByDesc('reporting_year')
            ->orderByDesc('reporting_quarter')
            ->paginate(12)
            ->withQueryString();

        $assignments = $this->availableAssignments($member);

        return view('think-tank.me-performance-reports.index', [
            'member' => $member,
            'reports' => $reports,
            'summary' => $summary,
            'assignments' => $assignments,
            'periodTypes' => MePerformanceReport::REPORTING_PERIOD_TYPES,
            'periodLabels' => MePerformanceReport::PERIOD_LABELS,
            'statusFilter' => $status,
            'search' => $search,
            'canAuthor' => $this->canAuthor($request),
            'portalRouteParams' => $this->portalRouteParams($request, $member),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $this->assertCanAuthor($request);
        $this->normalizeLegacyPeriodInput($request);
        $validated = $request->validate([
            'assignment_id' => ['required', 'uuid', Rule::exists('me_data_collection_assignments', 'id')],
            'reporting_period_type' => ['required', Rule::in(array_keys(MePerformanceReport::REPORTING_PERIOD_TYPES))],
            'reporting_period_label' => ['required', 'string', 'max:40'],
            'reporting_year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $this->assertValidPeriodSelection($validated['reporting_period_type'], $validated['reporting_period_label']);

        $assignment = MeDataCollectionAssignment::query()
            ->whereKey($validated['assignment_id'])
            ->where('think_tank_member_id', $member->id)
            ->with([
                'collection.form.projectComponent:id,project_id,name,governance_node_id',
                'collection.form.indicators.frequency',
                'collection.form.indicators.setupTarget',
                'collection.form.indicators.targets',
            ])
            ->firstOrFail();
        $form = $assignment->collection?->form;
        if (! $form || $form->status !== MeDataEntryForm::STATUS_PUBLISHED) {
            throw ValidationException::withMessages([
                'assignment_id' => 'This assigned reporting form is not available for performance reporting.',
            ]);
        }

        $report = $this->createReportFor(
            $form,
            (int) $validated['reporting_year'],
            (string) $validated['reporting_period_type'],
            (string) $validated['reporting_period_label'],
            $request,
            $member,
            $assignment
        );

        return redirect()
            ->route(
                'think-tank.performance-reports.edit',
                array_merge(['report' => $report], $this->portalRouteParams($request, $member))
            )
            ->with('success', 'Draft report created. Complete every section before submitting it.');
    }

    public function edit(Request $request, MePerformanceReport $report): View
    {
        $member = $this->member($request);
        $this->assertOwnedReport($report, $member);
        $internalView = parent::edit($request, $report);
        $data = $internalView->getData();
        $data['member'] = $member;
        $data['canManage'] = $this->canAuthor($request) && $report->isEditable();
        $data['portalRouteParams'] = $this->portalRouteParams($request, $member);

        return view('think-tank.me-performance-reports.edit', $data);
    }

    public function update(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $member = $this->member($request);
        $this->assertOwnedReport($report, $member);
        $this->assertCanAuthor($request);
        parent::update($request, $report);

        return redirect()
            ->route(
                'think-tank.performance-reports.edit',
                array_merge(['report' => $report], $this->portalRouteParams($request, $member))
            )
            ->with('success', 'Draft saved and indicator progress recalculated.');
    }

    public function submit(Request $request, MePerformanceReport $report): RedirectResponse
    {
        $member = $this->member($request);
        $this->assertOwnedReport($report, $member);
        $this->assertCanAuthor($request);
        parent::submit($request, $report);

        return redirect()
            ->route(
                'think-tank.performance-reports.edit',
                array_merge(['report' => $report], $this->portalRouteParams($request, $member))
            )
            ->with('success', 'Report submitted to the Secretariat/M&E Officer for review.');
    }

    public function downloadDocument(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportDocument $document
    ) {
        $member = $this->member($request);
        $this->assertOwnedReport($report, $member);

        return parent::downloadDocument($request, $report, $document);
    }

    public function destroyDocument(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportDocument $document
    ): RedirectResponse {
        $member = $this->member($request);
        $this->assertOwnedReport($report, $member);
        $this->assertCanAuthor($request);

        return parent::destroyDocument($request, $report, $document);
    }

    public function replaceDocument(
        Request $request,
        MePerformanceReport $report,
        MePerformanceReportDocument $document
    ): RedirectResponse {
        $member = $this->member($request);
        $this->assertOwnedReport($report, $member);
        $this->assertCanAuthor($request);

        return parent::replaceDocument($request, $report, $document);
    }

    private function availableAssignments(ConsortiumThinkTank $member)
    {
        return MeDataCollectionAssignment::query()
            ->where('think_tank_member_id', $member->id)
            ->whereHas('collection.form', fn ($query) => $query
                ->where('status', MeDataEntryForm::STATUS_PUBLISHED)
                ->whereNotNull('project_component_id')
                ->whereHas('indicators'))
            ->with([
                'collection.form:id,portfolio_id,project_component_id,code,title,status',
                'collection.form.portfolio:id,name',
                'collection.form.projectComponent:id,project_id,name,governance_node_id',
                'collection.form.projectComponent.governanceNode:id,name,code',
                'collection.form.indicators:id,indicator_code,name,frequency_of_reporting_id',
                'collection.form.indicators.frequency:id,name,code,interval_unit,interval_value,frequency_in_days',
                'collection.reportingPeriod:id,label,period_start,period_end',
            ])
            ->latest('assigned_at')
            ->get()
            ->unique(fn (MeDataCollectionAssignment $assignment): string => (string) $assignment->collection?->form_id)
            ->values();
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();
        abort_unless($user, 403);
        abort_if($user->hasActiveLoginBlock() || (bool) $user->is_blacklisted, 403, 'This account is not permitted to manage reports.');

        if ($user->isSuperAdmin() || $user->isAdmin()) {
            $memberId = trim((string) $request->query('think_tank_member_id'));
            $member = ConsortiumThinkTank::query()
                ->where('status', 'active')
                ->when($memberId !== '', fn ($query) => $query->whereKey($memberId))
                ->orderBy('name')
                ->first();
            abort_unless($member, 404, 'No active think tank was found.');

            return $member;
        }

        abort_unless($user->isThinkTankUser(), 403);
        $member = $user->resolvedThinkTankMembership();
        abort_unless($member && $member->status === 'active', 403, 'This account is not linked to an active think tank or implementing partner.');

        return $member;
    }

    private function assertOwnedReport(MePerformanceReport $report, ConsortiumThinkTank $member): void
    {
        abort_unless(
            (string) $report->think_tank_member_id === (string) $member->id,
            403,
            'This report belongs to another organization.'
        );
    }

    private function assertCanAuthor(Request $request): void
    {
        abort_unless($this->canAuthor($request), 403, 'Your assigned role cannot create, edit, or submit performance reports.');
    }

    private function canAuthor(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user
            && $user->isThinkTankUser()
            && $user->canAccessThinkTankArea('me')
            && $user->can('think_tank.me.reports.manage')
            && $user->can('think_tank.me.reports.submit'));
    }

    private function portalRouteParams(Request $request, ConsortiumThinkTank $member): array
    {
        $user = $request->user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    }
}
