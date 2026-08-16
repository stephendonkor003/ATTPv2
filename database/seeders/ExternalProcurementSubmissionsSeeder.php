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
            ? 'Preflighting external procurement submissions (no writes)...'
            : 'Importing external procurement submissions...');

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
            $this->command?->warn('Dry-run complete. No users, submissions, values, audit rows, or files were created.');
        } else {
            $this->command?->info('External procurement submission import completed successfully.');
            $this->command?->line('Source folders were preserved. Placeholder vendor accounts are disabled and no mail was sent.');
        }
    }

    private function sourcePath(): string
    {
        $configured = trim((string) getenv('EXTERNAL_PROCUREMENT_SUBMISSIONS_PATH'));
        if ($configured !== '') {
            return $configured;
        }

        $candidates = [
            storage_path('app/private/submissions'),
            storage_path('app/private/Submissions'),
        ];

        $existing = collect($candidates)
            ->filter(fn (string $candidate): bool => is_dir($candidate))
            ->unique(fn (string $candidate): string => realpath($candidate) ?: $candidate)
            ->values();

        if ($existing->count() !== 1) {
            throw new RuntimeException(
                'Could not resolve one submissions source directory. Set EXTERNAL_PROCUREMENT_SUBMISSIONS_PATH explicitly.'
            );
        }

        return $existing->first();
    }
}
