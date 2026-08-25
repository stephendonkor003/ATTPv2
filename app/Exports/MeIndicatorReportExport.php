<?php

namespace App\Exports;

use App\Exports\Sheets\MeIndicatorConsolidationSheet;
use App\Exports\Sheets\MeIndicatorReportContributionSheet;
use App\Exports\Sheets\MeIndicatorReportEvidenceSheet;
use App\Exports\Sheets\MeIndicatorReportProfileSheet;
use App\Exports\Sheets\MeIndicatorReportSummarySheet;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MeIndicatorReportExport implements WithMultipleSheets
{
    private readonly string $mode;

    private readonly Collection $indicatorRows;

    private readonly Collection $contributionRows;

    private readonly Collection $evidenceRows;

    public function __construct(
        string $mode,
        private readonly array $reportSummary,
        Collection|array $indicatorRows,
        Collection|array $contributionRows,
        Collection|array $evidenceRows,
        private readonly array $filters,
        private readonly string $scopeLabel
    ) {
        if (! in_array($mode, ['individual', 'consolidated'], true)) {
            throw new InvalidArgumentException('Indicator report mode must be individual or consolidated.');
        }

        $this->mode = $mode;
        $this->indicatorRows = collect($indicatorRows)->values();
        $this->contributionRows = collect($contributionRows)->values();
        $this->evidenceRows = collect($evidenceRows)->values();
    }

    public function sheets(): array
    {
        return [
            new MeIndicatorReportSummarySheet(
                $this->mode,
                $this->reportSummary,
                $this->filters,
                $this->scopeLabel
            ),
            $this->mode === 'individual'
                ? new MeIndicatorReportProfileSheet($this->indicatorRows)
                : new MeIndicatorConsolidationSheet($this->indicatorRows),
            new MeIndicatorReportContributionSheet($this->contributionRows),
            new MeIndicatorReportEvidenceSheet($this->evidenceRows),
        ];
    }
}
