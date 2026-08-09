<?php

namespace App\Console\Commands;

use App\Services\ThinkTankProcurementWorkbookImporter;
use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;
use Throwable;

class ImportThinkTankProcurementWorkbooks extends Command
{
    protected $signature = 'procurement:import-think-tank-plans
        {path=C:\\Users\\user\\Downloads\\attp procurement : Workbook or directory to import}
        {--member= : Force every workbook to a Consortium Think Tank UUID}
        {--force : Re-import a workbook checksum already present}
        {--dry-run : Inventory workbook files without writing to the database}';

    protected $description = 'Losslessly archive and migrate Think Tank procurement workbooks, including every worksheet and source row.';

    public function handle(ThinkTankProcurementWorkbookImporter $importer): int
    {
        $path = (string) $this->argument('path');
        $files = $this->spreadsheetFiles($path);
        if ($files === []) {
            $this->warn("No .xlsx, .xls, .xlsm, .ods or .csv files were found in: {$path}");
            return self::SUCCESS;
        }

        $this->info('Found '.count($files).' procurement workbook(s).');
        if ($this->option('dry-run')) {
            $this->table(['File', 'Bytes', 'SHA-256'], array_map(fn ($file) => [
                $file,
                number_format(filesize($file) ?: 0),
                hash_file('sha256', $file),
            ], $files));
            return self::SUCCESS;
        }

        $rows = [];
        $failed = false;
        foreach ($files as $file) {
            try {
                $result = $importer->import($file, $this->option('member') ?: null, (bool) $this->option('force'));
                $batch = $result['batch'];
                $rows[] = [
                    basename($file),
                    $result['status'],
                    $batch->sheet_count,
                    $batch->source_row_count,
                    $batch->mapped_item_count,
                    $batch->warning_count,
                    $batch->source_checksum,
                ];
            } catch (Throwable $exception) {
                $failed = true;
                $rows[] = [basename($file), 'failed: '.$exception->getMessage(), 0, 0, 0, 1, '—'];
            }
        }

        $this->table(['Workbook', 'Status', 'Sheets', 'Preserved rows', 'Mapped items', 'Warnings', 'Checksum'], $rows);
        $this->line('Every processed workbook is archived byte-for-byte; raw cell values, formatted values and formulas are retained in the import staging tables.');

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function spreadsheetFiles(string $path): array
    {
        $resolved = realpath($path);
        if (! $resolved) {
            return [];
        }
        if (is_file($resolved)) {
            return preg_match('/\.(xlsx?|xlsm|ods|csv)$/i', $resolved) ? [$resolved] : [];
        }

        $finder = (new Finder())->files()->in($resolved)->name('/\.(xlsx?|xlsm|ods|csv)$/i')->sortByName();
        $files = [];
        foreach ($finder as $file) {
            if (! str_starts_with($file->getFilename(), '~$')) {
                $files[] = $file->getRealPath();
            }
        }

        return $files;
    }
}
