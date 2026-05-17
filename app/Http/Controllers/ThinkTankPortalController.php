<?php

namespace App\Http\Controllers;

use App\Models\ConsortiumActivityReport;
use App\Models\ConsortiumDisbursementRequest;
use App\Models\ConsortiumExpenseReport;
use App\Models\ConsortiumFundAllocation;
use App\Models\ConsortiumRiskFlag;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\DynamicFormField;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ThinkTankProcurementPlan;
use App\Models\ThinkTankProcurementReview;
use App\Models\ThinkTankResearchOutput;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ThinkTankPortalController extends Controller
{
    public function dashboard(Request $request)
    {
        $member = $this->member($request);
        $member->load(['consortium.funder', 'fundAllocations', 'disbursementRequests', 'reports', 'researchOutputs']);

        $today = CarbonImmutable::now()->startOfDay();
        $reportingPeriodStart = $today->startOfMonth();
        $reportingPeriodEnd = $today->endOfMonth();
        $monthlyReportDue = $reportingPeriodEnd->addDays(7)->startOfDay();
        $monthlyReportDaysLeft = $today->diffInDays($monthlyReportDue, false);
        $dashboardFilter = $this->dashboardFilter($request);
        $periodStart = $dashboardFilter['start'];
        $periodEnd = $dashboardFilter['end'];

        $applyPeriod = function ($query, string $column = 'created_at') use ($periodStart, $periodEnd) {
            return $query
                ->when($periodStart, fn ($periodQuery) => $periodQuery->whereDate($column, '>=', $periodStart))
                ->when($periodEnd, fn ($periodQuery) => $periodQuery->whereDate($column, '<=', $periodEnd));
        };

        $applyReportPeriod = function ($query) use ($periodStart, $periodEnd) {
            if (! $periodStart && ! $periodEnd) {
                return $query;
            }

            return $query->where(function ($periodQuery) use ($periodStart, $periodEnd) {
                if ($periodStart && $periodEnd) {
                    $periodQuery->whereBetween('reporting_period_start', [$periodStart, $periodEnd])
                        ->orWhereBetween('reporting_period_end', [$periodStart, $periodEnd])
                        ->orWhereBetween('submitted_at', [$periodStart, $periodEnd]);
                } elseif ($periodStart) {
                    $periodQuery->whereDate('reporting_period_start', '>=', $periodStart)
                        ->orWhereDate('reporting_period_end', '>=', $periodStart)
                        ->orWhereDate('submitted_at', '>=', $periodStart);
                } elseif ($periodEnd) {
                    $periodQuery->whereDate('reporting_period_start', '<=', $periodEnd)
                        ->orWhereDate('reporting_period_end', '<=', $periodEnd)
                        ->orWhereDate('submitted_at', '<=', $periodEnd);
                }
            });
        };

        $procurementsBase = Procurement::where('think_tank_member_id', $member->id);
        $filteredProcurementsBase = $applyPeriod(Procurement::where('think_tank_member_id', $member->id));
        $procurementIds = (clone $filteredProcurementsBase)->pluck('id');
        $allocationQuery = $applyPeriod(ConsortiumFundAllocation::where('think_tank_member_id', $member->id));
        $disbursementQuery = $applyPeriod(ConsortiumDisbursementRequest::where('think_tank_member_id', $member->id));
        $actualPaymentQuery = $applyPeriod(ProcurementDisbursement::where('think_tank_member_id', $member->id), 'paid_at');
        $expenseQuery = $applyPeriod(ConsortiumExpenseReport::where('think_tank_member_id', $member->id), 'expense_date');
        $reportQuery = $applyReportPeriod(ConsortiumActivityReport::where('think_tank_member_id', $member->id));
        $researchQuery = $applyPeriod(ThinkTankResearchOutput::where('think_tank_member_id', $member->id), 'submitted_at');
        $planQuery = $applyPeriod(ThinkTankProcurementPlan::where('think_tank_member_id', $member->id));

        $actualPaid = (float) (clone $actualPaymentQuery)->sum('amount');
        if ($actualPaid <= 0) {
            $actualPaid = (float) (clone $disbursementQuery)->sum('amount_approved');
        }
        if ($actualPaid <= 0) {
            $actualPaid = (float) (clone $allocationQuery)->sum('amount_disbursed');
        }

        $metrics = [
            'allocated' => (clone $allocationQuery)->sum('amount_allocated') + ($dashboardFilter['is_all_time'] ? (float) $member->budget_allocated : 0),
            'disbursed' => $actualPaid,
            'requested' => (clone $disbursementQuery)->sum('amount_requested'),
            'spent' => (clone $expenseQuery)->sum('amount'),
            'reports' => (clone $reportQuery)->count(),
            'research' => (clone $researchQuery)->count(),
            'procurement_plans' => (clone $planQuery)->count(),
            'opportunities' => $procurementIds->count(),
            'applications' => FormSubmission::whereIn('procurement_id', $procurementIds)->count(),
            'selected' => (clone $filteredProcurementsBase)->whereNotNull('awarded_submission_id')->count(),
            'open_risks' => ConsortiumRiskFlag::where('think_tank_member_id', $member->id)->where('status', 'open')->count(),
        ];

        $metrics['balance'] = max(0, (float) $metrics['disbursed'] - (float) $metrics['spent']);
        $metrics['utilization'] = (float) $metrics['disbursed'] > 0
            ? round(((float) $metrics['spent'] / (float) $metrics['disbursed']) * 100, 1)
            : 0;

        $recentProcurements = Procurement::withCount('submissions')
            ->where('think_tank_member_id', $member->id)
            ->when($periodStart, fn ($query) => $query->whereDate('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($query) => $query->whereDate('created_at', '<=', $periodEnd))
            ->latest()
            ->limit(6)
            ->get();

        $recentReports = (clone $reportQuery)->latest()->limit(5)->get();
        $recentResearch = (clone $researchQuery)->latest()->limit(5)->get();

        $fundedActivities = (clone $allocationQuery)
            ->latest()
            ->limit(6)
            ->get()
            ->map(function (ConsortiumFundAllocation $allocation) {
                $disbursed = (float) $allocation->amount_disbursed;
                $spent = (float) $allocation->amount_spent;

                return [
                    'budget_line' => $allocation->budget_line,
                    'allocated' => (float) $allocation->amount_allocated,
                    'disbursed' => $disbursed,
                    'spent' => $spent,
                    'status' => $allocation->status,
                    'utilization' => $disbursed > 0 ? min(100, round(($spent / $disbursed) * 100, 1)) : 0,
                ];
            });

        $reportSubmittedThisPeriod = ConsortiumActivityReport::where('think_tank_member_id', $member->id)
            ->where(function ($query) use ($reportingPeriodStart, $reportingPeriodEnd) {
                $query->whereBetween('reporting_period_start', [$reportingPeriodStart, $reportingPeriodEnd])
                    ->orWhereBetween('reporting_period_end', [$reportingPeriodStart, $reportingPeriodEnd])
                    ->orWhereBetween('submitted_at', [$reportingPeriodStart, $reportingPeriodEnd]);
            })
            ->exists();

        $closingProcurements = Procurement::withCount('submissions')
            ->where('think_tank_member_id', $member->id)
            ->where('status', 'published')
            ->whereDate('application_end_date', '>=', $today)
            ->when($periodStart, fn ($query) => $query->whereDate('created_at', '>=', $periodStart))
            ->when($periodEnd, fn ($query) => $query->whereDate('created_at', '<=', $periodEnd))
            ->orderBy('application_end_date')
            ->limit(4)
            ->get();

        $pendingResearchCount = (clone $researchQuery)
            ->whereIn('status', ['submitted', 'pending', 'under_review'])
            ->count();

        $pendingReportsCount = (clone $reportQuery)
            ->whereIn('status', ['submitted', 'pending', 'under_review'])
            ->count();

        $upcomingActivities = collect([
            [
                'type' => $reportSubmittedThisPeriod ? 'complete' : ($monthlyReportDaysLeft <= 3 ? 'urgent' : 'due'),
                'title' => $reportSubmittedThisPeriod ? 'Monthly activity report submitted' : 'Submit this month\'s activity report',
                'meta' => $reportSubmittedThisPeriod
                    ? 'The Secretariat has a report for ' . $today->format('F Y') . '.'
                    : 'Due ' . $monthlyReportDue->format('M d, Y') . ' to the ATTP Secretariat.',
                'value' => $reportSubmittedThisPeriod ? 'Done' : ($monthlyReportDaysLeft >= 0 ? $monthlyReportDaysLeft . ' days left' : abs($monthlyReportDaysLeft) . ' days late'),
                'route' => route('think-tank.reports', $this->portalRouteParams($request, $member)),
            ],
            [
                'type' => $pendingReportsCount > 0 ? 'review' : 'complete',
                'title' => 'Secretariat report review',
                'meta' => $pendingReportsCount > 0 ? 'Submitted reports awaiting Secretariat action.' : 'No report is waiting for review.',
                'value' => number_format($pendingReportsCount),
                'route' => route('think-tank.reports', $this->portalRouteParams($request, $member)),
            ],
            [
                'type' => $pendingResearchCount > 0 ? 'review' : 'info',
                'title' => 'Research awaiting clearance',
                'meta' => 'Outputs submitted for Secretariat visibility and approval.',
                'value' => number_format($pendingResearchCount),
                'route' => route('think-tank.research', $this->portalRouteParams($request, $member)),
            ],
            [
                'type' => $metrics['open_risks'] > 0 ? 'urgent' : 'complete',
                'title' => 'Open oversight risks',
                'meta' => $metrics['open_risks'] > 0 ? 'Resolve or update mitigation notes.' : 'No open risk flags.',
                'value' => number_format($metrics['open_risks']),
                'route' => route('think-tank.dashboard', $this->portalRouteParams($request, $member)),
            ],
        ]);

        foreach ($closingProcurements as $procurement) {
            $daysLeft = $procurement->application_end_date
                ? $today->diffInDays($procurement->application_end_date->startOfDay(), false)
                : null;

            $upcomingActivities->push([
                'type' => $daysLeft !== null && $daysLeft <= 3 ? 'urgent' : 'procurement',
                'title' => $procurement->title,
                'meta' => 'Procurement closes ' . ($procurement->application_end_date?->format('M d, Y') ?? 'soon') . ' with ' . number_format($procurement->submissions_count) . ' applications.',
                'value' => $daysLeft === null ? 'Open' : ($daysLeft >= 0 ? $daysLeft . ' days left' : 'Closed'),
                'route' => route('think-tank.procurement.submissions', array_merge($this->portalRouteParams($request, $member), ['procurement' => $procurement])),
            ]);
        }

        $lastSixMonths = collect(range(5, 0))->map(fn ($monthsAgo) => $today->subMonths($monthsAgo));
        $allReports = (clone $reportQuery)->get();
        $allProcurements = (clone $filteredProcurementsBase)->get();
        $allResearch = (clone $researchQuery)->get();

        $chartData = [
            'finance' => [
                'labels' => ['Allocated', 'Disbursed', 'Spent', 'Requested'],
                'values' => [
                    round((float) $metrics['allocated'], 2),
                    round((float) $metrics['disbursed'], 2),
                    round((float) $metrics['spent'], 2),
                    round((float) $metrics['requested'], 2),
                ],
            ],
            'reports' => [
                'labels' => $lastSixMonths->map(fn ($date) => $date->format('M'))->values(),
                'values' => $lastSixMonths->map(function ($date) use ($allReports) {
                    return $allReports->filter(function ($report) use ($date) {
                        $reportDate = $report->submitted_at ?? $report->created_at;

                        return $reportDate && $reportDate->format('Y-m') === $date->format('Y-m');
                    })->count();
                })->values(),
            ],
            'procurements' => [
                'labels' => ['Draft', 'Published', 'Awarded', 'Closed'],
                'values' => collect(['draft', 'published', 'awarded', 'closed'])
                    ->map(fn ($status) => $allProcurements->where('status', $status)->count())
                    ->values(),
            ],
            'research' => [
                'labels' => $allResearch->groupBy(fn ($output) => str_replace('_', ' ', ucfirst($output->output_type ?? 'Research')))->keys()->values(),
                'values' => $allResearch->groupBy(fn ($output) => str_replace('_', ' ', ucfirst($output->output_type ?? 'Research')))->map->count()->values(),
            ],
        ];

        return view('think-tank.dashboard', compact(
            'member',
            'metrics',
            'recentProcurements',
            'recentReports',
            'recentResearch',
            'fundedActivities',
            'upcomingActivities',
            'monthlyReportDue',
            'monthlyReportDaysLeft',
            'reportSubmittedThisPeriod',
            'chartData',
            'dashboardFilter'
        ));
    }

    public function reports(Request $request)
    {
        $member = $this->member($request);
        $today = CarbonImmutable::now()->startOfDay();
        $monthlyReportDue = $today->endOfMonth()->addDays(7)->startOfDay();
        $monthlyReportDaysLeft = $today->diffInDays($monthlyReportDue, false);

        $reports = ConsortiumActivityReport::where('think_tank_member_id', $member->id)
            ->with(['workplan', 'evidence'])
            ->latest()
            ->paginate(15);

        $workplans = $member->consortium->workplans()->orderBy('title')->get();
        $reportStats = [
            'total' => ConsortiumActivityReport::where('think_tank_member_id', $member->id)->count(),
            'submitted' => ConsortiumActivityReport::where('think_tank_member_id', $member->id)->where('status', 'submitted')->count(),
            'approved' => ConsortiumActivityReport::where('think_tank_member_id', $member->id)->where('status', 'approved')->count(),
            'revisions' => ConsortiumActivityReport::where('think_tank_member_id', $member->id)->where('status', 'revisions_requested')->count(),
            'average_progress' => round((float) ConsortiumActivityReport::where('think_tank_member_id', $member->id)->avg('progress_percent'), 1),
            'funds_spent' => ConsortiumActivityReport::where('think_tank_member_id', $member->id)->sum('funds_spent'),
        ];

        return view('think-tank.reports', compact(
            'member',
            'reports',
            'workplans',
            'reportStats',
            'monthlyReportDue',
            'monthlyReportDaysLeft'
        ));
    }

    public function storeReport(Request $request)
    {
        $member = $this->member($request);

        app(ConsortiumOperationsController::class)->storeReport($request->merge([
            'think_tank_member_id' => $member->id,
        ]), $member->consortium);

        return back()->with('success', 'Report submitted to the ATTP Secretariat.');
    }

    public function research(Request $request)
    {
        $member = $this->member($request);
        $outputs = ThinkTankResearchOutput::where('think_tank_member_id', $member->id)->latest()->paginate(15);
        $researchStats = [
            'total' => ThinkTankResearchOutput::where('think_tank_member_id', $member->id)->count(),
            'submitted' => ThinkTankResearchOutput::where('think_tank_member_id', $member->id)->where('status', 'submitted')->count(),
            'approved' => ThinkTankResearchOutput::where('think_tank_member_id', $member->id)->where('status', 'approved')->count(),
            'with_files' => ThinkTankResearchOutput::where('think_tank_member_id', $member->id)->whereNotNull('file_path')->count(),
        ];
        $outputTypes = ThinkTankResearchOutput::where('think_tank_member_id', $member->id)
            ->select('output_type', DB::raw('count(*) as total'))
            ->groupBy('output_type')
            ->orderByDesc('total')
            ->get();

        return view('think-tank.research', compact('member', 'outputs', 'researchStats', 'outputTypes'));
    }

    public function purchaseOrders(Request $request)
    {
        $member = $this->member($request);
        $member->loadMissing(['consortium', 'vendorUser', 'fundAllocations']);

        $purchaseOrders = ProcurementPurchaseOrder::with(['disbursements'])
            ->where('think_tank_member_id', $member->id)
            ->where('po_type', 'think_tank_transfer')
            ->orderByDesc('created_at')
            ->paginate(15);

        $allocations = ConsortiumFundAllocation::where('think_tank_member_id', $member->id)
            ->orderBy('budget_line')
            ->get();

        $allTransferOrders = ProcurementPurchaseOrder::where('think_tank_member_id', $member->id)
            ->where('po_type', 'think_tank_transfer')
            ->get();

        $stats = [
            'total' => $allTransferOrders->count(),
            'amount' => $allTransferOrders->sum('amount'),
            'paid' => $allTransferOrders->sum(fn (ProcurementPurchaseOrder $order) => $order->paidAmount()),
            'remaining' => $allTransferOrders->sum(fn (ProcurementPurchaseOrder $order) => $order->remainingAmount()),
        ];

        return view('think-tank.purchase-orders', compact('member', 'purchaseOrders', 'allocations', 'stats'));
    }

    public function storePurchaseOrder(Request $request)
    {
        $member = $this->member($request);
        $member->loadMissing(['consortium', 'vendorUser']);

        if (! $member->vendor_user_id) {
            return back()->with('error', 'This think tank is not linked to a vendor account yet.');
        }

        $data = $request->validate([
            'fund_allocation_id' => 'nullable|exists:attp_fund_allocations,id',
            'au_sap_vendor_number' => 'nullable|string|max:80',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'issued_at' => 'nullable|date',
            'notes' => 'nullable|string|max:2000',
        ]);

        if (! $member->au_sap_vendor_number && empty($data['au_sap_vendor_number'])) {
            return back()
                ->withErrors(['au_sap_vendor_number' => 'Please enter your AU SAP vendor number before creating a purchase order.'])
                ->withInput();
        }

        if (! empty($data['au_sap_vendor_number']) && $data['au_sap_vendor_number'] !== $member->au_sap_vendor_number) {
            $member->update(['au_sap_vendor_number' => $data['au_sap_vendor_number']]);
            $member->refresh();
        }

        $allocation = null;
        if (! empty($data['fund_allocation_id'])) {
            $allocation = ConsortiumFundAllocation::where('think_tank_member_id', $member->id)
                ->whereKey($data['fund_allocation_id'])
                ->firstOrFail();
        }

        $purchaseOrder = ProcurementPurchaseOrder::create([
            'po_type' => 'think_tank_transfer',
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'vendor_id' => $member->vendor_user_id,
            'reference_no' => ProcurementPurchaseOrder::generateThinkTankTransferReference($member),
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?: ($allocation?->currency ?? $member->consortium?->currency ?? 'USD'),
            'status' => 'issued',
            'created_by' => $request->user()?->id,
            'issued_at' => $data['issued_at'] ?? now(),
        ]);

        if (! empty($data['notes'])) {
            logger()->info('Think tank transfer PO notes', [
                'purchase_order_id' => $purchaseOrder->id,
                'think_tank_member_id' => $member->id,
                'notes' => $data['notes'],
            ]);
        }

        return redirect()
            ->route('think-tank.purchase-orders.show', array_merge($this->portalRouteParams($request, $member), ['purchaseOrder' => $purchaseOrder]))
            ->with('success', 'Purchase order created: ' . $purchaseOrder->reference_no);
    }

    public function showPurchaseOrder(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        $purchaseOrder->load(['vendor', 'consortium', 'thinkTankMember', 'disbursements']);

        return view('think-tank.purchase-orders-show', compact('member', 'purchaseOrder'));
    }

    public function purchaseOrderPdf(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        $purchaseOrder->load(['vendor', 'consortium', 'thinkTankMember', 'disbursements']);

        return Pdf::loadView('think-tank.purchase-orders-pdf', compact('member', 'purchaseOrder'))
            ->stream('purchase-order-' . ($purchaseOrder->reference_no ?? 'po') . '.pdf');
    }

    public function downloadPurchaseOrder(Request $request, ProcurementPurchaseOrder $purchaseOrder)
    {
        $member = $this->member($request);
        $this->assertMemberPurchaseOrder($member, $purchaseOrder);

        $purchaseOrder->load(['vendor', 'consortium', 'thinkTankMember', 'disbursements']);

        return Pdf::loadView('think-tank.purchase-orders-pdf', compact('member', 'purchaseOrder'))
            ->download('purchase-order-' . ($purchaseOrder->reference_no ?? 'po') . '.pdf');
    }

    public function storeResearch(Request $request)
    {
        $member = $this->member($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'output_type' => 'required|in:research,policy_brief,report,working_paper,dataset,publication',
            'published_on' => 'nullable|date',
            'abstract' => 'nullable|string',
            'external_url' => 'nullable|url|max:2000',
            'file' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,zip|max:20480',
        ]);

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store("think-tank-research/{$member->id}");
        }

        unset($data['file']);

        ThinkTankResearchOutput::create([
            ...$data,
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'status' => 'submitted',
            'submitted_by' => $request->user()?->id,
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Research output submitted to the Secretariat.');
    }

    public function procurement(Request $request)
    {
        $member = $this->member($request);

        $plans = ThinkTankProcurementPlan::where('think_tank_member_id', $member->id)
            ->withCount('procurements')
            ->latest()
            ->get();

        $procurements = Procurement::withCount('submissions')
            ->with('thinkTankProcurementPlan')
            ->where('think_tank_member_id', $member->id)
            ->latest()
            ->paginate(15);

        $allProcurements = Procurement::where('think_tank_member_id', $member->id)->get();
        $procurementStats = [
            'plans' => $plans->count(),
            'plan_budget' => $plans->sum(fn ($plan) => (float) $plan->estimated_budget),
            'opportunities' => $allProcurements->count(),
            'published' => $allProcurements->where('status', 'published')->count(),
            'draft' => $allProcurements->where('status', 'draft')->count(),
            'awarded' => $allProcurements->where('status', 'awarded')->count(),
            'applications' => FormSubmission::whereIn('procurement_id', $allProcurements->pluck('id'))->count(),
        ];

        return view('think-tank.procurement', compact('member', 'plans', 'procurements', 'procurementStats'));
    }

    public function storeProcurementPlan(Request $request)
    {
        $member = $this->member($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'fiscal_year' => 'nullable|string|max:20',
            'estimated_budget' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'planned_publish_date' => 'nullable|date',
            'description' => 'nullable|string',
        ]);

        ThinkTankProcurementPlan::create([
            ...$data,
            'consortium_id' => $member->consortium_id,
            'think_tank_member_id' => $member->id,
            'plan_code' => $this->nextCode('TT-PLAN'),
            'currency' => $data['currency'] ?? $member->consortium->currency,
            'status' => 'submitted',
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('success', 'Procurement plan submitted.');
    }

    public function storeProcurement(Request $request)
    {
        $member = $this->member($request);

        $data = $request->validate([
            'think_tank_procurement_plan_id' => 'nullable|exists:attp_think_tank_procurement_plans,id',
            'title' => 'required|string|max:255',
            'reference_no' => 'nullable|string|max:100',
            'description' => 'required|string',
            'fiscal_year' => 'nullable|string|max:20',
            'estimated_budget' => 'required|numeric|min:0',
            'application_start_date' => 'nullable|date',
            'application_end_date' => 'required|date|after_or_equal:application_start_date',
            'status' => 'required|in:draft,published',
        ]);

        DB::transaction(function () use ($data, $request, $member) {
            $procurement = Procurement::create([
                ...$data,
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'procurement_owner_type' => 'think_tank',
                'oversight_status' => 'visible',
                'visibility_type' => 'public',
                'created_by' => $request->user()?->id,
            ]);

            $form = DynamicForm::create([
                'name' => $procurement->title . ' Application Form',
                'applies_to' => 'procurement',
                'status' => 'approved',
                'is_active' => true,
                'procurement_id' => $procurement->id,
                'created_by' => $request->user()?->id,
                'approved_by' => $request->user()?->id,
                'approved_at' => now(),
            ]);

            $form->ensureGlobalFields();

            foreach ($this->defaultProcurementFields() as $field) {
                DynamicFormField::updateOrCreate(
                    ['form_id' => $form->id, 'field_key' => $field['field_key']],
                    [...$field, 'created_by' => $request->user()?->id]
                );
            }
        });

        return back()->with('success', 'Procurement opportunity created. Published items appear on the public procurement page.');
    }

    public function submissions(Request $request, Procurement $procurement)
    {
        $member = $this->member($request);
        abort_unless($procurement->think_tank_member_id === $member->id, 403);

        $procurement->load(['submissions.submitter', 'submissions.values', 'submissions.thinkTankReview', 'awardedSubmission']);

        return view('think-tank.submissions', compact('member', 'procurement'));
    }

    public function reviewSubmission(Request $request, Procurement $procurement, FormSubmission $submission)
    {
        $member = $this->member($request);
        abort_unless($procurement->think_tank_member_id === $member->id && $submission->procurement_id === $procurement->id, 403);

        $data = $request->validate([
            'technical_score' => 'required|numeric|min:0|max:100',
            'financial_score' => 'required|numeric|min:0|max:100',
            'recommendation' => 'required|in:pending,shortlisted,recommended,rejected',
            'comments' => 'nullable|string',
        ]);

        $total = round(((float) $data['technical_score'] * 0.7) + ((float) $data['financial_score'] * 0.3), 2);

        ThinkTankProcurementReview::updateOrCreate(
            ['procurement_id' => $procurement->id, 'form_submission_id' => $submission->id],
            [
                ...$data,
                'think_tank_member_id' => $member->id,
                'reviewed_by' => $request->user()?->id,
                'total_score' => $total,
                'reviewed_at' => now(),
            ]
        );

        $submission->update(['status' => $data['recommendation']]);

        return back()->with('success', 'Evaluation saved.');
    }

    public function selectSubmission(Request $request, Procurement $procurement, FormSubmission $submission)
    {
        $member = $this->member($request);
        abort_unless($procurement->think_tank_member_id === $member->id && $submission->procurement_id === $procurement->id, 403);

        $procurement->update([
            'awarded_submission_id' => $submission->id,
            'awarded_vendor_id' => $submission->submitted_by,
            'awarded_at' => now(),
            'status' => 'awarded',
        ]);

        $submission->update(['status' => 'selected']);

        return back()->with('success', 'Selected vendor saved. ATTP Secretariat and funders can see this decision immediately.');
    }

    private function dashboardFilter(Request $request): array
    {
        $today = CarbonImmutable::now()->startOfDay();
        $start = null;
        $end = null;
        $mode = 'all';
        $label = 'All time';

        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');
        $month = trim((string) $request->input('filter_month', ''));
        $year = trim((string) $request->input('filter_year', ''));

        if ($dateFrom || $dateTo) {
            $mode = 'custom';
            $start = $dateFrom ? CarbonImmutable::parse($dateFrom)->startOfDay() : null;
            $end = $dateTo ? CarbonImmutable::parse($dateTo)->endOfDay() : null;
            $label = ($start?->format('M d, Y') ?? 'Start') . ' to ' . ($end?->format('M d, Y') ?? 'Today');
        } elseif (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $mode = 'month';
            $start = CarbonImmutable::createFromFormat('Y-m', $month)->startOfMonth();
            $end = $start->endOfMonth();
            $label = $start->format('F Y');
        } elseif (preg_match('/^\d{4}$/', $year)) {
            $mode = 'year';
            $start = CarbonImmutable::create((int) $year, 1, 1)->startOfYear();
            $end = $start->endOfYear();
            $label = $start->format('Y');
        }

        if ($start && $end && $start->gt($end)) {
            [$start, $end] = [$end->startOfDay(), $start->endOfDay()];
        }

        return [
            'mode' => $mode,
            'label' => $label,
            'start' => $start,
            'end' => $end,
            'is_all_time' => $mode === 'all',
            'month' => $month,
            'year' => $year,
            'date_from' => $dateFrom ? CarbonImmutable::parse($dateFrom)->toDateString() : null,
            'date_to' => $dateTo ? CarbonImmutable::parse($dateTo)->toDateString() : null,
            'year_options' => range((int) $today->format('Y') + 1, (int) $today->format('Y') - 6),
        ];
    }

    private function member(Request $request): ConsortiumThinkTank
    {
        $user = $request->user();

        if ($user && ($user->isSuperAdmin() || $user->isAdmin())) {
            $routeProcurement = $request->route('procurement');
            $memberId = $request->input('think_tank_member_id')
                ?: $request->input('member_id')
                ?: $routeProcurement?->think_tank_member_id;

            return ConsortiumThinkTank::with('consortium')
                ->when($memberId, fn ($query) => $query->whereKey($memberId))
                ->orderBy('name')
                ->firstOrFail();
        }

        return $user->thinkTankMembership()->with('consortium')->firstOrFail();
    }

    private function defaultProcurementFields(): array
    {
        return [
            ['label' => 'Organization Profile', 'field_key' => 'organization_profile', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 10],
            ['label' => 'Technical Proposal', 'field_key' => 'technical_proposal', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 20],
            ['label' => 'Financial Proposal', 'field_key' => 'financial_proposal', 'field_type' => 'file', 'is_required' => true, 'sort_order' => 30],
            ['label' => 'Quoted Amount', 'field_key' => 'quoted_amount', 'field_type' => 'number', 'is_required' => true, 'sort_order' => 40],
            ['label' => 'Relevant Experience', 'field_key' => 'relevant_experience', 'field_type' => 'textarea', 'is_required' => true, 'sort_order' => 50],
        ];
    }

    private function nextCode(string $prefix): string
    {
        return $prefix . '-' . now()->format('Ymd') . '-' . Str::upper(Str::random(5));
    }

    private function portalRouteParams(Request $request, ConsortiumThinkTank $member): array
    {
        $user = $request->user();

        return $user && ($user->isSuperAdmin() || $user->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    }

    private function assertMemberPurchaseOrder(ConsortiumThinkTank $member, ProcurementPurchaseOrder $purchaseOrder): void
    {
        abort_unless(
            $purchaseOrder->po_type === 'think_tank_transfer'
            && (string) $purchaseOrder->think_tank_member_id === (string) $member->id,
            403
        );
    }
}
