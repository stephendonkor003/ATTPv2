<?php

use App\Models\ProcurementDocument;

it('formats procurement document sizes for display', function () {
    $document = new ProcurementDocument();

    $document->file_size = 1_572_864;
    expect($document->formatted_size)->toBe('1.5 MB');

    $document->file_size = 10_240;
    expect($document->formatted_size)->toBe('10.0 KB');
});

it('keeps the procurement plan create and edit forms complete', function () {
    $create = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/procurement/plans/create.blade.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/procurement/plans/edit.blade.php'
    );

    expect($create)
        ->toContain("old('procurement_code', \$defaultCode)")
        ->toContain('name="is_code_auto_generated"')
        ->toContain('data-node-id="{{ $programPlan->governance_node_id }}"')
        ->toContain('loadSubActivities.call(activitySelect, initialSubActivityId)')
        ->toContain('name="currency"')
        ->toContain('name="fiscal_year"');

    expect($edit)
        ->toContain('name="program_plan_id"')
        ->toContain('name="is_code_auto_generated"')
        ->toContain("old('program_plan_id', \$plan->program_plan_id)")
        ->toContain('name="currency"')
        ->toContain('name="fiscal_year"');
});

it('provides repeatable named document fields on procurement creation', function () {
    $template = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/procurement/create.blade.php'
    );

    expect($template)
        ->toContain('enctype="multipart/form-data"')
        ->toContain('id="addDocumentBtn"')
        ->toContain('id="documentRows"')
        ->toContain('id="procurementDocumentTemplate"')
        ->toContain('documents[__INDEX__][name]')
        ->toContain('documents[__INDEX__][file]')
        ->toContain("documentRows.insertAdjacentHTML('beforeend', markup)")
        ->toContain("removeButton.closest('[data-document-row]')?.remove()");
});

it('lists controlled procurement document downloads on bidder pages', function () {
    $public = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/public/procurements/show.blade.php'
    );
    $vendor = file_get_contents(
        dirname(__DIR__, 2) . '/resources/views/vendor/procurements/show.blade.php'
    );

    expect($public)
        ->toContain("route('public.procurement.documents.download'")
        ->toContain('{{ $document->document_name }}');

    expect($vendor)
        ->toContain("route('vendor.procurements.documents.download'")
        ->toContain('{{ $document->document_name }}');
});
