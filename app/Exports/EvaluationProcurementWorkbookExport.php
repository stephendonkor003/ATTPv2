<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EvaluationProcurementWorkbookExport implements WithMultipleSheets
{
    /**
     * @param  array<string, array<int, array<int, mixed>>>  $reportSheets
     */
    public function __construct(private readonly array $reportSheets) {}

    public function sheets(): array
    {
        $sheets = [];

        foreach ($this->reportSheets as $title => $rows) {
            $sheets[] = new EvaluationReportSheet($title, $rows);
        }

        return $sheets;
    }
}
