<?php

use App\Services\ThinkTankProcurementSpreadsheetMigrationService;
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

function procurementUniqueWorkbookRecord(array $dataset, string $recordKey): array
{
    foreach ($dataset['unique_records'] as $record) {
        if ($record['record_key'] === $recordKey) {
            return $record;
        }
    }

    throw new \RuntimeException("Unique procurement workbook record [{$recordKey}] was not found.");
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

it('maps each literal workbook activity status to the audited seeded workflow state', function (): void {
    $dataset = procurementWorkbookDataset();
    $expected = [
        'Cleared' => [
            'workflow_status' => 'no_objection_obtained',
            'records' => 45,
            'amount_minor' => 143_908_500,
        ],
        'Returned' => [
            'workflow_status' => 'revision_requested',
            'records' => 5,
            'amount_minor' => 11_730_000,
        ],
        'New' => [
            'workflow_status' => 'draft',
            'records' => 67,
            'amount_minor' => 24_857_338,
        ],
    ];
    $actual = array_map(
        static fn (array $group): array => [
            'workflow_status' => $group['workflow_status'],
            'records' => 0,
            'amount_minor' => 0,
        ],
        $expected,
    );

    foreach ($dataset['unique_records'] as $record) {
        $sourceStatus = $record['source_activity_status'];
        expect($expected)->toHaveKey($sourceStatus)
            ->and($record['source_fields']['source_activity_status'])->toBe($sourceStatus)
            ->and($record['workflow_status'])->toBe($expected[$sourceStatus]['workflow_status']);

        $actual[$sourceStatus]['records']++;
        $actual[$sourceStatus]['amount_minor'] += $record['estimated_amount_minor'];

        $reviewFlags = array_column($record['review_flags'], 'type');
        if ($sourceStatus === 'Cleared') {
            expect($reviewFlags)->toContain('no_objection_evidence_not_supplied_in_workbook');
        } else {
            expect($reviewFlags)->not->toContain('no_objection_evidence_not_supplied_in_workbook');
        }
    }

    expect($actual)->toBe($expected);

    $migration = new ThinkTankProcurementSpreadsheetMigrationService(
        new ThinkTankProcurementWorkbookDataset,
    );
    $seededWorkflowStatus = new ReflectionMethod($migration, 'seededWorkflowStatus');
    foreach ($dataset['unique_records'] as $record) {
        expect($seededWorkflowStatus->invoke($migration, $record))
            ->toBe($record['workflow_status']);
    }
});

it('preserves the Cleared source literal and imports no invented no-objection evidence', function (): void {
    $dataset = procurementWorkbookDataset();
    $record = procurementUniqueWorkbookRecord($dataset, 'above_caceps|DIR|4');
    $manifest = require $dataset['manifest']['path'];
    $migration = new ThinkTankProcurementSpreadsheetMigrationService(
        new ThinkTankProcurementWorkbookDataset,
    );
    $payloadMethod = new ReflectionMethod($migration, 'itemSourcePayload');
    $payload = $payloadMethod->invoke(
        $migration,
        $record,
        $dataset,
        $manifest,
        $record['threshold_band'],
    );
    $physicalPayloadMethod = new ReflectionMethod($migration, 'physicalRowPayload');
    $physicalPayload = $physicalPayloadMethod->invoke($migration, $record);

    expect($record['source_activity_status'])->toBe('Cleared')
        ->and($record['source_fields']['source_activity_status'])->toBe('Cleared')
        ->and($record['workflow_status'])->toBe('no_objection_obtained')
        ->and($payload['migration']['imported_workflow_status'])->toBe('no_objection_obtained')
        ->and($payload['source_statuses']['activity_status'])->toBe('Cleared')
        ->and($payload['source']['source_fields']['source_activity_status'])->toBe('Cleared')
        ->and(array_column($payload['review_flags'], 'type'))
        ->toContain('no_objection_evidence_not_supplied_in_workbook')
        ->and($physicalPayload['classification']['workflow_status'])->toBe('no_objection_obtained')
        ->and($physicalPayload['source_fields']['source_activity_status'])->toBe('Cleared')
        ->and(array_column($physicalPayload['review_flags'], 'type'))
        ->toContain('no_objection_evidence_not_supplied_in_workbook');

    $valuesForKey = function (mixed $value, string $key) use (&$valuesForKey): array {
        if (! is_array($value)) {
            return [];
        }

        $matches = [];
        foreach ($value as $candidateKey => $candidateValue) {
            if ($candidateKey === $key) {
                $matches[] = $candidateValue;
            }
            array_push($matches, ...$valuesForKey($candidateValue, $key));
        }

        return $matches;
    };

    foreach ([$payload, $physicalPayload] as $persistedSourcePayload) {
        foreach ([
            'no_objection_reference',
            'no_objection_date',
            'no_objection_notes',
            'no_objection_by',
            'no_objection_recorded_at',
        ] as $evidenceField) {
            expect(array_values(array_filter(
                $valuesForKey($persistedSourcePayload, $evidenceField),
                static fn (mixed $value): bool => $value !== null && $value !== '',
            )))->toBe([]);
        }
    }
});

it('promotes the three Bridge direct-selection columns without losing their source cells', function (): void {
    $dataset = procurementWorkbookDataset();
    $expected = [
        14 => [
            'reference' => 'ET-AUC-034-NC-DIR',
            'market_source' => 'Direct - National',
            'justification_sha256' => 'a35d898ba7662c3a62514c12aa343898afeeabc4f56c08d3f371703082fab9d5',
            'estimate_comment' => 'Please revise the estimate to less than 10,000 or Shift to above 10,000',
        ],
        15 => [
            'reference' => 'ET-AUC-035-CS-CDS',
            'market_source' => 'Direct - national',
            'justification_sha256' => 'e9e2df4d8f50b71390500f8a23cf4931ecbba3e427a18815108c965fc5c2d1fb',
            'estimate_comment' => null,
        ],
        16 => [
            'reference' => 'ET-AUC-036',
            'market_source' => 'Direct - national',
            'justification_sha256' => '05aafd024f7aa07c77acfe03b83d63f6696d893fe0d5df899106e01eb5f6659a',
            'estimate_comment' => null,
        ],
    ];

    foreach ($expected as $row => $values) {
        $record = procurementUniqueWorkbookRecord(
            $dataset,
            "below_bridge|Goods and Non consultancy |{$row}",
        );
        $marketCell = $record['row_payload']['cells']['G'];
        $justificationCell = $record['row_payload']['cells']['I'];

        expect($record['source_reference'])->toBe($values['reference'])
            ->and($record['header_payload']['cells']['G']['formatted'])->toBe('Market apporch ')
            ->and($record['header_payload']['cells']['I']['formatted'])->toBe('Justfication')
            ->and($marketCell['coordinate'])->toBe("G{$row}")
            ->and($marketCell['formatted'])->toBe($values['market_source'])
            ->and($record['source_fields']['market_approach'])->toBe($values['market_source'])
            ->and($record['source_field_provenance']['market_approach']['column'])->toBe('G')
            ->and($record['market_approach'])->toBe('Direct - National')
            ->and($justificationCell['coordinate'])->toBe("I{$row}")
            ->and(hash('sha256', $justificationCell['formatted']))
            ->toBe($values['justification_sha256'])
            ->and($record['source_fields']['limited_selection_justification'])
            ->toBe($justificationCell['formatted'])
            ->and($record['source_field_provenance']['limited_selection_justification']['column'])
            ->toBe('I')
            ->and($record['limited_selection_justification'])->toBe($justificationCell['formatted'])
            ->and(data_get($record, 'row_payload.cells.H.comment.text'))->toBe($values['estimate_comment']);

        if ($values['estimate_comment'] !== null) {
            expect($record['row_payload']['cells']['H']['comment'])
                ->toHaveKeys(['type', 'author', 'text', 'raw_text', 'visible'])
                ->and($record['row_payload']['cells']['H']['comment']['type'])->toBe('threaded')
                ->and($record['row_payload']['cells']['H']['comment']['raw_text'])
                ->toContain($values['estimate_comment']);
        }
    }
});

it('captures the CACEPS RFB and direct-selection auxiliary fields canonically and raw', function (): void {
    $dataset = procurementWorkbookDataset();
    $cases = [
        'above_caceps|RFB|4' => [
            'reference' => 'ET-AUC-563630-GO-RFB',
            'fields' => [
                'source_prequalification' => ['column' => 'H', 'header' => 'Prequalification (Y/N)', 'value' => 'N'],
                'source_procurement_process' => ['column' => 'I', 'header' => 'Procurement Process', 'value' => 'Single Stage One Envelope'],
            ],
        ],
        'above_caceps|DIR|4' => [
            'reference' => 'ET-AUC-563624-NC-DIR',
            'fields' => [
                'source_evaluation_options' => ['column' => 'G', 'header' => 'Evaluation Options', 'value' => 'Direct - National'],
            ],
        ],
    ];

    foreach ($cases as $recordKey => $case) {
        $record = procurementUniqueWorkbookRecord($dataset, $recordKey);
        expect($record['source_reference'])->toBe($case['reference']);

        foreach ($case['fields'] as $field => $source) {
            expect($record[$field])->toBe($source['value'])
                ->and($record['source_fields'][$field])->toBe($source['value'])
                ->and($record['source_field_provenance'][$field]['column'])->toBe($source['column'])
                ->and($record['header_payload']['cells'][$source['column']]['formatted'])
                ->toBe($source['header'])
                ->and($record['row_payload']['cells'][$source['column']]['formatted'])
                ->toBe($source['value']);
        }
    }
});

it('keeps all fourteen inherited RFQ schedules raw and explicitly review flagged', function (): void {
    $dataset = procurementWorkbookDataset();
    $references = [
        16 => 'ET-AUC-007-CS-INDV',
        17 => 'ET-AUC-008-CS-INDV',
        18 => 'ET-AUC-009-CS-INDV',
    ];
    foreach (range(19, 29) as $row) {
        $references[$row] = sprintf('ET-AUC-%03d-NC', $row - 9);
    }
    $earlyDates = [
        16 => ['K' => '2026-07-27', 'M' => '2026-08-16', 'O' => '2026-08-26', 'S' => '2026-08-29', 'U' => '2026-09-08', 'W' => '2026-09-18'],
        17 => ['K' => '2026-07-22', 'M' => '2026-08-11', 'O' => '2026-08-21', 'S' => '2026-08-24', 'U' => '2026-09-03', 'W' => '2026-09-13'],
        18 => ['K' => '2026-07-22', 'M' => '2026-08-11', 'O' => '2026-08-21', 'S' => '2026-08-24', 'U' => '2026-09-03', 'W' => '2026-09-13'],
    ];
    $laterDates = [
        'K' => '2026-09-07',
        'M' => '2026-09-12',
        'O' => '2026-09-22',
        'Q' => '2026-09-29',
        'S' => '2026-10-09',
        'V' => '2026-11-08',
    ];

    foreach ($references as $row => $reference) {
        $record = procurementUniqueWorkbookRecord(
            $dataset,
            "below_caceps|Goods and Non Consulting servic|{$row}",
        );
        $expectedDates = $earlyDates[$row] ?? $laterDates;
        $capturedDates = [];
        foreach (['K', 'M', 'O', 'Q', 'S', 'U', 'V', 'W'] as $column) {
            $cell = $record['row_payload']['cells'][$column] ?? null;
            if ($cell !== null && ($cell['formatted'] ?? '') !== '') {
                $capturedDates[$column] = $cell['formatted'];
            }
        }

        expect($record['source_reference'])->toBe($reference)
            ->and($capturedDates)->toBe($expectedDates)
            ->and($record['planned_milestones'])->toBe([])
            ->and($record['planned_start_date'])->toBeNull()
            ->and($record['planned_end_date'])->toBeNull();

        foreach ($expectedDates as $column => $date) {
            expect($record['row_payload']['cells'][$column])
                ->toHaveKeys(['coordinate', 'raw', 'formatted', 'formula', 'cached', 'data_type'])
                ->and($record['row_payload']['cells'][$column]['coordinate'])->toBe("{$column}{$row}")
                ->and($record['row_payload']['cells'][$column]['formatted'])->toBe($date);
        }

        $reviewFlags = array_column($record['review_flags'], 'type');
        expect($reviewFlags)->toContain('milestone_columns_preserved_raw_only');
        if ($row <= 18) {
            expect($reviewFlags)->toContain('method_and_milestone_ambiguity');
        } else {
            expect($reviewFlags)->not->toContain('method_and_milestone_ambiguity');
        }
    }

    $headerRecord = procurementUniqueWorkbookRecord(
        $dataset,
        'below_caceps|Goods and Non Consulting servic|19',
    );
    expect([
        'K' => [
            $headerRecord['header_payload']['cells']['K']['formatted'],
            $headerRecord['subheader_payload']['cells']['K']['formatted'],
        ],
        'M' => [
            $headerRecord['header_payload']['cells']['M']['formatted'],
            $headerRecord['subheader_payload']['cells']['M']['formatted'],
        ],
        'O' => [
            $headerRecord['header_payload']['cells']['O']['formatted'],
            $headerRecord['subheader_payload']['cells']['O']['formatted'],
        ],
        'Q' => [
            $headerRecord['header_payload']['cells']['Q']['formatted'],
            $headerRecord['subheader_payload']['cells']['Q']['formatted'],
        ],
        'S' => [
            $headerRecord['header_payload']['cells']['S']['formatted'],
            $headerRecord['subheader_payload']['cells']['S']['formatted'],
        ],
        'U' => [
            $headerRecord['header_payload']['cells']['U']['formatted'],
            $headerRecord['subheader_payload']['cells']['U']['formatted'],
        ],
        'V' => [
            $headerRecord['header_payload']['cells']['V']['formatted'],
            $headerRecord['subheader_payload']['cells']['V']['formatted'],
        ],
        'W' => [
            $headerRecord['header_payload']['cells']['W']['formatted'] ?? null,
            $headerRecord['subheader_payload']['cells']['W']['formatted'],
        ],
    ])->toBe([
        'K' => ['Justification for Direct Procurement', 'Planned'],
        'M' => ['Invitation to Supplier / Contractor ', 'Planned'],
        'O' => ['Draft Contract', 'Planned'],
        'Q' => ['Notification of Intention of Award', 'Planned'],
        'S' => ['Signed Contract', 'Planned'],
        'U' => ['Contract Amendments', 'Actual'],
        'V' => ['Contract Completion', 'Planned'],
        'W' => [null, 'Actual'],
    ]);
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
            'activity_status_mapping' => $manifest['activity_status_mapping'],
        ];

        expect(fn () => (new ThinkTankProcurementWorkbookDataset)->load($tampered))
            ->toThrow(\RuntimeException::class, 'byte-size mismatch');
    } finally {
        @unlink($copy);
    }
});
