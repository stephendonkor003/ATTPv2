<?php

use App\Mail\VendorApplicationReceived;
use App\Mail\VendorProcurementLifecycleMail;
use App\Models\ConsortiumThinkTank;
use App\Models\DynamicForm;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\Role;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use App\Models\User;
use App\Services\ThinkTankProcurementWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
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
        $this->actingAs($user)->withSession([
            '_token' => $token,
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    public function getAs(User $user, string $uri)
    {
        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this->get($uri);
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
            'file_path' => "smoke/{$token}/tor-{$index}.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 128,
            'uploaded_by' => $actor->id,
        ]);
        Storage::disk('local')->put("smoke/{$token}/tor-{$index}.pdf", '%PDF smoke TOR');

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
        ->assertSee('Configure application form &amp; publication', false)
        ->assertSee('data-add-publication-question', false)
        ->assertSee("document.querySelectorAll('[data-publication-builder]')", false)
        ->assertSee('value="image"', false)
        ->assertSee('value="radio"', false)
        ->assertSee('Answer requirement');

    $launchResponse = $browser->postAs($actor, route('think-tank.procurement-plans.items.launch', [$plan, $items[0]]), [
        'application_start_date' => now()->toDateString(),
        'application_end_date' => now()->addDays(21)->toDateString(),
        'visibility_type' => 'public',
        'publish_now' => '1',
        'cover_image' => UploadedFile::fake()->image('opportunity-cover.jpg', 1200, 675),
        'fields' => [
            ['label' => 'Operating region', 'type' => 'select', 'required' => '1', 'options' => "East\nWest\nCentral", 'help_text' => 'Choose the primary operating region.'],
            ['label' => 'Delivery model', 'type' => 'radio', 'required' => '1', 'options' => "On-site\nHybrid\nRemote"],
            ['label' => 'Portfolio image', 'type' => 'image', 'required' => '0', 'allowed_extensions' => 'jpg, png, webp', 'max_file_size_mb' => 4],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $publishedItem = $items[0]->fresh();
    $ensure(
        $publishedItem->status === ThinkTankProcurementItem::STATUS_PUBLISHED,
        'No-objection item was not published. Redirect: '.($launchResponse->headers->get('Location') ?: 'none')
            .'; flash error: '.(session('error') ?: 'none')
    );
    $ensure($publishedItem->source_activity_status === ThinkTankProcurementItem::ACTIVITY_STATUS_WORLD_BANK_APPROVED, 'Publishing changed the World Bank no-objection activity label.');
    $ensure($publishedItem->procurement?->status === 'published', 'Public procurement opportunity was not published.');
    $ensure((bool) $publishedItem->procurement?->cover_image_path, 'The public opportunity cover image was not stored.');
    $ensure(Storage::disk('public')->exists($publishedItem->procurement->cover_image_path), 'The public opportunity cover file is missing.');
    $form = DynamicForm::query()->where('procurement_id', $publishedItem->procurement_id)->first();
    $ensure($form && $form->fields()->count() === 10, 'Default and custom application fields were not created.');
    $ensure($form->fields()->where('field_type', 'image')->exists(), 'The image-upload application question was not stored.');

    $browser->getAs($actor, route('public.procurement.index'))
        ->assertOk()
        ->assertSee($member->name)
        ->assertSee($publishedItem->procurement->title);
    $browser->getAs($actor, route('public.procurement.show', $publishedItem->procurement))
        ->assertOk()
        ->assertSee('Published by '.$member->name)
        ->assertSee('Operating region')
        ->assertSee('East')
        ->assertSee('West')
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
        'custom_operating_region_1' => 'East',
        'custom_delivery_model_2' => 'Hybrid',
        'custom_portfolio_image_3' => UploadedFile::fake()->image('portfolio.png', 800, 500),
    ])->assertRedirect()->assertSessionHasNoErrors();
    $publicVendor = User::query()->where('email', $vendorEmail)->first();
    $publicSubmission = FormSubmission::query()
        ->where('procurement_id', $publishedItem->procurement_id)
        ->where('submitted_by', (string) $publicVendor?->id)
        ->with('values')
        ->first();
    $ensure((bool) $publicSubmission, 'The generated public application form did not accept a submission.');
    $ensure(
        $publicSubmission->values->firstWhere('field_key', 'custom_delivery_model_2')?->value === 'Hybrid',
        'The generated radio-button answer was not stored.'
    );
    $ensure(
        filled($publicSubmission->values->firstWhere('field_key', 'custom_portfolio_image_3')?->value),
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
        'official_name' => 'Publication Form Test Vendor',
        'official_email' => $vendorEmail,
        'quoted_amount' => 14250,
        'relevant_experience' => 'Updated delivery experience for the republished opportunity.',
        'custom_operating_region_1' => 'East',
        'custom_delivery_model_2' => 'Hybrid',
    ])->assertRedirect(route('vendor.submissions'))->assertSessionHasNoErrors();
    $ensure($publicSubmission->fresh()->status === FormSubmission::STATUS_SUBMITTED, 'The recalled application was not resubmitted.');
    $ensure(filled($publicSubmission->fresh()->vendor_response), 'The vendor response to the recall was not retained.');

    $browser->postAs($publicVendor, route('vendor.applications.withdraw', $publicSubmission), [
        'withdrawal_reason' => 'We need to replace the application with a revised submission.',
    ])->assertRedirect(route('vendor.submissions'))->assertSessionHasNoErrors();
    $ensure($publicSubmission->fresh()->status === FormSubmission::STATUS_WITHDRAWN, 'The vendor application was not withdrawn.');

    $browser->postAs($actor, route('public.procurement.apply', $republishedProcurement), [
        'official_name' => 'Publication Form Test Vendor',
        'official_email' => $vendorEmail,
        'organization_profile' => UploadedFile::fake()->create('organization-profile-v2.pdf', 24, 'application/pdf'),
        'technical_proposal' => UploadedFile::fake()->create('technical-proposal-v2.pdf', 32, 'application/pdf'),
        'financial_proposal' => UploadedFile::fake()->create('financial-proposal-v2.pdf', 20, 'application/pdf'),
        'quoted_amount' => 13900,
        'relevant_experience' => 'Replacement application after withdrawal.',
        'custom_operating_region_1' => 'West',
        'custom_delivery_model_2' => 'Remote',
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
