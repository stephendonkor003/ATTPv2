<?php

namespace App\Http\Controllers;

use App\Exports\MeIndicatorsManagementReportExport;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\Activity;
use App\Models\Indicator;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorResult;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorTarget;
use App\Models\IndicatorUnit;
use App\Models\Program;
use App\Models\Project;
use App\Models\ReportingFrequency;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Models\User;
use App\Support\MeSurvey;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class MeIndicatorController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view')->only([
            'index',
            'exportManagementExcel',
            'exportManagementPdf',
        ]);
        $this->middleware('permission:me.configuration.manage')->only([
            'store',
            'update',
            'destroy',
            'storeData',
            'validateData',
            'approveData',
        ]);
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $showForm = $request->boolean('create')
            || $request->filled('edit')
            || $request->query('tab') === 'settings';
        $ownerRequired = false;

        $registryQuery = Indicator::query();
        $this->scopeIndicatorsForCurrentPortfolio($registryQuery);

        $completeQuery = (clone $registryQuery)
            ->whereNotNull('indicator_code')
            ->where('indicator_code', '<>', '')
            ->whereNotNull('name')
            ->where('name', '<>', '')
            ->whereNotNull('definitions')
            ->where('definitions', '<>', '')
            ->whereNotNull('unit_id')
            ->whereNotNull('baseline_value')
            ->whereNotNull('frequency_of_reporting_id')
            ->whereNotNull('primary_source')
            ->where('primary_source', '<>', '')
            ->whereNotNull('responsible_user_id')
            ->whereHas('setupTarget');

        $summary = [
            'total' => (clone $registryQuery)->count(),
            'complete' => $completeQuery->count(),
            'with_target' => (clone $registryQuery)->whereHas('setupTarget')->count(),
        ];
        $summary['needs_attention'] = max(0, $summary['total'] - $summary['complete']);
        $summary['without_target'] = max(0, $summary['total'] - $summary['with_target']);

        $indicators = (clone $registryQuery)
            ->with([
                'indicatorable',
                'frequency:id,name',
                'unit:id,name,symbol',
                'responsiblePerson:id,name,email',
                'setupTarget:id,indicator_id,target_value,unit_id,target_context',
            ])
            ->when($search !== '', function ($query) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $term = '%'.$escaped.'%';

                $query->where(function ($searchQuery) use ($term) {
                    $searchQuery->whereLike('indicator_code', $term)
                        ->orWhereLike('name', $term)
                        ->orWhereLike('definitions', $term)
                        ->orWhereLike('primary_source', $term)
                        ->orWhereHas('responsiblePerson', function ($personQuery) use ($term) {
                            $personQuery->whereLike('name', $term)
                                ->orWhereLike('email', $term);
                        });
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $editingIndicator = null;
        if ($request->filled('edit')) {
            $editingIndicator = Indicator::query()
                ->with(['setupTarget', 'targets'])
                ->findOrFail((string) $request->query('edit'));
            $this->assertIndicatorInCurrentPortfolioScope($editingIndicator);
        }

        $users = collect();
        $units = collect();
        $frequencies = collect();
        $portfolios = collect();
        $programs = collect();
        $projects = collect();
        $activities = collect();
        $subActivities = collect();
        $frequencyIntervalOptions = [];

        if ($showForm) {
            $users = $this->indicatorResponsibleUsersQuery()
                ->orderBy('name')
                ->get(['id', 'name', 'email']);

            $unitQuery = IndicatorUnit::query()->with('portfolio:id,name')->active()->ordered();
            $frequencyQuery = ReportingFrequency::query()->with('portfolio:id,name')->active()->ordered();
            $this->scopeIndicatorConfigurationQuery($unitQuery);
            $this->scopeIndicatorConfigurationQuery($frequencyQuery);
            $units = $unitQuery->get(['id', 'portfolio_id', 'name', 'symbol']);
            $frequencies = $frequencyQuery->get(['id', 'portfolio_id', 'name']);
            $frequencyIntervalOptions = ReportingFrequency::intervalOptions();

            $portfolioQuery = Sector::query()->orderBy('name');
            $programQuery = Program::query()->orderBy('name');
            $projectQuery = Project::with('program:id,sector_id')->orderBy('name');
            $activityQuery = Activity::with([
                'project:id,name,program_id',
                'project.program:id,sector_id',
            ])->orderBy('name');
            $subActivityQuery = SubActivity::with([
                'activity:id,name,project_id',
                'activity.project:id,name,program_id',
                'activity.project.program:id,sector_id',
            ])->orderBy('name');

            if ($this->userHasAssignedPortfolioScope()) {
                $this->applyAssignedPortfolioScopeToSectors($portfolioQuery);
                $this->applyAssignedPortfolioScopeToPrograms($programQuery);
                $this->applyAssignedPortfolioScopeToProjects($projectQuery);
                $this->applyAssignedPortfolioScopeToActivities($activityQuery);
                $this->applyAssignedPortfolioScopeToSubActivities($subActivityQuery);
            }

            $portfolios = $portfolioQuery->get(['id', 'name']);
            $programs = $programQuery->get(['id', 'program_id', 'sector_id', 'name']);
            $projects = $projectQuery->get(['id', 'project_id', 'name', 'program_id']);
            $activities = $activityQuery->get(['id', 'name', 'project_id']);
            $subActivities = $subActivityQuery->get(['id', 'name', 'activity_id']);
        }

        $editingOwnerReference = $this->ownerReferenceForIndicator($editingIndicator);
        $editingPortfolioId = $editingIndicator
            ? $this->portfolioIdForOwner(
                $editingIndicator->indicatorable_type,
                $editingIndicator->indicatorable_id
            )
            : null;
        $ownerPortfolioMap = $this->ownerPortfolioMap(
            $portfolios,
            $programs,
            $projects,
            $activities,
            $subActivities
        );
        $editingResponsibleUserIds = $this->responsibleUserIdsForIndicator($editingIndicator);
        [, $editingPrimarySourceValue] = $this->unpackPrimarySource($editingIndicator?->primary_source);
        $editingTargetValue = $editingIndicator?->setupTarget?->target_value
            ?? $editingIndicator?->targets->sortByDesc('period_start')->first()?->target_value;

        return view('me.indicators.index', compact(
            'indicators',
            'editingIndicator',
            'editingOwnerReference',
            'editingPortfolioId',
            'ownerPortfolioMap',
            'editingResponsibleUserIds',
            'editingPrimarySourceValue',
            'editingTargetValue',
            'users',
            'units',
            'frequencies',
            'portfolios',
            'programs',
            'projects',
            'activities',
            'subActivities',
            'summary',
            'search',
            'showForm',
            'ownerRequired',
            'frequencyIntervalOptions'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validateIndicator($request);
        $this->assertPortfolioInCurrentScope((string) $validated['portfolio_id']);
        [$indicatorableType, $indicatorableId] = $this->resolveOwnerForPortfolio($validated);
        $this->assertOwnerReferenceInCurrentPortfolioScope($indicatorableType, $indicatorableId);
        $this->assertCoreConfigurationInCurrentPortfolioScope($validated);

        DB::transaction(function () use ($validated, $indicatorableType, $indicatorableId) {
            $indicator = Indicator::create($this->indicatorAttributes(
                $validated,
                null,
                $indicatorableType,
                $indicatorableId
            ));

            $this->syncSetupTarget($indicator, $validated['target_value']);
            $this->syncSurveyLinkForIndicator($indicator);
        });

        return redirect()
            ->route('budget.me.indicators.index')
            ->with('success', 'Indicator created successfully.');
    }

    public function update(Request $request, Indicator $indicator)
    {
        $this->assertIndicatorInCurrentPortfolioScope($indicator);

        $validated = $this->validateIndicator($request);
        $this->assertPortfolioInCurrentScope((string) $validated['portfolio_id']);
        $this->assertCoreConfigurationInCurrentPortfolioScope($validated);
        [$indicatorableType, $indicatorableId] = $this->resolveOwnerForPortfolio($validated);
        $this->assertOwnerReferenceInCurrentPortfolioScope($indicatorableType, $indicatorableId);

        DB::transaction(function () use ($validated, $indicator, $indicatorableType, $indicatorableId) {
            $lockedIndicator = Indicator::query()->lockForUpdate()->findOrFail($indicator->id);
            $this->assertIndicatorInCurrentPortfolioScope($lockedIndicator);

            $lockedIndicator->update($this->indicatorAttributes(
                $validated,
                $lockedIndicator,
                $indicatorableType,
                $indicatorableId
            ));

            $this->syncSetupTarget($lockedIndicator, $validated['target_value']);
            $this->syncSurveyLinkForIndicator($lockedIndicator);
        });

        return redirect()
            ->route('budget.me.indicators.index')
            ->with('success', 'Indicator updated successfully.');
    }

    public function destroy(Indicator $indicator)
    {
        $this->assertIndicatorInCurrentPortfolioScope($indicator);

        $indicator->delete();

        return redirect()
            ->route('budget.me.indicators.index')
            ->with('success', 'Indicator deleted successfully.');
    }

    public function storeData(Request $request, Indicator $indicator)
    {
        $this->assertIndicatorInCurrentPortfolioScope($indicator);

        $validated = $this->validateIndicatorData($request);
        [$periodLabel, $periodStart, $periodEnd] = $this->normalizeReportingPeriod($validated);

        if (array_key_exists('target_value', $validated) && $validated['target_value'] !== null && $validated['target_value'] !== '') {
            IndicatorTarget::updateOrCreate(
                [
                    'indicator_id' => $indicator->id,
                    'target_context' => null,
                    'period_type' => $validated['period_type'],
                    'period_label' => $periodLabel,
                ],
                [
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'target_value' => $validated['target_value'],
                    'unit_id' => $indicator->unit_id,
                    'notes' => $validated['target_notes'] ?? null,
                    'updated_by' => auth()->id(),
                    'created_by' => auth()->id(),
                ]
            );
        }

        IndicatorResult::create([
            'indicator_id' => $indicator->id,
            'period_type' => $validated['period_type'],
            'period_label' => $periodLabel,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'actual_value' => $validated['actual_value'],
            'unit_id' => $indicator->unit_id,
            'data_source' => $validated['data_source'] ?? null,
            'method' => $validated['method'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'review_status' => 'submitted',
            'collected_by' => auth()->id(),
            'collected_at' => now(),
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('budget.me.rebuild.data-entry', ['tab' => 'submissions'])
            ->with('success', 'Indicator data submitted for validation.');
    }

    public function validateData(Request $request, IndicatorResult $result)
    {
        $this->assertIndicatorResultInCurrentPortfolioScope($result);

        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:1000',
        ]);

        if (($result->review_status ?: 'submitted') === 'approved') {
            return redirect()
                ->route('budget.me.indicators.index', ['tab' => 'quality'])
                ->withErrors(['review_status' => 'Approved data cannot be revalidated.']);
        }

        $result->update([
            'review_status' => 'validated',
            'validated_by' => auth()->id(),
            'validated_at' => now(),
            'review_notes' => $validated['review_notes'] ?? $result->review_notes,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('budget.me.indicators.index', ['tab' => 'quality'])
            ->with('success', 'Indicator data validated.');
    }

    public function approveData(Request $request, IndicatorResult $result)
    {
        $this->assertIndicatorResultInCurrentPortfolioScope($result);

        $validated = $request->validate([
            'review_notes' => 'nullable|string|max:1000',
        ]);

        if (($result->review_status ?: 'submitted') !== 'validated') {
            return redirect()
                ->route('budget.me.indicators.index', ['tab' => 'quality'])
                ->withErrors(['review_status' => 'Data must be validated before approval.']);
        }

        $result->update([
            'review_status' => 'approved',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
            'review_notes' => $validated['review_notes'] ?? $result->review_notes,
            'updated_by' => auth()->id(),
        ]);

        return redirect()
            ->route('budget.me.indicators.index', ['tab' => 'quality'])
            ->with('success', 'Indicator data approved.');
    }

    public function exportManagementExcel(Request $request)
    {
        $searchTerm = trim((string) $request->query('q', ''));
        $userNamesById = User::query()->pluck('name', 'id');

        $allIndicators = $this->collectIndicatorsWithRelations();
        $statusRowsById = $allIndicators
            ->map(fn (Indicator $indicator) => $this->buildStatusRow($indicator))
            ->keyBy('id');

        $managementReportRows = $this->buildManagementReportRows(
            $allIndicators,
            $statusRowsById,
            $userNamesById,
            $searchTerm
        );

        $filename = 'me-management-report-'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(
            new MeIndicatorsManagementReportExport(
                $managementReportRows->values()->all(),
                $searchTerm
            ),
            $filename
        );
    }

    public function exportManagementPdf(Request $request)
    {
        $searchTerm = trim((string) $request->query('q', ''));
        $userNamesById = User::query()->pluck('name', 'id');

        $allIndicators = $this->collectIndicatorsWithRelations();
        $statusRowsById = $allIndicators
            ->map(fn (Indicator $indicator) => $this->buildStatusRow($indicator))
            ->keyBy('id');

        $managementReportRows = $this->buildManagementReportRows(
            $allIndicators,
            $statusRowsById,
            $userNamesById,
            $searchTerm
        );

        $pdf = Pdf::loadView('me.indicators.report_pdf', [
            'rows' => $managementReportRows,
            'searchTerm' => $searchTerm,
            'generatedAt' => now(),
        ])->setPaper('a3', 'landscape');

        return $pdf->download('me-management-report-'.now()->format('Ymd_His').'.pdf');
    }

    protected function validateIndicator(Request $request): array
    {
        $this->normalizeIndicatorInput($request);

        return $request->validate([
            'portfolio_id' => 'required|uuid|exists:myb_sectors,id',
            'name' => 'required|string|max:255',
            'definition' => 'required|string|max:10000',
            'unit_id' => 'required|exists:me_indicator_units,id',
            'baseline_value' => 'required|numeric',
            'target_value' => 'required|numeric',
            'frequency_of_reporting_id' => 'required|exists:me_reporting_frequencies,id',
            'data_source' => 'required|string|max:255',
            'responsible_user_id' => [
                'required',
                'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $this->indicatorResponsibleUsersQuery()->whereKey($value)->exists()) {
                        $fail('Please select an eligible responsible person.');
                    }
                },
            ],
            'owner_reference' => [
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (empty($value)) {
                        return;
                    }

                    [$type, $id] = array_pad(explode(':', (string) $value, 2), 2, null);
                    if (! $type || ! $id || ! in_array($type, ['portfolio', 'program', 'project', 'activity', 'sub_activity'], true)) {
                        $fail('Please select a valid owner (Portfolio, Program, Project, Activity, or Sub-Activity).');

                        return;
                    }

                    $exists = match ($type) {
                        'portfolio' => Sector::whereKey($id)->exists(),
                        'program' => Program::whereKey($id)->exists(),
                        'project' => Project::whereKey($id)->exists(),
                        'activity' => Activity::whereKey($id)->exists(),
                        'sub_activity' => SubActivity::whereKey($id)->exists(),
                        default => false,
                    };

                    if (! $exists) {
                        $fail('Selected owner record does not exist.');
                    }
                },
            ],
            'baseline_year' => [
                'sometimes',
                'nullable',
                'string',
                'max:50',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $period = trim((string) $value);
                    if ($period === '') {
                        return;
                    }

                    $type = (string) $request->input('baseline_type', 'year');
                    $isValid = match ($type) {
                        'year' => (bool) preg_match('/^\d{4}$/', $period),
                        'quarter' => (bool) preg_match('/^\d{4}-Q[1-4]$/', $period),
                        'month' => (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period),
                        'week' => (bool) preg_match('/^\d{4}-W(0[1-9]|[1-4][0-9]|5[0-3])$/', $period),
                        'day' => (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/', $period),
                        default => false,
                    };

                    if (! $isValid) {
                        $fail('Baseline period format does not match the selected baseline type.');
                    }
                },
            ],
            'baseline_type' => 'sometimes|nullable|in:year,month,quarter,week,day',
            'indicator_level_id' => 'sometimes|nullable|exists:me_indicator_levels,id',
            'methodology' => 'sometimes|nullable|string|max:255',
            'notes' => 'sometimes|nullable|string|max:10000',
            'primary_source_type' => 'sometimes|nullable|in:file_location,link,external_system_connector',
        ]);
    }

    /**
     * Keep the new form contract small while accepting submissions from the
     * previous indicator editor during the transition.
     */
    protected function normalizeIndicatorInput(Request $request): void
    {
        if (! $request->exists('definition')) {
            $definition = trim((string) $request->input('definition_custom', ''));

            if ($definition === '' && $request->filled('definition_id')) {
                $definitionQuery = IndicatorDefinition::query()
                    ->whereKey($request->input('definition_id'))
                    ->where('is_active', true);
                $this->scopeIndicatorConfigurationQuery($definitionQuery);
                $definitionRecord = $definitionQuery->first(['name', 'description']);
                $definition = trim((string) ($definitionRecord?->description ?: $definitionRecord?->name));
            }

            $request->merge(['definition' => $definition]);
        }

        if (! $request->exists('data_source')) {
            $request->merge([
                'data_source' => trim((string) $request->input('primary_source_value', '')),
            ]);
        }

        if (! $request->exists('responsible_user_id')) {
            $legacyResponsibleIds = collect((array) $request->input('responsible_user_ids', []))
                ->filter(fn ($id) => is_scalar($id) && trim((string) $id) !== '')
                ->values();

            $request->merge([
                'responsible_user_id' => $legacyResponsibleIds->first(),
            ]);
        }
    }

    protected function indicatorResponsibleUsersQuery()
    {
        return User::query()->where(function ($query) {
            $query->whereNull('user_type')
                ->orWhere('user_type', '!=', 'funding_partner');
        });
    }

    protected function assertCoreConfigurationInCurrentPortfolioScope(array $validated): void
    {
        $portfolioId = (string) $validated['portfolio_id'];
        $unitQuery = IndicatorUnit::query()
            ->whereKey($validated['unit_id'])
            ->where('portfolio_id', $portfolioId)
            ->where('is_active', true);
        $frequencyQuery = ReportingFrequency::query()
            ->whereKey($validated['frequency_of_reporting_id'])
            ->where('portfolio_id', $portfolioId)
            ->where('is_active', true);
        $this->scopeIndicatorConfigurationQuery($unitQuery);
        $this->scopeIndicatorConfigurationQuery($frequencyQuery);

        $errors = [];
        if (! $unitQuery->exists()) {
            $errors['unit_id'] = 'Please select an active unit configured for the selected portfolio.';
        }
        if (! $frequencyQuery->exists()) {
            $errors['frequency_of_reporting_id'] = 'Please select an active reporting frequency configured for the selected portfolio.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    protected function assertPortfolioInCurrentScope(string $portfolioId): void
    {
        if ($this->userHasAssignedPortfolioScope()
            && ! in_array($portfolioId, $this->assignedPortfolioIds(), true)) {
            abort(403, 'You do not have access to the selected portfolio.');
        }
    }

    /**
     * Keep the selected portfolio as the indicator's effective owner when a
     * more specific results-hierarchy owner was not chosen.
     *
     * @return array{0: class-string|null, 1: mixed}
     */
    protected function resolveOwnerForPortfolio(array $validated): array
    {
        $portfolioId = (string) $validated['portfolio_id'];
        [$indicatorableType, $indicatorableId] = $this->parseOwnerReference(
            $validated['owner_reference'] ?? null
        );

        if (! $indicatorableType || ! $indicatorableId) {
            return [Sector::class, $portfolioId];
        }

        if ($this->portfolioIdForOwner($indicatorableType, $indicatorableId) !== $portfolioId) {
            throw ValidationException::withMessages([
                'owner_reference' => 'The selected owner must belong to the selected portfolio.',
            ]);
        }

        return [$indicatorableType, $indicatorableId];
    }

    protected function portfolioIdForOwner(?string $indicatorableType, mixed $indicatorableId): ?string
    {
        if (! $indicatorableType || ! $indicatorableId) {
            return null;
        }

        $portfolioId = match ($indicatorableType) {
            Sector::class => Sector::query()->whereKey($indicatorableId)->value('id'),
            Program::class => Program::query()->whereKey($indicatorableId)->value('sector_id'),
            Project::class => Project::query()
                ->with('program:id,sector_id')
                ->find($indicatorableId)?->program?->sector_id,
            Activity::class => Activity::query()
                ->with('project.program:id,sector_id')
                ->find($indicatorableId)?->project?->program?->sector_id,
            SubActivity::class => SubActivity::query()
                ->with('activity.project.program:id,sector_id')
                ->find($indicatorableId)?->activity?->project?->program?->sector_id,
            default => null,
        };

        return $portfolioId ? (string) $portfolioId : null;
    }

    /**
     * @return array<string, string>
     */
    protected function ownerPortfolioMap(
        Collection $portfolios,
        Collection $programs,
        Collection $projects,
        Collection $activities,
        Collection $subActivities
    ): array {
        $map = [];

        foreach ($portfolios as $portfolio) {
            $map['portfolio:'.$portfolio->id] = (string) $portfolio->id;
        }
        foreach ($programs as $program) {
            if ($program->sector_id) {
                $map['program:'.$program->id] = (string) $program->sector_id;
            }
        }
        foreach ($projects as $project) {
            if ($project->program?->sector_id) {
                $map['project:'.$project->id] = (string) $project->program->sector_id;
            }
        }
        foreach ($activities as $activity) {
            if ($activity->project?->program?->sector_id) {
                $map['activity:'.$activity->id] = (string) $activity->project->program->sector_id;
            }
        }
        foreach ($subActivities as $subActivity) {
            if ($subActivity->activity?->project?->program?->sector_id) {
                $map['sub_activity:'.$subActivity->id] = (string) $subActivity->activity->project->program->sector_id;
            }
        }

        return $map;
    }

    protected function indicatorAttributes(
        array $validated,
        ?Indicator $indicator,
        ?string $indicatorableType,
        mixed $indicatorableId
    ): array {
        [$existingSourceType] = $this->unpackPrimarySource($indicator?->primary_source);
        $sourceType = array_key_exists('primary_source_type', $validated)
            ? $validated['primary_source_type']
            : $existingSourceType;

        $attributes = [
            'indicatorable_type' => $indicatorableType,
            'indicatorable_id' => $indicatorableId,
            'name' => trim((string) $validated['name']),
            'baseline_value' => $validated['baseline_value'],
            'responsible_user_id' => $validated['responsible_user_id'],
            'responsible_party' => $this->packResponsibleParty([$validated['responsible_user_id']]),
            'frequency_of_reporting_id' => $validated['frequency_of_reporting_id'],
            'unit_id' => $validated['unit_id'],
            'primary_source' => $this->packPrimarySource($sourceType, $validated['data_source']),
            'definitions' => trim((string) $validated['definition']),
        ];

        foreach (['baseline_year', 'baseline_type', 'indicator_level_id', 'methodology', 'notes'] as $optionalField) {
            if (array_key_exists($optionalField, $validated)) {
                $attributes[$optionalField] = $validated[$optionalField];
            }
        }

        if (! $indicator) {
            $attributes['baseline_type'] ??= 'year';
            $attributes['created_by'] = auth()->id();
        }

        return $attributes;
    }

    protected function syncSetupTarget(Indicator $indicator, mixed $targetValue): IndicatorTarget
    {
        $target = $indicator->setupTarget()->firstOrNew();
        $target->indicator_id = $indicator->id;
        $target->target_context = Indicator::SETUP_TARGET_CONTEXT;
        $target->period_type = 'custom';
        $target->period_label = 'Framework target';
        $target->target_value = $targetValue;
        $target->unit_id = $indicator->unit_id;
        $target->notes = 'Expected achievement defined in the Results Framework.';
        $target->updated_by = auth()->id();
        $target->created_by ??= auth()->id();
        $target->save();

        $indicator->setRelation('setupTarget', $target);

        return $target;
    }

    protected function validateIndicatorData(Request $request): array
    {
        $validated = $request->validate([
            'period_type' => 'required|in:year,quarter,month,custom',
            'period_label' => 'nullable|string|max:100',
            'period_start' => 'nullable|date',
            'period_end' => 'nullable|date|after_or_equal:period_start',
            'target_value' => 'nullable|numeric',
            'target_notes' => 'nullable|string|max:1000',
            'actual_value' => 'required|numeric',
            'data_source' => 'nullable|string|max:255',
            'method' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        $periodType = (string) $validated['period_type'];
        $periodLabel = trim((string) ($validated['period_label'] ?? ''));
        $periodStart = trim((string) ($validated['period_start'] ?? ''));

        if ($periodLabel === '' && $periodStart === '') {
            throw ValidationException::withMessages([
                'period_label' => 'Enter a reporting period label or start date.',
            ]);
        }

        $isValidLabel = match ($periodType) {
            'year' => $periodLabel === '' || (bool) preg_match('/^\d{4}$/', $periodLabel),
            'quarter' => $periodLabel === '' || (bool) preg_match('/^\d{4}-Q[1-4]$/', $periodLabel),
            'month' => $periodLabel === '' || (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periodLabel),
            'custom' => true,
            default => false,
        };

        if (! $isValidLabel) {
            throw ValidationException::withMessages([
                'period_label' => 'The reporting period label does not match the selected period type.',
            ]);
        }

        return $validated;
    }

    protected function normalizeReportingPeriod(array $validated): array
    {
        $periodType = (string) $validated['period_type'];
        $periodLabel = trim((string) ($validated['period_label'] ?? ''));
        $periodStart = ! empty($validated['period_start']) ? Carbon::parse($validated['period_start']) : null;
        $periodEnd = ! empty($validated['period_end']) ? Carbon::parse($validated['period_end']) : null;

        if ($periodType === 'year' && $periodLabel !== '') {
            $year = (int) $periodLabel;
            $periodStart = Carbon::create($year, 1, 1)->startOfDay();
            $periodEnd = Carbon::create($year, 12, 31)->startOfDay();
        }

        if ($periodType === 'quarter' && preg_match('/^(\d{4})-Q([1-4])$/', $periodLabel, $matches)) {
            $year = (int) $matches[1];
            $quarter = (int) $matches[2];
            $month = (($quarter - 1) * 3) + 1;
            $periodStart = Carbon::create($year, $month, 1)->startOfDay();
            $periodEnd = $periodStart->copy()->addMonths(2)->endOfMonth()->startOfDay();
        }

        if ($periodType === 'month' && preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $periodLabel, $matches)) {
            $periodStart = Carbon::create((int) $matches[1], (int) $matches[2], 1)->startOfDay();
            $periodEnd = $periodStart->copy()->endOfMonth()->startOfDay();
        }

        if ($periodLabel === '' && $periodStart) {
            $periodLabel = match ($periodType) {
                'year' => $periodStart->format('Y'),
                'quarter' => $periodStart->format('Y').'-Q'.$periodStart->quarter,
                'month' => $periodStart->format('Y-m'),
                default => $periodStart->format('Y-m-d'),
            };
        }

        return [
            $periodLabel !== '' ? $periodLabel : null,
            $periodStart?->toDateString(),
            $periodEnd?->toDateString(),
        ];
    }

    protected function scopeIndicatorsForCurrentPortfolio($query): void
    {
        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToIndicators($query);
        }
    }

    protected function scopeIndicatorConfigurationQuery($query): void
    {
        $query->whereNotNull('portfolio_id');

        if ($this->userHasAssignedPortfolioScope()) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query);
        }
    }

    protected function assertIndicatorInCurrentPortfolioScope(Indicator $indicator): void
    {
        if ($this->userHasAssignedPortfolioScope() && ! $this->indicatorIsInAssignedPortfolio($indicator)) {
            abort(403, 'This indicator is outside your assigned portfolio.');
        }
    }

    protected function scopeIndicatorResultsForCurrentPortfolio($query): void
    {
        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        $query->whereHas('indicator', function ($indicatorQuery) {
            $this->applyAssignedPortfolioScopeToIndicators($indicatorQuery);
        });
    }

    protected function assertIndicatorResultInCurrentPortfolioScope(IndicatorResult $result): void
    {
        $result->loadMissing('indicator');

        if (! $result->indicator) {
            abort(404, 'Indicator data record is missing its indicator.');
        }

        $this->assertIndicatorInCurrentPortfolioScope($result->indicator);
    }

    protected function assertOwnerReferenceInCurrentPortfolioScope(?string $indicatorableType, mixed $indicatorableId): void
    {
        if (! $this->userHasAssignedPortfolioScope()) {
            return;
        }

        $isAllowed = match ($indicatorableType) {
            Sector::class => $indicatorableId && in_array((string) $indicatorableId, $this->assignedPortfolioIds(), true),
            Program::class => $indicatorableId && in_array((string) $indicatorableId, $this->assignedProgramIds(), true),
            Project::class => $indicatorableId && in_array((string) $indicatorableId, $this->assignedProjectIds(), true),
            Activity::class => $indicatorableId && in_array((string) $indicatorableId, $this->assignedActivityIds(), true),
            SubActivity::class => $indicatorableId && in_array((string) $indicatorableId, $this->assignedSubActivityIds(), true),
            default => false,
        };

        if (! $isAllowed) {
            throw ValidationException::withMessages([
                'owner_reference' => 'Select an owner within your assigned portfolio.',
            ]);
        }
    }

    protected function parseOwnerReference(?string $ownerReference): array
    {
        if (! $ownerReference) {
            return [null, null];
        }

        [$type, $id] = array_pad(explode(':', $ownerReference, 2), 2, null);
        if (! $type || ! $id) {
            return [null, null];
        }

        return match ($type) {
            'portfolio' => [Sector::class, $id],
            'program' => [Program::class, $id],
            'project' => [Project::class, $id],
            'activity' => [Activity::class, $id],
            'sub_activity' => [SubActivity::class, $id],
            default => [null, null],
        };
    }

    protected function ownerReferenceForIndicator(?Indicator $indicator): ?string
    {
        if (! $indicator || ! $indicator->indicatorable_type || ! $indicator->indicatorable_id) {
            return null;
        }

        return match ($indicator->indicatorable_type) {
            Sector::class => 'portfolio:'.$indicator->indicatorable_id,
            Program::class => 'program:'.$indicator->indicatorable_id,
            Project::class => 'project:'.$indicator->indicatorable_id,
            Activity::class => 'activity:'.$indicator->indicatorable_id,
            SubActivity::class => 'sub_activity:'.$indicator->indicatorable_id,
            default => null,
        };
    }

    protected function responsibleUserIdsForIndicator(?Indicator $indicator): array
    {
        if (! $indicator) {
            return [];
        }

        $decoded = json_decode((string) $indicator->responsible_party, true);

        return collect([$indicator->responsible_user_id])
            ->merge(is_array($decoded) ? $decoded : [])
            ->filter(fn ($id) => is_scalar($id) && (string) $id !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function packResponsibleParty(array $responsibleUserIds): ?string
    {
        $cleanIds = collect($responsibleUserIds)
            ->filter(fn ($id) => is_scalar($id) && trim((string) $id) !== '')
            ->map(fn ($id) => (string) $id)
            ->unique()
            ->values()
            ->all();

        return empty($cleanIds) ? null : json_encode($cleanIds);
    }

    protected function packPrimarySource(?string $type, ?string $value): ?string
    {
        $sourceType = trim((string) $type);
        $sourceValue = trim((string) $value);

        if ($sourceType === '' && $sourceValue === '') {
            return null;
        }

        if ($sourceType !== '' && $sourceValue !== '') {
            return $sourceType.':'.$sourceValue;
        }

        return $sourceValue !== '' ? $sourceValue : null;
    }

    protected function unpackPrimarySource(?string $source): array
    {
        if (! $source) {
            return [null, null];
        }

        if (! str_contains($source, ':')) {
            return [null, $source];
        }

        [$type, $value] = explode(':', $source, 2);
        if (! in_array($type, ['file_location', 'link', 'external_system_connector'], true)) {
            return [null, $source];
        }

        return [$type, $value];
    }

    protected function resolveDefinitionText(?string $definitionId, ?string $definitionCustom): ?string
    {
        $customText = trim((string) $definitionCustom);
        if ($customText !== '') {
            return $customText;
        }

        if (! $definitionId) {
            return null;
        }

        $definitionQuery = IndicatorDefinition::query()
            ->whereKey($definitionId)
            ->where('is_active', true);
        $this->scopeIndicatorConfigurationQuery($definitionQuery);

        return $definitionQuery->value('name');
    }

    protected function definitionStateForIndicator(?Indicator $indicator, $definitions): array
    {
        $existingDefinition = trim((string) ($indicator?->definitions ?? ''));
        if ($existingDefinition === '') {
            return [null, null];
        }

        $matched = $definitions->first(function ($definition) use ($existingDefinition) {
            return strtolower((string) $definition->name) === strtolower($existingDefinition);
        });

        if ($matched) {
            return [$matched->id, null];
        }

        return [null, $existingDefinition];
    }

    protected function paginatedIndicatorResultRows(Collection $userNamesById, string $pageName, int $perPage)
    {
        $query = IndicatorResult::query()
            ->with([
                'indicator.indicatorable',
                'indicator.level:id,name',
                'indicator.unit:id,name,symbol',
                'indicator.targets:id,indicator_id,target_context,period_type,period_label,period_start,target_value',
                'unit:id,name,symbol',
                'collectedByUser:id,name',
                'validatedByUser:id,name',
                'approvedByUser:id,name',
            ])
            ->latest('created_at');

        $this->scopeIndicatorResultsForCurrentPortfolio($query);

        $rows = $query
            ->paginate($perPage, ['*'], $pageName)
            ->withQueryString();

        $rows->getCollection()->transform(function (IndicatorResult $result) use ($userNamesById) {
            return $this->buildIndicatorResultRow($result, $userNamesById);
        });

        return $rows;
    }

    protected function buildReviewSummary(): array
    {
        $query = IndicatorResult::query();
        $this->scopeIndicatorResultsForCurrentPortfolio($query);

        $counts = $query
            ->selectRaw("COALESCE(review_status, 'submitted') as review_status, COUNT(*) as total")
            ->groupBy('review_status')
            ->pluck('total', 'review_status');

        return [
            'total' => (int) $counts->sum(),
            'submitted' => (int) ($counts['submitted'] ?? 0),
            'validated' => (int) ($counts['validated'] ?? 0),
            'approved' => (int) ($counts['approved'] ?? 0),
        ];
    }

    protected function buildIndicatorChoice(Indicator $indicator): array
    {
        return [
            'id' => $indicator->id,
            'name' => $indicator->name,
            'owner' => $this->ownerLabelForIndicator($indicator),
            'unit' => $indicator->unit?->symbol ?: ($indicator->unit?->name ?: 'Value'),
        ];
    }

    protected function buildIndicatorResultRow(IndicatorResult $result, Collection $userNamesById): array
    {
        $indicator = $result->indicator;
        $target = $indicator ? $this->findMatchingTargetForResult($indicator, $result) : null;
        $targetValue = $target?->target_value;
        $actualValue = $result->actual_value;
        $achievement = null;

        if ($targetValue !== null && is_numeric($targetValue) && (float) $targetValue > 0 && is_numeric($actualValue)) {
            $achievement = round(((float) $actualValue / (float) $targetValue) * 100, 1);
        }

        $reviewStatus = $result->review_status ?: 'submitted';

        return [
            'id' => $result->id,
            'indicator_id' => $indicator?->id,
            'indicator_name' => $indicator?->name ?: 'Unknown indicator',
            'owner' => $indicator ? $this->ownerLabelForIndicator($indicator) : 'Unlinked',
            'level' => $indicator?->level?->name ?: 'Unassigned',
            'period' => $this->formatResultPeriod($result),
            'target' => $this->formatMetric($targetValue),
            'actual' => $this->formatMetric($actualValue),
            'achievement' => $achievement !== null ? $achievement.'%' : 'N/A',
            'unit' => $result->unit?->symbol ?: ($result->unit?->name ?: ($indicator?->unit?->symbol ?: $indicator?->unit?->name)),
            'data_source' => $result->data_source ?: 'N/A',
            'method' => $result->method ?: 'N/A',
            'notes' => $result->notes ?: 'N/A',
            'review_notes' => $result->review_notes ?: '',
            'review_status' => $reviewStatus,
            'review_status_label' => $this->formatReviewStatus($reviewStatus),
            'review_status_class' => $this->reviewStatusClass($reviewStatus),
            'collected_by' => $result->collectedByUser?->name
                ?: ($result->collected_by ? $userNamesById->get((string) $result->collected_by, 'Unknown') : 'N/A'),
            'collected_at' => $this->formatDateTime($result->collected_at),
            'validated_by' => $result->validatedByUser?->name ?: 'N/A',
            'validated_at' => $this->formatDateTime($result->validated_at),
            'approved_by' => $result->approvedByUser?->name ?: 'N/A',
            'approved_at' => $this->formatDateTime($result->approved_at),
        ];
    }

    protected function findMatchingTargetForResult(Indicator $indicator, IndicatorResult $result): ?IndicatorTarget
    {
        return $indicator->targets
            ->whereNull('target_context')
            ->first(function (IndicatorTarget $target) use ($result) {
                if ($target->period_type !== $result->period_type) {
                    return false;
                }

                if ($target->period_label && $result->period_label) {
                    return $target->period_label === $result->period_label;
                }

                return optional($target->period_start)->toDateString() === optional($result->period_start)->toDateString();
            });
    }

    protected function formatResultPeriod(IndicatorResult $result): string
    {
        if ($result->period_label) {
            return $result->period_label;
        }

        if ($result->period_start && $result->period_end) {
            return $result->period_start->format('Y-m-d').' to '.$result->period_end->format('Y-m-d');
        }

        if ($result->period_start) {
            return $result->period_start->format('Y-m-d');
        }

        return 'N/A';
    }

    protected function ownerLabelForIndicator(Indicator $indicator): string
    {
        if ($indicator->indicatorable_type === Sector::class) {
            return 'Portfolio: '.($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === Program::class) {
            return 'Program: '.($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === Project::class) {
            return 'Project: '.($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === Activity::class) {
            return 'Activity: '.($indicator->indicatorable?->name ?: 'Unknown');
        }

        if ($indicator->indicatorable_type === SubActivity::class) {
            return 'Sub-Activity: '.($indicator->indicatorable?->name ?: 'Unknown');
        }

        return 'Unlinked';
    }

    protected function formatReviewStatus(?string $status): string
    {
        return match ($status ?: 'submitted') {
            'validated' => 'Validated',
            'approved' => 'Approved',
            default => 'Submitted',
        };
    }

    protected function reviewStatusClass(?string $status): string
    {
        return match ($status ?: 'submitted') {
            'approved' => 'success',
            'validated' => 'primary',
            default => 'warning',
        };
    }

    protected function formatDateTime(mixed $value): string
    {
        if (! $value) {
            return 'N/A';
        }

        return Carbon::parse($value)->format('Y-m-d H:i');
    }

    protected function collectIndicatorsWithRelations(): Collection
    {
        $query = Indicator::with([
            'indicatorable',
            'level:id,name',
            'targets:id,indicator_id,target_value,target_context,period_type,period_label,period_start',
            'results:id,indicator_id,actual_value,period_type,period_label,period_start,review_status,validated_at,approved_at',
            'frequency:id,name',
            'unit:id,name,symbol',
        ]);
        $this->scopeIndicatorsForCurrentPortfolio($query);
        $indicators = $query->get();

        $indicators->loadMorph('indicatorable', [
            Sector::class => [],
            Program::class => ['sector:id,name'],
            Project::class => ['program:id,program_id,name,sector_id', 'program.sector:id,name'],
            Activity::class => ['project:id,name,project_id,program_id', 'project.program:id,program_id,name,sector_id', 'project.program.sector:id,name'],
            SubActivity::class => [
                'activity:id,name,project_id',
                'activity.project:id,name,project_id,program_id',
                'activity.project.program:id,program_id,name,sector_id',
                'activity.project.program.sector:id,name',
            ],
        ]);

        return $indicators;
    }

    protected function buildManagementReportRows(
        Collection $allIndicators,
        Collection $statusRowsById,
        Collection $userNamesById,
        ?string $searchTerm = null
    ): Collection {
        $rows = $allIndicators
            ->map(function (Indicator $indicator) use ($statusRowsById, $userNamesById) {
                $hierarchy = $this->resolveHierarchyForIndicator($indicator);
                $status = $statusRowsById->get($indicator->id, []);
                [$sourceType, $sourceValue] = $this->unpackPrimarySource($indicator->primary_source);

                $unitLabel = $indicator->unit?->symbol ?: $indicator->unit?->name;
                $baselineValue = $this->formatMetric($indicator->baseline_value);
                if ($baselineValue !== '—' && $unitLabel) {
                    $baselineValue .= ' '.$unitLabel;
                }

                return [
                    'portfolio_key' => $hierarchy['portfolio_key'],
                    'portfolio' => $hierarchy['portfolio'],
                    'program_key' => $hierarchy['program_key'],
                    'program' => $hierarchy['program'],
                    'project_key' => $hierarchy['project_key'],
                    'project' => $hierarchy['project'],
                    'activity_key' => $hierarchy['activity_key'],
                    'activity' => $hierarchy['activity'],
                    'sub_activity_key' => $hierarchy['sub_activity_key'],
                    'sub_activity' => $hierarchy['sub_activity'],
                    'owner_type' => $hierarchy['owner_type'],
                    'indicator_name' => $indicator->name,
                    'indicator_level' => $indicator->level?->name ?: '—',
                    'frequency' => $indicator->frequency?->name ?: '—',
                    'baseline_type' => $indicator->baseline_type ? ucfirst($indicator->baseline_type) : '—',
                    'baseline_period' => $indicator->baseline_year ?: '—',
                    'baseline_value' => $baselineValue,
                    'responsible' => $this->formatResponsiblePartyForDisplay($indicator->responsible_party, $userNamesById),
                    'methodology' => $indicator->methodology ?: '—',
                    'primary_source_type' => $sourceType ? ucwords(str_replace('_', ' ', $sourceType)) : '—',
                    'primary_source_value' => $sourceValue ?: '—',
                    'definition' => $indicator->definitions ?: '—',
                    'target' => $this->formatMetric($status['target'] ?? null),
                    'actual' => $this->formatMetric($status['actual'] ?? null),
                    'achievement' => isset($status['achievement']) ? $status['achievement'].'%' : '—',
                    'status' => $status['status'] ?? 'Not Started',
                    'status_class' => $status['status_class'] ?? 'secondary',
                    'data_review_status' => $status['data_review_status'] ?? 'No Data',
                    'validated_at' => $status['validated_at'] ?? 'N/A',
                    'approved_at' => $status['approved_at'] ?? 'N/A',
                    'notes' => $indicator->notes ?: '—',
                ];
            })
            ->sortBy(function (array $row) {
                return strtolower(implode('|', [
                    $row['portfolio_key'],
                    $row['program_key'],
                    $row['project_key'],
                    $row['activity_key'],
                    $row['sub_activity_key'],
                    $row['indicator_name'],
                ]));
            })
            ->values();

        $query = strtolower(trim((string) $searchTerm));
        if ($query === '') {
            return $rows;
        }

        return $rows
            ->filter(function (array $row) use ($query) {
                $haystack = strtolower(implode(' ', [
                    $row['portfolio'] ?? '',
                    $row['program'] ?? '',
                    $row['project'] ?? '',
                    $row['activity'] ?? '',
                    $row['sub_activity'] ?? '',
                    $row['indicator_name'] ?? '',
                    $row['owner_type'] ?? '',
                    $row['indicator_level'] ?? '',
                    $row['frequency'] ?? '',
                    $row['baseline_type'] ?? '',
                    $row['baseline_period'] ?? '',
                    $row['baseline_value'] ?? '',
                    $row['responsible'] ?? '',
                    $row['methodology'] ?? '',
                    $row['primary_source_type'] ?? '',
                    $row['primary_source_value'] ?? '',
                    $row['definition'] ?? '',
                    $row['target'] ?? '',
                    $row['actual'] ?? '',
                    $row['achievement'] ?? '',
                    $row['status'] ?? '',
                    $row['data_review_status'] ?? '',
                    $row['validated_at'] ?? '',
                    $row['approved_at'] ?? '',
                    $row['notes'] ?? '',
                ]));

                return str_contains($haystack, $query);
            })
            ->values();
    }

    protected function resolveHierarchyForIndicator(Indicator $indicator): array
    {
        $fallback = [
            'portfolio_key' => 'zzzz-unlinked',
            'portfolio' => 'Unlinked Indicators',
            'program_key' => 'zzzz-unlinked',
            'program' => 'Unlinked Indicators',
            'project_key' => 'zzzz-unlinked',
            'project' => '—',
            'activity_key' => 'zzzz-unlinked',
            'activity' => '—',
            'sub_activity_key' => 'zzzz-unlinked',
            'sub_activity' => '—',
            'owner_type' => 'Unlinked',
        ];

        if (! $indicator->indicatorable_type || ! $indicator->indicatorable) {
            return $fallback;
        }

        if ($indicator->indicatorable_type === Sector::class) {
            /** @var Sector $portfolio */
            $portfolio = $indicator->indicatorable;

            return [
                'portfolio_key' => strtolower((string) $portfolio->id),
                'portfolio' => $portfolio->name ?: 'Unnamed Portfolio',
                'program_key' => '0000-portfolio',
                'program' => '-',
                'project_key' => '0000-portfolio',
                'project' => '-',
                'activity_key' => '0000-portfolio',
                'activity' => '-',
                'sub_activity_key' => '0000-portfolio',
                'sub_activity' => '-',
                'owner_type' => 'Portfolio',
            ];
        }

        if ($indicator->indicatorable_type === Program::class) {
            /** @var Program $program */
            $program = $indicator->indicatorable;
            $portfolio = $program->sector;

            return [
                'portfolio_key' => strtolower((string) ($portfolio?->id ?: 'zzzy-missing-portfolio')),
                'portfolio' => $portfolio?->name ?: 'Portfolio Not Linked',
                'program_key' => strtolower((string) ($program->program_id ?: $program->id)),
                'program' => trim((string) (($program->program_id ? $program->program_id.' - ' : '').$program->name)),
                'project_key' => '0000-program',
                'project' => '—',
                'activity_key' => '0000-program',
                'activity' => '—',
                'sub_activity_key' => '0000-program',
                'sub_activity' => '—',
                'owner_type' => 'Program',
            ];
        }

        if ($indicator->indicatorable_type === Project::class) {
            /** @var Project $project */
            $project = $indicator->indicatorable;
            $program = $project->program;
            $portfolio = $program?->sector;

            return [
                'portfolio_key' => strtolower((string) ($portfolio?->id ?: 'zzzy-missing-portfolio')),
                'portfolio' => $portfolio?->name ?: 'Portfolio Not Linked',
                'program_key' => strtolower((string) ($program?->program_id ?: $program?->id ?: 'zzzy-missing-program')),
                'program' => $program
                    ? trim((string) (($program->program_id ? $program->program_id.' - ' : '').$program->name))
                    : 'Program Not Linked',
                'project_key' => strtolower((string) ($project->project_id ?: $project->id)),
                'project' => trim((string) (($project->project_id ? $project->project_id.' - ' : '').$project->name)),
                'activity_key' => '0001-no-activity',
                'activity' => '—',
                'sub_activity_key' => '0001-no-sub-activity',
                'sub_activity' => '—',
                'owner_type' => 'Project',
            ];
        }

        if ($indicator->indicatorable_type === Activity::class) {
            /** @var Activity $activity */
            $activity = $indicator->indicatorable;
            $project = $activity->project;
            $program = $project?->program;
            $portfolio = $program?->sector;

            return [
                'portfolio_key' => strtolower((string) ($portfolio?->id ?: 'zzzy-missing-portfolio')),
                'portfolio' => $portfolio?->name ?: 'Portfolio Not Linked',
                'program_key' => strtolower((string) ($program?->program_id ?: $program?->id ?: 'zzzy-missing-program')),
                'program' => $program
                    ? trim((string) (($program->program_id ? $program->program_id.' - ' : '').$program->name))
                    : 'Program Not Linked',
                'project_key' => strtolower((string) ($project?->project_id ?: $project?->id ?: 'zzzy-missing-project')),
                'project' => $project
                    ? trim((string) (($project->project_id ? $project->project_id.' - ' : '').$project->name))
                    : 'Project Not Linked',
                'activity_key' => strtolower((string) $activity->id),
                'activity' => $activity->name ?: 'Unnamed Activity',
                'sub_activity_key' => '0001-no-sub-activity',
                'sub_activity' => '—',
                'owner_type' => 'Activity',
            ];
        }

        if ($indicator->indicatorable_type === SubActivity::class) {
            /** @var SubActivity $subActivity */
            $subActivity = $indicator->indicatorable;
            $activity = $subActivity->activity;
            $project = $activity?->project;
            $program = $project?->program;
            $portfolio = $program?->sector;

            return [
                'portfolio_key' => strtolower((string) ($portfolio?->id ?: 'zzzy-missing-portfolio')),
                'portfolio' => $portfolio?->name ?: 'Portfolio Not Linked',
                'program_key' => strtolower((string) ($program?->program_id ?: $program?->id ?: 'zzzy-missing-program')),
                'program' => $program
                    ? trim((string) (($program->program_id ? $program->program_id.' - ' : '').$program->name))
                    : 'Program Not Linked',
                'project_key' => strtolower((string) ($project?->project_id ?: $project?->id ?: 'zzzy-missing-project')),
                'project' => $project
                    ? trim((string) (($project->project_id ? $project->project_id.' - ' : '').$project->name))
                    : 'Project Not Linked',
                'activity_key' => strtolower((string) ($activity?->id ?: 'zzzy-missing-activity')),
                'activity' => $activity?->name ?: 'Activity Not Linked',
                'sub_activity_key' => strtolower((string) $subActivity->id),
                'sub_activity' => $subActivity->name ?: 'Unnamed Sub-Activity',
                'owner_type' => 'Sub-Activity',
            ];
        }

        return $fallback;
    }

    protected function formatResponsiblePartyForDisplay(?string $value, $userNamesById): string
    {
        if (! $value) {
            return '—';
        }

        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $names = collect($decoded)
                ->filter(fn ($id) => is_scalar($id) && trim((string) $id) !== '')
                ->map(fn ($id) => $userNamesById->get((string) $id, (string) $id))
                ->unique()
                ->values()
                ->all();

            return empty($names) ? '—' : implode(', ', $names);
        }

        return trim($value) !== '' ? trim($value) : '—';
    }

    protected function formatMetric(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (is_numeric($value)) {
            return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }

        return (string) $value;
    }

    protected function methodologyHasSurveyConfig(IndicatorMethodology $methodology): bool
    {
        return MeSurvey::hasEnabledQuestions(
            (array) ($methodology->metadata ?? []),
            trim((string) $methodology->name) !== '' ? ($methodology->name.' Public Survey') : 'Public Survey'
        );
    }

    protected function resolveSurveyMethodologyByIndicator(Indicator $indicator): ?IndicatorMethodology
    {
        $methodologyName = strtolower(trim((string) $indicator->methodology));
        if ($methodologyName === '') {
            return null;
        }

        $methodologyQuery = IndicatorMethodology::query()
            ->where('is_active', true)
            ->orderBy('name');
        $this->scopeIndicatorConfigurationQuery($methodologyQuery);

        $methodology = $methodologyQuery->get()
            ->first(function (IndicatorMethodology $item) use ($methodologyName) {
                return strtolower(trim((string) $item->name)) === $methodologyName;
            });

        if (! $methodology || ! $this->methodologyHasSurveyConfig($methodology)) {
            return null;
        }

        return $methodology;
    }

    protected function syncSurveyLinkForIndicator(Indicator $indicator): void
    {
        $surveyMethodology = $this->resolveSurveyMethodologyByIndicator($indicator);
        if (! $surveyMethodology) {
            IndicatorSurveyLink::query()
                ->where('indicator_id', $indicator->id)
                ->update([
                    'is_active' => false,
                    'updated_by' => auth()->id(),
                ]);

            return;
        }

        $link = IndicatorSurveyLink::query()->firstOrNew([
            'indicator_id' => $indicator->id,
        ]);

        $isNew = ! $link->exists;
        $refreshToken = $isNew || ! $link->public_token || $link->methodology_id !== $surveyMethodology->id;

        $link->methodology_id = $surveyMethodology->id;
        $link->is_active = true;
        $link->updated_by = auth()->id();

        if ($isNew) {
            $link->created_by = auth()->id();
        }
        if ($refreshToken) {
            $link->public_token = Str::random(64);
        }

        $link->save();
    }

    protected function buildStatusRow(Indicator $indicator): array
    {
        $latestTarget = $indicator->targets
            ->whereNull('target_context')
            ->sortByDesc('period_start')
            ->first()
            ?? $indicator->targets->firstWhere('target_context', Indicator::SETUP_TARGET_CONTEXT);
        $latestAnyResult = $indicator->results->sortByDesc('period_start')->first();
        $latestApprovedResult = $indicator->results
            ->where('review_status', 'approved')
            ->sortByDesc('period_start')
            ->first();
        $latestResult = $latestApprovedResult ?: $latestAnyResult;

        $status = 'Not Started';
        $statusClass = 'secondary';
        $statusKey = 'not_started';
        $achievement = null;
        $reviewStatus = $latestAnyResult?->review_status ?: null;

        if ($latestAnyResult && $reviewStatus !== 'approved') {
            if ($reviewStatus === 'validated') {
                $status = 'Awaiting Approval';
                $statusClass = 'primary';
                $statusKey = 'awaiting_approval';
            } else {
                $status = 'Awaiting Validation';
                $statusClass = 'warning';
                $statusKey = 'awaiting_validation';
            }
        } elseif ($latestTarget && ! $latestResult) {
            $status = 'Pending Reporting';
            $statusClass = 'warning';
            $statusKey = 'pending';
        } elseif (! $latestTarget && $latestResult) {
            $status = 'Reported (No Target)';
            $statusClass = 'info';
            $statusKey = 'reported_without_target';
        } elseif ($latestTarget && $latestResult) {
            $target = (float) $latestTarget->target_value;
            $actual = (float) $latestResult->actual_value;
            $achievement = $target > 0 ? round(($actual / $target) * 100, 1) : null;

            if ($achievement !== null && $achievement >= 100) {
                $status = 'Achieved';
                $statusClass = 'success';
                $statusKey = 'achieved';
            } elseif ($achievement !== null && $achievement >= 80) {
                $status = 'On Track';
                $statusClass = 'primary';
                $statusKey = 'on_track';
            } else {
                $status = 'Behind';
                $statusClass = 'danger';
                $statusKey = 'behind';
            }
        }

        $owner = 'Unlinked';
        if ($indicator->indicatorable_type === Sector::class) {
            $owner = 'Portfolio: '.($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === Program::class) {
            $owner = 'Program: '.($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === Project::class) {
            $owner = 'Project: '.($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === Activity::class) {
            $owner = 'Activity: '.($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === SubActivity::class) {
            $owner = 'Sub-Activity: '.($indicator->indicatorable?->name ?: 'Unknown');
        }

        return [
            'id' => $indicator->id,
            'name' => $indicator->name,
            'owner' => $owner,
            'level' => $indicator->level?->name ?: 'Unassigned',
            'target' => $latestTarget?->target_value,
            'actual' => $latestResult?->actual_value,
            'achievement' => $achievement,
            'status' => $status,
            'status_class' => $statusClass,
            'status_key' => $statusKey,
            'data_review_status' => $latestAnyResult ? $this->formatReviewStatus($reviewStatus) : 'No Data',
            'validated_at' => $this->formatDateTime($latestAnyResult?->validated_at),
            'approved_at' => $this->formatDateTime($latestAnyResult?->approved_at),
        ];
    }
}
