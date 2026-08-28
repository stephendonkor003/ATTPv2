<?php

use App\Mail\ApplicantEvaluationRecordMail;
use App\Mail\QualifiedProposalInvitationMail;
use App\Models\EoiReportCommunication;
use App\Models\Evaluation;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationCriteria;
use App\Models\EvaluationCriteriaScore;
use App\Models\EvaluationSection;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Permission;
use App\Models\Procurement;
use App\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Concerns\InteractsWithAuthentication;
use Illuminate\Foundation\Testing\Concerns\InteractsWithExceptionHandling;
use Illuminate\Foundation\Testing\Concerns\InteractsWithSession;
use Illuminate\Foundation\Testing\Concerns\MakesHttpRequests;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

class EoiReportCommunicationsSmoke
{
    use InteractsWithAuthentication;
    use InteractsWithExceptionHandling;
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
            [$administrator, $viewer] = $this->staffUsers();
            [$procurement, $evaluator, $qualifiedVendor, $notQualifiedVendor, $otherVendor] = $this->fixture($administrator);

            $this->actingAs($administrator)
                ->get(route('reports.evaluations.eoi.procurement', $procurement))
                ->assertOk()
                ->assertSee('Release outcomes &amp; request proposals', false)
                ->assertSee('Send evaluation records')
                ->assertSee('Invite proposal submissions')
                ->assertSee('name="templates[]"', false)
                ->assertSee('multiple', false);

            $viewerResponse = $this->actingAs($viewer)
                ->postWithCsrf(route('reports.evaluations.eoi.communications.evaluation-records', $procurement));
            if ($viewerResponse->status() !== 403) {
                throw new RuntimeException('A report-only user could reach the send action. HTTP '.$viewerResponse->status().': '.$viewerResponse->getContent());
            }

            $this->actingAs($administrator)
                ->postWithCsrf(route('reports.evaluations.eoi.communications.evaluation-records', $procurement))
                ->assertRedirect()
                ->assertSessionHas('success');

            Mail::assertSent(ApplicantEvaluationRecordMail::class, 2);
            $recordAddresses = Mail::sent(ApplicantEvaluationRecordMail::class)
                ->map(fn (ApplicantEvaluationRecordMail $mail): string => $mail->recipient->recipient_email)
                ->sort()
                ->values()
                ->all();
            $expectedRecordAddresses = collect([$qualifiedVendor->email, $notQualifiedVendor->email])
                ->sort()
                ->values()
                ->all();
            $this->assertSame($expectedRecordAddresses, $recordAddresses, 'Applicant record emails were not individualized to the final applicants.');

            $recordBatch = EoiReportCommunication::query()
                ->where('procurement_id', $procurement->id)
                ->where('type', EoiReportCommunication::TYPE_EVALUATION_RECORDS)
                ->with('recipients')
                ->firstOrFail();
            $this->assertSame(2, $recordBatch->recipients->count(), 'Every final outcome did not receive an individualized record.');
            $this->assertTrue(
                $recordBatch->recipients->every(fn ($recipient): bool => $recipient->delivery_status === 'sent'),
                'A successful evaluation-record delivery was not tracked as sent.'
            );

            foreach ($recordBatch->recipients as $recipient) {
                $this->assertTrue(Storage::disk('local')->exists($recipient->record_file_path), 'An applicant PDF was not stored privately.');
                $this->assertTrue(
                    str_starts_with(Storage::disk('local')->get($recipient->record_file_path), '%PDF'),
                    'An applicant evaluation record is not a valid PDF.'
                );
            }

