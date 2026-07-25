<?php

use App\Models\BudgetCommitment;
use App\Models\Procurement;
use App\Models\ProcurementDocument;
use App\Models\ProcurementMethodPlanned;
use App\Models\ProcurementPlan;
use App\Models\ProcurementProgramPlan;
use App\Models\Resource;
use App\Models\SubActivity;
use App\Models\User;
use App\Models\VendorCategory;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require __DIR__ . '/../../vendor/autoload.php';

$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$http = $app->make(HttpKernel::class);

$admin = User::query()->where('user_type', 'admin')->firstOrFail();
$method = ProcurementMethodPlanned::query()->where('is_active', true)->firstOrFail();
$resource = Resource::query()->firstOrFail();

$subActivity = SubActivity::query()
    ->with('activity.project')
    ->whereExists(function ($query) {
        $query->selectRaw('1')
            ->from('myb_budget_commitments as commitments')
            ->whereColumn('commitments.allocation_id', 'myb_sub_activities.id')
            ->where('commitments.allocation_level', 'sub_activity')
            ->where('commitments.status', BudgetCommitment::STATUS_APPROVED);
    })
    ->get()
    ->first(function (SubActivity $candidate) {
        return (bool) (
            $candidate->governance_node_id
            ?? $candidate->activity?->governance_node_id
            ?? $candidate->activity?->project?->governance_node_id
        );
    });

if (! $subActivity || ! $subActivity->activity) {
    fwrite(STDERR, "No approved committed sub-activity with a portfolio is available for the smoke test.\n");
    exit(1);
}

$nodeId = $subActivity->governance_node_id
    ?? $subActivity->activity->governance_node_id
    ?? $subActivity->activity->project?->governance_node_id;

Auth::login($admin);
$session = $app['session.store'];
$session->start();
$token = bin2hex(random_bytes(20));
$session->put('_token', $token);
$session->save();

$storedDirectory = null;
DB::beginTransaction();

