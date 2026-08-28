<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationAttachment;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiReportProposalDocument;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\Procurement;
use App\Services\EoiQualificationService;
use App\Services\EoiReportCommunicationService;
use App\Services\EoiTechnicalProposalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EoiReportCommunicationController extends Controller
{
    use ScopesAssignedPortfolios;

    public function sendEvaluationRecords(
        Request $request,
        Procurement $procurement,
        EoiQualificationService $qualificationService,
        EoiReportCommunicationService $communicationService
    ): RedirectResponse {
        $this->assertProcurementScope($request, $procurement);
        $report = $this->report($procurement, $qualificationService);

        if ($communicationService->finalRows($report)->isEmpty()) {
            throw ValidationException::withMessages([
                'evaluation_records' => 'There are no panel-complete applicant outcomes to notify yet.',
            ]);
        }

        if (data_get($communicationService->recipientPreview($report), 'evaluation_records.eligible', 0) < 1) {
            throw ValidationException::withMessages([
                'evaluation_records' => 'No finalized applicant has an enabled account with a deliverable email address.',
            ]);
        }

        $result = $communicationService->sendEvaluationRecords(
            $procurement,
            $report,
            $request->user()
        );

        return back()->with(($result['failed'] + $result['skipped']) > 0 ? 'warning' : 'success', $this->deliveryMessage(
            'Evaluation records processed',
            $result
        ));
    }

    public function sendProposalInvitation(
        Request $request,
        Procurement $procurement,
        EoiQualificationService $qualificationService,
        EoiReportCommunicationService $communicationService,
        EoiTechnicalProposalService $technicalProposalService
    ): RedirectResponse {
        $this->assertProcurementScope($request, $procurement);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'proposal_title' => ['nullable', 'string', 'max:180'],
            'opens_at' => ['nullable', 'date'],
            'deadline_at' => ['nullable', 'date'],
            'timezone' => ['nullable', 'timezone'],
            'late_policy' => ['nullable', 'in:reject,allow_flagged,admin_capture_only'],
            'portal_requirement' => ['nullable', 'in:required,allowed,not_allowed'],
            'email_requirement' => ['nullable', 'in:required,allowed,not_allowed'],
            'physical_requirement' => ['nullable', 'in:required,allowed,not_allowed'],
            'rules' => ['nullable', 'array', 'min:1', 'max:250'],
            'rules.*.title' => ['required_with:rules', 'string', 'max:255'],
            'rules.*.description' => ['nullable', 'string', 'max:10000'],
            'rules.*.category' => ['nullable', 'in:general,eligibility,document,deadline,channel,declaration'],
            'rules.*.is_mandatory' => ['nullable', 'boolean'],
            'rules.*.is_disqualifying' => ['nullable', 'boolean'],
            'rules.*.requires_acknowledgement' => ['nullable', 'boolean'],
            'templates' => ['nullable', 'array', 'max:20'],
            'templates.*' => ['file', 'max:20480'],
        ], [
            'templates.max' => 'You may upload up to 20 proposal templates.',
            'templates.*.max' => 'Each proposal template may not exceed 20 MB.',
        ]);

        $templates = array_values(array_filter($request->file('templates', [])));
        $report = $this->report($procurement, $qualificationService);

        if ($communicationService->qualifiedRows($report)->isEmpty()) {
            throw ValidationException::withMessages([
                'message' => 'There are no panel-complete Qualified Applicants to invite.',
            ]);
        }

        if ($resumed = $communicationService->resumePendingProposalInvitation($procurement)) {
            return back()->with('success', $this->deliveryMessage(
                'The unfinished proposal invitation was resumed',
                $resumed
            ));
        }

        $rules = $validated['rules'] ?? [
            [
                'title' => 'Submission received by the stated deadline',
                'description' => 'The proposal must be received no later than the published deadline.',
                'category' => 'deadline',
                'is_mandatory' => true,
                'is_disqualifying' => true,
            ],
            [
                'title' => 'Submission channel requirements followed',
                'description' => 'Every channel marked as required must be satisfied and prohibited channels must not be used.',
                'category' => 'channel',
                'is_mandatory' => true,
                'is_disqualifying' => true,
            ],
            [
                'title' => 'Required proposal documents are complete',
                'description' => 'All required forms, schedules, and supporting documents must be included.',
                'category' => 'document',
                'is_mandatory' => true,
                'is_disqualifying' => true,
            ],
        ];

        $failureReference = Str::upper(Str::random(10));
        $stage = 'creating the proposal round';
        $round = null;

        try {
            $round = $technicalProposalService->createDraft([
                'procurement_id' => $procurement->getKey(),
                'title' => $validated['proposal_title']
                    ?? 'Technical Proposal Submission — '.($procurement->reference_no ?: $procurement->title),
                'instructions' => $validated['message'],
                'opens_at' => $validated['opens_at'] ?? null,
                'deadline_at' => $validated['deadline_at'] ?? now()->addDays(14),
                'timezone' => $validated['timezone'] ?? config('app.timezone', 'UTC'),
                'late_policy' => $validated['late_policy'] ?? EoiTechnicalProposalRound::LATE_ALLOW_FLAGGED,
                'portal_requirement' => $validated['portal_requirement'] ?? EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
                'email_requirement' => $validated['email_requirement'] ?? EoiTechnicalProposalRound::REQUIREMENT_ALLOWED,
                'physical_requirement' => $validated['physical_requirement'] ?? EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
            ], $rules, $templates, $request->user());

            $stage = 'enrolling qualified applicants';
            $round = $technicalProposalService->publish(
                $round,
                $communicationService->qualifiedRows($report),
                $request->user()
            );

            $stage = 'preparing applicant notifications';
            $result = $communicationService->sendProposalInvitation(
                $procurement,
                $report,
                $request->user(),
                $validated['subject'],
                $validated['message'],
                [],
                $round
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('EOI proposal invitation setup failed.', [
                'reference' => $failureReference,
                'stage' => $stage,
                'procurement_id' => $procurement->getKey(),
                'round_id' => $round?->getKey(),
                'actor_id' => $request->user()?->getKey(),
                'exception' => $exception,
            ]);

            throw ValidationException::withMessages([
                'proposal_invitation' => "The invitation could not be prepared while {$stage}. Please check the proposal history before retrying, or give support reference {$failureReference} to the administrator.",
            ]);
        }

        return back()->with(($result['failed'] + $result['skipped']) > 0 ? 'warning' : 'success', $this->deliveryMessage(
            'Proposal invitations processed',
            $result
        ));
    }

    public function downloadAttachment(
        Request $request,
        Procurement $procurement,
        EoiReportCommunication $communication,
        EoiReportCommunicationAttachment $attachment
    ): StreamedResponse {
        $this->assertProcurementScope($request, $procurement);
        $validTemplatePath = str_starts_with(
            $attachment->file_path,
            'eoi-communications/'.$communication->getKey().'/templates/'
        ) || ($communication->technical_proposal_round_id
            && str_starts_with(
                $attachment->file_path,
                'eoi-technical-proposals/'.$communication->technical_proposal_round_id.'/templates/'
            ));
        abort_unless(
            (string) $communication->procurement_id === (string) $procurement->getKey()
                && (string) $attachment->communication_id === (string) $communication->getKey()
                && $validTemplatePath,
            404
        );
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return Storage::disk('local')->download(
            $attachment->file_path,
            $attachment->original_filename,
            [
                'Content-Type' => $attachment->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function downloadProposalDocument(
        Request $request,
        Procurement $procurement,
        EoiReportCommunication $communication,
        EoiReportCommunicationRecipient $recipient,
        EoiReportProposalDocument $document
    ): StreamedResponse {
        $this->assertProcurementScope($request, $procurement);
        $technicalCandidate = $communication->technical_proposal_round_id
            ? EoiTechnicalProposalCandidate::query()
                ->where('round_id', $communication->technical_proposal_round_id)
                ->where('form_submission_id', $recipient->form_submission_id)
                ->first()
            : null;
        $validProposalPath = str_starts_with(
            $document->file_path,
            'eoi-communications/'.$communication->getKey().'/proposals/'.$recipient->getKey().'/'
        ) || ($technicalCandidate && str_starts_with(
            $document->file_path,
            'eoi-technical-proposals/'.$technicalCandidate->round_id.'/candidates/'.$technicalCandidate->getKey().'/revisions/'
        ));
        abort_unless(
            (string) $communication->procurement_id === (string) $procurement->getKey()
                && $communication->type === EoiReportCommunication::TYPE_PROPOSAL_INVITATION
                && (string) $recipient->communication_id === (string) $communication->getKey()
                && (string) $document->recipient_id === (string) $recipient->getKey()
                && $validProposalPath,
            404
        );
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download(
            $document->file_path,
            $document->original_filename,
            [
                'Content-Type' => $document->mime_type,
                'Cache-Control' => 'private, no-store, max-age=0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function report(Procurement $procurement, EoiQualificationService $qualificationService): array
    {
        $report = $qualificationService->buildProcurementReport($procurement);

        abort_if(
            $report['evaluations']->isEmpty(),
            404,
            'An Expression of Interest evaluation was not found for this procurement.'
        );

        return $report;
    }

    private function assertProcurementScope(Request $request, Procurement $procurement): void
    {
        if (! $this->userHasAssignedPortfolioScope($request->user())) {
            return;
        }

        abort_unless(
            $this->procurementIsInAssignedPortfolio($procurement, $request->user()),
            403,
            'This evaluation report is not assigned to your portfolio.'
        );
    }

    private function deliveryMessage(string $prefix, array $result): string
    {
        $pending = (int) ($result['pending'] ?? 0);

        if ($pending < 1) {
            return sprintf(
                '%s: %d sent, %d skipped, %d failed.',
                $prefix,
                $result['sent'],
                $result['skipped'],
                $result['failed']
            );
        }

        return sprintf(
            '%s: %d queued, %d sent, %d skipped, %d failed.',
            $prefix,
            $pending,
            $result['sent'],
            $result['skipped'],
            $result['failed']
        );
    }
}
