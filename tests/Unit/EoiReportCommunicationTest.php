<?php

use App\Mail\ApplicantEvaluationRecordMail;
use App\Mail\QualifiedProposalInvitationMail;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationAttachment;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use App\Services\EoiQualificationService;
use App\Services\EoiReportCommunicationService;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function communicationApplicant(string $email, array $userAttributes = []): FormSubmission
{
    $user = (new User)->forceFill(array_merge([
        'id' => (string) Str::uuid(),
        'name' => 'Applicant Contact',
        'email' => $email,
        'user_type' => 'vendor',
        'is_disabled' => false,
        'is_blacklisted' => false,
    ], $userAttributes));
    $applicant = (new FormSubmission)->forceFill([
        'id' => (string) Str::uuid(),
        'submitted_by' => $user->id,
        'procurement_submission_code' => 'EOI-TEST-001',
    ]);
    $applicant->setRelation('submitter', $user);
    $applicant->setRelation('values', new EloquentCollection);

    return $applicant;
}

function communicationRow(
    FormSubmission $applicant,
    string $outcome,
    bool $complete,
    bool $advance,
    ?bool $withinShortlist = null
): array {
    $row = [
        'applicant' => $applicant,
        'panel_complete' => $complete,
        'can_advance' => $advance,
        'outcome' => [
            'code' => $outcome,
            'label' => Str::headline($outcome),
        ],
        'next_stage' => $advance ? 'Technical Evaluation' : 'Does not advance',
    ];

    if ($withinShortlist !== null) {
        $row['within_qualified_shortlist'] = $withinShortlist;
    }

    return $row;
}

it('uses finalized web-report rows as the only evaluation-record and proposal recipients', function () {
    $service = new EoiReportCommunicationService;
    $fullyQualified = communicationRow(
        communicationApplicant('fully@example.test'),
        EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
        true,
        true
    );
    $averageQualified = communicationRow(
        communicationApplicant('average@example.test'),
        EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
        true,
        true
    );
    $finalNotQualified = communicationRow(
        communicationApplicant('not-qualified@example.test'),
        EoiQualificationService::OUTCOME_NOT_QUALIFIED,
        true,
        false
    );
    $earlyVeto = communicationRow(
        communicationApplicant('early-veto@example.test'),
        EoiQualificationService::OUTCOME_NOT_QUALIFIED,
        false,
        false
    );
    $pending = communicationRow(
        communicationApplicant('pending@example.test'),
        EoiQualificationService::OUTCOME_PENDING,
        false,
        false
    );
    $previouslyDisqualified = communicationRow(
        communicationApplicant('proposal-disqualified@example.test'),
        EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
        true,
        true
    );
    $previouslyDisqualified['applicant']->status = FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED;
    $report = ['applicants' => collect([
        $fullyQualified,
        $averageQualified,
        $finalNotQualified,
        $earlyVeto,
        $pending,
        $previouslyDisqualified,
    ])];

    expect($service->finalRows($report)->pluck('applicant.submitter.email')->all())
        ->toBe([
            'fully@example.test',
            'average@example.test',
            'not-qualified@example.test',
            'proposal-disqualified@example.test',
        ])
        ->and($service->qualifiedRows($report)->pluck('applicant.submitter.email')->all())
        ->toBe(['fully@example.test', 'average@example.test']);
});

it('uses the shared top-eight progression decision for future proposal invitations', function () {
    $service = new EoiReportCommunicationService;
    $proceeding = communicationRow(
        communicationApplicant('proceeding@example.test'),
        EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
        true,
        true,
        true
    );
    $belowShortlist = communicationRow(
        communicationApplicant('below-shortlist@example.test'),
        EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
        true,
        true,
        false
    );

    expect($service->qualifiedRows(['applicants' => collect([$proceeding, $belowShortlist])])
        ->pluck('applicant.submitter.email')
        ->all())
        ->toBe(['proceeding@example.test']);
});

