<?php

use App\Mail\EvaluationCompleted;
use App\Models\Evaluation;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;

it('provides and renders the evaluation completion email with its important details', function () {
    $root = dirname(__DIR__, 2);
    $mailableSource = file_get_contents($root.'/app/Mail/EvaluationCompleted.php');
    $viewPath = $root.'/resources/views/emails/evaluations/completed.blade.php';

    expect(is_file($viewPath))->toBeTrue()
        ->and($mailableSource)
        ->toContain("->view('emails.evaluations.completed', compact('submission', 'overallMax'))")
        ->toContain("->attachData(")
        ->toContain("'mime' => 'application/pdf'");

    $app = require $root.'/bootstrap/app.php';

    try {
        $app->make(Kernel::class)->bootstrap();

        $evaluation = (new Evaluation)->forceFill([
            'id' => '11111111-1111-4111-8111-111111111111',
            'name' => 'Technical services scoring',
            'type' => Evaluation::TYPE_SERVICES,
        ]);
        $procurement = (new Procurement)->forceFill([
            'id' => '22222222-2222-4222-8222-222222222222',
            'title' => 'Regional data platform services',
            'reference_no' => 'ATTP-SVC-2026-014',
        ]);
        $vendor = (new User)->forceFill([
            'id' => '33333333-3333-4333-8333-333333333333',
            'name' => 'Acme Research',
        ]);
        $applicant = (new FormSubmission)->forceFill([
            'id' => '44444444-4444-4444-8444-444444444444',
            'procurement_submission_code' => 'EV-COMP-2026',
        ]);
        $applicant->setRelation('submitter', $vendor);

        $evaluator = (new User)->forceFill([
            'id' => '55555555-5555-4555-8555-555555555555',
            'name' => 'Dr Test Evaluator',
        ]);
        $submission = (new EvaluationSubmission)->forceFill([
            'id' => '66666666-6666-4666-8666-666666666666',
            'overall_score' => 82.75,
            'submitted_at' => now()->setDate(2026, 8, 26)->setTime(14, 30),
        ]);
        $submission->setRelation('evaluation', $evaluation);
        $submission->setRelation('procurement', $procurement);
        $submission->setRelation('applicant', $applicant);
        $submission->setRelation('evaluator', $evaluator);

        $mailable = new EvaluationCompleted($submission);
        $html = $app['view']->make('emails.evaluations.completed', [
            'submission' => $mailable->submission,
            'overallMax' => 100,
        ])->render();
    } finally {
        restore_error_handler();
        restore_exception_handler();
    }

    expect($html)
        ->toContain('Evaluation submitted')
        ->toContain('EV-COMP-2026')
        ->toContain('Regional data platform services')
        ->toContain('ATTP-SVC-2026-014')
        ->toContain('Technical services scoring')
        ->toContain('Services')
        ->toContain('Acme Research')
        ->toContain('Dr Test Evaluator')
        ->toContain('82.75 / 100.00')
        ->toContain('A detailed PDF report is attached to this email.')
        ->toContain('Open My Evaluations');
});
