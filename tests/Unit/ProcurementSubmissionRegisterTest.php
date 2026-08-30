<?php

function procurementSubmissionRegisterSources(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'controller' => file_get_contents(
            $root.'/app/Http/Controllers/Procurement/ProcurementSubmissionController.php'
        ),
        'view' => file_get_contents(
            $root.'/resources/views/procurement/procuresubmissions/index.blade.php'
        ),
        'show_view' => file_get_contents(
            $root.'/resources/views/procurement/procuresubmissions/show.blade.php'
        ),
        'screening_report_view' => file_get_contents(
            $root.'/resources/views/procurement/procuresubmissions/screening-report.blade.php'
        ),
        'routes' => file_get_contents($root.'/routes/web.php'),
    ];
}

it('builds scoped procurement aggregates and resolves one query-parameter drilldown', function () {
    $controller = procurementSubmissionRegisterSources()['controller'];

    expect($controller)
        ->toContain('$scopedNodeIds = $this->scopedNodeIds();')
        ->toContain("abort(403, 'You do not have access to submissions.')")
        ->toContain('$procurementGroups = $this->procurementGroupsQuery($scopedNodeIds)->get();')
        ->toContain('$this->applyProcurementNodeScope(Procurement::query(), $scopedNodeIds)')
        ->toContain("->whereHas('submissions')")
        ->toContain("'submissions as screening_records_count'")
        ->toContain("'submissions as screening_success_count'")
        ->toContain("'submissions as screening_failed_count'")
        ->toContain("'submissions as fit_count'")
        ->toContain("'submissions as not_fit_count'")
        ->toContain("->withMax('submissions as latest_submission_at', 'submitted_at')")
        ->toContain("'submissions' => (int) \$procurementGroups->sum('submissions_count')")
        ->toContain("'needs_attention' => (int) \$procurementGroups->sum(")
        ->toContain("if (\$request->filled('procurement_id'))")
        ->toContain("(string) \$request->query('procurement_id')")
        ->toContain('abort_unless(Str::isUuid($procurementId), 404)')
        ->toContain('->whereKey($procurementId)')
        ->toContain('$this->submissionsQuery($scopedNodeIds)')
        ->toContain("->where('procurement_id', \$selectedProcurement->id)")
        ->toContain("'selectedProcurement' => \$selectedProcurement")
        ->toContain("'statusDistribution' => \$statusDistribution");
});

it('limits bulk screening to the selected scoped procurement while retaining accessible-all mode', function () {
    $sources = procurementSubmissionRegisterSources();
    $controller = $sources['controller'];
    $view = $sources['view'];

    expect($controller)
        ->toContain("\$selectedProcurement = \$request->filled('procurement_id')")
        ->toContain("\$this->resolveScopedProcurement((string) \$request->input('procurement_id'), \$scopedNodeIds)")
        ->toContain('$this->applySubmissionScope(')
        ->toContain('FormSubmission::with([\'values\', \'submitter\'])')
        ->toContain('$selectedProcurement,')
        ->toContain("fn (\$query) => \$query->where('procurement_id', \$selectedProcurement->id)")
        ->toContain("'No submissions were available for this procurement.'")
        ->toContain("\$screeningService->screenSubmissions(\$submissions, \$request->user(), 'bulk')")
        ->and($view)
        ->toContain('@if (!$selectedProcurement)')
        ->toContain('Run 3PAP checks for all applicants')
        ->toContain('<input type="hidden" name="procurement_id" value="{{ $selectedProcurement->id }}">')
        ->toContain('Run 3PAP checks for this procurement')
        ->and(substr_count($view, "route('procurement.submissions.screen-all')"))->toBe(2);
});

