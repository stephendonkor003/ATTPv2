<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationAttachment;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiReportProposalDocument;
use App\Services\EoiReportCommunicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class EoiCommunicationController extends Controller
{
    public function index(Request $request)
    {
        $this->assertVendor($request);

        $recipients = EoiReportCommunicationRecipient::query()
            ->where('user_id', $request->user()->getKey())
            ->with([
                'communication.procurement' => fn ($query) => $query->withTrashed(),
                'communication.attachments',
                'proposalDocuments',
            ])
            ->latest()
            ->paginate(15);

        return view('vendor.eoi-communications.index', compact('recipients'));
    }

    public function show(Request $request, EoiReportCommunicationRecipient $recipient)
    {
        $this->assertRecipientOwner($request, $recipient);
        $recipient->load([
            'communication.procurement' => fn ($query) => $query->withTrashed(),
            'communication.attachments',
            'proposalDocuments',
        ]);

        if (! $recipient->read_at) {
            $recipient->forceFill(['read_at' => now()])->save();
        }

        return view('vendor.eoi-communications.show', compact('recipient'));
    }

    public function downloadEvaluationRecord(
        Request $request,
        EoiReportCommunicationRecipient $recipient
    ): StreamedResponse {
        $this->assertRecipientOwner($request, $recipient);
        $recipient->loadMissing('communication');
        abort_unless(
            $recipient->communication?->type === EoiReportCommunication::TYPE_EVALUATION_RECORDS
                && filled($recipient->record_file_path)
                && str_starts_with(
                    $recipient->record_file_path,
                    'eoi-communications/'.$recipient->communication_id.'/records/'
                ),
            404
        );
        abort_unless(Storage::disk('local')->exists($recipient->record_file_path), 404);

        return $this->privateDownload(
            $recipient->record_file_path,
            $recipient->record_file_name ?: 'eoi-evaluation-record.pdf',
            $recipient->record_mime_type ?: 'application/pdf'
        );
    }

    public function downloadTemplate(
        Request $request,
        EoiReportCommunicationRecipient $recipient,
        EoiReportCommunicationAttachment $attachment
    ): StreamedResponse {
        $this->assertRecipientOwner($request, $recipient);
        $recipient->loadMissing('communication');
        abort_unless(
            $recipient->communication?->type === EoiReportCommunication::TYPE_PROPOSAL_INVITATION
                && (string) $attachment->communication_id === (string) $recipient->communication_id
                && str_starts_with(
                    $attachment->file_path,
                    'eoi-communications/'.$recipient->communication_id.'/templates/'
                ),
            404
        );
        abort_unless(Storage::disk('local')->exists($attachment->file_path), 404);

        return $this->privateDownload(
            $attachment->file_path,
            $attachment->original_filename,
            $attachment->mime_type
        );
    }

    public function submitProposal(
        Request $request,
        EoiReportCommunicationRecipient $recipient,
        EoiReportCommunicationService $communicationService
    ): RedirectResponse {
        $this->assertRecipientOwner($request, $recipient);
        $recipient->loadMissing('communication');
        abort_unless(
            $recipient->communication?->type === EoiReportCommunication::TYPE_PROPOSAL_INVITATION,
            404
        );

        $validated = $request->validate([
            'proposal_message' => ['nullable', 'string', 'max:2000'],
            'documents' => ['required', 'array', 'min:1', 'max:10'],
            'documents.*' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx', 'max:10240'],
        ], [
            'documents.required' => 'Choose at least one proposal document.',
            'documents.max' => 'You may upload up to 10 proposal documents at a time.',
            'documents.*.mimes' => 'Proposal documents must be PDF, Word, or Excel files.',
            'documents.*.max' => 'Each proposal document may not exceed 10 MB.',
        ]);

        $documents = array_values(array_filter($request->file('documents', [])));
        $communicationService->assertCombinedUploadSize($documents);
        $storedPaths = [];

        try {
            DB::transaction(function () use ($documents, $recipient, $request, $validated, &$storedPaths): void {
                foreach ($documents as $document) {
                    $sha256 = hash_file('sha256', $document->getRealPath());
                    $extension = strtolower((string) $document->getClientOriginalExtension());
                    $path = $document->storeAs(
                        'eoi-communications/'.$recipient->communication_id.'/proposals/'.$recipient->getKey(),
                        Str::uuid().($extension !== '' ? '.'.$extension : ''),
                        'local'
                    );

                    if (! is_string($path) || $path === '') {
                        throw new \RuntimeException('A proposal document could not be stored.');
                    }

                    $storedPaths[] = $path;
                    EoiReportProposalDocument::create([
                        'recipient_id' => $recipient->getKey(),
                        'uploaded_by' => $request->user()->getKey(),
                        'file_path' => $path,
                        'original_filename' => $this->safeFilename($document->getClientOriginalName()),
                        'mime_type' => $document->getMimeType() ?: 'application/octet-stream',
                        'file_size' => $document->getSize(),
                        'sha256' => $sha256 ?: hash('sha256', $path),
                    ]);
                }

                $recipient->forceFill([
                    'proposal_submitted_at' => now(),
                    'proposal_message' => trim((string) ($validated['proposal_message'] ?? '')) ?: null,
                ])->save();
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        return back()->with('success', 'Your proposal documents were submitted securely.');
    }

    public function downloadProposalDocument(
        Request $request,
        EoiReportCommunicationRecipient $recipient,
        EoiReportProposalDocument $document
    ): StreamedResponse {
        $this->assertRecipientOwner($request, $recipient);
        abort_unless(
            (string) $document->recipient_id === (string) $recipient->getKey()
                && str_starts_with(
                    $document->file_path,
                    'eoi-communications/'.$recipient->communication_id.'/proposals/'.$recipient->getKey().'/'
                ),
            404
        );
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return $this->privateDownload(
            $document->file_path,
            $document->original_filename,
            $document->mime_type
        );
    }

    private function assertVendor(Request $request): void
    {
        $user = $request->user();

        abort_unless($user && $user->user_type === 'vendor', 403, 'Access denied. Vendor portal only.');
        abort_if($user->is_blacklisted, 403, 'Your vendor account has been blacklisted. Please contact the administrator.');
        abort_if($user->is_disabled, 403, 'Your vendor account has been disabled. Please contact the administrator.');
    }

    private function assertRecipientOwner(Request $request, EoiReportCommunicationRecipient $recipient): void
    {
        $this->assertVendor($request);
        abort_unless((string) $recipient->user_id === (string) $request->user()->getKey(), 404);
        $recipient->loadMissing('applicant');
        abort_if(
            $recipient->applicant
                && (string) $recipient->applicant->submitted_by !== (string) $request->user()->getKey(),
            404
        );
    }

    private function privateDownload(string $path, string $filename, string $mimeType): StreamedResponse
    {
        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim(str_replace(["\0", "\r", "\n", '/', '\\'], '-', $filename));

        return Str::limit($filename !== '' ? $filename : 'proposal-document', 240, '');
    }
}
