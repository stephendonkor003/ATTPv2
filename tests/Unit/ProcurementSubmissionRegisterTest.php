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
        ->toContain('Check all accessible applicants')
        ->toContain('<input type="hidden" name="procurement_id" value="{{ $selectedProcurement->id }}">')
        ->toContain('Check this procurement')
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
        ->toContain("route('procurement.submissions.screening.report', ['submission' => \$submission")
        ->toContain("{{ \$screening ? 'Report' : 'Check' }}")
        ->and($routes)
        ->toContain("Route::prefix('procurement/submissions')")
        ->toContain("->middleware(['auth', 'not.funding.partner'])")
        ->toContain("->name('procurement.submissions.index')")
        ->toContain("->name('procurement.submissions.screen-all')")
        ->toContain("->name('procurement.submissions.show')")
        ->toContain("->name('procurement.submissions.screening.report')");
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
