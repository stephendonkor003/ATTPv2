<?php

use App\Models\AssistantSubmission;
use App\Models\BudgetCommitment;
use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Models\Resource;
use App\Models\Role;
use App\Models\User;
use App\Services\AssistantSubmissionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

// This smoke uses real route middleware and financial validation, with every
// database change rolled back and all files, jobs, and outbound mail isolated.
putenv('SESSION_DRIVER=array');
$_ENV['SESSION_DRIVER'] = $_SERVER['SESSION_DRIVER'] = 'array';
require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$http = $app->make(HttpKernel::class);
approvalSmokeAssert(Schema::hasTable('assistant_submissions'), 'Apply the assistant submission migration before this smoke.');
config(['session.driver' => 'array', 'mail.default' => 'smtp', 'filesystems.default' => 'local']);
Mail::fake();
Bus::fake();
Queue::fake();
Storage::fake('local');
Storage::fake('public');
$baseline = approvalSmokeCounts();
DB::beginTransaction();

try {
    $assistant = User::with('role')->whereHas('role', fn ($q) => $q->whereIn('name', User::ADMINISTRATIVE_ASSISTANT_ROLES))->firstOrFail();
    $administrator = User::with('role')->get()->first(fn ($user) => $user->isAdmin() || $user->isSuperAdmin());
    approvalSmokeAssert($administrator !== null, 'A configured administrator is required.');
    foreach ([$assistant, $administrator] as $user) {
        $user->forceFill(['must_change_password' => false, 'password_changed_at' => now(), 'is_disabled' => false])->save();
    }
    $otherAssistant = approvalSmokeUser($assistant->role_id, 'Other assistant');
    $staffRole = Role::firstOrCreate(['name' => 'Assistant approval smoke staff']);
    $ordinaryStaff = approvalSmokeUser($staffRole->id, 'Ordinary staff');
    $service = app(AssistantSubmissionService::class);

    $funding = ProgramFunding::where('status', 'approved')
        ->when($assistant->governance_node_id, fn ($q) => $q->where('governance_node_id', $assistant->governance_node_id))->firstOrFail();
    $resource = Resource::where('status', 'active')->whereHas('category', fn ($q) => $q->where('status', 'active'))
        ->when($assistant->governance_node_id, fn ($q) => $q->where('governance_node_id', $assistant->governance_node_id))->firstOrFail();
    $candidates = DB::table('myb_sub_activity_allocations as a')
        ->join('myb_sub_activities as s', 's.id', '=', 'a.sub_activity_id')
        ->join('myb_activities as ac', 'ac.id', '=', 's.activity_id')
        ->join('myb_projects as p', 'p.id', '=', 'ac.project_id')
        ->where('p.program_id', $funding->program_id)->where('a.year', '>=', now()->year)
        ->when($assistant->governance_node_id, fn ($q) => $q->where('s.governance_node_id', $assistant->governance_node_id))
        ->groupBy('a.sub_activity_id', 'a.year')->orderBy('a.year')
        ->select(['a.sub_activity_id', 'a.year', DB::raw('SUM(a.amount) as allocated_amount')])->get();
    $allocation = $candidates->first(function ($candidate) use ($funding) {
        $committed = BudgetCommitment::where('allocation_level', 'sub_activity')->where('allocation_id', $candidate->sub_activity_id)
            ->where('commitment_year', $candidate->year)->whereIn('status', ['draft', 'submitted', 'approved'])
            ->when($funding->governance_node_id, fn ($q) => $q->where('governance_node_id', $funding->governance_node_id))->sum('commitment_amount');
        return (float) $candidate->allocated_amount - (float) $committed >= 30;
    });
    approvalSmokeAssert($allocation !== null, 'No eligible sub-activity with 30 available budget units was found.');
    $pdfBytes = Pdf::loadHTML('<h1>Assistant approval smoke supporting document</h1><p>Temporary validation fixture.</p>')->output();
    $makePrFiles = fn () => ['pr_attachments' => [
        UploadedFile::fake()->createWithContent('fund-availability.pdf', $pdfBytes),
        UploadedFile::fake()->createWithContent('terms-of-reference.pdf', $pdfBytes),
    ]];
    $marker = 'Approval smoke '.Str::upper(Str::random(10));
    $payload = [
        'program_funding_id' => $funding->id, 'allocation_level' => 'sub_activity',
        'commitment_year' => (string) $allocation->year, 'allocation_id' => $allocation->sub_activity_id,
        'description' => $marker, 'delivery_date' => now()->addDays(30)->toDateString(),
        'items' => [['resource_category_id' => $resource->resource_category_id, 'resource_id' => $resource->id,
            'unit_price' => '10.00', 'quantity' => '1', 'amount' => '10.00', 'milestone' => $marker,
            'milestone_date' => now()->addDays(30)->toDateString()]],
        'pr_attachment_types' => ['fund_availability', 'tors'],
        'pr_attachment_titles' => ['Fund Availability', 'Terms of Reference'],
        'status' => 'approved', 'created_by' => $administrator->id, 'reviewed_by' => $administrator->id,
    ];

    $page = approvalSmokeRequest($assistant, '/administrative-assistant/requests/create');
    approvalSmokeHtml('approval-pr.html', $page);
    approvalSmokeAssert($page->getStatusCode() === 200 && str_contains($page->getContent(), '/administrative-assistant/requests')
        && str_contains($page->getContent(), 'program_funding_id'), 'Full assistant purchase request form did not render.');
    foreach (['/finance/purchase-requests/create', '/procurement/disbursements/create'] as $legacyPath) {
        $legacy = approvalSmokeRequest($assistant, $legacyPath);
        approvalSmokeAssert(in_array($legacy->getStatusCode(), [200, 302, 303], true), 'Assistant create entry point failed: '.$legacyPath);
    }
    $invalid = approvalSmokeRequest($assistant, '/administrative-assistant/requests', 'POST', $payload);
    approvalSmokeAssert(in_array($invalid->getStatusCode(), [302, 303], true) && approvalSmokeCounts() === $baseline,
        'Missing required PR documents created a submission or financial record.');
    $response = approvalSmokeRequest($assistant, '/administrative-assistant/requests', 'POST', $payload, $makePrFiles());
    approvalSmokeAssertRedirect($response, 'Full purchase request submission');
    $submission = AssistantSubmission::where('created_by', $assistant->id)->where('kind', 'purchase_request')->latest()->first();
    approvalSmokeAssert($submission && ($submission->payload['description'] ?? null) === $marker,
        'No assistant submission was captured. '.approvalSmokeErrors());
    approvalSmokeAssert($submission->status === 'pending' && $submission->reviewed_by === null && $submission->published_ids === null,
        'Untrusted review fields bypassed pending state.');
    approvalSmokeAssert(! isset($submission->payload['status'], $submission->payload['created_by'])
        && count($submission->documents) === 2 && $submission->notification_status === 'queued', 'Validated payload, private documents, or queued email status is incomplete.');
    approvalSmokeAssert(PurchaseRequest::count() === $baseline['purchase_requests'] && BudgetCommitment::count() === $baseline['commitments'],
        'Pending purchase request affected financial records.');
    foreach ($submission->documents as $document) {
        approvalSmokeAssert(Storage::disk('local')->exists($document['path'])
            && str_starts_with($document['path'], 'assistant-submissions/'.$submission->id.'/'), 'Pending document is not privately stored.');
    }
    approvalSmokeAssert(Storage::disk('public')->allFiles() === [], 'Pending documents leaked to public storage.');
    $show = approvalSmokeRequest($assistant, '/administrative-assistant/submissions/'.$submission->id);
    approvalSmokeHtml('approval-show.html', $show);
    approvalSmokeAssert($show->getStatusCode() === 200 && str_contains($show->getContent(), $submission->reference_no), 'Assistant detail page omitted saved submission.');
    $pdf = approvalSmokeRequest($assistant, '/administrative-assistant/submissions/'.$submission->id.'/pdf');
    approvalSmokeAssert($pdf->getStatusCode() === 200 && str_starts_with($pdf->getContent(), '%PDF-'), 'Submission PDF did not render real PDF bytes.');
    $document = approvalSmokeRequest($assistant, '/administrative-assistant/submissions/'.$submission->id.'/documents/0');
    approvalSmokeAssert($document->getStatusCode() === 200 && str_contains((string) $document->headers->get('Cache-Control'), 'no-store'), 'Private supporting document download failed.');
    foreach (['', '/pdf', '/documents/0'] as $suffix) {
        approvalSmokeAssert(in_array(approvalSmokeRequest($otherAssistant, '/administrative-assistant/submissions/'.$submission->id.$suffix)->getStatusCode(), [403, 404], true),
            'Another assistant accessed a private submission'.$suffix.'.');
    }
    foreach ([$assistant, $ordinaryStaff] as $unauthorized) {
        foreach (['approve', 'reject'] as $decision) {
            $denied = approvalSmokeRequest($unauthorized, '/assistant-approvals/'.$submission->id.'/'.$decision, 'POST', ['review_note' => 'Forbidden reviewer probe']);
            approvalSmokeAssert($denied->getStatusCode() === 403, 'Unauthorized '.$decision.' was not blocked.');
        }
    }
    $selfReviewProbe = new AssistantSubmission(['created_by' => $administrator->id, 'governance_node_id' => $funding->governance_node_id]);
    approvalSmokeAssert(! $service->canReview($administrator, $selfReviewProbe), 'Administrators can approve their own submission.');
    foreach ([[$assistant, '/administrative-assistant/submissions?status=pending&q='.urlencode($marker)], [$administrator, '/assistant-approvals?status=pending']] as [$viewer, $uri]) {
        $list = approvalSmokeRequest($viewer, $uri);
        if ($viewer->id === $assistant->id) approvalSmokeHtml('approval-index.html', $list);
        approvalSmokeAssert($list->getStatusCode() === 200 && str_contains($list->getContent(), $submission->reference_no), 'Submission missing from assistant/reviewer pending list.');
    }
    echo "PENDING_PR_PRIVATE_DOCUMENTS_AND_AUTHORIZATION_OK\n";

    $approve = approvalSmokeRequest($administrator, '/assistant-approvals/'.$submission->id.'/approve', 'POST', ['review_note' => 'Checked budget and supporting documents.']);
    approvalSmokeAssertRedirect($approve, 'Purchase request approval');
    $submission->refresh();
    approvalSmokeAssert($submission->status === 'approved' && $submission->reviewed_by === $administrator->id && count($submission->published_ids ?? []) === 1,
        'Purchase request approval did not post records. '.approvalSmokeErrors());
    $purchaseRequest = PurchaseRequest::with(['items', 'commitments', 'attachments'])->findOrFail($submission->published_ids[0]);
    approvalSmokeAssert($purchaseRequest->status === 'approved' && $purchaseRequest->created_by === $assistant->id
        && $purchaseRequest->approved_by === $administrator->id && $purchaseRequest->approved_at !== null,
        'Published PR lost assistant creator or reviewer approval.');
    approvalSmokeAssert($purchaseRequest->items->count() === 1 && $purchaseRequest->attachments->count() === 2
        && $purchaseRequest->commitments->isNotEmpty() && $purchaseRequest->commitments->every(fn ($row) => $row->status === 'approved' && $row->created_by === $assistant->id)
        && round((float) $purchaseRequest->commitments->sum('commitment_amount'), 2) === 10.0, 'Approved PR details/attachments/commitments are incomplete.');
    $afterApproval = approvalSmokeCounts();
    foreach (['approve', 'reject'] as $decision) {
        $duplicate = approvalSmokeRequest($administrator, '/assistant-approvals/'.$submission->id.'/'.$decision, 'POST', ['review_note' => 'Duplicate review probe']);
        approvalSmokeAssert($duplicate->getStatusCode() === 409 && approvalSmokeCounts() === $afterApproval, 'Reviewed PR was posted again or changed by a duplicate decision.');
    }

    $rejectedPayload = array_replace($payload, ['description' => $marker.' rejection']);
    approvalSmokeAssertRedirect(approvalSmokeRequest($assistant, '/administrative-assistant/requests', 'POST', $rejectedPayload, $makePrFiles()), 'Second PR submission');
    $rejected = AssistantSubmission::where('created_by', $assistant->id)->where('kind', 'purchase_request')->where('status', 'pending')->latest()->firstOrFail();
    approvalSmokeAssertRedirect(approvalSmokeRequest($administrator, '/assistant-approvals/'.$rejected->id.'/reject', 'POST', ['review_note' => 'Please correct the supporting justification.']), 'PR rejection');
    approvalSmokeAssert($rejected->fresh()->status === 'rejected' && $rejected->fresh()->published_ids === null
        && PurchaseRequest::count() === $afterApproval['purchase_requests'] && BudgetCommitment::count() === $afterApproval['commitments'], 'Rejected PR affected reports or lost rejection state.');
    $rejectedShow = approvalSmokeRequest($assistant, '/administrative-assistant/submissions/'.$rejected->id);
    approvalSmokeAssert(str_contains($rejectedShow->getContent(), 'Please correct the supporting justification.'), 'Assistant cannot see rejection feedback.');
    echo "APPROVED_PR_REPORTABLE_ONCE_AND_REJECTION_OK\n";

    // A temporary source PO makes the payment balance deterministic without
    // altering any existing purchase order or its evidence.
    $purchaseOrder = ProcurementPurchaseOrder::create([
        'purchase_request_id' => $purchaseRequest->id, 'budget_commitment_id' => $purchaseRequest->commitments->first()->id,
        'sub_activity_id' => $allocation->sub_activity_id, 'governance_node_id' => $funding->governance_node_id,
        'reference_no' => 'SMOKE-PO-'.Str::upper(Str::random(10)), 'po_title' => $marker,
        'amount' => 10, 'currency' => $purchaseRequest->resolved_currency, 'status' => 'issued', 'created_by' => $administrator->id,
    ]);
    $item = $purchaseRequest->items->first();
    $paymentPayload = ['purchase_order_id' => $purchaseOrder->id, 'payments' => [[
        'purchase_request_item_id' => $item->id, 'amount' => '8.00', 'payment_method' => 'Bank Transfer',
        'paid_at' => now()->toDateString(), 'status' => 'completed', 'transfer_reference' => $marker,
        'signed_document_names' => ['Authorized payment'], 'notes' => $marker,
    ]], 'item_evidence' => [$item->id => ['is_met' => '1', 'deliverable_date' => now()->toDateString(), 'notes' => 'Reviewed with payment approval.']]];
    $paymentFiles = fn () => ['payments' => [['signed_documents' => [UploadedFile::fake()->createWithContent('signed-payment.pdf', $pdfBytes)]]]];
    $paymentForm = approvalSmokeRequest($assistant, '/administrative-assistant/disbursements/create?purchase_order_id='.$purchaseOrder->id);
    approvalSmokeHtml('approval-disbursement.html', $paymentForm);
    approvalSmokeAssert($paymentForm->getStatusCode() === 200 && str_contains($paymentForm->getContent(), $purchaseOrder->reference_no)
        && str_contains($paymentForm->getContent(), '/administrative-assistant/disbursements'), 'Full assistant disbursement form or eligible PO failed.');
    $beforePayment = approvalSmokeCounts();
    $paymentSubmit = approvalSmokeRequest($assistant, '/administrative-assistant/disbursements', 'POST', $paymentPayload, $paymentFiles());
    approvalSmokeAssertRedirect($paymentSubmit, 'Disbursement submission');
    $payment = AssistantSubmission::where('created_by', $assistant->id)->where('kind', 'disbursement')->latest()->first();
    approvalSmokeAssert($payment && $payment->status === 'pending' && count($payment->documents) === 1,
        'Pending disbursement was not captured. '.approvalSmokeErrors());
    approvalSmokeAssert(ProcurementDisbursement::count() === $beforePayment['disbursements']
        && ! ProcurementDisbursement::recognizedPayment()->where('purchase_order_id', $purchaseOrder->id)->exists()
        && ! ProcurementPurchaseOrderItemEvidence::where('purchase_order_id', $purchaseOrder->id)->exists()
        && $purchaseOrder->fresh()->status === 'issued', 'Pending disbursement changed reports, PO status, or evidence.');

    // Simulate another payment between submission and review: stale balance
    // must block publishing while leaving the submission pending.
    $intervening = ProcurementDisbursement::create([
        'purchase_order_id' => $purchaseOrder->id, 'purchase_request_item_id' => $item->id,
        'reference_no' => 'SMOKE-INTERVENING-'.Str::upper(Str::random(8)), 'amount' => 3,
        'currency' => $purchaseRequest->resolved_currency, 'status' => 'completed', 'paid_at' => now(),
        'payment_method' => 'Bank Transfer', 'created_by' => $administrator->id,
    ]);
    $stale = approvalSmokeRequest($administrator, '/assistant-approvals/'.$payment->id.'/approve', 'POST');
    approvalSmokeAssert(in_array($stale->getStatusCode(), [302, 303, 422], true) && $payment->fresh()->status === 'pending'
        && ProcurementDisbursement::count() === $beforePayment['disbursements'] + 1
        && ! ProcurementPurchaseOrderItemEvidence::where('purchase_order_id', $purchaseOrder->id)->exists(),
        'Approval failed to reject a stale payment balance atomically.');
    $intervening->delete();

    $approvedPayment = approvalSmokeRequest($administrator, '/assistant-approvals/'.$payment->id.'/approve', 'POST', ['review_note' => 'Payment and signed document checked.']);
    approvalSmokeAssertRedirect($approvedPayment, 'Disbursement approval');
    $payment->refresh();
    approvalSmokeAssert($payment->status === 'approved' && count($payment->published_ids ?? []) === 1,
        'Disbursement approval failed. '.approvalSmokeErrors());
    $disbursement = ProcurementDisbursement::findOrFail($payment->published_ids[0]);
    approvalSmokeAssert($disbursement->created_by === $assistant->id && (float) $disbursement->amount === 8.0
        && count($disbursement->signed_documents ?? []) === 1
        && ProcurementDisbursement::recognizedPayment()->whereKey($disbursement->id)->exists()
        && ProcurementPurchaseOrderItemEvidence::where('purchase_order_id', $purchaseOrder->id)->where('is_met', true)->exists(),
        'Approved disbursement did not preserve its author, files, evidence, and reportable payment.');
    $duplicatePayment = approvalSmokeRequest($administrator, '/assistant-approvals/'.$payment->id.'/approve', 'POST');
    approvalSmokeAssert($duplicatePayment->getStatusCode() === 409
        && ProcurementDisbursement::count() === $beforePayment['disbursements'] + 1, 'Disbursement review posted twice.');
    foreach ([[$assistant, '/administrative-assistant/submissions?status=approved'], [$administrator, '/assistant-approvals?status=approved']] as [$viewer, $uri]) {
        $list = approvalSmokeRequest($viewer, $uri);
        approvalSmokeAssert($list->getStatusCode() === 200 && str_contains($list->getContent(), $payment->reference_no), 'Approved payment is absent from the approval tracker.');
    }
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
    Bus::assertNothingDispatched();
    Queue::assertNothingPushed();
    echo "DISBURSEMENT_PENDING_STALE_BALANCE_APPROVAL_AND_REPORTING_OK\n";
} finally {
    while (DB::transactionLevel() > 0) DB::rollBack();
    Auth::logout();
}
approvalSmokeAssert(approvalSmokeCounts() === $baseline, 'Smoke left persistent financial or submission rows.');
echo "ASSISTANT_SUBMISSION_APPROVAL_SMOKE_OK (all changes rolled back; no mail or jobs sent)\n";