            $this->actingAs($administrator)
                ->postWithCsrf(route('reports.evaluations.eoi.communications.proposal-invitation', $procurement), [
                    'subject' => 'Qualified Applicant proposal stage',
                    'message' => 'Please complete the attached templates and submit your proposal through the protected vendor portal.',
                    'templates' => [
                        UploadedFile::fake()->create('technical-template.docx', 20, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
                        UploadedFile::fake()->create('financial-template.xlsx', 20, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                        UploadedFile::fake()->create('instructions.pdf', 20, 'application/pdf'),
                    ],
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            Mail::assertSent(QualifiedProposalInvitationMail::class, 1);
            $proposalAddresses = Mail::sent(QualifiedProposalInvitationMail::class)
                ->map(fn (QualifiedProposalInvitationMail $mail): string => $mail->recipient->recipient_email)
                ->values()
                ->all();
            $this->assertSame([$qualifiedVendor->email], $proposalAddresses, 'The proposal email was not limited to the Qualified Applicant.');

            $proposalBatch = EoiReportCommunication::query()
                ->where('procurement_id', $procurement->id)
                ->where('type', EoiReportCommunication::TYPE_PROPOSAL_INVITATION)
                ->with(['recipients', 'attachments'])
                ->firstOrFail();
            $this->assertSame(1, $proposalBatch->recipients->count(), 'A non-qualified applicant entered the proposal invitation batch.');
            $this->assertSame((string) $qualifiedVendor->id, (string) $proposalBatch->recipients->first()->user_id, 'The proposal invitation targeted the wrong vendor.');
            $this->assertSame(3, $proposalBatch->attachments->count(), 'Multiple proposal templates were not retained.');

            foreach ($proposalBatch->attachments as $attachment) {
                $this->assertTrue(Storage::disk('local')->exists($attachment->file_path), 'A proposal template was not stored on the private disk.');
            }

            $recipient = $proposalBatch->recipients->first();

            $this->actingAs($qualifiedVendor)
                ->withSession($this->otpSession($qualifiedVendor))
                ->get(route('vendor.dashboard'))
                ->assertOk()
                ->assertSee('Evaluation notices &amp; proposal invitations', false)
                ->assertSee('Qualified Applicant proposal stage');

            $this->actingAs($qualifiedVendor)
                ->withSession($this->otpSession($qualifiedVendor))
                ->get(route('vendor.eoi-communications.show', $recipient))
                ->assertOk()
                ->assertSee('Qualified Applicant proposal stage')
                ->assertSee('technical-template.docx')
                ->assertSee('Submit your proposal');

            $this->actingAs($notQualifiedVendor)
                ->withSession($this->otpSession($notQualifiedVendor))
                ->get(route('vendor.eoi-communications.show', $recipient))
                ->assertNotFound();

            $this->actingAs($otherVendor)
                ->withSession($this->otpSession($otherVendor))
                ->get(route('vendor.eoi-communications.templates.download', [$recipient, $proposalBatch->attachments->first()]))
                ->assertNotFound();

            $this->actingAs($qualifiedVendor)
                ->postWithCsrf(route('vendor.eoi-communications.proposal.submit', $recipient), [
                    'proposal_message' => 'Please find our technical and financial proposal attached.',
                    'documents' => [
                        UploadedFile::fake()->create('qualified-technical.pdf', 30, 'application/pdf'),
                        UploadedFile::fake()->create('qualified-financial.xlsx', 30, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'),
                    ],
                ])
                ->assertRedirect()
                ->assertSessionHas('success');

            $recipient->refresh()->load('proposalDocuments');
            $this->assertTrue((bool) $recipient->proposal_submitted_at, 'The portal did not record the proposal submission time.');
            $this->assertSame(2, $recipient->proposalDocuments->count(), 'Multiple vendor proposal documents were not retained.');

            foreach ($recipient->proposalDocuments as $document) {
                $this->assertTrue(Storage::disk('local')->exists($document->file_path), 'A vendor proposal document was not stored privately.');
            }

            $this->actingAs($qualifiedVendor)
                ->postWithCsrf(route('vendor.eoi-communications.proposal.submit', $recipient), [
                    'documents' => [UploadedFile::fake()->create('malware.exe', 10, 'application/x-msdownload')],
                ])
                ->assertRedirect()
                ->assertSessionHasErrors('documents.0');
            $this->assertSame(2, $recipient->proposalDocuments()->count(), 'An invalid executable altered the submitted proposal package.');

            $this->actingAs($administrator)
                ->get(route('reports.evaluations.eoi.procurement', $procurement))
                ->assertOk()
                ->assertSee('1 proposal response(s)')
                ->assertSee('qualified-technical.pdf')
                ->assertSee('qualified-financial.xlsx');

            $this->actingAs($administrator)
                ->get(route('reports.evaluations.eoi.communications.proposal-documents.download', [
                    $procurement,
                    $proposalBatch,
                    $recipient,
                    $recipient->proposalDocuments->first(),
                ]))
                ->assertOk()
                ->assertHeader('cache-control', 'max-age=0, no-store, private');

            $this->assertTrue(
                ! EoiReportCommunication::query()
                    ->where('type', EoiReportCommunication::TYPE_PROPOSAL_INVITATION)
                    ->whereHas('recipients', fn ($query) => $query->where('user_id', $notQualifiedVendor->id))
                    ->exists(),
                'A final Not Qualified applicant was invited to submit a proposal.'
            );

            echo "EOI_REPORT_COMMUNICATIONS_SMOKE_OK\n";
        } finally {
            DB::rollBack();
        }
    }

    private function staffUsers(): array
    {
        $permissions = Permission::query()
            ->whereIn('name', ['evaluations.view_all', 'evaluations.manage'])
            ->get()
            ->keyBy('name');

        if ($permissions->count() !== 2) {
            throw new RuntimeException('The EOI communication permissions are not seeded.');
        }

        $managerRole = Role::firstOrCreate(['name' => 'E2E EOI Communications Manager']);
        $managerRole->permissions()->syncWithoutDetaching($permissions->pluck('id')->all());
        $viewerRole = Role::firstOrCreate(['name' => 'E2E EOI Report Viewer']);
        $viewerRole->permissions()->syncWithoutDetaching([$permissions['evaluations.view_all']->id]);

        return [
            $this->user('EOI Communications Manager', 'admin', $managerRole->id),
            $this->user('EOI Report Viewer', 'employee', $viewerRole->id),
        ];
    }

    private function fixture(User $administrator): array
    {
        $procurement = Procurement::create([
            'title' => 'EOI communication smoke '.Str::upper(Str::random(6)),
            'reference_no' => 'EOI-COMMS-'.Str::upper(Str::random(8)),
            'description' => 'Transactional communication workflow fixture.',
            'status' => 'closed',
            'created_by' => $administrator->id,
        ]);
        $evaluation = Evaluation::create([
            'procurement_id' => $procurement->id,
            'name' => 'EOI final qualification',
            'description' => 'Qualification gate for the communication smoke.',
            'status' => 'open',
            'type' => Evaluation::TYPE_EOI,
            'created_by' => $administrator->id,
        ]);
        $section = EvaluationSection::create([
            'evaluation_id' => $evaluation->id,
            'name' => 'Eligibility',
            'sort_order' => 1,
        ]);
        $criterion = EvaluationCriteria::create([
            'evaluation_section_id' => $section->id,
            'name' => 'Institutional experience',
            'description' => 'Relevant institutional experience is demonstrated.',
        ]);
        $evaluator = $this->user('Panel Evaluator', 'employee');
        $qualifiedVendor = $this->user('Qualified Research Centre', 'vendor');
        $notQualifiedVendor = $this->user('Not Qualified Research Centre', 'vendor');
        $otherVendor = $this->user('Unrelated Research Centre', 'vendor');

        EvaluationAssignment::create([
            'evaluation_id' => $evaluation->id,
            'procurement_id' => $procurement->id,
            'form_submission_id' => null,
            'user_id' => $evaluator->id,
            'assigned_by' => $administrator->id,
            'assigned_at' => now(),
            'status' => 'assigned',
        ]);

        foreach ([[$qualifiedVendor, 2], [$notQualifiedVendor, 0]] as [$vendor, $decision]) {
            $applicant = FormSubmission::create([
                'procurement_id' => $procurement->id,
                'procurement_submission_code' => 'EOI-'.Str::upper(Str::random(8)),
                'submitted_by' => $vendor->id,
                'status' => FormSubmission::STATUS_EOI_EVALUATION,
                'submitted_at' => now(),
            ]);
            $submission = EvaluationSubmission::create([
                'evaluation_id' => $evaluation->id,
                'procurement_id' => $procurement->id,
                'evaluator_id' => $evaluator->id,
                'form_submission_id' => $applicant->id,
                'comments' => 'Completed active-panel evaluation.',
                'submitted_at' => now(),
            ]);
            EvaluationCriteriaScore::create([
                'submission_id' => $submission->id,
                'evaluation_criteria_id' => $criterion->id,
                'decision' => $decision,
                'comment' => 'Panel Evaluator confirms the decision.',
            ]);
        }

        return [$procurement, $evaluator, $qualifiedVendor, $notQualifiedVendor, $otherVendor];
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

    private function postWithCsrf(string $uri, array $data = [])
    {
        $token = Str::random(40);

        return $this->withSession([
            '_token' => $token,
            ...$this->otpSession(auth()->user()),
        ])
            ->post($uri, ['_token' => $token, ...$data]);
    }

    private function otpSession(?User $user): array
    {
        return [
            'otp_verified' => true,
            'otp_verified_at' => now()->toIso8601String(),
            'otp_verified_user_id' => (string) $user?->id,
        ];
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
}

(new EoiReportCommunicationsSmoke($app))->run();
