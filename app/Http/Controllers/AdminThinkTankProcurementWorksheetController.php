<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumThinkTank;
use App\Models\SystemAuditLog;
use App\Models\ThinkTankProcurementEvent;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Support\PdfBranding;
use App\Support\PdfPageNumbering;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AdminThinkTankProcurementWorksheetController extends Controller
{
    private const ITEM_STATUSES = [
        'draft',
        'submitted',
        'revision_requested',
        'rejected',
        'approved',
        'no_objection_obtained',
        'published',
    ];

    private const PLAN_STATUSES = [
        'draft',
        'submitted',
        'revision_requested',
        'rejected',
        'approved',
    ];

    private const QUEUES = [
        'secretariat_review',
        'world_bank_pending',
        'ready_to_execute',
        'published',
        'action_required',
    ];

    public function index(Request $request): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'think_tank_member_id' => ['nullable', 'uuid', 'exists:attp_consortium_think_tanks,id'],
            'fiscal_year' => ['nullable', 'string', 'max:20'],
            'plan_status' => ['nullable', Rule::in(self::PLAN_STATUSES)],
            'item_status' => ['nullable', Rule::in(self::ITEM_STATUSES)],
            'queue' => ['nullable', Rule::in(self::QUEUES)],
            'documents' => ['nullable', Rule::in(['missing_tor', 'has_tor', 'has_no_objection', 'missing_no_objection'])],
        ]);
        $filters = [
            'q' => trim((string) ($validated['q'] ?? '')),
            'think_tank_member_id' => trim((string) ($validated['think_tank_member_id'] ?? '')),
            'fiscal_year' => trim((string) ($validated['fiscal_year'] ?? '')),
            'plan_status' => trim((string) ($validated['plan_status'] ?? '')),
            'item_status' => trim((string) ($validated['item_status'] ?? '')),
            'queue' => trim((string) ($validated['queue'] ?? '')),
            'documents' => trim((string) ($validated['documents'] ?? '')),
        ];

        $query = $this->filteredItems($filters);
        $items = (clone $query)
            ->with([
                'plan.member:id,name,country,consortium_id',
                'plan.consortium:id,name',
                'documents:id,item_id,document_type,document_name,original_name,file_size',
            ])
            ->withCount([
                'documents',
                'documents as tor_documents_count' => fn (Builder $documents) => $documents->where('document_type', 'tor'),
                'documents as no_objection_documents_count' => fn (Builder $documents) => $documents->where('document_type', 'no_objection'),
            ])
            ->orderByRaw("CASE WHEN status = 'submitted' THEN 0 WHEN status = 'approved' THEN 1 WHEN status = 'no_objection_obtained' THEN 2 ELSE 3 END")
            ->orderByDesc('updated_at')
            ->paginate(24)
            ->withQueryString();

        $stats = [
            'total' => (clone $query)->count(),
            'secretariat_review' => (clone $query)->where('status', 'submitted')->count(),
            'world_bank_pending' => (clone $query)
                ->where('status', 'approved')
                ->whereHas('plan', fn (Builder $plan) => $plan->where('status', 'approved'))
                ->count(),
            'ready_to_execute' => (clone $query)->where('status', 'no_objection_obtained')->count(),
            'published' => (clone $query)->where('status', 'published')->count(),
            'action_required' => (clone $query)->whereIn('status', ['revision_requested', 'rejected'])->count(),
        ];
        $currencyTotals = (clone $query)
            ->selectRaw("COALESCE(NULLIF(TRIM(currency), ''), 'Unspecified') as currency_code, SUM(estimated_amount) as aggregate_amount")
            ->groupBy('currency_code')
            ->orderBy('currency_code')
            ->get()
            ->map(fn (ThinkTankProcurementItem $row): array => [
                'currency' => (string) $row->getAttribute('currency_code'),
                'amount' => (float) $row->getAttribute('aggregate_amount'),
            ]);
        $members = ConsortiumThinkTank::query()
            ->whereHas('procurementPlans')
            ->orderBy('name')
            ->get(['id', 'name']);
        $fiscalYears = ThinkTankProcurementPlan::query()
            ->pluck('fiscal_year')
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return view('think-tank-procurement-admin.worksheet.index', compact(
            'items', 'stats', 'currencyTotals', 'members', 'fiscalYears', 'filters'
        ));
    }

    public function show(Request $request, ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item): View
    {
        return view('think-tank-procurement-admin.worksheet.show', $this->itemContext($request, $plan, $item));
    }

    public function pdf(
        Request $request,
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item
    ): Response {
        $data = $request->validate(['download' => ['nullable', 'boolean']]);
        $context = $this->itemContext($request, $plan, $item);
        $context['generatedAt'] = now();
        $filename = 'procurement-worksheet-'.(Str::slug($item->item_code ?: (string) $item->id) ?: 'item').'.pdf';

        try {
            SystemAuditLog::create([
                'user_id' => $request->user()?->id,
                'module' => 'think_tank_procurement',
                'action' => 'procurement_item_worksheet_pdf_'.(! empty($data['download']) ? 'downloaded' : 'viewed'),
                'action_message' => 'Procurement item worksheet PDF accessed',
                'description' => $item->item_code.' - '.$item->title,
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'route_name' => $request->route()?->getName(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status_code' => 200,
                'payload' => [
                    'plan_id' => $plan->id,
                    'item_id' => $item->id,
                    'disposition' => ! empty($data['download']) ? 'download' : 'inline',
                ],
            ]);
        } catch (Throwable) {
            // The controlled worksheet remains available if the secondary audit store is unavailable.
        }

        $pdf = Pdf::loadView('think-tank-procurement-admin.worksheet.pdf', array_merge(
            $context,
            PdfBranding::viewData(),
        ))
            ->setPaper('a4', 'portrait');
        $pdf = PdfPageNumbering::stamp($pdf);
        $response = ! empty($data['download'])
            ? $pdf->download($filename)
            : $pdf->stream($filename);
        $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    /** @param array<string, string> $filters */
    private function filteredItems(array $filters): Builder
    {
        return ThinkTankProcurementItem::query()
            ->whereHas('plan')
            ->when($filters['q'], function (Builder $query, string $keyword): void {
                $search = '%'.$keyword.'%';
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('title', 'like', $search)
                        ->orWhere('item_code', 'like', $search)
                        ->orWhere('source_reference', 'like', $search)
                        ->orWhere('step_reference', 'like', $search)
                        ->orWhere('no_objection_reference', 'like', $search)
                        ->orWhereHas('plan', function (Builder $plan) use ($search): void {
                            $plan->where('plan_code', 'like', $search)
                                ->orWhereHas('member', fn (Builder $member) => $member->where('name', 'like', $search));
                        });
                });
            })
            ->when($filters['think_tank_member_id'], fn (Builder $query, string $id) =>
                $query->whereHas('plan', fn (Builder $plan) => $plan->where('think_tank_member_id', $id)))
            ->when($filters['fiscal_year'], fn (Builder $query, string $year) =>
                $query->whereHas('plan', fn (Builder $plan) => $plan->where('fiscal_year', $year)))
            ->when($filters['plan_status'], fn (Builder $query, string $status) =>
                $query->whereHas('plan', fn (Builder $plan) => $plan->where('status', $status)))
            ->when($filters['item_status'], fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['queue'], fn (Builder $query, string $queue) => $this->applyQueue($query, $queue))
            ->when($filters['documents'], function (Builder $query, string $documents): void {
                match ($documents) {
                    'missing_tor' => $query->whereDoesntHave('documents', fn (Builder $document) => $document->where('document_type', 'tor')),
                    'has_tor' => $query->whereHas('documents', fn (Builder $document) => $document->where('document_type', 'tor')),
                    'has_no_objection' => $query->whereHas('documents', fn (Builder $document) => $document->where('document_type', 'no_objection')),
                    'missing_no_objection' => $query->whereDoesntHave('documents', fn (Builder $document) => $document->where('document_type', 'no_objection')),
                };
            });
    }

    private function applyQueue(Builder $query, string $queue): void
    {
        match ($queue) {
            'secretariat_review' => $query
                ->where('status', 'submitted')
                ->whereHas('plan', fn (Builder $plan) => $plan->whereIn('status', ['submitted', 'revision_requested'])),
            'world_bank_pending' => $query
                ->where('status', 'approved')
                ->whereHas('plan', fn (Builder $plan) => $plan->where('status', 'approved')),
            'ready_to_execute' => $query->where('status', 'no_objection_obtained'),
            'published' => $query->where('status', 'published'),
            'action_required' => $query->whereIn('status', ['revision_requested', 'rejected']),
        };
    }

    /** @return array<string, mixed> */
    private function itemContext(
        Request $request,
        ThinkTankProcurementPlan $plan,
        ThinkTankProcurementItem $item
    ): array {
        abort_unless((string) $item->plan_id === (string) $plan->id, 404);

        $plan->loadMissing(['member:id,name,country,email,consortium_id', 'consortium:id,name,currency']);
        $item->loadMissing([
            'documents.uploader:id,name',
            'reviewer:id,name',
            'procurement:id,title,slug,status,application_start_date,application_end_date',
        ]);
        $timeline = ThinkTankProcurementEvent::query()
            ->where('plan_id', $plan->id)
            ->where(fn (Builder $events) => $events->whereNull('item_id')->orWhere('item_id', $item->id))
            ->with('actor:id,name')
            ->withCount([
                'statusNotifications as notifications_queued_count' => fn (Builder $notifications) => $notifications->whereIn('status', ['pending', 'processing', 'sending']),
                'statusNotifications as notifications_sent_count' => fn (Builder $notifications) => $notifications->where('status', 'sent'),
                'statusNotifications as notifications_failed_count' => fn (Builder $notifications) => $notifications->where('status', 'failed'),
            ])
            ->oldest('created_at')
            ->get();
        $user = $request->user();
        $permissions = [
            'review' => $user?->can('think_tank.procurement.review') || $user?->can('procurement.manage_all'),
            'step' => $user?->can('think_tank.procurement.step') || $user?->can('procurement.manage_all'),
        ];

        return [
            'plan' => $plan,
            'item' => $item,
            'timeline' => $timeline,
            'permissions' => $permissions,
            'workflowStages' => $this->workflowStages($plan, $item),
        ];
    }

    /** @return array<int, array{label: string, state: string, detail: string}> */
    private function workflowStages(ThinkTankProcurementPlan $plan, ThinkTankProcurementItem $item): array
    {
        $rank = match ($item->status) {
            'submitted' => 2,
            'approved' => $plan->status !== 'approved'
                ? 2
                : ($item->step_exported_at ? 4 : 3),
            'no_objection_obtained' => 5,
            'published' => 6,
            default => 0,
        };
        $labels = [
            1 => ['Submitted', 'Think Tank submission received'],
            2 => ['Secretariat review', 'Item reviewed by ATTP'],
            3 => ['STEP handoff', 'Approved plan ready for World Bank processing'],
            4 => ['World Bank review', 'Pending World Bank no-objection'],
            5 => ['Ready to execute', 'No-objection received and recorded'],
            6 => ['Published', 'Procurement opportunity in execution'],
        ];

        return collect($labels)->map(function (array $stage, int $position) use ($rank, $item): array {
            $state = $position < $rank ? 'complete' : ($position === $rank ? 'current' : 'upcoming');
            if (in_array($item->status, ['revision_requested', 'rejected'], true) && $position === 1) {
                $state = 'attention';
            }

            return ['label' => $stage[0], 'detail' => $stage[1], 'state' => $state];
        })->values()->all();
    }
}
