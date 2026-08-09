<?php

use App\Models\MeMatrixVersion;

it('provides an international-standard matrix control workspace', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/me/matrices/index.blade.php');
    $styles = file_get_contents($root.'/resources/views/me/matrices/partials/styles.blade.php');

    expect($view)
        ->toContain('M&amp;E Matrix control centre')
        ->toContain('Search and register scope')
        ->toContain('matrix-status-chart')
        ->toContain('matrix-activity-chart')
        ->toContain('matrix-portfolio-chart')
        ->toContain('Matrix version register')
        ->toContain('data-matrix-row')
        ->toContain('data-file-input')
        ->toContain("document.addEventListener('DOMContentLoaded', ready")
        ->and($styles)
        ->toContain('.mel-matrices')
        ->toContain('.mx-table-wrap')
        ->toContain('overflow: auto')
        ->toContain('@media (max-width:');
});

it('exports a controlled PDF register with an inspection appendix', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/MeMatrixController.php');
    $pdf = file_get_contents($root.'/resources/views/me/matrices/report-pdf.blade.php');
    $routes = file_get_contents($root.'/routes/web.php');

    expect($controller)
        ->toContain('public function pdf(Request $request)')
        ->toContain("Pdf::loadView('me.matrices.report-pdf'")
        ->toContain("setPaper('a4', 'landscape')")
        ->and($pdf)
        ->toContain('Executive control summary')
        ->toContain('Graphical register profile')
        ->toContain('Detailed matrix version register')
        ->toContain('Workbook inspection appendix')
        ->and($routes)
        ->toContain("Route::get('export/pdf', 'pdf')->name('pdf')");
});

it('summarizes workbook inspection data and synchronizes lifecycle audit state', function () {
    $matrix = new MeMatrixVersion([
        'import_summary' => [
            'format' => 'XLSX',
            'sheet_count' => 2,
            'sheets' => [
                ['data_rows' => 12, 'data_columns' => 8, 'formula_cells' => 4, 'validated_cells' => 3],
                ['data_rows' => 7, 'data_columns' => 5, 'formula_cells' => 2, 'validated_cells' => 1],
            ],
        ],
    ]);
    $controller = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/MeMatrixController.php');

    expect($matrix->formatLabel())->toBe('XLSX')
        ->and($matrix->inspectionTotals())->toBe([
            'sheet_count' => 2,
            'data_rows' => 19,
            'data_columns' => 13,
            'formula_cells' => 6,
            'validated_cells' => 4,
        ])
        ->and($controller)
        ->toContain("'retired_at' => now()")
        ->toContain("'retired_at' => null")
        ->toContain('lockForUpdate()');
});
