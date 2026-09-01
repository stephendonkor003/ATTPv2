<?php

use App\Models\ProcurementInvoice;
use App\Models\ProcurementPurchaseOrderItemEvidence;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$http = $app->make(HttpKernel::class);

Storage::fake('local');
DB::beginTransaction();

try {
    $assistant = User::query()
        ->whereHas('role', fn ($query) => $query->whereIn('name', User::ADMINISTRATIVE_ASSISTANT_ROLES))
        ->firstOrFail();
    $assistant->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'is_disabled' => false,
    ])->save();

    $evidence = ProcurementPurchaseOrderItemEvidence::query()
        ->with(['purchaseOrder.vendor', 'purchaseRequestItem'])
        ->whereNull('invoice_id')
        ->whereHas('purchaseOrder', fn ($query) => $query->whereNotNull('vendor_id'))
        ->firstOrFail();
    $purchaseOrder = $evidence->purchaseOrder;
    $item = $evidence->purchaseRequestItem;
    $originalDocumentCount = collect($evidence->documents ?? [])->count();

    Auth::login($assistant);
    $session = $app['session.store'];
    $session->start();
    $token = bin2hex(random_bytes(20));
    $session->put('_token', $token);
    $session->put('otp_verified', true);
    $session->put('otp_verified_user_id', (string) $assistant->id);
    $session->put('otp_verified_at', now()->toIso8601String());
    $session->save();

    $dashboard = assistantEvidenceRequest($http, $session, '/administrative-assistant');
    assistantEvidenceAssert($dashboard->getStatusCode() === 200, 'Assistant dashboard did not load.');
    assistantEvidenceAssert(str_contains((string) $dashboard->getContent(), 'Upload centre'), 'Assistant dashboard content is missing.');

    $folderDate = $evidence->deliverable_date ?: $item->milestone_date ?: now();
    $folderYear = $folderDate->year;
    $folderMonth = $folderDate->month;
    assistantEvidenceAssert(
        str_contains((string) $dashboard->getContent(), (string) $folderYear),
        'The relevant year folder is missing from the assistant dashboard.'
    );

    $yearFolder = assistantEvidenceRequest($http, $session, "/administrative-assistant?year={$folderYear}");
    assistantEvidenceAssert($yearFolder->getStatusCode() === 200, 'Year folder did not open.');
    assistantEvidenceAssert(
        str_contains((string) $yearFolder->getContent(), "{$folderYear} monthly folders")
            && str_contains((string) $yearFolder->getContent(), $folderDate->format('F')),
        'Year folder does not show the expected month card.'
    );

    $monthFolder = assistantEvidenceRequest(
        $http,
        $session,
        "/administrative-assistant?year={$folderYear}&month={$folderMonth}"
    );
    assistantEvidenceAssert($monthFolder->getStatusCode() === 200, 'Month folder did not open.');
    assistantEvidenceAssert(
        str_contains((string) $monthFolder->getContent(), $purchaseOrder->vendor->name)
            && str_contains((string) $monthFolder->getContent(), $item->milestone ?: 'Deliverable'),
        'Month folder does not show the expected vendor card and deliverable.'
    );

    $blockedAdminPage = assistantEvidenceRequest($http, $session, '/finance/purchase-requests');
    assistantEvidenceAssert(
        in_array($blockedAdminPage->getStatusCode(), [302, 303], true)
            && str_contains((string) $blockedAdminPage->headers->get('Location'), '/administrative-assistant'),
        'Assistant was not redirected away from the general finance area.'
    );

    $reference = 'SMOKE-'.strtoupper(bin2hex(random_bytes(4)));
    $upload = assistantEvidenceRequest(
        $http,
        $session,
        "/administrative-assistant/purchase-orders/{$purchaseOrder->id}/items/{$item->id}",
        'POST',
        [
            '_token' => $token,
            'deliverable_date' => ($item->milestone_date ?: now())->format('Y-m-d'),
            'invoice_reference' => $reference,
            'invoice_amount' => (string) ($item->amount ?: 1),
            'notes' => 'Administrative Assistant evidence smoke test.',
        ],
        [
            'invoice_documents' => [
                UploadedFile::fake()->create('monthly-invoice.pdf', 20, 'application/pdf'),
                UploadedFile::fake()->create('monthly-invoice-annex.pdf', 20, 'application/pdf'),
            ],
            'supporting_documents' => [
                UploadedFile::fake()->create('monthly-report.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                UploadedFile::fake()->create('monthly-receipt.jpg', 20, 'image/jpeg'),
            ],
        ]
    );
    assistantEvidenceAssert(in_array($upload->getStatusCode(), [302, 303], true), 'Assistant evidence upload failed.');

    $evidence->refresh();
    $invoice = ProcurementInvoice::query()->find($evidence->invoice_id);
    assistantEvidenceAssert((bool) $invoice, 'Invoice record was not created from the upload.');
    assistantEvidenceAssert($invoice->reference_no === $reference, 'Uploaded invoice reference was not recorded.');
    assistantEvidenceAssert((string) $invoice->vendor_id === (string) $purchaseOrder->vendor_id, 'Invoice was not linked to the PO vendor.');
    $newDocuments = collect($evidence->documents)->slice($originalDocumentCount)->values();
    assistantEvidenceAssert(
        $newDocuments->count() === 4,
        'Evidence did not retain all four uploaded documents.'
    );
    assistantEvidenceAssert(
        $newDocuments->where('document_type', 'invoice')->count() === 2
            && $newDocuments->where('document_type', 'supporting')->count() === 2,
        'Uploaded documents were not assigned to the expected invoice and supporting groups.'
    );
    assistantEvidenceAssert(
        $newDocuments->every(fn ($document) => ($document['source'] ?? null) === 'administrative_assistant'),
        'Uploaded evidence source was not recorded.'
    );
    assistantEvidenceAssert(
        $newDocuments->every(fn ($document) => Storage::disk('local')->exists($document['path'] ?? '')),
        'One or more uploaded evidence files are missing from private storage.'
    );

    $assistantEvidencePage = assistantEvidenceRequest(
        $http,
        $session,
        "/administrative-assistant/purchase-orders/{$purchaseOrder->id}/items/{$item->id}"
    );
    assistantEvidenceAssert(
        collect(['monthly-invoice.pdf', 'monthly-invoice-annex.pdf', 'monthly-report.docx', 'monthly-receipt.jpg'])
            ->every(fn ($name) => str_contains((string) $assistantEvidencePage->getContent(), $name)),
        'The assistant page does not list every uploaded document.'
    );

    $documentCountBeforeRejectedUpload = collect($evidence->documents)->count();
    $tooManyDocuments = assistantEvidenceRequest(
        $http,
        $session,
        "/administrative-assistant/purchase-orders/{$purchaseOrder->id}/items/{$item->id}",
        'POST',
        [
            '_token' => $token,
            'deliverable_date' => ($item->milestone_date ?: now())->format('Y-m-d'),
        ],
        [
            'invoice_documents' => array_map(
                fn ($index) => UploadedFile::fake()->create("invoice-limit-{$index}.pdf", 1, 'application/pdf'),
                range(1, 10)
            ),
            'supporting_documents' => array_map(
                fn ($index) => UploadedFile::fake()->create("support-limit-{$index}.pdf", 1, 'application/pdf'),
                range(1, 11)
            ),
        ]
    );
    assistantEvidenceAssert(
        in_array($tooManyDocuments->getStatusCode(), [302, 303], true),
        'The over-limit document request was not rejected.'
    );
    $evidence->refresh();
    assistantEvidenceAssert(
        collect($evidence->documents)->count() === $documentCountBeforeRejectedUpload,
        'An over-limit upload changed the evidence documents.'
    );

    $admin = User::query()->where('user_type', 'admin')->firstOrFail();
    Auth::login($admin);
    $adminSession = $app['session.store'];
    $adminSession->start();
    $adminSession->save();
    $financeInvoice = assistantEvidenceRequest($http, $adminSession, "/procurement/invoices/{$invoice->id}");
    assistantEvidenceAssert($financeInvoice->getStatusCode() === 200, 'Finance invoice page did not load.');
    assistantEvidenceAssert(
        str_contains((string) $financeInvoice->getContent(), 'monthly-invoice.pdf')
            && str_contains((string) $financeInvoice->getContent(), 'monthly-invoice-annex.pdf')
            && str_contains((string) $financeInvoice->getContent(), 'monthly-report.docx')
            && str_contains((string) $financeInvoice->getContent(), 'monthly-receipt.jpg'),
        'Uploaded files were not reflected on the finance invoice.'
    );

    $vendor = $purchaseOrder->vendor;
    $vendor->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ])->save();
    Auth::login($vendor);
    $vendorSession = $app['session.store'];
    $vendorSession->start();
    $vendorSession->put('otp_verified', true);
    $vendorSession->put('otp_verified_user_id', (string) $purchaseOrder->vendor_id);
    $vendorSession->put('otp_verified_at', now()->toIso8601String());
    $vendorSession->save();

    $vendorInvoice = assistantEvidenceRequest($http, $vendorSession, "/vendor/invoices/{$invoice->id}");
    assistantEvidenceAssert(
        $vendorInvoice->getStatusCode() === 200,
        'Vendor invoice page did not load (status '.$vendorInvoice->getStatusCode().', location '.($vendorInvoice->headers->get('Location') ?: 'none').').'
    );
    assistantEvidenceAssert(
        str_contains((string) $vendorInvoice->getContent(), 'monthly-invoice.pdf')
            && str_contains((string) $vendorInvoice->getContent(), 'monthly-invoice-annex.pdf')
            && str_contains((string) $vendorInvoice->getContent(), 'monthly-report.docx')
            && str_contains((string) $vendorInvoice->getContent(), 'monthly-receipt.jpg'),
        'Uploaded files were not reflected on the vendor invoice.'
    );

    echo "ADMINISTRATIVE_ASSISTANT_EVIDENCE_OK\n";
} finally {
    DB::rollBack();
}

function assistantEvidenceRequest(
    HttpKernel $http,
    $session,
    string $uri,
    string $method = 'GET',
    array $parameters = [],
    array $files = []
) {
    $request = Request::create($uri, $method, $parameters, [], $files);
    $request->setLaravelSession($session);

    return $http->handle($request);
}

function assistantEvidenceAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
