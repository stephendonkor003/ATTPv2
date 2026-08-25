<?php

namespace App\Exports\Sheets;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeConsolidationSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    private const METRIC_HEADING_ROW = 6;

    private const FILTER_HEADING_ROW = 22;

    public function __construct(
        private readonly array $summary,
        private readonly array $filters,
        private readonly string $scopeLabel
    ) {}

    public function title(): string
    {
        return 'Summary & Scope';
    }

    public function array(): array
    {
        $organizations = collect($this->summary['reporting_organizations'] ?? [])
            ->filter()
            ->map(fn (mixed $organization): string => (string) $organization)
            ->join(', ');
        $rows = [
            ['ATTP Consolidations Engine'],
            ['Approved-results consolidation workbook · '.$this->scopeLabel],
            ['Generated At', now()->format('Y-m-d H:i:s T')],
            ['Official Data Guardrail', 'Only finally approved indicator results are included. Draft, submitted, returned, rejected and unapproved records are excluded.'],
            [],
            ['Key Metric', 'Value', 'Interpretation'],
            ['Results Areas', (int) ($this->summary['results_area_count'] ?? 0), 'PDO/cross-project area plus project or component scorecards in scope.'],
            ['Framework Projects', (int) ($this->summary['project_count'] ?? 0), 'Project/component records represented by the selected indicator scope.'],
            ['Indicators', (int) ($this->summary['indicator_count'] ?? 0), 'Active framework indicators after all authorized filters.'],
            ['Reported Indicators', (int) ($this->summary['reported_indicator_count'] ?? 0), 'Indicators with at least one approved contribution.'],
            ['Approved Contributions', (int) ($this->summary['approved_contribution_count'] ?? 0), 'Deduplicated approved source-result records used in calculations.'],
            ['Reporting Organizations', (int) ($this->summary['organization_count'] ?? 0), 'Distinct contributor organizations represented in approved results.'],
            ['Average Target Achievement', $this->percentage($this->summary['average_achievement'] ?? null), 'Unweighted average across rated indicators, with each indicator capped at 100%.'],
            ['Reporting Completeness', $this->percentage($this->summary['reporting_completeness'] ?? 0), 'Reported organization slots divided by expected organization slots.'],
            ['Evidence Links', (int) ($this->summary['evidence_count'] ?? 0), 'Indicator-evidence link instances attached to approved contributions.'],
            ['Verified Evidence Links', (int) ($this->summary['verified_evidence_count'] ?? 0), 'Linked evidence with a verified or validated status.'],
            ['Evidence Verification Rate', $this->percentage($this->summary['evidence_verification_rate'] ?? null), 'Verified evidence divided by all linked evidence.'],
            ['Participant / Beneficiary Instances', (int) ($this->summary['beneficiary_count'] ?? 0), 'Participation instances in approved breakdowns; this is not a unique-person count.'],
            ['Latest Approval', $this->dateTime($this->summary['latest_approval_at'] ?? null), 'Most recent approval timestamp represented in this workbook.'],
            ['Contributor Organizations', $organizations ?: 'No approved contributor in this scope', 'Organization provenance for the consolidated results.'],
            [],
            ['Report Filter', 'Selected Value', 'Meaning'],
        ];

        foreach ($this->filterDefinitions() as $key => $meaning) {
            $rows[] = [
                Str::of($key)->replace('_', ' ')->headline()->toString(),
                $this->filterValue($this->filters[$key] ?? null),
                $meaning,
            ];
        }

        $rows[] = ['Resolved Scope', $this->scopeLabel, 'Human-readable authorized scope used for every workbook sheet.'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $headingStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '075C7A']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ];

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 17, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '073F30']],
            ],
            2 => [
                'font' => ['italic' => true, 'color' => ['rgb' => '36535C']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EAF5F2']],
            ],
            4 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '7A4B00']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'FFF7E5']],
            ],
            self::METRIC_HEADING_ROW => $headingStyle,
            self::FILTER_HEADING_ROW => $headingStyle,
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 34,
            'B' => 66,
            'C' => 64,
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $highestRow = $sheet->getHighestRow();

                $sheet->mergeCells('A1:C1');
                $sheet->mergeCells('A2:C2');
                $sheet->freezePane('A7');
                $sheet->getStyle("A1:C{$highestRow}")
                    ->getAlignment()
                    ->setVertical(Alignment::VERTICAL_TOP)
                    ->setWrapText(true);
                $sheet->getStyle('A1:C2')
                    ->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3:A4')->getFont()->setBold(true);
                $sheet->getStyle('A7:A20')->getFont()->setBold(true);
                $sheet->getRowDimension(1)->setRowHeight(30);
                $sheet->getRowDimension(self::METRIC_HEADING_ROW)->setRowHeight(25);
                $sheet->getRowDimension(self::FILTER_HEADING_ROW)->setRowHeight(25);
            },
        ];
    }

    /** @return array<string, string> */
    private function filterDefinitions(): array
    {
        return [
            'level' => 'Selected interactive/CSV consolidation level; this complete workbook includes both levels.',
            'project_year' => 'Project year used to resolve the approved target benchmark.',
            'reporting_year' => 'Calendar reporting year for approved actual results.',
            'reporting_period_id' => 'Specific reporting period; when selected it takes precedence over reporting year.',
            'portfolio_id' => 'Authorized portfolio selection.',
            'component_id' => 'Selected project or results component.',
            'indicator_id' => 'Selected framework indicator.',
            'think_tank_id' => 'Selected contributing Think Tank.',
            'results_level' => 'PDO or intermediate-results classification.',
            'performance_status' => 'Post-calculation performance classification.',
            'country' => 'Contributor or achievement country filter.',
            'thematic_area' => 'Approved achievement thematic-area filter.',
        ];
    }

    private function filterValue(mixed $value): string|int|float
    {
        if ($value === null || $value === '') {
            return 'All / not restricted';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value) || $value instanceof \Traversable) {
            return collect($value)->filter()->join(', ');
        }

        return is_int($value) || is_float($value) ? $value : (string) $value;
    }

    private function percentage(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 1).'%' : 'Not available';
    }

    private function dateTime(mixed $value): string
    {
        if (! $value) {
            return 'Not available';
        }
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s T');
        }

        try {
            return Carbon::parse($value)->format('Y-m-d H:i:s T');
        } catch (\Throwable) {
            return (string) $value;
        }
    }
}