try {
    foreach ([
        '/procurement/structure' => 'My Procurement Plan',
        '/procurement/plans/create' => 'Create Procurement Plan Item',
        '/procurements/create' => 'Procurement Documents',
    ] as $path => $needle) {
        $response = procurementSmokeRequest($http, $session, $path);
        procurementSmokeAssert($response->getStatusCode() === 200, "{$path} returned {$response->getStatusCode()}.");
        procurementSmokeAssert(str_contains((string) $response->getContent(), $needle), "{$path} is missing {$needle}.");
    }

    $suffix = bin2hex(random_bytes(4));
    $programPlanName = "Procurement Workflow Smoke {$suffix}";

    $response = procurementSmokeRequest($http, $session, '/procurement/structure', 'POST', [
        '_token' => $token,
        'governance_node_id' => $nodeId,
        'name' => $programPlanName,
        'description' => 'Original smoke-test structure description.',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addYear()->toDateString(),
        'is_active' => '1',
    ]);
    procurementSmokeAssert(
        in_array($response->getStatusCode(), [302, 303], true),
        'Plan structure was not saved. Status ' . $response->getStatusCode()
            . ': ' . substr(strip_tags((string) $response->getContent()), 0, 500)
    );

    $programPlan = ProcurementProgramPlan::query()->where('name', $programPlanName)->first();
    procurementSmokeAssert((bool) $programPlan, 'Saved plan structure could not be found.');

    $response = procurementSmokeRequest(
        $http,
        $session,
        "/procurement/structure/{$programPlan->id}",
        'POST',
        [
            '_token' => $token,
            '_method' => 'PUT',
            'governance_node_id' => $nodeId,
            'name' => $programPlanName,
            'description' => 'Updated smoke-test structure description.',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'is_active' => '1',
        ]
    );
    procurementSmokeAssert(in_array($response->getStatusCode(), [302, 303], true), 'Plan structure was not updated.');
    procurementSmokeAssert(
        $programPlan->fresh()->description === 'Updated smoke-test structure description.',
        'Plan structure edit did not persist.'
    );

    $code = ProcurementPlan::generateCode();
    $planPayload = [
        '_token' => $token,
        'procurement_code' => $code,
        'is_code_auto_generated' => '1',
        'title' => "Procurement Plan Item {$suffix}",
        'activity_id' => $subActivity->activity_id,
        'sub_activity_id' => $subActivity->id,
        'method_planned_id' => $method->id,
        'program_plan_id' => $programPlan->id,
        'is_launched' => '0',
        'estimated_start_date' => now()->addDays(5)->toDateString(),
        'estimated_budget' => '125000.50',
        'currency' => 'usd',
        'fiscal_year' => (string) now()->year,
    ];

    $response = procurementSmokeRequest($http, $session, '/procurement/plans', 'POST', $planPayload);
    procurementSmokeAssert(in_array($response->getStatusCode(), [302, 303], true), 'Procurement plan item was not saved.');

    $plan = ProcurementPlan::query()->where('procurement_code', $code)->first();
    procurementSmokeAssert((bool) $plan, 'Saved procurement plan item could not be found.');
    procurementSmokeAssert($plan->currency === 'USD', 'Plan-item currency was not normalized.');
    procurementSmokeAssert((string) $plan->program_plan_id === (string) $programPlan->id, 'Plan item lost its sheet.');

    $planPayload['_method'] = 'PUT';
    $planPayload['title'] = "Updated Procurement Plan Item {$suffix}";
    $response = procurementSmokeRequest(
        $http,
        $session,
        "/procurement/plans/{$plan->id}",
        'POST',
        $planPayload
    );
    procurementSmokeAssert(in_array($response->getStatusCode(), [302, 303], true), 'Procurement plan item was not updated.');
    procurementSmokeAssert(
        $plan->fresh()->title === "Updated Procurement Plan Item {$suffix}",
        'Plan-item edit did not persist.'
    );

    $response = procurementSmokeRequest(
        $http,
        $session,
        "/procurement/plans/sub-activities/{$subActivity->activity_id}"
    );
    procurementSmokeAssert($response->getStatusCode() === 200, 'Sub-activity lookup failed.');
    procurementSmokeAssert(
        collect(json_decode((string) $response->getContent(), true))->contains('id', $subActivity->id),
        'Approved committed sub-activity was missing from the lookup.'
    );

    $upload = UploadedFile::fake()->create('terms-of-reference.pdf', 8, 'application/pdf');
    $procurementTitle = "Procurement Document Smoke {$suffix}";
    $parameters = [
        '_token' => $token,
        'resource_id' => $resource->id,
        'title' => $procurementTitle,
        'description' => 'Procurement document upload and download workflow test.',
        'fiscal_year' => (string) now()->year,
        'application_start_date' => now()->subDay()->toDateString(),
        'application_duration_days' => '30',
        'visibility_type' => 'public',
        'reference_no' => '',
        'estimated_budget' => '5000',
        'documents' => [
            ['name' => 'Terms of Reference'],
        ],
    ];
    $files = [
        'documents' => [
            ['file' => $upload],
        ],
    ];

    $response = procurementSmokeRequest($http, $session, '/procurements', 'POST', $parameters, $files);
    procurementSmokeAssert(in_array($response->getStatusCode(), [302, 303], true), 'Procurement with document was not saved.');

    $procurement = Procurement::query()->where('title', $procurementTitle)->first();
    procurementSmokeAssert((bool) $procurement, 'Saved procurement could not be found.');
    $storedDirectory = "procurements/{$procurement->id}";

    $document = ProcurementDocument::query()->where('procurement_id', $procurement->id)->first();
    procurementSmokeAssert((bool) $document, 'Uploaded procurement document metadata was not saved.');
    procurementSmokeAssert(Storage::disk('local')->exists($document->file_path), 'Uploaded procurement document file is missing.');

    $procurement->update(['status' => 'published']);
    $publicPage = procurementSmokeRequest($http, $session, "/public/procurement/{$procurement->slug}");
    procurementSmokeAssert($publicPage->getStatusCode() === 200, 'Public procurement page failed.');
    procurementSmokeAssert(
        str_contains((string) $publicPage->getContent(), 'Terms of Reference'),
        'Public procurement page does not list the document.'
    );

    $publicDownload = procurementSmokeRequest(
        $http,
        $session,
        "/public/procurement/{$procurement->slug}/documents/{$document->id}/download"
    );
    procurementSmokeAssert($publicDownload->getStatusCode() === 200, 'Public document download failed.');

    $vendorCategory = VendorCategory::query()->where('is_active', true)->first()
        ?? VendorCategory::create([
            'name' => "Smoke Vendors {$suffix}",
            'description' => 'Temporary smoke-test category.',
            'is_active' => true,
            'created_by' => $admin->id,
        ]);
    $vendor = User::query()->where('user_type', 'vendor')->firstOrFail();
    $vendor->forceFill([
        'vendor_category' => $vendorCategory->name,
        'is_disabled' => false,
        'is_blacklisted' => false,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
    ])->save();
    $procurement->update([
        'visibility_type' => 'vendor_group',
        'vendor_categories' => [$vendorCategory->name],
        'status' => 'published',
    ]);

    $blockedPublicDownload = procurementSmokeRequest(
        $http,
        $session,
        "/public/procurement/{$procurement->slug}/documents/{$document->id}/download"
    );
    procurementSmokeAssert(
        $blockedPublicDownload->getStatusCode() === 404,
        'A vendor-only procurement document was exposed through the public endpoint.'
    );

    Auth::login($vendor);
    $vendorSession = $app['session.store'];
    $vendorSession->start();
    $vendorSession->put('otp_verified', true);
    $vendorSession->put('otp_verified_user_id', (string) $vendor->id);
    $vendorSession->put('otp_verified_at', now()->toIso8601String());
    $vendorSession->save();

    $vendorPage = procurementSmokeRequest(
        $http,
        $vendorSession,
        "/vendor/procurements/{$procurement->slug}"
    );
    procurementSmokeAssert(
        $vendorPage->getStatusCode() === 200,
        'Authorized vendor procurement page failed with status ' . $vendorPage->getStatusCode()
            . ': ' . substr(strip_tags((string) $vendorPage->getContent()), 0, 500)
    );
    procurementSmokeAssert(
        str_contains((string) $vendorPage->getContent(), 'Terms of Reference'),
        'Vendor procurement page does not list the document.'
    );

    $vendorDownload = procurementSmokeRequest(
        $http,
        $vendorSession,
        "/vendor/procurements/{$procurement->slug}/documents/{$document->id}/download"
    );
    procurementSmokeAssert($vendorDownload->getStatusCode() === 200, 'Authorized vendor document download failed.');

    echo "PROCUREMENT_PLANNING_DOCUMENTS_OK\n";
} finally {
    if ($storedDirectory) {
        Storage::disk('local')->deleteDirectory($storedDirectory);
    }

    DB::rollBack();
}

function procurementSmokeRequest(
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

function procurementSmokeAssert(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
