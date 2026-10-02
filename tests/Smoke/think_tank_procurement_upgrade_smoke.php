<?php

use App\Mail\VendorApplicationReceived;
use App\Mail\VendorProcurementLifecycleMail;
use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\FormSubmissionValue;
use App\Models\Procurement;
use App\Models\Role;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use App\Services\ThinkTank\ThinkTankSessionService;
use App\Services\ThinkTankProcurementWorkflowService;
use App\Support\DynamicProcurementFormCatalog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
(new PHPUnit\TextUI\Configuration\Builder)->build(['phpunit']);

class ThinkTankProcurementUpgradeBrowser
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function postAs(User $user, string $uri, array $data)
    {
        $token = Str::random(40);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user);
        $sessionData = [
            '_token' => $token,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ];
        if ($user->user_type === 'think_tank') {
            $session = $this->app->make('session.store');
            $request = Request::create('/');
            $request->setLaravelSession($session);
            app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
            $sessionData['think_tank_security_stamp'] = $session->get('think_tank_security_stamp');
        }
        $this->withSession($sessionData);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    public function getAs(User $user, string $uri)
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user);
        $sessionData = [
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ];
        if ($user->user_type === 'think_tank') {
            $session = $this->app->make('session.store');
            $request = Request::create('/');
            $request->setLaravelSession($session);
            app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
            $sessionData['think_tank_security_stamp'] = $session->get('think_tank_security_stamp');
        }
        $this->withSession($sessionData);

        return $this->get($uri);
    }

    public function deleteAs(User $user, string $uri, array $data = [])
    {
        $token = Str::random(40);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user);
        $this->withSession([
            '_token' => $token,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this->delete($uri, ['_token' => $token, ...$data]);
    }

    public function apiGetAs(User $user, string $uri)
    {
        $this->asThinkTankApi($user);

        return $this->getJson($uri);
    }

    public function getJsonAs(User $user, string $uri)
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user);
        $this->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);
        $this->withHeaders(['Accept' => 'application/json']);

        return $this->getJson($uri);
    }

    public function apiPostJsonAs(User $user, string $uri, array $data)
    {
        $this->asThinkTankApi($user);

        return $this->postJson($uri, $data);
    }

    public function apiPutJsonAs(User $user, string $uri, array $data)
    {
        $this->asThinkTankApi($user);

        return $this->putJson($uri, $data);
    }

    public function apiMultipartAs(User $user, string $uri, array $data, array $files = [])
    {
        $token = $this->asThinkTankApi($user);

        return $this->call('POST', $uri, $data, [], $files, $this->transformHeadersToServerVars([
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:3100',
            'Referer' => 'http://localhost:3100/',
            'X-CSRF-TOKEN' => $token,
            'Content-Type' => 'multipart/form-data; boundary=attp-upgrade-smoke',
        ]));
    }

    private function asThinkTankApi(User $user): string
    {
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->actingAs($user);
        $session = $this->app->make('session.store');
        $request = Request::create('/');
        $request->setLaravelSession($session);
        app(ThinkTankSessionService::class)->bindCurrentSession($user, $request);
        $token = Str::random(40);
        $this->withSession([
            '_token' => $token,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
            'think_tank_security_stamp' => $session->get('think_tank_security_stamp'),
        ]);
        $this->withHeaders([
            'Accept' => 'application/json',
            'Origin' => 'http://localhost:3100',
            'Referer' => 'http://localhost:3100/',
            'X-CSRF-TOKEN' => $token,
        ]);

        return $token;
    }
}

$ensure = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

Mail::fake();
Storage::fake('local');
Storage::fake('public');
DB::beginTransaction();

