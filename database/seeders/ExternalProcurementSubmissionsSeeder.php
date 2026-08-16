<?php

namespace Database\Seeders;

use App\Services\ExternalProcurementSubmissionImporter;
use Illuminate\Database\Seeder;
use RuntimeException;

final class ExternalProcurementSubmissionsSeeder extends Seeder
{
    private const PROCUREMENT_TITLE = 'Selection of a Consulting Firm to conduct a Feasibility study for the Endowment Fund and Designing a Resource Mobilization Strategy for the Africa Think Tank Platform Endowment Fund';

    public function run(): void
    {
        $sourcePath = $this->sourcePath();
        $dryRunSetting = getenv('EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN');
        if ($dryRunSetting === false || trim($dryRunSetting) === '') {
            $dryRun = true;
        } else {
            $dryRun = filter_var($dryRunSetting, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
            if ($dryRun === null) {
                throw new RuntimeException(
                    'EXTERNAL_PROCUREMENT_IMPORT_DRY_RUN must be true or false. It defaults to true.'
                );
            }
        }

        $this->command?->info($dryRun
            ? 'Preflighting authoritative external procurement synchronization (no writes)...'
            : 'Synchronizing external procurement submissions...');

        $summary = app(ExternalProcurementSubmissionImporter::class)->import(
            self::PROCUREMENT_TITLE,
            $sourcePath,
            $dryRun
        );

        $this->command?->table(
            ['Result', 'Value'],
            collect($summary)->map(
                fn ($value, $key): array => [
                    str_replace('_', ' ', ucfirst((string) $key)),
                    is_scalar($value) ? (string) $value : json_encode($value),
                ]
            )->values()->all()
        );

        if ($dryRun) {
            $this->command?->warn('Dry-run complete. No users, submissions, values, audit rows, or files were created, changed, or removed.');
        } else {
            $this->command?->info('External procurement submission synchronization completed successfully.');
            $this->command?->line('Changed importer-owned document sets were replaced, unchanged submissions were reused, source folders were preserved, and no mail was sent.');
            if (($summary['failed_file_deletions'] ?? 0) > 0) {
                $this->command?->warn(
                    'Database reconciliation committed, but one or more obsolete packages could not be removed. Review the failed file deletion count.'
                );
            }
        }
    }

    private function sourcePath(): string
    {
        $configured = trim((string) getenv('EXTERNAL_PROCUREMENT_SUBMISSIONS_PATH'));
        if ($configured !== '') {
            return $configured;
        }

        return resource_path('Submissions');
    }
}
