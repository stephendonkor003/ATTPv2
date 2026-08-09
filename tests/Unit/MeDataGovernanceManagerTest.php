<?php

use App\Models\MeDataGovernanceAction;
use App\Models\MeDataGovernanceControl;

it('provides an operational data-governance control centre', function () {
    $root = dirname(__DIR__, 2);
    $view = file_get_contents($root.'/resources/views/me/data-governance/index.blade.php');
    $styles = file_get_contents($root.'/resources/views/me/data-governance/partials/styles.blade.php');

    expect($view)
        ->toContain('Data Governance Framework')
        ->toContain('Governance scope and action filters')
        ->toContain('dg-lifecycle-chart')
        ->toContain('dg-domain-chart')
        ->toContain('dg-risk-chart')
        ->toContain('Controlled governance register')
        ->toContain('Remediation and review action queue')
        ->toContain('data-action-resolve')
        ->toContain("document.addEventListener('DOMContentLoaded', ready")
        ->and($styles)
        ->toContain('.mel-governance')
        ->toContain('.dg-table-wrap')
        ->toContain('overflow:auto')
        ->toContain('@media(max-width:');
});

it('provides versioned governance, remediation, audit, and export workflows', function () {
    $root = dirname(__DIR__, 2);
    $controller = file_get_contents($root.'/app/Http/Controllers/MeDataGovernanceController.php');
    $pdf = file_get_contents($root.'/resources/views/me/data-governance/report-pdf.blade.php');
    $routes = file_get_contents($root.'/routes/web.php');

    expect($controller)
        ->toContain('public function submitReview')
        ->toContain('public function approve')
        ->toContain('public function newVersion')
        ->toContain('public function retire')
        ->toContain('public function resolveAction')
        ->toContain("'module' => 'me_data_governance'")
        ->toContain('Close or formally accept all actions on the currently approved version')
        ->toContain("Pdf::loadView('me.data-governance.report-pdf'")
        ->and($pdf)
        ->toContain('Executive governance posture')
        ->toContain('Controlled governance register')
        ->toContain('Governance action and remediation queue')
        ->toContain('Authoritative control definition')
        ->and($routes)
        ->toContain("->name('data-governance.pdf')")
        ->toContain("->name('data-governance.csv')");
});

it('calculates governance review and action states consistently', function () {
    $scheduled = new MeDataGovernanceControl;
    $scheduled->setRawAttributes(['status' => 'approved', 'next_review_date' => now()->addMonths(2)->toDateString()]);
    $overdue = new MeDataGovernanceControl;
    $overdue->setRawAttributes(['status' => 'approved', 'next_review_date' => now()->subDay()->toDateString()]);
    $action = new MeDataGovernanceAction;
    $action->setRawAttributes(['status' => 'open', 'due_date' => now()->subDay()->toDateString()]);

    expect($scheduled->reviewState())->toBe('scheduled')
        ->and($overdue->reviewState())->toBe('overdue')
        ->and($action->isOpen())->toBeTrue()
        ->and($action->isOverdue())->toBeTrue();
});
