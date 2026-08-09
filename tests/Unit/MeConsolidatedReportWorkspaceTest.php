<?php

use App\Exports\ConsolidatedMeReportExport;

it('provides a professional approved-only consolidated reporting workspace', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/MeConsolidatedReportController.php');
    $service = file_get_contents($root.'/app/Services/MeConsolidatedReportingService.php');
    $view = file_get_contents($root.'/resources/views/me/consolidated-reports/index.blade.php');
    $pdf = file_get_contents($root.'/resources/views/me/consolidated-reports/pdf.blade.php');

    expect($controller)
        ->toContain('scopedReportQuery')
        ->toContain('STATUS_APPROVED')
        ->toContain('STATUS_ARCHIVED')
        ->toContain('coverageRate')
        ->toContain('stageDistribution')
        ->toContain("'documents:id,report_id'")
        ->and($service)
        ->toContain('hasDisaggregationFilters')
        ->toContain('qualitative_values')
        ->toContain('organizationName')
        ->and($view)
        ->toContain('Reporting context and consolidation scope')
        ->toContain('Consolidation quality controls')
        ->toContain('Organization submission register')
        ->toContain('Approved consolidated indicator performance')
        ->toContain('consolidated-stage-chart')
        ->toContain('consolidated-gender-chart')
        ->toContain("asset('admin/assets/vendors/js/apexcharts.min.js')")
        ->and($pdf)
        ->toContain('Official approved consolidation')
        ->toContain('Approved source report register')
        ->toContain('qualitative_values');
});

it('exports qualitative results, organizations and complete beneficiary dimensions to Excel', function () {
    $export = new ConsolidatedMeReportExport(collect(), [
        'year' => 2026,
        'period_type' => 'quarter',
        'period_label' => 'Q1',
    ]);

    expect($export->headings())
        ->toContain('Qualitative Results')
        ->toContain('Reporting Organizations')
        ->toContain('Gender Not Disaggregated')
        ->toContain('Age Not Disaggregated')
        ->and($export->title())->toBe('Approved Consolidation')
        ->and($export->columnWidths())->toHaveKeys(['A', 'I', 'AC']);
});
