<?php

namespace App\Http\Controllers;

use App\Exports\ThinkTankProcurementStepExport;
use App\Models\ConsortiumThinkTank;
use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementDocument;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Services\ThinkTankProcurementWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class AdminThinkTankProcurementController extends Controller
{
    public function __construct(
        private readonly ThinkTankProcurementWorkflowService $workflow
    ) {}

    public function index(Request $request)
    {
        $filters = $this->filters($request);
        $query = $this->planQuery($filters);
        $plans = (clone $query)
            ->with(['member:id,name,country,consortium_id', 'consortium:id,name'])
            ->withCount([
                'items',
                'items as approved_items_count' => fn ($items) => $items->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_APPROVED,
                    ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                    ThinkTankProcurementItem::STATUS_PUBLISHED,
                ]),
                'items as action_items_count' => fn ($items) => $items->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                    ThinkTankProcurementItem::STATUS_REJECTED,
                ]),
                'items as no_objection_items_count' => fn ($items) => $items->whereIn('status', [
                    ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                    ThinkTankProcurementItem::STATUS_PUBLISHED,
                ]),
            ])
            ->with('items:id,plan_id,status,estimated_amount')
            ->orderByDesc('fiscal_year')
            ->latest('submitted_at')
            ->get();

        $folders = $plans
            ->groupBy(fn (ThinkTankProcurementPlan $plan): string => (string) $plan->think_tank_member_id)
            ->sortBy(fn ($folderPlans): string => Str::lower((string) $folderPlans->first()?->member?->name))
            ->values();
        $items = $plans->flatMap->items;
        $stepReadyItems = $plans
            ->where('status', ThinkTankProcurementPlan::STATUS_APPROVED)
            ->flatMap->items
            ->whereIn('status', [
                ThinkTankProcurementItem::STATUS_APPROVED,
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ]);
        $stats = [
            'folders' => $folders->count(),
            'plans' => $plans->count(),
            'draft_plans' => $plans->where('status', ThinkTankProcurementPlan::STATUS_DRAFT)->count(),
            'submitted' => $plans->where('status', ThinkTankProcurementPlan::STATUS_SUBMITTED)->count(),
            'approved_plans' => $plans->where('status', ThinkTankProcurementPlan::STATUS_APPROVED)->count(),
            'plan_action_required' => $plans->whereIn('status', [
                ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementPlan::STATUS_REJECTED,
            ])->count(),
            'items' => $items->count(),
            'budget' => (float) $items->sum('estimated_amount'),
            'action_required' => $items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementItem::STATUS_REJECTED,
            ])->count(),
            'no_objection' => $items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])->count(),
            'step_ready' => $stepReadyItems->count(),
        ];
        $members = ConsortiumThinkTank::query()->whereHas('procurementPlans')->orderBy('name')->get(['id', 'name']);
        $fiscalYears = ThinkTankProcurementPlan::query()->pluck('fiscal_year')->filter()->unique()->sortDesc()->values();
        $yearFilters = [...$filters, 'fiscal_year' => ''];
        $yearCounts = $this->planQuery($yearFilters)
            ->select('fiscal_year')
            ->selectRaw('COUNT(*) as plan_count')
            ->groupBy('fiscal_year')
            ->pluck('plan_count', 'fiscal_year')
            ->map(fn ($count) => (int) $count);

        return view('think-tank-procurement-admin.index', compact(
            'folders', 'stats', 'members', 'fiscalYears', 'yearCounts', 'filters'
        ));
    }

    public function show(ThinkTankProcurementPlan $plan)
    {
        $plan->load([
            'member.portalUsers:id,name,email,think_tank_member_id',
            'member.portalUser:id,name,email',
            'consortium:id,name,currency',
            'items.documents.uploader:id,name',
            'items.procurement:id,title,slug,status,application_start_date,application_end_date',
            'events.actor:id,name',
            'events.item:id,item_code,title',
        ]);

        $stats = [
            'items' => $plan->items->count(),
            'budget' => (float) $plan->items->sum('estimated_amount'),
            'approved' => $plan->items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_APPROVED,
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])->count(),
            'action' => $plan->items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementItem::STATUS_REJECTED,
            ])->count(),
            'no_objection' => $plan->items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])->count(),
        ];

        return view('think-tank-procurement-admin.show', compact('plan', 'stats'));
    }

    public function decidePlan(Request $request, ThinkTankProcurementPlan $plan)
    {
        $data = $request->validate([
            'decision' => 'required|in:approve,revision_requested,rejected',
            'reason' => 'nullable|string|max:5000',
        ]);
        $this->workflow->decidePlan($plan, $request->user(), $data['decision'], $data['reason'] ?? null);

        return back()->with('success', match ($data['decision']) {
            'approve' => 'The full procurement plan has been approved.',
            'revision_requested' => 'The plan was returned to the Think Tank for correction.',
            default => 'The full procurement plan was rejected with the recorded reason.',
        });
    }

    public function decideItem(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        $this->assertItem($item, $plan);
        $data = $request->validate([
            'decision' => 'required|in:approve,revision_requested,rejected',
            'reason' => 'nullable|string|max:5000',
        ]);
        $this->workflow->reviewItem($item, $request->user(), $data['decision'], $data['reason'] ?? null);

        return back()->with('success', $item->item_code.' review decision recorded.');
    }

    public function noObjection(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item)
    {
        $this->assertItem($item, $plan);
        $data = $request->validate([
            'step_reference' => 'required|string|max:255',
            'no_objection_reference' => 'nullable|string|max:255',
            'no_objection_date' => 'required|date',
            'no_objection_notes' => 'nullable|string|max:5000',
            'no_objection_document' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
        ]);

        $storedPath = null;
        try {
            DB::transaction(function () use ($request, $plan, $item, $data, &$storedPath): void {
                if ($request->hasFile('no_objection_document')) {
                    $file = $request->file('no_objection_document');
                    $storedPath = $file->store("think-tank-procurement/{$plan->id}/{$item->id}", 'local');
                    $item->documents()->create([
                        'document_type' => 'no_objection',
                        'document_name' => 'World Bank No-Objection',
                        'original_name' => basename($file->getClientOriginalName()),
                        'file_path' => $storedPath,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => (int) $file->getSize(),
                        'uploaded_by' => $request->user()->id,
                    ]);
                }
                $this->workflow->recordNoObjection($item, $request->user(), $data);
            });
        } catch (\Throwable $exception) {
            if ($storedPath) {
                Storage::disk('local')->delete($storedPath);
            }
            throw $exception;
        }

        return back()->with('success', 'World Bank no-objection recorded and the Think Tank was notified by email.');
    }

    public function downloadDocument(
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item,
        ThinkTankProcurementDocument $document
    ) {
        $this->assertItem($item, $plan);
        abort_unless((string) $document->item_id === (string) $item->id, 404);
        abort_unless(str_starts_with($document->file_path, "think-tank-procurement/{$plan->id}/{$item->id}/"), 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404, 'Document file not found.');

        return Storage::disk('local')->download($document->file_path, $document->original_name, [
            'Content-Type' => $document->mime_type ?: 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function reports(Request $request)
    {
        $filters = $this->filters($request);
        $itemsQuery = $this->itemQuery($filters);
        $items = (clone $itemsQuery)
            ->with(['plan.member:id,name,country', 'plan.consortium:id,name', 'documents:id,item_id,document_type'])
            ->latest()
            ->paginate(30)
            ->withQueryString();
        $allItems = (clone $itemsQuery)
            ->with(['plan.member:id,name,country', 'plan.consortium:id,name', 'documents:id,item_id,document_type'])
            ->get();
        $summary = $this->reportSummary($allItems);
        $byThinkTank = $this->groupReportItems($allItems);
        $members = ConsortiumThinkTank::query()->whereHas('procurementPlans')->orderBy('name')->get(['id', 'name']);
        $fiscalYears = ThinkTankProcurementPlan::query()->pluck('fiscal_year')->filter()->unique()->sortDesc()->values();

        return view('think-tank-procurement-admin.reports', compact(
            'items', 'summary', 'byThinkTank', 'members', 'fiscalYears', 'filters'
        ));
    }

    public function exportReportPdf(Request $request)
    {
        $data = $request->validate([
            'scope' => 'required|in:individual,consolidated',
            'think_tank_member_id' => 'required_if:scope,individual|nullable|uuid|exists:attp_consortium_think_tanks,id',
            'fiscal_year' => 'nullable|string|max:20',
            'status' => 'nullable|in:draft,submitted,revision_requested,rejected,approved,no_objection_obtained,published',
            'q' => 'nullable|string|max:255',
        ]);

        $filters = [
            'status' => trim((string) ($data['status'] ?? '')),
            'fiscal_year' => trim((string) ($data['fiscal_year'] ?? '')),
            'think_tank_member_id' => $data['scope'] === 'individual'
                ? trim((string) ($data['think_tank_member_id'] ?? ''))
                : '',
            'q' => trim((string) ($data['q'] ?? '')),
        ];
        $items = $this->itemQuery($filters)
            ->with([
                'plan.member:id,name,country',
                'plan.consortium:id,name',
                'documents:id,item_id,document_type,document_name,original_name',
            ])
            ->orderBy('item_code')
            ->get();
        abort_if($items->isEmpty(), 422, 'No procurement items match this PDF report.');

        $selectedMember = $data['scope'] === 'individual'
            ? ConsortiumThinkTank::query()->findOrFail($filters['think_tank_member_id'])
            : null;
        $summary = $this->reportSummary($items);
        $byThinkTank = $this->groupReportItems($items);
        $plans = $items->pluck('plan')->filter()->unique('id')->values();
        $reportGeneratedAt = now();
        $reportTitle = $data['scope'] === 'individual'
            ? 'Think Tank Procurement Report'
            : 'Consolidated Think Tank Procurement Report';
        $scopeLabel = $selectedMember?->name ?: 'All Think Tanks';

        try {
            SystemAuditLog::create([
                'user_id' => $request->user()->id,
                'module' => 'think_tank_procurement',
                'action' => 'procurement_report_pdf_downloaded',
                'action_message' => $reportTitle.' downloaded',
                'description' => $scopeLabel,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 200,
                'payload' => [
                    'scope' => $data['scope'],
                    'think_tank_member_id' => $selectedMember?->id,
                    'filters' => $filters,
                    'item_count' => $items->count(),
                ],
            ]);
        } catch (\Throwable) {
            // The report must remain available if the secondary audit store is unavailable.
        }

        $fiscalYearFilename = Str::slug(str_replace(
            ['/', '\\'],
            '-',
            $filters['fiscal_year'] ?: 'all-years'
        )) ?: 'all-years';
        $filename = ($data['scope'] === 'individual'
            ? 'think-tank-procurement-'.Str::slug($scopeLabel)
            : 'consolidated-think-tank-procurement')
            .'-'.$fiscalYearFilename
            .'-'.$reportGeneratedAt->format('Ymd-His').'.pdf';

        return Pdf::loadView('think-tank-procurement-admin.report-pdf', compact(
            'items', 'summary', 'byThinkTank', 'plans', 'filters', 'selectedMember',
            'reportGeneratedAt', 'reportTitle', 'scopeLabel'
        ))->setPaper('a4', 'landscape')->download($filename);
    }

    public function exportStep(Request $request)
    {
        $data = $request->validate([
            'selection_mode' => 'required|in:explicit,filtered',
            'item_ids' => 'nullable|array',
            'item_ids.*' => 'uuid|exists:attp_think_tank_procurement_items,id',
            'think_tank_member_id' => 'nullable|uuid|exists:attp_consortium_think_tanks,id',
            'fiscal_year' => 'nullable|string|max:20',
            'status' => 'nullable|in:draft,submitted,revision_requested,rejected,approved,no_objection_obtained,published',
            'q' => 'nullable|string|max:255',
        ]);

        if ($data['selection_mode'] === 'explicit' && empty($data['item_ids'])) {
            return back()->with('error', 'Select at least one STEP-ready procurement item before downloading the workbook.');
        }

        $query = ThinkTankProcurementItem::query()
            ->whereIn('status', [
                ThinkTankProcurementItem::STATUS_APPROVED,
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])
            ->whereHas('plan', fn ($plan) => $plan->where('status', ThinkTankProcurementPlan::STATUS_APPROVED))
            ->when($data['selection_mode'] === 'explicit', fn ($q) => $q->whereIn('id', $data['item_ids']))
            ->when($data['think_tank_member_id'] ?? null, fn ($q, $id) => $q->whereHas('plan', fn ($p) => $p->where('think_tank_member_id', $id)))
            ->when($data['fiscal_year'] ?? null, fn ($q, $year) => $q->whereHas('plan', fn ($p) => $p->where('fiscal_year', $year)))
            ->when($data['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($data['q'] ?? null, function ($query, $keyword): void {
                $search = '%'.trim($keyword).'%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', $search)
                        ->orWhere('item_code', 'like', $search)
                        ->orWhere('source_reference', 'like', $search);
                });
            })
            ->with(['plan.member', 'plan.consortium', 'documents'])
            ->orderBy('item_code');
        $items = $query->get();

        if ($items->isEmpty()) {
            $message = $data['selection_mode'] === 'explicit'
                ? 'None of the selected items is STEP-ready. The annual plan and item must both be approved before export.'
                : 'No STEP-ready items match the current report filters. Approve the annual plan and procurement items first.';

            return back()->with('error', $message);
        }

        foreach ($items as $item) {
            $item->forceFill([
                'step_exported_at' => now(),
                'step_exported_by' => $request->user()->id,
            ])->save();
            $this->workflow->event($item->plan, $item, $request->user(), 'item_exported_for_step', $item->status, $item->status);
        }

        return Excel::download(
            new ThinkTankProcurementStepExport($items),
            'attp-step-procurement-export-'.now()->format('Ymd-His').'.xlsx'
        );
    }

    private function filters(Request $request): array
    {
        return [
            'status' => trim((string) $request->input('status')),
            'fiscal_year' => trim((string) $request->input('fiscal_year')),
            'think_tank_member_id' => trim((string) $request->input('think_tank_member_id')),
            'q' => trim((string) $request->input('q')),
        ];
    }

    private function reportSummary(Collection $items): array
    {
        return [
            'think_tanks' => $items->pluck('plan.think_tank_member_id')->filter()->unique()->count(),
            'plans' => $items->pluck('plan_id')->filter()->unique()->count(),
            'items' => $items->count(),
            'budget' => (float) $items->sum('estimated_amount'),
            'budget_by_currency' => $items
                ->groupBy(fn ($item) => $item->currency ?: 'USD')
                ->map(fn ($records, $currency) => [
                    'currency' => $currency,
                    'amount' => (float) $records->sum('estimated_amount'),
                ])->values(),
            'approved' => $items->filter(fn ($item) =>
                $item->status === ThinkTankProcurementItem::STATUS_APPROVED
                && $item->plan?->status === ThinkTankProcurementPlan::STATUS_APPROVED
            )->count(),
            'step_eligible' => $items->filter(fn ($item) =>
                $item->plan?->status === ThinkTankProcurementPlan::STATUS_APPROVED
                && in_array($item->status, [
                    ThinkTankProcurementItem::STATUS_APPROVED,
                    ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                    ThinkTankProcurementItem::STATUS_PUBLISHED,
                ], true)
            )->count(),
            'action_required' => $items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                ThinkTankProcurementItem::STATUS_REJECTED,
            ])->count(),
            'no_objection' => $items->whereIn('status', [
                ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                ThinkTankProcurementItem::STATUS_PUBLISHED,
            ])->count(),
            'published' => $items->where('status', ThinkTankProcurementItem::STATUS_PUBLISHED)->count(),
            'step_exported' => $items->whereNotNull('step_exported_at')->count(),
            'tor_documents' => $items->sum(fn ($item) => $item->documents->where('document_type', 'tor')->count()),
            'supporting_documents' => $items->sum(fn ($item) => $item->documents->where('document_type', 'supporting')->count()),
        ];
    }

    private function groupReportItems(Collection $items): Collection
    {
        return $items
            ->groupBy(fn ($item) => (string) ($item->plan?->think_tank_member_id ?: 'unknown'))
            ->map(function (Collection $records): array {
                $first = $records->first();

                return [
                    'id' => $first->plan?->think_tank_member_id,
                    'name' => $first->plan?->member?->name ?: 'Unknown Think Tank',
                    'country' => $first->plan?->member?->country,
                    'consortium' => $first->plan?->consortium?->name,
                    'plans' => $records->pluck('plan_id')->filter()->unique()->count(),
                    'years' => $records->pluck('plan.fiscal_year')->filter()->unique()->sortDesc()->values(),
                    'items' => $records->count(),
                    'budget' => (float) $records->sum('estimated_amount'),
                    'budget_by_currency' => $records
                        ->groupBy(fn ($item) => $item->currency ?: 'USD')
                        ->map(fn ($currencyRecords, $currency) => $currency.' '.number_format((float) $currencyRecords->sum('estimated_amount'), 2))
                        ->values(),
                    'approved' => $records->where('status', ThinkTankProcurementItem::STATUS_APPROVED)->count(),
                    'action_required' => $records->whereIn('status', [
                        ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
                        ThinkTankProcurementItem::STATUS_REJECTED,
                    ])->count(),
                    'no_objection' => $records->whereIn('status', [
                        ThinkTankProcurementItem::STATUS_NO_OBJECTION,
                        ThinkTankProcurementItem::STATUS_PUBLISHED,
                    ])->count(),
                    'published' => $records->where('status', ThinkTankProcurementItem::STATUS_PUBLISHED)->count(),
                ];
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    private function planQuery(array $filters)
    {
        return ThinkTankProcurementPlan::query()
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['fiscal_year'], fn ($query, $year) => $query->where('fiscal_year', $year))
            ->when($filters['think_tank_member_id'], fn ($query, $id) => $query->where('think_tank_member_id', $id))
            ->when($filters['q'], function ($query, $keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', $search)
                        ->orWhere('plan_code', 'like', $search)
                        ->orWhereHas('member', fn ($member) => $member->where('name', 'like', $search));
                });
            });
    }

    private function itemQuery(array $filters)
    {
        return ThinkTankProcurementItem::query()
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['fiscal_year'], fn ($query, $year) => $query->whereHas('plan', fn ($plan) => $plan->where('fiscal_year', $year)))
            ->when($filters['think_tank_member_id'], fn ($query, $id) => $query->whereHas('plan', fn ($plan) => $plan->where('think_tank_member_id', $id)))
            ->when($filters['q'], function ($query, $keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(function ($nested) use ($search): void {
                    $nested->where('title', 'like', $search)
                        ->orWhere('item_code', 'like', $search)
                        ->orWhere('source_reference', 'like', $search);
                });
            });
    }

    private function assertItem(ThinkTankProcurementItem $item, ThinkTankProcurementPlan $plan): void
    {
        abort_unless((string) $item->plan_id === (string) $plan->id, 404);
    }
}
