<?php

use App\Services\ThinkTankProcurementWorkbookDataset;

function procurementWorkbookRecord(array $dataset, string $recordKey): array
{
    foreach ($dataset['records'] as $record) {
        if ($record['record_key'] === $recordKey) {
            return $record;
        }
    }

    throw new \RuntimeException("Procurement workbook record [{$recordKey}] was not found.");
}

function procurementWorkbookDataset(): array
{
    static $dataset;

    return $dataset ??= (new ThinkTankProcurementWorkbookDataset)->load();
}

it('loads the pinned workbook manifest as an exact read-only dataset', function (): void {
    $dataset = procurementWorkbookDataset();

    expect($dataset['manifest'])->toMatchArray([
        'schema_version' => 1,
        'source_priority_order' => 'higher_wins',
    ])->and($dataset['diagnostics']['counts'])->toMatchArray([
        'workbooks' => 4,
        'physical_records' => 123,
        'unique_seedable_records' => 117,
        'excluded_records' => 3,
        'physical_below_10000' => 66,
        'physical_at_or_above_10000' => 57,
        'unique_below_10000' => 66,
        'unique_at_or_above_10000' => 51,
    ])->and($dataset['diagnostics']['totals'])->toMatchArray([
        'physical_usd' => '1964271.38',
        'unique_seedable_usd' => '1804958.38',
        'excluded_usd' => '115213.00',
        'unique_below_10000_usd' => '238573.38',
        'unique_at_or_above_10000_usd' => '1566385.00',
    ]);

    foreach ($dataset['workbooks'] as $workbook) {
        expect($workbook['verification'])->toMatchArray([
            'bytes_verified' => true,
            'sha256_verified' => true,
        ]);
        foreach ($workbook['sheets'] as $sheet) {
            foreach ($sheet['source_rows'] as $sourceRow) {
                expect(array_key_exists('XDJ', $sourceRow['cells']))->toBeFalse();
            }
        }
    }
});

it('applies audited owner, method, reference, review, merge, and title rules', function (): void {
    $dataset = procurementWorkbookDataset();

    $lcs = procurementWorkbookRecord($dataset, 'above_caceps|QCBS - FBS - LCS|8');
    expect($lcs)->toMatchArray([
        'source_reference' => 'ET-AUC-563626-CS-CDS',
        'member_key' => 'aphrc',
        'procurement_method' => 'Least Cost-Based Selection (LCS)',
        'procurement_method_code' => 'qcbs_fbs_lcs',
        'procurement_method_source' => 'manifest_record_override',
    ]);

    foreach ([5 => 'afidep', 7 => 'acet', 9 => 'reprc', 11 => 'reprc'] as $row => $ownerKey) {
        $crossConsortium = procurementWorkbookRecord($dataset, "above_caceps|RFQ|{$row}");
        expect($crossConsortium['member_key'])->toBe($ownerKey)
            ->and($crossConsortium['consortium_code'])->toBe(
                in_array($ownerKey, ['afidep', 'acet'], true) ? 'BRIDGE-AFRICA' : 'RAISED-AFRICA',
            );
    }

    $blankReview = procurementWorkbookRecord($dataset, 'below_bridge|Consultancy |39');
    expect($blankReview['source_reference_raw'])->toBe('E T-AUC-027-CS-INDV')
        ->and($blankReview['source_reference'])->toBe('ET-AUC-027-CS-INDV')
        ->and($blankReview['review_type'])->toBe('Post')
        ->and(trim((string) $blankReview['source_fields']['review_type']))->toBe('')
        ->and($blankReview['estimated_amount'])->toBe('10000.00')
        ->and($blankReview['threshold_band'])->toBe(ThinkTankProcurementWorkbookDataset::BAND_AT_OR_ABOVE);

    $merged = procurementWorkbookRecord($dataset, 'below_bridge|Consultancy |33');
    expect($merged['budget_reference'])->not->toBeNull()
        ->and($merged['source_field_provenance']['budget_reference']['inherited_from'])->toBe('AE32')
        ->and($merged['row_payload']['cells']['AE']['formatted'])->toBe('');

    $partialReference = procurementWorkbookRecord($dataset, 'below_bridge|Goods and Non consultancy |16');
    $doubleSeparator = procurementWorkbookRecord($dataset, 'below_bridge|Goods and Non consultancy |22');
    expect($partialReference['source_reference'])->toBe('ET-AUC-036')
        ->and($partialReference['reference_parts']['category'])->toBeNull()
        ->and($partialReference['reference_parts']['method'])->toBeNull()
        ->and($doubleSeparator['source_reference'])->toBe('ET-AUC-037-NC-RFQ');

    $longTitle = procurementWorkbookRecord($dataset, 'below_caceps|CACEPS Consultant Services|14');
    expect(mb_strlen($longTitle['description']))->toBe(337)
        ->and(mb_strlen($longTitle['title']))->toBeGreaterThan(255)
        ->and(mb_strlen($longTitle['display_title']))->toBe(255)
        ->and(array_column($longTitle['review_flags'], 'type'))->toContain('display_title_overflow')
        ->and($longTitle['row_payload'])->toHaveKeys(['row_number', 'cells'])
        ->and($longTitle['source_field_payloads']['activity'])->toHaveKeys([
            'raw', 'formatted', 'formula', 'data_type',
        ])
        ->and($longTitle['planned_start_date'])->toBe('2026-09-07')
        ->and($longTitle['planned_end_date'])->toBe('2027-11-09');

    $formulaEndDate = procurementWorkbookRecord($dataset, 'below_caceps|CACEPS Consultant Services|20');
    expect($formulaEndDate['planned_end_date'])->toBe('2028-08-28')
        ->and(collect($formulaEndDate['planned_milestones'])
            ->whereNotNull('formula')
            ->whereNotNull('date'))
        ->not->toBeEmpty();

    $postdoc = procurementWorkbookRecord($dataset, 'above_bridge|INDV|6');
    $translation = procurementWorkbookRecord($dataset, 'above_bridge|CQS|4');
    $categoryMismatch = procurementWorkbookRecord($dataset, 'below_bridge|Consultancy |41');
    foreach ([16, 17, 18] as $row) {
        $ambiguous = procurementWorkbookRecord(
            $dataset,
            "below_caceps|Goods and Non Consulting servic|{$row}",
        );
        expect(array_column($ambiguous['review_flags'], 'type'))
            ->toContain('method_and_milestone_ambiguity')
            ->and($ambiguous['procurement_method'])->toBe('Request for Quotations (RFQ)')
            ->and($ambiguous['procurement_method_code'])->toBe('rfq')
            ->and($ambiguous['procurement_method_source'])->toBe('manifest_override')
            ->and($ambiguous['planned_milestones'])->toBe([]);
    }
    expect(array_column($postdoc['review_flags'], 'type'))
        ->toContain('possible_hr_staffing_not_procurement')
        ->and(array_column($translation['review_flags'], 'type'))
        ->toContain('category_should_be_non_consulting')
        ->and(array_column($categoryMismatch['review_flags'], 'type'))
        ->toContain('category_mismatch');
});