function approvalSmokeCounts(): array
{
    return ['submissions' => AssistantSubmission::count(), 'purchase_requests' => PurchaseRequest::count(),
        'commitments' => BudgetCommitment::count(), 'disbursements' => ProcurementDisbursement::count()];
}

function approvalSmokeUser(string $roleId, string $name): User
{
    return User::create(['name' => $name, 'email' => 'approval-smoke-'.Str::lower(Str::random(12)).'@example.test',
        'password' => Hash::make(Str::random(32)), 'user_type' => 'staff', 'role_id' => $roleId,
        'must_change_password' => false, 'password_changed_at' => now(), 'is_disabled' => false]);
}

function approvalSmokeRequest(User $user, string $uri, string $method = 'GET', array $parameters = [], array $files = [])
{
    global $app, $http;
    Auth::logout();
    $session = $app['session.store'];
    if ($session->isStarted()) $session->save();
    $session->setId(Str::random(40));
    $session->start();
    $session->flush();
    Auth::login($user);
    $token = bin2hex(random_bytes(20));
    $session->put('_token', $token);
    $session->put('otp_verified', true);
    $session->put('otp_verified_user_id', (string) $user->id);
    $session->put('otp_verified_at', now()->toIso8601String());
    $session->save();
    $request = Request::create($uri, $method, ['_token' => $token] + $parameters, [], $files,
        ['HTTP_ACCEPT' => 'text/html,application/xhtml+xml']);
    $request->setLaravelSession($session);
    return $http->handle($request);
}

function approvalSmokeErrors(): string
{
    return json_encode(app('session.store')->get('errors')?->getBag('default')->messages() ?? []);
}

function approvalSmokeHtml(string $name, $response): void
{
    if (getenv('APPROVAL_SMOKE_HTML') !== '1' || $response->getStatusCode() !== 200) return;
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'aa-browser-check';
    if (! is_dir($directory)) mkdir($directory, 0700, true);
    file_put_contents($directory.DIRECTORY_SEPARATOR.$name, $response->getContent());
}

function approvalSmokeAssertRedirect($response, string $context): void
{
    approvalSmokeAssert(in_array($response->getStatusCode(), [302, 303], true), $context.' returned HTTP '.$response->getStatusCode().'. '.approvalSmokeErrors());
}

function approvalSmokeAssert(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}
