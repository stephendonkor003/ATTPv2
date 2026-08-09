<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\ConsortiumThinkTank;
use App\Models\MeDataGovernanceAction;
use App\Models\MeDataGovernanceControl;
use App\Models\MeKnowledgeEvidenceItem;
use App\Models\Sector;
use App\Models\SystemAuditLog;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class MeDataGovernanceController extends Controller
{
    use ScopesAssignedPortfolios;

    public const DOMAINS = [
        'ownership_stewardship' => 'Ownership and stewardship',
        'data_quality' => 'Data quality',
        'privacy_consent' => 'Privacy and consent',
        'access_security' => 'Access and security',
        'classification_handling' => 'Classification and handling',
        'retention_disposal' => 'Retention and disposal',
        'sharing_interoperability' => 'Sharing and interoperability',
        'incident_continuity' => 'Incident response and continuity',
        'metadata_lineage' => 'Metadata and lineage',
        'ethics_responsible_use' => 'Ethics and responsible use',
    ];

    public const INSTRUMENT_TYPES = [
        'policy' => 'Policy',
        'standard' => 'Standard',
        'procedure' => 'Procedure',
        'control' => 'Control',
        'protocol' => 'Protocol',
    ];

    public const SCOPE_TYPES = [
        'enterprise' => 'ATTP enterprise',
        'portfolio' => 'Portfolio',
        'think_tank' => 'Think tank',
    ];

    public const CLASSIFICATIONS = [
        'public' => 'Public',
        'internal' => 'Internal',
        'confidential' => 'Confidential',
        'restricted' => 'Restricted',
    ];

    public const RISKS = [
        'low' => 'Low',
        'moderate' => 'Moderate',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const STATUSES = [
        'draft' => 'Draft',
        'under_review' => 'Under review',
        'approved' => 'Approved',
        'retired' => 'Retired',
    ];

    public const IMPLEMENTATION_STATUSES = [
        'not_started' => 'Not started',
        'in_progress' => 'In progress',
        'implemented' => 'Implemented',
        'exception' => 'Approved exception',
    ];

    public const FREQUENCIES = [
        'monthly' => 'Monthly',
        'quarterly' => 'Quarterly',
        'semi_annual' => 'Semi-annual',
        'annual' => 'Annual',
        'biennial' => 'Every two years',
        'event_driven' => 'Event driven',
    ];

    public const ACTION_TYPES = [
        'gap' => 'Control gap',
        'remediation' => 'Remediation',
        'review' => 'Scheduled review',
        'exception' => 'Exception follow-up',
        'incident' => 'Data incident',
    ];

    public const ACTION_PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const ACTION_STATUSES = [
        'open' => 'Open',
        'in_progress' => 'In progress',
        'resolved' => 'Resolved',
        'risk_accepted' => 'Risk accepted',
        'cancelled' => 'Cancelled',
    ];

    public function __construct()
    {
        $this->middleware(['auth', 'not.funding.partner']);
        $this->middleware('permission:me.configuration.view|me.configuration.manage|world.indicators.manage')->only(['index', 'pdf', 'csv']);
        $this->middleware('permission:me.configuration.manage')->except(['index', 'pdf', 'csv']);
    }

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $portfolios = $this->portfolios($request);
        $this->assertAuthorizedPortfolio($filters['portfolio_id'], $portfolios);

        $filteredQuery = $this->applyFilters($this->controlQuery($request), $filters);
        $analysis = (clone $filteredQuery)
            ->with(['portfolio:id,name', 'thinkTank:id,name,country', 'owner:id,name', 'steward:id,name'])
            ->withCount(['actions as open_actions_count' => fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress'])])
            ->get();
        $controls = (clone $filteredQuery)
            ->with(['portfolio:id,name', 'thinkTank:id,name,country', 'owner:id,name,email', 'steward:id,name,email'])
            ->withCount([
                'actions',
                'actions as open_actions_count' => fn (Builder $query) => $query->whereIn('status', ['open', 'in_progress']),
                'actions as overdue_actions_count' => fn (Builder $query) => $query
                    ->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today()),
            ])
            ->tap(fn (Builder $query): Builder => $this->applySort($query, $filters['sort']))
            ->paginate($filters['per_page'])
            ->withQueryString();

        $selectedControl = null;
        if ($filters['control_id']) {
            $selectedControl = (clone $filteredQuery)->whereKey($filters['control_id'])->first();
            abort_unless($selectedControl, 404, 'The selected governance instrument is outside the active scope.');
        } elseif ($controls->isNotEmpty()) {
            $selectedControl = $controls->first();
        }
        $selectedControl?->load([
            'portfolio:id,name', 'thinkTank:id,name,country', 'owner:id,name,email',
            'steward:id,name,email', 'evidence:id,title,validation_status,file_path,external_url',
            'creator:id,name', 'approver:id,name',
            'actions' => fn ($query) => $query->with(['owner:id,name,email', 'creator:id,name', 'resolver:id,name'])
                ->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_progress' THEN 1 ELSE 2 END")
                ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderBy('due_date'),
        ]);

        return view('me.data-governance.index', [
            'controls' => $controls,
            'selectedControl' => $selectedControl,
            'filters' => $filters,
            'metrics' => $this->metrics($analysis),
            'charts' => $this->charts($analysis),
            'portfolios' => $portfolios,
            'thinkTanks' => ConsortiumThinkTank::query()->where('status', 'active')->orderBy('name')->get(['id', 'name', 'country']),
            'users' => $this->governanceUsers(),
            'evidenceItems' => $this->evidenceItems($request),
            'domains' => self::DOMAINS,
            'instrumentTypes' => self::INSTRUMENT_TYPES,
            'scopeTypes' => self::SCOPE_TYPES,
            'classifications' => self::CLASSIFICATIONS,
            'risks' => self::RISKS,
            'statuses' => self::STATUSES,
            'implementationStatuses' => self::IMPLEMENTATION_STATUSES,
            'frequencies' => self::FREQUENCIES,
            'actionTypes' => self::ACTION_TYPES,
            'actionPriorities' => self::ACTION_PRIORITIES,
            'actionStatuses' => self::ACTION_STATUSES,
            'generatedAt' => now(),
            'canManage' => $request->user()->hasPermission('me.configuration.manage'),
            'activeFilterCount' => collect($filters)->except(['control_id', 'sort', 'per_page'])
                ->reject(fn ($value, $key) => ($key === 'lifecycle' && $value === 'active') || blank($value))->count(),
            'exportQuery' => collect($filters)->except(['control_id', 'per_page'])
                ->reject(fn ($value, $key): bool => blank($value)
                    || ($key === 'lifecycle' && $value === 'active')
                    || ($key === 'sort' && $value === 'risk'))->all(),
        ]);
    }

    public function pdf(Request $request)
    {
        [$controls, $filters, $portfolios] = $this->reportData($request);
        $filename = $filters['control_id'] && $controls->first()
            ? Str::slug($controls->first()->control_code.'-v'.$controls->first()->version).'-governance-control.pdf'
            : 'attp-data-governance-register-'.now()->format('Ymd-His').'.pdf';

        return Pdf::loadView('me.data-governance.report-pdf', [
            'controls' => $controls,
            'metrics' => $this->metrics($controls),
            'charts' => $this->charts($controls),
            'scopeLabel' => $this->scopeLabel($filters, $portfolios, $controls),
            'generatedAt' => now(),
            'generatedBy' => $request->user(),
            'isIndividual' => filled($filters['control_id']),
            'domains' => self::DOMAINS,
            'instrumentTypes' => self::INSTRUMENT_TYPES,
            'classifications' => self::CLASSIFICATIONS,
            'risks' => self::RISKS,
            'statuses' => self::STATUSES,
            'implementationStatuses' => self::IMPLEMENTATION_STATUSES,
            'actionStatuses' => self::ACTION_STATUSES,
        ])->setPaper('a4', 'landscape')->download($filename);
    }

    public function csv(Request $request): StreamedResponse
    {
        [$controls] = $this->reportData($request);
        $filename = 'attp-data-governance-register-'.now()->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($controls): void {
            $stream = fopen('php://output', 'wb');
            fputcsv($stream, ['Control code', 'Version', 'Title', 'Domain', 'Instrument', 'Scope', 'Portfolio / Think tank', 'Owner', 'Steward', 'Classification', 'Risk', 'Lifecycle', 'Implementation', 'Effective date', 'Next review', 'Open actions', 'Overdue actions']);
            foreach ($controls as $control) {
                fputcsv($stream, [
                    $control->control_code, $control->version, $control->title,
                    self::DOMAINS[$control->governance_domain] ?? Str::headline($control->governance_domain),
                    self::INSTRUMENT_TYPES[$control->instrument_type] ?? Str::headline($control->instrument_type),
                    self::SCOPE_TYPES[$control->scope_type] ?? Str::headline($control->scope_type),
                    $control->portfolio?->name ?: $control->thinkTank?->name ?: 'ATTP enterprise',
                    $control->owner?->name, $control->steward?->name,
                    self::CLASSIFICATIONS[$control->data_classification] ?? Str::headline($control->data_classification),
                    self::RISKS[$control->risk_rating] ?? Str::headline($control->risk_rating),
                    self::STATUSES[$control->status] ?? Str::headline($control->status),
                    self::IMPLEMENTATION_STATUSES[$control->implementation_status] ?? Str::headline($control->implementation_status),
                    $control->effective_date?->format('Y-m-d'), $control->next_review_date?->format('Y-m-d'),
                    $control->open_actions_count, $control->overdue_actions_count,
                ]);
            }
            fclose($stream);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function store(Request $request)
    {
        $data = $this->controlRules($request);
        $this->assertInputScope($request, $data);
        $control = DB::transaction(function () use ($request, $data): MeDataGovernanceControl {
            return MeDataGovernanceControl::query()->create($data + [
                'control_code' => Str::upper($data['control_code']),
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });
        $this->audit($request, 'governance_control_created', 'Created data-governance instrument '.$control->control_code.' version '.$control->version.'.', $control);

        return redirect()->route('budget.me.rebuild.data-governance', ['control_id' => $control->id])
            ->with('success', 'Governance instrument added as a controlled draft.');
    }

    public function update(Request $request, MeDataGovernanceControl $control)
    {
        $this->assertManageable($request, $control);
        if (! $control->isEditable()) {
            throw ValidationException::withMessages(['control' => 'Approved or retired instruments are immutable. Create a new controlled version instead.']);
        }
        $data = $this->controlRules($request, $control);
        $this->assertInputScope($request, $data);
        $control->update($data + ['control_code' => Str::upper($data['control_code']), 'updated_by' => $request->user()->id]);
        $this->audit($request, 'governance_control_updated', 'Updated data-governance instrument '.$control->control_code.' version '.$control->version.'.', $control);

        return redirect()->route('budget.me.rebuild.data-governance', ['control_id' => $control->id])
            ->with('success', 'Governance instrument updated.');
    }

    public function submitReview(Request $request, MeDataGovernanceControl $control)
    {
        $this->assertManageable($request, $control);
        if ($control->status !== 'draft') {
            throw ValidationException::withMessages(['control' => 'Only a draft instrument can be submitted for review.']);
        }
        $missing = collect([
            'owner' => $control->owner_user_id,
            'data steward' => $control->steward_user_id,
            'requirements' => $control->requirements,
            'next review date' => $control->next_review_date,
        ])->filter(fn ($value) => blank($value))->keys();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages(['control' => 'Complete the '.implode(', ', $missing->all()).' before submitting this instrument for review.']);
        }
        $control->update(['status' => 'under_review', 'updated_by' => $request->user()->id]);
        $this->audit($request, 'governance_control_submitted', 'Submitted '.$control->control_code.' version '.$control->version.' for governance review.', $control);

        return back()->with('success', 'Governance instrument submitted for approval review.');
    }

    public function approve(Request $request, MeDataGovernanceControl $control)
    {
        $this->assertManageable($request, $control);
        if ($control->status !== 'under_review') {
            throw ValidationException::withMessages(['control' => 'Only an instrument under review can be approved.']);
        }
        if (! $control->effective_date) {
            throw ValidationException::withMessages(['effective_date' => 'Set the effective date before approval.']);
        }
        $predecessorWithOpenActions = MeDataGovernanceControl::query()
            ->where('control_code', $control->control_code)
            ->where('status', 'approved')
            ->whereKeyNot($control->id)
            ->whereHas('actions', fn (Builder $action) => $action->whereIn('status', ['open', 'in_progress']))
            ->exists();
        if ($predecessorWithOpenActions) {
            throw ValidationException::withMessages([
                'control' => 'Close or formally accept all actions on the currently approved version before replacing it.',
            ]);
        }
        DB::transaction(function () use ($request, $control): void {
            MeDataGovernanceControl::query()->where('control_code', $control->control_code)
                ->where('status', 'approved')->whereKeyNot($control->id)
                ->update(['status' => 'retired', 'retired_by' => $request->user()->id, 'retired_at' => now(), 'updated_at' => now()]);
            $control->update([
                'status' => 'approved', 'approved_by' => $request->user()->id,
                'approved_at' => now(), 'updated_by' => $request->user()->id,
            ]);
        });
        $this->audit($request, 'governance_control_approved', 'Approved '.$control->control_code.' version '.$control->version.' and retired any previous approved version.', $control);

        return back()->with('success', 'Governance instrument approved and made authoritative.');
    }

    public function newVersion(Request $request, MeDataGovernanceControl $control)
    {
        $this->assertManageable($request, $control);
        if (! in_array($control->status, ['approved', 'retired'], true)) {
            throw ValidationException::withMessages(['control' => 'Create a new version only from an approved or retired instrument.']);
        }
        $version = $this->nextVersion($control);
        $copy = DB::transaction(function () use ($request, $control, $version): MeDataGovernanceControl {
            $attributes = $control->only([
                'control_code', 'title', 'governance_domain', 'instrument_type', 'scope_type',
                'portfolio_id', 'think_tank_member_id', 'owner_user_id', 'steward_user_id',
                'data_classification', 'risk_rating', 'implementation_status', 'review_frequency',
                'description', 'requirements', 'evidence_notes', 'evidence_repository_item_id',
            ]);

            return MeDataGovernanceControl::query()->create($attributes + [
                'version' => $version, 'status' => 'draft', 'effective_date' => null,
                'next_review_date' => null, 'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        });
        $this->audit($request, 'governance_control_version_created', 'Created draft '.$copy->control_code.' version '.$copy->version.' from version '.$control->version.'.', $copy, ['source_control_id' => $control->id]);

        return redirect()->route('budget.me.rebuild.data-governance', ['control_id' => $copy->id])
            ->with('success', 'A new controlled draft version was created. The approved version remains authoritative until this version is approved.');
    }

    public function retire(Request $request, MeDataGovernanceControl $control)
    {
        $this->assertManageable($request, $control);
        if ($control->status === 'retired') {
            throw ValidationException::withMessages(['control' => 'This instrument is already retired.']);
        }
        $openActions = $control->actions()->whereIn('status', ['open', 'in_progress'])->count();
        if ($openActions > 0) {
            throw ValidationException::withMessages(['control' => 'Resolve, cancel, or accept the risk for all open actions before retiring this instrument.']);
        }
        $control->update([
            'status' => 'retired', 'retired_by' => $request->user()->id,
            'retired_at' => now(), 'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'governance_control_retired', 'Retired '.$control->control_code.' version '.$control->version.'.', $control);

        return back()->with('success', 'Governance instrument retired with its history preserved.');
    }

    public function storeAction(Request $request, MeDataGovernanceControl $control)
    {
        $this->assertManageable($request, $control);
        if ($control->status === 'retired') {
            throw ValidationException::withMessages(['control' => 'New actions cannot be opened against a retired instrument.']);
        }
        $data = $this->actionRules($request);
        $action = $control->actions()->create($data + [
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'governance_action_created', 'Opened '.$data['action_type'].' action for '.$control->control_code.': '.$action->title.'.', $control, ['action_id' => $action->id]);

        return back()->with('success', 'Governance action added to the accountability queue.');
    }

    public function updateAction(Request $request, MeDataGovernanceAction $action)
    {
        $action->loadMissing('control');
        $this->assertManageable($request, $action->control);
        if (! $action->isOpen()) {
            throw ValidationException::withMessages(['action' => 'Resolved, accepted, or cancelled actions must be reopened before editing.']);
        }
        $data = $this->actionRules($request);
        $action->update($data + ['updated_by' => $request->user()->id]);
        $this->audit($request, 'governance_action_updated', 'Updated governance action '.$action->title.'.', $action->control, ['action_id' => $action->id]);

        return back()->with('success', 'Governance action updated.');
    }

    public function resolveAction(Request $request, MeDataGovernanceAction $action)
    {
        $action->loadMissing('control');
        $this->assertManageable($request, $action->control);
        if (! $action->isOpen()) {
            throw ValidationException::withMessages(['action' => 'Only an open or in-progress action can be closed.']);
        }
        $data = $request->validate([
            'resolution_status' => ['required', Rule::in(['resolved', 'risk_accepted', 'cancelled'])],
            'resolution_notes' => 'required|string|min:10|max:5000',
        ]);
        $action->update([
            'status' => $data['resolution_status'], 'resolution_notes' => $data['resolution_notes'],
            'resolved_by' => $request->user()->id, 'resolved_at' => now(), 'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'governance_action_closed', 'Closed governance action '.$action->title.' as '.self::ACTION_STATUSES[$data['resolution_status']].'.', $action->control, ['action_id' => $action->id, 'resolution_status' => $data['resolution_status']]);

        return back()->with('success', 'Governance action closed with a permanent resolution record.');
    }

    public function reopenAction(Request $request, MeDataGovernanceAction $action)
    {
        $action->loadMissing('control');
        $this->assertManageable($request, $action->control);
        if ($action->isOpen()) {
            throw ValidationException::withMessages(['action' => 'This action is already open.']);
        }
        $action->update([
            'status' => 'open', 'resolution_notes' => null, 'resolved_by' => null,
            'resolved_at' => null, 'updated_by' => $request->user()->id,
        ]);
        $this->audit($request, 'governance_action_reopened', 'Reopened governance action '.$action->title.'.', $action->control, ['action_id' => $action->id]);

        return back()->with('success', 'Governance action reopened.');
    }

    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'q' => 'nullable|string|max:150', 'domain' => ['nullable', Rule::in(array_keys(self::DOMAINS))],
            'status' => ['nullable', Rule::in(array_keys(self::STATUSES))],
            'implementation' => ['nullable', Rule::in(array_keys(self::IMPLEMENTATION_STATUSES))],
            'risk' => ['nullable', Rule::in(array_keys(self::RISKS))],
            'classification' => ['nullable', Rule::in(array_keys(self::CLASSIFICATIONS))],
            'scope_type' => ['nullable', Rule::in(array_keys(self::SCOPE_TYPES))],
            'portfolio_id' => 'nullable|uuid', 'owner_user_id' => 'nullable|uuid',
            'review_state' => ['nullable', Rule::in(['overdue', 'due_soon', 'scheduled', 'unscheduled'])],
            'action_state' => ['nullable', Rule::in(['open', 'overdue', 'clear'])],
            'lifecycle' => ['nullable', Rule::in(['active', 'all', 'retired'])],
            'sort' => ['nullable', Rule::in(['risk', 'review', 'newest', 'updated', 'code', 'title'])],
            'per_page' => ['nullable', 'integer', Rule::in([15, 25, 50, 100])],
            'control_id' => 'nullable|uuid',
        ]);

        return [
            'q' => trim((string) ($validated['q'] ?? '')), 'domain' => (string) ($validated['domain'] ?? ''),
            'status' => (string) ($validated['status'] ?? ''), 'implementation' => (string) ($validated['implementation'] ?? ''),
            'risk' => (string) ($validated['risk'] ?? ''), 'classification' => (string) ($validated['classification'] ?? ''),
            'scope_type' => (string) ($validated['scope_type'] ?? ''), 'portfolio_id' => (string) ($validated['portfolio_id'] ?? ''),
            'owner_user_id' => (string) ($validated['owner_user_id'] ?? ''), 'review_state' => (string) ($validated['review_state'] ?? ''),
            'action_state' => (string) ($validated['action_state'] ?? ''), 'lifecycle' => (string) ($validated['lifecycle'] ?? 'active'),
            'sort' => (string) ($validated['sort'] ?? 'risk'), 'per_page' => (int) ($validated['per_page'] ?? 25),
            'control_id' => (string) ($validated['control_id'] ?? ''),
        ];
    }

    private function controlRules(Request $request, ?MeDataGovernanceControl $control = null): array
    {
        return $request->validate([
            'control_code' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]*$/', Rule::unique('me_data_governance_controls')->where(fn ($query) => $query->where('version', $request->input('version')))->ignore($control?->id)],
            'title' => 'required|string|max:240', 'governance_domain' => ['required', Rule::in(array_keys(self::DOMAINS))],
            'instrument_type' => ['required', Rule::in(array_keys(self::INSTRUMENT_TYPES))], 'version' => 'required|string|max:30|regex:/^[A-Za-z0-9._-]+$/',
            'scope_type' => ['required', Rule::in(array_keys(self::SCOPE_TYPES))],
            'portfolio_id' => ['nullable', 'uuid', Rule::exists('myb_sectors', 'id')],
            'think_tank_member_id' => ['nullable', 'uuid', Rule::exists('attp_consortium_think_tanks', 'id')->where('status', 'active')],
            'owner_user_id' => ['nullable', 'uuid', $this->governanceUserRule()],
            'steward_user_id' => ['nullable', 'uuid', $this->governanceUserRule()],
            'data_classification' => ['required', Rule::in(array_keys(self::CLASSIFICATIONS))],
            'risk_rating' => ['required', Rule::in(array_keys(self::RISKS))],
            'implementation_status' => ['required', Rule::in(array_keys(self::IMPLEMENTATION_STATUSES))],
            'review_frequency' => ['required', Rule::in(array_keys(self::FREQUENCIES))],
            'effective_date' => 'nullable|date', 'next_review_date' => 'nullable|date|after:effective_date',
            'description' => 'nullable|string|max:5000', 'requirements' => 'nullable|string|max:20000',
            'evidence_notes' => 'nullable|string|max:5000',
            'evidence_repository_item_id' => 'nullable|uuid|exists:me_knowledge_evidence_items,id',
        ]);
    }

    private function actionRules(Request $request): array
    {
        return $request->validate([
            'action_type' => ['required', Rule::in(array_keys(self::ACTION_TYPES))],
            'title' => 'required|string|max:240', 'description' => 'nullable|string|max:5000',
            'priority' => ['required', Rule::in(array_keys(self::ACTION_PRIORITIES))],
            'status' => ['required', Rule::in(['open', 'in_progress'])],
            'owner_user_id' => ['nullable', 'uuid', $this->governanceUserRule()], 'due_date' => 'nullable|date',
        ]);
    }

    private function controlQuery(Request $request): Builder
    {
        $query = MeDataGovernanceControl::query();
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $ids = $this->assignedPortfolioIds($request->user());
            $query->where(fn (Builder $scope) => $scope->where('scope_type', 'enterprise')->orWhereIn('portfolio_id', $ids));
        }

        return $query;
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        if ($filters['lifecycle'] === 'active') {
            $query->where('status', '!=', 'retired');
        }
        if ($filters['lifecycle'] === 'retired') {
            $query->where('status', 'retired');
        }
        foreach (['governance_domain' => 'domain', 'status' => 'status', 'implementation_status' => 'implementation', 'risk_rating' => 'risk', 'data_classification' => 'classification', 'scope_type' => 'scope_type', 'portfolio_id' => 'portfolio_id', 'owner_user_id' => 'owner_user_id'] as $column => $key) {
            if ($filters[$key] !== '') {
                $query->where($column, $filters[$key]);
            }
        }
        if ($filters['review_state'] === 'overdue') {
            $query->whereDate('next_review_date', '<', today())->where('status', '!=', 'retired');
        }
        if ($filters['review_state'] === 'due_soon') {
            $query->whereBetween('next_review_date', [today(), today()->addDays(30)])->where('status', '!=', 'retired');
        }
        if ($filters['review_state'] === 'scheduled') {
            $query->whereDate('next_review_date', '>', today()->addDays(30))->where('status', '!=', 'retired');
        }
        if ($filters['review_state'] === 'unscheduled') {
            $query->whereNull('next_review_date')->where('status', '!=', 'retired');
        }
        if ($filters['action_state'] === 'open') {
            $query->whereHas('actions', fn (Builder $action) => $action->whereIn('status', ['open', 'in_progress']));
        }
        if ($filters['action_state'] === 'overdue') {
            $query->whereHas('actions', fn (Builder $action) => $action->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today()));
        }
        if ($filters['action_state'] === 'clear') {
            $query->whereDoesntHave('actions', fn (Builder $action) => $action->whereIn('status', ['open', 'in_progress']));
        }
        if ($filters['q'] !== '') {
            $like = '%'.addcslashes(strtolower($filters['q']), '%_\\').'%';
            $query->where(fn (Builder $search) => $search->whereRaw('LOWER(control_code) LIKE ?', [$like])
                ->orWhereRaw('LOWER(title) LIKE ?', [$like])->orWhereRaw("LOWER(COALESCE(description, '')) LIKE ?", [$like])
                ->orWhereRaw("LOWER(COALESCE(requirements, '')) LIKE ?", [$like])
                ->orWhereHas('owner', fn (Builder $owner) => $owner->whereRaw('LOWER(name) LIKE ?', [$like]))
                ->orWhereHas('steward', fn (Builder $steward) => $steward->whereRaw('LOWER(name) LIKE ?', [$like])));
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'review' => $query->orderByRaw('CASE WHEN next_review_date IS NULL THEN 1 ELSE 0 END')->orderBy('next_review_date'),
            'newest' => $query->latest(), 'updated' => $query->latest('updated_at'),
            'code' => $query->orderBy('control_code')->orderByDesc('version'), 'title' => $query->orderBy('title'),
            default => $query->orderByRaw("CASE risk_rating WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'moderate' THEN 2 ELSE 3 END")
                ->orderByRaw("CASE status WHEN 'under_review' THEN 0 WHEN 'draft' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END")->orderBy('control_code'),
        };
    }

    private function metrics(Collection $controls): array
    {
        $active = $controls->where('status', '!=', 'retired');
        $approved = $active->where('status', 'approved');
        $implemented = $active->where('implementation_status', 'implemented');
        $owned = $active->filter(fn ($control) => $control->owner_user_id && $control->steward_user_id);
        $openActions = (int) $active->sum('open_actions_count');
        $overdueReviews = $active->filter(fn (MeDataGovernanceControl $control) => $control->reviewState() === 'overdue')->count();
        $dueSoon = $active->filter(fn (MeDataGovernanceControl $control) => $control->reviewState() === 'due_soon')->count();

        return [
            'controls' => $controls->count(), 'active' => $active->count(), 'approved' => $approved->count(),
            'implemented' => $implemented->count(), 'under_review' => $active->where('status', 'under_review')->count(),
            'draft' => $active->where('status', 'draft')->count(), 'retired' => $controls->where('status', 'retired')->count(),
            'open_actions' => $openActions, 'overdue_reviews' => $overdueReviews, 'due_soon' => $dueSoon,
            'critical_high' => $active->whereIn('risk_rating', ['critical', 'high'])->count(),
            'approval_rate' => $active->isNotEmpty() ? round(($approved->count() / $active->count()) * 100, 1) : 0.0,
            'implementation_rate' => $active->isNotEmpty() ? round(($implemented->count() / $active->count()) * 100, 1) : 0.0,
            'ownership_rate' => $active->isNotEmpty() ? round(($owned->count() / $active->count()) * 100, 1) : 0.0,
        ];
    }

    private function charts(Collection $controls): array
    {
        $domain = collect(self::DOMAINS)->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'controls' => $controls->where('governance_domain', $key)->count(),
            'implemented' => $controls->where('governance_domain', $key)->where('implementation_status', 'implemented')->count(),
        ])->filter(fn ($row) => $row['controls'] > 0)->values();
        $lifecycle = collect(self::STATUSES)->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'count' => $controls->where('status', $key)->count(),
            'color' => match ($key) {
                'approved' => '#187459', 'under_review' => '#3f8aa0', 'draft' => '#b8791f', default => '#73838a'
            },
        ])->filter(fn ($row) => $row['count'] > 0)->values();
        $risk = collect(self::RISKS)->map(fn ($label, $key) => [
            'key' => $key, 'label' => $label, 'count' => $controls->where('risk_rating', $key)->count(),
            'color' => match ($key) {
                'critical' => '#8f3744', 'high' => '#b85f3f', 'moderate' => '#c09338', default => '#43856d'
            },
        ])->filter(fn ($row) => $row['count'] > 0)->values();

        return compact('domain', 'lifecycle', 'risk');
    }

    private function portfolios(Request $request): Collection
    {
        $query = Sector::query()->orderBy('name');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToSectors($query, $request->user());
        }

        return $query->get(['id', 'name']);
    }

    private function governanceUsers(): Collection
    {
        return User::query()->with('role:id,name')->where('is_blacklisted', false)->where('is_disabled', false)
            ->where(fn (Builder $query) => $query->whereNull('user_type')->orWhereIn('user_type', ['admin', 'think_tank']))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'email', 'role_id', 'user_type', 'think_tank_member_id']);
    }

    private function evidenceItems(Request $request): Collection
    {
        $query = MeKnowledgeEvidenceItem::query()->whereNull('retired_at')->where('validation_status', 'validated')->orderBy('title');
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            $this->applyAssignedPortfolioScopeToPortfolioOwnedRecords($query, $request->user());
        }

        return $query->limit(500)->get(['id', 'portfolio_id', 'title', 'document_type', 'validation_status']);
    }

    private function assertAuthorizedPortfolio(string $portfolioId, Collection $portfolios): void
    {
        if ($portfolioId !== '' && ! $portfolios->contains(fn ($portfolio) => (string) $portfolio->id === $portfolioId)) {
            abort(403, 'The selected portfolio is outside your assigned scope.');
        }
    }

    private function assertInputScope(Request $request, array &$data): void
    {
        if ($data['scope_type'] === 'enterprise') {
            $data['portfolio_id'] = null;
            $data['think_tank_member_id'] = null;
        }
        if ($data['scope_type'] === 'portfolio') {
            $data['think_tank_member_id'] = null;
            if (blank($data['portfolio_id'])) {
                throw ValidationException::withMessages(['portfolio_id' => 'Select a portfolio for a portfolio-scoped instrument.']);
            }
        }
        if ($data['scope_type'] === 'think_tank' && blank($data['think_tank_member_id'])) {
            throw ValidationException::withMessages(['think_tank_member_id' => 'Select a think tank for a think-tank-scoped instrument.']);
        }
        if ($this->userHasAssignedPortfolioScope($request->user())) {
            if ($data['scope_type'] === 'enterprise' || blank($data['portfolio_id']) || ! in_array((string) $data['portfolio_id'], $this->assignedPortfolioIds($request->user()), true)) {
                throw ValidationException::withMessages(['portfolio_id' => 'Portfolio managers can maintain only instruments assigned to one of their portfolios.']);
            }
        }
        if (filled($data['evidence_repository_item_id'] ?? null)) {
            $evidence = MeKnowledgeEvidenceItem::query()
                ->whereKey($data['evidence_repository_item_id'])
                ->whereNull('retired_at')
                ->where('validation_status', 'validated')
                ->first();
            if (! $evidence) {
                throw ValidationException::withMessages([
                    'evidence_repository_item_id' => 'Governance instruments may link only an active, validated repository document.',
                ]);
            }
            if ($this->userHasAssignedPortfolioScope($request->user())
                && ! in_array((string) $evidence->portfolio_id, $this->assignedPortfolioIds($request->user()), true)) {
                throw ValidationException::withMessages([
                    'evidence_repository_item_id' => 'The selected evidence document is outside your assigned portfolio scope.',
                ]);
            }
        }
    }

    private function assertManageable(Request $request, MeDataGovernanceControl $control): void
    {
        if ($this->userHasAssignedPortfolioScope($request->user()) && ! $this->portfolioOwnedRecordIsInAssignedPortfolio($control, $request->user())) {
            abort(403, 'You may view this enterprise instrument but cannot change it.');
        }
    }

    private function nextVersion(MeDataGovernanceControl $control): string
    {
        $versions = MeDataGovernanceControl::query()->where('control_code', $control->control_code)->pluck('version');
        $major = max(1, (int) $versions->map(fn ($version) => (int) explode('.', (string) $version)[0])->max());
        do {
            $candidate = ($major + 1).'.0';
            $major++;
        } while ($versions->contains($candidate));

        return $candidate;
    }

    private function governanceUserRule(): mixed
    {
        return Rule::exists('users', 'id')->where(fn ($query) => $query
            ->where('is_disabled', false)
            ->where('is_blacklisted', false)
            ->where(fn ($type) => $type->whereNull('user_type')->orWhereIn('user_type', ['admin', 'think_tank'])));
    }

    private function reportData(Request $request): array
    {
        $filters = $this->filters($request);
        $portfolios = $this->portfolios($request);
        $this->assertAuthorizedPortfolio($filters['portfolio_id'], $portfolios);
        $query = $this->applyFilters($this->controlQuery($request), $filters);
        if ($filters['control_id']) {
            $query->whereKey($filters['control_id']);
        }
        $controls = $query->with(['portfolio:id,name', 'thinkTank:id,name,country', 'owner:id,name,email', 'steward:id,name,email', 'approver:id,name', 'evidence:id,title,validation_status'])
            ->with(['actions' => fn ($action) => $action->with('owner:id,name')->orderBy('due_date')])
            ->withCount([
                'actions as open_actions_count' => fn (Builder $action) => $action->whereIn('status', ['open', 'in_progress']),
                'actions as overdue_actions_count' => fn (Builder $action) => $action->whereIn('status', ['open', 'in_progress'])->whereDate('due_date', '<', today()),
            ])->tap(fn (Builder $builder) => $this->applySort($builder, $filters['sort']))->get();
        abort_if($filters['control_id'] && $controls->isEmpty(), 404, 'The selected governance instrument is outside the active scope.');

        return [$controls, $filters, $portfolios];
    }

    private function scopeLabel(array $filters, Collection $portfolios, Collection $controls): string
    {
        if ($filters['control_id'] && $controls->first()) {
            return $controls->first()->control_code.' v'.$controls->first()->version.' - '.$controls->first()->title;
        }
        $parts = [];
        if ($filters['domain']) {
            $parts[] = self::DOMAINS[$filters['domain']];
        }
        if ($filters['portfolio_id']) {
            $parts[] = $portfolios->firstWhere('id', $filters['portfolio_id'])?->name ?: 'Selected portfolio';
        }
        if ($filters['status']) {
            $parts[] = self::STATUSES[$filters['status']];
        }
        if ($filters['risk']) {
            $parts[] = self::RISKS[$filters['risk']].' risk';
        }
        if ($filters['q']) {
            $parts[] = 'Search: '.$filters['q'];
        }

        return $parts === [] ? 'All active data-governance instruments' : implode(' | ', $parts);
    }

    private function audit(Request $request, string $action, string $message, MeDataGovernanceControl $control, array $extra = []): void
    {
        try {
            SystemAuditLog::query()->create([
                'user_id' => $request->user()?->id, 'module' => 'me_data_governance', 'action' => $action,
                'action_message' => $message, 'description' => $message, 'method' => $request->method(),
                'url' => $request->fullUrl(), 'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(), 'user_agent' => Str::limit((string) $request->userAgent(), 1000),
                'status_code' => 200, 'payload' => ['control_id' => $control->id, 'control_code' => $control->control_code, 'version' => $control->version] + $extra,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Data-governance audit event could not be recorded.', ['action' => $action, 'error' => $exception->getMessage()]);
        }
    }
}