it('keeps exclusions and duplicate provenance deterministic', function (): void {
    $dataset = procurementWorkbookDataset();
    $excluded = [];
    foreach ($dataset['excluded_records'] as $record) {
        $excluded[$record['source_reference']] = $record;
    }

    expect(array_keys($excluded))->toEqualCanonicalizing([
        'ET-AUC-494922-GO-RFQ',
        'ET-AUC-570606-CS-QCBS',
        'ET-AUC-570613-CS-CDS',
    ])->and($excluded['ET-AUC-494922-GO-RFQ']['exclusion_reason_code'])
        ->toBe('attp_secretariat_not_think_tank')
        ->and($excluded['ET-AUC-570606-CS-QCBS']['exclusion_reason_code'])
        ->toBe('consortium_wide_not_think_tank')
        ->and($excluded['ET-AUC-570613-CS-CDS']['exclusion_reason_code'])
        ->toBe('explicitly_excluded_non_procurement_activity')
        ->and($excluded['ET-AUC-570613-CS-CDS']['duplicate_count'])->toBe(1);

    $duplicates = [];
    foreach ($dataset['diagnostics']['duplicates'] as $duplicate) {
        $duplicates[$duplicate['reference']] = $duplicate;
    }
    expect($duplicates['ET-AUC-557675-GO-RFQ']['winner'])->toMatchArray([
        'workbook_key' => 'above_bridge',
        'source_sheet' => 'RFQ',
        'source_row' => 4,
    ])->and($duplicates['ET-AUC-557631-GO-RFQ']['winner'])->toMatchArray([
        'workbook_key' => 'above_bridge',
        'source_sheet' => 'RFQ',
        'source_row' => 5,
    ])->and($duplicates['ET-AUC-570613-CS-CDS']['winner'])->toMatchArray([
        'workbook_key' => 'above_bridge',
        'source_sheet' => 'CDS',
        'source_row' => 5,
    ]);
});

it('fails closed before parsing when a pinned workbook is changed', function (): void {
    $manifest = require dirname(__DIR__, 2).'/database/data/think_tank_procurement_workbooks.php';
    $source = dirname(__DIR__, 2).'/'.$manifest['workbooks']['above_bridge']['path'];
    $copy = sys_get_temp_dir().DIRECTORY_SEPARATOR.'attp-procurement-'.uniqid('', true).'.xlsx';
    copy($source, $copy);
    file_put_contents($copy, 'changed', FILE_APPEND);

    try {
        $definition = $manifest['workbooks']['above_bridge'];
        $definition['path'] = $copy;
        $tampered = [
            'schema_version' => 1,
            'workbooks' => ['tampered' => $definition],
            'owners' => $manifest['owners'],
        ];

        expect(fn () => (new ThinkTankProcurementWorkbookDataset)->load($tampered))
            ->toThrow(\RuntimeException::class, 'byte-size mismatch');
    } finally {
        @unlink($copy);
    }
});
