<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

require dirname(__DIR__).'/vendor/autoload.php';

$root = $argv[1] ?? dirname(__DIR__).'/public/procurement_data';
$root = realpath($root) ?: $root;
$sheetFilter = $argv[2] ?? null;

$files = is_file($root) ? [$root] : [];
if (is_dir($root)) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if (! $file->isFile()
            || strtolower($file->getExtension()) !== 'xlsx'
            || str_starts_with($file->getBasename(), '~$')) {
            continue;
        }

        $files[] = $file->getPathname();
    }
}

sort($files, SORT_NATURAL | SORT_FLAG_CASE);

foreach ($files as $path) {
    echo "WORKBOOK\t{$path}\t".filesize($path)."\t".hash_file('sha256', $path).PHP_EOL;

    $reader = IOFactory::createReaderForFile($path);
    $reader->setReadDataOnly(false);
    $vendorRoot = strtolower(str_replace('\\', '/', dirname(__DIR__).'/vendor/phpoffice/phpspreadsheet/'));
    $previousHandler = null;
    $previousHandler = set_error_handler(
        static function (int $severity, string $message, string $file, int $line) use (&$previousHandler, $vendorRoot): bool {
            $fromPhpSpreadsheet = str_starts_with(
                strtolower(str_replace('\\', '/', $file)),
                $vendorRoot,
            );
            if ($fromPhpSpreadsheet && in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                return true;
            }

            return is_callable($previousHandler)
                ? (bool) $previousHandler($severity, $message, $file, $line)
                : false;
        },
    );

    try {
        $spreadsheet = $reader->load($path);
    } finally {
        restore_error_handler();
    }

    foreach ($spreadsheet->getWorksheetIterator() as $worksheet) {
        if ($sheetFilter !== null && $worksheet->getTitle() !== $sheetFilter) {
            continue;
        }

        $highestRow = max(1, $worksheet->getHighestDataRow());
        $highestColumn = $worksheet->getHighestDataColumn();
        echo "SHEET\t{$worksheet->getTitle()}\t{$highestRow}\t{$highestColumn}\t".
            count($worksheet->getMergeCells()).PHP_EOL;

        $rows = [];
        foreach ($worksheet->getCellCollection()->getCoordinates() as $coordinate) {
            [$column, $row] = Coordinate::indexesFromString($coordinate);
            $cell = $worksheet->getCell($coordinate);
            $raw = $cell->getValue();
            if (is_string($raw) && str_starts_with(ltrim($raw), '=')) {
                $cached = $cell->getOldCalculatedValue();
                if ($cached === null) {
                    throw new RuntimeException(
                        "Formula cell [{$worksheet->getTitle()}!{$coordinate}] has no cached Excel value."
                    );
                }
                $value = NumberFormat::toFormattedString(
                    $cached,
                    $cell->getStyle()->getNumberFormat()->getFormatCode(),
                );
            } else {
                $value = $cell->getFormattedValue();
            }
            $value = preg_replace('/\s+/u', ' ', trim((string) $value));
            if ($value !== '') {
                $rows[$row][Coordinate::stringFromColumnIndex($column)] = $value;
            }
        }

        ksort($rows, SORT_NUMERIC);
        foreach ($rows as $row => $values) {
            uksort($values, static fn (string $left, string $right): int => Coordinate::columnIndexFromString($left) <=> Coordinate::columnIndexFromString($right)
            );
            echo 'ROW'."\t{$row}\t".json_encode(
                $values,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            ).PHP_EOL;
        }
    }

    $spreadsheet->disconnectWorksheets();
}