it('previews disabled blacklisted placeholder invalid and non-vendor contacts as unsendable', function () {
    $service = new EoiReportCommunicationService;
    $rows = collect([
        communicationApplicant('ready@example.test'),
        communicationApplicant('disabled@example.test', ['is_disabled' => true]),
        communicationApplicant('blocked@example.test', ['is_blacklisted' => true]),
        communicationApplicant('historical@africathinktank.africa'),
        communicationApplicant('not-an-email'),
        communicationApplicant('employee@example.test', ['user_type' => 'employee']),
    ])->map(fn (FormSubmission $applicant): array => communicationRow(
        $applicant,
        EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
        true,
        true
    ));
    $preview = $service->recipientPreview(['applicants' => $rows]);

    expect(data_get($preview, 'evaluation_records.eligible'))->toBe(2)
        ->and(data_get($preview, 'evaluation_records.unsendable'))->toBe(4)
        ->and(data_get($preview, 'proposal_invitation.eligible'))->toBe(1)
        ->and(data_get($preview, 'proposal_invitation.unsendable'))->toBe(5)
        ->and(collect(data_get($preview, 'proposal_invitation.recipients'))->pluck('reason')->filter()->implode(' '))
        ->toContain('disabled')
        ->toContain('blacklisted')
        ->toContain('placeholder')
        ->toContain('deliverable email')
        ->toContain('vendor portal');
});

it('redacts evaluator names and email addresses from applicant-facing free text', function () {
    $service = new EoiReportCommunicationService;
    $method = new ReflectionMethod($service, 'redactEvaluatorIdentifiers');
    $text = 'Reviewed by Dr Example Evaluator (evaluator@example.test). Dr Example Evaluator confirms the record.';

    $redacted = $method->invoke($service, $text, [
        'Dr Example Evaluator',
        'evaluator@example.test',
    ]);

    expect($redacted)
        ->not->toContain('Dr Example Evaluator')
        ->not->toContain('evaluator@example.test')
        ->toContain(EoiReportCommunicationService::EVALUATOR_MASK);
});

it('keeps communication delivery private scoped and recipient owned throughout the UI and routes', function () {
    $root = dirname(__DIR__, 2);
    $service = file_get_contents($root.'/app/Services/EoiReportCommunicationService.php');
    $technicalService = file_get_contents($root.'/app/Services/EoiTechnicalProposalService.php');
    $adminController = file_get_contents($root.'/app/Http/Controllers/EoiReportCommunicationController.php');
    $deliveryJob = file_get_contents($root.'/app/Jobs/SendQualifiedProposalInvitation.php');
    $deliveryCommand = file_get_contents($root.'/app/Console/Commands/DeliverPendingEoiProposalInvitations.php');
    $schedule = file_get_contents($root.'/bootstrap/app.php');
    $vendorController = file_get_contents($root.'/app/Http/Controllers/Vendor/EoiCommunicationController.php');
    $routes = file_get_contents($root.'/routes/web.php');
    $report = file_get_contents($root.'/resources/views/reports/evaluations/eoi-procurement.blade.php');
    $recordPdf = file_get_contents($root.'/resources/views/reports/evaluations/pdf/eoi-applicant-record.blade.php');
    $proposalMail = file_get_contents($root.'/resources/views/emails/evaluations/proposal-invitation.blade.php');

    expect($service)
        ->toContain("Storage::disk('local')")
        ->toContain("str_ends_with(\$email, '@africathinktank.africa')")
        ->toContain('panel_complete')
        ->toContain('can_advance')
        ->toContain('dispatchAfterResponse')
        ->toContain('resumePendingProposalInvitation')
        ->toContain('recoverableProposalInvitationRecipientIds')
        ->and($adminController)
        ->toContain("'templates' => ['nullable', 'array', 'max:20']")
        ->toContain('EoiTechnicalProposalService')
        ->toContain('EOI proposal invitation setup failed.')
        ->and($deliveryJob)
        ->toContain('deliverProposalInvitationRecipient')
        ->and($deliveryCommand)
        ->toContain('eoi:communications:deliver')
        ->and($schedule)
        ->toContain('eoi:communications:deliver --limit=25')
        ->and($technicalService)
        ->toContain('ALLOWED_DOCUMENT_MIME_TYPES')
        ->toContain("Storage::disk('local')")
        ->and($vendorController)
        ->toContain('assertRecipientOwner')
        ->toContain("'Cache-Control' => 'private, no-store, max-age=0'")
        ->toContain("'X-Content-Type-Options' => 'nosniff'")
        ->and($routes)
        ->toContain("->middleware('permission:evaluations.manage')")
        ->toContain("->name('eoi-communications.proposal.submit')")
        ->and($report)
        ->toContain('name="templates[]"')
        ->toContain('multiple')
        ->toContain('XXX-XXXX-XXXX')
        ->and($recordPdf)
        ->toContain('Applicant Evaluation Record')
        ->toContain('Final workflow decision')
        ->toContain('Only currently assigned panel tasks are included')
        ->and($proposalMail)
        ->toContain('{!! nl2br(e($communication->message)) !!}')
        ->toContain('submit proposal');
});

