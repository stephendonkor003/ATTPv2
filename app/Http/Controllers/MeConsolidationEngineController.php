<?php

namespace App\Http\Controllers;

use App\Exports\MeConsolidationEngineExport;
use App\Exports\Sheets\MeIndicatorConsolidationSheet;
use App\Exports\Sheets\MeProjectConsolidationSheet;
use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MeFramework;
use App\Models\MeIndicatorAchievement;
use App\Models\MeReportingPeriod;
use App\Models\Project;
use App\Services\MeConsolidationEngineService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MeConsolidationEngineController extends Controller
{
    use ScopesAssignedPortfolios;

    private const LEVELS = [
        'indicator' => 'Indicator level',
        'project' => 'Project level',
    ];

    private const PERFORMANCE_STATUSES = [
        'achieved' => 'Achieved / Exceeded',
        'on_track' => 'On Track / Moderate',
        'needs_attention' => 'Needs Attention',
        'off_track' => 'Off Track',
        'qualitative_result' => 'Qualitative result',
        'not_rated' => 'Not rated / not reported',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.results.view|me.performance_reports.view|me.configuration.view|me.configuration.manage')
            ->only('index');
        $this->middleware('permission:me.reports.export|me.performance_reports.view|me.configuration.manage')
            ->only(['excel', 'csv', 'pdf']);
    }

    public function index(Request $request, MeConsolidationEngineService $service): View
    {
        $context = $this->context($request);
        $data = $service->build($context['serviceFilters']);

        return view('me.consolidation-engine.index', array_merge($data, $context, [
            'filters' => $context['filters'],
            'exportQuery' => collect($context['filters'])->filter(fn ($value) => filled($value))->all(),
            'levels' => self::LEVELS,
            'performanceStatuses' => self::PERFORMANCE_STATUSES,
            'generatedAt' => now(),
            'canExport' => $request->user()->can('me.reports.export')
                || $request->user()->can('me.performance_reports.view')
                || $request->user()->can('me.configuration.manage'),
        ]));
    }

    public function excel(Request $request, MeConsolidationEngineService $service)
    {
        $context = $this->context($request);
        $data = $service->build($context['serviceFilters']);
        $previousErrorReporting = error_reporting();

        try {
            error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
            $response = Excel::download(
                new MeConsolidationEngineExport(
                    $data['engineSummary'],
                    $data['projectRows'],
                    $data['indicatorRows'],
                    $context['filters'],
                    $context['scopeLabel']
                ),
                $this->filename('Complete', $context['filters'], 'xlsx')
            );
        } finally {
            error_reporting($previousErrorReporting);
        }

        $response->headers->set(
            'Content-Type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        );

        return $response;
    }

    public function csv(Request $request, MeConsolidationEngineService $service): StreamedResponse
    {
        $context = $this->context($request);
        $data = $service->build($context['serviceFilters']);
        $level = $context['filters']['level'];
        $sheet = $level === 'project'
            ? new MeProjectConsolidationSheet($data['projectRows'])
            : new MeIndicatorConsolidationSheet($data['indicatorRows']);

        return response()->streamDownload(function () use ($sheet): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $sheet->headings(), ',', '"', '');
            foreach ($sheet->array() as $row) {
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, $this->filename(Str::headline($level), $context['filters'], 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request, MeConsolidationEngineService $service)
    {
        $context = $this->context($request);
        $data = $service->build($context['serviceFilters']);
        $level = $context['filters']['level'];

        return Pdf::loadView('me.consolidation-engine.pdf', array_merge($data, $context, [
            'filters' => $context['filters'],
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
            'levelLabel' => self::LEVELS[$level],
        ]))->setPaper('a4', 'landscape')->download(
            $this->filename(Str::headline($level), $context['filters'], 'pdf')
        );
    }

    /** @return array<string, mixed> */
    private function context(Request $request): array
    {
        $filters = $this->filters($request);
        $framework = MeFramework::query()->current()->first();
        $frameworkId = $framework?->id;
        $projectIds = Indicator::query()
            ->when(
                $frameworkId,
                fn (Builder $query): Builder => $query->where('framework_id', $frameworkId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0')
            )
            ->where('is_active', true)
            ->whereNotNull('project_component_id')
            ->pluck('project_component_id')
            ->unique()
            ->values();

        $allProjectsQuery = Project::query()
            ->whereIn('id', $projectIds)
            ->with(['program:id,sector_id,name', 'program.sector:id,name'])
            ->orderBy('project_id');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToProjects($allProjectsQuery, $request->user());
        }
        $allProjects = $allProjectsQuery->get(['id', 'program_id', 'project_id', 'name']);
        $portfolios = $allProjects
            ->pluck('program.sector')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();

        if ($filters['portfolio_id'] && ! $portfolios->contains('id', $filters['portfolio_id'])) {
            abort(403, 'The selected portfolio is outside your authorized consolidation scope.');
        }

        $projects = $allProjects
            ->when(
                $filters['portfolio_id'],
                fn (Collection $rows): Collection => $rows
                    ->where('program.sector_id', $filters['portfolio_id'])
                    ->values()
            );
        if ($filters['component_id'] && ! $projects->contains('id', $filters['component_id'])) {
            abort(403, 'The selected project/component is outside your authorized consolidation scope.');
        }

        $indicatorQuery = Indicator::query()
            ->when(
                $frameworkId,
                fn (Builder $query): Builder => $query->where('framework_id', $frameworkId),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0')
            )
            ->where('is_active', true)
            ->when(
                $this->userHasAssignedPortfolioScope($request->user()) || $filters['portfolio_id'],
                fn (Builder $query): Builder => $query->whereIn('project_component_id', $projects->pluck('id'))
            )
            ->when($filters['component_id'], fn (Builder $query): Builder => $query
                ->where('project_component_id', $filters['component_id']))
            ->orderBy('display_order');
        $indicators = $indicatorQuery->get(['id', 'indicator_code', 'name', 'project_component_id', 'results_level']);
        if ($filters['indicator_id'] && ! $indicators->contains('id', $filters['indicator_id'])) {
            abort(403, 'The selected indicator is outside your authorized consolidation scope.');
        }

        $periodQuery = MeReportingPeriod::query()->orderByDesc('period_start');
        if ($filters['portfolio_id']) {
            $periodQuery->where(fn (Builder $query): Builder => $query
                ->whereNull('portfolio_id')
                ->orWhere('portfolio_id', $filters['portfolio_id']));
        } elseif ($this->userHasAssignedPortfolioScope($request->user())) {
            $assignedPortfolioIds = $this->assignedPortfolioIds($request->user());
            $periodQuery->where(fn (Builder $query): Builder => $query
                ->whereNull('portfolio_id')
                ->orWhereIn('portfolio_id', $assignedPortfolioIds));
        }
        $periods = $periodQuery->get([
            'id', 'portfolio_id', 'label', 'reporting_year', 'period_type', 'period_start', 'period_end',
        ]);
        if ($filters['reporting_period_id'] && ! $periods->contains('id', $filters['reporting_period_id'])) {
            abort(403, 'The selected reporting period is outside your authorized consolidation scope.');
        }

        $serviceFilters = $filters;
        if (($this->userHasAssignedPortfolioScope($request->user()) || $filters['portfolio_id'])
            && ! $filters['component_id']) {
            $serviceFilters['component_ids'] = $projects->pluck('id')->all();
        }
        unset($serviceFilters['portfolio_id'], $serviceFilters['level']);

        $selectedPortfolio = $portfolios->firstWhere('id', $filters['portfolio_id']);
        $selectedProject = $projects->firstWhere('id', $filters['component_id']);
        $selectedIndicator = $indicators->firstWhere('id', $filters['indicator_id']);
        $selectedPeriod = $periods->firstWhere('id', $filters['reporting_period_id']);
        $selectedThinkTank = $filters['think_tank_id']
            ? ConsortiumThinkTank::query()->find($filters['think_tank_id'])
            : null;
        $scopeParts = collect([
            $selectedPortfolio?->name,
            $selectedProject ? $selectedProject->project_id.' · '.$selectedProject->name : null,
            $selectedIndicator ? $selectedIndicator->indicator_code.' · '.$selectedIndicator->name : null,
            $selectedThinkTank?->name,
            $selectedPeriod?->label,
            ! $selectedPeriod && $filters['reporting_year'] ? 'Reporting year '.$filters['reporting_year'] : null,
            $filters['country'],
            $filters['thematic_area'] ? MeIndicatorAchievement::PRIORITY_THEMES[$filters['thematic_area']] : null,
        ])->filter();

        return [
            'filters' => $filters,
            'serviceFilters' => $serviceFilters,
            'frameworkContext' => $framework,
            'portfolios' => $portfolios,
            'projects' => $projects,
            'indicators' => $indicators,
            'periods' => $periods,
            'reportingYears' => $periods->pluck('reporting_year')->filter()->unique()->sortDesc()->values(),
            'thinkTanks' => ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'country']),
            'countries' => ConsortiumThinkTank::query()->where('status', 'active')->whereNotNull('country')
                ->distinct()->orderBy('country')->pluck('country'),
            'selectedPortfolio' => $selectedPortfolio,
            'selectedProject' => $selectedProject,
            'selectedIndicator' => $selectedIndicator,
            'selectedPeriod' => $selectedPeriod,
            'selectedThinkTank' => $selectedThinkTank,
            'scopeLabel' => $scopeParts->isEmpty() ? 'All authorized approved results' : $scopeParts->join(' · '),
            'activeFilterCount' => collect($filters)->filter(function ($value, string $key): bool {
                if (! filled($value)) {
                    return false;
                }

                return ! (($key === 'level' && $value === 'indicator')
                    || ($key === 'project_year' && (int) $value === 1));
            })->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function filters(Request $request): array
    {
        $level = trim((string) $request->query('level', 'indicator'));
        $year = filter_var($request->query('reporting_year'), FILTER_VALIDATE_INT);
        $resultsLevel = trim((string) $request->query('results_level'));
        $performanceStatus = trim((string) $request->query('performance_status'));
        $thematicArea = trim((string) $request->query('thematic_area'));

        return [
            'level' => array_key_exists($level, self::LEVELS) ? $level : 'indicator',
            'project_year' => max(1, min(4, (int) $request->query('project_year', 1))),
            'reporting_year' => $year && $year >= 2000 && $year <= 2100 ? $year : null,
            'reporting_period_id' => $this->uuidOrNull($request->query('reporting_period_id')),
            'portfolio_id' => $this->uuidOrNull($request->query('portfolio_id')),
            'component_id' => $this->uuidOrNull($request->query('component_id')),
            'indicator_id' => $this->uuidOrNull($request->query('indicator_id')),
            'think_tank_id' => $this->uuidOrNull($request->query('think_tank_id')),
            'results_level' => in_array($resultsLevel, ['pdo', 'intermediate_results'], true)
                ? $resultsLevel
                : null,
            'performance_status' => array_key_exists($performanceStatus, self::PERFORMANCE_STATUSES)
                ? $performanceStatus
                : null,
            'country' => $request->filled('country')
                ? Str::limit(trim((string) $request->query('country')), 120, '')
                : null,
            'thematic_area' => array_key_exists($thematicArea, MeIndicatorAchievement::PRIORITY_THEMES)
                ? $thematicArea
                : null,
        ];
    }

    private function uuidOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }

    private function filename(string $level, array $filters, string $extension): string
    {
        $scope = $filters['reporting_year'] ?: 'all-periods';

        return 'ATTP-'.Str::slug($level).'-Consolidation-'.$scope.'.'.$extension;
    }
}
