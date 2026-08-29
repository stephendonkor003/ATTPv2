<?php

namespace App\Services;

use App\Jobs\SendQualifiedProposalInvitation;
use App\Mail\ApplicantEvaluationRecordMail;
use App\Mail\QualifiedProposalInvitationMail;
use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationAttachment;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalRound;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\ReworkRequest;
use App\Models\User;
use App\Support\PdfBranding;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class EoiReportCommunicationService
{
    public const EVALUATOR_MASK = 'XXX-XXXX-XXXX';

    public const MAX_EMAIL_TEMPLATE_BYTES = 18 * 1024 * 1024;

    public const MAX_PROPOSAL_UPLOAD_BYTES = 25 * 1024 * 1024;

    /**
     * Final rows are exactly the rows the web report is allowed to present as a
     * released outcome. An incomplete panel is never communicated as final.
     */
    public function finalRows(array $report): Collection
    {
        return collect($report['applicants'] ?? [])
            ->filter(fn (array $row): bool => (bool) ($row['panel_complete'] ?? false)
                && in_array(
                    data_get($row, 'outcome.code'),
                    [
                        EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
                        EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
                        EoiQualificationService::OUTCOME_NOT_QUALIFIED,
                    ],
                    true
                ))
            ->values();
    }

    /** Qualified means the same thing here as it does in the web workflow. */
    public function qualifiedRows(array $report): Collection
    {
        return $this->finalRows($report)
            ->filter(fn (array $row): bool => (bool) ($row['can_advance'] ?? false)
                // New reports expose the shared top-eight decision. Older
                // historical report snapshots do not have this field, so they
                // remain readable without being silently reclassified.
                && (bool) ($row['within_qualified_shortlist'] ?? true)
                && ($row['applicant']->status ?? null) !== FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED
                && in_array(
                    data_get($row, 'outcome.code'),
                    [
                        EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
                        EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
                    ],
                    true
                ))
            ->values();
    }

    public function recipientPreview(array $report): array
    {
        return [
            'evaluation_records' => $this->previewRows($this->finalRows($report), false),
            'proposal_invitation' => $this->previewRows($this->qualifiedRows($report), true),
        ];
    }

    public function sendEvaluationRecords(Procurement $procurement, array $report, User $sender): array
    {
        [$communication, $report, $rows] = DB::transaction(function () use ($procurement, $sender): array {
            $lockedProcurement = Procurement::query()
                ->whereKey($procurement->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $currentReport = app(EoiQualificationService::class)
                ->buildProcurementReport($lockedProcurement);
            $currentRows = $this->finalRows($currentReport);

            if ($currentRows->isEmpty()) {
                throw ValidationException::withMessages([
                    'evaluation_records' => 'There are no current panel-complete EOI evaluation records to release.',
                ]);
            }

            $communication = EoiReportCommunication::create([
                'procurement_id' => $lockedProcurement->getKey(),
                'type' => EoiReportCommunication::TYPE_EVALUATION_RECORDS,
                'subject' => $this->cleanSubject('Your EOI evaluation outcome — '.$this->procurementLabel($lockedProcurement)),
                'message' => 'Your completed EOI evaluation record is attached. Evaluator identities have been protected.',
                'created_by' => $sender->getKey(),
            ]);

            foreach ($currentRows as $row) {
                $recipient = $this->createRecipient($communication, $row);
                $ineligibility = $this->contactIneligibility($row['applicant'], false);

                if ($ineligibility !== null) {
                    $this->skipRecipient($recipient, $ineligibility);
                }
            }

            return [$communication, $currentReport, $currentRows];
        }, 3);

        $recipientsByApplicant = $communication->fresh('recipients')->recipients
            ->keyBy(fn (EoiReportCommunicationRecipient $recipient): string => (string) $recipient->form_submission_id);

        foreach ($rows as $row) {
            $recipient = $recipientsByApplicant->get((string) $row['applicant']->getKey());

            if (! $recipient
                || $recipient->delivery_status !== EoiReportCommunicationRecipient::STATUS_PENDING) {
                continue;
            }

            try {
                $contents = $this->renderApplicantRecord($report, $row);
                $filename = 'eoi-evaluation-record-'.Str::slug(
                    $row['applicant']->procurement_submission_code ?: $row['applicant']->getKey()
                ).'.pdf';
                $path = 'eoi-communications/'.$communication->getKey().'/records/'.$recipient->getKey().'.pdf';

                $stored = Storage::disk('local')->put($path, $contents, ['visibility' => 'private']);

                if (! $stored) {
                    throw new \RuntimeException('The applicant evaluation PDF could not be stored.');
                }

                $recipient->forceFill([
                    'record_file_path' => $path,
                    'record_file_name' => $filename,
                    'record_mime_type' => 'application/pdf',
                    'record_file_size' => strlen($contents),
                    'record_sha256' => hash('sha256', $contents),
                ])->save();

                Mail::to($recipient->recipient_email, $recipient->recipient_name)
                    ->send(new ApplicantEvaluationRecordMail($recipient->fresh(['communication.procurement'])));

                $this->markSent($recipient);
            } catch (Throwable $exception) {
                $this->markFailed($recipient, $exception);
            }
        }

        $communication->forceFill(['sent_at' => now()])->save();

        return $this->deliverySummary($communication->fresh('recipients'));
    }

    /**
     * @param  array<int, UploadedFile>  $templates
     */
    public function sendProposalInvitation(
        Procurement $procurement,
        array $report,
        User $sender,
        string $subject,
        string $message,
        array $templates = [],
        ?EoiTechnicalProposalRound $technicalProposalRound = null
    ): array {
        $rows = $this->qualifiedRows($report);

        if ($technicalProposalRound) {
            $rows = EoiTechnicalProposalCandidate::query()
                ->where('round_id', $technicalProposalRound->getKey())
                ->whereIn('status', [
                    EoiTechnicalProposalCandidate::STATUS_INVITED,
                    EoiTechnicalProposalCandidate::STATUS_SUBMITTED,
                    EoiTechnicalProposalCandidate::STATUS_LATE,
                    EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW,
                ])
                ->whereHas('applicant', fn ($query) => $query->where(function ($statusQuery): void {
                    $statusQuery->whereNull('status')
                        ->orWhereNotIn('status', [
                            FormSubmission::STATUS_WITHDRAWN,
                            FormSubmission::STATUS_EOI_NOT_QUALIFIED,
                            FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED,
                            FormSubmission::STATUS_TECHNICAL_EVALUATION,
                        ]);
                }))
                ->with('applicant.submitter')
                ->get()
                ->map(fn (EoiTechnicalProposalCandidate $candidate): array => [
                    'applicant' => $candidate->applicant,
                    'outcome' => [
                        'code' => $candidate->eoi_outcome_code,
                        'label' => $candidate->eoi_outcome_label,
                    ],
                    'next_stage' => $candidate->workflow_decision,
                ])
                ->values();
        }

        $storedPaths = [];

        try {
            $communication = DB::transaction(function () use (
                $procurement,
                $rows,
                $sender,
                $subject,
                $message,
                $templates,
                $technicalProposalRound,
                &$storedPaths
            ): EoiReportCommunication {
                $communication = EoiReportCommunication::create([
                    'procurement_id' => $procurement->getKey(),
                    'type' => EoiReportCommunication::TYPE_PROPOSAL_INVITATION,
                    'subject' => $this->cleanSubject($subject),
                    'message' => trim($message),
                    'technical_proposal_round_id' => $technicalProposalRound?->getKey(),
                    'created_by' => $sender->getKey(),
                ]);

                if ($technicalProposalRound) {
                    $technicalProposalRound->loadMissing('templates');

                    foreach ($technicalProposalRound->templates as $template) {
                        EoiReportCommunicationAttachment::create([
                            'communication_id' => $communication->getKey(),
                            'uploaded_by' => $template->uploaded_by ?: $sender->getKey(),
                            'file_path' => $template->file_path,
                            'original_filename' => $template->original_filename,
                            'mime_type' => $template->mime_type,
                            'file_size' => $template->file_size,
                            'sha256' => $template->sha256,
                        ]);
                    }
                } else {
                    foreach ($templates as $template) {
                        $sha256 = hash_file('sha256', $template->getRealPath());
                        $extension = strtolower((string) $template->getClientOriginalExtension());
                        $path = $template->storeAs(
                            'eoi-communications/'.$communication->getKey().'/templates',
                            Str::uuid().($extension !== '' ? '.'.$extension : ''),
                            'local'
                        );

                        if (! is_string($path) || $path === '') {
                            throw new \RuntimeException('A proposal template could not be stored.');
                        }

                        $storedPaths[] = $path;
                        EoiReportCommunicationAttachment::create([
                            'communication_id' => $communication->getKey(),
                            'uploaded_by' => $sender->getKey(),
                            'file_path' => $path,
                            'original_filename' => $this->safeFilename($template->getClientOriginalName()),
                            'mime_type' => $template->getMimeType() ?: 'application/octet-stream',
                            'file_size' => $template->getSize(),
                            'sha256' => $sha256 ?: hash('sha256', $path),
                        ]);
                    }
                }

                foreach ($rows as $row) {
                    $recipient = $this->createRecipient($communication, $row);
                    $ineligibility = $this->contactIneligibility($row['applicant'], true);

                    if ($ineligibility !== null) {
                        $this->skipRecipient($recipient, $ineligibility);
                    }
                }

                return $communication;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $communication->load(['attachments', 'procurement', 'recipients.user']);

        foreach ($communication->recipients->where('delivery_status', EoiReportCommunicationRecipient::STATUS_PENDING) as $recipient) {
            // SMTP must never hold the administrator's HTTP request open. The
            // recipient row is the durable outbox; the scheduled recovery
            // command can pick it up if PHP is stopped after the response.
            SendQualifiedProposalInvitation::dispatchAfterResponse((string) $recipient->getKey());
        }

        $this->finalizeCommunicationIfComplete($communication->getKey());

        return $this->deliverySummary($communication->fresh('recipients'));
    }

    /**
     * Re-dispatch an unfinished batch created by an interrupted older request.
     * This prevents an administrator retry from creating another round and
     * sending duplicate invitations.
     */
    public function resumePendingProposalInvitation(Procurement $procurement): ?array
    {
        $communication = EoiReportCommunication::query()
            ->where('procurement_id', $procurement->getKey())
            ->where('type', EoiReportCommunication::TYPE_PROPOSAL_INVITATION)
            ->whereHas('recipients', fn ($query) => $query->whereIn('delivery_status', [
                EoiReportCommunicationRecipient::STATUS_PENDING,
                EoiReportCommunicationRecipient::STATUS_PROCESSING,
            ]))
            ->with(['attachments', 'procurement', 'recipients.user'])
            ->latest('created_at')
            ->first();

        if (! $communication) {
            return null;
        }

        foreach ($communication->recipients->where('delivery_status', EoiReportCommunicationRecipient::STATUS_PENDING) as $recipient) {
            SendQualifiedProposalInvitation::dispatchAfterResponse((string) $recipient->getKey());
        }

        return $this->deliverySummary($communication);
    }

    /**
     * Deliver one claimed proposal invitation. This is shared by the
     * after-response job and the scheduled recovery command.
     */
    public function deliverProposalInvitationRecipient(string $recipientId): bool
    {
        $recipient = DB::transaction(function () use ($recipientId): ?EoiReportCommunicationRecipient {
            $recipient = EoiReportCommunicationRecipient::query()
                ->with('communication')
                ->lockForUpdate()
                ->find($recipientId);

            if (! $recipient
                || $recipient->communication?->type !== EoiReportCommunication::TYPE_PROPOSAL_INVITATION) {
                return null;
            }

            $staleProcessing = $recipient->delivery_status === EoiReportCommunicationRecipient::STATUS_PROCESSING
                && $recipient->updated_at?->lessThanOrEqualTo(now()->subMinutes(10));

            if ($recipient->delivery_status !== EoiReportCommunicationRecipient::STATUS_PENDING && ! $staleProcessing) {
                return null;
            }

            $recipient->forceFill([
                'delivery_status' => EoiReportCommunicationRecipient::STATUS_PROCESSING,
                'delivery_error' => null,
            ])->save();

            return $recipient;
        });

        if (! $recipient) {
            return false;
        }

        try {
            $recipient->loadMissing([
                'communication.procurement',
                'communication.attachments',
                'communication.technicalProposalRound.templates',
                'user',
            ]);

            if ($recipient->communication?->technical_proposal_round_id) {
                $candidateCanBeInvited = EoiTechnicalProposalCandidate::query()
                    ->where('round_id', $recipient->communication->technical_proposal_round_id)
                    ->where('form_submission_id', $recipient->form_submission_id)
                    ->whereIn('status', [
                        EoiTechnicalProposalCandidate::STATUS_INVITED,
                        EoiTechnicalProposalCandidate::STATUS_SUBMITTED,
                        EoiTechnicalProposalCandidate::STATUS_LATE,
                        EoiTechnicalProposalCandidate::STATUS_UNDER_REVIEW,
                    ])
                    ->whereHas('applicant', fn ($query) => $query->where(function ($statusQuery): void {
                        $statusQuery->whereNull('status')
                            ->orWhereNotIn('status', [
                                FormSubmission::STATUS_WITHDRAWN,
                                FormSubmission::STATUS_EOI_NOT_QUALIFIED,
                                FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED,
                                FormSubmission::STATUS_TECHNICAL_EVALUATION,
                            ]);
                    }))
                    ->exists();

                if ($candidateCanBeInvited
                    && ReworkRequest::query()
                        ->where('form_submission_id', $recipient->form_submission_id)
                        ->where('status', ReworkRequest::STATUS_PENDING)
                        ->exists()) {
                    $candidateCanBeInvited = false;
                }

                if (! $candidateCanBeInvited) {
                    $this->skipRecipient(
                        $recipient,
                        'The applicant is no longer eligible for this proposal-round notification.'
                    );

                    return false;
                }
            }

            Mail::to($recipient->recipient_email, $recipient->recipient_name)
                ->send(new QualifiedProposalInvitationMail($recipient));

            $this->markSent($recipient);

            if ($recipient->form_submission_id) {
                FormSubmission::query()
                    ->whereKey($recipient->form_submission_id)
                    ->where(function ($statusQuery): void {
                        $statusQuery->whereNull('status')
                            ->orWhereNotIn('status', [
                                FormSubmission::STATUS_WITHDRAWN,
                                FormSubmission::STATUS_EOI_NOT_QUALIFIED,
                                FormSubmission::STATUS_TECHNICAL_PROPOSAL_SUBMITTED,
                                FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED,
                                FormSubmission::STATUS_TECHNICAL_EVALUATION,
                            ]);
                    })
                    ->update(['status' => FormSubmission::STATUS_TECHNICAL_PROPOSAL_INVITED]);
            }
        } catch (Throwable $exception) {
            $this->markFailed($recipient, $exception);
        } finally {
            $this->finalizeCommunicationIfComplete($recipient->communication_id);
        }

        return $recipient->fresh()->delivery_status === EoiReportCommunicationRecipient::STATUS_SENT;
    }

    /** @return Collection<int, string> */
    public function recoverableProposalInvitationRecipientIds(int $limit = 25): Collection
    {
        return EoiReportCommunicationRecipient::query()
            ->whereHas('communication', fn ($query) => $query
                ->where('type', EoiReportCommunication::TYPE_PROPOSAL_INVITATION))
            ->where(function ($query): void {
                $query->where('delivery_status', EoiReportCommunicationRecipient::STATUS_PENDING)
                    ->orWhere(function ($query): void {
                        $query->where('delivery_status', EoiReportCommunicationRecipient::STATUS_PROCESSING)
                            ->where('updated_at', '<=', now()->subMinutes(10));
                    });
            })
            ->oldest('created_at')
            ->limit(max(1, min($limit, 100)))
            ->pluck('id');
    }

    public function assertCombinedUploadSize(
        array $files,
        string $field = 'documents',
        int $maximumBytes = self::MAX_PROPOSAL_UPLOAD_BYTES
    ): void {
        $size = collect($files)
            ->filter(fn ($file): bool => $file instanceof UploadedFile)
            ->sum(fn (UploadedFile $file): int => max(0, (int) $file->getSize()));

        if ($size > $maximumBytes) {
            $maximumMegabytes = (int) floor($maximumBytes / 1024 / 1024);

            throw ValidationException::withMessages([
                $field => "The combined size of all uploaded files may not exceed {$maximumMegabytes} MB.",
            ]);
        }
    }

    private function createRecipient(EoiReportCommunication $communication, array $row): EoiReportCommunicationRecipient
    {
        /** @var FormSubmission $applicant */
        $applicant = $row['applicant'];
        $user = $this->linkedUser($applicant);

        return EoiReportCommunicationRecipient::create([
            'communication_id' => $communication->getKey(),
            'form_submission_id' => $applicant->getKey(),
            'user_id' => $user?->getKey(),
            'recipient_name' => $applicant->display_name,
            'recipient_email' => trim((string) $user?->email),
            'outcome_code' => (string) data_get($row, 'outcome.code'),
            'outcome_label' => (string) data_get($row, 'outcome.label'),
            'workflow_decision' => (string) ($row['next_stage'] ?? 'Awaiting EOI panel'),
            'delivery_status' => EoiReportCommunicationRecipient::STATUS_PENDING,
        ]);
    }

    private function contactIneligibility(FormSubmission $applicant, bool $requiresVendorPortal): ?string
    {
        $user = $this->linkedUser($applicant);

        if ($requiresVendorPortal
            && $applicant->exists
            && ReworkRequest::query()
                ->where('form_submission_id', $applicant->getKey())
                ->where('status', ReworkRequest::STATUS_PENDING)
                ->exists()) {
            return 'The applicant has an EOI evaluation awaiting rework. Proposal notification is paused.';
        }

        if ($requiresVendorPortal && in_array($applicant->status, [
            FormSubmission::STATUS_EOI_NOT_QUALIFIED,
            FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED,
            FormSubmission::STATUS_WITHDRAWN,
        ], true)) {
            return 'The applicant does not currently have a valid EOI qualification for the proposal stage.';
        }

        if (! $user) {
            return 'No applicant account is linked to this submission.';
        }

        if ($requiresVendorPortal && $user->user_type !== 'vendor') {
            return 'The linked account does not have vendor portal access.';
        }

        if ((bool) $user->is_blacklisted) {
            return 'The linked applicant account is blacklisted.';
        }

        if ((bool) $user->is_disabled) {
            return 'The linked applicant account is disabled.';
        }

        $email = strtolower(trim((string) $user->email));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'No deliverable email address is available.';
        }

        if (str_ends_with($email, '@africathinktank.africa')) {
            return 'The imported placeholder email address cannot receive notifications.';
        }

        return null;
    }

    private function linkedUser(FormSubmission $applicant): ?User
    {
        if ($applicant->relationLoaded('submitter')) {
            return $applicant->getRelation('submitter');
        }

        $user = $applicant->submitter()->first();
        $applicant->setRelation('submitter', $user);

        return $user;
    }

    private function previewRows(Collection $rows, bool $requiresVendorPortal): array
    {
        $recipients = $rows->map(function (array $row) use ($requiresVendorPortal): array {
            /** @var FormSubmission $applicant */
            $applicant = $row['applicant'];
            $reason = $this->contactIneligibility($applicant, $requiresVendorPortal);

            return [
                'name' => $applicant->display_name,
                'email' => trim((string) $applicant->submitter?->email),
                'eligible' => $reason === null,
                'reason' => $reason,
            ];
        })->values();

        return [
            'total' => $recipients->count(),
            'eligible' => $recipients->where('eligible', true)->count(),
            'unsendable' => $recipients->where('eligible', false)->count(),
            'recipients' => $recipients,
        ];
    }

    private function renderApplicantRecord(array $report, array $row): string
    {
        $identifiers = collect($row['evaluation_reports'] ?? [])
            ->flatMap(fn (array $evaluation): Collection => collect($evaluation['members'] ?? [])
                ->flatMap(fn (array $member): array => [
                    $member['name'] ?? null,
                    $member['email'] ?? null,
                    $member['evaluator_id'] ?? null,
                ]))
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->sortByDesc(fn (string $value): int => strlen($value))
            ->values()
            ->all();

        $evaluations = collect($row['evaluation_reports'] ?? [])->map(function (array $evaluation) use ($identifiers): array {
            return [
                'name' => $this->redactEvaluatorIdentifiers(
                    (string) ($evaluation['evaluation']?->name ?? 'EOI Evaluation'),
                    $identifiers
                ),
                'description' => $this->redactEvaluatorIdentifiers(
                    (string) ($evaluation['evaluation']?->description ?? ''),
                    $identifiers
                ),
                'members' => collect($evaluation['members'] ?? [])->values()->map(
                    fn (array $member, int $index): array => [
                        'number' => $index + 1,
                        'name' => self::EVALUATOR_MASK,
                        'submitted' => (bool) ($member['submitted'] ?? false),
                        'task_complete' => (bool) ($member['task_complete'] ?? false),
                        'submitted_at' => $member['submitted_at'] ?? null,
                        'counts' => $member['counts'] ?? [],
                    ]
                ),
                'criteria' => collect($evaluation['criteria'] ?? [])->map(fn (array $criterion): array => [
                    'name' => $this->redactEvaluatorIdentifiers(
                        (string) ($criterion['criterion']?->name
                            ?? $criterion['criterion']?->criterion
                            ?? 'Criterion'),
                        $identifiers
                    ),
                    'section' => $this->redactEvaluatorIdentifiers(
                        (string) ($criterion['section']?->name
                            ?? $criterion['section']?->title
                            ?? 'Evaluation criteria'),
                        $identifiers
                    ),
                    'description' => $this->redactEvaluatorIdentifiers(
                        (string) ($criterion['criterion']?->description ?? ''),
                        $identifiers
                    ),
                    'outcome' => $criterion['outcome'] ?? [],
                    'assessments' => collect($criterion['assessments'] ?? [])->values()->map(
                        fn (array $assessment, int $index): array => [
                            'number' => $index + 1,
                            'evaluator' => self::EVALUATOR_MASK,
                            'label' => (string) ($assessment['label'] ?? ''),
                            'comment' => $this->redactEvaluatorIdentifiers(
                                (string) ($assessment['comment'] ?? ''),
                                $identifiers
                            ),
                        ]
                    ),
                ]),
            ];
        });

        return Pdf::loadView('reports.evaluations.pdf.eoi-applicant-record', array_merge([
            'procurement' => $report['procurement'],
            'applicant' => $row['applicant'],
            'row' => $row,
            'evaluations' => $evaluations,
            'generatedAt' => now(),
            'evaluatorMask' => self::EVALUATOR_MASK,
        ], PdfBranding::viewData()))
            ->setPaper('a4', 'portrait')
            ->output();
    }

    private function redactEvaluatorIdentifiers(string $text, array $identifiers): string
    {
        return str_ireplace($identifiers, self::EVALUATOR_MASK, $text);
    }

    private function markSent(EoiReportCommunicationRecipient $recipient): void
    {
        $recipient->forceFill([
            'delivery_status' => EoiReportCommunicationRecipient::STATUS_SENT,
            'delivery_error' => null,
            'emailed_at' => now(),
        ])->save();
    }

    private function markFailed(EoiReportCommunicationRecipient $recipient, Throwable $exception): void
    {
        Log::error('EOI applicant communication failed.', [
            'recipient_id' => $recipient->getKey(),
            'communication_id' => $recipient->communication_id,
            'exception' => $exception,
        ]);

        $recipient->forceFill([
            'delivery_status' => EoiReportCommunicationRecipient::STATUS_FAILED,
            'delivery_error' => Str::limit($exception->getMessage(), 1000),
        ])->save();
    }

    private function finalizeCommunicationIfComplete(string $communicationId): void
    {
        $hasOutstandingRecipients = EoiReportCommunicationRecipient::query()
            ->where('communication_id', $communicationId)
            ->whereIn('delivery_status', [
                EoiReportCommunicationRecipient::STATUS_PENDING,
                EoiReportCommunicationRecipient::STATUS_PROCESSING,
            ])
            ->exists();

        if (! $hasOutstandingRecipients) {
            EoiReportCommunication::query()
                ->whereKey($communicationId)
                ->whereNull('sent_at')
                ->update(['sent_at' => now()]);
        }
    }

    private function skipRecipient(EoiReportCommunicationRecipient $recipient, string $reason): void
    {
        $recipient->forceFill([
            'delivery_status' => EoiReportCommunicationRecipient::STATUS_SKIPPED,
            'delivery_error' => $reason,
        ])->save();
    }

    private function deliverySummary(EoiReportCommunication $communication): array
    {
        $recipients = $communication->recipients;

        return [
            'communication' => $communication,
            'total' => $recipients->count(),
            'pending' => $recipients->whereIn('delivery_status', [
                EoiReportCommunicationRecipient::STATUS_PENDING,
                EoiReportCommunicationRecipient::STATUS_PROCESSING,
            ])->count(),
            'sent' => $recipients->where('delivery_status', EoiReportCommunicationRecipient::STATUS_SENT)->count(),
            'failed' => $recipients->where('delivery_status', EoiReportCommunicationRecipient::STATUS_FAILED)->count(),
            'skipped' => $recipients->where('delivery_status', EoiReportCommunicationRecipient::STATUS_SKIPPED)->count(),
        ];
    }

    private function procurementLabel(Procurement $procurement): string
    {
        return trim((string) ($procurement->reference_no ?: $procurement->title ?: 'Procurement'));
    }

    private function cleanSubject(string $subject): string
    {
        return Str::squish(str_replace(["\r", "\n"], ' ', $subject));
    }

    private function safeFilename(string $filename): string
    {
        $filename = trim(str_replace(["\0", "\r", "\n", '/', '\\'], '-', $filename));

        return Str::limit($filename !== '' ? $filename : 'attachment', 240, '');
    }
}
