<?php

namespace App\Exports\Sheets;

use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class MeIndicatorReportSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use MeIndicatorReportSheetFormatting;

    private const METRIC_HEADING_ROW = 6;

    private const FILTER_HEADING_ROW = 18;

    public function __construct(
        private readonly string $mode,
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
        $rows = [
            ['ATTP M&E Indicator Report'],
            [Str::headline($this->mode).' mode · '.$this->scopeLabel],
            ['Generated At', now()->format('Y-m-d H:i:s T')],
            ['Official Data Guardrail', 'Only finally approved indicator results are included. Draft, submitted, returned, rejected and otherwise unapproved records are excluded.'],
            [],
            ['Key Metric', 'Value', 'Interpretation'],
            ['Indicators', (int) ($this->summary['indicator_count'] ?? 0), 'Active framework indicators represented by this authorized report scope.'],
            ['Reported Indicators', (int) ($this->summary['reported_indicator_count'] ?? 0), 'Indicators with at least one deduplicated approved contribution.'],
            ['Approved Contributions', (int) ($this->summary['approved_contribution_count'] ?? 0), 'Approved source-result records used in the report calculations.'],
            ['Average Target Achievement', $this->percentage($this->summary['average_achievement'] ?? null) ?: 'Not available', 'Average of rateable indicator target-attainment percentages.'],
            ['Reporting Completeness', $this->percentage($this->summary['reporting_completeness'] ?? 0), 'Reported organization slots divided by expected reporting slots.'],
            ['Evidence Links', (int) ($this->summary['evidence_count'] ?? 0), 'Evidence-link instances attached to approved contributions.'],
            ['Verified Evidence Links', (int) ($this->summary['verified_evidence_count'] ?? 0), 'Evidence links with a verified, validated or approved status.'],
            ['Evidence Verification Rate', $this->percentage($this->summary['evidence_verification_rate'] ?? null) ?: 'Not available', 'Verified evidence links divided by all evidence links.'],
            ['Participant / Beneficiary Instances', (int) ($this->summary['beneficiary_count'] ?? 0), 'Approved participation instances; this is not necessarily a unique-person count.'],
            ['Latest Approval', $this->dateTime($this->summary['latest_approval_at'] ?? null) ?: 'Not available', 'Most recent result approval represented in this report.'],
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

        $rows[] = ['Resolved Scope', $this->scopeLabel, 'Human-readable authorized scope used for every sheet.'];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $heading = [
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
            self::METRIC_HEADING_ROW => $heading,
            self::FILTER_HEADING_ROW => $heading,
        ];
    }

    public function columnWidths(): array
    {
        return ['A' => 36, 'B' => 68, 'C' => 66];
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
                $sheet->getStyle('A1:C2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A3:A4')->getFont()->setBold(true);
                $sheet->getStyle('A7:A16')->getFont()->setBold(true);
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
            'mode' => 'Individual indicator dossier or consolidated indicator register.',
            'project_year' => 'Project year used to resolve the approved target benchmark.',
            'reporting_year' => 'Calendar reporting year for approved actual results.',
            'reporting_period_id' => 'Specific reporting period; when selected it takes precedence over reporting year.',
            'portfolio_id' => 'Authorized portfolio selection.',
            'component_id' => 'Selected project or results component.',
            'indicator_id' => 'Selected framework indicator.',
            'think_tank_id' => 'Selected contributor organization scope.',
            'performance_report_id' => 'Optional exact approved source report in individual mode.',
            'report_id' => 'Optional legacy alias for the exact source report.',
            'results_level' => 'PDO or intermediate-results classification.',
            'performance_status' => 'Post-calculation performance classification.',
            'country' => 'Contributor or achievement country filter.',
            'thematic_area' => 'Approved achievement thematic-area filter.',
            'geographic_scope' => 'Advanced consolidated achievement geography filter.',
            'rec' => 'Regional Economic Community filter.',
            'gender' => 'Participant or beneficiary gender filter.',
            'age_group' => 'Participant or beneficiary age-group filter.',
            'stakeholder_category' => 'Participant or beneficiary stakeholder-category filter.',
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
}
