<?php

use App\Models\BudgetCommitment;
use App\Models\ProgramFunding;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestIntake;
use App\Models\PurchaseRequestIntakeDocument;
use App\Models\Resource;
use App\Models\SubActivity;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

putenv('SESSION_DRIVER=array');
$_ENV['SESSION_DRIVER'] = 'array';
$_SERVER['SESSION_DRIVER'] = 'array';

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$http = $app->make(HttpKernel::class);

intakeSmokeAssert(
    Schema::hasTable('purchase_request_intakes')
        && Schema::hasTable('purchase_request_intake_items')
        && Schema::hasTable('purchase_request_intake_documents'),
    'Purchase request intake migrations have not been applied.'
);

Storage::fake('local');
Storage::fake('public');
DB::beginTransaction();
$documentFailureListenerRegistered = false;

try {
    $assistant = User::query()
        ->whereHas('role', fn ($query) => $query->whereIn('name', User::ADMINISTRATIVE_ASSISTANT_ROLES))
        ->firstOrFail();
    $assistant->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'is_disabled' => false,
    ])->save();

    $otherAssistant = User::query()->create([
        'name' => 'Assistant Intake Ownership Probe',
        'email' => 'assistant-intake-'.Str::lower(Str::random(12)).'@example.test',
        'password' => Hash::make(Str::random(32)),
        'user_type' => 'staff',
        'role_id' => $assistant->role_id,
        'governance_node_id' => $assistant->governance_node_id,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'is_disabled' => false,
    ]);

    $administrator = User::query()
        ->where('user_type', 'admin')
        ->whereKeyNot($assistant->id)
        ->firstOrFail();

    [$assistantSession, $assistantToken] = intakeSmokeSession($app, $assistant);
    $createPage = intakeSmokeRequest(
        $http,
        $assistantSession,
        '/administrative-assistant/purchase-requests/create'
    );
    intakeSmokeAssert($createPage->getStatusCode() === 200, 'Assistant PR create page did not load.');
    intakeSmokeAssert(
        str_contains((string) $createPage->getContent(), 'Submit PR to back office')
            && str_contains((string) $createPage->getContent(), 'Submitting this form does not reserve or approve budget'),
        'Assistant PR create page does not explain the intake-only handoff.'
    );

    $intakesBefore = PurchaseRequestIntake::query()->count();
    $purchaseRequestsBefore = PurchaseRequest::query()->count();
    $commitmentsBefore = BudgetCommitment::query()->count();
    $marker = 'Assistant intake smoke '.Str::upper(Str::random(8));

    $storeResponse = intakeSmokeRequest(
        $http,
        $assistantSession,
        '/administrative-assistant/purchase-requests',
        'POST',
        [
            '_token' => $assistantToken,
            'title' => $marker,
            'description' => 'A simple Administrative Assistant request that Finance must classify and complete.',
            'needed_by' => now()->addDays(14)->toDateString(),
            'priority' => 'urgent',
            'estimated_amount' => '1250.50',
            'currency' => 'kes',
            'items' => [
                [
                    'name' => 'Workshop venue',
                    'quantity' => '1',
                    'notes' => 'Accessible room for 25 participants.',
                ],
                [
                    'name' => 'Participant materials',
                    'quantity' => '25',
                    'notes' => 'Printed packs and name badges.',
                ],
            ],
            // These lifecycle fields are intentionally untrusted and must be ignored.
            'reference_no' => 'FORGED-REFERENCE',
            'status' => PurchaseRequestIntake::STATUS_CONVERTED,
            'created_by' => $otherAssistant->id,
            'converted_by' => $otherAssistant->id,
            'converted_at' => now()->toDateTimeString(),
        ],
        [
            'documents' => [
                UploadedFile::fake()->create('intake-request.pdf', 12, 'application/pdf'),
                UploadedFile::fake()->create('supplier-quote.csv', 8, 'text/csv'),
            ],
        ]
    );
    intakeSmokeAssert(
        in_array($storeResponse->getStatusCode(), [302, 303], true),
        'Valid Assistant PR intake was not accepted.'
    );

    $intake = PurchaseRequestIntake::query()
        ->with(['items', 'documents'])
        ->where('title', $marker)
        ->firstOrFail();

    intakeSmokeAssert(
        preg_match('/^APR-\d{4}-[A-Z0-9]{6}$/', $intake->reference_no) === 1
            && $intake->reference_no !== 'FORGED-REFERENCE',
        'The intake reference was not generated by the server.'
    );
    intakeSmokeAssert(
        (string) $intake->created_by === (string) $assistant->id
            && $intake->status === PurchaseRequestIntake::STATUS_SUBMITTED
            && $intake->converted_by === null
            && $intake->converted_at === null
            && $intake->converted_purchase_request_id === null,
        'Assistant identity or intake lifecycle fields were accepted from untrusted input.'
    );
    intakeSmokeAssert(
        (string) ($intake->governance_node_id ?? '') === (string) ($assistant->governance_node_id ?? ''),
        'The intake governance node was not derived from the Assistant account.'
    );
    intakeSmokeAssert(
        $intake->currency === 'KES'
            && (float) $intake->estimated_amount === 1250.50
            && $intake->items->count() === 2
            && $intake->documents->count() === 2,
        'The valid intake details, items, or documents were not stored completely.'
    );
    intakeSmokeAssert(
        PurchaseRequestIntake::query()->count() === $intakesBefore + 1
            && PurchaseRequest::query()->count() === $purchaseRequestsBefore
            && BudgetCommitment::query()->count() === $commitmentsBefore,
        'Assistant intake directly created or reserved a Finance purchase request or commitment.'
    );
    intakeSmokeAssert(
        $intake->documents->every(fn ($document) => (string) $document->uploaded_by === (string) $assistant->id
            && str_starts_with($document->file_path, "administrative-assistant/purchase-request-intakes/{$intake->id}/")
            && Storage::disk('local')->exists($document->file_path)
        ),
        'One or more intake documents lack uploader attribution or private storage.'
    );
    intakeSmokeAssert(
        Storage::disk('public')->allFiles() === [],
        'An Assistant intake document was exposed through public storage.'
    );

    $showResponse = intakeSmokeRequest(
        $http,
        $assistantSession,
        "/administrative-assistant/purchase-requests/{$intake->id}"
    );
    intakeSmokeAssert($showResponse->getStatusCode() === 200, 'Assistant could not open their own PR intake.');
    intakeSmokeAssert(
        str_contains((string) $showResponse->getContent(), $intake->reference_no)
            && str_contains((string) $showResponse->getContent(), 'Workshop venue')
            && str_contains((string) $showResponse->getContent(), 'intake-request.pdf'),
        'Assistant PR detail page omitted saved request information.'
    );

    $document = $intake->documents->firstOrFail();
    $downloadResponse = intakeSmokeRequest(
        $http,
        $assistantSession,
        "/administrative-assistant/purchase-requests/{$intake->id}/documents/{$document->id}?download=1"
    );
    intakeSmokeAssert($downloadResponse->getStatusCode() === 200, 'Assistant could not download their own private intake document.');
    intakeSmokeAssert(
        str_contains((string) $downloadResponse->headers->get('Cache-Control'), 'no-store')
            && $downloadResponse->headers->get('X-Content-Type-Options') === 'nosniff',
        'Private intake download is missing no-cache or content-sniffing protection.'
    );

    $tamperedPath = 'administrative-assistant/purchase-request-intakes/outside-'.$intake->id.'/tampered.txt';
    Storage::disk('local')->put($tamperedPath, 'This path must never be served as an intake document.');
    $tamperedDocument = $intake->documents()->create([
        'uploaded_by' => $assistant->id,
        'file_path' => $tamperedPath,
        'file_name' => 'tampered.txt',
        'mime_type' => 'text/plain',
        'file_size_bytes' => Storage::disk('local')->size($tamperedPath),
    ]);
    $tamperedDownload = intakeSmokeRequest(
        $http,
        $assistantSession,
        "/administrative-assistant/purchase-requests/{$intake->id}/documents/{$tamperedDocument->id}"
    );
    intakeSmokeAssert(
        $tamperedDownload->getStatusCode() === 404,
        'Assistant document download served a database path outside its private intake directory.'
    );

    [$otherSession] = intakeSmokeSession($app, $otherAssistant);
    $foreignShow = intakeSmokeRequest(
        $http,
        $otherSession,
        "/administrative-assistant/purchase-requests/{$intake->id}"
    );
    $foreignDownload = intakeSmokeRequest(
        $http,
        $otherSession,
        "/administrative-assistant/purchase-requests/{$intake->id}/documents/{$document->id}"
    );
    intakeSmokeAssert(
        $foreignShow->getStatusCode() === 404 && $foreignDownload->getStatusCode() === 404,
        'A different Administrative Assistant could access another user\'s intake or document.'
    );

    [$administratorSession, $administratorToken] = intakeSmokeSession($app, $administrator);
    $roleBoundary = intakeSmokeRequest(
        $http,
        $administratorSession,
        '/administrative-assistant/purchase-requests/create'
    );
    intakeSmokeAssert($roleBoundary->getStatusCode() === 403, 'A non-Assistant account entered the Assistant PR workspace.');

    $financeIndex = intakeSmokeRequest($http, $administratorSession, '/finance/purchase-requests');
    intakeSmokeAssert($financeIndex->getStatusCode() === 200, 'Finance pending-intake register did not load.');
    intakeSmokeAssert(
        str_contains((string) $financeIndex->getContent(), $intake->reference_no)
            && str_contains((string) $financeIndex->getContent(), 'Complete PR'),
        'Submitted Assistant intake was not visible in the Finance handoff register.'
    );

    $financeCompletion = intakeSmokeRequest(
        $http,
        $administratorSession,
        "/finance/purchase-requests/create?intake={$intake->id}"
    );
    intakeSmokeAssert($financeCompletion->getStatusCode() === 200, 'Finance could not open the intake completion form.');
    intakeSmokeAssert(
        str_contains((string) $financeCompletion->getContent(), 'Complete Assistant PR Intake')
            && str_contains((string) $financeCompletion->getContent(), 'name="purchase_request_intake_id"')
            && str_contains((string) $financeCompletion->getContent(), (string) $intake->id),
        'Finance completion form did not retain the source intake identity.'
    );

    $funding = ProgramFunding::query()
        ->where('status', 'approved')
        ->firstOrFail();
    $resource = Resource::query()
        ->where('status', 'active')
        ->whereHas('category', fn ($query) => $query->where('status', 'active'))
        ->firstOrFail();
    $allocationCandidates = DB::table('myb_sub_activity_allocations as allocations')
        ->join('myb_sub_activities as sub_activities', 'sub_activities.id', '=', 'allocations.sub_activity_id')
        ->join('myb_activities as activities', 'activities.id', '=', 'sub_activities.activity_id')
        ->join('myb_projects as projects', 'projects.id', '=', 'activities.project_id')
        ->where('projects.program_id', $funding->program_id)
        ->where('allocations.year', '>=', now()->year)
        ->groupBy('allocations.sub_activity_id', 'allocations.year')
        ->orderBy('allocations.year')
        ->select([
            'allocations.sub_activity_id',
            'allocations.year',
            DB::raw('SUM(allocations.amount) as allocated_amount'),
        ])
        ->get();

    $conversionAmount = 10.00;
    $allocation = $allocationCandidates->first(function ($candidate) use ($funding, $conversionAmount): bool {
        $committed = BudgetCommitment::query()
            ->where('allocation_level', 'sub_activity')
            ->where('allocation_id', $candidate->sub_activity_id)
            ->where('commitment_year', (int) $candidate->year)
            ->whereIn('status', [
                BudgetCommitment::STATUS_DRAFT,
                BudgetCommitment::STATUS_SUBMITTED,
                BudgetCommitment::STATUS_APPROVED,
            ])
            ->when(
                filled($funding->governance_node_id),
                fn ($query) => $query->where('governance_node_id', $funding->governance_node_id)
            )
            ->sum('commitment_amount');

        return (float) $candidate->allocated_amount - (float) $committed >= $conversionAmount;
    });
    intakeSmokeAssert($allocation !== null, 'No funded sub-activity was available to verify Finance intake conversion.');
    intakeSmokeAssert(
        SubActivity::query()->whereKey($allocation->sub_activity_id)->exists(),
        'The selected intake conversion allocation does not resolve to a sub-activity.'
    );

    $conversionPayload = [
        '_token' => $administratorToken,
        'purchase_request_intake_id' => $intake->id,
        'program_funding_id' => $funding->id,
        'allocation_level' => 'sub_activity',
        'commitment_year' => (string) $allocation->year,
        'allocation_id' => $allocation->sub_activity_id,
        'description' => 'Finance-completed purchase request for '.$intake->reference_no,
        'delivery_date' => now()->addDays(21)->toDateString(),
        'items' => [[
            'resource_category_id' => $resource->resource_category_id,
            'resource_id' => $resource->id,
            'unit_price' => number_format($conversionAmount, 2, '.', ''),
            'quantity' => '1',
            'amount' => number_format($conversionAmount, 2, '.', ''),
            'milestone' => 'Assistant intake conversion smoke',
            'milestone_date' => now()->addDays(21)->toDateString(),
        ]],
        'pr_attachment_types' => ['fund_availability', 'tors'],
        'pr_attachment_titles' => ['Fund Availability', 'Terms of Reference'],
    ];
    $purchaseRequestCountBeforeConversion = PurchaseRequest::query()->count();
    $conversionResponse = intakeSmokeRequest(
        $http,
        $administratorSession,
        '/finance/purchase-requests',
        'POST',
        $conversionPayload,
        ['pr_attachments' => [
            UploadedFile::fake()->create('fund-availability.pdf', 4, 'application/pdf'),
            UploadedFile::fake()->create('terms-of-reference.pdf', 4, 'application/pdf'),
        ]]
    );
    intakeSmokeAssert(
        in_array($conversionResponse->getStatusCode(), [302, 303], true),
        'Finance did not convert the Assistant intake into a formal purchase request.'
    );

    $intake->refresh();
    $convertedPurchaseRequest = PurchaseRequest::query()
        ->with(['items', 'commitments', 'attachments'])
        ->findOrFail($intake->converted_purchase_request_id);
    intakeSmokeAssert(
        $intake->status === PurchaseRequestIntake::STATUS_CONVERTED
            && (string) $intake->converted_by === (string) $administrator->id
            && $intake->converted_at !== null,
        'Finance conversion did not close and attribute the source Assistant intake.'
    );
    intakeSmokeAssert(
        PurchaseRequest::query()->count() === $purchaseRequestCountBeforeConversion + 1
            && $convertedPurchaseRequest->status === 'draft'
            && (string) $convertedPurchaseRequest->created_by === (string) $administrator->id
            && $convertedPurchaseRequest->items->count() === 1
            && $convertedPurchaseRequest->commitments->isNotEmpty()
            && $convertedPurchaseRequest->commitments->every(fn ($commitment) => $commitment->status === BudgetCommitment::STATUS_DRAFT)
            && round((float) $convertedPurchaseRequest->commitments->sum('commitment_amount'), 2) === $conversionAmount
            && $convertedPurchaseRequest->attachments->pluck('document_type')->sort()->values()->all() === ['fund_availability', 'tors'],
        'Converted PR, coded item, required documents, or draft budget commitments are incomplete.'
    );

    $formalPurchaseRequestPage = intakeSmokeRequest(
        $http,
        $administratorSession,
        "/finance/purchase-requests/{$convertedPurchaseRequest->id}"
    );
    intakeSmokeAssert(
        $formalPurchaseRequestPage->getStatusCode() === 200
            && str_contains((string) $formalPurchaseRequestPage->getContent(), $intake->reference_no)
            && str_contains((string) $formalPurchaseRequestPage->getContent(), 'Original supporting documents')
            && str_contains((string) $formalPurchaseRequestPage->getContent(), 'intake-request.pdf'),
        'The converted PR lost its Assistant source or original supporting-document links.'
    );

    $financeRegisterAfterConversion = intakeSmokeRequest(
        $http,
        $administratorSession,
        '/finance/purchase-requests'
    );
    intakeSmokeAssert(
        $financeRegisterAfterConversion->getStatusCode() === 200
            && str_contains((string) $financeRegisterAfterConversion->getContent(), $convertedPurchaseRequest->reference_no)
            && str_contains((string) $financeRegisterAfterConversion->getContent(), 'Administrative Assistant')
            && str_contains((string) $financeRegisterAfterConversion->getContent(), $assistant->name),
        'The formal Finance register did not retain the originating Assistant identity.'
    );

    $duplicateConversion = intakeSmokeRequest(
        $http,
        $administratorSession,
        '/finance/purchase-requests',
        'POST',
        $conversionPayload,
        ['pr_attachments' => [
            UploadedFile::fake()->create('duplicate-fund-availability.pdf', 4, 'application/pdf'),
            UploadedFile::fake()->create('duplicate-terms-of-reference.pdf', 4, 'application/pdf'),
        ]]
    );
    intakeSmokeAssert(
        in_array($duplicateConversion->getStatusCode(), [302, 303], true)
            && PurchaseRequest::query()->count() === $purchaseRequestCountBeforeConversion + 1,
        'The same Assistant intake could be converted into more than one formal PR.'
    );

    $tamperedFinanceDownload = intakeSmokeRequest(
        $http,
        $administratorSession,
        "/finance/purchase-request-intakes/{$intake->id}/documents/{$tamperedDocument->id}"
    );
    intakeSmokeAssert(
        $tamperedFinanceDownload->getStatusCode() === 404,
        'Finance document download served a database path outside the intake private directory.'
    );

    [$assistantSession, $assistantToken] = intakeSmokeSession($app, $assistant);
    $blockedFinance = intakeSmokeRequest($http, $assistantSession, '/finance/purchase-requests');
    intakeSmokeAssert(
        in_array($blockedFinance->getStatusCode(), [302, 303], true)
            && str_contains((string) $blockedFinance->headers->get('Location'), '/administrative-assistant'),
        'Administrative Assistant retained direct access to the Finance purchase request workspace.'
    );

    // Force a database failure after two files have been written. The intake,
    // its first document row, and both private files must all be rolled back.
    $intakeCountBeforeFailure = PurchaseRequestIntake::query()->count();
    $filesBeforeFailure = Storage::disk('local')->allFiles();
    sort($filesBeforeFailure);
    $documentCreateAttempts = 0;
    PurchaseRequestIntakeDocument::creating(function () use (&$documentCreateAttempts): void {
        $documentCreateAttempts++;
        if ($documentCreateAttempts === 2) {
            throw new RuntimeException('Forced intake document persistence failure.');
        }
    });
    $documentFailureListenerRegistered = true;
    $atomicMarker = 'Atomic rollback '.Str::upper(Str::random(8));

    $atomicFailure = intakeSmokeRequest(
        $http,
        $assistantSession,
        '/administrative-assistant/purchase-requests',
        'POST',
        [
            '_token' => $assistantToken,
            'title' => $atomicMarker,
            'description' => 'This request must roll back completely when document persistence fails.',
            'priority' => 'normal',
            'currency' => 'USD',
            'items' => [
                ['name' => 'Rollback probe', 'quantity' => '1', 'notes' => null],
            ],
        ],
        [
            'documents' => [
                UploadedFile::fake()->create('atomic-one.pdf', 4, 'application/pdf'),
                UploadedFile::fake()->create('atomic-two.pdf', 4, 'application/pdf'),
            ],
        ]
    );
    intakeSmokeAssert($atomicFailure->getStatusCode() === 500, 'Forced intake persistence failure did not surface as a failed request.');

    $filesAfterFailure = Storage::disk('local')->allFiles();
    sort($filesAfterFailure);
    intakeSmokeAssert(
        PurchaseRequestIntake::query()->count() === $intakeCountBeforeFailure
            && ! PurchaseRequestIntake::query()->where('title', $atomicMarker)->exists()
            && $filesAfterFailure === $filesBeforeFailure,
        'Failed intake persistence left a partial database row or orphaned private file.'
    );

    echo "ADMINISTRATIVE_ASSISTANT_PURCHASE_REQUEST_INTAKE_OK\n";
} finally {
    if ($documentFailureListenerRegistered) {
        PurchaseRequestIntakeDocument::flushEventListeners();
    }
    DB::rollBack();
}

/**
 * @return array{0: mixed, 1: string}
 */
function intakeSmokeSession($app, User $user): array
{
    Auth::logout();
    $session = $app['session.store'];

    if ($session->isStarted()) {
        $session->save();
    }

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

    return [$session, $token];
}

function intakeSmokeRequest(
    HttpKernel $http,
    $session,
    string $uri,
    string $method = 'GET',
    array $parameters = [],
    array $files = []
) {
    $request = Request::create($uri, $method, $parameters, [], $files, [
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
    ]);
    $request->setLaravelSession($session);

    return $http->handle($request);
}

function intakeSmokeAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
