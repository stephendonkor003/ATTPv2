<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationAttachment;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiReportProposalDocument;
use App\Models\Procurement;
use App\Services\EoiQualificationService;
use App\Services\EoiReportCommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return back()->with($result['failed'] > 0 ? 'warning' : 'success', $this->deliveryMessage(
            'Evaluation records processed',
            $result
        ));
    }

    public function sendProposalInvitation(
        Request $request,
        Procurement $procurement,
        EoiQualificationService $qualificationService,
        EoiReportCommunicationService $communicationService
    ): RedirectResponse {
        $this->assertProcurementScope($request, $procurement);

        $validated = $request->validate([
            'subject' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'min:5', 'max:5000'],
            'templates' => ['nullable', 'array', 'max:10'],
            'templates.*' => ['file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
        ], [
            'templates.max' => 'You may upload up to 10 proposal templates.',
            'templates.*.mimes' => 'Templates must be PDF, Word, or Excel files.',
            'templates.*.max' => 'Each proposal template may not exceed 10 MB.',
        ]);

        $templates = array_values(array_filter($request->file('templates', [])));
        $communicationService->assertCombinedUploadSize(
            $templates,
            'templates',
            EoiReportCommunicationService::MAX_EMAIL_TEMPLATE_BYTES
        );
        $report = $this->report($procurement, $qualificationService);

        if ($communicationService->qualifiedRows($report)->isEmpty()) {
            throw ValidationException::withMessages([
                'message' => 'There are no panel-complete Qualified Applicants to invite.',
            ]);
        }

        if (data_get($communicationService->recipientPreview($report), 'proposal_invitation.eligible', 0) < 1) {
            throw ValidationException::withMessages([
                'message' => 'No Qualified Applicant has an enabled vendor account with a deliverable email address.',
            ]);
        }

        $result = $communicationService->sendProposalInvitation(
            $procurement,
            $report,
            $request->user(),
            $validated['subject'],
            $validated['message'],
            $templates
        );

        return back()->with($result['failed'] > 0 ? 'warning' : 'success', $this->deliveryMessage(
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
        abort_unless(
            (string) $communication->procurement_id === (string) $procurement->getKey()
                && (string) $attachment->communication_id === (string) $communication->getKey()
                && str_starts_with(
                    $attachment->file_path,
                    'eoi-communications/'.$communication->getKey().'/templates/'
                ),
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
        abort_unless(
            (string) $communication->procurement_id === (string) $procurement->getKey()
                && $communication->type === EoiReportCommunication::TYPE_PROPOSAL_INVITATION
                && (string) $recipient->communication_id === (string) $communication->getKey()
                && (string) $document->recipient_id === (string) $recipient->getKey()
                && str_starts_with(
                    $document->file_path,
                    'eoi-communications/'.$communication->getKey().'/proposals/'.$recipient->getKey().'/'
                ),
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
        return sprintf(
            '%s: %d sent, %d skipped, %d failed.',
            $prefix,
            $result['sent'],
            $result['skipped'],
            $result['failed']
        );
    }
}
