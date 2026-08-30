<?php

namespace App\Console\Commands;

use App\Models\EoiTechnicalProposalDocument;
use App\Models\EoiTechnicalProposalRound;
use App\Services\EndowmentFundTechnicalProposalDocumentRehydrator;
use App\Support\EndowmentFundTechnicalProposalDocumentManifest;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

final class AuditEndowmentFundTechnicalProposalDocuments extends Command
{
    protected $signature = 'eoi:endowment-proposals:audit
                            {--repair : Restore recognized copies; run as the same OS user as the PHP web service}';

    protected $description = 'Audit the four bundled Endowment Fund technical-proposal documents and optionally repair private copies';

    public function handle(EndowmentFundTechnicalProposalDocumentRehydrator $rehydrator): int
    {
        $repair = (bool) $this->option('repair');

        if ($repair) {
            $this->warn('Repair mode is enabled. Only checksum-pinned manifest files can be written; database records are read-only.');
            $this->warn('Run this command as the same OS account as the PHP/Apache service so restored private paths remain readable.');
        } else {
            $this->info('Audit mode only. No files will be changed. Use --repair explicitly to restore verified private copies.');
        }

        $rounds = EoiTechnicalProposalRound::query()
            ->where('title', EndowmentFundTechnicalProposalDocumentManifest::ROUND_TITLE)
            ->whereHas('procurement', fn ($query) => $query->where(
                'reference_no',
                EndowmentFundTechnicalProposalDocumentManifest::PROCUREMENT_REFERENCE
            ))
            ->get();

        if ($rounds->count() !== 1) {
            $this->error(sprintf(
                'Expected exactly one immutable Endowment Fund scenario round; found %d. No file was changed.',
                $rounds->count()
            ));

            return self::FAILURE;
        }

        $documents = EoiTechnicalProposalDocument::query()
            ->whereIn('original_filename', EndowmentFundTechnicalProposalDocumentManifest::filenames())
            ->whereHas('submission.candidate', fn ($query) => $query->where(
                'round_id',
                $rounds->first()->getKey()
            ))
            ->with([
                'submission.candidate.round.procurement',
                'submission.candidate.applicant.values',
            ])
            ->get();

        $documentsByManifestKey = $documents->groupBy(fn (EoiTechnicalProposalDocument $document): string => $this->manifestKey(
            (string) $document->submission?->candidate?->applicant?->display_name,
            (string) $document->original_filename
        ));
        $rows = collect();
        $unresolved = 0;

        foreach (EndowmentFundTechnicalProposalDocumentManifest::all() as $manifestDocument) {
            $manifestKey = $this->manifestKey(
                $manifestDocument['applicant_name'],
                $manifestDocument['filename']
            );
            /** @var Collection<int, EoiTechnicalProposalDocument> $matches */
            $matches = $documentsByManifestKey->get($manifestKey, collect());

            if ($matches->count() !== 1) {
                $unresolved++;
                $rows->push([
                    $manifestDocument['applicant_name'],
                    $manifestDocument['filename'],
                    $matches->isEmpty() ? 'record missing' : 'duplicate records',
                    'not checked',
                    'not repaired',
                ]);

                continue;
            }

            $result = $repair
                ? $rehydrator->repair($matches->first())
                : $rehydrator->inspect($matches->first());

            if (! $result['ok']) {
                $unresolved++;
            }

            $rows->push([
                $result['applicant_name'] ?: $manifestDocument['applicant_name'],
                $result['filename'],
                $result['storage_status'],
                $result['source_status'],
                $result['repair_status'],
            ]);

            if (! $result['ok']) {
                $this->warn($manifestDocument['applicant_name'].': '.$result['message']);
            }
        }

        $this->newLine();
        $this->table(
            ['Applicant', 'Manifest document', 'Private copy', 'Bundled source', 'Repair'],
            $rows->all()
        );

        if ($unresolved > 0) {
            $this->error("{$unresolved} manifest document(s) remain unavailable or unverifiable.");

            return self::FAILURE;
        }

        $this->info('All four immutable Endowment Fund proposal documents are verified and available.');

        return self::SUCCESS;
    }

    private function manifestKey(string $applicantName, string $filename): string
    {
        return $applicantName."\0".$filename;
    }
}
