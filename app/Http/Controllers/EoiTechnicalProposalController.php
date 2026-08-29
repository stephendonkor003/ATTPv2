<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopesAssignedPortfolios;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiReportProposalDocument;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalRuleApplication;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\EoiTechnicalProposalTemplate;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ProcurementAuditLog;
use App\Services\EoiTechnicalProposalService;
use App\Services\EvaluationReworkGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EoiTechnicalProposalController extends Controller
{
    use ScopesAssignedPortfolios;

    public function capture(
        Request $request,
        Procurement $procurement,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalService $service,
        EvaluationReworkGuard $reworkGuard
    ): RedirectResponse {
        $this->assertNestedScope($request, $procurement, $round, $candidate);

        $validated = $request->validate([
            'received_via' => ['required', 'in:email,physical,courier,other'],
            'received_at' => ['required', 'date'],
            'cover_note' => ['nullable', 'string', 'max:5000'],
            'capture_note' => ['required', 'string', 'min:5', 'max:5000'],
            'documents' => ['required', 'array', 'min:1', 'max:20'],
            'documents.*' => ['required', 'file', 'max:25600'],
        ], [
            'capture_note.required' => 'Record how the proposal reached the procurement team and why it is being uploaded on the applicant’s behalf.',
            'documents.required' => 'Choose at least one proposal document.',
            'documents.max' => 'You may capture up to 20 files in one revision.',
        ]);

        $proposalSubmission = DB::transaction(function () use (
            $candidate,
            $procurement,
            $request,
            $reworkGuard,
            $service,
            $validated
        ): EoiTechnicalProposalSubmission {
            $lockedProcurement = Procurement::query()
                ->whereKey($procurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedApplicant = FormSubmission::query()
                ->whereKey($candidate->form_submission_id)
                ->where('procurement_id', $lockedProcurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $reworkGuard->assertTechnicalProposalCanContinue($lockedApplicant);

            return $service->createSubmission(
                $candidate,
                array_values(array_filter($request->file('documents', []))),
                $request->user(),
                EoiTechnicalProposalSubmission::SOURCE_ADMIN_CAPTURE,
                $validated['received_via'],
                $validated['received_at'],
                [
                    'cover_note' => $validated['cover_note'] ?? null,
                    'capture_note' => $validated['capture_note'],
                ]
            );

        }, 3);

        $this->syncLegacyCommunication($round, $candidate, $proposalSubmission, $request);
        $this->audit($request, $procurement, 'technical_proposal_captured', [
            'round_id' => $round->getKey(),
            'candidate_id' => $candidate->getKey(),
            'form_submission_id' => $candidate->form_submission_id,
            'proposal_submission_id' => $proposalSubmission->getKey(),
            'revision_number' => $proposalSubmission->revision_number,
            'received_via' => $proposalSubmission->received_via,
            'received_at' => $proposalSubmission->received_at?->toIso8601String(),
            'is_late' => $proposalSubmission->is_late,
        ]);

        return back()->with('success', 'The applicant’s proposal was captured as an auditable revision.');
    }

    public function review(
        Request $request,
        Procurement $procurement,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalService $service,
        EvaluationReworkGuard $reworkGuard
    ): RedirectResponse {
        $this->assertNestedScope($request, $procurement, $round, $candidate);
        $candidate->loadMissing('latestSubmission');
        abort_unless($candidate->latestSubmission, 409, 'A proposal must be received before compliance findings can be recorded.');

        $validated = $request->validate([
            'findings' => ['required', 'array', 'min:1', 'max:250'],
            'findings.*.finding' => ['required', 'in:compliant,non_compliant,waived,not_applicable'],
            'findings.*.effect' => ['nullable', 'in:none,disqualify'],
            'findings.*.rationale' => ['nullable', 'string', 'max:10000'],
        ]);

        $rules = $round->rules()->whereIn('id', array_keys($validated['findings']))->get()->keyBy('id');
        abort_if($rules->count() !== count($validated['findings']), 422, 'One or more selected rules do not belong to this proposal round.');

        DB::transaction(function () use (
            $candidate,
            $procurement,
            $request,
            $reworkGuard,
            $round,
            $rules,
            $service,
            $validated
        ): void {
            $lockedProcurement = Procurement::query()
                ->whereKey($procurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedApplicant = FormSubmission::query()
                ->whereKey($candidate->form_submission_id)
                ->where('procurement_id', $lockedProcurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $reworkGuard->assertTechnicalProposalCanContinue($lockedApplicant);

            foreach ($validated['findings'] as $ruleId => $findingData) {
                $service->applyRuleFinding(
                    $candidate,
                    $rules->get($ruleId),
                    $findingData['finding'],
                    $findingData['effect'] ?? EoiTechnicalProposalRuleApplication::EFFECT_NONE,
                    $findingData['rationale'] ?? null,
                    $request->user(),
                    $candidate->latestSubmission
                );
            }

            $candidate->refresh();
            $applicantStatus = match ($candidate->status) {
                EoiTechnicalProposalCandidate::STATUS_QUALIFIED => FormSubmission::STATUS_TECHNICAL_EVALUATION,
                EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED => FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED,
                default => FormSubmission::STATUS_TECHNICAL_PROPOSAL_SUBMITTED,
            };
            $lockedApplicant->forceFill(['status' => $applicantStatus])->save();
            $this->audit($request, $lockedProcurement, 'technical_proposal_reviewed', [
                'round_id' => $round->getKey(),
                'candidate_id' => $candidate->getKey(),
                'form_submission_id' => $candidate->form_submission_id,
                'proposal_submission_id' => $candidate->latestSubmission->getKey(),
                'candidate_status' => $candidate->status,
                'findings_recorded' => count($validated['findings']),
            ]);
        });

        return back()->with('success', 'Compliance findings were saved. Applicant status: '.str($candidate->status)->headline().'.');
    }

    public function downloadTemplate(
        Request $request,
        Procurement $procurement,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalTemplate $template
    ): StreamedResponse {
        $this->assertNestedScope($request, $procurement, $round);
        abort_unless(
            (string) $template->round_id === (string) $round->getKey()
                && str_starts_with($template->file_path, 'eoi-technical-proposals/'.$round->getKey().'/templates/'),
            404
        );

        return $this->privateDownload($template->file_path, $template->original_filename, $template->mime_type);
    }

    public function downloadDocument(
        Request $request,
        Procurement $procurement,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposalSubmission,
        EoiTechnicalProposalDocument $document
    ): StreamedResponse {
        $this->assertNestedScope($request, $procurement, $round, $candidate);
        abort_unless(
            (string) $proposalSubmission->candidate_id === (string) $candidate->getKey()
                && (string) $document->proposal_submission_id === (string) $proposalSubmission->getKey()
                && str_starts_with(
                    $document->file_path,
                    'eoi-technical-proposals/'.$round->getKey().'/candidates/'.$candidate->getKey().'/revisions/'
                ),
            404
        );

        return $this->privateDownload($document->file_path, $document->original_filename, $document->mime_type);
    }

    private function assertNestedScope(
        Request $request,
        Procurement $procurement,
        EoiTechnicalProposalRound $round,
        ?EoiTechnicalProposalCandidate $candidate = null
    ): void {
        abort_unless((string) $round->procurement_id === (string) $procurement->getKey(), 404);
        abort_if(
            $this->userHasAssignedPortfolioScope($request->user())
                && ! $this->procurementIsInAssignedPortfolio($procurement, $request->user()),
            403,
            'This procurement is not assigned to your portfolio.'
        );

        if ($candidate) {
            abort_unless((string) $candidate->round_id === (string) $round->getKey(), 404);
        }
    }

    private function syncLegacyCommunication(
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposalSubmission,
        Request $request
    ): void {
        $communication = EoiReportCommunication::query()
            ->where('technical_proposal_round_id', $round->getKey())
            ->latest()
            ->first();

        if (! $communication) {
            return;
        }

        $recipient = EoiReportCommunicationRecipient::query()
            ->where('communication_id', $communication->getKey())
            ->where('form_submission_id', $candidate->form_submission_id)
            ->first();

        if (! $recipient) {
            return;
        }

        foreach ($proposalSubmission->documents as $document) {
            EoiReportProposalDocument::create([
                'recipient_id' => $recipient->getKey(),
                'uploaded_by' => $request->user()->getKey(),
                'file_path' => $document->file_path,
                'original_filename' => $document->original_filename,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'sha256' => $document->sha256,
            ]);
        }

        $recipient->forceFill([
            'proposal_submitted_at' => $proposalSubmission->received_at,
            'proposal_message' => $proposalSubmission->cover_note,
        ])->save();
    }

    private function privateDownload(string $path, string $filename, string $mimeType): StreamedResponse
    {
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string, mixed> $metadata */
    private function audit(Request $request, Procurement $procurement, string $action, array $metadata): void
    {
        ProcurementAuditLog::create([
            'user_id' => $request->user()->getKey(),
            'action' => $action,
            'procurement_id' => $procurement->getKey(),
            'submission_id' => $metadata['form_submission_id'] ?? null,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
