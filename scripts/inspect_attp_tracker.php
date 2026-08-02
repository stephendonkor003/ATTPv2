<?php

require dirname(__DIR__).'/vendor/autoload.php';

$path = $argv[1] ?? null;
if (! $path || ! is_file($path)) {
    fwrite(STDERR, "Pass the ATTP tracker workbook path.\n");
    exit(1);
}

$workbook = PhpOffice\PhpSpreadsheet\IOFactory::load($path);
foreach ($workbook->getWorksheetIterator() as $sheet) {
    echo PHP_EOL.'SHEET: '.$sheet->getTitle().' ('.$sheet->getHighestDataRow().' rows, '.$sheet->getHighestDataColumn().' columns)'.PHP_EOL;
    $lastRow = min($sheet->getHighestDataRow(), 40);
    $lastColumn = min(PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()), 35);
    for ($row = 1; $row <= $lastRow; $row++) {
        $values = [];
        for ($column = 1; $column <= $lastColumn; $column++) {
            $value = $sheet->getCell([$column, $row])->getFormattedValue();
            if ($value !== '') {
                $values[] = PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($column).'='.$value;
            }
        }
        if ($values !== []) {
            echo $row.': '.implode(' | ', $values).PHP_EOL;
        }
    }
}
