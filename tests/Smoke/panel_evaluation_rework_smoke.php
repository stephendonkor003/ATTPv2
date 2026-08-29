<?php

use App\Mail\EvaluationReworkRequested;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSectionScore;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\ProcurementContractNegotiation;
use App\Models\ReworkRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\EoiTechnicalProposalService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class PanelEvaluationReworkSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithSession;
    use MakesHttpRequests;

    protected $app;

    public function __construct($app)
    {
        $this->app = $app;
    }

    public function run(): void
    {
        Mail::fake();
        Storage::fake('local');
        DB::beginTransaction();

        try {
            [$manager, $viewer, $evaluator, $vendor, $workflowAdministrator] = $this->users();
            [$procurement, $evaluation, $section, $criterion, $assignment, $applicant, $submission] =
                $this->fixture($manager, $evaluator, $vendor);

            $panel = $this->actingAsVerified($manager)
                ->get(route('eval.panel.procurement', $procurement));
            $this->assertStatus($panel, 200, 'The Panel Evaluation journey did not render.');
            $this->assertContains($panel, 'Review submitted evaluations', 'The individual evaluation register is missing.');
            $this->assertContains($panel, 'Send for rework', 'The manager cannot open the rework dialog.');
            $this->assertContains($panel, $evaluator->name, 'The target evaluator is missing from the rework card.');
            $this->assertContains($panel, $applicant->procurement_submission_code, 'The target application is missing from the rework card.');

            $denied = $this->postAs($viewer, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'The score needs a documented correction.',
            ]);
            $this->assertStatus($denied, 403, 'A report-only user could request evaluation rework.');
            $this->assertTrue($submission->fresh()->isSubmitted(), 'An unauthorized request reopened the evaluation.');

            $otherProcurement = Procurement::create([
                'title' => 'Cross-procurement rework guard '.Str::upper(Str::random(6)),
                'reference_no' => 'OTHER-'.Str::upper(Str::random(8)),
                'status' => 'closed',
                'created_by' => $manager->id,
            ]);
            $crossProcurement = $this->postAs(
                $manager,
                route('eval.panel.rework', [$otherProcurement, $submission]),
                ['reason' => 'This crafted request must not cross procurement boundaries.']
            );
            $this->assertStatus($crossProcurement, 404, 'A cross-procurement rework request was accepted.');

            $invalid = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'Too short',
            ]);
            $this->assertStatus($invalid, 302, 'Invalid rework guidance did not redirect with validation feedback.');
            $this->assertTrue($invalid->getSession()->has('errors'), 'Invalid rework guidance was accepted.');
            $this->assertTrue($submission->fresh()->isSubmitted(), 'Invalid guidance reopened the evaluation.');
            Mail::assertNotSent(EvaluationReworkRequested::class);

            $evaluator->forceFill([
                'is_disabled' => true,
                'disabled_at' => now(),
                'disabled_until' => now()->addDay(),
            ])->save();
            $inactiveEvaluator = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'This evaluator cannot complete a returned evaluation while the account is disabled.',
            ]);
            $this->assertStatus($inactiveEvaluator, 302, 'An inactive evaluator did not return validation feedback.');
            $this->assertSame(
                'The assigned evaluator cannot currently access the evaluation workspace. Reactivate or reassign the evaluator before requesting rework.',
                $inactiveEvaluator->getSession()->get('errors')?->first('rework'),
                'The administrator was not told that the evaluator account is inactive.'
            );
            $this->assertTrue($submission->fresh()->isSubmitted(), 'An unfinishable rework task was created for an inactive evaluator.');
            $this->assertSame(0, ReworkRequest::query()->where('evaluation_submission_id', $submission->id)->count(), 'The inactive evaluator created a rework cycle.');
            $evaluator->forceFill([
                'is_disabled' => false,
                'disabled_at' => null,
                'disabled_until' => null,
            ])->save();

            $reason = 'Recheck the technical approach score and strengthen both the strengths and weaknesses evidence.';
            $requested = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => $reason,
            ]);
            $this->assertStatus($requested, 302, 'The valid rework request did not redirect.');
            $this->assertTrue(
                str_contains((string) $requested->headers->get('Location'), '/panel-evaluations/procurements/'),
                'The rework action did not return to the stable Panel Evaluation journey.'
            );

            $reopened = $submission->fresh();
            $rework = ReworkRequest::query()
                ->where('evaluation_submission_id', $submission->getKey())
                ->firstOrFail();
            $this->assertTrue($reopened->submitted_at === null, 'The selected evaluation remained final after rework.');
            $this->assertSame(EvaluationSubmission::WORKFLOW_REWORK_REQUESTED, $reopened->workflow_status, 'The live evaluation is not marked for rework.');
            $this->assertSame('rework', $assignment->fresh()->status, 'The assignment does not expose its rework state.');
            $this->assertSame($reason, $rework->reason, 'The correction instructions were not preserved.');
            $this->assertSame(ReworkRequest::STATUS_PENDING, $rework->status, 'The rework request was not left pending.');
            $this->assertSame(1, (int) $rework->source_revision_number, 'The source revision was not captured.');
            $this->assertSame(8.0, (float) data_get($rework->source_snapshot, 'submission.overall_score'), 'The original total was not snapshotted.');
            $this->assertSame('application', data_get($rework->source_snapshot, 'assignment.workflow_stage'), 'The workflow stage was not snapshotted.');
            $this->assertSame((string) $manager->id, data_get($rework->source_snapshot, 'event.actor.id'), 'The requesting administrator was not snapshotted.');
            $this->assertSame($this->snapshotHash($rework->source_snapshot), $rework->source_snapshot_hash, 'The source snapshot integrity hash is invalid.');
            $reorderedSourceSnapshot = array_reverse($rework->source_snapshot, true);
            $reorderedSourceSnapshot['submission'] = array_reverse($reorderedSourceSnapshot['submission'], true);
            $this->assertSame($rework->source_snapshot_hash, $this->snapshotHash($reorderedSourceSnapshot), 'The snapshot hash depends on JSON object key order.');
            $this->assertSame(8.0, (float) $reopened->criteriaScores()->value('score'), 'Rework cleared the evaluator answers instead of prefilling them.');
            $reopenedLegacyPdf = $this->actingAsVerified($manager)
                ->get(route('evals.cfg.panel.pdf.single', $submission));
            $this->assertStatus($reopenedLegacyPdf, 404, 'A reopened draft remained downloadable through the legacy final-report PDF route.');
            Mail::assertSent(EvaluationReworkRequested::class, 1);
            Mail::assertSent(EvaluationReworkRequested::class, function (EvaluationReworkRequested $mail) use ($evaluator, $reason): bool {
                return $mail->hasTo($evaluator->email) && $mail->rework->reason === $reason;
            });

            $duplicate = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'A duplicate administrator request that must not create another cycle.',
            ]);
            $this->assertStatus($duplicate, 302, 'A duplicate rework request did not return validation feedback.');
            $this->assertSame(
                'This evaluation is already awaiting rework from the evaluator.',
                $duplicate->getSession()->get('errors')?->first('rework'),
                'A duplicate request did not explain the existing rework cycle.'
            );
            $this->assertSame(1, ReworkRequest::query()->where('evaluation_submission_id', $submission->id)->count(), 'A duplicate rework record was created.');
            Mail::assertSent(EvaluationReworkRequested::class, 1);

            $worklist = $this->actingAsVerified($evaluator)->get(route('my.eval.index'));
            $this->assertStatus($worklist, 200, 'The evaluator worklist did not render after rework.');
            $this->assertContains($worklist, 'Rework requested', 'The evaluator cannot see the rework status.');
            $this->assertContains($worklist, $reason, 'The evaluator cannot see the administrator instructions.');
            $this->assertContains($worklist, 'Revise evaluation', 'The evaluator cannot continue the returned record.');
            $this->assertContains($worklist, 'Rework requires your attention', 'The returned evaluation is not prioritised at the top of My Evaluations.');
            $this->assertContains($worklist, 'Edit and resubmit', 'The evaluator does not have a direct rework edit action.');
            $this->assertContains(
                $worklist,
                route('my.eval.start', [$assignment, $applicant]),
                'The priority rework action does not link to the returned evaluation.'
            );

            $workspace = $this->actingAsVerified($evaluator)
                ->get(route('my.eval.start', [$assignment, $applicant]));
            $this->assertStatus($workspace, 200, 'The returned evaluation did not reopen in editable mode.');
            $this->assertContains($workspace, 'This evaluation has been returned for correction', 'The scoring workspace lacks the rework banner.');
            $this->assertContains($workspace, $reason, 'The scoring workspace lacks the correction instructions.');

            $otherEvaluatorWorkspace = $this->actingAsVerified($viewer)
                ->get(route('my.eval.start', [$assignment, $applicant]));
            $this->assertStatus($otherEvaluatorWorkspace, 403, 'A user other than the assigned evaluator opened the returned evaluation.');

            $autosaved = $this->postAs($evaluator, route('my.eval.save', [$assignment, $applicant]), [
                'criteria' => [$criterion->id => 8.5],
                'sections' => [
                    $section->id => [
                        'strengths' => 'The revised approach is supported by clearer evidence.',
                        'weaknesses' => 'The evaluator is still checking one delivery assumption.',
                    ],
                ],
            ]);
            $this->assertStatus($autosaved, 200, 'The assigned evaluator could not save corrections to the returned evaluation.');
            $this->assertSame(
                8.5,
                (float) $submission->fresh()->criteriaScores()->where('evaluation_criteria_id', $criterion->id)->value('score'),
                'The rework autosave did not persist the evaluator correction.'
            );
            $this->assertSame(ReworkRequest::STATUS_PENDING, $rework->fresh()->status, 'Saving rework as a draft closed the request before final resubmission.');

            $resubmitted = $this->postAs($evaluator, route('my.eval.submit', [$assignment, $applicant]), [
                'criteria' => [$criterion->id => 9],
                'sections' => [
                    $section->id => [
                        'strengths' => 'The revised technical approach now has clear and verifiable strengths.',
                        'weaknesses' => 'A minor delivery sequencing risk remains and has been documented.',
                    ],
                ],
                'video' => UploadedFile::fake()->create('rework-proof.webm', 64, 'video/webm'),
            ]);
            $this->assertStatus($resubmitted, 302, 'The evaluator could not resubmit the corrected evaluation.');

            $final = $submission->fresh();
            $completedRework = $rework->fresh();
            $this->assertTrue($final->submitted_at !== null, 'The corrected evaluation was not finalized.');
            $this->assertSame(EvaluationSubmission::WORKFLOW_SUBMITTED, $final->workflow_status, 'The corrected evaluation did not return to submitted status.');
            $this->assertSame(2, (int) $final->revision_number, 'The corrected evaluation did not advance to revision 2.');
            $this->assertSame(9.0, (float) $final->overall_score, 'The corrected score was not saved.');
            $this->assertSame('submitted', $assignment->fresh()->status, 'The assignment did not return to submitted after rework completion.');
            $this->assertSame(ReworkRequest::STATUS_COMPLETED, $completedRework->status, 'The rework request remains pending after resubmission.');
            $this->assertSame(2, (int) $completedRework->completed_revision_number, 'The completed revision number was not recorded.');
            $this->assertSame(9.0, (float) data_get($completedRework->completed_snapshot, 'submission.overall_score'), 'The corrected result snapshot was not retained.');
            $this->assertSame('submitted', data_get($completedRework->completed_snapshot, 'assignment.status'), 'The completed audit snapshot retained the pre-completion assignment state.');
            $this->assertSame(8.0, (float) data_get($completedRework->source_snapshot, 'submission.overall_score'), 'The original snapshot was changed during resubmission.');
            $this->assertSame($this->snapshotHash($completedRework->completed_snapshot), $completedRework->completed_snapshot_hash, 'The completed snapshot integrity hash is invalid.');
            $this->assertSame((string) config('filesystems.default', 'local'), data_get($completedRework->completed_snapshot, 'submission.video_disk'), 'The identity-video evidence disk was not recorded.');
            $this->assertTrue(data_get($completedRework->completed_snapshot, 'submission.video_file_size') !== null, 'The identity-video evidence size was not recorded.');
            $this->assertTrue(
                preg_match('/^[a-f0-9]{64}$/', (string) data_get($completedRework->completed_snapshot, 'submission.video_sha256')) === 1,
                'The identity-video evidence SHA-256 was not recorded.'
            );

            $eligibleApplicantStatus = $applicant->fresh()->status;
            $applicant->forceFill(['status' => FormSubmission::STATUS_WITHDRAWN])->save();
            $ineligibleTarget = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'This withdrawn applicant must not receive an evaluation rework task.',
            ]);
            $this->assertStatus($ineligibleTarget, 302, 'An ineligible applicant did not return validation feedback.');
            $this->assertTrue($submission->fresh()->isSubmitted(), 'An unfinishable rework task was created for an ineligible applicant.');
            $this->assertSame(1, ReworkRequest::query()->where('evaluation_submission_id', $submission->id)->count(), 'The ineligible target created a rework cycle.');
            $applicant->forceFill(['status' => $eligibleApplicantStatus])->save();

            $procurement->forceFill(['awarded_at' => now()])->save();
            $downstreamLocked = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'This evaluation must remain final after the award workflow starts.',
            ]);
            $this->assertStatus($downstreamLocked, 302, 'The downstream workflow lock did not return validation feedback.');
            $this->assertTrue($submission->fresh()->isSubmitted(), 'An awarded procurement evaluation was reopened.');
            $this->assertSame(1, ReworkRequest::query()->where('evaluation_submission_id', $submission->id)->count(), 'The downstream lock created a rework cycle.');
            $procurement->forceFill(['awarded_at' => null])->save();

            $evaluation->forceFill(['type' => Evaluation::TYPE_EOI])->save();
            $applicant->forceFill(['status' => FormSubmission::STATUS_EOI_NOT_QUALIFIED])->save();
            $evaluator->forceFill(['email' => ''])->save();
            $secondRequested = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
                'reason' => 'Reconsider this unreleased EOI not-qualified outcome while preserving the first completed revision.',
            ]);
            $this->assertStatus($secondRequested, 302, 'A second rework cycle could not be created.');
            $this->assertTrue($secondRequested->getSession()->has('warning'), 'A missing evaluator email did not warn the administrator.');
            $secondRework = ReworkRequest::query()
                ->where('evaluation_submission_id', $submission->id)
                ->where('cycle', 2)
                ->firstOrFail();
            $this->assertSame(ReworkRequest::STATUS_PENDING, $secondRework->status, 'The second rework cycle is not pending.');
            $this->assertTrue(filled($secondRework->notification_error), 'The missing evaluator email was not recorded.');
            $this->assertSame(
                FormSubmission::STATUS_EOI_EVALUATION,
                $applicant->fresh()->status,
                'An unreleased EOI not-qualified outcome did not return to the evaluation stage.'
            );
            $this->assertSame((string) $secondRework->id, (string) $submission->fresh()->latestReworkRequest?->id, 'The ordered latest-rework relation did not select cycle 2.');
            Mail::assertSent(EvaluationReworkRequested::class, 1);

            $blockedNegotiation = $this->postAs(
                $workflowAdministrator,
                route('procurement.contract-negotiations.store', $procurement),
                [
                    'submission_id' => $applicant->getKey(),
                    'proposed_amount' => 125000,
                    'notes' => 'This must wait for the returned evaluation to be resubmitted.',
                ]
            );
            $this->assertStatus($blockedNegotiation, 302, 'Contracting during pending rework did not return validation feedback.');
            $this->assertSame(
                'Complete or resolve all pending evaluation rework before starting an award, contract, or purchase-order workflow.',
                $blockedNegotiation->getSession()->get('errors')?->first('procurement'),
                'The administrator was not told why contracting is temporarily locked.'
            );
            $this->assertSame(
                0,
                ProcurementContractNegotiation::query()->where('procurement_id', $procurement->id)->count(),
                'A contract negotiation was created while evaluation rework was pending.'
            );

            $procurement->forceFill(['status' => 'published'])->save();
            $blockedWithdrawal = $this->postAs(
                $vendor,
                route('vendor.applications.withdraw', $applicant),
                ['withdrawal_reason' => 'This withdrawal must wait until the evaluator completes the requested rework.']
            );
            $this->assertStatus($blockedWithdrawal, 302, 'A withdrawal during pending rework did not return validation feedback.');
            $this->assertSame(
                'This application has an evaluation awaiting rework and cannot move to an ineligible status yet.',
                $blockedWithdrawal->getSession()->get('errors')?->first('status'),
                'The vendor was not told why withdrawal is temporarily locked.'
            );
            $this->assertSame(
                FormSubmission::STATUS_EOI_EVALUATION,
                $applicant->fresh()->status,
                'A vendor withdrawal invalidated an evaluation that was awaiting rework.'
            );

            $this->assertProposalRoundAdminOverride(
                $manager,
                $workflowAdministrator,
                $evaluator,
                $vendor
            );

            echo "PANEL_EVALUATION_REWORK_SMOKE_OK\n";
        } finally {
            DB::rollBack();
            $this->app['auth']->forgetGuards();
        }
    }

    private function users(): array
    {
        $permissions = collect(['evaluations.view_all', 'evaluations.manage', 'forms.manage'])
            ->mapWithKeys(fn (string $name): array => [
                $name => Permission::firstOrCreate(
                    ['name' => $name],
                    [
                        'module' => 'evaluations',
                        'description' => 'Panel evaluation rework smoke permission.',
                    ]
                ),
            ]);

        $managerRole = Role::firstOrCreate(['name' => 'E2E Panel Rework Manager']);
        $managerRole->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $viewerRole = Role::firstOrCreate(['name' => 'E2E Panel Rework Viewer']);
        $viewerRole->permissions()->syncWithoutDetaching([$permissions['evaluations.view_all']->id]);
        $administratorRole = Role::firstOrCreate(['name' => 'System Admin']);

        $manager = $this->user('Panel Rework Manager', 'employee', $managerRole->id);
        $manager->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());

        return [
            $manager,
            $this->user('Panel Rework Viewer', 'employee', $viewerRole->id),
            $this->user('Panel Rework Evaluator', 'employee'),
            $this->user('Panel Rework Applicant', 'vendor'),
            $this->user('Panel Rework Workflow Administrator', 'employee', $administratorRole->id),
        ];
    }

    private function fixture(User $manager, User $evaluator, User $vendor): array
    {
        $token = Str::upper(Str::random(8));
        $procurement = Procurement::create([
            'title' => "Panel rework services {$token}",
            'reference_no' => "REWORK-{$token}",
            'description' => 'Panel evaluation rework smoke fixture.',
            'status' => 'closed',
            'created_by' => $manager->id,
        ]);
        $applicant = FormSubmission::create([
            'procurement_id' => $procurement->id,
            'procurement_submission_code' => "APP-{$token}",
            'submitted_by' => $vendor->id,
            'status' => FormSubmission::STATUS_SUBMITTED,
            'submitted_at' => now()->subDays(3),
        ]);
        $evaluation = Evaluation::create([
            'name' => 'Technical services assessment',
            'description' => 'Numeric evaluation used for rework coverage.',
            'status' => 'active',
            'type' => Evaluation::TYPE_SERVICES,
            'created_by' => $manager->id,
        ]);
        $section = EvaluationSection::create([
            'evaluation_id' => $evaluation->id,
            'name' => 'Technical approach',
            'description' => 'Assess the proposed technical approach.',
            'show_subtotal' => true,
            'sort_order' => 1,
        ]);
        $criterion = EvaluationCriteria::create([
            'evaluation_section_id' => $section->id,
            'name' => 'Quality of methodology',
            'description' => 'Score the quality and feasibility of the methodology.',
            'max_score' => 10,
        ]);
        $assignment = EvaluationAssignment::create([
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $procurement->id,
            'form_submission_id' => $applicant->id,
            'workflow_stage' => EvaluationAssignment::STAGE_APPLICATION,
            'user_id' => $evaluator->id,
            'assigned_by' => $manager->id,
            'assigned_at' => now()->subDays(2),
            'status' => 'submitted',
        ]);
        $submission = EvaluationSubmission::create([
            'evaluation_assignment_id' => $assignment->id,
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $procurement->id,
            'evaluator_id' => $evaluator->id,
            'form_submission_id' => $applicant->id,
            'overall_score' => 8,
            'comments' => 'Original submitted assessment.',
            'video_path' => 'evaluation_proofs/original/proof.webm',
            'submitted_at' => now()->subDay(),
            'workflow_status' => EvaluationSubmission::WORKFLOW_SUBMITTED,
            'revision_number' => 1,
        ]);
        EvaluationCriteriaScore::create([
            'submission_id' => $submission->id,
            'evaluation_criteria_id' => $criterion->id,
            'score' => 8,
        ]);
        EvaluationSectionScore::create([
            'submission_id' => $submission->id,
            'evaluation_section_id' => $section->id,
            'section_score' => 8,
            'strengths' => 'Original strengths.',
            'weaknesses' => 'Original weaknesses.',
        ]);

        return [$procurement, $evaluation, $section, $criterion, $assignment, $applicant, $submission];
    }

    private function assertProposalRoundAdminOverride(
        User $manager,
        User $administrator,
        User $evaluator,
        User $vendor
    ): void {
        [$procurement, $evaluation, , , , $applicant, $submission] =
            $this->fixture($manager, $evaluator, $vendor);
        $evaluation->forceFill(['type' => Evaluation::TYPE_EOI])->save();

        $round = EoiTechnicalProposalRound::create([
            'procurement_id' => $procurement->getKey(),
            'round_number' => 1,
            'title' => 'Published proposal-round rework guard',
            'timezone' => config('app.timezone', 'UTC'),
            'late_policy' => EoiTechnicalProposalRound::LATE_REJECT,
            'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
            'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
            'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
            'status' => EoiTechnicalProposalRound::STATUS_PUBLISHED,
            'created_by' => $administrator->getKey(),
            'published_by' => $administrator->getKey(),
            'published_at' => now()->subHour(),
        ]);
        $applicant->forceFill([
            'status' => FormSubmission::STATUS_TECHNICAL_PROPOSAL_SUBMITTED,
        ])->save();
        $candidate = EoiTechnicalProposalCandidate::create([
            'round_id' => $round->getKey(),
            'form_submission_id' => $applicant->getKey(),
            'user_id' => $vendor->getKey(),
            'eoi_outcome_code' => 'fully_qualified',
            'eoi_outcome_label' => 'Fully Qualified',
            'workflow_decision' => 'Technical Proposal',
            'status' => EoiTechnicalProposalCandidate::STATUS_SUBMITTED,
            'invited_at' => now()->subDay(),
            'first_submitted_at' => now()->subHours(2),
            'last_submitted_at' => now()->subHours(2),
        ]);

        $managerPanel = $this->actingAsVerified($manager)
            ->get(route('eval.panel.procurement', $procurement));
        $this->assertStatus($managerPanel, 200, 'The locked manager panel did not render.');
        $this->assertContains($managerPanel, 'Rework locked', 'A normal evaluation manager was not shown the proposal-round lock.');
        $this->assertNotContains($managerPanel, 'Override lock &amp; rework', 'A normal evaluation manager was offered the administrator override.');

        $administratorPanel = $this->actingAsVerified($administrator)
            ->get(route('eval.panel.procurement', $procurement));
        $this->assertStatus($administratorPanel, 200, 'The administrator panel did not render.');
        $this->assertContains($administratorPanel, 'Override lock &amp; rework', 'The administrator cannot open the proposal-round override dialog.');
        $this->assertContains($administratorPanel, 'override_proposal_round_lock', 'The administrator override acknowledgement is missing.');

        $managerSpoof = $this->postAs($manager, route('eval.panel.rework', [$procurement, $submission]), [
            'reason' => 'This manager must not bypass the published technical-proposal round lock.',
            'override_proposal_round_lock' => '1',
        ]);
        $this->assertStatus($managerSpoof, 302, 'A spoofed manager override did not return validation feedback.');
        $this->assertSame(
            'Only a System or Super Administrator can override an existing technical-proposal round.',
            $managerSpoof->getSession()->get('errors')?->first('override_proposal_round_lock'),
            'The server accepted or misreported a non-administrator override.'
        );
        $this->assertTrue($submission->fresh()->isSubmitted(), 'A normal manager bypassed the proposal-round lock.');

        $missingConfirmation = $this->postAs($administrator, route('eval.panel.rework', [$procurement, $submission]), [
            'reason' => 'The administrator must explicitly confirm the proposal-round override before rework.',
        ]);
        $this->assertStatus($missingConfirmation, 302, 'An unconfirmed administrator override did not return validation feedback.');
        $this->assertSame(
            'This EOI evaluation cannot be reopened because the technical-proposal round has already started or been prepared.',
            $missingConfirmation->getSession()->get('errors')?->first('rework'),
            'An administrator bypassed the proposal-round lock without confirmation.'
        );
        $this->assertTrue($submission->fresh()->isSubmitted(), 'An unconfirmed override reopened the evaluation.');

        $procurement->forceFill(['awarded_at' => now()])->save();
        $finalityAttempt = $this->postAs($administrator, route('eval.panel.rework', [$procurement, $submission]), [
            'reason' => 'A proposal-round override must not bypass award and contracting finality.',
            'override_proposal_round_lock' => '1',
        ]);
        $this->assertStatus($finalityAttempt, 302, 'The award finality guard did not return validation feedback.');
        $this->assertSame(
            'This evaluation cannot be reopened after an award, contract negotiation, or purchase order has started.',
            $finalityAttempt->getSession()->get('errors')?->first('rework'),
            'The proposal-round override bypassed the award finality guard.'
        );
        $procurement->forceFill(['awarded_at' => null])->save();

        $reason = 'Recheck this application-stage EOI assessment despite the existing proposal round.';
        $overridden = $this->postAs($administrator, route('eval.panel.rework', [$procurement, $submission]), [
            'reason' => $reason,
            'override_proposal_round_lock' => '1',
        ]);
        $this->assertStatus($overridden, 302, 'The confirmed administrator override was not accepted.');

        $rework = ReworkRequest::query()
            ->where('evaluation_submission_id', $submission->getKey())
            ->where('status', ReworkRequest::STATUS_PENDING)
            ->firstOrFail();
        $this->assertTrue($submission->fresh()->submitted_at === null, 'The administrator override did not reopen the evaluation.');
        $this->assertSame(
            'eoi_technical_proposal_round',
            data_get($rework->source_snapshot, 'event.workflow_lock_override.type'),
            'The overridden workflow lock was not captured in the immutable snapshot.'
        );
        $this->assertSame(
            (string) $round->getKey(),
            (string) data_get($rework->source_snapshot, 'event.workflow_lock_override.rounds.0.id'),
            'The published proposal round was not identified in the override audit snapshot.'
        );
        $this->assertSame(
            (string) $administrator->getKey(),
            (string) data_get($rework->source_snapshot, 'event.actor.id'),
            'The overriding administrator was not identified in the audit snapshot.'
        );
        $this->assertSame(
            $this->snapshotHash($rework->source_snapshot),
            $rework->source_snapshot_hash,
            'The administrator override snapshot failed its integrity hash check.'
        );
        $this->assertSame(
            FormSubmission::STATUS_EOI_EVALUATION,
            $applicant->fresh()->status,
            'A proposal-submitted applicant was not returned to the incomplete EOI stage during rework.'
        );
        $this->assertSame(
            EoiTechnicalProposalCandidate::STATUS_SUBMITTED,
            $candidate->fresh()->status,
            'The existing technical-proposal history was changed instead of being preserved during EOI rework.'
        );

        try {
            app(EoiTechnicalProposalService::class)->createSubmission(
                $candidate,
                [UploadedFile::fake()->create('paused-proposal.pdf', 20, 'application/pdf')],
                $vendor,
                \App\Models\EoiTechnicalProposalSubmission::SOURCE_VENDOR_PORTAL,
                \App\Models\EoiTechnicalProposalSubmission::CHANNEL_PORTAL
            );
            $this->assertTrue(false, 'A vendor uploaded another proposal while the EOI evaluation awaited rework.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This applicant has an EOI evaluation awaiting rework. Proposal uploads and compliance decisions are paused until the evaluator resubmits.',
                $exception->errors()['proposal'][0] ?? null,
                'The paused proposal workflow did not explain its pending EOI rework dependency.'
            );
        }
    }

    private function user(string $name, string $type, ?string $roleId = null): User
    {
        return User::create([
            'name' => $name,
            'email' => Str::slug($name).'-'.Str::lower(Str::random(8)).'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => $type,
            'role_id' => $roleId,
            'must_change_password' => false,
            'otp_verified_at' => now(),
            'password_changed_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
        ]);
    }

    private function postAs(User $user, string $uri, array $data)
    {
        $token = Str::random(40);
        $this->actingAsVerified($user)->withSession(['_token' => $token]);

        return $this->post($uri, ['_token' => $token, ...$data]);
    }

    private function actingAsVerified(User $user): self
    {
        $this->actingAs($user)->withSession([
            'otp_verified' => true,
            'otp_verified_user_id' => (string) $user->id,
            'otp_verified_at' => now()->toIso8601String(),
        ]);

        return $this;
    }

    private function assertStatus($response, int $expected, string $message): void
    {
        $actual = $response->getStatusCode();
        if ($actual !== $expected) {
            throw new RuntimeException("{$message} Expected {$expected}, received {$actual}. ".Str::limit(strip_tags((string) $response->getContent()), 600));
        }
    }

    private function assertContains($response, string $needle, string $message): void
    {
        $this->assertTrue(str_contains((string) $response->getContent(), $needle), $message);
    }

    private function assertNotContains($response, string $needle, string $message): void
    {
        $this->assertTrue(! str_contains((string) $response->getContent(), $needle), $message);
    }

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertSame(mixed $expected, mixed $actual, string $message): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException($message.' Expected '.var_export($expected, true).', received '.var_export($actual, true).'.');
        }
    }

    private function snapshotHash(array $snapshot): string
    {
        return hash('sha256', json_encode(
            $this->canonicalizeSnapshotValue($snapshot),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }

    private function canonicalizeSnapshotValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalizeSnapshotValue($item),
                $value
            );
        }

        ksort($value, SORT_STRING);

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalizeSnapshotValue($item);
        }

        return $value;
    }
}

(new PanelEvaluationReworkSmoke($app))->run();
