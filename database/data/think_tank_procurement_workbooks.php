<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Audited Think Tank Procurement Workbook Manifest
|--------------------------------------------------------------------------
|
| This manifest is data only. It pins the four reviewed FY2026 workbooks,
| describes their non-uniform worksheet layouts, and records the audit
| invariants that an idempotent importer/seeder must enforce.
|
| Monetary values are decimal strings so the audit totals remain exact.
| Section row ranges are inclusive [first row, last row]. A workbook's
| consortium is source context only: the owner token in each activity title
| must resolve globally before the destination think-tank plan is selected.
|
*/

return [
    'schema_version' => 1,
    'fiscal_year' => 2026,
    'currency' => 'USD',
    'money_encoding' => 'decimal_string',
    'row_range_format' => 'inclusive_[start,end]',

    'threshold' => [
        'amount_usd' => '10000.00',
        'below_band' => [
            'key' => 'below_10000',
            'comparison' => '<',
        ],
        'above_band' => [
            'key' => 'at_or_above_10000',
            'comparison' => '>=',
        ],
        'route_by_audited_amount' => true,
    ],

    'activity_status_mapping' => [
        'New' => 'draft',
        'Returned' => 'revision_requested',
        'Cleared' => 'no_objection_obtained',
    ],

    'activity_status_policy' => [
        'source_field' => 'source_activity_status',
        'workflow_field' => 'workflow_status',
        'unknown_status' => 'fail_closed',
        'preserve_source_literal' => true,
        'no_objection_evidence' => 'never_synthesize_from_activity_status',
        'notes' => 'Cleared records enter the matching portal workflow position, but the workbook supplies no formal no-objection date, reference, actor, or document.',
    ],

    'field_capture' => [
        'named_values' => 'source_payload.source.source_fields',
        'named_cell_payloads' => 'source_payload.source.source_field_payloads',
        'named_cell_provenance' => 'source_payload.source.source_field_provenance',
        'complete_row' => 'source_payload.source.row',
        'header' => 'source_payload.source.header',
        'subheader' => 'source_payload.source.subheader',
        'preserve_formulas_and_cached_values' => true,
    ],

    'source_priority' => [
        'direction' => 'higher_wins',
        'tie_breakers' => ['path_ascending', 'sheet_ascending', 'row_ascending'],
        'notes' => 'Priority is used only after owner plus canonical-reference identity matching.',
    ],

    'identity' => [
        'fields' => ['owner_key', 'canonical_reference'],
        'owner_resolution' => 'global_owner_aliases',
        'workbook_consortium_is_authoritative' => false,
        'same_owner_same_reference' => 'deduplicate_by_source_priority',
        'cross_owner_same_reference' => 'keep_as_distinct_items',
        'reference_normalization' => [
            'uppercase' => true,
            'trim' => true,
            'collapse_whitespace' => true,
            'remove_whitespace_inside_reference_prefix' => true,
            'normalize_whitespace_around_hyphens' => true,
        ],
    ],

    'method_labels' => [
        'RFB' => 'Request for Bids (RFB)',
        'RFQ' => 'Request for Quotations (RFQ)',
        'DIR' => 'Direct Selection',
        'QCBS' => 'Quality and Cost-Based Selection (QCBS)',
        'FBS' => 'Fixed Budget-Based Selection (FBS)',
        'LCS' => 'Least Cost-Based Selection (LCS)',
        'CQS' => "Consultant's Qualifications-Based Selection (CQS)",
        'CDS' => 'Consultant Direct Selection (CDS)',
        'INDV' => 'Individual Consultant Selection (INDV)',
    ],

    'consortia' => [
        'BRIDGE-AFRICA' => 'Bridge Africa Consortium',
        'CACEPS' => 'CACEPS Consortium',
        'RAISED-AFRICA' => 'RAISED Africa',
    ],

    'owners' => [
        'acet' => [
            'consortium_code' => 'BRIDGE-AFRICA',
            'name' => 'African Center for Economic Transformation (ACET)',
            'aliases' => ['ACET', 'African Center for Economic Transformation'],
        ],
        'afidep' => [
            'consortium_code' => 'BRIDGE-AFRICA',
            'name' => 'African Institute for Development Policy (AFIDEP)',
            'aliases' => ['AFIDEP', 'African Institute for Development Policy'],
        ],
        'nkafu' => [
            'consortium_code' => 'BRIDGE-AFRICA',
            'name' => 'Denis and Lenora Foretia Foundation (Nkafu Policy Institute)',
            'aliases' => ['NKAFU', 'Nkafu Policy Institute', 'Foretia Foundation'],
        ],
        'pcns' => [
            'consortium_code' => 'BRIDGE-AFRICA',
            'name' => 'Policy Center for the New South (PCNS)',
            'aliases' => ['PCNS', 'Policy Center for the New South'],
        ],
        'saiia' => [
            'consortium_code' => 'BRIDGE-AFRICA',
            'name' => 'South Africa Institute of International Affairs (SAIIA)',
            'aliases' => ['SAIIA', 'South Africa Institute of International Affairs'],
        ],
        'aphrc' => [
            'consortium_code' => 'CACEPS',
            'name' => 'African Population and Health Research Center (APHRC)',
            'aliases' => ['APHRC', 'African Population and Health Research Center'],
        ],
        'cip' => [
            'consortium_code' => 'CACEPS',
            'name' => 'Centro de Integridade Publica',
            'aliases' => ['CIP', 'Centro de Integridade Publica', 'Centro de Integridade Pública'],
        ],
        'cped' => [
            'consortium_code' => 'CACEPS',
            'name' => 'Centre for Population and Environmental Development (CPED)',
            'aliases' => ['CPED', 'Centre for Population and Environmental Development'],
        ],
        'eces' => [
            'consortium_code' => 'CACEPS',
            'name' => 'The Egyptian Center for Economic Studies (ECES)',
            'aliases' => ['ECES', 'The Egyptian Center for Economic Studies'],
        ],
        'ipar' => [
            'consortium_code' => 'CACEPS',
            'name' => 'Initiative Prospective Agricole et Rurale',
            'aliases' => ['IPAR', 'Initiative Prospective Agricole et Rurale'],
        ],
        'erf' => [
            'consortium_code' => 'RAISED-AFRICA',
            'name' => 'Economic Research Forum',
            'aliases' => ['ERF', 'Economic Research Forum'],
        ],
        'pep' => [
            'consortium_code' => 'RAISED-AFRICA',
            'name' => 'Partnership for Economic Policy (PEP)',
            'aliases' => ['PEP', 'Partnership for Economic Policy'],
        ],
        'reprc' => [
            'consortium_code' => 'RAISED-AFRICA',
            'name' => 'Resource and Environmental Policy Research Centre (REPRC), Environment for Development (EfD) Nigeria',
            'aliases' => ['REPRC', 'REPRC-UNN', 'Resource and Environmental Policy Research Centre'],
        ],
    ],

    'workbooks' => [
        'above_bridge' => [
            'path' => 'database/data/procurement/above_10k/bridge edit.xlsx',
            'sha256' => '8df0633d4f541bbba5c20076e0f7e57aedd30ab4f4de754575f8caa9ed2f54ed',
            'bytes' => 29894,
            'fiscal_year' => 2026,
            'source_label' => 'Above 10K - Bridge Africa',
            'source_priority' => 200,
            'source_declared_band' => 'at_or_above_10000',
            'default_consortium_code' => 'BRIDGE-AFRICA',
            'enforce_default_consortium' => false,
            'expected' => [
                'physical_records' => 32,
                'amount_usd' => '871895.00',
            ],
            'sheets' => [
                'RFQ' => [
                    'sections' => [[
                        'id' => 'rfq',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 5],
                        'method_override' => 'RFQ',
                        'milestones' => true,
                        'max_column' => 'AE',
                    ]],
                ],
                'QCBS - FBS - LCS' => [
                    'sections' => [[
                        'id' => 'mixed_firm_selection',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 11],
                        'method_override' => null,
                        'method_source' => 'canonical_reference_suffix',
                        'milestones' => true,
                        'max_column' => 'AM',
                        'notes' => 'The sheet name is not a method override; resolve LCS or QCBS from each canonical reference.',
                    ]],
                ],
                'CQS' => [
                    'sections' => [[
                        'id' => 'cqs',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 6],
                        'method_override' => 'CQS',
                        'milestones' => true,
                        'max_column' => 'AD',
                        'column_overrides' => [
                            'AC' => 'bank_comment',
                            'AD' => 'action_taken',
                        ],
                    ]],
                ],
                'CDS' => [
                    'sections' => [[
                        'id' => 'cds',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 7],
                        'method_override' => 'CDS',
                        'milestones' => true,
                        'max_column' => 'AD',
                        'column_overrides' => [
                            'AC' => 'bank_comment',
                            'AD' => 'action_taken',
                        ],
                    ]],
                ],
                'INDV' => [
                    'sections' => [[
                        'id' => 'indv',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 18],
                        'method_override' => 'INDV',
                        'milestones' => true,
                        'max_column' => 'AF',
                        'column_overrides' => [
                            'AE' => 'bank_comment',
                            'AF' => 'action_taken',
                        ],
                        'notes' => 'AE and AF contain reviewer text although the worksheet omits their header labels.',
                    ]],
                ],
            ],
            'excluded_sheets' => [
                'Evaluation Warning' => [
                    'reason' => 'Aspose evaluation watermark only; it contains no procurement records.',
                ],
            ],
        ],

        'above_caceps' => [
            'path' => 'database/data/procurement/above_10k/CACEPS Edit.xlsx',
            'sha256' => 'fb832c1d7cd9ea8bcb569e6db342056189f1e0cc8cb9a03659b1e845ab0f46d9',
            'bytes' => 31746,
            'fiscal_year' => 2026,
            'source_label' => 'Above 10K - CACEPS and mixed consortium records',
            'source_priority' => 100,
            'source_declared_band' => 'at_or_above_10000',
            'default_consortium_code' => 'CACEPS',
            'enforce_default_consortium' => false,
            'expected' => [
                'physical_records' => 24,
                'amount_usd' => '843803.00',
            ],
            'row_owner_overrides' => [
                ['sheet' => 'RFQ', 'row' => 5, 'owner_key' => 'afidep', 'note' => 'Bridge record duplicated in the Bridge workbook.'],
                ['sheet' => 'RFQ', 'row' => 7, 'owner_key' => 'acet', 'note' => 'Bridge record duplicated in the Bridge workbook.'],
                ['sheet' => 'RFQ', 'row' => 9, 'owner_key' => 'reprc', 'note' => 'RAISED record stored only in this mixed workbook.'],
                ['sheet' => 'RFQ', 'row' => 11, 'owner_key' => 'reprc', 'note' => 'RAISED record stored only in this mixed workbook.'],
            ],
            'sheets' => [
                'RFB' => [
                    'sections' => [[
                        'id' => 'rfb',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 4],
                        'method_override' => 'RFB',
                        'milestones' => true,
                        'max_column' => 'AQ',
                        'column_overrides' => [
                            'H' => 'source_prequalification',
                            'I' => 'source_procurement_process',
                        ],
                    ]],
                ],
                'RFQ' => [
                    'sections' => [[
                        'id' => 'rfq',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 11],
                        'method_override' => 'RFQ',
                        'milestones' => true,
                        'max_column' => 'AE',
                    ]],
                ],
                'DIR' => [
                    'sections' => [[
                        'id' => 'direct_selection',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 4],
                        'method_override' => 'DIR',
                        'milestones' => true,
                        'max_column' => 'Z',
                        'column_overrides' => [
                            'G' => 'source_evaluation_options',
                        ],
                    ]],
                ],
                'QCBS - FBS - LCS' => [
                    'sections' => [[
                        'id' => 'mixed_firm_selection',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 10],
                        'method_override' => null,
                        'method_source' => 'canonical_reference_suffix_with_record_overrides',
                        'milestones' => true,
                        'max_column' => 'AM',
                        'column_overrides' => [
                            'AM' => 'bank_comment',
                        ],
                        'notes' => 'The sheet is mixed. ET-AUC-563626-CS-CDS has an audited LCS override below.',
                    ]],
                ],
                'CDS' => [
                    'sections' => [[
                        'id' => 'cds',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 4],
                        'method_override' => 'CDS',
                        'milestones' => true,
                        'max_column' => 'AD',
                        'column_overrides' => [
                            'AC' => 'bank_comment',
                            'AD' => 'action_taken',
                        ],
                    ]],
                ],
                'INDV' => [
                    'sections' => [[
                        'id' => 'indv',
                        'title_row' => 1,
                        'header_row' => 2,
                        'subheader_row' => 3,
                        'rows' => [4, 9],
                        'method_override' => 'INDV',
                        'milestones' => true,
                        'max_column' => 'AF',
                        'column_overrides' => [
                            'AE' => 'bank_comment',
                            'AF' => 'action_taken',
                        ],
                    ]],
                ],
            ],
        ],

        'below_bridge' => [
            'path' => 'database/data/procurement/below_10k/Bridge Less than 10 K revised sent to bank .xlsx',
            'sha256' => '94d4af0303b25a00077aa663252fb29240be0d5c3ba60f1ffae3d53fc20b437f',
            'bytes' => 53964,
            'fiscal_year' => 2026,
            'source_label' => 'Below 10K - Bridge Africa',
            'source_priority' => 200,
            'source_declared_band' => 'below_10000',
            'default_consortium_code' => 'BRIDGE-AFRICA',
            'enforce_default_consortium' => false,
            'expected' => [
                'physical_records' => 41,
                'amount_usd' => '175317.38',
                'below_10000' => ['records' => 40, 'amount_usd' => '165317.38'],
                'at_or_above_10000' => ['records' => 1, 'amount_usd' => '10000.00'],
            ],
            'sheets' => [
                'Consultancy ' => [
                    'sections' => [
                        [
                            'id' => 'consultancy_indv',
                            'title_row' => 10,
                            'header_row' => 11,
                            'subheader_row' => 12,
                            'rows' => [13, 40],
                            'method_override' => 'INDV',
                            'milestones' => true,
                            'max_column' => 'AG',
                            'column_overrides' => [
                                'I' => 'limited_selection_justification',
                                'J' => 'source_activity_status',
                                'AE' => 'budget_reference',
                                'AF' => 'bank_comment',
                                'AG' => 'action_taken',
                            ],
                            'merged_value_inheritance' => [[
                                'range' => 'AE32:AE35',
                                'field' => 'budget_reference',
                                'source_cell' => 'AE32',
                                'inherit_to_rows' => [32, 35],
                                'preserve_raw_blanks' => true,
                                'notes' => 'The merged top-left value applies semantically to all four ACET records.',
                            ]],
                        ],
                        [
                            'id' => 'consultancy_lcs',
                            'title_row' => 10,
                            'header_row' => 11,
                            'subheader_row' => 12,
                            'rows' => [41, 41],
                            'method_override' => 'LCS',
                            'milestones' => true,
                            'max_column' => 'AG',
                            'column_overrides' => [
                                'I' => 'limited_selection_justification',
                                'J' => 'source_activity_status',
                                'AE' => 'budget_reference',
                                'AF' => 'bank_comment',
                                'AG' => 'action_taken',
                            ],
                            'notes' => 'The NC-LCS reference is reliable for method; the source category Consultancy remains review-required.',
                        ],
                        [
                            'id' => 'consultancy_cds',
                            'title_row' => 10,
                            'header_row' => 11,
                            'subheader_row' => 12,
                            'rows' => [42, 43],
                            'method_override' => 'CDS',
                            'milestones' => true,
                            'max_column' => 'AG',
                            'column_overrides' => [
                                'I' => 'limited_selection_justification',
                                'J' => 'source_activity_status',
                                'AE' => 'budget_reference',
                                'AF' => 'bank_comment',
                                'AG' => 'action_taken',
                            ],
                        ],
                        [
                            'id' => 'consultancy_cqs',
                            'title_row' => 46,
                            'header_row' => 47,
                            'subheader_row' => 48,
                            'rows' => [49, 49],
                            'method_override' => 'CQS',
                            'milestones' => true,
                            'max_column' => 'AE',
                            'column_overrides' => [
                                'I' => 'limited_selection_justification',
                                'J' => 'source_activity_status',
                                'AC' => 'budget_reference',
                                'AD' => 'bank_comment',
                                'AE' => 'action_taken',
                            ],
                        ],
                        [
                            'id' => 'consultancy_lcs_second_section',
                            'title_row' => 46,
                            'header_row' => 47,
                            'subheader_row' => 48,
                            'rows' => [50, 50],
                            'method_override' => 'LCS',
                            'milestones' => true,
                            'max_column' => 'AE',
                            'column_overrides' => [
                                'I' => 'limited_selection_justification',
                                'J' => 'source_activity_status',
                                'AC' => 'budget_reference',
                                'AD' => 'bank_comment',
                                'AE' => 'action_taken',
                            ],
                        ],
                    ],
                    'structural_rows' => [44, 46, 47, 48, 51, 53],
                ],
                'Goods and Non consultancy ' => [
                    'sections' => [
                        [
                            'id' => 'goods_direct_selection',
                            'title_row' => 11,
                            'header_row' => 12,
                            'subheader_row' => 13,
                            'rows' => [14, 16],
                            'method_override' => 'DIR',
                            'milestones' => true,
                            'max_column' => 'AC',
                            'column_overrides' => [
                                'G' => 'market_approach',
                                'I' => 'limited_selection_justification',
                                'K' => 'source_document_type',
                                'L' => 'source_process_status',
                                'M' => 'source_activity_status',
                                'AA' => 'budget_reference',
                                'AB' => 'bank_comment',
                                'AC' => 'action_taken',
                            ],
                        ],
                        [
                            'id' => 'goods_rfq',
                            'title_row' => 19,
                            'header_row' => 20,
                            'subheader_row' => 21,
                            'rows' => [22, 26],
                            'method_override' => 'RFQ',
                            'milestones' => true,
                            'max_column' => 'AG',
                            'column_overrides' => [
                                'I' => 'source_document_type',
                                'J' => 'source_process_status',
                                'K' => 'source_activity_status',
                                'AD' => 'limited_selection_justification',
                                'AE' => 'budget_reference',
                                'AF' => 'bank_comment',
                                'AG' => 'action_taken',
                            ],
                        ],
                    ],
                    'structural_rows' => [17, 19, 20, 21, 27, 30],
                    'audit_notes' => [
                        'The displayed subtotal/total omits Excel row 24 (USD 6000.00). Use the sum of audited activity rows: USD 32893.00, not USD 26893.00.',
                    ],
                ],
            ],
        ],

        'below_caceps' => [
            'path' => 'database/data/procurement/below_10k/CACEPS-Procurement-Plan-below10k-revised 01 september 2026.xlsx',
            'sha256' => 'b017879bf9e79fcf691d1d7678f762b1f26aa633021105ca35a55a6033c456a3',
            'bytes' => 100806,
            'fiscal_year' => 2026,
            'source_label' => 'Below 10K - CACEPS',
            'source_priority' => 100,
            'source_declared_band' => 'below_10000',
            'default_consortium_code' => 'CACEPS',
            'enforce_default_consortium' => false,
            'expected' => [
                'physical_records' => 26,
                'amount_usd' => '73256.00',
                'below_10000' => ['records' => 26, 'amount_usd' => '73256.00'],
                'at_or_above_10000' => ['records' => 0, 'amount_usd' => '0.00'],
            ],
            'sheets' => [
                'CACEPS Consultant Services' => [
                    'sections' => [
                        [
                            'id' => 'consultant_indv',
                            'title_row' => 11,
                            'header_row' => 12,
                            'subheader_row' => 13,
                            'rows' => [14, 14],
                            'method_override' => 'INDV',
                            'milestones' => true,
                            'max_column' => 'AF',
                            'column_overrides' => [
                                'I' => 'source_activity_status',
                                'AD' => 'budget_reference',
                                'AE' => 'bank_comment',
                                'AF' => 'action_taken',
                            ],
                        ],
                        [
                            'id' => 'consultant_lcs',
                            'title_row' => 17,
                            'header_row' => 18,
                            'subheader_row' => 19,
                            'rows' => [20, 21],
                            'method_override' => 'LCS',
                            'milestones' => true,
                            'max_column' => 'AL',
                            'column_overrides' => [
                                'I' => 'source_activity_status',
                                'AJ' => 'budget_reference',
                                'AK' => 'bank_comment',
                                'AL' => 'action_taken',
                            ],
                        ],
                    ],
                    'structural_rows' => [15, 17, 18, 19, 22, 24],
                ],
                'Goods and Non Consulting servic' => [
                    'worksheet_audit' => [
                        'reported_highest_data_column' => 'XDJ',
                        'real_value_max_column' => 'AD',
                        'merge_count' => 4132,
                        'read_policy' => 'sparse_cells_bounded_by_section_max_column',
                        'notes' => 'Thousands of formatted/merged phantom cells must not expand row payloads.',
                    ],
                    'sections' => [
                        [
                            'id' => 'direct_selection',
                            'title_row' => 6,
                            'header_row' => 7,
                            'subheader_row' => 8,
                            'rows' => [9, 14],
                            'method_override' => 'DIR',
                            'milestones' => true,
                            'max_column' => 'AB',
                            'column_overrides' => [
                                'I' => 'source_document_type',
                                'J' => 'source_activity_status',
                                'Y' => 'budget_reference',
                                'Z' => 'limited_selection_justification',
                                'AA' => 'bank_comment',
                                'AB' => 'action_taken',
                            ],
                        ],
                        [
                            'id' => 'rfq_non_consulting_reference_ambiguous',
                            'title_row' => 15,
                            'header_row' => 7,
                            'subheader_row' => 8,
                            'header_inherited' => true,
                            'rows' => [16, 18],
                            'method_override' => 'RFQ',
                            'method_candidates' => ['RFQ', 'INDV'],
                            'method_resolution' => 'rfq_section_and_document_evidence_with_review_required',
                            'milestones' => false,
                            'milestone_policy' => 'preserve_raw_only',
                            'max_column' => 'AB',
                            'column_overrides' => [
                                'I' => 'source_document_type',
                                'J' => 'source_activity_status',
                                'Y' => 'budget_reference',
                                'Z' => 'limited_selection_justification',
                                'AA' => 'bank_comment',
                                'AB' => 'action_taken',
                            ],
                            'notes' => 'The section, category, and reviewer action identify RFQ/non-consulting; the three stale references end CS-INDV. Preserve that conflict for review and do not infer milestones from the inherited direct-selection header.',
                        ],
                        [
                            'id' => 'rfq_non_consulting',
                            'title_row' => 15,
                            'header_row' => 7,
                            'subheader_row' => 8,
                            'header_inherited' => true,
                            'rows' => [19, 29],
                            'method_override' => 'RFQ',
                            'milestones' => false,
                            'milestone_policy' => 'preserve_raw_only',
                            'max_column' => 'AB',
                            'column_overrides' => [
                                'I' => 'source_document_type',
                                'J' => 'source_activity_status',
                                'Y' => 'budget_reference',
                                'Z' => 'limited_selection_justification',
                                'AA' => 'bank_comment',
                                'AB' => 'action_taken',
                            ],
                            'notes' => 'No replacement header exists after row 15; RFQ is explicit in column I, but milestone meanings remain ambiguous.',
                        ],
                        [
                            'id' => 'goods_rfq',
                            'title_row' => 31,
                            'auxiliary_rows' => [32],
                            'header_row' => 33,
                            'subheader_row' => 34,
                            'rows' => [35, 37],
                            'method_override' => 'RFQ',
                            'milestones' => true,
                            'max_column' => 'AD',
                            'column_overrides' => [
                                'I' => 'source_document_type',
                                'J' => 'source_activity_status',
                                'AB' => 'budget_reference',
                                'AC' => 'bank_comment',
                                'AD' => 'action_taken',
                            ],
                        ],
                    ],
                    'structural_rows' => [15, 30, 31, 32, 33, 34, 38, 40],
                ],
            ],
        ],
    ],

    'record_normalizations' => [
        [
            'workbook' => 'below_bridge',
            'sheet' => 'Consultancy ',
            'row' => 39,
            'reference' => 'ET-AUC-027-CS-INDV',
            'owner_key' => 'afidep',
            'source' => [
                'reference' => 'E T-AUC-027-CS-INDV',
                'review_type' => null,
            ],
            'set' => [
                'canonical_reference' => 'ET-AUC-027-CS-INDV',
                'review_type' => 'Post',
            ],
            'notes' => 'The source Review Type cell is blank. Normalize explicitly to Post, consistent with the audited surrounding FY2026 section, and retain the blank in raw source payload.',
        ],
        [
            'workbook' => 'below_caceps',
            'sheet' => 'Goods and Non Consulting servic',
            'row' => 17,
            'owner_key' => 'ipar',
            'source' => ['reference' => 'ET-AUC-008 CS-INDV'],
            'set' => ['canonical_reference' => 'ET-AUC-008-CS-INDV'],
            'notes' => 'Insert the missing separator between the sequence and procurement category.',
        ],
        [
            'workbook' => 'below_caceps',
            'sheet' => 'Goods and Non Consulting servic',
            'row' => 18,
            'owner_key' => 'ipar',
            'source' => ['reference' => 'ET-AUC-009 -CS-INDV'],
            'set' => ['canonical_reference' => 'ET-AUC-009-CS-INDV'],
            'notes' => 'Remove stray whitespace before the category separator.',
        ],
    ],

    'record_overrides' => [
        [
            'workbook' => 'above_caceps',
            'sheet' => 'QCBS - FBS - LCS',
            'row' => 8,
            'reference' => 'ET-AUC-563626-CS-CDS',
            'owner_key' => 'aphrc',
            'set' => ['procurement_method' => 'LCS'],
            'notes' => 'The bank reviewer explicitly directs Least Cost Selection for this routine financial audit assignment.',
        ],
    ],

    'hard_exclusions' => [
        [
            'reference' => 'ET-AUC-494922-GO-RFQ',
            'amount_usd' => '50000.00',
            'reason_code' => 'attp_secretariat_not_think_tank',
            'reason' => 'ATTP Secretariat procurement has no think-tank owner and must not enter a tenant plan.',
            'occurrences' => [
                ['workbook' => 'above_caceps', 'sheet' => 'RFQ', 'row' => 10],
            ],
        ],
        [
            'reference' => 'ET-AUC-570606-CS-QCBS',
            'amount_usd' => '55213.00',
            'reason_code' => 'consortium_wide_not_think_tank',
            'reason' => 'CACEPS-wide framework activity has no member owner; its category and method also require redesign.',
            'occurrences' => [
                ['workbook' => 'above_caceps', 'sheet' => 'QCBS - FBS - LCS', 'row' => 10],
            ],
        ],
        [
            'reference' => 'ET-AUC-570613-CS-CDS',
            'amount_usd' => '10000.00',
            'reason_code' => 'explicitly_excluded_non_procurement_activity',
            'reason' => 'Central policy mentorship has no member owner and the reviewer explicitly says to exclude it from the procurement plan.',
            'occurrences' => [
                ['workbook' => 'above_bridge', 'sheet' => 'CDS', 'row' => 5],
                ['workbook' => 'above_caceps', 'sheet' => 'CDS', 'row' => 4],
            ],
        ],
    ],

    'duplicate_refs' => [
        [
            'reference' => 'ET-AUC-557675-GO-RFQ',
            'owner_key' => 'afidep',
            'amount_usd' => '17500.00',
            'preferred' => ['workbook' => 'above_bridge', 'sheet' => 'RFQ', 'row' => 4],
            'duplicate' => ['workbook' => 'above_caceps', 'sheet' => 'RFQ', 'row' => 5],
        ],
        [
            'reference' => 'ET-AUC-557631-GO-RFQ',
            'owner_key' => 'acet',
            'amount_usd' => '16600.00',
            'preferred' => ['workbook' => 'above_bridge', 'sheet' => 'RFQ', 'row' => 5],
            'duplicate' => ['workbook' => 'above_caceps', 'sheet' => 'RFQ', 'row' => 7],
        ],
        [
            'reference' => 'ET-AUC-570613-CS-CDS',
            'owner_key' => null,
            'amount_usd' => '10000.00',
            'preferred' => ['workbook' => 'above_bridge', 'sheet' => 'CDS', 'row' => 5],
            'duplicate' => ['workbook' => 'above_caceps', 'sheet' => 'CDS', 'row' => 4],
            'notes' => 'Both occurrences are subsequently removed by the hard exclusion.',
        ],
    ],

    'cross_owner_reference_collisions' => [
        [
            'reference' => 'ET-AUC-007-CS-INDV',
            'items' => [
                ['workbook' => 'below_bridge', 'sheet' => 'Consultancy ', 'row' => 19, 'owner_key' => 'saiia'],
                ['workbook' => 'below_caceps', 'sheet' => 'Goods and Non Consulting servic', 'row' => 16, 'owner_key' => 'cip'],
            ],
        ],
        [
            'reference' => 'ET-AUC-009-CS-INDV',
            'items' => [
                ['workbook' => 'below_bridge', 'sheet' => 'Consultancy ', 'row' => 21, 'owner_key' => 'saiia'],
                ['workbook' => 'below_caceps', 'sheet' => 'Goods and Non Consulting servic', 'row' => 18, 'owner_key' => 'ipar'],
            ],
        ],
        [
            'reference' => 'ET-AUC-024-CS-INDV',
            'items' => [
                ['workbook' => 'below_bridge', 'sheet' => 'Consultancy ', 'row' => 36, 'owner_key' => 'afidep'],
                ['workbook' => 'below_caceps', 'sheet' => 'CACEPS Consultant Services', 'row' => 14, 'owner_key' => 'cip'],
            ],
        ],
    ],

    'review_required' => [
        [
            'type' => 'possible_hr_staffing_not_procurement',
            'workbook' => 'above_bridge',
            'sheet' => 'INDV',
            'owner_key' => 'afidep',
            'references' => [
                ['reference' => 'ET-AUC-563573-CS-INDV', 'row' => 6, 'amount_usd' => '30000.00'],
                ['reference' => 'ET-AUC-563571-CS-INDV', 'row' => 7, 'amount_usd' => '30000.00'],
                ['reference' => 'ET-AUC-561427-CS-INDV', 'row' => 8, 'amount_usd' => '30000.00'],
                ['reference' => 'ET-AUC-561425-CS-INDV', 'row' => 15, 'amount_usd' => '30000.00'],
            ],
            'notes' => 'Reviewer comments identify postdoctoral fellow engagements as possible HR/staffing. They remain in expected mapped counts until an authorized exclusion decision is made.',
        ],
        [
            'type' => 'category_should_be_non_consulting',
            'workbook' => 'above_bridge',
            'sheet' => 'CQS',
            'owner_key' => 'pcns',
            'references' => [
                ['reference' => 'ET-AUC-557701-CS-CQS', 'row' => 4, 'amount_usd' => '21150.00'],
                ['reference' => 'ET-AUC-557703-CS-CQS', 'row' => 5, 'amount_usd' => '21150.00'],
            ],
            'notes' => 'Reviewer says translation is non-consulting. Preserve the source record and flag category/method for approval.',
        ],
        [
            'type' => 'category_mismatch',
            'workbook' => 'below_bridge',
            'sheet' => 'Consultancy ',
            'row' => 41,
            'reference' => 'ET-AUC-029-NC-LCS',
            'owner_key' => 'nkafu',
            'notes' => 'The reference says non-consulting LCS while the source category says Consultancy.',
        ],
        [
            'type' => 'method_and_milestone_ambiguity',
            'workbook' => 'below_caceps',
            'sheet' => 'Goods and Non Consulting servic',
            'rows' => [16, 18],
            'notes' => 'Section/category evidence indicates RFQ/non-consulting, references indicate INDV, and no replacement milestone header exists.',
        ],
        [
            'type' => 'milestone_columns_preserved_raw_only',
            'workbook' => 'below_caceps',
            'sheet' => 'Goods and Non Consulting servic',
            'rows' => [16, 29],
            'notes' => 'No RFQ milestone header exists for these rows. Preserve every populated cell and inherited header in the raw payload without assigning unsupported milestone meanings.',
        ],
        [
            'type' => 'no_objection_evidence_not_supplied_in_workbook',
            'source_activity_status' => 'Cleared',
            'notes' => 'Cleared maps to the no-objection workflow position, but the workbook does not supply a formal decision date, reference, actor, or evidence document. Do not synthesize them.',
        ],
        [
            'type' => 'display_title_overflow',
            'workbook' => 'below_caceps',
            'sheet' => 'CACEPS Consultant Services',
            'row' => 14,
            'reference' => 'ET-AUC-024-CS-INDV',
            'source_title_length' => 337,
            'display_title_max_length' => 255,
            'preserve_full_text_in' => ['description', 'source_payload'],
            'notes' => 'Create a deterministic Unicode-safe display title of at most 255 characters without discarding the full source wording.',
        ],
    ],

    'expected' => [
        'source' => [
            'physical_records' => 123,
            'amount_usd' => '1964271.38',
        ],
        'same_owner_deduplicated' => [
            'records' => 120,
            'amount_usd' => '1920171.38',
            'removed_duplicate_records' => 3,
            'removed_duplicate_amount_usd' => '44100.00',
        ],
        'hard_exclusions' => [
            'physical_records' => 4,
            'physical_amount_usd' => '125213.00',
            'unique_records' => 3,
            'unique_amount_usd' => '115213.00',
        ],
        'mapped_unique' => [
            'records' => 117,
            'amount_usd' => '1804958.38',
        ],
        'physical_activity_statuses' => [
            'New' => 67,
            'Returned' => 8,
            'Cleared' => 48,
        ],
        'mapped_activity_statuses' => [
            'New' => 67,
            'Returned' => 5,
            'Cleared' => 45,
        ],
        'mapped_workflow_statuses' => [
            'draft' => 67,
            'revision_requested' => 5,
            'no_objection_obtained' => 45,
        ],
        'mapped_bands' => [
            'below_10000' => [
                'records' => 66,
                'amount_usd' => '238573.38',
            ],
            'at_or_above_10000' => [
                'records' => 51,
                'amount_usd' => '1566385.00',
            ],
        ],
        'above_source_unique_before_exclusions' => [
            'records' => 53,
            'amount_usd' => '1671598.00',
            'method_counts' => [
                'RFB' => 1,
                'RFQ' => 8,
                'DIR' => 1,
                'LCS' => 12,
                'CDS' => 4,
                'QCBS' => 3,
                'CQS' => 3,
                'INDV' => 21,
            ],
        ],
        'above_source_mapped_after_exclusions' => [
            'records' => 50,
            'amount_usd' => '1556385.00',
        ],
        'below_source' => [
            'physical_records' => 67,
            'amount_usd' => '248573.38',
            'below_10000' => ['records' => 66, 'amount_usd' => '238573.38'],
            'at_or_above_10000' => ['records' => 1, 'amount_usd' => '10000.00'],
        ],
    ],
];
