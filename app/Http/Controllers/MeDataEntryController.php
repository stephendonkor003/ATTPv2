<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Activity;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MeDataCollection;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeDataEntryFormField;
use App\Models\MeDataEntryFormSection;
use App\Models\MeDataSubmission;
use App\Models\MePerformanceReport;
use App\Models\MeReportingPeriod;
use App\Models\Program;
use App\Models\Project;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\User;
use App\Services\MeReportingReadinessService;
use App\Services\MeReportingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeDataEntryController extends Controller
{
    use ScopesAssignedPortfolios;

    private const TABS = ['collections', 'forms', 'periods', 'reports', 'submissions'];

    private const DUE_SOON_DAYS = 7;

    private const DEFAULT_SECTION_DESCRIPTION = 'Complete the questions in this section using the most accurate information available. Review your answers before continuing to the next section.';

    private const FIELD_TYPES = [
        'integer',
        'number',
        'percentage',
        'currency',
        'text',
        'textarea',
        'email',
        'phone',
        'url',
        'date',
        'time',
        'datetime',
        'month',
        'year',
        'select',
        'radio',
        'multiselect',
        'checkbox',
        'yes_no',
        'rating',
        'scale',
        'file',
        'image',
    ];

    private const MAPPABLE_FIELD_TYPES = ['integer', 'number', 'percentage', 'currency'];

    private const NUMERIC_VALIDATION_TYPES = ['integer', 'number', 'percentage', 'currency', 'rating', 'scale'];

    private const TEXT_VALIDATION_TYPES = ['text', 'textarea', 'email', 'phone', 'url'];

    private const TWO_OPTION_FIELD_TYPES = ['select', 'radio'];

    private const ONE_OPTION_FIELD_TYPES = ['multiselect', 'checkbox'];

    private const UPLOAD_FIELD_TYPES = ['file', 'image'];

    private const DEFAULT_FILE_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];

    private const DEFAULT_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    private const BLOCKED_FILE_EXTENSIONS = [
        'apk',
        'app',
        'asp',
        'aspx',
        'bat',
        'cgi',
        'cmd',
        'com',
        'cpl',
        'dll',
        'exe',
        'html',
        'htm',
        'hta',
        'jar',
        'js',
        'jsp',
        'lnk',
        'msi',
        'phar',
        'php',
        'phtml',
        'pl',
        'ps1',
        'py',
        'rb',
        'reg',
        'scr',
        'sh',
        'shtml',
        'svg',
        'svgz',
        'sys',
        'vb',
        'vbs',
        'wsf',
        'xhtml',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.data_entry.view|me.data_entry.manage|me.configuration.view|me.configuration.manage')->only('index');
        $this->middleware('permission:me.data_entry.manage|me.configuration.manage')->except('index');
    }

    public function index(Request $request, MeReportingReadinessService $readinessService): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true)
            ? (string) $request->query('tab')
            : 'collections';
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));
        $searchTerm = '%'.addcslashes(Str::lower($search), '%_\\').'%';
        $periodLifecycleFilter = match ($statusFilter) {
            MeReportingPeriod::STATUS_DRAFT => MeReportingPeriod::LIFECYCLE_PLANNED,
            MeReportingPeriod::STATUS_ACTIVE => MeReportingPeriod::LIFECYCLE_OPEN,
            default => $statusFilter,
        };

        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name', 'status']);
        $reportingReadiness = $readinessService->assess($portfolios->pluck('id'));

        $portfolioId = trim((string) $request->query('portfolio_id', '')) ?: null;
        if ($portfolioId && ! $portfolios->contains(fn (Sector $portfolio): bool => (string) $portfolio->id === $portfolioId)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }

        $summaryCollectionQuery = $this->scopedCollectionQuery($request, $portfolioId);
        $summary = [
            'open' => (clone $summaryCollectionQuery)
                ->where('status', MeDataCollection::STATUS_OPEN)
                ->count(),
            'due_soon' => (clone $summaryCollectionQuery)
                ->where('status', MeDataCollection::STATUS_OPEN)
                ->whereBetween('due_at', [now(), now()->addDays(self::DUE_SOON_DAYS)])
                ->count(),
            'submitted' => $this->scopedSubmissionQuery($request, $portfolioId)
                ->where(function ($query): void {
                    $statuses = [
                        MeDataSubmission::STATUS_SUBMITTED,
                        MeDataSubmission::STATUS_RESUBMITTED,
                        MeDataSubmission::STATUS_UNDER_REVIEW,
                        MeDataSubmission::STATUS_VALIDATED,
                        MeDataSubmission::STATUS_VERIFIED,
                        MeDataSubmission::STATUS_APPROVED,
                    ];
                    $query->whereIn('workflow_status', $statuses)
                        ->orWhere(function ($legacyQuery) use ($statuses): void {
                            $legacyQuery->whereNull('workflow_status')->whereIn('status', $statuses);
                        });
                })
                ->count(),
        ];

        $forms = null;
        $periods = null;
        $collections = null;
        $reports = null;
        $submissions = null;
        $editingForm = null;
        $editingPeriod = null;
        $editingCollection = null;
        $editingFormHasSubmissions = false;

        if ($tab === 'forms') {
            $forms = $this->scopedFormQuery($request, $portfolioId)
                ->with([
                    'portfolio:id,name',
                    'indicator:id,indicator_code,name,unit_id',
                    'indicator.unit:id,name,symbol',
                    'projectComponent:id,project_id,name,governance_node_id',
                    'projectComponent.governanceNode:id,name,code',
                    'responsiblePerson:id,name,email',
                ])
                ->withCount([
                    'fields',
                    'indicators',
                    'collections',
                    'performanceReports',
                    'collections as submitted_collections_count' => fn ($query) => $query->whereHas('submissions'),
                ])
                ->when($search !== '', function ($query) use ($searchTerm): void {
                    $query->where(function ($searchQuery) use ($searchTerm): void {
                        $searchQuery->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(description) LIKE ?', [$searchTerm])
                            ->orWhereHas('indicator', function ($indicatorQuery) use ($searchTerm): void {
                                $indicatorQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                                    ->orWhereRaw('LOWER(indicator_code) LIKE ?', [$searchTerm]);
                            });
                    });
                })
                ->when(
                    in_array($statusFilter, [
                        MeDataEntryForm::STATUS_DRAFT,
                        MeDataEntryForm::STATUS_PUBLISHED,
                        MeDataEntryForm::STATUS_ARCHIVED,
                    ], true),
                    fn ($query) => $query->where('status', $statusFilter)
                )
                ->latest()
                ->paginate(12, ['*'], 'forms_page')
                ->withQueryString();

            if ($request->filled('edit_form')) {
                $editingForm = $this->scopedFormQuery($request)
                    ->with([
                        'portfolio:id,name',
                        'indicator:id,indicator_code,name,unit_id',
                        'indicator.unit:id,name,symbol',
                        'projectComponent:id,project_id,name,governance_node_id',
                        'projectComponent.governanceNode:id,name,code',
                        'responsiblePerson:id,name,email',
                        'sections' => fn ($query) => $query->orderBy('sort_order')->orderBy('created_at'),
                        'sections.fields' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                        'fields' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
                        'fields.formSection',
                    ])
                    ->findOrFail((string) $request->query('edit_form'));
                $editingFormHasSubmissions = $editingForm->collections()->whereHas('submissions')->exists()
                    || $editingForm->performanceReports()->exists();
            }
        } elseif ($tab === 'periods') {
            $periods = $this->scopedPeriodQuery($request, $portfolioId)
                ->with('portfolio:id,name')
                ->withCount('collections')
                ->when($search !== '', function ($query) use ($searchTerm): void {
                    $query->where(function ($searchQuery) use ($searchTerm): void {
                        $searchQuery->whereRaw('LOWER(label) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$searchTerm]);
                    });
                })
                ->when(
                    in_array($periodLifecycleFilter, [
                        MeReportingPeriod::LIFECYCLE_PLANNED,
                        MeReportingPeriod::LIFECYCLE_OPEN,
                        MeReportingPeriod::LIFECYCLE_CLOSED,
                        MeReportingPeriod::LIFECYCLE_UNDER_REVIEW,
                        MeReportingPeriod::LIFECYCLE_COMPLETED,
                    ], true),
                    fn ($query) => $query->where('lifecycle_status', $periodLifecycleFilter)
                )
                ->orderByDesc('period_start')
                ->paginate(12, ['*'], 'periods_page')
                ->withQueryString();

            if ($request->filled('edit_period')) {
                $editingPeriod = $this->scopedPeriodQuery($request)
                    ->with('portfolio:id,name')
                    ->findOrFail((string) $request->query('edit_period'));
            }
        } elseif ($tab === 'reports') {
            $reports = $this->scopedPerformanceReportQuery($request, $portfolioId)
                ->with([
                    'form:id,code,title',
                    'portfolio:id,name',
                    'projectComponent:id,project_id,name',
                    'responsibleDirectorate:id,name,code',
                    'thinkTank:id,name,role,country',
                    'createdBy:id,name',
                ])
                ->withCount(['indicatorResults', 'documents'])
                ->when($search !== '', function ($query) use ($searchTerm): void {
                    $query->where(function ($searchQuery) use ($searchTerm): void {
                        $searchQuery->whereHas('form', fn ($formQuery) => $formQuery
                            ->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$searchTerm]))
                            ->orWhereHas('projectComponent', fn ($componentQuery) => $componentQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(project_id) LIKE ?', [$searchTerm]))
                            ->orWhereHas('responsibleDirectorate', fn ($directorateQuery) => $directorateQuery
                                ->whereRaw('LOWER(name) LIKE ?', [$searchTerm]));
                    });
                })
                ->when(
                    in_array($statusFilter, [
                        MePerformanceReport::STATUS_DRAFT,
                        MePerformanceReport::STATUS_SUBMITTED,
                        MePerformanceReport::STATUS_REVIEWED,
                        MePerformanceReport::STATUS_VERIFIED,
                        MePerformanceReport::STATUS_APPROVED,
                        MePerformanceReport::STATUS_ARCHIVED,
                    ], true),
                    fn ($query) => $query->where('status', $statusFilter)
                )
                ->orderByDesc('reporting_year')
                ->orderByDesc('reporting_quarter')
                ->paginate(15, ['*'], 'reports_page')
                ->withQueryString();
        } elseif ($tab === 'submissions') {
            $submissions = $this->scopedSubmissionQuery($request, $portfolioId)
                ->with([
                    'assignment.thinkTank:id,name,email,country,status',
                    'assignment.collection.form:id,portfolio_id,indicator_id,code,title',
                    'assignment.collection.form.indicator:id,indicator_code,name,unit_id',
                    'assignment.collection.form.indicator.unit:id,name,symbol',
                    'assignment.collection.form.portfolio:id,name',
                    'assignment.collection.reportingPeriod:id,portfolio_id,code,label,period_start,period_end,status',
                    'submittedBy:id,name,email,think_tank_member_id,think_tank_access_level',
                    'reviewedBy:id,name,email',
                ])
                ->withCount('answers')
                ->when(
                    $search !== '',
                    fn ($query) => $this->applySubmissionRegisterSearch($query, $search)
                )
                ->when(
                    in_array($statusFilter, [
                        MeDataSubmission::STATUS_DRAFT,
                        MeDataSubmission::STATUS_SUBMITTED,
                        MeDataSubmission::STATUS_RESUBMITTED,
                        MeDataSubmission::STATUS_UNDER_REVIEW,
                        MeDataSubmission::STATUS_RETURNED,
                        MeDataSubmission::STATUS_VALIDATED,
                        MeDataSubmission::STATUS_VERIFIED,
                        MeDataSubmission::STATUS_APPROVED,
                        MeDataSubmission::STATUS_REJECTED,
                    ], true),
                    fn ($query) => $query->where(function ($statusQuery) use ($statusFilter): void {
                        $statusQuery->where('workflow_status', $statusFilter)
                            ->orWhere(function ($legacyQuery) use ($statusFilter): void {
                                $legacyQuery->whereNull('workflow_status')->where('status', $statusFilter);
                            });
                    })
                )
                ->latest('updated_at')
                ->paginate(15, ['*'], 'submissions_page')
                ->withQueryString();
        } else {
            $collections = $this->scopedCollectionQuery($request, $portfolioId)
                ->with([
                    'form:id,portfolio_id,indicator_id,code,title,status',
                    'form.indicator:id,indicator_code,name,unit_id',
                    'form.indicator.unit:id,name,symbol',
                    'form.portfolio:id,name',
                    'reportingPeriod:id,portfolio_id,code,label,period_start,period_end,status',
                    'assignments.thinkTank:id,name,country,status',
                ])
                ->withCount(['assignments', 'submissions'])
                ->when($search !== '', function ($query) use ($searchTerm): void {
                    $query->where(function ($searchQuery) use ($searchTerm): void {
                        $searchQuery->whereHas('form', function ($formQuery) use ($searchTerm): void {
                            $formQuery->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(code) LIKE ?', [$searchTerm]);
                        })->orWhereHas('reportingPeriod', function ($periodQuery) use ($searchTerm): void {
                            $periodQuery->whereRaw('LOWER(label) LIKE ?', [$searchTerm])
                                ->orWhereRaw('LOWER(code) LIKE ?', [$searchTerm]);
                        })->orWhereHas('assignments.thinkTank', fn ($memberQuery) => $memberQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$searchTerm]));
                    });
                })
                ->when(
                    in_array($statusFilter, [
                        MeDataCollection::STATUS_DRAFT,
                        MeDataCollection::STATUS_OPEN,
                        MeDataCollection::STATUS_CLOSED,
                    ], true),
                    fn ($query) => $query->where('status', $statusFilter)
                )
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->orderBy('due_at')
                ->paginate(12, ['*'], 'collections_page')
                ->withQueryString();

            if ($request->filled('edit_collection')) {
                $editingCollection = $this->scopedCollectionQuery($request)
                    ->with([
                        'form:id,portfolio_id,indicator_id,code,title,status',
                        'form.indicator:id,indicator_code,name,unit_id',
                        'form.indicator.unit:id,name,symbol',
                        'form.portfolio:id,name',
                        'reportingPeriod:id,portfolio_id,code,label,status',
                        'assignments.thinkTank:id,name,country,status',
                    ])
                    ->findOrFail((string) $request->query('edit_collection'));
            }
        }

        $showFormBuilder = $tab === 'forms'
            && ($editingForm || $request->query('create') === 'form');
        $showPeriodForm = $tab === 'periods'
            && ($editingPeriod || $request->query('create') === 'period');
        $showCollectionForm = $tab === 'collections'
            && ($editingCollection || $request->query('create') === 'collection');

        $responsibleUsers = collect();
        $indicatorOptions = collect();
        $projectComponents = collect();
        if ($showFormBuilder) {
            $responsibleUsers = User::query()
                ->whereNotNull('name')
                ->where(function ($query): void {
                    $query->whereNull('user_type')
                        ->orWhereNotIn('user_type', ['funding_partner', 'vendor', 'think_tank']);
                })
                ->orderBy('name')
                ->get(['id', 'name', 'email']);
            $indicatorOptions = $this->indicatorOptionsForPortfolios($portfolios);
            $projectComponents = $this->componentOptionsForPortfolios($portfolios);
        }

        $publishedForms = collect();
        $activePeriods = collect();
        $availableThinkTanks = collect();
        if ($showCollectionForm) {
            $publishedForms = $this->scopedFormQuery($request)
                ->where('status', MeDataEntryForm::STATUS_PUBLISHED)
                ->with([
                    'portfolio:id,name',
                    'indicator:id,indicator_code,name,unit_id',
                    'indicator.unit:id,name,symbol',
                ])
                ->orderBy('title')
                ->get(['id', 'portfolio_id', 'indicator_id', 'code', 'title']);
            $activePeriods = $this->scopedPeriodQuery($request)
                ->where('status', MeReportingPeriod::STATUS_ACTIVE)
                ->where('lifecycle_status', MeReportingPeriod::LIFECYCLE_OPEN)
                ->with('portfolio:id,name')
                ->orderByDesc('period_start')
                ->get(['id', 'portfolio_id', 'code', 'label', 'period_start', 'period_end']);

            $existingMemberIds = $editingCollection
                ? $editingCollection->assignments->pluck('think_tank_member_id')->filter()->all()
                : [];
            $availableThinkTanks = ConsortiumThinkTank::query()
                ->with('consortium:id,name')
                ->where(function ($query) use ($existingMemberIds): void {
                    $query->where('status', 'active');
                    if ($existingMemberIds !== []) {
                        $query->orWhereIn('id', $existingMemberIds);
                    }
                })
                ->orderBy('name')
                ->get(['id', 'consortium_id', 'name', 'country', 'status']);
        }

        return view('me.data-entry.index', compact(
            'tab',
            'search',
            'statusFilter',
            'portfolioId',
            'portfolios',
            'summary',
            'forms',
            'periods',
            'collections',
            'reports',
            'submissions',
            'editingForm',
            'editingPeriod',
            'editingCollection',
            'editingFormHasSubmissions',
            'showFormBuilder',
            'showPeriodForm',
            'showCollectionForm',
            'responsibleUsers',
            'indicatorOptions',
            'projectComponents',
            'publishedForms',
            'activePeriods',
            'availableThinkTanks',
            'reportingReadiness'
        ));
    }

    public function storeForm(Request $request): RedirectResponse
    {
        [$validated, $sections, $fields] = $this->validatedFormPayload($request);

        DB::transaction(function () use ($validated, $sections, $fields, $request): void {
            $form = MeDataEntryForm::query()->create([
                'portfolio_id' => $validated['portfolio_id'],
                'project_component_id' => $validated['project_component_id'],
                'indicator_id' => $validated['indicator_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'responsible_user_id' => $validated['responsible_user_id'],
                'version' => 1,
                'status' => MeDataEntryForm::STATUS_DRAFT,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->syncFormStructure($form, $sections, $fields);
            $this->syncFormIndicators($form, (string) $validated['indicator_id'], $fields);
        });

        return $this->redirectToTab('forms', 'Collection form created as a draft.');
    }

    public function updateForm(Request $request, MeDataEntryForm $form): RedirectResponse
    {
        $this->assertFormInScope($request, $form);
        if ($form->status === MeDataEntryForm::STATUS_ARCHIVED) {
            throw ValidationException::withMessages([
                'form' => 'Archived collection forms cannot be edited.',
            ]);
        }

        [$validated, $sections, $fields] = $this->validatedFormPayload($request, $form);
        $form->load(['sections.fields', 'fields.formSection']);

        $structureChanged = $this->formStructureFingerprint(
            (string) $form->portfolio_id,
            $form->project_component_id ? (string) $form->project_component_id : null,
            $form->indicator_id ? (string) $form->indicator_id : null,
            (string) $form->code,
            $this->existingSectionStructure($form),
            $this->existingFieldStructure($form)
        ) !== $this->formStructureFingerprint(
            (string) $validated['portfolio_id'],
            (string) $validated['project_component_id'],
            (string) $validated['indicator_id'],
            (string) $form->code,
            $sections,
            $fields
        );
        $hasSubmissions = $form->collections()->whereHas('submissions')->exists()
            || $form->performanceReports()->exists();

        if ($form->status === MeDataEntryForm::STATUS_PUBLISHED && $hasSubmissions && $structureChanged) {
            throw ValidationException::withMessages([
                'fields' => 'This published form already has submissions or performance reports. Its portfolio, project component, indicator links, sections, and question structure are locked; you may still update the title, description, instructions, or responsible person.',
            ]);
        }

        DB::transaction(function () use ($validated, $sections, $fields, $form, $request, $structureChanged): void {
            $form->update([
                'portfolio_id' => $validated['portfolio_id'],
                'project_component_id' => $validated['project_component_id'],
                'indicator_id' => $validated['indicator_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'instructions' => $validated['instructions'] ?? null,
                'responsible_user_id' => $validated['responsible_user_id'],
                'version' => $structureChanged ? max(1, (int) $form->version) + 1 : max(1, (int) $form->version),
                'updated_by' => $request->user()->id,
            ]);

            if ($structureChanged) {
                $this->syncFormStructure($form, $sections, $fields);
                $this->syncFormIndicators($form, (string) $validated['indicator_id'], $fields);
            }
        });

        return $this->redirectToTab('forms', 'Collection form updated.');
    }

    public function publishForm(Request $request, MeDataEntryForm $form): RedirectResponse
    {
        $this->assertFormInScope($request, $form);
        if ($form->status === MeDataEntryForm::STATUS_ARCHIVED) {
            throw ValidationException::withMessages(['form' => 'Archived forms cannot be published.']);
        }
        if (! $form->sections()->exists() || ! $form->fields()->exists()) {
            throw ValidationException::withMessages(['fields' => 'Add at least one section with one question before publishing this form.']);
        }
        if (! $form->indicator_id) {
            throw ValidationException::withMessages(['indicator_id' => 'Link this template to a performance indicator before publishing it.']);
        }
        if (! $form->project_component_id) {
            throw ValidationException::withMessages([
                'project_component_id' => 'Link this template to a project component before publishing it.',
            ]);
        }
        $this->validateProjectComponent(
            (string) $form->portfolio_id,
            (string) $form->project_component_id
        );
        $this->validateTemplateIndicator(
            (string) $form->portfolio_id,
            (string) $form->project_component_id,
            (string) $form->indicator_id
        );
        if ($form->sections()->whereDoesntHave('fields')->exists() || $form->fields()->whereNull('section_id')->exists()) {
            throw ValidationException::withMessages(['fields' => 'Every section must contain at least one question before this form can be published.']);
        }

        DB::transaction(function () use ($form, $request): void {
            $form->update([
                'status' => MeDataEntryForm::STATUS_PUBLISHED,
                'updated_by' => $request->user()->id,
            ]);
        });

        return $this->redirectToTab('forms', 'Collection form published and ready for a collection.');
    }

    public function archiveForm(Request $request, MeDataEntryForm $form): RedirectResponse
    {
        $this->assertFormInScope($request, $form);
        if ($form->collections()->whereIn('status', [
            MeDataCollection::STATUS_DRAFT,
            MeDataCollection::STATUS_OPEN,
        ])->exists()) {
            throw ValidationException::withMessages([
                'form' => 'Close or remove the form from its active collections before archiving it.',
            ]);
        }

        DB::transaction(function () use ($form, $request): void {
            $form->update([
                'status' => MeDataEntryForm::STATUS_ARCHIVED,
                'updated_by' => $request->user()->id,
            ]);
        });

        return $this->redirectToTab('forms', 'Collection form archived.');
    }

    public function storePeriod(Request $request): RedirectResponse
    {
        $validated = $this->validatedPeriodPayload($request);

        $period = DB::transaction(function () use ($validated, $request): MeReportingPeriod {
            return MeReportingPeriod::query()->create([
                ...$validated,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });
        if ($period->lifecycle_status === MeReportingPeriod::LIFECYCLE_OPEN) {
            app(MeReportingNotificationService::class)->periodOpened($period);
        }

        return $this->redirectToTab('periods', 'Reporting period created.');
    }

    public function updatePeriod(Request $request, MeReportingPeriod $period): RedirectResponse
    {
        $this->assertPeriodInScope($request, $period);
        $validated = $this->validatedPeriodPayload($request, $period);
        $wasOpen = $period->lifecycle_status === MeReportingPeriod::LIFECYCLE_OPEN;

        if ((string) $validated['portfolio_id'] !== (string) $period->portfolio_id && $period->collections()->exists()) {
            throw ValidationException::withMessages([
                'portfolio_id' => 'A reporting period linked to a collection cannot move to another portfolio.',
            ]);
        }
        if ($validated['status'] === MeReportingPeriod::STATUS_CLOSED
            && $period->collections()->where('status', '!=', MeDataCollection::STATUS_CLOSED)->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Close every collection in this period before closing the reporting period.',
            ]);
        }

        DB::transaction(function () use ($validated, $period, $request): void {
            $period->update([
                ...$validated,
                'updated_by' => $request->user()->id,
            ]);
        });
        if (! $wasOpen && $period->lifecycle_status === MeReportingPeriod::LIFECYCLE_OPEN) {
            app(MeReportingNotificationService::class)->periodOpened($period);
        }

        return $this->redirectToTab('periods', 'Reporting period updated.');
    }

    public function storeCollection(Request $request): RedirectResponse
    {
        [$validated, $form, $period, $memberIds] = $this->validatedCollectionPayload($request);

        DB::transaction(function () use ($validated, $form, $period, $memberIds, $request): void {
            $collection = MeDataCollection::query()->create([
                'form_id' => $form->id,
                'reporting_period_id' => $period->id,
                'instructions' => $validated['instructions'] ?? null,
                'opens_at' => $validated['opens_at'],
                'due_at' => $validated['due_at'],
                'closes_at' => $validated['closes_at'],
                'status' => $validated['status'],
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);

            $this->syncAssignments($collection, $memberIds, $request->user()->id);
        });

        return $this->redirectToTab('collections', 'Collection created and think tanks assigned.');
    }

    public function updateCollection(Request $request, MeDataCollection $collection): RedirectResponse
    {
        $this->assertCollectionInScope($request, $collection);
        if ($collection->status === MeDataCollection::STATUS_CLOSED) {
            throw ValidationException::withMessages([
                'collection' => 'Closed collections cannot be edited.',
            ]);
        }

        [$validated, $form, $period, $memberIds] = $this->validatedCollectionPayload($request, $collection);
        $hasSubmissions = $collection->submissions()->exists();
        if ($hasSubmissions && (
            (string) $form->id !== (string) $collection->form_id
            || (string) $period->id !== (string) $collection->reporting_period_id
        )) {
            throw ValidationException::withMessages([
                'form_id' => 'The form and reporting period are locked after the first submission.',
            ]);
        }

        DB::transaction(function () use ($validated, $form, $period, $memberIds, $collection, $request): void {
            $collection->update([
                'form_id' => $form->id,
                'reporting_period_id' => $period->id,
                'instructions' => $validated['instructions'] ?? null,
                'opens_at' => $validated['opens_at'],
                'due_at' => $validated['due_at'],
                'closes_at' => $validated['closes_at'],
                'status' => $validated['status'],
                'updated_by' => $request->user()->id,
            ]);

            $this->syncAssignments($collection, $memberIds, $request->user()->id);
        });

        return $this->redirectToTab('collections', 'Collection updated.');
    }

    public function closeCollection(Request $request, MeDataCollection $collection): RedirectResponse
    {
        $this->assertCollectionInScope($request, $collection);
        DB::transaction(function () use ($collection, $request): void {
            $collection->update([
                'status' => MeDataCollection::STATUS_CLOSED,
                'updated_by' => $request->user()->id,
            ]);
        });

        return $this->redirectToTab('collections', 'Collection closed. Existing submissions remain available for review.');
    }

    private function scopedFormQuery(Request $request, ?string $portfolioId = null)
    {
        $query = MeDataEntryForm::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query->when($portfolioId, fn ($builder) => $builder->where('portfolio_id', $portfolioId));
    }

    private function scopedPeriodQuery(Request $request, ?string $portfolioId = null)
    {
        $query = MeReportingPeriod::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query->when($portfolioId, fn ($builder) => $builder->where('portfolio_id', $portfolioId));
    }

    private function scopedCollectionQuery(Request $request, ?string $portfolioId = null)
    {
        return MeDataCollection::query()
            ->when(
                $this->userHasAssignedPortfolioScope($request->user()),
                function ($query) use ($request): void {
                    $query->whereHas('form', function ($formQuery) use ($request): void {
                        $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($formQuery, $request->user());
                    });
                }
            )
            ->when($portfolioId, fn ($query) => $query->whereHas('form', fn ($formQuery) => $formQuery->where('portfolio_id', $portfolioId)));
    }

    private function scopedSubmissionQuery(Request $request, ?string $portfolioId = null)
    {
        return MeDataSubmission::query()
            ->when(
                $this->userHasAssignedPortfolioScope($request->user()),
                function ($query) use ($request): void {
                    $query->whereHas('assignment.collection.form', function ($formQuery) use ($request): void {
                        $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($formQuery, $request->user());
                    });
                }
            )
            ->when($portfolioId, fn ($query) => $query->whereHas(
                'assignment.collection.form',
                fn ($formQuery) => $formQuery->where('portfolio_id', $portfolioId)
            ));
    }

    private function scopedPerformanceReportQuery(Request $request, ?string $portfolioId = null)
    {
        $query = MePerformanceReport::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query->when($portfolioId, fn ($reportQuery) => $reportQuery->where('portfolio_id', $portfolioId));
    }

    private function applySubmissionRegisterSearch($query, string $search): void
    {
        $term = '%'.Str::lower(addcslashes(trim($search), '%_\\')).'%';
        $statusColumn = (new MeDataSubmission)->qualifyColumn('status');
        $workflowStatusColumn = (new MeDataSubmission)->qualifyColumn('workflow_status');
        $notesColumn = (new MeDataSubmission)->qualifyColumn('notes');

        $query->where(function ($searchQuery) use ($term, $statusColumn, $workflowStatusColumn, $notesColumn): void {
            $searchQuery
                ->whereRaw("LOWER({$statusColumn}) LIKE ?", [$term])
                ->orWhereRaw("LOWER({$workflowStatusColumn}) LIKE ?", [$term])
                ->orWhereRaw("LOWER({$notesColumn}) LIKE ?", [$term])
                ->orWhereHas('submittedBy', function ($userQuery) use ($term): void {
                    $userQuery->where(function ($participantQuery) use ($term): void {
                        $participantQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term]);
                    });
                })
                ->orWhereHas('assignment.thinkTank', function ($memberQuery) use ($term): void {
                    $memberQuery->where(function ($participantQuery) use ($term): void {
                        $participantQuery
                            ->whereRaw('LOWER(name) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(country) LIKE ?', [$term]);
                    });
                })
                ->orWhereHas('assignment.collection.form', function ($formQuery) use ($term): void {
                    $formQuery->where(function ($templateQuery) use ($term): void {
                        $templateQuery
                            ->whereRaw('LOWER(title) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$term])
                            ->orWhereHas('portfolio', function ($portfolioQuery) use ($term): void {
                                $portfolioQuery->whereRaw('LOWER(name) LIKE ?', [$term]);
                            })
                            ->orWhereHas('indicator', function ($indicatorQuery) use ($term): void {
                                $indicatorQuery->where(function ($indicatorSearchQuery) use ($term): void {
                                    $indicatorSearchQuery
                                        ->whereRaw('LOWER(name) LIKE ?', [$term])
                                        ->orWhereRaw('LOWER(indicator_code) LIKE ?', [$term]);
                                });
                            });
                    });
                })
                ->orWhereHas('assignment.collection.reportingPeriod', function ($periodQuery) use ($term): void {
                    $periodQuery->where(function ($periodSearchQuery) use ($term): void {
                        $periodSearchQuery
                            ->whereRaw('LOWER(label) LIKE ?', [$term])
                            ->orWhereRaw('LOWER(code) LIKE ?', [$term]);
                    });
                });
        });
    }

    private function assertFormInScope(Request $request, MeDataEntryForm $form): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($form, $request->user())) {
            abort(403, 'You do not have access to this collection form.');
        }
    }

    private function assertPeriodInScope(Request $request, MeReportingPeriod $period): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($period, $request->user())) {
            abort(403, 'You do not have access to this reporting period.');
        }
    }

    private function assertCollectionInScope(Request $request, MeDataCollection $collection): void
    {
        $collection->loadMissing('form:id,portfolio_id');
        if (! $collection->form) {
            abort(404);
        }
        $this->assertFormInScope($request, $collection->form);
    }

    private function assertPortfolioInScope(Request $request, string $portfolioId): Sector
    {
        $portfolio = Sector::query()->findOrFail($portfolioId);
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! $this->sectorIsAssignedToUser($portfolio, $request->user())) {
            abort(403, 'You do not have access to the selected portfolio.');
        }

        return $portfolio;
    }

    private function validatedFormPayload(Request $request, ?MeDataEntryForm $form = null): array
    {
        $this->prepareLegacySectionPayload($request, $form);
        if (! $request->filled('project_component_id') && $request->filled('indicator_id')) {
            $request->merge([
                'project_component_id' => Indicator::query()
                    ->whereKey((string) $request->input('indicator_id'))
                    ->value('project_component_id'),
            ]);
        }
        $fieldTable = (new MeDataEntryFormField)->getTable();
        $sectionTable = (new MeDataEntryFormSection)->getTable();

        $validated = $request->validate([
            'portfolio_id' => ['required', 'uuid', Rule::exists((new Sector)->getTable(), 'id')],
            'project_component_id' => ['required', 'uuid', Rule::exists((new Project)->getTable(), 'id')],
            'indicator_id' => ['required', 'uuid', Rule::exists((new Indicator)->getTable(), 'id')],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'responsible_user_id' => ['required', 'uuid', Rule::exists((new User)->getTable(), 'id')],
            'sections' => ['required', 'array', 'min:1', 'max:30'],
            'sections.*.id' => ['nullable', 'uuid', Rule::exists($sectionTable, 'id')],
            'sections.*.section_key' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/'],
            'sections.*.name' => ['required', 'string', 'max:255'],
            'sections.*.description' => ['required', 'string', 'max:2000'],
            'sections.*.background_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'sections.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'fields' => ['required', 'array', 'min:1', 'max:100'],
            'fields.*.id' => ['nullable', 'uuid', Rule::exists($fieldTable, 'id')],
            'fields.*.field_key' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9_]+$/'],
            'fields.*.section_key' => ['required', 'string', 'max:120', 'regex:/^[a-z0-9_-]+$/'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.field_type' => ['required', Rule::in($this->fieldTypes())],
            'fields.*.is_required' => ['nullable', 'boolean'],
            'fields.*.help_text' => ['nullable', 'string', 'max:1000'],
            'fields.*.options' => ['nullable'],
            'fields.*.unit_label' => ['nullable', 'string', 'max:80'],
            'fields.*.indicator_id' => ['nullable', 'uuid', Rule::exists((new Indicator)->getTable(), 'id')],
            'fields.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'fields.*.validation' => ['nullable', 'array'],
            'fields.*.validation.min' => ['nullable', 'numeric'],
            'fields.*.validation.max' => ['nullable', 'numeric'],
            'fields.*.validation.step' => ['nullable', 'numeric'],
            'fields.*.validation.min_length' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'fields.*.validation.max_length' => ['nullable', 'integer', 'min:0', 'max:20000'],
            'fields.*.validation.allowed_extensions' => ['nullable'],
            'fields.*.validation.allowed_extensions.*' => ['nullable', 'string', 'max:20'],
            'fields.*.validation.max_file_size_mb' => ['nullable', 'integer', 'min:1', 'max:50'],
            'fields.*.validation.multiple' => ['nullable', 'boolean'],
        ]);

        $this->assertPortfolioInScope($request, (string) $validated['portfolio_id']);
        $this->validateProjectComponent(
            (string) $validated['portfolio_id'],
            (string) $validated['project_component_id']
        );
        $this->validateTemplateIndicator(
            (string) $validated['portfolio_id'],
            (string) $validated['project_component_id'],
            (string) $validated['indicator_id']
        );
        $sections = $this->normalizeFormSections($validated['sections'], $form);
        $fields = $this->normalizeFormFields($validated['fields'], $sections, $form);
        $this->validateFieldMappings(
            (string) $validated['portfolio_id'],
            (string) $validated['project_component_id'],
            $fields
        );

        return [$validated, $sections, $fields];
    }

    private function prepareLegacySectionPayload(Request $request, ?MeDataEntryForm $form = null): void
    {
        if ($request->exists('sections')) {
            return;
        }

        $rows = $request->input('fields');
        if (! is_array($rows)) {
            return;
        }

        $existingSections = $form
            ? $form->sections()->get()->keyBy(fn (MeDataEntryFormSection $section): string => (string) $section->section_key)
            : collect();
        $existingByName = $existingSections->keyBy(
            fn (MeDataEntryFormSection $section): string => Str::lower(trim((string) $section->name))
        );
        $sections = [];
        $keysByName = [];
        $usedKeys = [];
        $palette = MeDataEntryFormSection::SOFT_BACKGROUND_COLORS;
        $rows = array_values($rows);

        foreach ($rows as $index => &$row) {
            $name = trim((string) ($row['section'] ?? '')) ?: MeDataEntryFormSection::DEFAULT_NAME;
            $requestedKey = trim((string) ($row['section_key'] ?? ''));
            $existingSection = $requestedKey !== ''
                ? $existingSections->get($requestedKey)
                : $existingByName->get(Str::lower($name));
            $nameLookup = Str::lower($name);

            if (isset($keysByName[$nameLookup])) {
                $key = $keysByName[$nameLookup];
            } else {
                $baseKey = (string) ($existingSection?->section_key
                    ?: ($requestedKey !== '' ? $requestedKey : Str::slug($name, '_')));
                $baseKey = Str::limit($baseKey !== '' ? $baseKey : 'section_'.($index + 1), 110, '');
                $key = $baseKey;
                $suffix = 2;
                while (isset($usedKeys[$key])) {
                    $key = Str::limit($baseKey, 112, '').'_'.$suffix;
                    $suffix++;
                }

                $usedKeys[$key] = true;
                $keysByName[$nameLookup] = $key;
                $sections[] = [
                    'id' => $existingSection?->id,
                    'section_key' => $key,
                    'name' => $existingSection?->name ?: $name,
                    'description' => trim((string) $existingSection?->description)
                        ?: self::DEFAULT_SECTION_DESCRIPTION,
                    'background_color' => $existingSection?->background_color
                        ?: $palette[count($sections) % count($palette)],
                    'sort_order' => (count($sections) + 1) * 10,
                ];
            }

            $row['section_key'] = $key;
        }
        unset($row);

        $request->merge(['sections' => $sections, 'fields' => $rows]);
    }

    private function normalizeFormSections(array $rows, ?MeDataEntryForm $form = null): array
    {
        $existing = $form
            ? $form->sections()->get()->keyBy(fn (MeDataEntryFormSection $section): string => (string) $section->id)
            : collect();
        $existingByKey = $existing->keyBy(fn (MeDataEntryFormSection $section): string => (string) $section->section_key);
        $normalized = [];
        $usedKeys = [];
        $usedNames = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $sectionId = trim((string) ($row['id'] ?? '')) ?: null;
            $existingSection = $sectionId ? $existing->get($sectionId) : null;
            if ($sectionId && ! $existingSection) {
                $errors["sections.$index.id"] = 'This section does not belong to the selected collection form.';

                continue;
            }

            $name = trim((string) ($row['name'] ?? ''));
            $requestedKey = trim((string) ($row['section_key'] ?? ''));
            $matchedByKey = $requestedKey !== '' ? $existingByKey->get($requestedKey) : null;
            if (! $existingSection && $matchedByKey) {
                $existingSection = $matchedByKey;
                $sectionId = (string) $matchedByKey->id;
            }

            $baseKey = (string) ($existingSection?->section_key
                ?: ($requestedKey !== '' ? $requestedKey : Str::slug($name, '_')));
            $baseKey = Str::limit($baseKey !== '' ? $baseKey : 'section_'.($index + 1), 110, '');
            $sectionKey = $baseKey;
            $suffix = 2;
            while ($requestedKey === '' && ! $existingSection && isset($usedKeys[$sectionKey])) {
                $sectionKey = Str::limit($baseKey, 112, '').'_'.$suffix;
                $suffix++;
            }
            if (isset($usedKeys[$sectionKey])) {
                $errors["sections.$index.section_key"] = 'Each section must have a unique key.';
            }
            $usedKeys[$sectionKey] = true;

            $nameKey = Str::lower($name);
            if (isset($usedNames[$nameKey])) {
                $errors["sections.$index.name"] = 'Each section must have a distinct name.';
            }
            $usedNames[$nameKey] = true;

            $normalized[] = [
                'id' => $sectionId,
                'form_id' => $form?->id,
                'section_key' => $sectionKey,
                'name' => $name,
                'description' => trim((string) $row['description']),
                'background_color' => Str::upper((string) ($row['background_color'] ?? MeDataEntryFormSection::DEFAULT_COLOR)),
                'sort_order' => ($index + 1) * 10,
                '_source_index' => $index,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function normalizeFormFields(array $rows, array $sections, ?MeDataEntryForm $form = null): array
    {
        $existing = $form
            ? $form->fields()->with('formSection')->get()->keyBy(fn (MeDataEntryFormField $field): string => (string) $field->id)
            : collect();
        $sectionsByKey = collect($sections)->keyBy('section_key');
        $normalized = [];
        $usedKeys = [];
        $questionCounts = [];
        $errors = [];

        foreach (array_values($rows) as $index => $row) {
            $fieldId = trim((string) ($row['id'] ?? '')) ?: null;
            $existingField = $fieldId ? $existing->get($fieldId) : null;
            if ($fieldId && ! $existingField) {
                $errors["fields.$index.id"] = 'This field does not belong to the selected collection form.';

                continue;
            }

            $sectionKey = trim((string) ($row['section_key'] ?? ''));
            $section = $sectionsByKey->get($sectionKey);
            if (! $section) {
                $errors["fields.$index.section_key"] = 'Choose a valid section for this question.';

                continue;
            }
            $questionCounts[$sectionKey] = ($questionCounts[$sectionKey] ?? 0) + 1;

            $label = trim((string) ($row['label'] ?? ''));
            $requestedKey = trim((string) ($row['field_key'] ?? ''));
            $baseKey = $existingField?->field_key
                ?: ($requestedKey !== '' ? $requestedKey : Str::slug($label, '_'));
            $baseKey = Str::limit($baseKey !== '' ? $baseKey : 'field_'.($index + 1), 90, '');
            $fieldKey = $baseKey;
            $suffix = 2;
            while (isset($usedKeys[$fieldKey])) {
                $fieldKey = Str::limit($baseKey, 84, '').'_'.$suffix;
                $suffix++;
            }
            $usedKeys[$fieldKey] = true;

            $type = (string) ($row['field_type'] ?? MeDataEntryFormField::TYPE_TEXT);
            $options = in_array($type, [...self::TWO_OPTION_FIELD_TYPES, ...self::ONE_OPTION_FIELD_TYPES], true)
                ? $this->normalizeFieldOptions($row['options'] ?? null)
                : [];
            if (in_array($type, self::TWO_OPTION_FIELD_TYPES, true) && count($options) < 2) {
                $errors["fields.$index.options"] = 'Add at least two choices for a select or radio field.';
            } elseif (in_array($type, self::ONE_OPTION_FIELD_TYPES, true) && $options === []) {
                $errors["fields.$index.options"] = 'Add at least one choice for a multi-select or checkbox field.';
            }

            $rawValidation = array_key_exists('validation', $row)
                ? $row['validation']
                : ($existingField?->validation ?? []);
            $validation = $this->normalizeFieldValidation($type, $rawValidation, $index, $errors);

            $unitLabel = trim((string) ($row['unit_label'] ?? '')) ?: null;
            if ($type === MeDataEntryFormField::TYPE_PERCENTAGE && ! $unitLabel) {
                $unitLabel = '%';
            }

            $normalized[] = [
                'id' => $fieldId,
                'form_id' => $form?->id,
                'indicator_id' => trim((string) ($row['indicator_id'] ?? '')) ?: null,
                'section_key' => $sectionKey,
                'section' => $section['name'],
                'field_key' => $fieldKey,
                'label' => $label,
                'help_text' => trim((string) ($row['help_text'] ?? '')) ?: null,
                'field_type' => $type,
                'options' => $options,
                'validation' => $validation,
                'unit_label' => $unitLabel,
                'is_required' => filter_var($row['is_required'] ?? false, FILTER_VALIDATE_BOOL),
                'sort_order' => $questionCounts[$sectionKey] * 10,
                '_source_index' => $index,
            ];
        }

        foreach ($sections as $sectionIndex => $section) {
            if (($questionCounts[$section['section_key']] ?? 0) === 0) {
                $errors["sections.$sectionIndex.name"] = 'Add at least one question to this section.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    private function normalizeFieldOptions(mixed $value): array
    {
        $options = is_array($value)
            ? $value
            : (preg_split('/[\r\n,]+/', (string) $value) ?: []);

        return collect($options)
            ->map(fn ($option): string => trim((string) $option))
            ->filter()
            ->unique(fn (string $option): string => Str::lower($option))
            ->values()
            ->take(100)
            ->all();
    }

    private function normalizeFieldValidation(string $type, mixed $value, int $index, array &$errors): array
    {
        $settings = is_array($value) ? $value : [];

        if (in_array($type, self::NUMERIC_VALIDATION_TYPES, true)) {
            $validation = [];
            foreach (['min', 'max', 'step'] as $key) {
                if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
                    $validation[$key] = $this->normalizeNumericSetting($settings[$key]);
                }
            }

            if ($type === 'integer') {
                $validation['step'] ??= 1;
            } elseif ($type === 'percentage') {
                $validation['min'] ??= 0;
                $validation['max'] ??= 100;
            } elseif ($type === 'rating') {
                $validation['min'] ??= 1;
                $validation['max'] ??= 5;
                $validation['step'] ??= 1;
            } elseif ($type === 'scale') {
                $validation['min'] ??= 1;
                $validation['max'] ??= 10;
                $validation['step'] ??= 1;
            }

            if ($type === 'rating') {
                foreach (['min' => 'minimum', 'max' => 'maximum'] as $key => $label) {
                    if ((float) (int) $validation[$key] !== (float) $validation[$key]) {
                        $errors["fields.$index.validation.$key"] = "The rating {$label} must be a whole number.";
                    } elseif ($validation[$key] < 1 || $validation[$key] > 10) {
                        $errors["fields.$index.validation.$key"] = "The rating {$label} must be between 1 and 10.";
                    } else {
                        $validation[$key] = (int) $validation[$key];
                    }
                }

                if ((float) (int) $validation['step'] !== (float) $validation['step'] || $validation['step'] < 1) {
                    $errors["fields.$index.validation.step"] = 'The rating step must be a positive whole number.';
                } else {
                    $validation['step'] = (int) $validation['step'];
                }
            }

            if (isset($validation['step']) && $validation['step'] <= 0
                && ! isset($errors["fields.$index.validation.step"])) {
                $errors["fields.$index.validation.step"] = 'The numeric step must be greater than zero.';
            }
            if (isset($validation['min'], $validation['max']) && $validation['min'] > $validation['max']) {
                $errors["fields.$index.validation.max"] = 'The maximum value must be greater than or equal to the minimum value.';
            }

            return $validation;
        }

        if (in_array($type, self::TEXT_VALIDATION_TYPES, true)) {
            $validation = [];
            foreach (['min_length', 'max_length'] as $key) {
                if (array_key_exists($key, $settings) && $settings[$key] !== null && $settings[$key] !== '') {
                    $validation[$key] = (int) $settings[$key];
                }
            }

            $lengthCap = match ($type) {
                'email' => 255,
                'phone' => 30,
                'url' => 2048,
                default => 20000,
            };
            $typeLabel = match ($type) {
                'textarea' => 'long text',
                'email' => 'email',
                'phone' => 'phone',
                'url' => 'URL',
                default => 'short text',
            };
            foreach (['min_length' => 'minimum', 'max_length' => 'maximum'] as $key => $label) {
                if (isset($validation[$key]) && $validation[$key] > $lengthCap) {
                    $errors["fields.$index.validation.$key"] = "The {$label} length for a {$typeLabel} field cannot exceed ".number_format($lengthCap).' characters.';
                }
            }

            if (isset($validation['min_length'], $validation['max_length'])
                && $validation['min_length'] > $validation['max_length']) {
                $errors["fields.$index.validation.max_length"] = 'The maximum length must be greater than or equal to the minimum length.';
            }

            return $validation;
        }

        if (in_array($type, self::UPLOAD_FIELD_TYPES, true)) {
            $extensions = $this->normalizeAllowedExtensions($settings['allowed_extensions'] ?? null);
            $extensions = $type === 'image'
                ? array_values(array_intersect($extensions, self::DEFAULT_IMAGE_EXTENSIONS))
                : array_values(array_diff($extensions, self::BLOCKED_FILE_EXTENSIONS));
            if ($extensions === []) {
                $extensions = $type === 'image'
                    ? self::DEFAULT_IMAGE_EXTENSIONS
                    : self::DEFAULT_FILE_EXTENSIONS;
            }

            return [
                'allowed_extensions' => $extensions,
                'max_file_size_mb' => isset($settings['max_file_size_mb']) && $settings['max_file_size_mb'] !== ''
                    ? (int) $settings['max_file_size_mb']
                    : 10,
                'multiple' => filter_var($settings['multiple'] ?? false, FILTER_VALIDATE_BOOL),
            ];
        }

        return [];
    }

    private function normalizeNumericSetting(mixed $value): int|float
    {
        $value = trim((string) $value);

        return str_contains(Str::lower($value), '.') || str_contains(Str::lower($value), 'e')
            ? (float) $value
            : (int) $value;
    }

    private function normalizeAllowedExtensions(mixed $value): array
    {
        $extensions = is_array($value)
            ? collect($value)->flatten()->all()
            : (preg_split('/[\s,;]+/', (string) $value) ?: []);

        return collect($extensions)
            ->map(function ($extension): string {
                $extension = Str::lower(ltrim(trim((string) $extension), '.'));

                return preg_replace('/[^a-z0-9]/', '', $extension) ?? '';
            })
            ->filter(fn (string $extension): bool => $extension !== '' && strlen($extension) <= 12)
            ->unique()
            ->values()
            ->take(30)
            ->all();
    }

    private function validateFieldMappings(string $portfolioId, string $componentId, array $fields): void
    {
        $errors = [];
        $mappedIds = collect($fields)->pluck('indicator_id')->filter()->values();
        $allowedIds = $mappedIds->isEmpty()
            ? collect()
            : $this->indicatorsForPortfolioQuery($portfolioId)
                ->where('project_component_id', $componentId)
                ->whereIn('id', $mappedIds->all())
                ->pluck('id')
                ->map(fn ($id): string => (string) $id);
        $seenIndicators = [];

        foreach ($fields as $field) {
            $index = $field['_source_index'];
            $indicatorId = $field['indicator_id'];
            if (! $indicatorId) {
                continue;
            }
            if (! in_array($field['field_type'], self::MAPPABLE_FIELD_TYPES, true)) {
                $errors["fields.$index.indicator_id"] = 'Only integer, number, percentage and currency fields can map to a performance indicator.';
            } elseif (! $allowedIds->contains((string) $indicatorId)) {
                $errors["fields.$index.indicator_id"] = 'The selected indicator does not belong to this project component.';
            } elseif (isset($seenIndicators[(string) $indicatorId])) {
                $errors["fields.$index.indicator_id"] = 'Each indicator may be mapped only once in a collection form.';
            }
            $seenIndicators[(string) $indicatorId] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function validateProjectComponent(string $portfolioId, string $componentId): void
    {
        if (! Project::query()
            ->whereKey($componentId)
            ->whereHas('program', fn ($query) => $query->where('sector_id', $portfolioId))
            ->exists()) {
            throw ValidationException::withMessages([
                'project_component_id' => 'The selected project component does not belong to the selected portfolio.',
            ]);
        }
    }

    private function validateTemplateIndicator(
        string $portfolioId,
        string $componentId,
        string $indicatorId
    ): void {
        if (! $this->indicatorsForPortfolioQuery($portfolioId)
            ->where('project_component_id', $componentId)
            ->whereKey($indicatorId)
            ->exists()) {
            throw ValidationException::withMessages([
                'indicator_id' => 'The selected indicator does not belong to the selected project component.',
            ]);
        }
    }

    private function syncFormStructure(MeDataEntryForm $form, array $sections, array $fields): void
    {
        [$sectionsByKey, $retainedSectionIds] = $this->syncFormSections($form, $sections);
        $this->syncFormFields($form, $fields, $sectionsByKey);

        $form->sections()
            ->when($retainedSectionIds !== [], fn ($query) => $query->whereNotIn('id', $retainedSectionIds))
            ->delete();
    }

    private function syncFormSections(MeDataEntryForm $form, array $sections): array
    {
        $existing = $form->sections()->get()->keyBy(fn (MeDataEntryFormSection $section): string => (string) $section->id);
        $sectionsByKey = collect();
        $retainedIds = [];

        foreach ($sections as $sectionData) {
            $sectionId = $sectionData['id'];
            unset($sectionData['id'], $sectionData['_source_index']);
            $sectionData['form_id'] = $form->id;

            if ($sectionId && $existing->has((string) $sectionId)) {
                $section = $existing->get((string) $sectionId);
                $section->update($sectionData);
            } else {
                $section = $form->sections()->create($sectionData);
            }

            $retainedIds[] = (string) $section->id;
            $sectionsByKey->put((string) $section->section_key, $section);
        }

        return [$sectionsByKey, $retainedIds];
    }

    private function syncFormFields(MeDataEntryForm $form, array $fields, Collection $sectionsByKey): void
    {
        $existing = $form->fields()->get()->keyBy(fn (MeDataEntryFormField $field): string => (string) $field->id);
        $retainedIds = [];

        foreach ($fields as $fieldData) {
            $fieldId = $fieldData['id'];
            $sectionKey = $fieldData['section_key'];
            unset($fieldData['id'], $fieldData['_source_index'], $fieldData['section_key']);
            $fieldData['form_id'] = $form->id;
            $fieldData['section_id'] = $sectionsByKey->get($sectionKey)?->id;

            if ($fieldId && $existing->has((string) $fieldId)) {
                $field = $existing->get((string) $fieldId);
                $field->update($fieldData);
                $retainedIds[] = (string) $field->id;
            } else {
                $field = $form->fields()->create($fieldData);
                $retainedIds[] = (string) $field->id;
            }
        }

        $form->fields()
            ->when($retainedIds !== [], fn ($query) => $query->whereNotIn('id', $retainedIds))
            ->delete();
    }

    private function syncFormIndicators(MeDataEntryForm $form, string $primaryIndicatorId, array $fields): void
    {
        $indicatorIds = collect([$primaryIndicatorId])
            ->merge(collect($fields)->pluck('indicator_id')->filter())
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();
        $form->indicators()->detach();
        $indicatorIds->each(function (string $indicatorId, int $index) use ($form, $primaryIndicatorId): void {
            $form->indicators()->attach($indicatorId, [
                'id' => (string) Str::uuid(),
                'is_primary' => $indicatorId === $primaryIndicatorId,
                'sort_order' => ($index + 1) * 10,
            ]);
        });
    }

    private function existingSectionStructure(MeDataEntryForm $form): array
    {
        return $form->sections
            ->sortBy(fn (MeDataEntryFormSection $section): string => str_pad((string) $section->sort_order, 10, '0', STR_PAD_LEFT).$section->created_at.$section->id)
            ->values()
            ->map(fn (MeDataEntryFormSection $section, int $index): array => [
                'id' => (string) $section->id,
                'form_id' => (string) $form->id,
                'section_key' => (string) $section->section_key,
                'name' => (string) $section->name,
                'description' => trim((string) $section->description)
                    ?: self::DEFAULT_SECTION_DESCRIPTION,
                'background_color' => Str::upper((string) $section->background_color),
                'sort_order' => ($index + 1) * 10,
                '_source_index' => $index,
            ])
            ->all();
    }

    private function existingFieldStructure(MeDataEntryForm $form): array
    {
        $sectionOrder = $form->sections
            ->sortBy(fn (MeDataEntryFormSection $section): string => str_pad((string) $section->sort_order, 10, '0', STR_PAD_LEFT).$section->created_at.$section->id)
            ->values()
            ->mapWithKeys(fn (MeDataEntryFormSection $section, int $index): array => [(string) $section->id => $index]);

        $questionCounts = [];

        return $form->fields
            ->sortBy(function (MeDataEntryFormField $field) use ($sectionOrder): string {
                $sectionIndex = $sectionOrder->get((string) $field->section_id, 9999);

                return str_pad((string) $sectionIndex, 5, '0', STR_PAD_LEFT)
                    .str_pad((string) $field->sort_order, 10, '0', STR_PAD_LEFT)
                    .$field->id;
            })
            ->values()
            ->map(function (MeDataEntryFormField $field, int $index) use ($form, &$questionCounts): array {
                $sectionKey = $field->formSection?->section_key
                    ?: Str::slug($field->section ?: MeDataEntryFormSection::DEFAULT_NAME, '_');
                $questionCounts[$sectionKey] = ($questionCounts[$sectionKey] ?? 0) + 1;

                return [
                    'id' => (string) $field->id,
                    'form_id' => (string) $form->id,
                    'indicator_id' => $field->indicator_id ? (string) $field->indicator_id : null,
                    'section_key' => $sectionKey,
                    'section' => $field->formSection?->name ?: ($field->section ?: MeDataEntryFormSection::DEFAULT_NAME),
                    'field_key' => (string) $field->field_key,
                    'label' => (string) $field->label,
                    'help_text' => $field->help_text ?: null,
                    'field_type' => (string) $field->field_type,
                    'options' => array_values($field->options ?? []),
                    'validation' => $field->validation ?? [],
                    'unit_label' => $field->unit_label ?: null,
                    'is_required' => (bool) $field->is_required,
                    'sort_order' => $questionCounts[$sectionKey] * 10,
                    '_source_index' => $index,
                ];
            })
            ->all();
    }

    private function formStructureFingerprint(
        string $portfolioId,
        ?string $componentId,
        ?string $indicatorId,
        string $code,
        array $sections,
        array $fields
    ): string {
        $normalizedSections = collect($sections)->map(function (array $section): array {
            unset($section['_source_index'], $section['form_id']);

            return $section;
        })->values()->all();
        $normalizedFields = collect($fields)->map(function (array $field): array {
            unset($field['_source_index'], $field['form_id']);

            return $field;
        })->values()->all();

        return hash('sha256', json_encode([
            'portfolio_id' => $portfolioId,
            'project_component_id' => $componentId,
            'indicator_id' => $indicatorId,
            'code' => $code,
            'sections' => $normalizedSections,
            'fields' => $normalizedFields,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function validatedPeriodPayload(Request $request, ?MeReportingPeriod $period = null): array
    {
        $request->merge(['code' => Str::upper(trim((string) $request->input('code')))]);
        $lifecycleStatus = (string) $request->input('lifecycle_status', match ((string) $request->input('status')) {
            MeReportingPeriod::STATUS_ACTIVE => MeReportingPeriod::LIFECYCLE_OPEN,
            MeReportingPeriod::STATUS_CLOSED => MeReportingPeriod::LIFECYCLE_CLOSED,
            default => MeReportingPeriod::LIFECYCLE_PLANNED,
        });
        $legacyStatus = match ($lifecycleStatus) {
            MeReportingPeriod::LIFECYCLE_OPEN, MeReportingPeriod::LIFECYCLE_UNDER_REVIEW => MeReportingPeriod::STATUS_ACTIVE,
            MeReportingPeriod::LIFECYCLE_CLOSED, MeReportingPeriod::LIFECYCLE_COMPLETED => MeReportingPeriod::STATUS_CLOSED,
            default => MeReportingPeriod::STATUS_DRAFT,
        };
        $request->merge([
            'lifecycle_status' => $lifecycleStatus,
            'status' => $legacyStatus,
            'reporting_year' => $request->input('reporting_year') ?: substr((string) $request->input('period_end'), 0, 4),
        ]);
        $table = (new MeReportingPeriod)->getTable();
        $uniqueCode = Rule::unique($table, 'code');
        if ($period) {
            $uniqueCode->ignore($period->id);
        }
        $submissionDeadlineRules = ['nullable', 'date'];
        if ($request->filled('submission_opens_at')) {
            $submissionDeadlineRules[] = 'after_or_equal:submission_opens_at';
        }
        $reviewDeadlineRules = ['nullable', 'date'];
        if ($request->filled('submission_deadline')) {
            $reviewDeadlineRules[] = 'after_or_equal:submission_deadline';
        }

        $validated = $request->validate([
            'portfolio_id' => ['required', 'uuid', Rule::exists((new Sector)->getTable(), 'id')],
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z0-9][A-Z0-9._-]*$/', $uniqueCode],
            'label' => ['required', 'string', 'max:150'],
            'period_type' => ['required', Rule::in([
                MeReportingPeriod::TYPE_YEAR,
                MeReportingPeriod::TYPE_QUARTER,
                MeReportingPeriod::TYPE_MONTH,
                MeReportingPeriod::TYPE_CUSTOM,
                MeReportingPeriod::TYPE_SEMI_ANNUAL,
                MeReportingPeriod::TYPE_ANNUAL,
            ])],
            'period_start' => ['required', 'date'],
            'period_end' => ['required', 'date', 'after_or_equal:period_start'],
            'status' => ['required', Rule::in([
                MeReportingPeriod::STATUS_DRAFT,
                MeReportingPeriod::STATUS_ACTIVE,
                MeReportingPeriod::STATUS_CLOSED,
            ])],
            'reporting_year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'submission_opens_at' => ['nullable', 'date'],
            'submission_deadline' => $submissionDeadlineRules,
            'review_deadline' => $reviewDeadlineRules,
            'lifecycle_status' => ['required', Rule::in([
                MeReportingPeriod::LIFECYCLE_PLANNED,
                MeReportingPeriod::LIFECYCLE_OPEN,
                MeReportingPeriod::LIFECYCLE_CLOSED,
                MeReportingPeriod::LIFECYCLE_UNDER_REVIEW,
                MeReportingPeriod::LIFECYCLE_COMPLETED,
            ])],
            'instructions' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->assertPortfolioInScope($request, (string) $validated['portfolio_id']);

        return $validated;
    }

    private function validatedCollectionPayload(Request $request, ?MeDataCollection $collection = null): array
    {
        $validated = $request->validate([
            'form_id' => ['required', 'uuid', Rule::exists((new MeDataEntryForm)->getTable(), 'id')],
            'reporting_period_id' => ['required', 'uuid', Rule::exists((new MeReportingPeriod)->getTable(), 'id')],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'opens_at' => ['required', 'date'],
            'due_at' => ['required', 'date', 'after_or_equal:opens_at'],
            'closes_at' => ['required', 'date', 'after_or_equal:due_at'],
            'status' => ['required', Rule::in([
                MeDataCollection::STATUS_DRAFT,
                MeDataCollection::STATUS_OPEN,
            ])],
            'member_ids' => ['required', 'array', 'min:1', 'max:500'],
            'member_ids.*' => ['required', 'uuid', 'distinct', Rule::exists((new ConsortiumThinkTank)->getTable(), 'id')],
        ]);

        $form = MeDataEntryForm::query()->findOrFail($validated['form_id']);
        $period = MeReportingPeriod::query()->findOrFail($validated['reporting_period_id']);
        $this->assertFormInScope($request, $form);
        $this->assertPeriodInScope($request, $period);

        if ($form->status !== MeDataEntryForm::STATUS_PUBLISHED) {
            throw ValidationException::withMessages(['form_id' => 'Choose a published collection form.']);
        }
        if (! $period->isActive()) {
            throw ValidationException::withMessages(['reporting_period_id' => 'Choose an open reporting period.']);
        }
        if ((string) $form->portfolio_id !== (string) $period->portfolio_id) {
            throw ValidationException::withMessages([
                'reporting_period_id' => 'The form and reporting period must belong to the same portfolio.',
            ]);
        }

        $duplicate = MeDataCollection::query()
            ->where('form_id', $form->id)
            ->where('reporting_period_id', $period->id)
            ->when($collection, fn ($query) => $query->where('id', '!=', $collection->id))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages([
                'reporting_period_id' => 'A collection for this form and reporting period already exists.',
            ]);
        }

        $memberIds = collect($validated['member_ids'])
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();
        $activeMemberIds = ConsortiumThinkTank::query()
            ->whereIn('id', $memberIds->all())
            ->where('status', 'active')
            ->pluck('id')
            ->map(fn ($id): string => (string) $id);
        $existingMemberIds = $collection
            ? $collection->assignments()->pluck('think_tank_member_id')->map(fn ($id): string => (string) $id)
            : collect();
        $invalidMemberIds = $memberIds->diff($activeMemberIds->merge($existingMemberIds)->unique());
        if ($invalidMemberIds->isNotEmpty()) {
            throw ValidationException::withMessages([
                'member_ids' => 'Only active think tanks can be newly assigned to a collection.',
            ]);
        }
        if ($memberIds->intersect($activeMemberIds)->isEmpty()) {
            throw ValidationException::withMessages([
                'member_ids' => 'Assign at least one active think tank.',
            ]);
        }

        return [$validated, $form, $period, $memberIds];
    }

    private function syncAssignments(MeDataCollection $collection, Collection $memberIds, string $userId): void
    {
        $assignments = $collection->assignments()
            ->with(['submission', 'thinkTank:id,name'])
            ->lockForUpdate()
            ->get();
        $desired = $memberIds->map(fn ($id): string => (string) $id);
        $removing = $assignments->filter(
            fn (MeDataCollectionAssignment $assignment): bool => ! $desired->contains((string) $assignment->think_tank_member_id)
        );
        $protected = $removing->filter(fn (MeDataCollectionAssignment $assignment): bool => (bool) $assignment->submission);

        if ($protected->isNotEmpty()) {
            $names = $protected->map(fn (MeDataCollectionAssignment $assignment): string => $assignment->thinkTank?->name ?: 'Assigned think tank')
                ->join(', ');
            throw ValidationException::withMessages([
                'member_ids' => "The following assignments already have submissions and cannot be removed: {$names}.",
            ]);
        }

        $removing->each->delete();
        $existingMemberIds = $assignments->pluck('think_tank_member_id')->map(fn ($id): string => (string) $id);
        foreach ($desired->diff($existingMemberIds) as $memberId) {
            $collection->assignments()->create([
                'think_tank_member_id' => $memberId,
                'assigned_by' => $userId,
                'assigned_at' => now(),
            ]);
        }
    }

    private function indicatorOptionsForPortfolios(Collection $portfolios): Collection
    {
        return $portfolios->flatMap(function (Sector $portfolio): Collection {
            return $this->indicatorsForPortfolioQuery((string) $portfolio->id)
                ->with('unit:id,name,symbol')
                ->orderBy('indicator_code')
                ->orderBy('name')
                ->get(['id', 'indicator_code', 'name', 'unit_id', 'project_component_id'])
                ->map(fn (Indicator $indicator): array => [
                    'id' => (string) $indicator->id,
                    'portfolio_id' => (string) $portfolio->id,
                    'project_component_id' => (string) $indicator->project_component_id,
                    'portfolio_name' => (string) $portfolio->name,
                    'label' => trim(($indicator->indicator_code ? $indicator->indicator_code.' - ' : '').$indicator->name),
                    'unit' => $indicator->unit?->symbol ?: $indicator->unit?->name,
                ]);
        })->values();
    }

    private function componentOptionsForPortfolios(Collection $portfolios): Collection
    {
        $portfolioIds = $portfolios->pluck('id')->map(fn ($id): string => (string) $id);

        return Project::query()
            ->with([
                'program:id,sector_id',
                'governanceNode:id,name,code,level_id',
                'governanceNode.level:id,key,name',
            ])
            ->whereHas('program', fn ($query) => $query->whereIn('sector_id', $portfolioIds->all()))
            ->orderBy('name')
            ->get(['id', 'project_id', 'program_id', 'governance_node_id', 'name'])
            ->map(fn (Project $component): array => [
                'id' => (string) $component->id,
                'portfolio_id' => (string) $component->program?->sector_id,
                'label' => trim(($component->project_id ? $component->project_id.' - ' : '').$component->name),
                'directorate' => $component->governanceNode?->name ?: 'Responsible Directorate not assigned',
                'directorate_level' => $component->governanceNode?->level?->name,
            ])
            ->values();
    }

    private function indicatorsForPortfolioQuery(string $portfolioId)
    {
        $programIds = Program::query()->select('id')->where('sector_id', $portfolioId);
        $projectIds = Project::query()->select('id')->whereIn('program_id', Program::query()->select('id')->where('sector_id', $portfolioId));
        $activityIds = Activity::query()->select('id')->whereIn('project_id', Project::query()->select('id')->whereIn(
            'program_id',
            Program::query()->select('id')->where('sector_id', $portfolioId)
        ));
        $subActivityIds = SubActivity::query()->select('id')->whereIn('activity_id', Activity::query()->select('id')->whereIn(
            'project_id',
            Project::query()->select('id')->whereIn('program_id', Program::query()->select('id')->where('sector_id', $portfolioId))
        ));

        return Indicator::query()->where(function ($query) use (
            $portfolioId,
            $programIds,
            $projectIds,
            $activityIds,
            $subActivityIds
        ): void {
            $query->where(function ($ownerQuery) use ($portfolioId): void {
                $ownerQuery->where('indicatorable_type', Sector::class)->where('indicatorable_id', $portfolioId);
            })->orWhere(function ($ownerQuery) use ($programIds): void {
                $ownerQuery->where('indicatorable_type', Program::class)->whereIn('indicatorable_id', $programIds);
            })->orWhere(function ($ownerQuery) use ($projectIds): void {
                $ownerQuery->where('indicatorable_type', Project::class)->whereIn('indicatorable_id', $projectIds);
            })->orWhere(function ($ownerQuery) use ($activityIds): void {
                $ownerQuery->where('indicatorable_type', Activity::class)->whereIn('indicatorable_id', $activityIds);
            })->orWhere(function ($ownerQuery) use ($subActivityIds): void {
                $ownerQuery->where('indicatorable_type', SubActivity::class)->whereIn('indicatorable_id', $subActivityIds);
            });
        });
    }

    private function fieldTypes(): array
    {
        return self::FIELD_TYPES;
    }

    private function redirectToTab(string $tab, string $message): RedirectResponse
    {
        return redirect()
            ->route('budget.me.rebuild.data-entry', ['tab' => $tab])
            ->with('success', $message);
    }
}
