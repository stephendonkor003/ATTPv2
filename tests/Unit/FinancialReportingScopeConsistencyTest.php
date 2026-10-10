<?php

it('keeps an explicit programme reconciliation row when no components survive', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/MasterDashboard.php');

    expect($source)
        ->toContain('$components->isEmpty()')
        ->not->toContain("if (empty(\$componentIds)) {\n            return collect();");
});

it('requires component commitments and payments to match their mapped programme funding', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/MasterDashboard.php');

    expect($source)
        ->toContain("leftJoin('myb_program_fundings as c_funding'")
        ->toContain("whereColumn('c_funding.program_id', 'mapped_project.program_id')")
        ->toContain("whereColumn('pr_funding.program_id', 'mapped_project.program_id')")
        ->toContain("orWhereColumn('bc_funding.program_id', 'mapped_project.program_id')");
});

it('uses submitted and approved commitments consistently in financial reports', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BudgetReportController.php');

    expect($source)
        ->toContain('private function financialReportingCommitmentQuery(array $fundingIds)')
        ->toContain('BudgetCommitment::STATUS_SUBMITTED')
        ->toContain('BudgetCommitment::STATUS_APPROVED')
        ->toContain('$this->buildIfrActualCommitmentBySubActivity(')
        ->toContain('planned pipeline commitments');
});

it('reconciles reliably scoped orphan facts for custom funding and date views', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BudgetReportController.php');

    expect($source)
        ->toContain('$filteredFactsAligned = ! $dashboardAligned')
        ->toContain("! empty(\$filters['funding_id']) || (\$filters['mode'] ?? 'life_to_date') !== 'life_to_date'")
        ->toContain('private function projectPositionFilteredReconciliation(')
        ->toContain("'label' => 'Unassigned Financial Activity'");
});

it('scopes payments by their own paid date independently of the purchase order period', function () {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Http/Controllers/BudgetReportController.php');

    expect($source)
        ->toContain('A payment belongs to the selected reporting period by')
        ->toContain("\$query->{\$method}('sub_activity_id', \$subActivityIds);")
        ->not->toContain("->whereIn('sub_activity_id', \$subActivityIds)\n                                ->whereNull('purchase_order_id')");
});
