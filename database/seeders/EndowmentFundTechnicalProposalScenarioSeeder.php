<?php

namespace Database\Seeders;

use App\Models\EoiReportCommunication;
use App\Models\EoiReportCommunicationRecipient;
use App\Models\EoiTechnicalProposalCandidate;
use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalRound;
use App\Models\EoiTechnicalProposalRule;
use App\Models\EoiTechnicalProposalRuleApplication;
use App\Models\EoiTechnicalProposalSubmission;
use App\Models\EvaluationAssignment;
use App\Models\EvaluationSubmission;
use App\Models\FormSubmission;
use App\Models\Procurement;
use App\Models\User;
use App\Services\EoiQualificationService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Reconstructs the documented physical-delivery technical-proposal outcome
 * for ET-AUC-494958-CS-QCBS without sending mail or changing the original
 * imported proposal round. It is deliberately not part of DatabaseSeeder.
 *
 * The RFP recipient roster is an explicit historical register. Impact Africa
 * Consulting is deliberately included even though the current live ranking
 * shows it below the automatic top-eight shortlist; its candidate snapshot
 * records that inclusion as a documented RFP-recipient override.
 */
final class EndowmentFundTechnicalProposalScenarioSeeder extends Seeder
{
    public const PROCUREMENT_REFERENCE = 'ET-AUC- 494958-CS-QCBS';

    public const ROUND_TITLE = 'Technical Proposal Submission — AU Physical Delivery';

    private const COMMUNICATION_SUBJECT = 'Request for Technical Proposal — physical delivery to the AU office';

    /**
     * The three unsubmitted names are the highest-ranked remaining firms in
     * the imported register after the five applicants explicitly named in the
     * scenario. Keep this list explicit so the historical RFP roster remains
     * auditable and does not silently change if panel rankings are recalculated.
     *
     * @var array<int, string>
     */
    private const RFP_RECIPIENTS = [
        'KPMG',
        'Impact Africa Consulting',
        'BwB',
        'LNO',
        'AVAHI',
        'Genesis Analytics',
        'RebelGroup',
        'BDO',
    ];

    /** @var array<int, string> */
    private const ACCEPTED_APPLICANTS = [
        'KPMG',
        'Impact Africa Consulting',
        'BwB',
        'LNO',
    ];

    private const ELECTRONICALLY_DISQUALIFIED_APPLICANT = 'AVAHI';

    /**
     * These proposal scans are committed with the dedicated seeder, rather
     * than being read from a developer's Downloads directory. This makes a
     * deployment deterministic after `git pull` and `git lfs pull`.
     */
    private const BUNDLED_ASSET_DIRECTORY = 'seeders/assets/endowment-fund-technical-proposals';

    /**
     * @var array<string, array<int, string>>
     */
    private const PROPOSAL_DOCUMENT_FILENAMES = [
        'KPMG' => ['Auc (KPMG)_compressed.pdf'],
        'Impact Africa Consulting' => ['Impact Africa August 2026_compressed.pdf'],
        'BwB' => ['Power of Attorney_compressed.pdf'],
        'LNO' => ['LNO.pdf'],
    ];

    /**
     * Immutable fingerprints of the supplied historical files. The dedicated
     * reconstruction must never accept a correctly named but wrong scan.
     *
     * @var array<string, string>
     */
    private const PROPOSAL_DOCUMENT_SHA256 = [
        'Auc (KPMG)_compressed.pdf' => '23f91a5c03a4fec91a0fe4f643b0da49bf5b6ed2efdf7a1f8f38f2613fd06aec',
        'Impact Africa August 2026_compressed.pdf' => '485876f28273730375b3809964a5a5714560049a92b15f6f66bae202d38f1b21',
        'Power of Attorney_compressed.pdf' => '6e9ffbc85841f12c9d138fb4c2db697183615456ee17f7ec8150c99ab87ddbb7',
        'LNO.pdf' => 'dafe11cbf0e757f73739248bfe62336105b8e7bc09c7357e2077df2da4a1a182',
    ];

    /**
     * A prior local reconstruction mistakenly attached LNO's scan to BwB
     * under this generic name. Remove that stale copy on a safe re-run before
     * evaluators have been assigned.
     *
     * @var array<int, string>
     */
    private const LEGACY_SEED_DOCUMENT_FILENAMES = ['Technical Proposal_compressed.pdf'];

