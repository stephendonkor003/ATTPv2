<?php

it('provides a filterable non-overlapping indicator DataTable with the approved columns', function () {
    $root = dirname(__DIR__, 2);
    $register = file_get_contents($root.'/resources/views/me/indicators/partials/register.blade.php');
    $page = file_get_contents($root.'/resources/views/me/indicators/index.blade.php');
    $form = file_get_contents($root.'/resources/views/me/indicators/partials/form.blade.php');
    $styles = file_get_contents($root.'/resources/views/me/indicators/partials/styles.blade.php');
    $controller = file_get_contents($root.'/app/Http/Controllers/MeIndicatorController.php');

    expect($register)
        ->toContain('<th>Indicator</th>')
        ->toContain('<th>Measurement</th>')
        ->toContain('<th>Reporting &amp; evidence</th>')
        ->toContain('<th>Responsible person</th>')
        ->toContain('>Actions</th>')
        ->toContain('data-indicator-register-table')
        ->toContain('data-indicator-table-search')
        ->toContain('data-indicator-table-filter="componentId"')
        ->toContain('data-indicator-table-filter="unitId"')
        ->toContain('data-indicator-table-filter="frequencyId"')
        ->toContain('data-indicator-table-filter="responsibleId"')
        ->not->toContain('$indicators->hasPages()')
        ->and($page)
        ->toContain("dataTables.min.js")
        ->toContain("$(table).DataTable({")
        ->toContain("targets: 4, orderable: false")
        ->toContain("document.addEventListener('submit'")
        ->toContain("event.target.closest('[data-disaggregation-open]')")
        ->and($form)
        ->not->toContain('Aggregation across reporting periods')
        ->not->toContain('Cross-think-tank consolidation')
        ->not->toContain('name="aggregation_method"')
        ->not->toContain('name="organization_rollup_method"')
        ->toContain('Surplus target')
        ->toContain('name="extra_target"')
        ->toContain('(optional)')
        ->and($styles)
        ->toContain('table-layout: fixed !important')
        ->toContain('overflow-wrap: anywhere')
        ->toContain('flex-wrap: wrap')
        ->toContain('.me-register-toolbar')
        ->and($controller)
        ->toContain('normalizeIndicatorInput($request, $indicator)')
        ->toContain("\$indicator?->aggregation_method ?: 'sum'")
        ->toContain("\$indicator?->organization_rollup_method ?: 'sum'")
        ->toContain("'extra_target' => ['nullable', 'numeric', 'gte:target_value']")
        ->toContain('->latest()')
        ->toContain('->get();');
});
