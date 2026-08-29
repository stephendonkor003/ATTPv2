<?php

use App\Mail\EvaluationReworkRequested;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

it('wires a permission scoped and auditable panel evaluation rework lifecycle', function () {
    $root = dirname(__DIR__, 2);
    $routes = file_get_contents($root.'/routes/web.php');
    $service = file_get_contents($root.'/app/Services/EvaluationReworkService.php');
    $proposalService = file_get_contents($root.'/app/Services/EoiTechnicalProposalService.php');
    $reworkGuard = file_get_contents($root.'/app/Services/EvaluationReworkGuard.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/EvaluationReworkController.php');
    $legacyPdfController = file_get_contents($root.'/app/Http/Controllers/EvaluationPanelPdfController.php');
    $panel = file_get_contents($root.'/resources/views/evaluations/panel/show.blade.php');
    $register = file_get_contents($root.'/resources/views/evaluations/panel/partials/rework-register.blade.php');
    $worklist = file_get_contents($root.'/resources/views/evaluations/my.blade.php');
    $workspace = file_get_contents($root.'/resources/views/evaluations/submit.blade.php');
    $migration = file_get_contents(
        $root.'/database/migrations/2026_08_29_000001_extend_rework_requests_for_evaluation_submissions.php'
    );

    expect($routes)
        ->toContain("->name('rework')")
        ->toContain("->middleware('permission:evaluations.manage')")
        ->and($controller)
        ->toContain("can('evaluations.manage')")
        ->toContain('evaluationSubmissionIsInAssignedPortfolio')
        ->toContain('Mail::to($evaluatorEmail)->send(new EvaluationReworkRequested($rework))')
        ->and($legacyPdfController)
        ->toContain('$submission->isSubmitted()')
        ->toContain('$submission->workflow_status === EvaluationSubmission::WORKFLOW_SUBMITTED')
        ->toContain("->where('workflow_status', EvaluationSubmission::WORKFLOW_SUBMITTED)")
        ->and($service)
        ->toContain('pg_advisory_xact_lock')
        ->toContain("'source_snapshot' => \$sourceSnapshot")
        ->toContain("'submitted_at' => null")
        ->toContain("'status' => 'rework'")
        ->toContain('technical-proposal round has already started')
        ->toContain('$this->targetResolver->assertEligible(')
        ->toContain('applicant evaluation record has been released')
        ->toContain('$evaluator->hasActiveLoginBlock()')
        ->toContain("\$evaluator->hasPermission('evaluations.evaluate')")
        ->toContain('$requester->isAdmin() || $requester->isSuperAdmin()')
        ->toContain("\$event['workflow_lock_override'] = \$workflowLockOverride")
        ->toContain("'source_snapshot_hash' => \$this->snapshotHash(\$sourceSnapshot)")
        ->and($proposalService)
        ->toContain('buildProcurementReport($lockedProcurement)')
        ->toContain('The selected applicants are no longer qualified')
        ->toContain('assertTechnicalProposalCanContinue($lockedApplicant)')
        ->and($reworkGuard)
        ->toContain('Proposal uploads and compliance decisions are paused')
        ->and($migration)
        ->toContain("'workflow_status'")
        ->toContain("'revision_number'")
        ->toContain("'evaluation_submission_id'")
        ->toContain('eval_rework_one_pending_per_submission')
        ->toContain('->restrictOnDelete()')
        ->toContain("'source_snapshot_hash'")
        ->toContain('Evaluation rework history exists')
        ->and($panel)
        ->toContain('id="evaluationReworkModal"')
        ->toContain('data-rework-open')
        ->toContain('name="override_proposal_round_lock"')
        ->toContain('Administrator override required')
        ->and($register)
        ->toContain('Review submitted evaluations')
        ->toContain('Send for rework')
        ->toContain('Override lock & rework')
        ->toContain('data-rework-requires-proposal-round-override')
        ->and($worklist)
        ->toContain('Rework requested')
        ->toContain('Revise evaluation')
        ->and($workspace)
        ->toContain('This evaluation has been returned for correction')
        ->toContain('$openRework->reason');
});

it('renders individualized and escaped evaluator rework instructions', function () {
    $root = dirname(__DIR__, 2);
    $app = require $root.'/bootstrap/app.php';

    try {
        $app->make(Kernel::class)->bootstrap();

        $evaluator = (new User)->forceFill([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Dr Rework Evaluator',
            'email' => 'rework-evaluator@example.test',
        ]);
        $requester = (new User)->forceFill([
            'id' => '22222222-2222-4222-8222-222222222222',
            'name' => 'Evaluation Administrator',
        ]);
        $evaluation = (new Evaluation)->forceFill([
            'id' => '33333333-3333-4333-8333-333333333333',
            'name' => 'Technical Services Evaluation',
            'type' => Evaluation::TYPE_SERVICES,
        ]);
        $procurement = (new Procurement)->forceFill([
            'id' => '44444444-4444-4444-8444-444444444444',
            'title' => 'Continental research services',
            'reference_no' => 'ATTP-REWORK-026',
        ]);
        $applicant = (new FormSubmission)->forceFill([
            'id' => '55555555-5555-4555-8555-555555555555',
            'procurement_submission_code' => 'APP-REWORK-01',
        ]);
        $assignment = (new EvaluationAssignment)->forceFill([
            'id' => '66666666-6666-4666-8666-666666666666',
        ]);
        $rework = (new ReworkRequest)->forceFill([
            'id' => '77777777-7777-4777-8777-777777777777',
            'reason' => 'Correct the methodology score and explain <script>alert("unsafe")</script> evidence.',
            'source_revision_number' => 1,
            'requested_at' => now()->setDate(2026, 8, 29)->setTime(10, 30),
        ]);
        $rework->setRelation('evaluator', $evaluator);
        $rework->setRelation('requester', $requester);
        $rework->setRelation('evaluation', $evaluation);
        $rework->setRelation('procurement', $procurement);
        $rework->setRelation('applicant', $applicant);
        $rework->setRelation('assignment', $assignment);

        $mailable = new EvaluationReworkRequested($rework);
        $mailable->build();
        $html = $app['view']->make('emails.evaluations.rework-requested', compact('rework'))->render();
    } finally {
        restore_error_handler();
        restore_exception_handler();
    }

    expect($mailable->subject)->toBe('Evaluation Rework Required: ATTP-REWORK-026')
        ->and($html)
        ->toContain('Evaluation Rework Required')
        ->toContain('Dr Rework Evaluator')
        ->toContain('Evaluation Administrator')
        ->toContain('Technical Services Evaluation')
        ->toContain('Continental research services')
        ->toContain('APP-REWORK-01')
        ->toContain('Revision 2')
        ->toContain('Correct the methodology score')
        ->toContain('&lt;script&gt;alert(&quot;unsafe&quot;)&lt;/script&gt;')
        ->not->toContain('<script>alert("unsafe")</script>')
        ->toContain('Open and revise evaluation');
});
