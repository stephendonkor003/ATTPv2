<?php

use App\Support\EndowmentFundTechnicalProposalDocumentManifest;

it('shares an immutable production manifest with the dedicated Endowment Fund scenario seeder', function () {
    $seederSource = file_get_contents(
        dirname(__DIR__, 2).'/database/seeders/EndowmentFundTechnicalProposalScenarioSeeder.php'
    );
    $manifestSource = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/EndowmentFundTechnicalProposalDocumentManifest.php'
    );

    expect($seederSource)
        ->toContain('EndowmentFundTechnicalProposalDocumentManifest::forApplicant')
        ->toContain('EndowmentFundTechnicalProposalDocumentManifest::find')
        ->toContain('EndowmentFundTechnicalProposalDocumentManifest::BUNDLED_ASSET_DIRECTORY')
        ->toContain('Run `git lfs pull` before running this seeder.')
        ->not->toContain('EOI_ENDOWMENT_LNO_PROPOSAL_PATH')
        ->not->toContain('C:\\\\Users\\\\user\\\\Downloads\\\\technical proposal');

    expect($manifestSource)
        ->toContain("public const BUNDLED_ASSET_DIRECTORY = 'seeders/assets/endowment-fund-technical-proposals';")
        ->toContain("'filename' => 'Auc (KPMG)_compressed.pdf'")
        ->toContain("'filename' => 'Impact Africa August 2026_compressed.pdf'")
        ->toContain("'filename' => 'Power of Attorney_compressed.pdf'")
        ->toContain("'filename' => 'LNO.pdf'");
});

it('pins every bundled proposal asset to its approved bytes', function () {
    $manifest = new ReflectionClass(EndowmentFundTechnicalProposalDocumentManifest::class);

    expect($manifest->isFinal())->toBeTrue()
        ->and($manifest->getConstructor()?->isPrivate())->toBeTrue()
        ->and(EndowmentFundTechnicalProposalDocumentManifest::all())->toHaveCount(4);

    foreach (EndowmentFundTechnicalProposalDocumentManifest::all() as $document) {
        $path = dirname(__DIR__, 2).'/database/'
            .EndowmentFundTechnicalProposalDocumentManifest::BUNDLED_ASSET_DIRECTORY
            .'/'.$document['filename'];
        $handle = fopen($path, 'rb');

        expect($handle)->not->toBeFalse();

        try {
            $header = fread($handle, 5);
        } finally {
            fclose($handle);
        }

        expect($header)->toBe('%PDF-')
            ->and(filesize($path))->toBe($document['file_size'])
            ->and(hash_file('sha256', $path))->toBe($document['sha256']);
    }
});

it('does not reuse the former LNO scan as BwB’s technical proposal', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/database/seeders/EndowmentFundTechnicalProposalScenarioSeeder.php'
    );
    $manifestSource = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/EndowmentFundTechnicalProposalDocumentManifest.php'
    );

    expect($source)
        ->toContain("private const LEGACY_SEED_DOCUMENT_FILENAMES = ['Technical Proposal_compressed.pdf'];")
        ->toContain('primary proposal scan is still pending')
        ->toContain('pruneLegacySeedDocuments');

    expect($manifestSource)
        ->toContain("'applicant_name' => 'BwB'")
        ->toContain("'filename' => 'Power of Attorney_compressed.pdf'")
        ->toContain('Supporting document');
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
