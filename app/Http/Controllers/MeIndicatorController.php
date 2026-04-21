<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Exports\MeIndicatorsManagementReportExport;
use App\Models\Indicator;
use App\Models\IndicatorDefinition;
use App\Models\IndicatorLevel;
use App\Models\IndicatorMethodology;
use App\Models\IndicatorSurveyLink;
use App\Models\IndicatorUnit;
use App\Models\Program;
use App\Models\Project;
use App\Models\ReportingFrequency;
use App\Models\SubActivity;
use App\Models\User;
use App\Support\MeSurvey;
use Barryvdh\DomPDF\Facade\Pdf;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class MeIndicatorController extends Controller
{
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
        ]);
    }

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'description');
        if (!in_array($tab, ['description', 'settings', 'pictorial', 'status'], true)) {
            $tab = 'description';
        }

        $users = User::query()
            ->where(function ($query) {
                $query->whereNull('user_type')
                    ->orWhere('user_type', '!=', 'funding_partner');
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);
        $userNamesById = $users->pluck('name', 'id');

        $indicators = Indicator::with([
            'indicatorable',
            'level:id,name',
            'frequency:id,name',
            'unit:id,name,symbol',
            'targets:id,indicator_id,target_value,period_start',
            'results:id,indicator_id,actual_value,period_start',
        ])->withCount('surveyResponses')->latest()->paginate(20)->withQueryString();

        $editingIndicator = null;
        if ($request->filled('edit')) {
            $editingIndicator = Indicator::find($request->query('edit'));
        }

        $allIndicators = $this->collectIndicatorsWithRelations();

        $statusRows = $allIndicators
            ->map(fn (Indicator $indicator) => $this->buildStatusRow($indicator))
            ->values();
        $statusRowsById = $statusRows->keyBy('id');

        $statusSummary = [
            'total' => $statusRows->count(),
            'achieved' => $statusRows->where('status_key', 'achieved')->count(),
            'on_track' => $statusRows->where('status_key', 'on_track')->count(),
            'behind' => $statusRows->where('status_key', 'behind')->count(),
            'pending' => $statusRows->where('status_key', 'pending')->count(),
            'not_started' => $statusRows->where('status_key', 'not_started')->count(),
            'reported_without_target' => $statusRows->where('status_key', 'reported_without_target')->count(),
        ];

        $levelBreakdown = $allIndicators
            ->groupBy(fn (Indicator $indicator) => $indicator->level?->name ?: 'Unassigned')
            ->map(fn ($rows) => $rows->count())
            ->sortDesc();

        $ownershipBreakdown = collect([
            'Program-linked' => $allIndicators->where('indicatorable_type', Program::class)->count(),
            'Project-linked' => $allIndicators->where('indicatorable_type', Project::class)->count(),
            'Activity-linked' => $allIndicators->where('indicatorable_type', Activity::class)->count(),
            'Sub-Activity-linked' => $allIndicators->where('indicatorable_type', SubActivity::class)->count(),
            'Unlinked' => $allIndicators->filter(function (Indicator $indicator) {
                return empty($indicator->indicatorable_type) || empty($indicator->indicatorable_id);
            })->count(),
        ]);

        $managementReportRows = $this->buildManagementReportRows(
            $allIndicators,
            $statusRowsById,
            $userNamesById
        );

        $programs = Program::orderBy('name')->get(['id', 'program_id', 'name']);
        $projects = Project::orderBy('name')->get(['id', 'project_id', 'name', 'program_id']);
        $activities = Activity::with([
            'project:id,name,project_id',
        ])->orderBy('name')->get(['id', 'name', 'project_id']);
        $subActivities = SubActivity::with([
            'activity:id,name,project_id',
            'activity.project:id,name,project_id',
        ])->orderBy('name')->get(['id', 'name', 'activity_id']);
        $levels = IndicatorLevel::active()->ordered()->get(['id', 'name']);
        $frequencies = ReportingFrequency::active()->ordered()->get(['id', 'name']);
        $units = IndicatorUnit::active()->ordered()->get(['id', 'name', 'symbol']);
        $methodologies = IndicatorMethodology::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'metadata']);
        $definitions = IndicatorDefinition::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $surveyMethodologyNames = $methodologies
            ->filter(fn (IndicatorMethodology $methodology) => $this->methodologyHasSurveyConfig($methodology))
            ->mapWithKeys(function (IndicatorMethodology $methodology) {
                return [strtolower(trim((string) $methodology->name)) => true];
            });

        $surveyLinksByIndicatorId = IndicatorSurveyLink::query()
            ->whereIn('indicator_id', $indicators->getCollection()->pluck('id')->all())
            ->where('is_active', true)
            ->get(['indicator_id', 'public_token'])
            ->keyBy('indicator_id');

        $surveyStatusByIndicatorId = $indicators->getCollection()
            ->mapWithKeys(function (Indicator $indicator) use ($surveyMethodologyNames, $surveyLinksByIndicatorId) {
                $methodologyKey = strtolower(trim((string) $indicator->methodology));
                $isSurvey = $methodologyKey !== '' && $surveyMethodologyNames->has($methodologyKey);
                $link = $surveyLinksByIndicatorId->get($indicator->id);

                return [
                    $indicator->id => [
                        'is_survey' => $isSurvey,
                        'has_link' => (bool) $link,
                        'public_url' => $link
                            ? route('public.me.indicators.surveys.show', ['token' => $link->public_token])
                            : null,
                    ],
                ];
            });

        $editingOwnerReference = $this->ownerReferenceForIndicator($editingIndicator);
        $editingResponsibleUserIds = $this->responsibleUserIdsForIndicator($editingIndicator);
        [$editingPrimarySourceType, $editingPrimarySourceValue] = $this->unpackPrimarySource(
            $editingIndicator?->primary_source
        );
        [$editingDefinitionId, $editingDefinitionCustom] = $this->definitionStateForIndicator(
            $editingIndicator,
            $definitions
        );

        return view('me.indicators.index', compact(
            'tab',
            'indicators',
            'editingIndicator',
            'editingOwnerReference',
            'editingResponsibleUserIds',
            'editingPrimarySourceType',
            'editingPrimarySourceValue',
            'editingDefinitionId',
            'editingDefinitionCustom',
            'statusRows',
            'statusSummary',
            'levelBreakdown',
            'ownershipBreakdown',
            'managementReportRows',
            'programs',
            'projects',
            'activities',
            'subActivities',
            'levels',
            'frequencies',
            'units',
            'users',
            'methodologies',
            'definitions',
            'surveyStatusByIndicatorId'
        ));
    }

    public function store(Request $request)
    {
        $validated = $this->validateIndicator($request);
        [$indicatorableType, $indicatorableId] = $this->parseOwnerReference($validated['owner_reference'] ?? null);
        $responsibleParty = $this->packResponsibleParty($validated['responsible_user_ids'] ?? []);
        $primarySource = $this->packPrimarySource(
            $validated['primary_source_type'] ?? null,
            $validated['primary_source_value'] ?? null
        );
        $definitions = $this->resolveDefinitionText(
            $validated['definition_id'] ?? null,
            $validated['definition_custom'] ?? null
        );

        $indicator = Indicator::create([
            'indicatorable_type' => $indicatorableType,
            'indicatorable_id' => $indicatorableId,
            'name' => $validated['name'],
            'baseline_year' => $validated['baseline_year'] ?? null,
            'baseline_type' => $validated['baseline_type'] ?? 'year',
            'baseline_value' => $validated['baseline_value'] ?? null,
            'indicator_level_id' => $validated['indicator_level_id'] ?? null,
            'methodology' => $validated['methodology'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'responsible_party' => $responsibleParty,
            'frequency_of_reporting_id' => $validated['frequency_of_reporting_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'primary_source' => $primarySource,
            'definitions' => $definitions,
            'created_by' => auth()->id(),
        ]);

        $this->syncSurveyLinkForIndicator($indicator);

        return redirect()
            ->route('budget.me.indicators.index', ['tab' => 'settings'])
            ->with('success', 'Indicator created successfully.');
    }

    public function update(Request $request, Indicator $indicator)
    {
        $validated = $this->validateIndicator($request);
        [$indicatorableType, $indicatorableId] = $this->parseOwnerReference($validated['owner_reference'] ?? null);
        $responsibleParty = $this->packResponsibleParty($validated['responsible_user_ids'] ?? []);
        $primarySource = $this->packPrimarySource(
            $validated['primary_source_type'] ?? null,
            $validated['primary_source_value'] ?? null
        );
        $definitions = $this->resolveDefinitionText(
            $validated['definition_id'] ?? null,
            $validated['definition_custom'] ?? null
        );

        $indicator->update([
            'indicatorable_type' => $indicatorableType,
            'indicatorable_id' => $indicatorableId,
            'name' => $validated['name'],
            'baseline_year' => $validated['baseline_year'] ?? null,
            'baseline_type' => $validated['baseline_type'] ?? 'year',
            'baseline_value' => $validated['baseline_value'] ?? null,
            'indicator_level_id' => $validated['indicator_level_id'] ?? null,
            'methodology' => $validated['methodology'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'responsible_party' => $responsibleParty,
            'frequency_of_reporting_id' => $validated['frequency_of_reporting_id'] ?? null,
            'unit_id' => $validated['unit_id'] ?? null,
            'primary_source' => $primarySource,
            'definitions' => $definitions,
        ]);

        $this->syncSurveyLinkForIndicator($indicator);

        return redirect()
            ->route('budget.me.indicators.index', ['tab' => 'settings'])
            ->with('success', 'Indicator updated successfully.');
    }

    public function destroy(Indicator $indicator)
    {
        $indicator->delete();

        return redirect()
            ->route('budget.me.indicators.index', ['tab' => 'settings'])
            ->with('success', 'Indicator deleted successfully.');
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

        $filename = 'me-management-report-' . now()->format('Ymd_His') . '.xlsx';

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

        return $pdf->download('me-management-report-' . now()->format('Ymd_His') . '.pdf');
    }

    protected function validateIndicator(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'owner_reference' => [
                'nullable',
                'string',
                'max:100',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (empty($value)) {
                        return;
                    }

                    [$type, $id] = array_pad(explode(':', (string) $value, 2), 2, null);
                    if (!$type || !$id || !in_array($type, ['program', 'project', 'activity', 'sub_activity'], true)) {
                        $fail('Please select a valid owner (Program, Project, Activity, or Sub-Activity).');
                        return;
                    }

                    $exists = match ($type) {
                        'program' => Program::whereKey($id)->exists(),
                        'project' => Project::whereKey($id)->exists(),
                        'activity' => Activity::whereKey($id)->exists(),
                        'sub_activity' => SubActivity::whereKey($id)->exists(),
                        default => false,
                    };

                    if (!$exists) {
                        $fail('Selected owner record does not exist.');
                    }
                },
            ],
            'baseline_year' => [
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

                    if (!$isValid) {
                        $fail('Baseline period format does not match the selected baseline type.');
                    }
                },
            ],
            'baseline_type' => 'nullable|in:year,month,quarter,week,day',
            'baseline_value' => 'nullable|numeric',
            'indicator_level_id' => 'nullable|exists:me_indicator_levels,id',
            'methodology' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'responsible_user_ids' => 'nullable|array|max:6',
            'responsible_user_ids.*' => 'exists:users,id',
            'frequency_of_reporting_id' => 'nullable|exists:me_reporting_frequencies,id',
            'unit_id' => 'nullable|exists:me_indicator_units,id',
            'primary_source_type' => 'nullable|in:file_location,link,external_system_connector',
            'primary_source_value' => [
                'nullable',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    $type = (string) $request->input('primary_source_type', '');
                    $sourceValue = trim((string) $value);

                    if ($type !== '' && $sourceValue === '') {
                        $fail('Primary source value is required when source type is selected.');
                        return;
                    }

                    if ($type === 'link' && $sourceValue !== '' && !filter_var($sourceValue, FILTER_VALIDATE_URL)) {
                        $fail('Primary source link must be a valid URL.');
                    }
                },
            ],
            'definition_id' => 'nullable|exists:indicator_definitions,id',
            'definition_custom' => 'nullable|string',
        ]);
    }

    protected function parseOwnerReference(?string $ownerReference): array
    {
        if (!$ownerReference) {
            return [null, null];
        }

        [$type, $id] = array_pad(explode(':', $ownerReference, 2), 2, null);
        if (!$type || !$id) {
            return [null, null];
        }

        return match ($type) {
            'program' => [Program::class, $id],
            'project' => [Project::class, $id],
            'activity' => [Activity::class, $id],
            'sub_activity' => [SubActivity::class, $id],
            default => [null, null],
        };
    }

    protected function ownerReferenceForIndicator(?Indicator $indicator): ?string
    {
        if (!$indicator || !$indicator->indicatorable_type || !$indicator->indicatorable_id) {
            return null;
        }

        return match ($indicator->indicatorable_type) {
            Program::class => 'program:' . $indicator->indicatorable_id,
            Project::class => 'project:' . $indicator->indicatorable_id,
            Activity::class => 'activity:' . $indicator->indicatorable_id,
            SubActivity::class => 'sub_activity:' . $indicator->indicatorable_id,
            default => null,
        };
    }

    protected function responsibleUserIdsForIndicator(?Indicator $indicator): array
    {
        if (!$indicator || !$indicator->responsible_party) {
            return [];
        }

        $decoded = json_decode($indicator->responsible_party, true);
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
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
            return $sourceType . ':' . $sourceValue;
        }

        return $sourceValue !== '' ? $sourceValue : null;
    }

    protected function unpackPrimarySource(?string $source): array
    {
        if (!$source) {
            return [null, null];
        }

        if (!str_contains($source, ':')) {
            return [null, $source];
        }

        [$type, $value] = explode(':', $source, 2);
        if (!in_array($type, ['file_location', 'link', 'external_system_connector'], true)) {
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

        if (!$definitionId) {
            return null;
        }

        return IndicatorDefinition::query()
            ->whereKey($definitionId)
            ->value('name');
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

    protected function collectIndicatorsWithRelations(): Collection
    {
        $indicators = Indicator::with([
            'indicatorable',
            'level:id,name',
            'targets:id,indicator_id,target_value,period_start',
            'results:id,indicator_id,actual_value,period_start',
            'frequency:id,name',
            'unit:id,name,symbol',
        ])->get();

        $indicators->loadMorph('indicatorable', [
            Program::class => [],
            Project::class => ['program:id,program_id,name'],
            Activity::class => ['project:id,name,project_id,program_id', 'project.program:id,program_id,name'],
            SubActivity::class => [
                'activity:id,name,project_id',
                'activity.project:id,name,project_id,program_id',
                'activity.project.program:id,program_id,name',
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
                    $baselineValue .= ' ' . $unitLabel;
                }

                return [
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
                    'achievement' => isset($status['achievement']) ? $status['achievement'] . '%' : '—',
                    'status' => $status['status'] ?? 'Not Started',
                    'status_class' => $status['status_class'] ?? 'secondary',
                    'notes' => $indicator->notes ?: '—',
                ];
            })
            ->sortBy(function (array $row) {
                return strtolower(implode('|', [
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
                    $row['notes'] ?? '',
                ]));

                return str_contains($haystack, $query);
            })
            ->values();
    }

    protected function resolveHierarchyForIndicator(Indicator $indicator): array
    {
        $fallback = [
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

        if (!$indicator->indicatorable_type || !$indicator->indicatorable) {
            return $fallback;
        }

        if ($indicator->indicatorable_type === Program::class) {
            /** @var Program $program */
            $program = $indicator->indicatorable;

            return [
                'program_key' => strtolower((string) ($program->program_id ?: $program->id)),
                'program' => trim((string) (($program->program_id ? $program->program_id . ' - ' : '') . $program->name)),
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

            return [
                'program_key' => strtolower((string) ($program?->program_id ?: $program?->id ?: 'zzzy-missing-program')),
                'program' => $program
                    ? trim((string) (($program->program_id ? $program->program_id . ' - ' : '') . $program->name))
                    : 'Program Not Linked',
                'project_key' => strtolower((string) ($project->project_id ?: $project->id)),
                'project' => trim((string) (($project->project_id ? $project->project_id . ' - ' : '') . $project->name)),
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

            return [
                'program_key' => strtolower((string) ($program?->program_id ?: $program?->id ?: 'zzzy-missing-program')),
                'program' => $program
                    ? trim((string) (($program->program_id ? $program->program_id . ' - ' : '') . $program->name))
                    : 'Program Not Linked',
                'project_key' => strtolower((string) ($project?->project_id ?: $project?->id ?: 'zzzy-missing-project')),
                'project' => $project
                    ? trim((string) (($project->project_id ? $project->project_id . ' - ' : '') . $project->name))
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

            return [
                'program_key' => strtolower((string) ($program?->program_id ?: $program?->id ?: 'zzzy-missing-program')),
                'program' => $program
                    ? trim((string) (($program->program_id ? $program->program_id . ' - ' : '') . $program->name))
                    : 'Program Not Linked',
                'project_key' => strtolower((string) ($project?->project_id ?: $project?->id ?: 'zzzy-missing-project')),
                'project' => $project
                    ? trim((string) (($project->project_id ? $project->project_id . ' - ' : '') . $project->name))
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
        if (!$value) {
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
            trim((string) $methodology->name) !== '' ? ($methodology->name . ' Public Survey') : 'Public Survey'
        );
    }

    protected function resolveSurveyMethodologyByIndicator(Indicator $indicator): ?IndicatorMethodology
    {
        $methodologyName = strtolower(trim((string) $indicator->methodology));
        if ($methodologyName === '') {
            return null;
        }

        $methodology = IndicatorMethodology::query()
            ->where('is_active', true)
            ->get()
            ->first(function (IndicatorMethodology $item) use ($methodologyName) {
                return strtolower(trim((string) $item->name)) === $methodologyName;
            });

        if (!$methodology || !$this->methodologyHasSurveyConfig($methodology)) {
            return null;
        }

        return $methodology;
    }

    protected function syncSurveyLinkForIndicator(Indicator $indicator): void
    {
        $surveyMethodology = $this->resolveSurveyMethodologyByIndicator($indicator);
        if (!$surveyMethodology) {
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

        $isNew = !$link->exists;
        $refreshToken = $isNew || !$link->public_token || $link->methodology_id !== $surveyMethodology->id;

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
        $latestTarget = $indicator->targets->sortByDesc('period_start')->first();
        $latestResult = $indicator->results->sortByDesc('period_start')->first();

        $status = 'Not Started';
        $statusClass = 'secondary';
        $statusKey = 'not_started';
        $achievement = null;

        if ($latestTarget && !$latestResult) {
            $status = 'Pending Reporting';
            $statusClass = 'warning';
            $statusKey = 'pending';
        } elseif (!$latestTarget && $latestResult) {
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
        if ($indicator->indicatorable_type === Program::class) {
            $owner = 'Program: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === Project::class) {
            $owner = 'Project: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === Activity::class) {
            $owner = 'Activity: ' . ($indicator->indicatorable?->name ?: 'Unknown');
        } elseif ($indicator->indicatorable_type === SubActivity::class) {
            $owner = 'Sub-Activity: ' . ($indicator->indicatorable?->name ?: 'Unknown');
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
        ];
    }
}
