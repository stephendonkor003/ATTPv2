<?php

use App\Mail\EvaluationReworkBatchRequested;
use App\Mail\EvaluationReworkRequested;
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
use App\Models\ReworkRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\EvaluationManagementReportService;
use App\Services\EvaluationReportReworkPanel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class EvaluationReportBatchReworkSmoke
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
        $originalMailer = config('evaluation_rework.mailer');
        $originalDefaultMailer = config('mail.default');
        config(['evaluation_rework.mailer' => 'smtp', 'mail.default' => 'log']);
        Mail::fake();
        Storage::fake('local');
        DB::beginTransaction();
        try {
            [$manager, $viewer, $evaluator, $vendor, $administrator] = $this->users();
            [$procurement, $evaluation, , , $assignment, $applicant, $first] = $this->fixture($manager, $evaluator, $vendor);
            [, $secondEvaluation, , , $secondAssignment, $secondApplicant, $second] = $this->fixture($manager, $evaluator, $vendor);
            $secondEvaluation->forceFill(['name' => 'Financial assessment', 'evaluation_phase' => 'financial'])->save();
            foreach ([$secondAssignment, $secondApplicant, $second] as $record) {
                $record->forceFill(['procurement_id' => $procurement->id])->save();
            }
            [$otherProcurement, , , , , , $other] = $this->fixture($manager, $evaluator, $vendor);
            $url = route('eval.panel.rework.batch', $procurement);
            $reason = 'Correct the supporting evidence and explain <script>unsafe()</script> discrepancies before resubmitting.';
            $payload = ['submission_ids' => [$first->id, $second->id], 'evaluator_id' => $evaluator->id, 'reason' => $reason];
            $panelService = app(EvaluationReportReworkPanel::class);
            $this->actingAsVerified($manager);
            request()->setUserResolver(fn () => $manager);
            $panel = $panelService->forProcurements([$procurement]);
            $this->assertSame(1, count($panel['groups']), 'One evaluator was split into different report controls.');
            $this->assertSame(2, $panel['available_count'], 'Submitted records across forms were not selectable.');
            $this->assertTrue(collect($panel['groups'][0]['entries'])->contains(fn ($row) => str_contains($row['phase'], 'Financial')), 'The form phase is missing from the choices.');
            $individual = $panelService->forProcurements([$procurement], null, $first);
            $this->assertSame(route('reports.evaluations.procurement', $procurement, false), $individual['groups'][0]['return_to'], 'The reopened individual report would redirect to a final-only route.');

            $this->assertStatus($this->postAs($viewer, $url, $payload), 403, 'Report-only user reopened evaluations.');
            request()->setUserResolver(fn () => $viewer);
            $this->assertSame([], $panelService->forProcurements([$procurement])['groups'], 'Report-only user received action controls.');
            foreach ([
                ['submission_ids' => []],
                ['submission_ids' => [$first->id, $first->id]],
                ['submission_ids' => array_fill(0, 51, $first->id)],
                ['submission_ids' => [$first->id, $other->id]],
                ['evaluator_id' => $viewer->id],
                ['reason' => '          short     '],
                ['reason' => str_repeat('x', 5001)],
                ['override_proposal_round_lock' => '1'],
            ] as $invalid) {
                $response = $this->postAs($manager, $url, array_replace($payload, $invalid));
                $this->assertStatus($response, 302, 'Invalid batch did not return validation feedback.');
                $this->assertTrue($response->getSession()->has('errors'), 'Invalid batch was accepted.');
                $this->assertTrue($first->fresh()->isSubmitted() && $second->fresh()->isSubmitted(), 'Invalid batch changed a finalized record.');
                $this->assertSame(0, ReworkRequest::where('procurement_id', $procurement->id)->count(), 'Invalid batch retained partial audit effects.');
            }

            // A later record, including a draft, makes its older finalized revision unavailable.
            $latest = $first->replicate();
            $latest->forceFill(['submitted_at' => now(), 'revision_number' => 2])->save();
            $stale = $this->postAs($manager, $url, $payload);
            $this->assertTrue(str_contains($stale->getSession()->get('errors')?->first('submission_ids') ?? '', 'current submitted'), 'A superseded finalized record was selectable.');
            request()->setUserResolver(fn () => $manager);
            $entries = collect($panelService->forProcurements([$procurement->fresh()])['groups'])->flatMap(fn ($g) => $g['entries']);
            $this->assertTrue(! $entries->contains('id', $first->id) && $entries->contains('id', $latest->id), 'Report choices expose a superseded revision.');
            $latest->forceFill(['submitted_at' => null, 'workflow_status' => EvaluationSubmission::WORKFLOW_DRAFT])->save();
            $this->assertTrue(! $panelService->currentSubmissions($procurement)->contains('id', $first->id), 'An older finalized record remained current while a newer draft exists.');
            $latest->delete();

            // A second record failing eligibility must roll back the already processed first record.
            $secondApplicant->forceFill(['status' => FormSubmission::STATUS_WITHDRAWN])->save();
            $failed = $this->postAs($manager, $url, $payload);
            $this->assertStatus($failed, 302, 'The ineligible target did not reject the whole batch.');
            $this->assertTrue($failed->getSession()->has('errors'), 'An ineligible target was reopened.');
            $this->assertTrue($first->fresh()->isSubmitted() && $second->fresh()->isSubmitted(), 'The failed batch was only partially rolled back.');
            $this->assertSame('submitted', $assignment->fresh()->status, 'An assignment remained in rework after batch rollback.');
            $this->assertSame(0, ReworkRequest::where('procurement_id', $procurement->id)->count(), 'Failed batch left an immutable audit cycle behind.');
            Mail::assertNothingSent();
            $secondApplicant->forceFill(['status' => FormSubmission::STATUS_SUBMITTED])->save();

            $procurement->forceFill(['awarded_at' => now()])->save();
            $locked = $this->postAs($administrator, $url, $payload + ['override_proposal_round_lock' => '1']);
            $this->assertTrue($locked->getSession()->has('errors'), 'Administrator override bypassed a final award lock.');
            request()->setUserResolver(fn () => $administrator);
            $lockedPanel = $panelService->forProcurements([$procurement->fresh()]);
            $this->assertSame(0, $lockedPanel['available_count'], 'An awarded procurement still offers selectable records.');
            $procurement->forceFill(['awarded_at' => null])->save();

            $portfolioRole = Role::firstOrCreate(['name' => 'Portfolio Coordinator']);
            $portfolio = $this->user('Unassigned Portfolio Coordinator', 'employee', $portfolioRole->id);
            $portfolio->permissions()->syncWithoutDetaching(Permission::whereIn('name', ['evaluations.view_all', 'evaluations.manage'])->pluck('id')->all());
            $this->assertStatus($this->postAs($portfolio, $url, $payload), 403, 'The batch crossed the assigned portfolio boundary.');
            request()->setUserResolver(fn () => $portfolio);
            $this->assertSame([], $panelService->forProcurements([$procurement])['groups'], 'The action data exposed another portfolio.');

            $historical = $first->replicate();
            $historical->forceFill(['submitted_at' => now()->subDays(2), 'revision_number' => 1])->save();
            foreach ($first->criteriaScores as $score) {
                $copy = $score->replicate();
                $copy->forceFill(['submission_id' => $historical->id])->save();
            }
            // Same applicant and evaluator, different form/phase: this result must remain reportable.
            $untouchedAssignment = $secondAssignment->replicate();
            $untouchedAssignment->forceFill(['form_submission_id' => $applicant->id])->save();
            $untouched = $second->replicate();
            $untouched->forceFill(['form_submission_id' => $applicant->id, 'evaluation_assignment_id' => $untouchedAssignment->id])->save();
            foreach ($second->criteriaScores as $score) {
                $copy = $score->replicate();
                $copy->forceFill(['submission_id' => $untouched->id])->save();
            }

            $transactions = $this->app['db.transactions'];
            $callbacksBefore = $transactions->getPendingTransactions()->sum(fn ($t) => count($t->getCallbacks()));
            $requested = $this->postAs($manager, $url, $payload + ['return_to' => 'https://outside.example.test/']);
            $this->assertStatus($requested, 302, 'A valid batch did not redirect.');
            $this->assertTrue(! $requested->getSession()->has('errors'), 'A valid batch failed: '.json_encode($requested->getSession()->get('errors')?->all()));
            $this->assertSame(route('reports.evaluations.procurement', $procurement), $requested->headers->get('Location'), 'The batch allowed an external return URL.');
            $reworks = ReworkRequest::where('procurement_id', $procurement->id)->orderBy('created_at')->get();
            $this->assertSame(2, $reworks->count(), 'The batch did not produce one audit snapshot per selected record.');
            foreach ($reworks as $rework) {
                $this->assertSame($reason, $rework->reason, 'The batch changed correction instructions.');
                $this->assertSame($this->snapshotHash($rework->source_snapshot), $rework->source_snapshot_hash, 'A source snapshot hash is invalid.');
                $this->assertSame(8.0, (float) data_get($rework->source_snapshot, 'submission.overall_score'), 'Original score was not preserved.');
                $this->assertTrue($rework->notified_at === null, 'Notification was marked delivered before outer commit.');
            }
            $this->assertTrue(! $first->fresh()->isSubmitted() && ! $second->fresh()->isSubmitted(), 'Reopened results remained final.');
            $report = app(EvaluationManagementReportService::class)->forProcurement($procurement->fresh(), Evaluation::TYPE_SERVICES);
            $this->assertSame(1, count($report['details']), 'Reopened evaluations fell back to an older finalized revision or excluded another form.');
            $this->assertSame($untouched->id, $report['audit'][0]['id'], 'A different form/phase for the same applicant was excluded.');
            $controller = app(\App\Http\Controllers\EvaluationReportController::class);
            $active = new ReflectionMethod($controller, 'activeReportSubmissions');
            $activeRows = $active->invoke($controller, EvaluationSubmission::where('procurement_id', $procurement->id)->whereNotNull('submitted_at')->get());
            $this->assertSame([$untouched->id], $activeRows->pluck('id')->all(), 'Controller summaries include a historical score for a pending task.');
            request()->setUserResolver(fn () => $manager);
            $pending = $panelService->forProcurements([$procurement->fresh()]);
            $this->assertSame(2, $pending['pending_count'], 'Pending rework disappeared from report controls.');
            $this->assertSame(1, $pending['available_count'], 'Pending rework is still selectable or an unrelated form was disabled.');
            $this->assertSame($reason, $pending['groups'][0]['entries'][0]['reason'], 'Pending instructions are not visible.');
            Mail::assertNothingSent();
            $callbacks = $transactions->getPendingTransactions()->flatMap(fn ($t) => $t->getCallbacks());
            $this->assertSame($callbacksBefore + 1, $callbacks->count(), 'The batch did not register exactly one after-commit notification.');

            // Run only the captured callback under Mail::fake while the fixture transaction is still rolled back below.
            $callbacks->last()();
            Mail::assertSent(EvaluationReworkBatchRequested::class, 1);
            Mail::assertNotSent(EvaluationReworkRequested::class);
            Mail::assertSent(EvaluationReworkBatchRequested::class, fn ($mail) => $mail->hasTo($evaluator->email) && $mail->reworks->count() === 2);
            $this->assertSame(2, ReworkRequest::where('procurement_id', $procurement->id)->whereNotNull('notified_at')->count(), 'Grouped notification status was not recorded for all records.');
            $mail = Mail::sent(EvaluationReworkBatchRequested::class)->first();
            $this->assertSame('smtp', $mail->mailer, 'The grouped notification ignored the dedicated evaluator mailer.');
            $html = $mail->render();
            $this->assertTrue(str_contains($html, '&lt;script&gt;unsafe()&lt;/script&gt;') && ! str_contains($html, '<script>unsafe()</script>'), 'Grouped email instructions were not escaped.');
            $this->assertTrue(str_contains($html, $applicant->procurement_submission_code) && str_contains($html, $secondApplicant->procurement_submission_code), 'Grouped email omitted a selected applicant.');
            $duplicate = $this->postAs($manager, $url, $payload);
            $this->assertTrue($duplicate->getSession()->has('errors'), 'Duplicate rework reopened pending evaluations.');
            $this->assertSame(2, ReworkRequest::where('procurement_id', $procurement->id)->count(), 'Duplicate batch created another audit cycle.');
            Mail::assertSent(EvaluationReworkBatchRequested::class, 1);

            [$eoiProcurement, $eoiEvaluation, , , $eoiAssignment, $eoiApplicant, $eoiCurrent] = $this->fixture($manager, $evaluator, $vendor);
            $eoiEvaluation->forceFill(['type' => Evaluation::TYPE_EOI])->save();
            $eoiApplicant->forceFill(['status' => FormSubmission::STATUS_EOI_EVALUATION])->save();
            $eoiCurrent->criteriaScores()->update(['score' => 0, 'decision' => 2]);
            $eoiHistorical = $eoiCurrent->replicate();
            $eoiHistorical->forceFill(['submitted_at' => now()->subDays(2)])->save();
            foreach ($eoiCurrent->criteriaScores()->get() as $score) {
                $copy = $score->replicate();
                $copy->forceFill(['submission_id' => $eoiHistorical->id])->save();
            }
            $round = EoiTechnicalProposalRound::create([
                'procurement_id' => $eoiProcurement->id, 'round_number' => 1, 'title' => 'Prepared round',
                'status' => EoiTechnicalProposalRound::STATUS_DRAFT, 'created_by' => $manager->id,
            ]);
            $eoiPayload = ['submission_ids' => [$eoiCurrent->id], 'evaluator_id' => $evaluator->id, 'reason' => 'Recheck the categorical decision and supporting evidence.'];
            $eoiUrl = route('eval.panel.rework.batch', $eoiProcurement);
            $ordinaryLocked = $this->postAs($manager, $eoiUrl, $eoiPayload);
            $this->assertTrue($ordinaryLocked->getSession()->has('errors'), 'Prepared proposal round was bypassed without an administrator override.');
            request()->setUserResolver(fn () => $manager);
            $this->assertSame(0, $panelService->forProcurements([$eoiProcurement])['available_count'], 'Ordinary manager was offered the administrator override.');
            request()->setUserResolver(fn () => $administrator);
            $overridePanel = $panelService->forProcurements([$eoiProcurement]);
            $this->assertTrue($overridePanel['groups'][0]['entries'][0]['requires_override'], 'EOI proposal-round override is not made explicit.');
            $overridden = $this->postAs($administrator, $eoiUrl, $eoiPayload + ['override_proposal_round_lock' => '1']);
            $this->assertStatus($overridden, 302, 'Explicit administrator EOI rework override failed.');
            $eoiRework = ReworkRequest::where('evaluation_submission_id', $eoiCurrent->id)->firstOrFail();
            $this->assertTrue((bool) data_get($eoiRework->source_snapshot, 'event.workflow_lock_override.confirmed'), 'Administrator override was not preserved in the audit snapshot.');
            $eoiReport = app(\App\Services\EoiQualificationService::class)->buildProcurementReport($eoiProcurement->fresh());
            $this->assertSame(0, $eoiReport['stats']['submitted_evaluations'], 'EOI qualification fell back to an old finalized decision while rework is pending.');
            $this->assertTrue(! $eoiReport['applicants']->first()['panel_complete'], 'EOI panel remains complete during rework.');

            // Preserve other stages and rounds of the same form/applicant/evaluator.
            $technicalAssignment = $eoiAssignment->replicate();
            $technicalAssignment->forceFill(['workflow_stage' => EvaluationAssignment::STAGE_TECHNICAL_PROPOSAL, 'technical_proposal_round_id' => $round->id])->save();
            $technical = $eoiHistorical->replicate();
            $technical->forceFill(['evaluation_assignment_id' => $technicalAssignment->id])->save();
            $anotherRound = $round->replicate();
            $anotherRound->forceFill(['round_number' => 2])->save();
            $anotherAssignment = $technicalAssignment->replicate();
            $anotherAssignment->forceFill(['technical_proposal_round_id' => $anotherRound->id])->save();
            $anotherTechnical = $technical->replicate();
            $anotherTechnical->forceFill(['evaluation_assignment_id' => $anotherAssignment->id])->save();
            $remaining = app(\App\Services\EvaluationReworkGuard::class)->excludePendingTasks(collect([$eoiHistorical, $technical, $anotherTechnical]));
            $this->assertSame([$technical->id, $anotherTechnical->id], $remaining->pluck('id')->all(), 'Pending application rework excluded another stage or technical-proposal round.');
            $currentRoundRecords = $panelService->currentSubmissions($eoiProcurement, $evaluator->id);
            $this->assertTrue($currentRoundRecords->contains('id', $technical->id) && $currentRoundRecords->contains('id', $anotherTechnical->id), 'Current-record selection merged distinct proposal rounds.');

            $fakeMailer = Mail::getFacadeRoot();
            Mail::swap(new class
            {
                public function mailer($name)
                {
                    if ($name !== 'smtp') {
                        throw new LogicException('Unexpected test mailer.');
                    }
                    throw new RuntimeException('Simulated evaluator mail transport failure.');
                }
            });
            try {
                $notify = new ReflectionMethod(app(\App\Http\Controllers\EvaluationReworkController::class), 'notifyBatch');
                $warning = $notify->invoke(app(\App\Http\Controllers\EvaluationReworkController::class), $reworks);
                $this->assertTrue(str_contains($warning ?? '', 'could not be delivered'), 'Transport failure did not produce clear notification feedback.');
                foreach ($reworks as $rework) {
                    $record = $rework->fresh();
                    $this->assertTrue($record->notified_at === null, 'A failed email was marked delivered.');
                    $this->assertSame('Simulated evaluator mail transport failure.', $record->notification_error, 'Transport failure was not recorded on every reopened evaluation.');
                    $this->assertSame(ReworkRequest::STATUS_PENDING, $record->status, 'Email failure changed the rework state.');
                }
            } finally {
                Mail::swap($fakeMailer);
            }

            echo "EVALUATION_REPORT_BATCH_REWORK_SMOKE_OK current records, phases, validation, portfolio, atomic rollback, audit snapshots, pending visibility, report exclusion and grouped after-commit mail\n";
        } finally {
            DB::rollBack();
            config(['evaluation_rework.mailer' => $originalMailer, 'mail.default' => $originalDefaultMailer]);
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

(new EvaluationReportBatchReworkSmoke($app))->run();
