<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Procurement\ProcurementDisbursementController;
use App\Models\AssistantSubmission;
use App\Models\BudgetCommitment;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Services\AssistantSubmissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssistantSubmissionController extends Controller
{
    public function __construct(private AssistantSubmissionService $service) {}

    public function createPurchaseRequest(Request $request)
    {
        $request->query->remove('intake');
        return app(BudgetCommitmentController::class)->createPurchaseRequest($request)->with('assistantMode', true);
    }

    public function storePurchaseRequest(Request $request)
    {
        $request->request->remove('purchase_request_intake_id');
        return app(BudgetCommitmentController::class)->store($request);
    }

    public function createDisbursement(Request $request)
    {
        return app(ProcurementDisbursementController::class)->create($request)->with('assistantMode', true);
    }

    public function storeDisbursement(Request $request)
    {
        return app(ProcurementDisbursementController::class)->store($request);
    }

    public function index(Request $request)
    {
        $isReviewer = $request->routeIs('assistant-approvals.*');
        if ($isReviewer) abort_unless($this->service->isReviewer($request->user()), 403);
        $query = AssistantSubmission::query()->with(['creator', 'reviewer']);
        if ($isReviewer) {
            $ids = AssistantSubmission::get(['id', 'created_by', 'governance_node_id'])
                ->filter(fn ($submission) => $this->service->canReview($request->user(), $submission))->pluck('id');
            $query->whereIn('id', $ids);
        } else {
            $query->where('created_by', $request->user()->id);
        }
        $counts = collect(['pending', 'approved', 'rejected'])->mapWithKeys(fn ($state) => [$state => (clone $query)->where('status', $state)->count()])->all();
        $status = in_array($request->query('status'), ['pending', 'approved', 'rejected'], true) ? $request->query('status') : 'all';
        if ($status !== 'all') $query->where('status', $status);
        if ($search = trim((string) $request->query('q'))) {
            $query->where(fn ($q) => $q->where('reference_no', 'like', '%'.$search.'%')->orWhere('summary', 'like', '%'.$search.'%')->orWhereHas('creator', fn ($creator) => $creator->where('name', 'like', '%'.$search.'%')));
        }
        $submissions = $query->latest()->paginate(15)->withQueryString();
        return view('administrative-assistant.submissions.index', compact('submissions', 'counts', 'isReviewer', 'status'));
    }

    private function authorizeView(Request $request, AssistantSubmission $submission): bool
    {
        $isReviewer = $request->routeIs('assistant-approvals.*');
        abort_unless($isReviewer ? $this->service->canReview($request->user(), $submission) : (string) $submission->created_by === (string) $request->user()->id, 403);
        return $isReviewer;
    }

    public function show(Request $request, AssistantSubmission $submission)
    {
        $isReviewer = $this->authorizeView($request, $submission);
        $submission->load(['creator', 'reviewer']);
        return view('administrative-assistant.submissions.show', compact('submission', 'isReviewer'));
    }

    public function pdf(Request $request, AssistantSubmission $submission)
    {
        $this->authorizeView($request, $submission);
        $submission->load(['creator', 'reviewer']);
        return Pdf::loadView('administrative-assistant.submissions.pdf', compact('submission'))->download($submission->reference_no.'.pdf');
    }

    public function document(Request $request, AssistantSubmission $submission, int $document)
    {
        $this->authorizeView($request, $submission);
        $file = $submission->documents[$document] ?? null;
        abort_unless($file, 404);
        $path = $this->service->documentPath($submission, $file);
        $mime = mime_content_type($path);
        $headers = ['Cache-Control' => 'no-store', 'X-Content-Type-Options' => 'nosniff'];
        if (! $request->boolean('download') && in_array($mime, ['application/pdf', 'image/jpeg', 'image/png', 'text/plain', 'text/csv'], true)) {
            return response()->file($path, $headers + ['Content-Type' => $mime]);
        }
        return response()->download($path, $file['name'], $headers);
    }

    public function reject(Request $request, AssistantSubmission $submission)
    {
        $this->authorizeView($request, $submission);
        $data = $request->validate(['review_note' => 'required|string|max:3000']);
        DB::transaction(function () use ($request, $submission, $data) {
            $locked = AssistantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This submission has already been reviewed.');
            $locked->update(['status' => 'rejected', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note']]);
            $this->service->audit($locked, 'Coordinator rejected assistant submission');
        });
        return back()->with('success', 'Submission rejected. The assistant can see your reason; no financial records were created.');
    }

    public function approve(Request $request, AssistantSubmission $submission)
    {
        $this->authorizeView($request, $submission);
        $data = $request->validate(['review_note' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($request, $submission, $data) {
            $locked = AssistantSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->status === 'pending', 409, 'This submission has already been reviewed.');
            abort_unless($this->service->canReview($request->user(), $locked), 403);
            $payload = $locked->payload;
            if ($locked->kind === 'purchase_request') {
                \App\Models\SubActivity::whereKey($payload['allocation_id'])->lockForUpdate()->firstOrFail();
                ProgramFunding::whereKey($payload['program_funding_id'])->lockForUpdate()->firstOrFail();
            } else {
                $purchaseOrder = ProcurementPurchaseOrder::whereKey($payload['purchase_order_id'])->lockForUpdate()->firstOrFail();
                if (in_array(strtolower((string) $purchaseOrder->status), ['cancelled', 'canceled', 'void'], true)) {
                    throw ValidationException::withMessages(['approval' => 'This purchase order is no longer open for payments.']);
                }
            }
            $replay = Request::create($request->url(), 'POST', $payload, [], $this->service->files($locked));
            $replay->setUserResolver(fn () => $request->user());
            $replay->setLaravelSession($request->session());
            $request->session()->forget('errors');
            $controller = $locked->kind === 'purchase_request' ? app(BudgetCommitmentController::class) : app(ProcurementDisbursementController::class);
            $controller->store($replay);
            $ids = $replay->attributes->get('assistant_published_ids');
            if (! $ids) {
                $errors = $request->session()->get('errors')?->getBag('default')->messages();
                throw ValidationException::withMessages($errors ?: ['approval' => 'The submission no longer passes financial validation. No records were posted.']);
            }
            if ($locked->kind === 'purchase_request') {
                $purchaseRequest = PurchaseRequest::findOrFail($ids[0]);
                $purchaseRequest->update(['created_by' => $locked->created_by]);
                $purchaseRequest->commitments()->update(['created_by' => $locked->created_by]);
                app(PurchaseRequestController::class)->approve($purchaseRequest);
                if ($purchaseRequest->fresh()->status !== 'approved') throw ValidationException::withMessages(['approval' => 'The purchase request could not be approved.']);
            } else {
                ProcurementDisbursement::whereIn('id', $ids)->update(['created_by' => $locked->created_by]);
            }
            $locked->update(['status' => 'approved', 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'review_note' => $data['review_note'] ?? null, 'published_ids' => $ids]);
            $this->service->audit($locked, 'Coordinator approved and posted assistant submission');
        });
        return back()->with('success', 'Approved. The financial records are now active and available to reporting.');
    }
}
