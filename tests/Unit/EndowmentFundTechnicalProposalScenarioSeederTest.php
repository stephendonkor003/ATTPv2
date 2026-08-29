<?php

it('ships the documented proposal scans with the dedicated Endowment Fund scenario seeder', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/database/seeders/EndowmentFundTechnicalProposalScenarioSeeder.php'
    );

    expect($source)
        ->toContain("private const BUNDLED_ASSET_DIRECTORY = 'seeders/assets/endowment-fund-technical-proposals';")
        ->toContain("'KPMG' => ['Auc (KPMG)_compressed.pdf']")
        ->toContain("'Impact Africa Consulting' => ['Impact Africa August 2026_compressed.pdf']")
        ->toContain("'BwB' => ['Power of Attorney_compressed.pdf']")
        ->toContain("'LNO' => ['LNO.pdf']")
        ->toContain('database_path(self::BUNDLED_ASSET_DIRECTORY)')
        ->toContain('Run `git lfs pull` before running this seeder.')
        ->not->toContain('EOI_ENDOWMENT_LNO_PROPOSAL_PATH')
        ->not->toContain('C:\\\\Users\\\\user\\\\Downloads\\\\technical proposal');
});

it('does not reuse the former LNO scan as BwB’s technical proposal', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/database/seeders/EndowmentFundTechnicalProposalScenarioSeeder.php'
    );

    expect($source)
        ->toContain("private const LEGACY_SEED_DOCUMENT_FILENAMES = ['Technical Proposal_compressed.pdf'];")
        ->toContain('Supporting document')
        ->toContain('primary proposal scan is still pending')
        ->toContain('pruneLegacySeedDocuments');
});

it('preserves LNO as an auditable historical RFP qualification override', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/database/seeders/EndowmentFundTechnicalProposalScenarioSeeder.php'
    );

    expect($source)
        ->toContain('HISTORICAL_QUALIFICATION_OVERRIDES')
        ->toContain("'LNO' => [")
        ->toContain('EoiQualificationService::OUTCOME_FULLY_QUALIFIED')
        ->toContain('Fully Qualified (historical RFP recipient override)')
        ->toContain('historical_rfp_override')
        ->toContain("'workflow_decision' => \$historicalOverride['workflow_decision']")
        ->toContain('not panel-qualified in the current Endowment Fund EOI report');
});