it('renders the dedicated applicant PDF with applicant identity and masked panel identity', function () {
    $root = dirname(__DIR__, 2);
    $app = require $root.'/bootstrap/app.php';

    try {
        $app->make(Kernel::class)->bootstrap();

        $applicant = communicationApplicant('applicant@example.test');
        $applicant->submitter->forceFill(['name' => 'Applicant Research Centre']);
        $procurement = (new Procurement)->forceFill([
            'id' => (string) Str::uuid(),
            'title' => 'Continental evidence programme',
            'reference_no' => 'ATTP-EOI-2026-031',
        ]);
        $row = [
            'outcome' => [
                'code' => EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
                'label' => 'Fully Qualified',
                'description' => 'Every active panel decision is Qualified.',
            ],
            'next_stage' => 'Technical Evaluation',
            'completed_tasks' => 2,
            'expected_tasks' => 2,
            'counts' => ['qualified' => 2, 'average_qualified' => 0, 'not_qualified' => 0],
        ];
        $evaluations = collect([[
            'name' => 'EOI eligibility',
            'description' => 'Applicant qualification evaluation.',
            'members' => collect([
                [
                    'number' => 1,
                    'name' => EoiReportCommunicationService::EVALUATOR_MASK,
                    'submitted' => true,
                    'task_complete' => true,
                    'counts' => ['qualified' => 1, 'average_qualified' => 0, 'not_qualified' => 0],
                ],
            ]),
            'criteria' => collect([
                [
                    'name' => 'Relevant experience',
                    'section' => 'Eligibility',
                    'description' => 'Demonstrated institutional experience.',
                    'outcome' => ['label' => 'Fully Qualified'],
                    'assessments' => collect([
                        [
                            'number' => 1,
                            'evaluator' => EoiReportCommunicationService::EVALUATOR_MASK,
                            'label' => 'Qualified',
                            'comment' => 'The evidence satisfies the requirement.',
                        ],
                    ]),
                ],
            ]),
        ]]);
        $viewData = array_merge([
            'procurement' => $procurement,
            'applicant' => $applicant,
            'row' => $row,
            'evaluations' => $evaluations,
            'generatedAt' => now(),
            'evaluatorMask' => EoiReportCommunicationService::EVALUATOR_MASK,
        ], \App\Support\PdfBranding::viewData());
        $html = $app['view']->make('reports.evaluations.pdf.eoi-applicant-record', $viewData)->render();
        $pdf = Pdf::loadView('reports.evaluations.pdf.eoi-applicant-record', $viewData)->output();
    } finally {
        restore_error_handler();
        restore_exception_handler();
    }

    expect($html)
        ->toContain('Applicant Research Centre')
        ->toContain('ATTP-EOI-2026-031')
        ->toContain('Fully Qualified')
        ->toContain('Technical Evaluation')
        ->toContain(EoiReportCommunicationService::EVALUATOR_MASK)
        ->not->toContain('Dr Real Evaluator')
        ->and($pdf)->toStartWith('%PDF');
});