it('renders filterable procurement cards and preserves applicant row actions', function () {
    $sources = procurementSubmissionRegisterSources();
    $view = $sources['view'];
    $routes = $sources['routes'];

    expect($view)
        ->toContain('id="procurementCardSearch"')
        ->toContain('id="procurementStatusFilter"')
        ->toContain('id="procurementAttentionFilter"')
        ->toContain('id="procurementSort"')
        ->toContain('id="clearProcurementFilters"')
        ->toContain('data-procurement-card')
        ->toContain('data-search=')
        ->toContain('data-status=')
        ->toContain('data-attention=')
        ->toContain('data-submissions=')
        ->toContain('data-latest=')
        ->toContain("route('procurement.submissions.index', ['procurement_id' => \$procurement->id])")
        ->toContain('aria-current="page"')
        ->toContain('const applyFilters = function ()')
        ->toContain('card.dataset.search.includes(query)')
        ->toContain("sort.value === 'submissions'")
        ->toContain("sort.value === 'title'")
        ->toContain("firstWhere('field_key', 'official_name')")
        ->toContain("firstWhere('field_key', 'official_email')")
        ->toContain("route('procurement.submissions.show', \$submission)")
        ->toContain("route('procurement.submissions.screening.report', \$submission)")
        ->toContain('<i class="feather-shield me-1" aria-hidden="true"></i> Report')
        ->and($routes)
        ->toContain("Route::prefix('procurement/submissions')")
        ->toContain("->middleware(['auth', 'not.funding.partner'])")
        ->toContain("->name('procurement.submissions.index')")
        ->toContain("->name('procurement.submissions.screen-all')")
        ->toContain("->name('procurement.submissions.show')")
        ->toContain("->name('procurement.submissions.screening.report')");
});

it('presents 3PAP sanctions screening as human-reviewed and keeps report navigation read only', function () {
    $sources = procurementSubmissionRegisterSources();
    $index = $sources['view'];
    $show = $sources['show_view'];
    $report = $sources['screening_report_view'];
    $screeningViews = $index."\n".$show."\n".$report;

    expect($index)
        ->toContain('3PAP Sanctions Screening')
        ->toContain("route('procurement.submissions.screening.report', \$submission)")
        ->and($show)
        ->toContain('3PAP Sanctions Screening')
        ->toContain('Open 3PAP Screening Report')
        ->toContain("route('procurement.submissions.screening.report', \$submission)")
        ->and($report)
        ->toContain('3PAP Sanctions Screening Report')
        ->toContain('<form method="POST" action="{{ route(\'procurement.submissions.screen\', $submission) }}">')
        ->toContain("{{ \$screening ? 'Re-run 3PAP Screening' : 'Run 3PAP Screening' }}")
        ->and($screeningViews)
        ->not->toContain("'run' =>")
        ->not->toContain('run=1')
        ->and(substr_count($screeningViews, '3PAP results support human review and do not automatically determine applicant eligibility.'))->toBe(3);
});

it('only makes validated http and https screening sources clickable', function () {
    $sources = procurementSubmissionRegisterSources();
    $sourceViews = $sources['show_view']."\n".$sources['screening_report_view'];

    expect(substr_count($sourceViews, 'FILTER_VALIDATE_URL'))->toBe(2)
        ->and(substr_count($sourceViews, "in_array(\$sourceScheme, ['http', 'https'], true)"))->toBe(2)
        ->and(substr_count($sourceViews, 'rel="noopener noreferrer"'))->toBe(2)
        ->and($sourceViews)
        ->not->toContain('href="{{ $match[\'source_url\'] }}"')
        ->not->toContain('href="{{ $sourceUrl }}" target="_blank" rel="noopener">');
});

it('uses one client-side applicant paginator and no competing server paginator', function () {
    $sources = procurementSubmissionRegisterSources();
    $controller = $sources['controller'];
    $view = $sources['view'];

    expect($controller)
        ->not->toContain('->paginate(')
        ->not->toContain('->simplePaginate(')
        ->and($view)
        ->toContain("'pageLength' => 25")
        ->not->toContain('$submissions->links(')
        ->not->toContain('$procurementGroups->links(')
        ->and(substr_count($view, '<x-data-table'))->toBe(1);
});
