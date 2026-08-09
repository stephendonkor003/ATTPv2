<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class AttpMelResultsExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public function __construct(private readonly Collection $rows) {}

    public function headings(): array
    {
        return ['Level', 'Component', 'Indicator Code', 'Indicator', 'Baseline', 'Target', 'Period Actual', 'Cumulative Actual', 'Period Trend', 'Achievement %', 'Variance', 'Performance', 'Reporting Completeness %', 'Approved Records', 'Organizations Reporting', 'Evidence', 'Verified Evidence', 'Female Beneficiaries', 'Male Beneficiaries', 'Reporting Source'];
    }

    public function array(): array
    {
        return $this->rows->map(fn (array $row): array => [
            $row['indicator']->resultsLevelLabel(),
            $row['indicator']->projectComponent?->name,
            $row['indicator']->indicator_code,
            $row['indicator']->name,
            $row['baseline'],
            $row['target_text'] ?? $row['target_value'],
            is_bool($row['period_actual']) ? ($row['period_actual'] ? 'Yes' : 'No') : $row['period_actual'],
            is_bool($row['cumulative_actual']) ? ($row['cumulative_actual'] ? 'Yes' : 'No') : $row['cumulative_actual'],
            $row['trend']['label'],
            $row['achievement_percent'],
            $row['variance'],
            $row['classification']['label'],
            $row['reporting_completeness'],
            $row['result_count'],
            $row['reported_organizations'],
            $row['evidence_count'],
            $row['verified_evidence_count'],
            $row['female_beneficiaries'],
            $row['male_beneficiaries'],
            str($row['indicator']->reporting_source)->headline(),
        ])->all();
    }
}
