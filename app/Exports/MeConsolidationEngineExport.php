<?php

namespace App\Exports;

use App\Exports\Sheets\MeConsolidationSummarySheet;
use App\Exports\Sheets\MeIndicatorConsolidationSheet;
use App\Exports\Sheets\MeProjectConsolidationSheet;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MeConsolidationEngineExport implements WithMultipleSheets
{
    public function __construct(
        private readonly array $summary,
        private readonly Collection $projectRows,
        private readonly Collection $indicatorRows,
        private readonly array $filters,
        private readonly string $scopeLabel
    ) {}

    public function sheets(): array
    {
        return [
            new MeConsolidationSummarySheet(
                $this->summary,
                $this->filters,
                $this->scopeLabel
            ),
            new MeProjectConsolidationSheet($this->projectRows),
            new MeIndicatorConsolidationSheet($this->indicatorRows),
        ];
    }
}
