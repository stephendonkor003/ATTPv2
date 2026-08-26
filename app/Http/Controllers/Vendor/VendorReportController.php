<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\User;
use App\Models\VendorDocument;
use App\Models\VendorReport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class VendorReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $this->vendor($request);

        $reports = VendorReport::with(['procurement', 'purchaseOrder', 'documents'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        $stats = [
            'total' => $reports->count(),
            'submitted' => $reports->where('status', 'submitted')->count(),
            'reviewed' => $reports->whereIn('status', ['accepted', 'reviewed'])->count(),
            'action_required' => $reports->where('status', 'rejected')->count(),
        ];

        return view('vendor.reports.index', compact('reports', 'stats'));
    }

    public function create(Request $request)
    {
        $user = $this->vendor($request);

        return view('vendor.reports.create', [
            'procurements' => $this->vendorProcurements($user),
            'purchaseOrders' => $this->vendorPurchaseOrders($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $this->vendor($request);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'report_type' => 'required|in:progress,completion,financial,deliverable,incident,other',
            'procurement_id' => [
                'nullable',
                Rule::exists('procurements', 'id')->whereNull('deleted_at'),
            ],
            'purchase_order_id' => ['nullable', Rule::exists('procurement_purchase_orders', 'id')],
            'reporting_period_start' => 'nullable|date',
            'reporting_period_end' => 'nullable|date|after_or_equal:reporting_period_start',
            'summary' => 'required|string|max:8000',
            'challenges' => 'nullable|string|max:8000',
            'next_steps' => 'nullable|string|max:8000',
            'documents.*' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,zip|max:20480',
        ]);

        $allowedProcurements = $this->vendorProcurements($user)->pluck('id')->all();
        if (!empty($data['procurement_id']) && !in_array($data['procurement_id'], $allowedProcurements, true)) {
            return back()->withErrors(['procurement_id' => 'You do not have access to this procurement.'])->withInput();
        }

        $allowedPurchaseOrders = $this->vendorPurchaseOrders($user)->pluck('id')->all();
        if (!empty($data['purchase_order_id']) && !in_array($data['purchase_order_id'], $allowedPurchaseOrders, true)) {
            return back()->withErrors(['purchase_order_id' => 'You do not have access to this purchase order.'])->withInput();
        }

        $report = DB::transaction(function () use ($request, $user, $data) {
            $report = VendorReport::create([
                'user_id' => $user->id,
                'procurement_id' => $data['procurement_id'] ?? null,
                'purchase_order_id' => $data['purchase_order_id'] ?? null,
                'reference_no' => VendorReport::generateReference(),
                'title' => $data['title'],
                'report_type' => $data['report_type'],
                'reporting_period_start' => $data['reporting_period_start'] ?? null,
                'reporting_period_end' => $data['reporting_period_end'] ?? null,
                'status' => 'submitted',
                'summary' => $data['summary'],
                'challenges' => $data['challenges'] ?? null,
                'next_steps' => $data['next_steps'] ?? null,
            ]);

            $this->storeDocuments($request, $user, $report);

            return $report;
        });

        return redirect()
            ->route('vendor.reports.show', $report)
            ->with('success', 'Report submitted for admin review.');
    }

    public function show(Request $request, VendorReport $report)
    {
        $user = $this->vendor($request);
        abort_unless((string) $report->user_id === (string) $user->id, 403);

        $report->load(['procurement', 'purchaseOrder', 'documents']);

        return view('vendor.reports.show', compact('report'));
    }

    public function download(Request $request, VendorReport $report, VendorDocument $document)
    {
        $user = $this->vendor($request);
        abort_unless((string) $report->user_id === (string) $user->id, 403);
        abort_unless((string) $document->user_id === (string) $user->id, 403);
        abort_unless($document->source_type === 'vendor_report' && (string) $document->source_id === (string) $report->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    private function vendor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->user_type === 'vendor', 403);
        abort_if($user->is_disabled || $user->is_blacklisted, 403);

        return $user;
    }

    private function vendorProcurements(User $user)
    {
        $submissionProcurementIds = FormSubmission::where('submitted_by', $user->id)
            ->pluck('procurement_id');
        $poProcurementIds = ProcurementPurchaseOrder::where('vendor_id', $user->id)
            ->pluck('procurement_id');

        return Procurement::whereIn('id', $submissionProcurementIds->merge($poProcurementIds)->filter()->unique())
            ->orderByDesc('created_at')
            ->get();
    }

    private function vendorPurchaseOrders(User $user)
    {
        return ProcurementPurchaseOrder::where('vendor_id', $user->id)
            ->orderByDesc('created_at')
            ->get();
    }

    private function storeDocuments(Request $request, User $user, VendorReport $report): void
    {
        foreach ($request->file('documents', []) as $file) {
            if (!$file) {
                continue;
            }

            $path = $file->store("vendor-documents/{$user->id}/reports/{$report->id}", 'local');

            VendorDocument::create([
                'user_id' => $user->id,
                'uploaded_by' => $user->id,
                'source_type' => 'vendor_report',
                'source_id' => $report->id,
                'title' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'document_type' => $report->report_type,
                'description' => $report->reference_no,
                'file_path' => $path,
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size_bytes' => $file->getSize(),
                'tags' => ['report', $report->report_type],
            ]);
        }
    }
}