try {
    $member = ConsortiumThinkTank::query()->whereNotNull('consortium_id')->firstOrFail();
    $actor = User::query()->create([
        'name' => 'Procurement Upgrade Smoke Administrator',
        'email' => 'procurement-smoke-'.Str::lower(Str::random(12)).'@example.test',
        'password' => 'Password123!',
        'user_type' => 'admin',
        'role_id' => Role::query()->where('name', 'System Admin')->firstOrFail()->id,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $executionActor = User::query()->create([
        'name' => 'Procurement Upgrade Think Tank Administrator',
        'email' => 'procurement-execution-smoke-'.Str::lower(Str::random(12)).'@example.test',
        'password' => 'Password123!',
        'user_type' => 'think_tank',
        'role_id' => Role::query()->where('name', 'Think Tank User')->firstOrFail()->id,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ADMIN,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $workflow = app(ThinkTankProcurementWorkflowService::class);
    $token = Str::upper(Str::random(10));

    $plan = ThinkTankProcurementPlan::query()->create([
        'consortium_id' => $member->consortium_id,
        'think_tank_member_id' => $member->id,
        'plan_code' => 'SMOKE-'.$token,
        'title' => 'Procurement upgrade smoke plan',
        'fiscal_year' => '2026/27',
        'estimated_budget' => 3000,
        'currency' => 'USD',
        'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
        'version' => 1,
        'created_by' => $actor->id,
    ]);

    $items = collect([1000, 2000])->map(function (int $amount, int $index) use ($plan, $actor, $token) {
        $item = $plan->items()->create([
            'item_code' => "SMOKE-{$token}-".($index + 1),
            'title' => 'Smoke procurement item '.($index + 1),
            'procurement_category' => 'consulting_services',
            'procurement_method' => 'QCBS',
            'estimated_amount' => $amount,
            'currency' => 'USD',
            'status' => ThinkTankProcurementItem::STATUS_DRAFT,
            'created_by' => $actor->id,
            'updated_by' => $actor->id,
        ]);
        $item->documents()->create([
            'document_type' => 'tor',
            'document_name' => 'Terms of Reference',
            'original_name' => 'tor-'.$index.'.pdf',
            'file_path' => "think-tank-procurement/{$plan->id}/{$item->id}/tor-{$index}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'uploaded_by' => $actor->id,
        ]);
        Storage::disk('local')->put("think-tank-procurement/{$plan->id}/{$item->id}/tor-{$index}.pdf", '%PDF smoke TOR');

        return $item;
    });

    $workflow->submit($plan, $actor);
    $ensure($plan->fresh()->status === ThinkTankProcurementPlan::STATUS_SUBMITTED, 'Plan was not submitted.');
    $ensure($plan->items()->where('status', ThinkTankProcurementItem::STATUS_SUBMITTED)->count() === 2, 'Items were not submitted.');
    $ensure($items[0]->fresh()->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_SUBMITTED, 'Submitted activity label was not synchronized.');

    $workflow->reviewItem($items[0]->fresh(), $actor, 'revision_requested', 'Clarify the deliverables.');
    $ensure($plan->fresh()->status === ThinkTankProcurementPlan::STATUS_REVISION_REQUESTED, 'Partial return did not update the plan.');
    $ensure($items[0]->fresh()->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_DRAFT, 'Returned activity did not return to Draft.');

    $approvalBlocked = false;
    try {
        $workflow->decidePlan($plan->fresh(), $actor, 'approve', null);
    } catch (ValidationException) {
        $approvalBlocked = true;
    }
    $ensure($approvalBlocked, 'A plan with an unresolved returned item was incorrectly approved.');

    $workflow->reviewItem($items[1]->fresh(), $actor, 'approve', null);
    $ensure($items[1]->fresh()->status === ThinkTankProcurementItem::STATUS_APPROVED, 'The second item could not be reviewed after a partial return.');
    $ensure($items[1]->fresh()->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_ATTP_APPROVED, 'ATTP approval activity label was not synchronized.');

    $items[0]->update([
        'status' => ThinkTankProcurementItem::STATUS_DRAFT,
        'review_reason' => null,
        'updated_by' => $actor->id,
    ]);
    $workflow->submit($plan->fresh(), $actor);
    $ensure($plan->fresh()->version === 2, 'Plan resubmission did not increment the version.');
    $ensure($items[1]->fresh()->status === ThinkTankProcurementItem::STATUS_APPROVED, 'Previously approved item was not retained.');

    $workflow->decidePlan($plan->fresh(), $actor, 'approve', null);
    $ensure($plan->fresh()->status === ThinkTankProcurementPlan::STATUS_APPROVED, 'Corrected plan was not approved.');
    $ensure($plan->items()->where('status', ThinkTankProcurementItem::STATUS_APPROVED)->count() === 2, 'All corrected items were not approved.');
    $ensure($items[0]->fresh()->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_ATTP_APPROVED, 'Plan approval did not apply the ATTP Secretariat activity label.');

    $workflow->recordNoObjection($items[0]->fresh(), $actor, [
        'step_reference' => 'STEP-'.$token,
        'no_objection_reference' => 'TTL-'.$token,
        'no_objection_date' => now()->toDateString(),
        'no_objection_notes' => 'No objection recorded by the smoke test.',
    ]);
    $ensure($items[0]->fresh()->status === ThinkTankProcurementItem::STATUS_NO_OBJECTION, 'No-objection state was not recorded.');
    $ensure($items[0]->fresh()->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED, 'World Bank no-objection activity label was not synchronized.');

    $browser = new ThinkTankProcurementUpgradeBrowser($app);
    $calculationPlan = ThinkTankProcurementPlan::query()->create([
        'consortium_id' => $member->consortium_id,
        'think_tank_member_id' => $member->id,
        'plan_code' => 'CALC-'.$token,
        'title' => 'Automatic amount calculation smoke plan',
        'fiscal_year' => 'CALC-'.$token,
        'estimated_budget' => 0,
        'currency' => 'USD',
        'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
        'version' => 1,
        'created_by' => $actor->id,
    ]);
    $browser->postAs($actor, route('think-tank.procurement-plans.items.store', $calculationPlan), [
        'title' => 'Automatically calculated procurement item',
        'procurement_category' => 'goods',
        'procurement_method' => 'Request for Quotations (RFQ)',
        'market_approach' => 'Limited - National',
        'review_type' => 'Post',
        'source_document_type' => 'Request for Quotations (Non Bank-SPD)',
        'source_process_status' => 'Pending Implementation',
        'quantity' => 3,
        'unit' => 'units',
        'estimated_unit_cost' => 12.34,
        'estimated_amount' => 99999,
        'currency' => 'USD',
        'tor' => UploadedFile::fake()->create('calculation-tor.pdf', 12, 'application/pdf'),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $calculatedItem = $calculationPlan->items()->firstOrFail();
    $ensure((float) $calculatedItem->estimated_amount === 37.02, 'Estimated amount was not calculated from quantity and unit cost on the server.');

    $browser->getAs($actor, route('think-tank.procurement-plans.show', $plan))
        ->assertOk()
        ->assertSee('Open Procurement Execution')
        ->assertSee('/procurement/executions/create?item='.urlencode((string) $items[0]->id), false);

    $browser->postAs($actor, route('think-tank.procurement-plans.items.launch', [$plan, $items[0]]), [
        'application_start_date' => now()->toDateString(),
        'application_end_date' => now()->addDays(21)->toDateString(),
        'visibility_type' => 'public',
        'publish_now' => '1',
    ])->assertStatus(410);

    $eligibleResponse = $browser->apiGetAs($executionActor, '/api/v1/think-tank/procurement/executions/eligible-items')
        ->assertOk();
    $eligibleItem = collect((array) $eligibleResponse->json('data.items'))
        ->firstWhere('id', (string) $items[0]->id);
    $ensure(is_array($eligibleItem), 'The no-objection item did not enter the staged execution queue.');

    $draftResponse = $browser->apiMultipartAs($executionActor, '/api/v1/think-tank/procurement/executions', [
        'item_id' => (string) $items[0]->id,
        'lock_token' => $eligibleItem['lockToken'],
        'application_start_date' => now()->toDateString(),
        'application_end_date' => now()->addDays(21)->toDateString(),
        'visibility_type' => 'public',
    ], [
        'cover_image' => UploadedFile::fake()->image('opportunity-cover.jpg', 1200, 675),
    ])->assertCreated();
    $ensure($draftResponse->json('data.status') === 'draft', 'Staged execution creation skipped the required draft state.');
    $executionId = (string) $draftResponse->json('data.id');
    $draftForm = DynamicForm::query()->where('procurement_id', $executionId)->firstOrFail();
    $draftProcurement = Procurement::query()->findOrFail($executionId);
    $draftCustomField = $draftForm->fields()->whereNotIn('field_key', DynamicForm::globalFieldKeys())->firstOrFail();
    $browser->getAs($actor, route('forms.edit', $draftForm))->assertForbidden();
    $browser->postAs($actor, route('forms.fields.store', $draftForm), [
        'label' => 'Legacy route injection',
        'field_type' => 'text',
    ])->assertForbidden();
    $browser->deleteAs($actor, route('forms.fields.destroy', $draftCustomField))->assertForbidden();
    $browser->postAs($actor, route('forms.submit', $draftForm), [])->assertForbidden();
    $browser->postAs($actor, route('forms.approve', $draftForm), [])->assertForbidden();
    $browser->postAs($actor, route('forms.reject', $draftForm), [
        'rejection_reason' => 'This legacy workflow must not control a tenant form.',
    ])->assertForbidden();
    $browser->deleteAs($actor, route('forms.destroy', $draftForm))->assertForbidden();
    $browser->getAs($actor, route('submissions.create', $draftForm))->assertForbidden();
    $browser->postAs($actor, route('submissions.store', $draftForm), [
        'official_name' => 'Injected applicant',
        'official_email' => 'injected@example.test',
    ])->assertForbidden();
    $browser->postAs($actor, route('attach-form'), [
        'form_id' => $draftForm->id,
        'procurement_id' => $draftProcurement->id,
    ])->assertForbidden();
    $assignmentCreateBlocked = false;
    try {
        app(\App\Http\Controllers\Procurement\ProcurementFormAssignmentController::class)
            ->create($draftProcurement);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        $assignmentCreateBlocked = $exception->getStatusCode() === 403;
    }
    $ensure($assignmentCreateBlocked, 'The legacy form-assignment display exposed a Think Tank execution.');
    $assignmentStoreBlocked = false;
    try {
        $assignmentRequest = Request::create('/procurement/forms/forms/attach', 'POST', [
            'form_id' => $draftForm->id,
            'stage' => 'submission',
        ]);
        $assignmentRequest->setUserResolver(fn () => $actor);
        app(\App\Http\Controllers\Procurement\ProcurementFormAssignmentController::class)
            ->store($assignmentRequest, $draftProcurement);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        $assignmentStoreBlocked = $exception->getStatusCode() === 403;
    }
    $ensure($assignmentStoreBlocked, 'The legacy form-assignment mutation accepted a Think Tank execution.');
    $ensure(! $draftForm->submissions()->exists(), 'A legacy form route injected a submission into the Think Tank draft.');

    $draftForm->fields()->where('field_key', 'official_name')->update([
        'help_text' => 'Corrupt legacy help',
        'placeholder' => 'Corrupt legacy placeholder',
        'validation_rules' => ['max_length' => 1],
    ]);

    $browser->apiPutJsonAs(
        $executionActor,
        "/api/v1/think-tank/procurement/executions/{$executionId}/form",
        [
            'lock_token' => $draftResponse->json('data.lockToken'),
            'name' => 'Rejected reserved-key form',
            'fields' => [
                ['key' => 'vendor_response', 'label' => 'Reserved workflow response', 'type' => 'text', 'required' => false],
            ],
        ],
    )->assertUnprocessable()->assertJsonValidationErrors('fields.0.key');

    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected duplicate choices form',
        'fields' => [[
            'key' => 'duplicate_choices', 'label' => 'Duplicate choices', 'type' => 'select',
            'required' => false, 'options' => ['Yes', 'yes'],
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('fields.0.options');

    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected irrelevant validation form',
        'fields' => [[
            'key' => 'event_date', 'label' => 'Event date', 'type' => 'date',
            'required' => false, 'validation' => ['max_length' => 100],
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('fields.0.validation');

    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected irrelevant placeholder form',
        'fields' => [[
            'key' => 'event_time', 'label' => 'Event time', 'type' => 'time',
            'required' => false, 'placeholder' => 'Not rendered by time controls',
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('fields.0.placeholder');

    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected duplicate position form',
        'fields' => [
            ['key' => 'first_field', 'label' => 'First', 'type' => 'text', 'required' => false, 'sort_order' => 110],
            ['key' => 'second_field', 'label' => 'Second', 'type' => 'text', 'required' => false, 'sort_order' => 110],
        ],
    ])->assertUnprocessable()->assertJsonValidationErrors('fields.1.sort_order');

    $tooManyFields = collect(range(1, 31))->map(fn (int $index): array => [
        'key' => "field_{$index}",
        'label' => "Field {$index}",
        'type' => 'text',
        'required' => false,
    ])->all();
    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected oversized form',
        'fields' => $tooManyFields,
    ])->assertUnprocessable()->assertJsonValidationErrors('fields');

    $tooManyUploadFields = collect(range(1, 21))->map(fn (int $index): array => [
        'key' => "upload_{$index}",
        'label' => "Upload {$index}",
        'type' => $index % 2 === 0 ? 'image' : 'file',
        'required' => false,
    ])->all();
    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected upload-heavy form',
        'fields' => $tooManyUploadFields,
    ])->assertUnprocessable()->assertJsonValidationErrors('fields');

    $tooManyTotalOptions = collect(range(1, 11))->map(fn (int $fieldIndex): array => [
        'key' => "large_choice_{$fieldIndex}",
        'label' => "Large choice {$fieldIndex}",
        'type' => 'checkbox',
        'required' => false,
        'options' => collect(range(1, 50))->map(fn (int $optionIndex): string => "Choice {$fieldIndex}-{$optionIndex}")->all(),
    ])->all();
    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected aggregate-option-heavy form',
        'fields' => $tooManyTotalOptions,
    ])->assertUnprocessable()->assertJsonValidationErrors('fields');

    $browser->apiPutJsonAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}/form", [
        'lock_token' => $draftResponse->json('data.lockToken'),
        'name' => 'Rejected generated key form',
        'fields' => [[
            'label' => str_repeat('Long generated key ', 10),
            'type' => 'text',
            'required' => false,
        ]],
    ])->assertUnprocessable()->assertJsonValidationErrors('fields.0.key');

    $formResponse = $browser->apiPutJsonAs(
        $executionActor,
        "/api/v1/think-tank/procurement/executions/{$executionId}/form",
        [
            'lock_token' => $draftResponse->json('data.lockToken'),
            'name' => 'Procurement opportunity application form',
            'fields' => [
                ['key' => 'organization_profile', 'label' => 'Organization Profile', 'type' => 'file', 'required' => true, 'options' => [], 'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'csv', 'txt', 'zip', 'jpg', 'jpeg', 'png', 'webp'], 'max_file_size_mb' => 20], 'sort_order' => 110],
                ['key' => 'technical_proposal', 'label' => 'Technical Proposal', 'type' => 'file', 'required' => true, 'options' => [], 'validation' => ['allowed_extensions' => ['pdf', 'doc', 'docx'], 'max_file_size_mb' => 20], 'sort_order' => 120],
                ['key' => 'financial_proposal', 'label' => 'Financial Proposal', 'type' => 'file', 'required' => true, 'options' => [], 'validation' => ['allowed_extensions' => ['pdf', 'xls', 'xlsx'], 'max_file_size_mb' => 20], 'sort_order' => 130],
                ['key' => 'quoted_amount', 'label' => 'Quoted Amount', 'type' => 'number', 'required' => true, 'options' => [], 'validation' => ['min' => 0], 'sort_order' => 140],
                ['key' => 'relevant_experience', 'label' => 'Relevant Experience', 'type' => 'textarea', 'required' => true, 'options' => [], 'validation' => ['max_length' => 10000], 'sort_order' => 150],
                ['key' => 'custom_reference_1', 'label' => 'Internal reference', 'type' => 'text', 'required' => false, 'options' => [], 'validation' => ['max_length' => 100], 'sort_order' => 160],
                ['key' => 'custom_contact_email_2', 'label' => 'Contact email', 'type' => 'email', 'required' => false, 'options' => [], 'validation' => ['max_length' => 255], 'sort_order' => 170],
                ['key' => 'custom_contact_phone_3', 'label' => 'Contact phone', 'type' => 'tel', 'required' => false, 'options' => [], 'validation' => ['max_length' => 50], 'sort_order' => 180],
                ['key' => 'custom_portfolio_url_4', 'label' => 'Portfolio URL', 'type' => 'url', 'required' => false, 'options' => [], 'validation' => ['max_length' => 500], 'sort_order' => 190],
                ['key' => 'custom_available_date_5', 'label' => 'Available date', 'type' => 'date', 'required' => false, 'options' => [], 'validation' => [], 'sort_order' => 200],
                ['key' => 'custom_contact_time_6', 'label' => 'Preferred contact time', 'type' => 'time', 'required' => false, 'options' => [], 'validation' => [], 'sort_order' => 210],
                ['key' => 'custom_presentation_slot_7', 'label' => 'Presentation slot', 'type' => 'datetime-local', 'required' => false, 'options' => [], 'validation' => [], 'sort_order' => 220],
                ['key' => 'custom_operating_region_8', 'label' => 'Operating region', 'type' => 'select', 'required' => true, 'options' => ['East', 'Washington, D.C.', "Cross-border\nremote"], 'help_text' => 'Choose the primary operating region.', 'validation' => [], 'sort_order' => 230],
                ['key' => 'custom_delivery_model_9', 'label' => 'Delivery model', 'type' => 'radio', 'required' => true, 'options' => ['On-site', 'Hybrid', 'Remote'], 'validation' => [], 'sort_order' => 240],
                ['key' => 'custom_service_areas_10', 'label' => 'Service areas', 'type' => 'multiselect', 'required' => false, 'options' => ['Research', 'Training', 'Advisory'], 'validation' => [], 'sort_order' => 250],
                ['key' => 'custom_certifications_11', 'label' => 'Certifications', 'type' => 'checkbox', 'required' => false, 'options' => ['ISO', 'Local registration', 'Tax clearance'], 'validation' => [], 'sort_order' => 260],
                ['key' => 'custom_declaration_12', 'label' => 'Declaration', 'type' => 'boolean', 'required' => false, 'options' => [], 'placeholder' => 'I confirm this application is accurate.', 'validation' => [], 'sort_order' => 270],
                ['key' => 'custom_portfolio_image_13', 'label' => 'Portfolio image', 'type' => 'image', 'required' => false, 'options' => [], 'validation' => ['allowed_extensions' => ['jpg', 'jpeg', 'png', 'webp'], 'max_file_size_mb' => 4], 'sort_order' => 280],
            ],
        ],
    )->assertOk()
        ->assertJsonCount(16, 'data.formBuilder.fieldTypes')
        ->assertJsonCount(14, 'data.formBuilder.fileTypes')
        ->assertJsonPath('data.formBuilder.limits.maxCustomFields', 30)
        ->assertJsonPath('data.formBuilder.limits.maxUploadFields', 20)
        ->assertJsonPath('data.formBuilder.limits.maxTotalOptions', 500)
        ->assertJsonPath('data.formBuilder.limits.maxSubmissionFiles', 20)
        ->assertJsonPath('data.formBuilder.limits.maxSubmissionUploadMb', 60);

    $savedRegionOptions = collect((array) $formResponse->json('data.form.fields'))
        ->firstWhere('key', 'custom_operating_region_8')['options'] ?? [];
    $ensure(
        $savedRegionOptions === ['East', 'Washington, D.C.', "Cross-border\nremote"],
        'Choice options did not survive the form save/read round trip exactly.',
    );
    $officialNameField = DynamicForm::query()->where('procurement_id', $executionId)->firstOrFail()
        ->fields()->where('field_key', 'official_name')->firstOrFail();
    $ensure(
        $officialNameField->help_text === null
            && $officialNameField->placeholder === null
            && $officialNameField->validation_rules === null,
        'Protected identity field configuration was not restored to its safe invariant.',
    );

    $browser->apiPutJsonAs(
        $executionActor,
        "/api/v1/think-tank/procurement/executions/{$executionId}/form",
        [
            'lock_token' => $draftResponse->json('data.lockToken'),
            'name' => 'Stale overwrite attempt',
            'fields' => [[
                'key' => 'stale_field', 'label' => 'Stale field', 'type' => 'text', 'required' => false,
            ]],
        ],
    )->assertStatus(409)->assertJsonPath('code', 'STALE_WRITE');

    $duplicateForm = DynamicForm::query()->create([
        'name' => 'Conflicting smoke application form',
        'applies_to' => 'procurement',
        'status' => 'approved',
        'is_active' => false,
        'procurement_id' => $executionId,
        'created_by' => $executionActor->id,
    ]);
    $browser->apiGetAs($executionActor, "/api/v1/think-tank/procurement/executions/{$executionId}")
        ->assertStatus(409)
        ->assertJsonPath('code', 'FORM_INTEGRITY_ERROR');
    $duplicateForm->fields()->delete();
    $duplicateForm->delete();

    $browser->apiPostJsonAs(
        $executionActor,
        "/api/v1/think-tank/procurement/executions/{$executionId}/publish",
        ['lock_token' => $formResponse->json('data.lockToken'), 'confirmation' => true],
    )->assertOk()->assertJsonPath('data.status', 'published');

    $publishedItem = $items[0]->fresh();
    $ensure(
        $publishedItem->status === ThinkTankProcurementItem::STATUS_PUBLISHED,
        'No-objection item was not published through the staged execution API.'
    );
    $ensure($publishedItem->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED, 'Publishing changed the World Bank no-objection activity label.');
    $ensure($publishedItem->procurement?->status === 'published', 'Public procurement opportunity was not published.');
    $ensure((bool) $publishedItem->procurement?->cover_image_path, 'The public opportunity cover image was not stored.');
    $ensure(Storage::disk('public')->exists($publishedItem->procurement->cover_image_path), 'The public opportunity cover file is missing.');
    $form = DynamicForm::query()->where('procurement_id', $publishedItem->procurement_id)->first();
    $ensure($form && $form->fields()->count() === 20, 'Default and all supported custom application fields were not created.');
    $ensure($form->fields()->where('field_type', 'image')->exists(), 'The image-upload application question was not stored.');
    $browser->getAs($actor, route('forms.edit', $form))->assertForbidden();
    $browser->postAs($actor, route('forms.fields.store', $form), [
        'label' => 'Published legacy mutation',
        'field_type' => 'text',
    ])->assertForbidden();
    $browser->getAs($actor, route('submissions.create', $form))->assertForbidden();

    $browser->getAs($actor, route('public.procurement.index'))
        ->assertOk()
        ->assertSee($member->name)
        ->assertSee($publishedItem->procurement->title);
    $browser->getAs($actor, route('public.procurement.show', $publishedItem->procurement))
        ->assertOk()
        ->assertSee('Published by '.$member->name)
        ->assertSee('Operating region')
        ->assertSee('East')
        ->assertSee('Washington, D.C.')
        ->assertSee("Cross-border\nremote")
        ->assertSee('Delivery model')
        ->assertSee('Hybrid')
        ->assertSee('Choose one of the answers specified above.')
        ->assertSee('Portfolio image');

    $exportResponse = $browser->postAs($actor, route('think-tank-procurement.step-export'), [
        'selection_mode' => 'explicit',
        'item_ids' => [$publishedItem->id],
        'think_tank_member_id' => $member->id,
        'fiscal_year' => $plan->fiscal_year,
    ]);
    $ensure($exportResponse->getStatusCode() === 200, 'STEP workbook download did not return successfully.');
    $ensure(
        str_contains((string) $exportResponse->headers->get('Content-Disposition'), '.xlsx'),
        'STEP export response is not an XLSX download.'
    );
    $ensure((bool) $publishedItem->fresh()->step_exported_at, 'STEP export was not recorded on the procurement item.');

    $filteredExportResponse = $browser->postAs($actor, route('think-tank-procurement.step-export'), [
        'selection_mode' => 'filtered',
        'think_tank_member_id' => $member->id,
        'fiscal_year' => $plan->fiscal_year,
        'status' => 'published',
        'q' => $publishedItem->item_code,
    ]);
    $ensure($filteredExportResponse->getStatusCode() === 200, 'Filtered STEP workbook download did not return successfully.');
    $ensure(
        str_contains((string) $filteredExportResponse->headers->get('Content-Disposition'), '.xlsx'),
        'Filtered STEP export response is not an XLSX download.'
    );

    foreach (['technical', 'financial'] as $phase) {
        $browser->postAs($actor, route('think-tank.procurement-plans.evaluations.store', [$plan, $publishedItem]), [
            'name' => Str::headline($phase).' evaluation '.$token,
            'evaluation_phase' => $phase,
            'description' => 'Smoke-test '.$phase.' scoring template.',
            'criteria' => [
                ['name' => 'Quality', 'description' => 'Overall evaluated quality.', 'max_score' => 60],
                ['name' => 'Value', 'description' => 'Overall evaluated value.', 'max_score' => 40],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
    $ensure(
        Evaluation::query()->where('procurement_id', $publishedItem->procurement_id)->count() === 2,
        'Technical and financial evaluation templates were not both created.'
    );

    $evaluator = User::query()->create([
        'name' => 'Procurement Upgrade Smoke Evaluator',
        'email' => 'procurement-evaluator-'.Str::lower(Str::random(12)).'@example.test',
        'password' => 'Password123!',
        'user_type' => 'think_tank',
        'role_id' => Role::query()->where('name', 'Think Tank User')->firstOrFail()->id,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $technicalEvaluation = Evaluation::query()
        ->where('procurement_id', $publishedItem->procurement_id)
        ->where('evaluation_phase', 'technical')
        ->firstOrFail();
    $browser->postAs(
        $actor,
        route('think-tank.procurement-plans.evaluations.assign', [$plan, $publishedItem, $technicalEvaluation]),
        ['evaluator_ids' => [$evaluator->id]]
    )->assertRedirect()->assertSessionHasNoErrors();
    $ensure(
        EvaluationAssignment::query()
            ->where('evaluation_id', $technicalEvaluation->id)
            ->where('user_id', $evaluator->id)
            ->exists(),
        'Think Tank evaluation team assignment was not created.'
    );

    $assignedMeEvaluator = User::query()->create([
        'name' => 'Assigned M and E Evaluator '.$token,
        'email' => 'assigned-me-evaluator-'.Str::lower(Str::random(12)).'@example.test',
        'password' => 'Password123!',
        'user_type' => 'think_tank',
        'role_id' => Role::query()->where('name', 'Think Tank User')->firstOrFail()->id,
        'think_tank_member_id' => $member->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_ME,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $browser->postAs(
        $evaluator,
        route('think-tank.procurement-plans.evaluations.assign', [$plan, $publishedItem, $technicalEvaluation]),
        ['evaluator_ids' => [$assignedMeEvaluator->id]]
    )->assertRedirect()->assertSessionHasNoErrors();
    $browser->getAs($assignedMeEvaluator, route('think-tank.evaluations.index'))
        ->assertOk()
        ->assertSee('Evaluation workspace')
        ->assertSee($technicalEvaluation->name);
    $browser->getAs($assignedMeEvaluator, route('think-tank.evaluation-assignments.index'))->assertForbidden();
    $browser->getAs($assignedMeEvaluator, route('think-tank.evaluation-templates.technical'))->assertForbidden();

    $centralTemplateName = 'Central technical template '.$token;
    $browser->postAs($evaluator, route('think-tank.evaluation-templates.technical.store'), [
        'item_id' => $publishedItem->id,
        'name' => $centralTemplateName,
        'description' => 'Created from the centralized Think Tank template library.',
        'criteria' => [
            ['name' => 'Approach', 'description' => 'Technical approach.', 'max_score' => 55],
            ['name' => 'Experience', 'description' => 'Relevant experience.', 'max_score' => 45],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();
    $ensure(
        Evaluation::query()
            ->where('think_tank_member_id', $member->id)
            ->where('evaluation_phase', 'technical')
            ->where('name', $centralTemplateName)
            ->exists(),
        'The centralized technical template page did not create its template.'
    );

    $browser->getAs($evaluator, route('think-tank.procurement-plans.create'))
        ->assertOk()
        ->assertSee('Create an annual procurement plan')
        ->assertSee('Plan information')
        ->assertSee('Recent annual plans');
    $browser->getAs($evaluator, route('think-tank.evaluations.index'))
        ->assertOk()
        ->assertSee('Evaluation workspace')
        ->assertSee($technicalEvaluation->name)
        ->assertSee($publishedItem->procurement->title)
        ->assertSee('No applications are ready');
    $browser->getAs($evaluator, route('think-tank.evaluation-assignments.index'))
        ->assertOk()
        ->assertSee('Assign team members')
        ->assertSee($technicalEvaluation->name)
        ->assertSee($evaluator->name);
    $browser->getAs($evaluator, route('think-tank.evaluation-templates.technical'))
        ->assertOk()
        ->assertSee('Technical evaluation templates')
        ->assertSee($centralTemplateName)
        ->assertSee('Criteria must total exactly 100 points');
    $browser->getAs($evaluator, route('think-tank.evaluation-templates.financial'))
        ->assertOk()
        ->assertSee('Financial evaluation templates')
        ->assertSee('Financial evaluation '.$token);

    $vendorEmail = 'publication-vendor-'.Str::lower(Str::random(10)).'@example.test';
    $browser->postAs($actor, route('public.procurement.apply', $publishedItem->procurement), [
        'official_name' => 'Publication Form Test Vendor',
        'official_email' => $vendorEmail,
        'organization_profile' => UploadedFile::fake()->create('organization-profile.pdf', 24, 'application/pdf'),
        'technical_proposal' => UploadedFile::fake()->create('technical-proposal.pdf', 32, 'application/pdf'),
        'financial_proposal' => UploadedFile::fake()->create('financial-proposal.pdf', 20, 'application/pdf'),
        'quoted_amount' => 14500,
        'relevant_experience' => 'Relevant delivery experience for the published opportunity.',
        'custom_reference_1' => 'SUPPLIER-001',
        'custom_contact_email_2' => $vendorEmail,
        'custom_contact_phone_3' => '+254700000000',
        'custom_portfolio_url_4' => 'https://example.test/portfolio',
        'custom_available_date_5' => now()->addWeek()->toDateString(),
        'custom_contact_time_6' => '09:30',
        'custom_presentation_slot_7' => now()->addWeek()->format('Y-m-d\\TH:i'),
        'custom_operating_region_8' => 'East',
        'custom_delivery_model_9' => 'Hybrid',
        'custom_service_areas_10' => ['Research', 'Advisory'],
        'custom_certifications_11' => ['ISO', 'Tax clearance'],
        'custom_declaration_12' => '1',
        'custom_portfolio_image_13' => UploadedFile::fake()->image('portfolio.png', 800, 500),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $publicVendor = User::query()->where('email', $vendorEmail)->first();
    $publicSubmission = FormSubmission::query()
        ->where('procurement_id', $publishedItem->procurement_id)
        ->where('submitted_by', (string) $publicVendor?->id)
        ->with('values')
        ->first();
    $ensure((bool) $publicSubmission, 'The generated public application form did not accept a submission.');
    // Exercise the authorization boundary after the account-setup middleware has
    // been satisfied; otherwise a newly invited vendor is intentionally sent to
    // password setup before the review-route middleware can return its JSON 403.
    $publicVendor->forceFill([
        'must_change_password' => false,
        'password_changed_at' => now(),
    ])->save();
    $browser->getJsonAs($publicVendor, '/procurement/submissions/'.$publicSubmission->id)->assertForbidden();

    $applicationsUrl = "/api/v1/think-tank/procurement/executions/{$executionId}/applications";
    $browser->apiGetAs($assignedMeEvaluator, $applicationsUrl)->assertForbidden();
    $applicationsResponse = $browser->apiGetAs(
        $executionActor,
        $applicationsUrl.'?q='.urlencode('Publication Form Test Vendor').'&status=submitted',
    )->assertOk()
        ->assertJsonPath('data.permissions.canView', true)
        ->assertJsonPath('data.permissions.canManage', true)
        ->assertJsonPath('data.execution.register.planId', (string) $plan->id)
        ->assertJsonPath('data.execution.register.itemId', (string) $publishedItem->id)
        ->assertJsonPath('data.summary.total', 1)
        ->assertJsonPath('data.summary.submitted', 1)
        ->assertJsonPath('data.applications.0.id', (string) $publicSubmission->id)
        ->assertJsonPath('data.applications.0.applicantEmail', $vendorEmail)
        ->assertJsonPath('data.applications.0.fileCount', 4)
        ->assertJsonPath('data.filters.status', 'submitted');
    $ensure(
        $applicationsResponse->json('data.pagination.total') === 1,
        'The tenant application register did not paginate the matching application.',
    );
    $executionWithReviewCapability = $browser->apiGetAs(
        $executionActor,
        "/api/v1/think-tank/procurement/executions/{$executionId}",
    )->assertOk();
    $ensure(
        $executionWithReviewCapability->json('data.canReviewApplications') === true,
        'The execution DTO did not advertise its application-review capability.',
    );

    $applicationDetailUrl = $applicationsUrl.'/'.$publicSubmission->id;
    $applicationDetail = $browser->apiGetAs($executionActor, $applicationDetailUrl)
        ->assertOk()
        ->assertJsonPath('data.application.status', FormSubmission::STATUS_SUBMITTED)
        ->assertJsonPath('data.application.applicantName', 'Publication Form Test Vendor')
        ->assertJsonPath('data.application.fileCount', 4)
        ->assertJsonCount(20, 'data.fields');
    $reviewFields = collect((array) $applicationDetail->json('data.fields'));
    $ensure(
        $reviewFields->pluck('type')->unique()->sort()->values()->all()
            === collect(DynamicProcurementFormCatalog::FIELD_TYPES)->sort()->values()->all(),
        'The tenant application detail did not canonicalize every supported field type.',
    );
    $ensure(
        $reviewFields->firstWhere('key', 'custom_service_areas_10')['value'] === ['Research', 'Advisory']
            && $reviewFields->firstWhere('key', 'custom_certifications_11')['value'] === ['ISO', 'Tax clearance']
            && $reviewFields->firstWhere('key', 'custom_declaration_12')['value'] === true,
        'The tenant application detail did not normalize array and boolean answers.',
    );
    $imageReviewField = $reviewFields->firstWhere('key', 'custom_portfolio_image_13');
    $ensure(
        is_array($imageReviewField)
            && $imageReviewField['value'] === null
            && $imageReviewField['hasFile'] === true
            && $imageReviewField['fileName'] === 'portfolio-image.png'
            && is_int($imageReviewField['fileSize'])
            && str_starts_with((string) $imageReviewField['downloadUrl'], $applicationDetailUrl.'/values/'),
        'The tenant application detail did not expose safe image metadata.',
    );
    $ensure(
        ! str_contains($applicationDetail->getContent(), 'procurement_submissions/'),
        'The tenant application API leaked a private storage path.',
    );
    $applicationFile = $browser->apiGetAs($executionActor, (string) $imageReviewField['downloadUrl'])
        ->assertOk();
    $ensure(
        str_contains((string) $applicationFile->headers->get('Cache-Control'), 'no-store')
            && $applicationFile->headers->get('X-Content-Type-Options') === 'nosniff'
            && str_contains((string) $applicationFile->headers->get('Content-Disposition'), 'attachment'),
        'The tenant application attachment response is missing secure download headers.',
    );
    $scalarValue = $publicSubmission->values->firstWhere('field_key', 'quoted_amount');
    $browser->apiGetAs(
        $executionActor,
        $applicationDetailUrl.'/values/'.$scalarValue->id.'/download',
    )->assertNotFound();
    $radioValue = $publicSubmission->values->firstWhere('field_key', 'custom_delivery_model_9');
    $radioValue->update(['value' => 'Not a configured choice']);
    $browser->apiGetAs($executionActor, $applicationDetailUrl)->assertStatus(409);
    $radioValue->update(['value' => 'Hybrid']);

    $otherStoredPath = 'procurement_submissions/'.Str::uuid().'.pdf';
    Storage::disk('public')->put($otherStoredPath, "%PDF-1.4\nnested ownership smoke\n");
    $otherSubmission = FormSubmission::query()->create([
        'procurement_id' => $publishedItem->procurement_id,
        'form_id' => $form->id,
        'submitted_by' => $actor->id,
        'status' => null,
        'submitted_at' => now(),
        'publication_version' => 1,
    ]);
    $otherStoredValue = FormSubmissionValue::query()->create([
        'submission_id' => $otherSubmission->id,
        'field_key' => 'organization_profile',
        'value' => $otherStoredPath,
    ]);
    $browser->apiGetAs(
        $executionActor,
        $applicationDetailUrl.'/values/'.$otherStoredValue->id.'/download',
    )->assertNotFound();
    $browser->apiGetAs(
        $executionActor,
        $applicationsUrl.'/'.$otherSubmission->id.'/values/'.$otherStoredValue->id.'/download',
    )->assertOk();
    $ensure(
        Storage::disk('local')->exists($otherStoredPath)
            && ! Storage::disk('public')->exists($otherStoredPath),
        'An authorized legacy attachment download did not move the public copy into private storage.',
    );
    $browser->apiGetAs($executionActor, $applicationsUrl.'?status=unknown')
        ->assertOk()
        ->assertJsonPath('data.applications.0.id', (string) $otherSubmission->id)
        ->assertJsonPath('data.applications.0.status', 'unknown')
        ->assertJsonFragment([
            'value' => 'unknown',
            'label' => 'Not recorded',
            'count' => 1,
        ]);
    $browser->apiGetAs(
        $executionActor,
        $applicationsUrl.'?q='.urlencode($actor->email).'&status=unknown',
    )->assertOk()->assertJsonPath('data.applications.0.id', (string) $otherSubmission->id);
    $otherStoredValue->delete();
    $otherSubmission->delete();
    Storage::disk('local')->delete($otherStoredPath);

    $otherConsortium = Consortium::query()->create([
        'code' => 'APP-'.$token,
        'name' => 'Cross-tenant application review '.$token,
        'country' => 'Kenya',
        'region' => 'Eastern Africa',
        'approved_budget' => 1000,
        'currency' => 'USD',
        'status' => 'active',
    ]);
    $crossTenantReviewer = User::query()->create([
        'name' => 'Cross-tenant application reviewer '.$token,
        'email' => 'cross-review-'.Str::lower(Str::random(12)).'@example.test',
        'password' => 'Password123!',
        'user_type' => 'think_tank',
        'role_id' => Role::query()->where('name', 'Think Tank User')->firstOrFail()->id,
        'think_tank_access_level' => User::THINK_TANK_ACCESS_PROCUREMENT,
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $crossTenantMember = ConsortiumThinkTank::query()->create([
        'consortium_id' => $otherConsortium->id,
        'portal_user_id' => $crossTenantReviewer->id,
        'name' => 'Cross-tenant review member '.$token,
        'country' => 'Kenya',
        'email' => $crossTenantReviewer->email,
        'role' => 'lead',
        'budget_allocated' => 1000,
        'status' => 'active',
        'joined_at' => now()->toDateString(),
    ]);
    $crossTenantReviewer->forceFill(['think_tank_member_id' => $crossTenantMember->id])->save();
    $browser->apiGetAs($crossTenantReviewer->fresh(), $applicationsUrl)->assertNotFound();
    $browser->apiGetAs($crossTenantReviewer->fresh(), $applicationDetailUrl)->assertNotFound();
    $browser->apiGetAs(
        $crossTenantReviewer->fresh(),
        (string) $imageReviewField['downloadUrl'],
    )->assertNotFound();

    $legacyShowBlocked = false;
    try {
        $legacyShowRequest = Request::create('/procurement/submissions/'.$publicSubmission->id, 'GET');
        $legacyShowRequest->setUserResolver(fn () => $actor);
        app(\App\Http\Controllers\Procurement\FormSubmissionController::class)
            ->show($legacyShowRequest, $publicSubmission);
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
        $legacyShowBlocked = $exception->getStatusCode() === 403;
    }
    $ensure($legacyShowBlocked, 'The unscoped legacy submission detail controller exposed a Think Tank application.');
    $ensure(
        $publicSubmission->values->firstWhere('field_key', 'custom_delivery_model_9')?->value === 'Hybrid',
        'The generated radio-button answer was not stored.'
    );
    $ensure(
        $publicSubmission->values->firstWhere('field_key', 'custom_service_areas_10')?->value === '["Research","Advisory"]',
        'The generated multiselect answer was not stored.'
    );
    $ensure(
        $publicSubmission->values->firstWhere('field_key', 'custom_certifications_11')?->value === '["ISO","Tax clearance"]',
        'The generated checkbox answer was not stored.'
    );
    $ensure(
        filled($publicSubmission->values->firstWhere('field_key', 'custom_portfolio_image_13')?->value),
        'The generated image-upload answer was not stored.'
    );
    Mail::assertQueued(VendorApplicationReceived::class, fn (VendorApplicationReceived $mail) => $mail->vendor->email === $vendorEmail);

    $publicVendor->update([
        'must_change_password' => false,
        'password_changed_at' => now(),
        'otp_verified_at' => now(),
        'is_disabled' => false,
        'is_blacklisted' => false,
    ]);
    $recallReason = 'The publication requires a clarified delivery schedule before applications proceed.';
    $browser->postAs($actor, route('think-tank.procurement-plans.items.recall-publication', [$plan, $publishedItem]), [
        'recall_reason' => $recallReason,
    ])->assertRedirect()->assertSessionHasNoErrors();
    $ensure($publishedItem->procurement->fresh()->status === 'recalled', 'The published procurement was not recalled.');
    $ensure($publicSubmission->fresh()->status === FormSubmission::STATUS_REVISION_REQUESTED, 'The vendor application was not returned for response after recall.');
    $browser->apiGetAs($executionActor, $applicationsUrl.'?status=revision_requested')
        ->assertOk()
        ->assertJsonPath('data.applications.0.id', (string) $publicSubmission->id)
        ->assertJsonPath('data.applications.0.status', FormSubmission::STATUS_REVISION_REQUESTED);
    $browser->getAs($actor, route('public.procurement.show', $publishedItem->procurement))->assertNotFound();
    Mail::assertQueued(VendorProcurementLifecycleMail::class, fn (VendorProcurementLifecycleMail $mail) => $mail->event === 'recalled' && $mail->vendor->email === $vendorEmail);
    $browser->getAs($publicVendor, route('vendor.submissions'))
        ->assertOk()
        ->assertSee('Awaiting republication')
        ->assertSee('Your application is preserved')
        ->assertSee('Withdraw');

    $browser->postAs($actor, route('think-tank.procurement-plans.items.republish', [$plan, $publishedItem]), [
        'application_start_date' => now()->toDateString(),
        'application_end_date' => now()->addDays(18)->toDateString(),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $republishedProcurement = $publishedItem->procurement->fresh();
    $ensure($republishedProcurement->status === 'published', 'The recalled procurement was not republished.');
    $ensure($republishedProcurement->publication_version === 2, 'The republication version was not incremented.');
    Mail::assertQueued(VendorProcurementLifecycleMail::class, fn (VendorProcurementLifecycleMail $mail) => $mail->event === 'republished' && $mail->vendor->email === $vendorEmail);

    $browser->getAs($publicVendor, route('vendor.applications.edit', $publicSubmission))
        ->assertOk()
        ->assertSee('Respond and resubmit application')
        ->assertSee($recallReason)
        ->assertSee('Response to the recall note');
    $browser->postAs($publicVendor, route('vendor.applications.update', $publicSubmission), [
        '_method' => 'PUT',
        'vendor_response' => 'We reviewed the clarified schedule and confirm our updated application remains valid.',
        'official_name' => 'Forged application identity',
        'official_email' => 'forged-'.Str::lower(Str::random(8)).'@example.test',
        'quoted_amount' => 14250,
        'relevant_experience' => 'Updated delivery experience for the republished opportunity.',
        'custom_operating_region_8' => 'East',
        'custom_delivery_model_9' => 'Hybrid',
    ])->assertRedirect(route('vendor.submissions'))->assertSessionHasNoErrors();
    $ensure($publicSubmission->fresh()->status === FormSubmission::STATUS_SUBMITTED, 'The recalled application was not resubmitted.');
    $ensure(filled($publicSubmission->fresh()->vendor_response), 'The vendor response to the recall was not retained.');
    $publicSubmission->load('values');
    $ensure(
        $publicSubmission->values->firstWhere('field_key', 'official_name')?->value === $publicVendor->name
            && $publicSubmission->values->firstWhere('field_key', 'official_email')?->value === $publicVendor->email,
        'A resubmission was able to overwrite the authenticated vendor identity fields.',
    );
    $browser->apiGetAs($executionActor, $applicationDetailUrl)
        ->assertOk()
        ->assertJsonPath('data.application.vendorResponse', 'We reviewed the clarified schedule and confirm our updated application remains valid.')
        ->assertJsonPath('data.application.publicationVersion', 2);

    $browser->postAs($publicVendor, route('vendor.applications.withdraw', $publicSubmission), [
        'withdrawal_reason' => 'We need to replace the application with a revised submission.',
    ])->assertRedirect(route('vendor.submissions'))->assertSessionHasNoErrors();
    $ensure($publicSubmission->fresh()->status === FormSubmission::STATUS_WITHDRAWN, 'The vendor application was not withdrawn.');
    $browser->apiGetAs($executionActor, $applicationsUrl.'?status=withdrawn')
        ->assertOk()
        ->assertJsonPath('data.applications.0.id', (string) $publicSubmission->id)
        ->assertJsonPath('data.applications.0.status', FormSubmission::STATUS_WITHDRAWN)
        ->assertJsonPath('data.summary.withdrawn', 1);

    $browser->postAs($actor, route('public.procurement.apply', $republishedProcurement), [
        'official_name' => 'Attempted account impersonation',
        'official_email' => $vendorEmail,
        'organization_profile' => UploadedFile::fake()->create('blocked-profile.pdf', 12, 'application/pdf'),
        'technical_proposal' => UploadedFile::fake()->create('blocked-technical.pdf', 12, 'application/pdf'),
        'financial_proposal' => UploadedFile::fake()->create('blocked-financial.pdf', 12, 'application/pdf'),
        'quoted_amount' => 13900,
        'relevant_experience' => 'This request must not bind to another account by email alone.',
        'custom_operating_region_8' => 'Washington, D.C.',
        'custom_delivery_model_9' => 'Remote',
    ])->assertUnprocessable()->assertJsonValidationErrors('official_email');

    $browser->postAs($publicVendor, route('public.procurement.apply', $republishedProcurement), [
        'official_name' => 'Publication Form Test Vendor',
        'official_email' => $vendorEmail,
        'organization_profile' => UploadedFile::fake()->create('organization-profile-v2.pdf', 24, 'application/pdf'),
        'technical_proposal' => UploadedFile::fake()->create('technical-proposal-v2.pdf', 32, 'application/pdf'),
        'financial_proposal' => UploadedFile::fake()->create('financial-proposal-v2.pdf', 20, 'application/pdf'),
        'quoted_amount' => 13900,
        'relevant_experience' => 'Replacement application after withdrawal.',
        'custom_operating_region_8' => 'Washington, D.C.',
        'custom_delivery_model_9' => 'Remote',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $ensure(
        FormSubmission::query()->where('procurement_id', $republishedProcurement->id)->where('submitted_by', $publicVendor->id)->count() === 2,
        'The vendor could not apply again after withdrawing the previous application.'
    );

    $browser->getAs($actor, route('think-tank-procurement.index'))
        ->assertOk()
        ->assertSee('Procurement records')
        ->assertSee('Current review queue')
        ->assertSee($member->name)
        ->assertSee('procurement items');
    $browser->getAs($actor, route('think-tank-procurement.show', $plan))
        ->assertOk()
        ->assertSee('Plan prepared')
        ->assertSee('Procurement items and documents')
        ->assertSee('Complete audit trail');
    $browser->getAs($actor, route('think-tank-procurement.reports'))
        ->assertOk()
        ->assertSee('Consolidated procurement report')
        ->assertSee('Individual Think Tank report')
        ->assertSee('Download individual PDF')
        ->assertSee('STEP-ready')
        ->assertSee('Export selected')
        ->assertSee('Export all');

    $consolidatedPdf = $browser->getAs($actor, route('think-tank-procurement.reports.pdf', [
        'scope' => 'consolidated',
        'fiscal_year' => '2026/27',
    ]));
    $ensure($consolidatedPdf->getStatusCode() === 200, 'Consolidated procurement PDF failed to download.');
    $ensure(
        str_contains((string) $consolidatedPdf->headers->get('Content-Type'), 'application/pdf'),
        'Consolidated procurement report is not a PDF response.'
    );
    $ensure(
        str_contains((string) $consolidatedPdf->headers->get('Content-Disposition'), '-2026-27-'),
        'The consolidated PDF filename did not safely normalize the fiscal-year slash.'
    );

    $individualPdf = $browser->getAs($actor, route('think-tank-procurement.reports.pdf', [
        'scope' => 'individual',
        'think_tank_member_id' => $member->id,
    ]));
    $ensure($individualPdf->getStatusCode() === 200, 'Individual Think Tank procurement PDF failed to download.');
    $ensure(
        str_contains((string) $individualPdf->headers->get('Content-Disposition'), '.pdf'),
        'Individual Think Tank report is not a PDF download response.'
    );

    foreach (['plan_submitted', 'item_revision_requested', 'item_approve', 'plan_approve', 'world_bank_no_objection_recorded', 'item_execution_created', 'item_publication_recalled', 'item_publication_republished', 'item_exported_for_step', 'evaluation_template_created', 'evaluation_team_assigned'] as $action) {
        $ensure($plan->events()->where('action', $action)->exists(), "Audit event [{$action}] is missing.");
    }

    echo "THINK_TANK_PROCUREMENT_UPGRADE_OK\n";
} finally {
    DB::rollBack();
}
