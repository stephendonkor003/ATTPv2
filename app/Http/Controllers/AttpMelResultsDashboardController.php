<?php

namespace App\Http\Controllers;

use App\Exports\AttpMelResultsExport;
use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\MeIndicatorAchievement;
use App\Models\MeReportingPeriod;
use App\Models\Project;
use App\Services\AttpMelResultsService;
use App\Services\ThinkTankMeAssignmentService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class AttpMelResultsDashboardController extends Controller
{
    public const REPORT_TYPES = [
        'results_framework' => 'ATTP Results Framework Report',
        'pdo_performance' => 'PDO Performance Report',
        'component_performance' => 'Component Performance Report',
        'indicator_performance' => 'Indicator Performance Report',
        'think_tank_performance' => 'Think Tank Performance Report',
        'reporting_compliance' => 'Reporting Compliance Report',
        'evidence_verification' => 'Evidence Verification Report',
        'gender_disaggregation' => 'Gender / Disaggregation Report',
        'semi_annual' => 'Semi-Annual M&E Report',
        'annual' => 'Annual M&E Report',
        'target_vs_actual' => 'Target vs Actual Report',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner'])
            ->except('thinkTank');
        $this->middleware('permission:me.results.view|me.performance_reports.view|me.configuration.view|me.configuration.manage')
            ->only('index');
        $this->middleware('permission:me.reports.export|me.performance_reports.view|me.configuration.manage')
            ->only(['excel', 'csv', 'pdf']);
    }

    public function index(Request $request, AttpMelResultsService $service)
    {
        $filters = $this->filters($request);

        return view('me.results-dashboard.index', $this->viewData($service->build($filters), $filters));
    }

    public function thinkTank(
        Request $request,
        AttpMelResultsService $service,
        ThinkTankMeAssignmentService $assignments
    ) {
        abort_unless($request->user()?->isThinkTankUser(), 403);
        $member = $request->user()->resolvedThinkTankMembership();
        abort_unless($member && $member->status === 'active', 403);
        $filters = $this->filters($request);
        $assignmentOverview = $assignments->overview(
            $member,
            [],
            $request->user()->can('think_tank.me.submit'),
            6
        );

        return view('think-tank.me-dashboard', $this->viewData($service->build($filters, $member->id), $filters) + [
            'member' => $member,
            'assignmentOverview' => $assignmentOverview,
        ]);
    }

    public function excel(Request $request, AttpMelResultsService $service)
    {
        $filters = $this->filters($request);
        $data = $service->build($filters);
        $previousErrorReporting = error_reporting();

        try {
            // PhpSpreadsheet currently emits PHP 8.5 string-increment deprecations while
            // creating valid XLSX files. Keep those vendor notices out of the download.
            error_reporting($previousErrorReporting & ~E_DEPRECATED & ~E_USER_DEPRECATED);
            $response = Excel::download(
                new AttpMelResultsExport($data['rows']),
                $this->filename($filters, 'xlsx')
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

    public function csv(Request $request, AttpMelResultsService $service)
    {
        $filters = $this->filters($request);
        $export = new AttpMelResultsExport($service->build($filters)['rows']);

        return response()->streamDownload(function () use ($export): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $export->headings(), ',', '"', '');
            foreach ($export->array() as $row) {
                fputcsv($stream, $row, ',', '"', '');
            }
            fclose($stream);
        }, $this->filename($filters, 'csv'), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function pdf(Request $request, AttpMelResultsService $service)
    {
        $filters = $this->filters($request);
        $data = $service->build($filters);

        return Pdf::loadView('me.results-dashboard.pdf', $data + [
            'filters' => $filters,
            'reportTitle' => self::REPORT_TYPES[$filters['report_type']],
            'scopeLabel' => $this->scopeLabel($data),
            'generatedBy' => $request->user(),
        ])->setPaper('a4', 'landscape')->download($this->filename($filters, 'pdf'));
    }

    private function viewData(array $data, array $filters): array
    {
        $frameworkId = $data['framework']?->id;
        $indicatorQuery = Indicator::query()
            ->when(
                $frameworkId,
                fn ($query) => $query->where('framework_id', $frameworkId),
                fn ($query) => $query->whereRaw('1 = 0')
            )
            ->where('is_active', true);
        $componentIds = (clone $indicatorQuery)
            ->whereNotNull('project_component_id')
            ->pluck('project_component_id')
            ->unique()
            ->values();
        $periods = MeReportingPeriod::query()
            ->orderByDesc('period_start')
            ->get(['id', 'label', 'reporting_year', 'period_type', 'period_start', 'period_end']);
        $exportQuery = collect($filters)
            ->reject(fn ($value): bool => $value === null || $value === '')
            ->all();
        $activeFilterCount = collect($filters)->filter(function ($value, string $key): bool {
            if ($value === null || $value === '') {
                return false;
            }

            return ! (($key === 'report_type' && $value === 'results_framework')
                || ($key === 'project_year' && (int) $value === 1));
        })->count();

        return $data + [
            'filters' => $filters,
            'reportTypes' => self::REPORT_TYPES,
            'reportTitle' => self::REPORT_TYPES[$filters['report_type']],
            'periods' => $periods,
            'reportingYears' => $periods->pluck('reporting_year')->filter()->unique()->sortDesc()->values(),
            'components' => Project::query()->whereIn('id', $componentIds)->orderBy('project_id')->get(['id', 'project_id', 'name']),
            'indicators' => (clone $indicatorQuery)->orderBy('display_order')->get(['id', 'indicator_code', 'name']),
            'thinkTanks' => ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'country']),
            'countries' => ConsortiumThinkTank::query()->where('status', 'active')->whereNotNull('country')->distinct()->orderBy('country')->pluck('country'),
            'scopeLabel' => $this->scopeLabel($data),
            'exportQuery' => $exportQuery,
            'activeFilterCount' => $activeFilterCount,
        ];
    }

    private function filters(Request $request): array
    {
        $reportType = (string) $request->query('report_type', 'results_framework');

        return [
            'report_type' => array_key_exists($reportType, self::REPORT_TYPES) ? $reportType : 'results_framework',
            'project_year' => max(1, min(4, (int) $request->query('project_year', 1))),
            'reporting_year' => $request->filled('reporting_year')
                && (int) $request->query('reporting_year') >= 2000
                && (int) $request->query('reporting_year') <= 2100
                    ? (int) $request->query('reporting_year')
                    : null,
            'reporting_period_id' => $this->uuid($request->query('reporting_period_id')),
            'component_id' => $this->uuid($request->query('component_id')),
            'indicator_id' => $this->uuid($request->query('indicator_id')),
            'think_tank_id' => $this->uuid($request->query('think_tank_id')),
            'country' => $request->filled('country') ? trim((string) $request->query('country')) : null,
            'thematic_area' => array_key_exists(
                trim((string) $request->query('thematic_area')),
                MeIndicatorAchievement::PRIORITY_THEMES
            ) ? trim((string) $request->query('thematic_area')) : null,
        ];
    }

    private function uuid(mixed $value): ?string
    {
        $value = trim((string) $value);

        return Str::isUuid($value) ? $value : null;
    }

    private function filename(array $filters, string $extension): string
    {
        return 'ATTP-'.Str::slug(self::REPORT_TYPES[$filters['report_type']]).'-'.now()->format('Ymd-His').'.'.$extension;
    }

    private function scopeLabel(array $data): string
    {
        if ($data['period'] ?? null) {
            return (string) $data['period']->label;
        }
        if ($data['reportingYear'] ?? null) {
            return 'Reporting year '.$data['reportingYear'];
        }

        return 'All approved reporting periods';
    }
}
