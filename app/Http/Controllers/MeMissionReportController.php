<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\MeMissionReport;
use App\Models\MeMissionReportDocument;
use App\Models\MeMissionReportTemplate;
use App\Models\Project;
use App\Models\Sector;
use App\Services\MeReportingNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MeMissionReportController extends Controller
{
    use ScopesAssignedPortfolios;

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.mission_reports.view|me.mission_reports.manage|me.mission_reports.review|me.mission_reports.archive')->only(['index', 'edit', 'downloadDocument']);
        $this->middleware('permission:me.mission_reports.manage')->only(['create', 'store', 'update', 'submit', 'destroyDocument']);
        $this->middleware('permission:me.mission_reports.review')->only('review');
        $this->middleware('permission:me.mission_reports.archive')->only('archive');
    }

    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', ''));
        $query = MeMissionReport::query()->with(['template:id,name', 'portfolio:id,name', 'projectComponent:id,name', 'createdBy:id,name']);
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }
        $reports = $query
            ->when($status !== '', fn ($builder) => $builder->where('status', $status))
            ->latest('mission_start_date')
            ->paginate(20)
            ->withQueryString();

        $countsQuery = MeMissionReport::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($countsQuery, $request->user());
        }

        return view('me.mission-reports.index', [
            'reports' => $reports,
            'counts' => $countsQuery->selectRaw('status, COUNT(*) as aggregate')->groupBy('status')->pluck('aggregate', 'status'),
            'status' => $status,
        ]);
    }

    public function create(Request $request): View
    {
        return view('me.mission-reports.form', $this->formData($request));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());
        $this->assertPortfolioAccess($request, (string) $validated['portfolio_id']);
        $this->assertComponentBelongsToPortfolio($validated);
        $stored = [];
        try {
            $report = DB::transaction(function () use ($request, $validated, &$stored): MeMissionReport {
                $report = MeMissionReport::query()->create($this->attributes($validated) + [
                    'report_number' => 'MISSION-'.now()->format('Ymd').'-'.Str::upper(Str::random(6)),
                    'status' => MeMissionReport::STATUS_DRAFT,
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]);
                $this->storeDocuments($request, $report, $validated, $stored);

                return $report;
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return redirect()->route('budget.me.mission-reports.edit', $report)
            ->with('success', 'Mission report created from the standardized template.');
    }

    public function edit(Request $request, MeMissionReport $missionReport): View
    {
        $this->assertScope($request, $missionReport);
        $missionReport->load(['template', 'portfolio:id,name', 'projectComponent:id,name', 'documents', 'createdBy:id,name', 'reviewedBy:id,name', 'archivedBy:id,name']);

        return view('me.mission-reports.form', $this->formData($request, $missionReport) + [
            'missionReport' => $missionReport,
        ]);
    }

    public function update(Request $request, MeMissionReport $missionReport): RedirectResponse
    {
        $this->assertScope($request, $missionReport);
        abort_unless($missionReport->isEditable(), 422, 'Only draft or returned mission reports can be edited.');
        $validated = $request->validate($this->rules());
        $this->assertPortfolioAccess($request, (string) $validated['portfolio_id']);
        $this->assertComponentBelongsToPortfolio($validated);
        $stored = [];
        try {
            DB::transaction(function () use ($request, $missionReport, $validated, &$stored): void {
                $missionReport->update($this->attributes($validated) + ['updated_by' => $request->user()->id]);
                $this->storeDocuments($request, $missionReport, $validated, $stored);
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $exception;
        }

        return back()->with('success', 'Mission report draft saved.');
    }

    public function submit(Request $request, MeMissionReport $missionReport): RedirectResponse
    {
        $this->assertScope($request, $missionReport);
        abort_unless($missionReport->isEditable(), 422);
        if (($issues = $missionReport->completionIssues()) !== []) {
            throw ValidationException::withMessages(['report' => 'Complete all mandatory sections: '.implode(', ', $issues).'.']);
        }
        $missionReport->update([
            'status' => MeMissionReport::STATUS_SUBMITTED,
            'submitted_by' => $request->user()->id,
            'submitted_at' => now(),
            'updated_by' => $request->user()->id,
        ]);
        app(MeReportingNotificationService::class)->missionLifecycle($missionReport, 'submitted');

        return back()->with('success', 'Mission report submitted for Secretariat review.');
    }

    public function review(Request $request, MeMissionReport $missionReport): RedirectResponse
    {
        $this->assertScope($request, $missionReport);
        abort_unless($missionReport->isSubmitted(), 422);
        if ((string) $missionReport->created_by === (string) $request->user()->id
            && ! $request->user()->isAdmin()
            && ! $request->user()->isSuperAdmin()) {
            abort(403, 'Mission report authors cannot approve their own report.');
        }
        $validated = $request->validate([
            'review_action' => ['required', Rule::in(['approve', 'return'])],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        if ($validated['review_action'] === 'return' && blank($validated['review_notes'] ?? null)) {
            throw ValidationException::withMessages(['review_notes' => 'Explain the required corrections.']);
        }
        $approved = $validated['review_action'] === 'approve';
        $missionReport->update([
            'status' => $approved ? MeMissionReport::STATUS_REVIEWED : MeMissionReport::STATUS_DRAFT,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'review_notes' => $validated['review_notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);
        app(MeReportingNotificationService::class)->missionLifecycle($missionReport, $approved ? 'approved' : 'returned');

        return back()->with('success', $approved ? 'Mission report approved.' : 'Mission report returned for revision.');
    }

    public function archive(Request $request, MeMissionReport $missionReport): RedirectResponse
    {
        $this->assertScope($request, $missionReport);
        abort_unless($missionReport->isReviewed(), 422);
        $validated = $request->validate(['archive_notes' => ['nullable', 'string', 'max:5000']]);
        $missionReport->update([
            'status' => MeMissionReport::STATUS_ARCHIVED,
            'archived_by' => $request->user()->id,
            'archived_at' => now(),
            'archive_notes' => $validated['archive_notes'] ?? null,
            'updated_by' => $request->user()->id,
        ]);
        app(MeReportingNotificationService::class)->missionLifecycle($missionReport, 'archived');

        return back()->with('success', 'Mission report archived.');
    }

    public function downloadDocument(Request $request, MeMissionReport $missionReport, MeMissionReportDocument $document)
    {
        $this->assertScope($request, $missionReport);
        abort_unless((string) $document->mission_report_id === (string) $missionReport->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->original_filename);
    }

    public function destroyDocument(Request $request, MeMissionReport $missionReport, MeMissionReportDocument $document): RedirectResponse
    {
        $this->assertScope($request, $missionReport);
        abort_unless($missionReport->isEditable() && (string) $document->mission_report_id === (string) $missionReport->id, 403);
        $path = $document->file_path;
        $document->delete();
        Storage::disk('local')->delete($path);

        return back()->with('success', 'Mission attachment removed.');
    }

    private function formData(Request $request, ?MeMissionReport $report = null): array
    {
        $portfolioQuery = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($portfolioQuery, $request->user());
        }
        $portfolios = $portfolioQuery->get(['id', 'name']);
        $portfolioIds = $portfolios->pluck('id');
        $components = Project::query()
            ->whereHas('program', fn ($query) => $query->whereIn('sector_id', $portfolioIds))
            ->with('program:id,sector_id')
            ->orderBy('name')
            ->get(['id', 'program_id', 'name']);

        return [
            'templates' => MeMissionReportTemplate::query()->where('is_active', true)->orderBy('name')->get(),
            'portfolios' => $portfolios,
            'components' => $components,
            'missionReport' => $report,
        ];
    }

    private function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid', 'exists:me_mission_report_templates,id'],
            'portfolio_id' => ['required', 'uuid', 'exists:myb_sectors,id'],
            'project_component_id' => ['required', 'uuid', 'exists:myb_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'mission_start_date' => ['required', 'date'],
            'mission_end_date' => ['required', 'date', 'after_or_equal:mission_start_date'],
            'action_due_at' => ['nullable', 'date', 'after_or_equal:mission_end_date'],
            'team_members' => ['nullable', 'string', 'max:10000'],
            'objectives' => ['nullable', 'string', 'max:20000'],
            'methodology' => ['nullable', 'string', 'max:20000'],
            'executive_summary' => ['nullable', 'string', 'max:20000'],
            'key_findings' => ['nullable', 'string', 'max:20000'],
            'recommendations' => ['nullable', 'string', 'max:20000'],
            'corrective_actions' => ['nullable', 'string', 'max:20000'],
            'responsible_parties' => ['nullable', 'string', 'max:10000'],
            'lessons_learned' => ['nullable', 'string', 'max:20000'],
            'conclusion' => ['nullable', 'string', 'max:20000'],
            'document_names' => ['nullable', 'array', 'max:10'],
            'document_names.*' => ['nullable', 'string', 'max:255'],
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => ['file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,jpg,jpeg,png,zip'],
        ];
    }

    private function attributes(array $validated): array
    {
        return collect($validated)->except(['document_names', 'documents'])->all();
    }

    private function storeDocuments(
        Request $request,
        MeMissionReport $report,
        array $validated,
        array &$stored
    ): void {
        foreach ($request->file('documents', []) as $index => $file) {
            $path = $file->store('me/mission-reports/'.$report->id, 'local');
            $stored[] = $path;
            $report->documents()->create([
                'document_name' => trim((string) data_get($validated, 'document_names.'.$index))
                    ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => $file->getSize(),
                'uploaded_by' => $request->user()->id,
            ]);
        }
    }

    private function assertComponentBelongsToPortfolio(array $validated): void
    {
        $valid = Project::query()
            ->whereKey($validated['project_component_id'])
            ->whereHas('program', fn ($query) => $query->where('sector_id', $validated['portfolio_id']))
            ->exists();
        if (! $valid) {
            throw ValidationException::withMessages([
                'project_component_id' => 'Choose a project component belonging to the selected portfolio.',
            ]);
        }
    }

    private function assertScope(Request $request, MeMissionReport $report): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($report, $request->user())) {
            abort(403);
        }
    }

    private function assertPortfolioAccess(Request $request, string $portfolioId): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user())
            && ! in_array($portfolioId, $this->assignedPortfolioIds($request->user()), true)) {
            abort(403);
        }
    }
}