    /** @var array<int, string> */
    private array $newStoragePaths = [];

    /** @var array<int, string> */
    private array $receiptOnlyDigitalArchiveApplicants = [];

    public function run(): void
    {
        $this->newStoragePaths = [];
        $this->receiptOnlyDigitalArchiveApplicants = [];

        $procurement = Procurement::query()
            ->where('reference_no', self::PROCUREMENT_REFERENCE)
            ->first();

        if (! $procurement) {
            throw new RuntimeException(
                'The Endowment Fund procurement '.self::PROCUREMENT_REFERENCE.' was not found. '
                .'Import the live procurement data before running this dedicated scenario seeder.'
            );
        }

        $actor = $this->resolveActor($procurement);
        $applicants = $this->resolveApplicants($procurement);
        $reportRows = $this->qualifiedReportRows($procurement, $applicants);

        try {
            $round = DB::transaction(function () use ($procurement, $actor, $applicants, $reportRows): EoiTechnicalProposalRound {
                $lockedProcurement = Procurement::query()
                    ->whereKey($procurement->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $round = EoiTechnicalProposalRound::query()
                    ->where('procurement_id', $lockedProcurement->getKey())
                    ->where('title', self::ROUND_TITLE)
                    ->lockForUpdate()
                    ->first();

                if ($round) {
                    $this->assertRoundCanBeSeeded($round);
                } else {
                    $nextRoundNumber = ((int) EoiTechnicalProposalRound::query()
                        ->where('procurement_id', $lockedProcurement->getKey())
                        ->max('round_number')) + 1;

                    $round = new EoiTechnicalProposalRound([
                        'procurement_id' => $lockedProcurement->getKey(),
                        'round_number' => $nextRoundNumber,
                        'title' => self::ROUND_TITLE,
                        'created_by' => $actor->getKey(),
                    ]);
                }

                $timeline = $this->timeline();
                $round->forceFill([
                    'instructions' => 'Historical RFP reconstruction: proposals had to be delivered physically to the AU office. '
                        .'Portal and email delivery were not accepted. This round creates no mail and preserves the earlier imported round as history.',
                    'opens_at' => $timeline['opens_at'],
                    'deadline_at' => $timeline['deadline_at'],
                    'timezone' => $timeline['timezone'],
                    'late_policy' => EoiTechnicalProposalRound::LATE_REJECT,
                    'portal_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
                    'email_requirement' => EoiTechnicalProposalRound::REQUIREMENT_NOT_ALLOWED,
                    'physical_requirement' => EoiTechnicalProposalRound::REQUIREMENT_REQUIRED,
                    'status' => EoiTechnicalProposalRound::STATUS_CLOSED,
                    'published_by' => $actor->getKey(),
                    'published_at' => $timeline['published_at'],
                    'closed_at' => $timeline['closed_at'],
                ])->save();

                $rules = $this->upsertRules($round, $actor);
                $communication = $this->upsertInvitationCommunication($lockedProcurement, $round, $actor, $timeline);

                foreach (self::RFP_RECIPIENTS as $position => $name) {
                    $applicant = $applicants->get($name);
                    $reportRow = $reportRows->get((string) $applicant->getKey());

                    if (in_array($name, self::ACCEPTED_APPLICANTS, true)) {
                        $this->seedAcceptedApplicant(
                            $round,
                            $applicant,
                            $reportRow,
                            $rules,
                            $actor,
                            $timeline,
                            $position
                        );
                    } elseif ($name === self::ELECTRONICALLY_DISQUALIFIED_APPLICANT) {
                        $this->seedElectronicallyDisqualifiedApplicant(
                            $round,
                            $applicant,
                            $reportRow,
                            $rules,
                            $actor,
                            $timeline,
                            $position
                        );
                    } else {
                        $this->seedInvitedOnlyApplicant($round, $applicant, $reportRow, $timeline, $position);
                    }

                    $candidate = EoiTechnicalProposalCandidate::query()
                        ->where('round_id', $round->getKey())
                        ->where('form_submission_id', $applicant->getKey())
                        ->firstOrFail();

                    $this->upsertCommunicationRecipient(
                        $communication,
                        $applicant,
                        $candidate,
                        $timeline['published_at']
                    );
                }

                return $round->fresh([
                    'candidates.applicant.values',
                    'candidates.submissions.documents',
                    'candidates.ruleApplications',
                    'rules',
                ]);
            }, 3);
        } catch (Throwable $exception) {
            foreach ($this->newStoragePaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $summary = $round->candidates
            ->map(function (EoiTechnicalProposalCandidate $candidate): array {
                $candidate->loadMissing('applicant.values');

                return [
                    $candidate->applicant?->display_name ?: 'Unknown applicant',
                    Str::headline($candidate->status),
                    (string) $candidate->submissions->count(),
                ];
            })
            ->sortBy(fn (array $row): string => $row[0])
            ->values()
            ->all();

        $this->command?->info('Endowment Fund physical-delivery technical-proposal scenario is ready. No email was sent.');
        $this->command?->table(['Applicant', 'Technical proposal outcome', 'Recorded revisions'], $summary);
        $this->command?->line('Round '.$round->round_number.' is closed and ready for technical evaluator assignment.');

        if ($this->receiptOnlyDigitalArchiveApplicants !== []) {
            $this->command?->warn(
                implode(', ', array_unique($this->receiptOnlyDigitalArchiveApplicants))
                .' has a physical-delivery receipt and supporting document on record, but its primary proposal scan is still pending in the digital archive.'
            );
        }
    }

    private function resolveActor(Procurement $procurement): User
    {
        $actor = User::query()->find($procurement->created_by)
            ?? User::query()
                ->where(function ($query): void {
                    $query->whereNull('user_type')
                        ->orWhereNotIn('user_type', ['vendor', 'think_tank']);
                })
                ->orderBy('created_at')
                ->first()
            ?? User::query()->orderBy('created_at')->first();

        if (! $actor) {
            throw new RuntimeException('A system or administrator account is required to seed the technical-proposal audit trail.');
        }

        return $actor;
    }

    /** @return Collection<string, FormSubmission> */
    private function resolveApplicants(Procurement $procurement): Collection
    {
        $applicantsByName = FormSubmission::query()
            ->where('procurement_id', $procurement->getKey())
            ->with(['submitter', 'values'])
            ->get()
            ->groupBy(fn (FormSubmission $applicant): string => $this->normaliseName($applicant->display_name));

        $resolved = collect();

        foreach (self::RFP_RECIPIENTS as $name) {
            $matches = $applicantsByName->get($this->normaliseName($name), collect());

            if ($matches->count() !== 1) {
                throw new RuntimeException(
                    sprintf(
                        'Expected exactly one Endowment Fund application named "%s"; found %d.',
                        $name,
                        $matches->count()
                    )
                );
            }

            $resolved->put($name, $matches->first());
        }

        return $resolved;
    }

    /** @return Collection<string, array<string, mixed>> */
    private function qualifiedReportRows(Procurement $procurement, Collection $applicants): Collection
    {
        $report = app(EoiQualificationService::class)->buildProcurementReport($procurement);
        $rows = collect($report['applicants'] ?? [])
            ->keyBy(fn (array $row): string => (string) $row['applicant']->getKey());

        foreach ($applicants as $name => $applicant) {
            $row = $rows->get((string) $applicant->getKey());
            $outcome = data_get($row, 'outcome.code');

            if (! $row || ! in_array($outcome, [
                EoiQualificationService::OUTCOME_FULLY_QUALIFIED,
                EoiQualificationService::OUTCOME_AVERAGE_QUALIFIED,
            ], true)) {
                throw new RuntimeException(
                    'The requested RFP recipient '.$name.' is not panel-qualified in the current Endowment Fund EOI report.'
                );
            }
        }

        return $rows;
    }

    /**
     * @return array{timezone: string, opens_at: CarbonImmutable, deadline_at: CarbonImmutable, published_at: CarbonImmutable, closed_at: CarbonImmutable}
     */
    private function timeline(): array
    {
        $timezone = 'Africa/Nairobi';
        $deadline = CarbonImmutable::now($timezone)
            ->subDays(7)
            ->setTime(17, 0);

        return [
            'timezone' => $timezone,
            'opens_at' => $deadline->subDays(14)->setTime(8, 0),
            'deadline_at' => $deadline,
            'published_at' => $deadline->subDays(14)->setTime(8, 0),
            'closed_at' => $deadline->addDay()->setTime(9, 0),
        ];
    }

    private function assertRoundCanBeSeeded(EoiTechnicalProposalRound $round): void
    {
        $candidateIds = EoiTechnicalProposalCandidate::query()
            ->where('round_id', $round->getKey())
            ->pluck('id');

        $hasStartedTechnicalWork = EvaluationAssignment::query()
            ->where('technical_proposal_round_id', $round->getKey())
            ->exists()
            || ($candidateIds->isNotEmpty() && EvaluationSubmission::query()
                ->whereIn('technical_proposal_candidate_id', $candidateIds)
                ->exists());

        if ($hasStartedTechnicalWork) {
            throw new RuntimeException(
                'The dedicated physical-delivery scenario round already has technical evaluation work. '
                .'It will not be reseeded or altered after evaluators have started.'
            );
        }
    }

    /** @return Collection<string, EoiTechnicalProposalRule> */
    private function upsertRules(EoiTechnicalProposalRound $round, User $actor): Collection
    {
        $definitions = [
            'DEADLINE' => [
                'title' => 'Submission received by the stated deadline',
                'description' => 'The physical proposal had to be received no later than the published deadline.',
                'category' => EoiTechnicalProposalRule::CATEGORY_DEADLINE,
                'sort_order' => 10,
            ],
            'PHYSICAL_AU_OFFICE' => [
                'title' => 'Physical proposal delivered to the AU office',
                'description' => 'A physical copy at the AU office was mandatory. Portal and email submissions were not accepted.',
                'category' => EoiTechnicalProposalRule::CATEGORY_CHANNEL,
                'sort_order' => 20,
            ],
            'REQUIRED_DOCUMENTS' => [
                'title' => 'Required technical proposal documents are complete',
                'description' => 'The physical technical proposal package contains the required forms and supporting documents.',
                'category' => EoiTechnicalProposalRule::CATEGORY_DOCUMENT,
                'sort_order' => 30,
            ],
        ];

        return collect($definitions)->mapWithKeys(function (array $definition, string $code) use ($round, $actor): array {
            $rule = EoiTechnicalProposalRule::query()->updateOrCreate(
                ['round_id' => $round->getKey(), 'code' => $code],
                [
                    ...$definition,
                    'is_mandatory' => true,
                    'is_disqualifying' => true,
                    'requires_acknowledgement' => false,
                    'created_by' => $actor->getKey(),
                ]
            );

            return [$code => $rule];
        });
    }

    private function upsertInvitationCommunication(
        Procurement $procurement,
        EoiTechnicalProposalRound $round,
        User $actor,
        array $timeline
    ): EoiReportCommunication {
        return EoiReportCommunication::query()->updateOrCreate(
            [
                'procurement_id' => $procurement->getKey(),
                'type' => EoiReportCommunication::TYPE_PROPOSAL_INVITATION,
                'technical_proposal_round_id' => $round->getKey(),
            ],
            [
                'subject' => self::COMMUNICATION_SUBJECT,
                'message' => 'Historical delivery record reconstructed from the physical-delivery scenario. '
                    .'The request required a physical proposal at the AU office; this seeder does not send mail.',
                'created_by' => $actor->getKey(),
                'sent_at' => $timeline['published_at'],
            ]
        );
    }

    private function seedAcceptedApplicant(
        EoiTechnicalProposalRound $round,
        FormSubmission $applicant,
        array $reportRow,
        Collection $rules,
        User $actor,
        array $timeline,
        int $position
    ): void {
        $name = $applicant->display_name;
        $candidate = $this->upsertCandidate(
            $round,
            $applicant,
            $reportRow,
            $name === 'Impact Africa Consulting'
                ? 'Technical Evaluation — documented RFP recipient override'
                : 'Technical Evaluation',
            EoiTechnicalProposalCandidate::STATUS_QUALIFIED,
            $timeline['published_at']
        );

        $receivedAt = $timeline['deadline_at']->subDays(2)->addMinutes($position * 11);
        $proposal = EoiTechnicalProposalSubmission::query()->updateOrCreate(
            ['candidate_id' => $candidate->getKey(), 'revision_number' => 1],
            [
                'source' => EoiTechnicalProposalSubmission::SOURCE_ADMIN_CAPTURE,
                'received_via' => EoiTechnicalProposalSubmission::CHANNEL_PHYSICAL,
                'received_at' => $receivedAt,
                'uploaded_at' => $timeline['closed_at'],
                'is_late' => false,
                'minutes_late' => 0,
                'capture_note' => 'Physical technical proposal received at the AU office and registered for technical evaluation.',
                'submitted_by' => $applicant->submitted_by,
                'captured_by' => $actor->getKey(),
            ]
        );

        $this->storeAcceptedProposalDocuments($name, $round, $candidate, $proposal, $actor);

        foreach ($rules as $rule) {
            $this->upsertRuleFinding(
                $candidate,
                $rule,
                $proposal,
                EoiTechnicalProposalRuleApplication::FINDING_COMPLIANT,
                EoiTechnicalProposalRuleApplication::EFFECT_NONE,
                'Physical receipt and proposal package checked against the required condition.',
                $actor,
                $timeline['closed_at']
            );
        }

        $candidate->forceFill([
            'status' => EoiTechnicalProposalCandidate::STATUS_QUALIFIED,
            'first_submitted_at' => $receivedAt,
            'last_submitted_at' => $receivedAt,
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => $timeline['closed_at'],
        ])->save();

        $applicant->forceFill(['status' => FormSubmission::STATUS_TECHNICAL_EVALUATION])->save();
    }

    private function seedElectronicallyDisqualifiedApplicant(
        EoiTechnicalProposalRound $round,
        FormSubmission $applicant,
        array $reportRow,
        Collection $rules,
        User $actor,
        array $timeline,
        int $position
    ): void {
        $candidate = $this->upsertCandidate(
            $round,
            $applicant,
            $reportRow,
            'Does not advance — physical delivery requirement not met',
            EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED,
            $timeline['published_at']
        );
        $receivedAt = $timeline['deadline_at']->subDays(2)->addMinutes($position * 11);
        $proposal = EoiTechnicalProposalSubmission::query()->updateOrCreate(
            ['candidate_id' => $candidate->getKey(), 'revision_number' => 1],
            [
                'source' => EoiTechnicalProposalSubmission::SOURCE_ADMIN_CAPTURE,
                'received_via' => EoiTechnicalProposalSubmission::CHANNEL_EMAIL,
                'received_at' => $receivedAt,
                'uploaded_at' => $timeline['closed_at'],
                'is_late' => false,
                'minutes_late' => 0,
                'capture_note' => 'Electronic proposal received for audit only. It cannot substitute for the required physical delivery to the AU office.',
                'submitted_by' => $applicant->submitted_by,
                'captured_by' => $actor->getKey(),
            ]
        );

        $this->storeReceiptRecord(
            'AVAHI electronic submission record',
            'AVAHI submitted electronically. The round required a physical technical proposal delivered to the AU office, so this electronic submission is retained only as an audit record and is not accepted.',
            $round,
            $candidate,
            $proposal,
            $actor,
            'Electronic submission audit record — not accepted'
        );

        foreach ($rules as $code => $rule) {
            $isChannelRule = $code === 'PHYSICAL_AU_OFFICE';
            $this->upsertRuleFinding(
                $candidate,
                $rule,
                $proposal,
                $isChannelRule
                    ? EoiTechnicalProposalRuleApplication::FINDING_NON_COMPLIANT
                    : EoiTechnicalProposalRuleApplication::FINDING_COMPLIANT,
                $isChannelRule
                    ? EoiTechnicalProposalRuleApplication::EFFECT_DISQUALIFY
                    : EoiTechnicalProposalRuleApplication::EFFECT_NONE,
                $isChannelRule
                    ? 'Submitted electronically; a physical proposal delivered to the AU office was mandatory.'
                    : 'Recorded for audit; the disqualifying delivery-channel finding controls the outcome.',
                $actor,
                $timeline['closed_at']
            );
        }

        $candidate->forceFill([
            'status' => EoiTechnicalProposalCandidate::STATUS_DISQUALIFIED,
            'first_submitted_at' => $receivedAt,
            'last_submitted_at' => $receivedAt,
            'reviewed_by' => $actor->getKey(),
            'reviewed_at' => $timeline['closed_at'],
        ])->save();

        $applicant->forceFill(['status' => FormSubmission::STATUS_TECHNICAL_PROPOSAL_DISQUALIFIED])->save();
    }

    private function seedInvitedOnlyApplicant(
        EoiTechnicalProposalRound $round,
        FormSubmission $applicant,
        array $reportRow,
        array $timeline,
        int $position
    ): void {
        $candidate = $this->upsertCandidate(
            $round,
            $applicant,
            $reportRow,
            'Technical proposal invited — no proposal received',
            EoiTechnicalProposalCandidate::STATUS_INVITED,
            $timeline['published_at']->addMinutes($position)
        );

        $candidate->forceFill([
            'status' => EoiTechnicalProposalCandidate::STATUS_INVITED,
            'first_submitted_at' => null,
            'last_submitted_at' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ])->save();

        $applicant->forceFill(['status' => FormSubmission::STATUS_TECHNICAL_PROPOSAL_INVITED])->save();
    }

    private function upsertCandidate(
        EoiTechnicalProposalRound $round,
        FormSubmission $applicant,
        array $reportRow,
        string $workflowDecision,
        string $status,
        CarbonImmutable $invitedAt
    ): EoiTechnicalProposalCandidate {
        return EoiTechnicalProposalCandidate::query()->updateOrCreate(
            [
                'round_id' => $round->getKey(),
                'form_submission_id' => $applicant->getKey(),
            ],
            [
                'user_id' => $applicant->submitted_by,
                'eoi_outcome_code' => (string) data_get($reportRow, 'outcome.code'),
                'eoi_outcome_label' => (string) data_get($reportRow, 'outcome.label'),
                'workflow_decision' => $workflowDecision,
                'status' => $status,
                'invited_at' => $invitedAt,
            ]
        );
    }

    private function upsertRuleFinding(
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalRule $rule,
        EoiTechnicalProposalSubmission $proposal,
        string $finding,
        string $effect,
        string $rationale,
        User $actor,
        CarbonImmutable $appliedAt
    ): void {
        $activeFinding = EoiTechnicalProposalRuleApplication::query()
            ->where('candidate_id', $candidate->getKey())
            ->where('rule_id', $rule->getKey())
            ->whereNull('revoked_at')
            ->first();

        $values = [
            'proposal_submission_id' => $proposal->getKey(),
            'rule_code_snapshot' => $rule->code,
            'rule_title_snapshot' => $rule->title,
            'rule_is_disqualifying_snapshot' => $rule->is_disqualifying,
            'finding' => $finding,
            'effect' => $effect,
            'rationale' => $rationale,
            'applied_by' => $actor->getKey(),
            'applied_at' => $appliedAt,
            'revoked_by' => null,
            'revoked_at' => null,
            'revocation_reason' => null,
        ];

        if ($activeFinding) {
            $activeFinding->forceFill($values)->save();

            return;
        }

        EoiTechnicalProposalRuleApplication::create([
            'candidate_id' => $candidate->getKey(),
            'rule_id' => $rule->getKey(),
            ...$values,
        ]);
    }

    private function storeAcceptedProposalDocuments(
        string $applicantName,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposal,
        User $actor
    ): void {
        $sources = $this->proposalSourcesFor($applicantName);

        foreach ($sources as $source) {
            $this->copySourceDocument(
                $source,
                $round,
                $candidate,
                $proposal,
                $actor,
                $this->documentLabelFor($applicantName, $source)
            );
        }

        if ($applicantName === 'BwB') {
            $this->storeReceiptRecord(
                'BwB physical proposal receipt record',
                'BwB delivered its technical proposal physically to the AU office and the signed Power of Attorney was digitised. '
                    .'The main technical-proposal scan was not supplied with the seed source; retrieve it from the physical AU file before remote substantive review.',
                $round,
                $candidate,
                $proposal,
                $actor,
                'Physical proposal receipt record — primary scan pending'
            );
            $this->receiptOnlyDigitalArchiveApplicants[] = $applicantName;
        }

        $this->pruneLegacySeedDocuments($applicantName, $round, $candidate, $proposal);
    }

    /** @return array<int, string> */
    private function proposalSourcesFor(string $applicantName): array
    {
        $filenames = self::PROPOSAL_DOCUMENT_FILENAMES[$applicantName] ?? [];
        $sourceDirectories = [database_path(self::BUNDLED_ASSET_DIRECTORY)];
        $overrideDirectory = trim((string) env('EOI_ENDOWMENT_TECHNICAL_PROPOSAL_SOURCE_DIR', ''));

        if ($overrideDirectory !== '') {
            $sourceDirectories[] = rtrim($overrideDirectory, '/\\');
        }

        return collect($filenames)
            ->map(function (string $filename) use ($applicantName, $sourceDirectories): string {
                $candidates = collect($sourceDirectories)
                    ->map(fn (string $directory): string => $directory.DIRECTORY_SEPARATOR.$filename);
                $source = $candidates->first(fn (string $path): bool => $this->isUsablePdfSource($path));

                if ($source) {
                    $this->assertProposalSourceIntegrity($filename, $source, $applicantName);

                    return $source;
                }

                $hasUnfetchedLfsPointer = $candidates
                    ->filter(fn (string $path): bool => is_file($path))
                    ->contains(fn (string $path): bool => $this->isGitLfsPointer($path));

                if ($hasUnfetchedLfsPointer) {
                    throw new RuntimeException(
                        'The bundled '.$filename.' for '.$applicantName.' is a Git LFS pointer, not the actual PDF. '
                        .'Run `git lfs pull` before running this seeder.'
                    );
                }

                throw new RuntimeException(
                    'The required bundled proposal document '.$filename.' for '.$applicantName.' was not found. '
                    .'Restore database/seeders/assets/endowment-fund-technical-proposals and run the seeder again.'
                );
            })
            ->all();
    }

    private function copySourceDocument(
        string $source,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposal,
        User $actor,
        string $documentLabel = 'Physical proposal scan'
    ): void {
        $filename = basename($source);
        $path = $this->documentPath($round, $candidate, $proposal, $filename);
        $alreadyStored = Storage::disk('local')->exists($path);
        $stream = fopen($source, 'rb');

        if ($stream === false) {
            throw new RuntimeException('Unable to read proposal source file: '.$source);
        }

        try {
            if (! Storage::disk('local')->put($path, $stream)) {
                throw new RuntimeException('Unable to store the proposal file for '.($candidate->applicant?->display_name ?: 'the applicant').'.');
            }
        } finally {
            fclose($stream);
        }

        if (! $alreadyStored) {
            $this->newStoragePaths[] = $path;
        }

        EoiTechnicalProposalDocument::query()->updateOrCreate(
            [
                'proposal_submission_id' => $proposal->getKey(),
                'original_filename' => $filename,
            ],
            [
                'document_label' => $documentLabel,
                'file_path' => $path,
                'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION)),
                'mime_type' => mime_content_type($source) ?: 'application/pdf',
                'file_size' => (int) filesize($source),
                'sha256' => hash_file('sha256', $source),
                'uploaded_by' => $actor->getKey(),
            ]
        );
    }

    private function storeReceiptRecord(
        string $title,
        string $contents,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposal,
        User $actor,
        string $documentLabel = 'Physical receipt record — digitised proposal pending'
    ): void {
        $filename = $this->receiptRecordFilename($title, $candidate);
        $path = $this->documentPath($round, $candidate, $proposal, $filename);
        $alreadyStored = Storage::disk('local')->exists($path);

        if (! Storage::disk('local')->put($path, $contents)) {
            throw new RuntimeException('Unable to store the physical-delivery receipt record for '.($candidate->applicant?->display_name ?: 'the applicant').'.');
        }

        if (! $alreadyStored) {
            $this->newStoragePaths[] = $path;
        }

        EoiTechnicalProposalDocument::query()->updateOrCreate(
            [
                'proposal_submission_id' => $proposal->getKey(),
                'original_filename' => $filename,
            ],
            [
                'document_label' => $documentLabel,
                'file_path' => $path,
                'extension' => 'txt',
                'mime_type' => 'text/plain',
                'file_size' => strlen($contents),
                'sha256' => hash('sha256', $contents),
                'uploaded_by' => $actor->getKey(),
            ]
        );
    }

    private function documentLabelFor(string $applicantName, string $source): string
    {
        if ($applicantName === 'BwB' && basename($source) === 'Power of Attorney_compressed.pdf') {
            return 'Supporting document — Power of Attorney';
        }

        return 'Physical proposal scan';
    }

    private function isUsablePdfSource(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return fread($handle, 5) === '%PDF-';
        } finally {
            fclose($handle);
        }
    }

    private function isGitLfsPointer(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            return str_starts_with((string) fread($handle, 64), 'version https://git-lfs.github.com/spec/');
        } finally {
            fclose($handle);
        }
    }

    private function assertProposalSourceIntegrity(
        string $filename,
        string $source,
        string $applicantName
    ): void {
        $expectedHash = self::PROPOSAL_DOCUMENT_SHA256[$filename] ?? null;
        $actualHash = hash_file('sha256', $source);

        if (! $expectedHash || ! is_string($actualHash) || ! hash_equals($expectedHash, strtolower($actualHash))) {
            throw new RuntimeException(
                'The bundled proposal document '.$filename.' for '.$applicantName.' did not match its approved historical checksum. '
                .'Restore the committed Git LFS object before running this seeder.'
            );
        }
    }

    private function pruneLegacySeedDocuments(
        string $applicantName,
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposal
    ): void {
        $currentFilenames = collect(self::PROPOSAL_DOCUMENT_FILENAMES[$applicantName] ?? []);
        $knownFilenames = $currentFilenames->merge([
            $this->receiptRecordFilename($applicantName.' physical proposal receipt record', $candidate),
        ]);

        if ($applicantName === 'BwB') {
            $knownFilenames = $knownFilenames->merge(self::LEGACY_SEED_DOCUMENT_FILENAMES);
            $currentFilenames->push(
                $this->receiptRecordFilename('BwB physical proposal receipt record', $candidate)
            );
        }

        $storagePrefix = $this->documentDirectory($round, $candidate, $proposal).'/';

        EoiTechnicalProposalDocument::query()
            ->where('proposal_submission_id', $proposal->getKey())
            ->whereIn('original_filename', $knownFilenames->unique()->all())
            ->get()
            ->reject(fn (EoiTechnicalProposalDocument $document): bool => $currentFilenames
                ->contains($document->original_filename))
            ->each(function (EoiTechnicalProposalDocument $document) use ($storagePrefix): void {
                if (Str::startsWith((string) $document->file_path, $storagePrefix)) {
                    Storage::disk('local')->delete($document->file_path);
                }

                $document->delete();
            });
    }

    private function receiptRecordFilename(
        string $title,
        EoiTechnicalProposalCandidate $candidate
    ): string {
        return Str::slug($title).'-'.substr(hash('sha256', $candidate->getKey()), 0, 10).'.txt';
    }

    private function documentDirectory(
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposal
    ): string {
        return 'eoi-technical-proposals/'.$round->getKey()
            .'/candidates/'.$candidate->getKey()
            .'/revisions/'.$proposal->revision_number;
    }

    private function documentPath(
        EoiTechnicalProposalRound $round,
        EoiTechnicalProposalCandidate $candidate,
        EoiTechnicalProposalSubmission $proposal,
        string $filename
    ): string {
        return $this->documentDirectory($round, $candidate, $proposal)
            .'/'.Str::slug(pathinfo($filename, PATHINFO_FILENAME)).'.'.strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }

    private function upsertCommunicationRecipient(
        EoiReportCommunication $communication,
        FormSubmission $applicant,
        EoiTechnicalProposalCandidate $candidate,
        CarbonImmutable $sentAt
    ): void {
        $isAccepted = $candidate->status === EoiTechnicalProposalCandidate::STATUS_QUALIFIED;
        $isAvahi = $applicant->display_name === self::ELECTRONICALLY_DISQUALIFIED_APPLICANT;

        EoiReportCommunicationRecipient::query()->updateOrCreate(
            [
                'communication_id' => $communication->getKey(),
                'form_submission_id' => $applicant->getKey(),
            ],
            [
                'user_id' => $applicant->submitted_by,
                'recipient_name' => $applicant->display_name,
                'recipient_email' => $applicant->submitter?->email ?: 'no-reply@invalid.local',
                'outcome_code' => $candidate->eoi_outcome_code,
                'outcome_label' => $candidate->eoi_outcome_label,
                'workflow_decision' => $candidate->workflow_decision,
                'delivery_status' => EoiReportCommunicationRecipient::STATUS_SENT,
                'delivery_error' => null,
                'emailed_at' => $sentAt,
                'proposal_submitted_at' => $isAccepted || $isAvahi ? $candidate->last_submitted_at : null,
                'proposal_message' => $isAccepted
                    ? 'Physical technical proposal received at the AU office.'
                    : ($isAvahi
                        ? 'Electronic submission retained for audit but not accepted; physical delivery to the AU office was mandatory.'
                        : null),
            ]
        );
    }

    private function normaliseName(string $name): string
    {
        return Str::lower((string) preg_replace('/\s+/u', ' ', trim($name)));
    }
}
