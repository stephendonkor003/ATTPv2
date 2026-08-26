<?php

use App\Models\DynamicForm;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
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

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class EvaluatorWorkspaceSmoke
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
        Storage::fake('public');
        DB::beginTransaction();

        try {
            $suffix = Str::lower(Str::random(10));
            $evaluator = $this->userFixture('Ordinary Assigned Evaluator', 'staff', "workspace-evaluator-{$suffix}");
            $otherEvaluator = $this->userFixture('Other Assigned Evaluator', 'vendor', "workspace-other-{$suffix}");
            $vendor = $this->userFixture('Evaluation Applicant', 'vendor', "workspace-vendor-{$suffix}");

            $this->assertTrue(
                ! $evaluator->hasPermission('evaluations.evaluate'),
                'The ordinary evaluator unexpectedly had evaluation capability before assignment.'
            );
            $this->assertTrue(
                ! DB::table('user_permission')
                    ->join('permissions', 'permissions.id', '=', 'user_permission.permission_id')
                    ->where('user_permission.user_id', $evaluator->id)
                    ->where('permissions.name', 'evaluations.evaluate')
                    ->exists(),
                'The ordinary evaluator has a stored evaluations.evaluate permission.'
            );

            $services = $this->workflowFixture(
                $evaluator,
                $vendor,
                Evaluation::TYPE_SERVICES,
                'Services evaluation',
                [
                    ['name' => 'Technical approach', 'max_score' => 10],
                ]
            );
            $goods = $this->workflowFixture(
                $evaluator,
                $vendor,
                Evaluation::TYPE_GOODS,
                'Goods evaluation',
                [
                    ['name' => 'Specification compliance', 'max_score' => null],
                    ['name' => 'Delivery compliance', 'max_score' => null],
                ]
            );
            $eoi = $this->workflowFixture(
                $evaluator,
                $vendor,
                Evaluation::TYPE_EOI,
                'Expression of interest evaluation',
                [
                    ['name' => 'Relevant experience', 'max_score' => null],
                    ['name' => 'Team capability', 'max_score' => null],
                    ['name' => 'Institutional capacity', 'max_score' => null],
                ]
            );
            $other = $this->workflowFixture(
                $otherEvaluator,
                $vendor,
                Evaluation::TYPE_SERVICES,
                'Other evaluator assignment',
                [
                    ['name' => 'Protected score', 'max_score' => 5],
                ]
            );

            $evaluator->unsetRelation('permissions');
            $evaluator->unsetRelation('role');
            $this->assertTrue(
                $evaluator->hasPermission('evaluations.evaluate'),
                'Assignment ownership did not derive evaluations.evaluate capability for the ordinary user.'
            );

            $worklist = $this->actingAsVerified($evaluator)->get(route('my.eval.index'));
            $this->assertResponseStatus($worklist, 200, 'The assigned evaluator could not open My Evaluations.');
            foreach ([$services, $goods, $eoi] as $fixture) {
                $this->assertResponseContains(
                    $worklist,
                    $fixture['procurement']->title,
                    'My Evaluations did not show an assigned procurement.'
                );
            }
            $this->assertResponseDoesNotContain(
                $worklist,
                $other['procurement']->title,
                'My Evaluations exposed another evaluator assignment.'
            );
            $this->assertResponseContains($worklist, 'Services', 'The Services method guide is missing.');
            $this->assertResponseContains($worklist, 'Goods', 'The Goods method guide is missing.');
            $this->assertResponseContains($worklist, 'EOI', 'The EOI method guide is missing.');

            $compareDenied = $this->actingAsVerified($evaluator)->get(
                route('my.eval.compare', $services['assignment'])
            );
            $this->assertResponseStatus(
                $compareDenied,
                403,
                'An ordinary evaluator could access peer-evaluator comparison data.'
            );

            $vendorEvaluatorWorklist = $this->actingAsVerified($otherEvaluator)->get(route('my.eval.index'));
            $this->assertResponseStatus(
                $vendorEvaluatorWorklist,
                200,
                'An explicitly assigned vendor-typed evaluator was redirected away from My Evaluations.'
            );
            $this->assertResponseContains(
                $vendorEvaluatorWorklist,
                'My Evaluations',
                'The vendor evaluator workspace did not expose its evaluation navigation.'
            );
            $this->assertResponseContains(
                $vendorEvaluatorWorklist,
                'ATTP Vendor Portal',
                'The assigned vendor evaluator did not retain the vendor portal shell.'
            );

            $this->assertStartCreatesAndReusesDraft($evaluator, $services);
            $this->assertServicesDraftRules($evaluator, $services);
            $this->assertGoodsDraft($evaluator, $goods);
            $this->assertEoiDraftAndNumberedChoices($evaluator, $eoi);

            $forbidden = $this->actingAsVerified($evaluator)->get(
                route('my.eval.start', [$other['assignment'], $other['applicant']])
            );
            $this->assertResponseStatus(
                $forbidden,
                403,
                'An evaluator opened an assignment owned by another user.'
            );

            $this->assertFinalSubmissionAndImmutability($evaluator, $services);

            echo "EVALUATOR_WORKSPACE_SMOKE_OK\n";
        } finally {
            DB::rollBack();
            Storage::disk('local')->deleteDirectory('evaluation_proofs');
            $this->app['auth']->forgetGuards();
        }
    }

    private function assertStartCreatesAndReusesDraft(User $evaluator, array $fixture): void
    {
        $route = route('my.eval.start', [$fixture['assignment'], $fixture['applicant']]);

        $firstResponse = $this->actingAsVerified($evaluator)->get($route);
        $this->assertResponseStatus($firstResponse, 200, 'Starting an assigned Services evaluation failed.');

        $firstDraft = $this->submissionFor($fixture);
        $this->assertTrue($firstDraft !== null, 'Starting an evaluation did not create its draft.');
        $this->assertTrue($firstDraft->submitted_at === null, 'A new evaluation draft was already submitted.');

        $draftView = $this->actingAsVerified($evaluator)->get(
            route('my.eval.view', [$fixture['assignment'], $fixture['applicant']])
        );
        $this->assertResponseStatus($draftView, 404, 'An unfinished draft was presented as a final evaluation.');

        $secondResponse = $this->actingAsVerified($evaluator)->get($route);
        $this->assertResponseStatus($secondResponse, 200, 'Continuing an assigned Services evaluation failed.');

        $drafts = $this->submissionQuery($fixture)->get();
        $this->assertTrue($drafts->count() === 1, 'Starting the same task twice created duplicate drafts.');
        $this->assertTrue(
            (string) $drafts->first()->id === (string) $firstDraft->id,
            'Continuing an evaluation did not reuse the original draft.'
        );
    }

    private function assertServicesDraftRules(User $evaluator, array $fixture): void
    {
        $criterion = $fixture['criteria']->first();
        $section = $fixture['section'];
        $route = route('my.eval.save', [$fixture['assignment'], $fixture['applicant']]);

        $valid = $this->postJsonAs($evaluator, $route, [
            'criteria' => [$criterion->id => '7.25'],
            'sections' => [
                $section->id => [
                    'strengths' => '  Strong technical response.  ',
                    'weaknesses' => '  Delivery assumptions need clarification.  ',
                ],
            ],
        ]);
        $this->assertResponseStatus($valid, 200, 'A valid Services draft was not saved.');

        $draft = $this->submissionFor($fixture)->fresh();
        $score = $draft->criteriaScores()->where('evaluation_criteria_id', $criterion->id)->firstOrFail();
        $notes = $draft->sectionScores()->where('evaluation_section_id', $section->id)->firstOrFail();

        $this->assertTrue((float) $score->score === 7.25, 'The Services criterion score did not persist.');
        $this->assertTrue($score->decision === null, 'A Services score retained a categorical decision.');
        $this->assertTrue((float) $draft->overall_score === 7.25, 'The Services overall total was not recalculated.');
        $this->assertTrue((float) $notes->section_score === 7.25, 'The Services section total was not recalculated.');
        $this->assertTrue(
            $notes->strengths === 'Strong technical response.'
                && $notes->weaknesses === 'Delivery assumptions need clarification.',
            'The Services section notes were not trimmed and persisted.'
        );

        foreach ([-0.01, 10.01] as $invalidScore) {
            $response = $this->postJsonAs($evaluator, $route, [
                'criteria' => [$criterion->id => $invalidScore],
                'sections' => [],
            ]);
            $this->assertResponseStatus(
                $response,
                422,
                "The invalid Services score {$invalidScore} was accepted."
            );
        }

        $score->refresh();
        $this->assertTrue((float) $score->score === 7.25, 'Rejected Services scores changed the saved draft.');
    }

    private function assertGoodsDraft(User $evaluator, array $fixture): void
    {
        $this->assertResponseStatus(
            $this->actingAsVerified($evaluator)->get(
                route('my.eval.start', [$fixture['assignment'], $fixture['applicant']])
            ),
            200,
            'The Goods evaluation workspace did not open.'
        );

        $criteria = $fixture['criteria']->values();
        $response = $this->postJsonAs(
            $evaluator,
            route('my.eval.save', [$fixture['assignment'], $fixture['applicant']]),
            [
                'criteria' => [
                    $criteria[0]->id => ['decision' => 1, 'comment' => '  Fully compliant.  '],
                    $criteria[1]->id => ['decision' => 0, 'comment' => '  Lead time is not compliant.  '],
                ],
                'sections' => [
                    $fixture['section']->id => [
                        'strengths' => 'Specification is clear.',
                        'weaknesses' => 'Delivery risk remains.',
                    ],
                ],
            ]
        );
        $this->assertResponseStatus($response, 200, 'A valid Goods draft was not saved.');

        $draft = $this->submissionFor($fixture)->fresh();
        $scores = $draft->criteriaScores()->orderBy('evaluation_criteria_id')->get();
        $decisions = $scores->pluck('decision')->map(fn ($decision): int => (int) $decision)->sort()->values()->all();

        $this->assertTrue($decisions === [0, 1], 'Goods Yes and No decisions did not both persist.');
        $this->assertTrue(
            $scores->pluck('comment')->sort()->values()->all() === [
                'Fully compliant.',
                'Lead time is not compliant.',
            ],
            'Goods evaluator comments were not trimmed and persisted.'
        );
        $this->assertTrue($scores->every(fn ($score) => $score->score === null), 'Goods decisions stored numeric scores.');
        $this->assertTrue($draft->overall_score === null, 'Goods produced an overall numeric total.');
        $this->assertTrue(
            $draft->sectionScores()->whereNotNull('section_score')->doesntExist(),
            'Goods produced a numeric section total.'
        );
    }

    private function assertEoiDraftAndNumberedChoices(User $evaluator, array $fixture): void
    {
        $workspace = $this->actingAsVerified($evaluator)->get(
            route('my.eval.start', [$fixture['assignment'], $fixture['applicant']])
        );
        $this->assertResponseStatus($workspace, 200, 'The EOI evaluation workspace did not open.');

        $html = (string) $workspace->getContent();
        foreach ([
            1 => 'Qualified',
            2 => 'Average Qualified',
            3 => 'Not Qualified',
        ] as $number => $label) {
            $pattern = '/<span class="decision-number">'.preg_quote((string) $number, '/').'<\/span>\s*<span>'
                .preg_quote($label, '/').'<\/span>/';
            $this->assertTrue(
                preg_match($pattern, $html) === 1,
                "The EOI workspace did not display numbered choice {$number}: {$label}."
            );
        }

        $criteria = $fixture['criteria']->values();
        $response = $this->postJsonAs(
            $evaluator,
            route('my.eval.save', [$fixture['assignment'], $fixture['applicant']]),
            [
                'criteria' => [
                    $criteria[0]->id => ['decision' => 2, 'comment' => 'Strong evidence.'],
                    $criteria[1]->id => ['decision' => 1, 'comment' => 'Partially demonstrated.'],
                    $criteria[2]->id => ['decision' => 0, 'comment' => 'Evidence not provided.'],
                ],
                'sections' => [
                    $fixture['section']->id => [
                        'strengths' => 'Relevant regional work.',
                        'weaknesses' => 'Some evidence gaps.',
                    ],
                ],
            ]
        );
        $this->assertResponseStatus($response, 200, 'A valid EOI draft was not saved.');

        $draft = $this->submissionFor($fixture)->fresh();
        $codes = $draft->criteriaScores()
            ->pluck('decision')
            ->map(fn ($decision): int => (int) $decision)
            ->sortDesc()
            ->values()
            ->all();

        $this->assertTrue($codes === [2, 1, 0], 'EOI persistence no longer uses the established 2/1/0 codes.');
        $this->assertTrue(
            $draft->criteriaScores()->whereNotNull('score')->doesntExist(),
            'EOI decisions stored numeric scores.'
        );
        $this->assertTrue($draft->overall_score === null, 'EOI produced an overall numeric total.');
    }

    private function assertFinalSubmissionAndImmutability(User $evaluator, array $fixture): void
    {
        $criterion = $fixture['criteria']->first();
        $response = $this->postAs(
            $evaluator,
            route('my.eval.submit', [$fixture['assignment'], $fixture['applicant']]),
            [
                'criteria' => [$criterion->id => '8.75'],
                'sections' => [
                    $fixture['section']->id => [
                        'strengths' => 'Strong and complete response.',
                        'weaknesses' => 'Minor delivery risk.',
                    ],
                ],
                'video' => UploadedFile::fake()->create('identity.webm', 24, 'video/webm'),
            ]
        );
        $this->assertResponseStatus($response, 302, 'A complete Services evaluation was not submitted.');

        $submitted = $this->submissionFor($fixture)->fresh();
        $this->assertTrue($submitted->submitted_at !== null, 'Final submission did not set submitted_at.');
        $this->assertTrue((float) $submitted->overall_score === 8.75, 'Final Services total is incorrect.');
        $this->assertTrue(
            filled($submitted->video_path) && Storage::disk('local')->exists($submitted->video_path),
            'The final verification video was not stored privately.'
        );
        $this->assertTrue(
            $fixture['assignment']->fresh()->status === 'submitted',
            'Completing the only assigned application did not update assignment status.'
        );

        $immutable = $this->postJsonAs(
            $evaluator,
            route('my.eval.save', [$fixture['assignment'], $fixture['applicant']]),
            [
                'criteria' => [$criterion->id => 1],
                'sections' => [],
            ]
        );
        $this->assertResponseStatus($immutable, 403, 'A submitted evaluation accepted a draft mutation.');

        $submitted->refresh();
        $savedScore = $submitted->criteriaScores()
            ->where('evaluation_criteria_id', $criterion->id)
            ->value('score');
        $this->assertTrue((float) $savedScore === 8.75, 'A rejected post-submit write changed the score.');

        $restart = $this->actingAsVerified($evaluator)->get(
            route('my.eval.start', [$fixture['assignment'], $fixture['applicant']])
        );
        $this->assertResponseStatus($restart, 302, 'A submitted evaluation still opened the editable workspace.');
        $this->assertTrue(
            str_contains((string) $restart->headers->get('Location'), '/view/'),
            'A submitted evaluation did not redirect to its read-only view.'
        );
    }

    /**
     * @param  array<int, array{name: string, max_score: int|float|null}>  $criteriaDefinitions
     * @return array{procurement: Procurement, form: DynamicForm, applicant: FormSubmission, evaluation: Evaluation, section: EvaluationSection, criteria: \Illuminate\Support\Collection, assignment: EvaluationAssignment}
     */
    private function workflowFixture(
        User $evaluator,
        User $vendor,
        string $type,
        string $label,
        array $criteriaDefinitions
    ): array {
        $token = Str::upper(Str::random(8));
        $procurement = Procurement::create([
            'title' => "{$label} {$token}",
            'reference_no' => "EVAL-{$token}",
            'description' => 'Evaluator workspace smoke fixture.',
            'fiscal_year' => 2026,
            'status' => 'published',
            'created_by' => $evaluator->id,
        ]);
        $form = DynamicForm::create([
            'name' => "Application form {$token}",
            'applies_to' => 'procurement',
            'status' => 'approved',
            'is_active' => true,
            'created_by' => $evaluator->id,
            'procurement_id' => $procurement->id,
            'approved_at' => now(),
            'approved_by' => $evaluator->id,
        ]);
        $applicant = FormSubmission::create([
            'procurement_id' => $procurement->id,
            'procurement_submission_code' => "APP-{$token}",
            'form_id' => $form->id,
            'submitted_by' => $vendor->id,
            'status' => FormSubmission::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
        $evaluation = Evaluation::create([
            'name' => "{$label} form",
            'description' => 'Evaluation workspace smoke template.',
            'status' => 'active',
            'type' => $type,
            'created_by' => $evaluator->id,
        ]);
        $section = EvaluationSection::create([
            'evaluation_id' => $evaluation->id,
            'name' => "{$label} criteria",
            'description' => 'Complete every criterion in this section.',
            'show_subtotal' => true,
            'sort_order' => 1,
        ]);
        $criteria = collect($criteriaDefinitions)->map(function (array $definition) use ($section) {
            return EvaluationCriteria::create([
                'evaluation_section_id' => $section->id,
                'name' => $definition['name'],
                'description' => 'Smoke-test evaluation criterion.',
                'max_score' => $definition['max_score'],
            ]);
        });
        $assignment = EvaluationAssignment::create([
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $procurement->id,
            'form_submission_id' => $applicant->id,
            'user_id' => $evaluator->id,
            'assigned_by' => $evaluator->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        return compact(
            'procurement',
            'form',
            'applicant',
            'evaluation',
            'section',
            'criteria',
            'assignment'
        );
    }

    private function userFixture(string $name, string $userType, string $emailPrefix): User
    {
        $user = new User;
        $user->forceFill([
            'name' => $name,
            'email' => $emailPrefix.'@example.test',
            'password' => Hash::make('Password123!'),
            'user_type' => $userType,
            'role_id' => null,
            'email_verified_at' => now(),
            'must_change_password' => false,
            'password_changed_at' => now(),
            'otp_verified_at' => now(),
            'is_disabled' => false,
            'is_blacklisted' => false,
        ])->save();

        return $user;
    }

    private function submissionFor(array $fixture): ?EvaluationSubmission
    {
        return $this->submissionQuery($fixture)->first();
    }

    private function submissionQuery(array $fixture)
    {
        return EvaluationSubmission::query()->where([
            'evaluation_id' => $fixture['evaluation']->id,
            'procurement_id' => $fixture['procurement']->id,
            'evaluator_id' => $fixture['assignment']->user_id,
            'form_submission_id' => $fixture['applicant']->id,
        ]);
    }

    private function postJsonAs(User $user, string $uri, array $data)
    {
        $token = Str::random(40);
        $this->actingAsVerified($user)->withSession(['_token' => $token]);

        return $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])->postJson($uri, ['_token' => $token, ...$data]);
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

    private function assertTrue(bool $condition, string $message): void
    {
        if (! $condition) {
            throw new RuntimeException($message);
        }
    }

    private function assertResponseStatus($response, int $expected, string $message): void
    {
        $actual = $response->getStatusCode();
        if ($actual !== $expected) {
            $body = trim(strip_tags((string) $response->getContent()));
            throw new RuntimeException(
                "{$message} Expected {$expected}, received {$actual}. Response: ".Str::limit($body, 500)
            );
        }
    }

    private function assertResponseContains($response, string $needle, string $message): void
    {
        $this->assertTrue(str_contains((string) $response->getContent(), $needle), $message);
    }

    private function assertResponseDoesNotContain($response, string $needle, string $message): void
    {
        $this->assertTrue(! str_contains((string) $response->getContent(), $needle), $message);
    }
}

(new EvaluatorWorkspaceSmoke($app))->run();
