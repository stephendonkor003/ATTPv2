<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Models\ProcurementContractNegotiation;
use App\Models\ProcurementDeliverable;
use App\Models\ProcurementInvoice;
use App\Models\ProcurementPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VendorInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $awardedProcurements = Procurement::where('status', 'awarded')
            ->where('awarded_vendor_id', $user->id)
            ->orderByDesc('awarded_at')
            ->get();

        $budgetByProcurement = [];
        $currencyByProcurement = [];
        $remainingByProcurement = [];

        foreach ($awardedProcurements as $procurement) {
            [$budget, $currency] = $this->resolveBudget($procurement);

            $totalInvoiced = ProcurementInvoice::where('procurement_id', $procurement->id)
                ->where('status', '!=', 'rejected')
                ->sum('amount');

            $budgetByProcurement[$procurement->id] = $budget;
            $currencyByProcurement[$procurement->id] = $currency;
            $remainingByProcurement[$procurement->id] = $budget !== null
                ? max($budget - $totalInvoiced, 0)
                : null;
        }

        $eligibleDeliverables = ProcurementDeliverable::with('procurement')
            ->where('vendor_id', $user->id)
            ->whereIn('procurement_id', $awardedProcurements->pluck('id'))
            ->where('status', 'completed')
            ->where('vendor_approval_status', 'approved')
            ->where('admin_approval_status', 'approved')
            ->whereDoesntHave('invoices', function ($query) {
                $query->where('status', '!=', 'rejected');
            })
            ->orderByRaw('COALESCE(timeline_end, timeline_start, created_at)')
            ->get();

        $invoices = ProcurementInvoice::with(['procurement', 'purchaseOrder'])
            ->where('vendor_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        return view('vendor.invoices.index', [
            'awardedProcurements' => $awardedProcurements,
            'invoices' => $invoices,
            'budgetByProcurement' => $budgetByProcurement,
            'currencyByProcurement' => $currencyByProcurement,
            'remainingByProcurement' => $remainingByProcurement,
            'eligibleDeliverables' => $eligibleDeliverables,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $this->assertVendor($user);

        $data = $request->validate([
            'procurement_id' => 'required|exists:procurements,id',
            'invoice_month' => 'required|date_format:Y-m',
            'amount' => 'required_without:deliverable_ids|numeric|min:0.01',
            'notes' => 'nullable|string|max:1000',
            'deliverable_ids' => 'nullable|array',
            'deliverable_ids.*' => 'exists:procurement_deliverables,id',
        ]);

        $procurement = Procurement::where('status', 'awarded')
            ->where('id', $data['procurement_id'])
            ->firstOrFail();

        if ($procurement->awarded_vendor_id !== $user->id) {
            abort(403, 'You are not authorized to submit invoices for this procurement.');
        }

        $invoiceMonth = Carbon::createFromFormat('Y-m', $data['invoice_month'])->startOfMonth();

        $existingMonthly = ProcurementInvoice::where('procurement_id', $procurement->id)
            ->where('vendor_id', $user->id)
            ->whereDate('invoice_month', $invoiceMonth)
            ->where('status', '!=', 'rejected')
            ->exists();

        if ($existingMonthly && empty($data['deliverable_ids'])) {
            return back()->withErrors([
                'invoice_month' => 'An invoice already exists for this month.',
            ])->withInput();
        }

        [$budget, $currency, $subActivityId] = $this->resolveBudget($procurement, true);
        if ($budget === null) {
            return back()->withErrors([
                'procurement_id' => 'Unable to determine the sub-activity budget for this procurement.',
            ])->withInput();
        }

        $selectedDeliverables = collect();
        if (!empty($data['deliverable_ids'])) {
            $selectedDeliverables = ProcurementDeliverable::whereIn('id', $data['deliverable_ids'])
                ->where('vendor_id', $user->id)
                ->where('procurement_id', $procurement->id)
                ->where('status', 'completed')
                ->where('vendor_approval_status', 'approved')
                ->where('admin_approval_status', 'approved')
                ->whereDoesntHave('invoices', function ($query) {
                    $query->where('status', '!=', 'rejected');
                })
                ->get();

            if ($selectedDeliverables->count() !== count($data['deliverable_ids'])) {
                return back()->withErrors([
                    'deliverable_ids' => 'Some selected deliverables are no longer eligible for invoicing.',
                ])->withInput();
            }

            $deliverableTotal = $selectedDeliverables->sum('amount');
            if ($deliverableTotal <= 0) {
                return back()->withErrors([
                    'deliverable_ids' => 'Selected deliverables must have a total amount greater than zero.',
                ])->withInput();
            }

            $data['amount'] = $deliverableTotal;
        }

        $totalInvoiced = ProcurementInvoice::where('procurement_id', $procurement->id)
            ->where('status', '!=', 'rejected')
            ->sum('amount');

        $remaining = $budget - $totalInvoiced;

        if ($remaining <= 0) {
            return back()->withErrors([
                'amount' => 'The available sub-activity budget has already been fully invoiced.',
            ])->withInput();
        }

        if ($data['amount'] > $remaining) {
            return back()->withErrors([
                'amount' => 'Invoice amount exceeds the remaining budget of ' . number_format($remaining, 2) . '.',
            ])->withInput();
        }

        $invoice = ProcurementInvoice::create([
            'procurement_id' => $procurement->id,
            'vendor_id' => $user->id,
            'sub_activity_id' => $subActivityId,
            'governance_node_id' => $procurement->governance_node_id,
            'invoice_month' => $invoiceMonth,
            'reference_no' => ProcurementInvoice::generateReference(),
            'amount' => $data['amount'],
            'currency' => $currency,
            'status' => 'submitted',
            'created_by' => $user->id,
            'notes' => $data['notes'] ?? null,
        ]);

        if ($selectedDeliverables->isNotEmpty()) {
            $invoice->deliverables()->attach($selectedDeliverables->pluck('id')->all());
        }

        ProcurementAuditLog::create([
            'user_id' => $user->id,
            'action' => 'Submitted procurement invoice',
            'procurement_id' => $procurement->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'amount' => $invoice->amount,
                'invoice_month' => $invoiceMonth->toDateString(),
            ],
            'created_at' => now(),
        ]);

        return back()->with('success', 'Invoice submitted successfully.');
    }

    public function show(Request $request, ProcurementInvoice $invoice)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($invoice->vendor_id !== $user->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $invoice->load(['procurement', 'purchaseOrder', 'subActivity', 'deliverables']);

        return view('vendor.invoices.show', [
            'invoice' => $invoice,
        ]);
    }

    public function pdf(Request $request, ProcurementInvoice $invoice)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($invoice->vendor_id !== $user->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $invoice->load(['procurement', 'purchaseOrder', 'subActivity', 'vendor']);

        $pdf = Pdf::loadView('vendor.invoices.pdf', [
            'invoice' => $invoice,
        ]);

        return $pdf->stream('invoice-' . ($invoice->reference_no ?? 'draft') . '.pdf');
    }

    public function download(Request $request, ProcurementInvoice $invoice)
    {
        $user = $request->user();
        $this->assertVendor($user);

        if ($invoice->vendor_id !== $user->id) {
            abort(403, 'You do not have access to this invoice.');
        }

        $invoice->load(['procurement', 'purchaseOrder', 'subActivity', 'vendor']);

        $pdf = Pdf::loadView('vendor.invoices.pdf', [
            'invoice' => $invoice,
        ]);

        return $pdf->download('invoice-' . ($invoice->reference_no ?? 'draft') . '.pdf');
    }

    private function resolveBudget(Procurement $procurement, bool $includeSubActivity = false): array
    {
        $plan = null;
        if (!empty($procurement->reference_no)) {
            $plan = ProcurementPlan::where('procurement_code', $procurement->reference_no)->first();
        }

        $negotiation = ProcurementContractNegotiation::where('procurement_id', $procurement->id)
            ->where('status', 'agreed')
            ->orderByDesc('agreed_at')
            ->first();

        $budget = $plan?->estimated_budget ?? $negotiation?->agreed_amount ?? $procurement->estimated_budget;
        $currency = $plan?->currency ?? null;
        $subActivityId = $plan?->sub_activity_id;

        if ($includeSubActivity) {
            return [$budget, $currency, $subActivityId];
        }

        return [$budget, $currency];
    }

    private function assertVendor($user): void
    {
        if (!$user || $user->user_type !== 'vendor') {
            abort(403, 'Access denied. Vendor portal only.');
        }

        if ($user->is_blacklisted) {
            abort(403, 'Your vendor account has been blacklisted. Please contact the administrator.');
        }

        if ($user->is_disabled) {
            abort(403, 'Your vendor account has been disabled. Please contact the administrator.');
        }
    }
}
