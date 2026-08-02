<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ConsolidatedMeReportExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly Collection $rows, private readonly array $filters)
    {
    }

    public function headings(): array
    {
        return [
            'Reporting Year', 'Reporting Frequency', 'Reporting Period', 'Indicator Code', 'Indicator',
            'Organization Roll-up', 'Consolidated Result', 'Common Period Target', 'Organizations Reporting',
            'Duplicate Source Results Suppressed', 'Achievement Records', 'Beneficiaries', 'Geographic Scopes',
            'Countries', 'Regional Economic Communities', 'Priority Themes', 'Implementing Institution Types',
            'Implementing Institutions', 'Stakeholder Categories', 'Female', 'Male', 'Youth Below 35', 'Adults 35+',
        ];
    }

    public function array(): array
    {
        return $this->rows->map(function (array $row): array {
            return [
                $this->filters['year'],
                $this->filters['period_type'],
                $this->filters['period_label'],
                $row['indicator']?->indicator_code,
                $row['indicator']?->name,
                $row['rollup_label'],
                $row['value'],
                $row['target'],
                $row['organization_count'],
                $row['duplicate_result_count'],
                $row['achievement_count'],
                $row['beneficiary_count'],
                $row['geographic_scopes']->keys()->join(', '),
                $row['countries']->keys()->join(', '),
                $row['recs']->keys()->join(', '),
                $row['themes']->keys()->join(', '),
                $row['institution_types']->keys()->join(', '),
                $row['institutions']->keys()->join(', '),
                $row['stakeholders']->map(fn ($count, $category) => $category.': '.$count)->values()->join('; '),
                $row['gender']->get('female', 0),
                $row['gender']->get('male', 0),
                $row['age_groups']->get('youth_below_35', 0),
                $row['age_groups']->get('adult_35_plus', 0),
            ];
        })->all();
    }
}