it('builds individualized branded mail with private record and template attachments', function () {
    $root = dirname(__DIR__, 2);
    $app = require $root.'/bootstrap/app.php';

    try {
        $app->make(Kernel::class)->bootstrap();
        Storage::fake('local');

        $procurement = (new Procurement)->forceFill([
            'id' => (string) Str::uuid(),
            'title' => 'Policy research framework',
            'reference_no' => 'ATTP-EOI-044',
        ]);
        $templatePath = 'eoi-communications/invitation/templates/template.docx';
        Storage::disk('local')->put($templatePath, 'template bytes');
        $attachment = (new EoiReportCommunicationAttachment)->forceFill([
            'id' => (string) Str::uuid(),
            'communication_id' => (string) Str::uuid(),
            'file_path' => $templatePath,
            'original_filename' => 'proposal-template.docx',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 14,
        ]);
        $invitation = (new EoiReportCommunication)->forceFill([
            'id' => $attachment->communication_id,
            'type' => EoiReportCommunication::TYPE_PROPOSAL_INVITATION,
            'subject' => 'Submit your proposal',
            'message' => "Please submit safely.\n<script>alert('unsafe')</script>",
        ]);
        $invitation->setRelation('procurement', $procurement);
        $invitation->setRelation('attachments', new EloquentCollection([$attachment]));
        $recipient = (new EoiReportCommunicationRecipient)->forceFill([
            'id' => (string) Str::uuid(),
            'communication_id' => $invitation->id,
            'recipient_name' => 'Applicant Research Centre',
            'outcome_label' => 'Fully Qualified',
            'workflow_decision' => 'Technical Evaluation',
        ]);
        $recipient->setRelation('communication', $invitation);

        $invitationMail = (new QualifiedProposalInvitationMail($recipient))->build();
        $invitationHtml = $app['view']->make('emails.evaluations.proposal-invitation', [
            'recipient' => $recipient,
            'communication' => $invitation,
            'procurement' => $procurement,
            'portalUrl' => route('vendor.eoi-communications.show', $recipient),
        ])->render();

        $recordPath = 'eoi-communications/record/records/applicant.pdf';
        Storage::disk('local')->put($recordPath, '%PDF record');
        $recordCommunication = (new EoiReportCommunication)->forceFill([
            'id' => (string) Str::uuid(),
            'type' => EoiReportCommunication::TYPE_EVALUATION_RECORDS,
            'subject' => 'Your EOI evaluation outcome',
            'message' => 'Your private record is attached.',
        ]);
        $recordCommunication->setRelation('procurement', $procurement);
        $recordRecipient = (new EoiReportCommunicationRecipient)->forceFill([
            'id' => (string) Str::uuid(),
            'communication_id' => $recordCommunication->id,
            'recipient_name' => 'Applicant Research Centre',
            'outcome_label' => 'Fully Qualified',
            'workflow_decision' => 'Technical Evaluation',
            'record_file_path' => $recordPath,
            'record_file_name' => 'evaluation-record.pdf',
            'record_mime_type' => 'application/pdf',
        ]);
        $recordRecipient->setRelation('communication', $recordCommunication);
        $recordMail = (new ApplicantEvaluationRecordMail($recordRecipient))->build();
    } finally {
        restore_error_handler();
        restore_exception_handler();
    }

    expect($invitationMail->hasAttachmentFromStorageDisk(
        'local',
        $templatePath,
        'proposal-template.docx',
        ['mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
    ))->toBeTrue()
        ->and($invitationMail->from[0]['name'] ?? null)->toBe(PdfBranding::PLATFORM_NAME)
        ->and($recordMail->from[0]['name'] ?? null)->toBe(PdfBranding::PLATFORM_NAME)
        ->and($invitationHtml)
        ->toContain(PdfBranding::PLATFORM_NAME)
        ->not->toContain('Think Thank')
        ->toContain('Applicant Research Centre')
        ->toContain('Open invitation &amp; submit proposal')
        ->toContain('&lt;script&gt;alert(&#039;unsafe&#039;)&lt;/script&gt;')
        ->not->toContain("<script>alert('unsafe')</script>")
        ->and($recordMail->hasAttachmentFromStorageDisk(
            'local',
            $recordPath,
            'evaluation-record.pdf',
            ['mime' => 'application/pdf']
        ))->toBeTrue();
});
