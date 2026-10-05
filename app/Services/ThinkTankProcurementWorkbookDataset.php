<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;
use Throwable;

/**
 * Read and normalize the pinned legacy Think Tank procurement workbooks.
 *
 * This service intentionally has no model, database, filesystem-write, or
 * storage dependencies. The returned dataset is suitable for a seeder or an
 * audit command, but loading it can never mutate application state.
 */
final class ThinkTankProcurementWorkbookDataset
{
    public const BAND_BELOW = 'below_10000';

    public const BAND_AT_OR_ABOVE = 'at_or_above_10000';

    public const BAND_CURRENCY_REVIEW = 'currency_review';

    private const THRESHOLD_MINOR_USD = 1_000_000;

    /**
     * A defensive ceiling for sparse cells when a manifest section does not
     * provide max_column. We iterate existing coordinates only; we never walk
     * from A to this ceiling.
     */
    private const DEFAULT_MAX_COLUMN = 'ZZ';

    /** @var array<string, array{member_key: string, consortium_code: string, member_name: string}> */
    private const OWNER_ALIASES = [
        'ACET' => [
            'member_key' => 'acet',
            'consortium_code' => 'BRIDGE-AFRICA',
            'member_name' => 'African Center for Economic Transformation (ACET)',
        ],
        'AFIDEP' => [
            'member_key' => 'afidep',
            'consortium_code' => 'BRIDGE-AFRICA',
            'member_name' => 'African Institute for Development Policy (AFIDEP)',
        ],
        'NKAFU' => [
            'member_key' => 'nkafu',
            'consortium_code' => 'BRIDGE-AFRICA',
            'member_name' => 'Denis and Lenora Foretia Foundation (Nkafu Policy Institute)',
        ],
        'PCNS' => [
            'member_key' => 'pcns',
            'consortium_code' => 'BRIDGE-AFRICA',
            'member_name' => 'Policy Center for the New South (PCNS)',
        ],
        'SAIIA' => [
            'member_key' => 'saiia',
            'consortium_code' => 'BRIDGE-AFRICA',
            'member_name' => 'South Africa Institute of International Affairs (SAIIA)',
        ],
        'APHRC' => [
            'member_key' => 'aphrc',
            'consortium_code' => 'CACEPS',
            'member_name' => 'African Population and Health Research Center (APHRC)',
        ],
        'CIP' => [
            'member_key' => 'cip',
            'consortium_code' => 'CACEPS',
            'member_name' => 'Centro de Integridade Publica',
        ],
        'CPED' => [
            'member_key' => 'cped',
            'consortium_code' => 'CACEPS',
            'member_name' => 'Centre for Population and Environmental Development (CPED)',
        ],
        'ECES' => [
            'member_key' => 'eces',
            'consortium_code' => 'CACEPS',
            'member_name' => 'The Egyptian Center for Economic Studies (ECES)',
        ],
        'IPAR' => [
            'member_key' => 'ipar',
            'consortium_code' => 'CACEPS',
            'member_name' => 'Initiative Prospective Agricole et Rurale',
        ],
        'REPRC' => [
            'member_key' => 'reprc',
            'consortium_code' => 'RAISED-AFRICA',
            'member_name' => 'Resource and Environmental Policy Research Centre (REPRC), Environment for Development (EfD) Nigeria',
        ],
        'PEP' => [
            'member_key' => 'pep',
            'consortium_code' => 'RAISED-AFRICA',
            'member_name' => 'Partnership for Economic Policy (PEP)',
        ],
        'ERF' => [
            'member_key' => 'erf',
            'consortium_code' => 'RAISED-AFRICA',
            'member_name' => 'Economic Research Forum',
        ],
    ];

    /** @var array<string, string> */
    private const CONSORTIUM_ALIASES = [
        'BRIDGE' => 'BRIDGE-AFRICA',
        'BRIDE' => 'BRIDGE-AFRICA',
        'BRIDGE AFRICA' => 'BRIDGE-AFRICA',
        'CACEPS' => 'CACEPS',
        'CAESEPS' => 'CACEPS',
        'RAISED' => 'RAISED-AFRICA',
        'RAISED AFRICA' => 'RAISED-AFRICA',
    ];

    /** @var array<string, array<int, string>> */
    private const HEADER_ALIASES = [
        'activity' => [
            'activity reference no description', 'activity reference number description',
            'procurement description', 'description of procurement', 'contract description',
            'activity description', 'item description', 'description', 'procurement item', 'activity',
        ],
        'source_reference' => [
            'reference no', 'reference number', 'procurement reference', 'contract reference',
            'package number', 'package no', 'ref no', 'reference', 'id',
        ],
        'title' => ['title', 'procurement title', 'activity title'],
        'source_in_process' => ['in process'],
        'loan_credit_no' => ['loan credit no', 'loan credit number'],
        'component' => ['component'],
        'review_type' => ['review type', 'bank review', 'prior post review', 'prior post'],
        'procurement_category' => ['procurement category', 'category', 'type of procurement', 'procurement type'],
        'procurement_method' => ['procurement method', 'selection method', 'method', 'method of procurement'],
        'market_approach' => ['market approach', 'approach to market', 'market'],
        'quantity' => ['quantity', 'qty'],
        'unit' => ['unit of measure', 'unit'],
        'estimated_unit_cost' => ['estimated unit cost', 'unit cost'],
        'estimated_amount' => [
            'estimated amount us', 'estimated amount usd', 'estimated amount', 'estimated cost',
            'estimated budget', 'budget', 'total amount', 'amount usd', 'cost',
        ],
        'currency' => ['currency', 'curr'],
        'planned_quarter' => ['planned quarter', 'quarter', 'qtr'],
        'planned_start_date' => ['planned start date', 'start date', 'launch date', 'planned date'],
        'planned_end_date' => ['planned end date', 'end date', 'completion date'],
        'fiscal_year' => ['fiscal year', 'financial year', 'fy'],
        'source_process_status' => ['process status'],
        'source_activity_status' => ['activity status'],
        'source_document_type' => ['procurement document type', 'document type'],
        'source_sea_sh_risk' => ['high sea sh risk', 'sea sh risk'],
        'source_prequalification' => ['prequalification y n', 'prequalification'],
        'source_procurement_process' => ['procurement process'],
        'source_evaluation_options' => ['evaluation options'],
        'budget_reference' => ['budget reference', 'budget ref'],
        'limited_selection_justification' => [
            'limited selection justification', 'direct selection justification',
            'justification for limited selection',
        ],
        'bank_comment' => ['bank s comment', 'bank comment', 'world bank comment'],
        'action_taken' => ['auc comment', 'action taken', 'auc action', 'response action taken'],
    ];

    /** @var array<string, array{code: ?string, label: string}> */
    private const METHOD_VALUES = [
        'RFQ' => ['code' => 'rfq', 'label' => 'Request for Quotations (RFQ)'],
        'RFB' => ['code' => 'rfb', 'label' => 'Request for Bids (RFB)'],
        'QCBS' => ['code' => 'qcbs_fbs_lcs', 'label' => 'QCBS / FBS / LCS'],
        'FBS' => ['code' => 'qcbs_fbs_lcs', 'label' => 'QCBS / FBS / LCS'],
        'LCS' => ['code' => 'qcbs_fbs_lcs', 'label' => 'QCBS / FBS / LCS'],
        'QCBS_FBS_LCS' => ['code' => 'qcbs_fbs_lcs', 'label' => 'QCBS / FBS / LCS'],
        'CQS' => ['code' => 'cqs', 'label' => "Consultant's Qualifications-Based Selection (CQS)"],
        'CDS' => ['code' => 'cds', 'label' => 'Consultant Direct Selection (CDS)'],
        'INDV' => ['code' => 'indv', 'label' => 'Individual Consultant Selection (INDV)'],
        'DIR' => ['code' => 'direct_selection', 'label' => 'Direct Selection'],
        'DIRECT' => ['code' => 'direct_selection', 'label' => 'Direct Selection'],
    ];

    private string $manifestPath;

    private string $basePath;

    /** @var array<string, array{code: ?string, label: string}> */
    private array $methodValues = self::METHOD_VALUES;

    /** @var array<int, array<string, mixed>> */
    private array $reviewRequirements = [];

    /** @var array<string, string> */
    private array $activityStatusMapping = [];

    public function __construct(?string $manifestPath = null, ?string $basePath = null)
    {
        $this->basePath = rtrim($basePath ?? dirname(__DIR__, 2), '/\\');
        $this->manifestPath = $manifestPath
            ?? $this->basePath.DIRECTORY_SEPARATOR.'database'.DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'think_tank_procurement_workbooks.php';
    }

    /**
     * @param  array<string, mixed>|null  $manifest
     * @return array{
     *     manifest: array<string, mixed>,
     *     workbooks: array<string, array<string, mixed>>,
     *     records: array<int, array<string, mixed>>,
     *     unique_records: array<int, array<string, mixed>>,
     *     excluded_records: array<int, array<string, mixed>>,
     *     diagnostics: array<string, mixed>
     * }
     */
    public function load(?array $manifest = null): array
    {
        $manifest ??= $this->readManifest();
        $workbookDefinitions = $this->workbookDefinitions($manifest);
        $ownerAliases = $this->ownerAliases($manifest);
        $this->methodValues = $this->methodValues($manifest);
        $this->reviewRequirements = is_array($manifest['review_required'] ?? null)
            ? array_values(array_filter($manifest['review_required'], 'is_array'))
            : [];
        $this->activityStatusMapping = $this->activityStatusMapping($manifest);
        $globalNormalizations = $this->recordRules($manifest);
        $explicitExclusions = $this->explicitExclusions($manifest);
        $priorityDirection = $manifest['source_priority']['direction']
            ?? $manifest['source_priority_order']
            ?? 'higher_wins';
        $priorityOrder = $priorityDirection === 'lower_wins'
            ? 'lower_wins'
            : 'higher_wins';

        $diagnostics = [
            'errors' => [],
            'warnings' => [],
            'normalizations' => [],
            'duplicates' => [],
            'timeline_anomalies' => [],
            'ignored_sparse_cells' => [],
            'review_required' => [],
        ];
        $workbooks = [];
        $records = [];
        $sequence = 0;

        foreach ($workbookDefinitions as $workbookKey => $definition) {
            $loaded = $this->loadWorkbook(
                $workbookKey,
                $definition,
                $ownerAliases,
                $globalNormalizations,
                $explicitExclusions,
                $diagnostics,
                $sequence,
            );
            $workbooks[$workbookKey] = $loaded['workbook'];
            array_push($records, ...$loaded['records']);
        }

        [$uniqueRecords, $excludedRecords, $duplicateDiagnostics] = $this->deduplicate(
            $records,
            $priorityOrder,
        );
        array_push($diagnostics['duplicates'], ...$duplicateDiagnostics);

        foreach ($records as &$record) {
            unset($record['_sequence']);
        }
        unset($record);

        $diagnostics['counts'] = $this->counts($workbooks, $records, $uniqueRecords, $excludedRecords);
        $diagnostics['totals'] = $this->totals($records, $uniqueRecords, $excludedRecords);
        $diagnostics['expected'] = $manifest['expected'] ?? null;

        return [
            'manifest' => [
                'path' => $this->manifestPath,
                'schema_version' => $manifest['schema_version'] ?? $manifest['version'] ?? null,
                'source_priority_order' => $priorityOrder,
            ],
            'workbooks' => $workbooks,
            'records' => $records,
            'unique_records' => $uniqueRecords,
            'excluded_records' => $excludedRecords,
            'diagnostics' => $diagnostics,
        ];
    }

    /** @return array<string, mixed> */
    private function readManifest(): array
    {
        if (! is_file($this->manifestPath)) {
            throw new RuntimeException("Think Tank procurement workbook manifest not found [{$this->manifestPath}].");
        }

        $manifest = require $this->manifestPath;
        if (! is_array($manifest)) {
            throw new RuntimeException('Think Tank procurement workbook manifest must return an array.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, array<string, mixed>>
     */
    private function workbookDefinitions(array $manifest): array
    {
        $definitions = $manifest['workbooks'] ?? null;
        if (! is_array($definitions) || $definitions === []) {
            throw new RuntimeException('Think Tank procurement workbook manifest contains no workbooks.');
        }

        $normalized = [];
        foreach ($definitions as $key => $definition) {
            if (! is_array($definition)) {
                throw new RuntimeException('Each procurement workbook manifest entry must be an array.');
            }
            $workbookKey = is_string($key) ? $key : (string) ($definition['key'] ?? 'workbook_'.$key);
            $normalized[$workbookKey] = $definition;
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, array{member_key: string, consortium_code: string, member_name: string}>
     */
    private function ownerAliases(array $manifest): array
    {
        $owners = self::OWNER_ALIASES;
        $configured = $manifest['owner_aliases'] ?? $manifest['owners'] ?? null;
        if (! is_array($configured)) {
            return $owners;
        }

        foreach ($configured as $key => $definition) {
            if (! is_string($key) || ! is_array($definition)) {
                continue;
            }
            $isOwnerRegistry = isset($definition['aliases']) && is_array($definition['aliases']);
            $memberKey = trim((string) ($definition['member_key'] ?? $definition['key'] ?? ($isOwnerRegistry ? $key : '')));
            $consortiumCode = trim((string) ($definition['consortium_code'] ?? ''));
            if ($memberKey === '' || $consortiumCode === '') {
                continue;
            }
            $aliases = $isOwnerRegistry ? $definition['aliases'] : [$key];
            foreach ($aliases as $alias) {
                if (! is_scalar($alias) || trim((string) $alias) === '') {
                    continue;
                }
                $owners[Str::upper(trim((string) $alias))] = [
                    'member_key' => $memberKey,
                    'consortium_code' => $consortiumCode,
                    'member_name' => trim((string) ($definition['member_name'] ?? $definition['name'] ?? $memberKey)),
                ];
            }
        }

        return $owners;
    }

    /** @return array<string, array{code: ?string, label: string}> */
    private function methodValues(array $manifest): array
    {
        $values = self::METHOD_VALUES;
        if (! is_array($manifest['method_labels'] ?? null)) {
            return $values;
        }
        foreach ($manifest['method_labels'] as $key => $label) {
            $key = Str::upper(trim((string) $key));
            if ($key === '' || ! is_scalar($label)) {
                continue;
            }
            $values[$key] = [
                'code' => self::METHOD_VALUES[$key]['code'] ?? Str::lower($key),
                'label' => trim((string) $label),
            ];
        }

        return $values;
    }

    /** @return array<int, array<string, mixed>> */
    private function recordRules(array $manifest): array
    {
        $rules = [];
        foreach (['record_normalizations' => 'record_normalization', 'record_overrides' => 'record_override', 'normalizations' => 'normalization'] as $key => $type) {
            if (! is_array($manifest[$key] ?? null)) {
                continue;
            }
            foreach ($manifest[$key] as $rule) {
                if (is_array($rule)) {
                    $rules[] = [...$rule, '_rule_type' => $type];
                }
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    private function activityStatusMapping(array $manifest): array
    {
        $mapping = $manifest['activity_status_mapping'] ?? null;
        $expected = [
            'New' => 'draft',
            'Returned' => 'revision_requested',
            'Cleared' => 'no_objection_obtained',
        ];

        if ($mapping !== $expected) {
            throw new RuntimeException('The procurement activity-status workflow mapping is missing or has drifted from the audited contract.');
        }

        return $mapping;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, array{reason: string, reason_code: ?string, definition: array<string, mixed>}>
     */
    private function explicitExclusions(array $manifest): array
    {
        $candidates = $manifest['excluded_references']
            ?? $manifest['exclusions']
            ?? $manifest['hard_exclusions']
            ?? ($manifest['expected']['excluded_references'] ?? []);
        if (! is_array($candidates)) {
            return [];
        }

        $exclusions = [];
        foreach ($candidates as $key => $value) {
            $reference = null;
            $reason = 'Excluded by workbook manifest.';
            if (is_string($key) && ! is_int($key)) {
                $reference = $key;
                $reason = is_scalar($value) ? trim((string) $value) : $reason;
            } elseif (is_string($value)) {
                $reference = $value;
            } elseif (is_array($value)) {
                $reference = (string) ($value['reference'] ?? $value['source_reference'] ?? '');
                $reason = trim((string) ($value['reason'] ?? $value['note'] ?? $reason));
            }
            if (! $reference) {
                continue;
            }
            $parsed = $this->canonicalReference($reference);
            $canonical = $parsed['canonical'] ?? trim($reference);
            if ($canonical !== '') {
                $exclusions[$canonical] = [
                    'reason' => $reason !== '' ? $reason : 'Excluded by workbook manifest.',
                    'reason_code' => is_array($value) && isset($value['reason_code'])
                        ? trim((string) $value['reason_code'])
                        : null,
                    'definition' => is_array($value) ? $value : [],
                ];
            }
        }

        return $exclusions;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, array{member_key: string, consortium_code: string, member_name: string}>  $ownerAliases
     * @param  array<int|string, mixed>  $globalNormalizations
     * @param  array<string, array<string, mixed>>  $explicitExclusions
     * @param  array<string, mixed>  $diagnostics
     * @return array{workbook: array<string, mixed>, records: array<int, array<string, mixed>>}
     */
    private function loadWorkbook(
        string $workbookKey,
        array $definition,
        array $ownerAliases,
        array $globalNormalizations,
        array $explicitExclusions,
        array &$diagnostics,
        int &$sequence,
    ): array {
        $relativePath = trim((string) ($definition['path'] ?? ''));
        if ($relativePath === '') {
            throw new RuntimeException("Workbook [{$workbookKey}] has no path in the manifest.");
        }
        $path = $this->resolvePath($relativePath);
        $verification = $this->verifyFile($workbookKey, $path, $definition);
        $sheetDefinitions = $definition['sheets'] ?? null;
        if (! is_array($sheetDefinitions) || $sheetDefinitions === []) {
            throw new RuntimeException("Workbook [{$workbookKey}] contains no sheet definitions.");
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        if (method_exists($reader, 'setReadEmptyCells')) {
            $reader->setReadEmptyCells(false);
        }
        if (method_exists($reader, 'setIncludeCharts')) {
            $reader->setIncludeCharts(false);
        }
        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly(array_map('strval', array_keys($sheetDefinitions)));
        }

        $spreadsheet = $this->loadSpreadsheet($reader, $path);
        $records = [];
        $loadedSheets = [];

        try {
            foreach ($sheetDefinitions as $sheetName => $sheetDefinition) {
                if (! is_string($sheetName) || ! is_array($sheetDefinition)) {
                    throw new RuntimeException("Workbook [{$workbookKey}] has an invalid sheet definition.");
                }
                $worksheet = $spreadsheet->getSheetByName($sheetName);
                if (! $worksheet) {
                    throw new RuntimeException("Pinned workbook [{$workbookKey}] is missing sheet [{$sheetName}].");
                }

                $loaded = $this->loadSheet(
                    $worksheet,
                    $workbookKey,
                    $relativePath,
                    $definition,
                    $sheetDefinition,
                    $ownerAliases,
                    $globalNormalizations,
                    $explicitExclusions,
                    $diagnostics,
                    $sequence,
                );
                $loadedSheets[$sheetName] = $loaded['sheet'];
                array_push($records, ...$loaded['records']);
            }
        } finally {
            $spreadsheet->disconnectWorksheets();
        }

        return [
            'workbook' => [
                'key' => $workbookKey,
                'path' => $relativePath,
                'absolute_path' => $path,
                'source_label' => $definition['source_label'] ?? $workbookKey,
                'source_priority' => (int) ($definition['source_priority'] ?? 0),
                'fiscal_year' => isset($definition['fiscal_year']) ? (string) $definition['fiscal_year'] : null,
                'verification' => $verification,
                'sheets' => $loadedSheets,
            ],
            'records' => $records,
        ];
    }

    private function resolvePath(string $relativePath): string
    {
        $candidate = $relativePath;
        $isAbsolute = str_starts_with($candidate, '/')
            || str_starts_with($candidate, '\\\\')
            || preg_match('~^[A-Za-z]:[\\\\/]~', $candidate) === 1;
        if (! $isAbsolute) {
            $candidate = $this->basePath.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate);
        }
        $resolved = realpath($candidate);
        if ($resolved === false || ! is_file($resolved)) {
            throw new RuntimeException("Pinned procurement workbook not found [{$relativePath}].");
        }

        return $resolved;
    }

    private function loadSpreadsheet(IReader $reader, string $path): Spreadsheet
    {
        $vendorRoot = Str::lower(str_replace(
            '\\',
            '/',
            $this->basePath.DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR.'phpoffice'.DIRECTORY_SEPARATOR.'phpspreadsheet'.DIRECTORY_SEPARATOR,
        ));
        $previousHandler = null;
        $previousHandler = set_error_handler(
            static function (int $severity, string $message, string $file, int $line) use (&$previousHandler, $vendorRoot): bool {
                $fromPhpSpreadsheet = str_starts_with(
                    Str::lower(str_replace('\\', '/', $file)),
                    $vendorRoot,
                );
                if ($fromPhpSpreadsheet && in_array($severity, [E_DEPRECATED, E_USER_DEPRECATED], true)) {
                    return true;
                }

                return is_callable($previousHandler)
                    ? (bool) $previousHandler($severity, $message, $file, $line)
                    : false;
            },
        );

        try {
            return $reader->load($path);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array{bytes: int, sha256: string, bytes_verified: bool, sha256_verified: bool}
     */
    private function verifyFile(string $workbookKey, string $path, array $definition): array
    {
        $expectedBytes = $definition['bytes'] ?? $definition['size'] ?? $definition['file_size'] ?? null;
        $expectedHash = Str::lower(trim((string) ($definition['sha256'] ?? $definition['hash'] ?? '')));
        if (! is_int($expectedBytes) && ! ctype_digit((string) $expectedBytes)) {
            throw new RuntimeException("Workbook [{$workbookKey}] has no pinned byte size.");
        }
        if (! preg_match('/^[a-f0-9]{64}$/', $expectedHash)) {
            throw new RuntimeException("Workbook [{$workbookKey}] has no valid pinned SHA-256 hash.");
        }

        $actualBytes = filesize($path);
        $actualHash = hash_file('sha256', $path);
        if ($actualBytes === false || $actualHash === false) {
            throw new RuntimeException("Unable to fingerprint pinned workbook [{$workbookKey}].");
        }
        if ($actualBytes !== (int) $expectedBytes) {
            throw new RuntimeException(
                "Pinned workbook [{$workbookKey}] byte-size mismatch: expected {$expectedBytes}, found {$actualBytes}."
            );
        }
        if (! hash_equals($expectedHash, Str::lower($actualHash))) {
            throw new RuntimeException("Pinned workbook [{$workbookKey}] SHA-256 mismatch.");
        }

        return [
            'bytes' => $actualBytes,
            'sha256' => Str::lower($actualHash),
            'bytes_verified' => true,
            'sha256_verified' => true,
        ];
    }

    /**
     * @param  array<string, mixed>  $workbookDefinition
     * @param  array<string, mixed>  $sheetDefinition
     * @param  array<string, array{member_key: string, consortium_code: string, member_name: string}>  $ownerAliases
     * @param  array<int|string, mixed>  $globalNormalizations
     * @param  array<string, array<string, mixed>>  $explicitExclusions
     * @param  array<string, mixed>  $diagnostics
     * @return array{sheet: array<string, mixed>, records: array<int, array<string, mixed>>}
     */
    private function loadSheet(
        Worksheet $worksheet,
        string $workbookKey,
        string $relativePath,
        array $workbookDefinition,
        array $sheetDefinition,
        array $ownerAliases,
        array $globalNormalizations,
        array $explicitExclusions,
        array &$diagnostics,
        int &$sequence,
    ): array {
        $sections = $this->sections($sheetDefinition);
        $targetRows = [];
        $sectionDefinitions = [];
        $sheetMaximumColumn = 0;

        foreach ($sections as $sectionIndex => $section) {
            $headerRow = (int) ($section['header_row'] ?? 0);
            $subheaderRow = isset($section['subheader_row']) ? (int) $section['subheader_row'] : null;
            $rows = $this->dataRows($section['rows'] ?? []);
            if ($headerRow < 1 || $rows === []) {
                throw new RuntimeException(
                    "Sheet [{$worksheet->getTitle()}] section [{$sectionIndex}] has no valid header/data rows."
                );
            }
            $maximumColumn = $this->maximumColumnIndex($section['max_column'] ?? self::DEFAULT_MAX_COLUMN);
            $sheetMaximumColumn = max($sheetMaximumColumn, $maximumColumn);
            $targetRows[$headerRow] = true;
            if ($subheaderRow && $subheaderRow > 0) {
                $targetRows[$subheaderRow] = true;
            }
            foreach ($rows as $row) {
                $targetRows[$row] = true;
            }
            $sectionDefinitions[] = [
                ...$section,
                '_index' => $sectionIndex,
                '_header_row' => $headerRow,
                '_subheader_row' => $subheaderRow,
                '_rows' => $rows,
                '_maximum_column' => $maximumColumn,
            ];
        }

        $coordinateIndex = $this->sparseCoordinateIndex(
            $worksheet,
            array_keys($targetRows),
            max(1, $sheetMaximumColumn),
            $diagnostics,
            $workbookKey,
        );
        $mergeRanges = $this->mergeRanges($worksheet, array_keys($targetRows), max(1, $sheetMaximumColumn));
        $sourceRows = [];
        $loadedSections = [];
        $records = [];

        foreach ($sectionDefinitions as $section) {
            $headerRow = $section['_header_row'];
            $subheaderRow = $section['_subheader_row'];
            $maximumColumn = $section['_maximum_column'];
            $headerPayload = $this->rowPayload(
                $worksheet,
                $headerRow,
                $coordinateIndex[$headerRow] ?? [],
                [],
                $maximumColumn,
            );
            $columnMap = $this->columnMap(
                $headerPayload,
                $section['column_overrides'] ?? $sheetDefinition['column_overrides'] ?? [],
            );
            $requiredColumns = array_values(array_unique($columnMap));
            $subheaderPayload = $subheaderRow
                ? $this->rowPayload(
                    $worksheet,
                    $subheaderRow,
                    $coordinateIndex[$subheaderRow] ?? [],
                    [],
                    $maximumColumn,
                )
                : null;

            $sourceRows[$headerRow] = $this->mergePayloads($sourceRows[$headerRow] ?? null, $headerPayload);
            if ($subheaderPayload) {
                $sourceRows[$subheaderRow] = $this->mergePayloads(
                    $sourceRows[$subheaderRow] ?? null,
                    $subheaderPayload,
                );
            }

            $sectionRows = [];
            foreach ($section['_rows'] as $rowNumber) {
                $payload = $this->rowPayload(
                    $worksheet,
                    $rowNumber,
                    $coordinateIndex[$rowNumber] ?? [],
                    $requiredColumns,
                    $maximumColumn,
                );
                $sourceRows[$rowNumber] = $this->mergePayloads($sourceRows[$rowNumber] ?? null, $payload);
                $record = $this->record(
                    $workbookKey,
                    $relativePath,
                    $workbookDefinition,
                    $worksheet->getTitle(),
                    $sheetDefinition,
                    $section,
                    $rowNumber,
                    $headerPayload,
                    $subheaderPayload,
                    $payload,
                    $columnMap,
                    $mergeRanges,
                    $worksheet,
                    $ownerAliases,
                    $globalNormalizations,
                    $explicitExclusions,
                    $diagnostics,
                    ++$sequence,
                );
                $sectionRows[] = $rowNumber;
                $records[] = $record;
            }

            $loadedSections[] = [
                'index' => $section['_index'],
                'header_row' => $headerRow,
                'subheader_row' => $subheaderRow,
                'data_rows' => $sectionRows,
                'method_override' => $section['method_override'] ?? null,
                'milestones' => (bool) ($section['milestones'] ?? true),
                'max_column' => Coordinate::stringFromColumnIndex($maximumColumn),
                'column_map' => $columnMap,
                'header_payload' => $headerPayload,
                'subheader_payload' => $subheaderPayload,
                'notes' => $section['notes'] ?? null,
            ];
        }

        ksort($sourceRows, SORT_NUMERIC);

        return [
            'sheet' => [
                'title' => $worksheet->getTitle(),
                'sections' => $loadedSections,
                'source_rows' => $sourceRows,
            ],
            'records' => $records,
        ];
    }

    /**
     * @param  array<string, mixed>  $sheetDefinition
     * @return array<int, array<string, mixed>>
     */
    private function sections(array $sheetDefinition): array
    {
        $sections = $sheetDefinition['sections'] ?? null;
        if ($sections === null && isset($sheetDefinition['header_row'], $sheetDefinition['rows'])) {
            return [$sheetDefinition];
        }
        if (! is_array($sections)) {
            return [];
        }
        if (isset($sections['header_row'])) {
            return [$sections];
        }

        return array_values(array_filter($sections, 'is_array'));
    }

    /** @return array<int, int> */
    private function dataRows(mixed $definition): array
    {
        if (is_int($definition) || (is_string($definition) && ctype_digit($definition))) {
            return [(int) $definition];
        }
        if (is_string($definition)
            && preg_match('/^\s*(\d+)\s*(?:\.\.|:|-)\s*(\d+)\s*$/', $definition, $match)) {
            return $this->safeRange((int) $match[1], (int) $match[2]);
        }
        if (! is_array($definition)) {
            return [];
        }
        if (isset($definition['start'], $definition['end'])) {
            return $this->safeRange((int) $definition['start'], (int) $definition['end']);
        }
        if (isset($definition['values']) && is_array($definition['values'])) {
            return $this->explicitRows($definition['values']);
        }

        $values = array_values($definition);
        if (count($values) === 2
            && is_numeric($values[0])
            && is_numeric($values[1])) {
            return $this->safeRange((int) $values[0], (int) $values[1]);
        }

        return $this->explicitRows($values);
    }

    /** @param array<int, mixed> $values @return array<int, int> */
    private function explicitRows(array $values): array
    {
        $rows = [];
        foreach ($values as $value) {
            if (is_array($value)) {
                array_push($rows, ...$this->dataRows($value));
            } elseif (is_numeric($value) && (int) $value > 0) {
                $rows[] = (int) $value;
            }
        }
        $rows = array_values(array_unique($rows));
        sort($rows, SORT_NUMERIC);

        return $rows;
    }

    /** @return array<int, int> */
    private function safeRange(int $start, int $end): array
    {
        if ($start < 1 || $end < $start || $end - $start > 10_000) {
            throw new RuntimeException("Unsafe procurement manifest row range [{$start}:{$end}].");
        }

        return range($start, $end);
    }

    private function maximumColumnIndex(mixed $column): int
    {
        $column = Str::upper(trim((string) $column));
        if (! preg_match('/^[A-Z]{1,3}$/', $column)) {
            throw new RuntimeException("Invalid procurement manifest max_column [{$column}].");
        }

        return Coordinate::columnIndexFromString($column);
    }

    /**
     * @param  array<int, int|string>  $targetRows
     * @param  array<string, mixed>  $diagnostics
     * @return array<int, array<string, string>>
     */
    private function sparseCoordinateIndex(
        Worksheet $worksheet,
        array $targetRows,
        int $maximumColumn,
        array &$diagnostics,
        string $workbookKey,
    ): array {
        $wanted = array_fill_keys(array_map('intval', $targetRows), true);
        $index = [];
        $ignored = 0;
        $samples = [];

        foreach ($worksheet->getCellCollection()->getCoordinates() as $coordinate) {
            [$columnIndex, $row] = Coordinate::indexesFromString($coordinate);
            if (! isset($wanted[$row])) {
                continue;
            }
            if ($columnIndex > $maximumColumn) {
                $ignored++;
                if (count($samples) < 5) {
                    $samples[] = $coordinate;
                }

                continue;
            }
            $column = Coordinate::stringFromColumnIndex($columnIndex);
            $index[$row][$column] = $coordinate;
        }

        if ($ignored > 0) {
            $diagnostics['ignored_sparse_cells'][] = [
                'workbook' => $workbookKey,
                'sheet' => $worksheet->getTitle(),
                'count' => $ignored,
                'samples' => $samples,
                'reason' => 'Existing sparse cells exceeded the manifest section safety ceiling.',
            ];
        }

        return $index;
    }

    /**
     * @param  array<int, int|string>  $targetRows
     * @return array<int, array{start_column: int, start_row: int, end_column: int, end_row: int, anchor: string, range: string}>
     */
    private function mergeRanges(Worksheet $worksheet, array $targetRows, int $maximumColumn): array
    {
        $minimumRow = min(array_map('intval', $targetRows));
        $maximumRow = max(array_map('intval', $targetRows));
        $ranges = [];

        foreach (array_keys($worksheet->getMergeCells()) as $range) {
            try {
                [[$startColumn, $startRow], [$endColumn, $endRow]] = Coordinate::rangeBoundaries($range);
            } catch (Throwable) {
                continue;
            }
            if ($startColumn > $maximumColumn || $endRow < $minimumRow || $startRow > $maximumRow) {
                continue;
            }
            $ranges[] = [
                'start_column' => $startColumn,
                'start_row' => $startRow,
                'end_column' => min($endColumn, $maximumColumn),
                'end_row' => $endRow,
                'anchor' => Coordinate::stringFromColumnIndex($startColumn).$startRow,
                'range' => $range,
            ];
        }

        return $ranges;
    }

    /**
     * @param  array<string, string>  $coordinates
     * @param  array<int, string>  $requiredColumns
     * @return array{row_number: int, cells: array<string, array<string, mixed>>}
     */
    private function rowPayload(
        Worksheet $worksheet,
        int $row,
        array $coordinates,
        array $requiredColumns,
        int $maximumColumn,
    ): array {
        $columns = [];
        foreach (array_keys($coordinates) as $column) {
            if (Coordinate::columnIndexFromString($column) <= $maximumColumn) {
                $columns[$column] = true;
            }
        }
        foreach ($requiredColumns as $column) {
            $column = Str::upper(trim($column));
            if (preg_match('/^[A-Z]{1,3}$/', $column)
                && Coordinate::columnIndexFromString($column) <= $maximumColumn) {
                $columns[$column] = true;
            }
        }
        $columns = array_keys($columns);
        usort($columns, static fn (string $left, string $right): int => Coordinate::columnIndexFromString($left) <=> Coordinate::columnIndexFromString($right)
        );

        $cells = [];
        foreach ($columns as $column) {
            $cells[$column] = $this->cellPayload($worksheet, $column.$row);
        }

        return ['row_number' => $row, 'cells' => $cells];
    }

    /** @return array<string, mixed> */
    private function cellPayload(Worksheet $worksheet, string $coordinate): array
    {
        $cell = $worksheet->getCell($coordinate);
        $comment = $worksheet->getComments()[$coordinate] ?? null;
        $commentPayload = null;
        if ($comment !== null) {
            $rawComment = trim($comment->getText()->getPlainText());
            $commentText = $rawComment;
            $commentType = 'note';
            if (str_starts_with($rawComment, '[Threaded comment]')
                && preg_match('/(?:^|\R)Comment:\R(?<text>.*)\z/su', $rawComment, $matches) === 1) {
                $commentText = trim((string) preg_replace('/^[ \t]{4}/m', '', $matches['text']));
                $commentType = 'threaded';
            }
            $commentPayload = [
                'type' => $commentType,
                'author' => $comment->getAuthor(),
                'text' => $commentText,
                'raw_text' => $rawComment,
                'visible' => $comment->getVisible(),
            ];
        }
        $raw = $this->scalarCellValue($cell->getValue());
        $formula = is_string($raw) && str_starts_with(ltrim($raw), '=') ? $raw : null;
        $cached = null;
        if ($formula !== null) {
            $cached = $this->scalarCellValue($cell->getOldCalculatedValue());
            if ($cached === null) {
                throw new RuntimeException(
                    "Formula cell [{$worksheet->getTitle()}!{$coordinate}] has no cached Excel value; the audited migration will not recalculate it silently."
                );
            }
            try {
                $formatted = $this->scalarCellValue(NumberFormat::toFormattedString(
                    $cached,
                    $cell->getStyle()->getNumberFormat()->getFormatCode(),
                ));
            } catch (Throwable) {
                $formatted = $cached;
            }
        } else {
            try {
                $formatted = $this->scalarCellValue($cell->getFormattedValue());
            } catch (Throwable) {
                $formatted = $raw;
            }
        }

        return [
            'coordinate' => $coordinate,
            'raw' => $raw,
            'formatted' => $formatted,
            'formula' => $formula,
            'cached' => $cached,
            'data_type' => $cell->getDataType(),
            'comment' => $commentPayload,
        ];
    }

    private function scalarCellValue(mixed $value): mixed
    {
        if (is_scalar($value) || $value === null) {
            return $value;
        }
        if (method_exists($value, 'getPlainText')) {
            return $value->getPlainText();
        }

        return (string) $value;
    }

    /**
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}|null  $existing
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $incoming
     * @return array{row_number: int, cells: array<string, array<string, mixed>>}
     */
    private function mergePayloads(?array $existing, array $incoming): array
    {
        if ($existing === null) {
            return $incoming;
        }
        $existing['cells'] = [...$existing['cells'], ...$incoming['cells']];
        uksort($existing['cells'], static fn (string $left, string $right): int => Coordinate::columnIndexFromString($left) <=> Coordinate::columnIndexFromString($right)
        );

        return $existing;
    }

    /**
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $headerPayload
     * @return array<string, string>
     */
    private function columnMap(array $headerPayload, mixed $overrides): array
    {
        $map = [];
        foreach ($headerPayload['cells'] as $column => $payload) {
            $label = $this->normalizeHeader((string) ($payload['formatted'] ?? ''));
            if ($label === '') {
                continue;
            }
            $field = $this->fieldForHeader($label);
            if ($field !== null && ! isset($map[$field])) {
                $map[$field] = $column;
            }
        }

        foreach ($this->normalizedColumnOverrides($overrides) as $field => $column) {
            $map[$field] = $column;
        }

        return $map;
    }

    private function normalizeHeader(string $value): string
    {
        $value = Str::lower(str_replace(['&', '$'], [' and ', ' '], $value));

        return trim((string) preg_replace('/[^a-z0-9]+/u', ' ', $value));
    }

    private function fieldForHeader(string $label): ?string
    {
        foreach (self::HEADER_ALIASES as $field => $aliases) {
            if (in_array($label, $aliases, true)) {
                return $field;
            }
        }

        return match (true) {
            str_contains($label, 'activity') && str_contains($label, 'description') => 'activity',
            str_contains($label, 'estimated') && str_contains($label, 'amount') => 'estimated_amount',
            str_contains($label, 'bank') && str_contains($label, 'comment') => 'bank_comment',
            str_contains($label, 'auc') && str_contains($label, 'comment') => 'action_taken',
            default => null,
        };
    }

    /** @return array<string, string> */
    private function normalizedColumnOverrides(mixed $overrides): array
    {
        if (! is_array($overrides)) {
            return [];
        }
        $normalized = [];
        foreach ($overrides as $key => $value) {
            $field = null;
            $column = null;
            if (is_string($key) && preg_match('/^[A-Z]{1,3}$/i', $key) && is_string($value)) {
                $column = $key;
                $field = $value;
            } elseif (is_string($key) && is_string($value) && preg_match('/^[A-Z]{1,3}$/i', $value)) {
                $field = $key;
                $column = $value;
            } elseif (is_array($value)) {
                $field = (string) ($value['field'] ?? (is_string($key) ? $key : ''));
                $column = (string) ($value['column'] ?? '');
            }
            $field = $this->canonicalFieldName((string) $field);
            $column = Str::upper(trim((string) $column));
            if ($field !== '' && preg_match('/^[A-Z]{1,3}$/', $column)) {
                $normalized[$field] = $column;
            }
        }

        return $normalized;
    }

    private function canonicalFieldName(string $field): string
    {
        $field = trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower($field)), '_');

        return match ($field) {
            'auc_comment', 'auc_action', 'response', 'response_action' => 'action_taken',
            'bank_comments', 'world_bank_comment' => 'bank_comment',
            'amount', 'amount_usd', 'estimated_amount_usd' => 'estimated_amount',
            'description', 'activity_description', 'activity_reference_description' => 'activity',
            'reference', 'reference_no', 'reference_number' => 'source_reference',
            default => $field,
        };
    }

    /**
     * @param  array<string, mixed>  $workbookDefinition
     * @param  array<string, mixed>  $sheetDefinition
     * @param  array<string, mixed>  $section
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $headerPayload
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}|null  $subheaderPayload
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $rowPayload
     * @param  array<string, string>  $columnMap
     * @param  array<int, array<string, mixed>>  $mergeRanges
     * @param  array<string, array{member_key: string, consortium_code: string, member_name: string}>  $ownerAliases
     * @param  array<int|string, mixed>  $globalNormalizations
     * @param  array<string, array<string, mixed>>  $explicitExclusions
     * @param  array<string, mixed>  $diagnostics
     * @return array<string, mixed>
     */
    private function record(
        string $workbookKey,
        string $relativePath,
        array $workbookDefinition,
        string $sheetName,
        array $sheetDefinition,
        array $section,
        int $rowNumber,
        array $headerPayload,
        ?array $subheaderPayload,
        array $rowPayload,
        array $columnMap,
        array $mergeRanges,
        Worksheet $worksheet,
        array $ownerAliases,
        array $globalNormalizations,
        array $explicitExclusions,
        array &$diagnostics,
        int $sequence,
    ): array {
        $sourceFields = [];
        $fieldPayloads = [];
        $fieldProvenance = [];
        $normalizations = [];

        foreach ($columnMap as $field => $column) {
            $physical = $rowPayload['cells'][$column]
                ?? $this->cellPayload($worksheet, $column.$rowNumber);
            $resolved = $this->resolvedMergedPayload(
                $worksheet,
                $physical,
                $column,
                $rowNumber,
                $mergeRanges,
            );
            $fieldPayloads[$field] = $resolved;
            $sourceFields[$field] = $resolved['formatted'] ?? null;
            $fieldProvenance[$field] = [
                'column' => $column,
                'coordinate' => $column.$rowNumber,
                'inherited_from' => $resolved['inherited_from'] ?? null,
            ];
            if (isset($resolved['inherited_from'])) {
                $normalizations[] = [
                    'type' => 'merged_cell_inheritance',
                    'field' => $field,
                    'from' => $physical['formatted'] ?? null,
                    'to' => $resolved['formatted'] ?? null,
                    'note' => "Inherited from merged-cell anchor {$resolved['inherited_from']}.",
                ];
            }
        }

        $activity = (string) ($sourceFields['activity'] ?? $sourceFields['title'] ?? '');
        $explicitReference = isset($sourceFields['source_reference'])
            ? (string) $sourceFields['source_reference']
            : null;
        $separateTitle = isset($sourceFields['title']) ? (string) $sourceFields['title'] : null;
        $activityParts = $this->activityParts($activity, $explicitReference, $separateTitle);
        array_push($normalizations, ...$activityParts['normalizations']);
        $owner = $this->resolveOwner($activityParts['title_raw'].' '.$activity, $ownerAliases);
        $ownerOverride = $this->matchingOwnerOverride(
            is_array($workbookDefinition['row_owner_overrides'] ?? null)
                ? $workbookDefinition['row_owner_overrides']
                : [],
            $workbookKey,
            $sheetName,
            $rowNumber,
            $activityParts['source_reference'],
        );
        if ($ownerOverride !== null) {
            $overriddenOwner = $this->ownerForMemberKey($ownerOverride['owner_key'], $ownerAliases);
            if ($overriddenOwner !== null) {
                $before = $owner['member_key'];
                $overriddenOwner['source_consortium_code'] = $owner['source_consortium_code'];
                $owner = $overriddenOwner;
                $normalizations[] = [
                    'type' => 'manifest_owner_override',
                    'field' => 'member_key',
                    'from' => $before,
                    'to' => $owner['member_key'],
                    'note' => $ownerOverride['note'],
                ];
            }
        }
        $title = $this->cleanTitle($activityParts['title_raw'], $owner['owner_alias']);
        if ($title !== trim($activityParts['title_raw'])) {
            $normalizations[] = [
                'type' => 'organizational_prefix_removed',
                'field' => 'title',
                'from' => $activityParts['title_raw'],
                'to' => $title,
                'note' => 'Removed consortium and Think Tank routing prefixes from the display title.',
            ];
        }
        $displayTitle = Str::limit($title, 255, '');
        if ($displayTitle !== $title) {
            $normalizations[] = [
                'type' => 'display_title_limit',
                'field' => 'display_title',
                'from_length' => mb_strlen($title),
                'to_length' => mb_strlen($displayTitle),
                'note' => 'Created a UTF-8-safe 255-character display title; full title remains in title/description/raw payload.',
            ];
        }

        $values = $sourceFields;
        $manifestNormalizations = $this->matchingNormalizations(
            [
                ...$globalNormalizations,
                ...(is_array($workbookDefinition['normalizations'] ?? null) ? $workbookDefinition['normalizations'] : []),
                ...(is_array($sheetDefinition['normalizations'] ?? null) ? $sheetDefinition['normalizations'] : []),
                ...(is_array($section['normalizations'] ?? null) ? $section['normalizations'] : []),
            ],
            $workbookKey,
            $relativePath,
            $sheetName,
            $rowNumber,
            $activityParts['source_reference'],
            $owner['member_key'],
        );
        $recordMethodOverride = null;
        foreach ($manifestNormalizations as $manifestNormalization) {
            foreach ($manifestNormalization['set'] as $field => $value) {
                $field = $this->canonicalFieldName($field);
                if ($field === 'canonical_reference') {
                    $before = $activityParts['source_reference'];
                    $parsedOverride = $this->canonicalReference((string) $value);
                    $activityParts['source_reference'] = $parsedOverride['canonical'] ?? trim((string) $value);
                    $activityParts['reference_parts'] = [
                        'number' => $parsedOverride['number'],
                        'category' => $parsedOverride['category'],
                        'method' => $parsedOverride['method'],
                    ];
                } else {
                    $before = $values[$field] ?? null;
                    $values[$field] = $value;
                }
                if ($field === 'procurement_method') {
                    $recordMethodOverride = $value;
                }
                $normalizations[] = [
                    'type' => 'manifest_'.$manifestNormalization['rule_type'],
                    'field' => $field,
                    'from' => $before,
                    'to' => $value,
                    'note' => $manifestNormalization['note'],
                ];
            }
        }

        $sourceInProcess = $this->controlledYesNo($values['source_in_process'] ?? null);
        $this->noteControlledNormalization($normalizations, 'source_in_process', $values['source_in_process'] ?? null, $sourceInProcess);
        $reviewType = $this->controlledReviewType($values['review_type'] ?? null);
        $this->noteControlledNormalization($normalizations, 'review_type', $values['review_type'] ?? null, $reviewType);
        $category = $this->controlledCategory($values['procurement_category'] ?? null);
        $this->noteControlledNormalization($normalizations, 'procurement_category', $values['procurement_category'] ?? null, $category);
        $marketApproach = $this->controlledMarketApproach($values['market_approach'] ?? null);
        $this->noteControlledNormalization($normalizations, 'market_approach', $values['market_approach'] ?? null, $marketApproach);
        $seaShRisk = $this->controlledSeaShRisk($values['source_sea_sh_risk'] ?? null);
        $this->noteControlledNormalization($normalizations, 'source_sea_sh_risk', $values['source_sea_sh_risk'] ?? null, $seaShRisk);

        $amount = $this->money($values['estimated_amount'] ?? null);
        $currency = $this->currency(
            $values['currency'] ?? null,
            $columnMap['estimated_amount'] ?? null,
            $headerPayload,
        );
        if ($currency['source'] === 'amount_header') {
            $normalizations[] = [
                'type' => 'currency_inferred',
                'field' => 'currency',
                'from' => $values['currency'] ?? null,
                'to' => 'USD',
                'note' => 'Inferred USD from the Estimated Amount (US$ / USD) header.',
            ];
        }
        $thresholdBand = $currency['value'] === 'USD' && $amount !== null
            ? ($amount['minor'] < self::THRESHOLD_MINOR_USD ? self::BAND_BELOW : self::BAND_AT_OR_ABOVE)
            : self::BAND_CURRENCY_REVIEW;

        $method = $this->method(
            $recordMethodOverride ?? $section['method_override'] ?? null,
            $activityParts['reference_parts']['method'] ?? null,
            $values['procurement_method'] ?? null,
            $sheetName,
        );
        if ($recordMethodOverride !== null) {
            $method['source'] = 'manifest_record_override';
        }
        if ($method['input'] !== null && trim((string) $method['input']) !== $method['label']) {
            $normalizations[] = [
                'type' => 'controlled_value',
                'field' => 'procurement_method',
                'from' => $method['input'],
                'to' => $method['label'],
                'note' => 'Normalized to the canonical procurement method label.',
            ];
        }
        $milestones = (bool) ($section['milestones'] ?? true)
            ? $this->milestones($headerPayload, $subheaderPayload, $rowPayload)
            : [];
        $timelineAnomalies = $this->timelineAnomalies($milestones);
        foreach ($timelineAnomalies as $anomaly) {
            $diagnostics['timeline_anomalies'][] = [
                ...$anomaly,
                'reference' => $activityParts['source_reference'],
                'workbook' => $workbookKey,
                'sheet' => $sheetName,
                'row' => $rowNumber,
            ];
        }

        $plannedDates = array_values(array_filter(array_map(
            static fn (array $milestone): ?string => $milestone['timing'] === 'planned'
                ? $milestone['date']
                : null,
            $milestones,
        )));

        $exclusionReason = null;
        if ($owner['member_key'] === null) {
            $exclusionReason = 'No unique Think Tank owner alias could be resolved.';
        }
        $exclusionReasonCode = $owner['member_key'] === null ? 'unresolved_owner' : null;
        if (isset($explicitExclusions[$activityParts['source_reference']])) {
            $exclusion = $explicitExclusions[$activityParts['source_reference']];
            $exclusionReason = $exclusion['reason'];
            $exclusionReasonCode = $exclusion['reason_code'];
        }
        if (($section['exclude'] ?? false) === true) {
            $exclusionReason = trim((string) ($section['exclusion_reason'] ?? 'Excluded by manifest section policy.'));
            $exclusionReasonCode = trim((string) ($section['exclusion_reason_code'] ?? 'manifest_section_policy'));
        }

        $provenance = [
            'workbook_key' => $workbookKey,
            'source_path' => $relativePath,
            'source_file' => basename(str_replace('\\', '/', $relativePath)),
            'source_sheet' => $sheetName,
            'source_row' => $rowNumber,
            'source_priority' => (int) ($workbookDefinition['source_priority'] ?? 0),
            'section_index' => $section['_index'] ?? null,
        ];
        $reviewFlags = $this->matchingReviewFlags(
            $workbookKey,
            $sheetName,
            $rowNumber,
            $activityParts['source_reference'],
            $owner['member_key'],
            $values['source_activity_status'] ?? null,
        );
        foreach ($reviewFlags as $reviewFlag) {
            $diagnostics['review_required'][] = [
                ...$reviewFlag,
                'provenance' => $provenance,
            ];
        }

        $record = [
            'record_key' => implode('|', [$workbookKey, $sheetName, $rowNumber]),
            'source_reference_raw' => $activityParts['source_reference_raw'],
            'source_reference' => $activityParts['source_reference'],
            'reference_parts' => $activityParts['reference_parts'],
            'activity_raw' => $activity,
            'title_raw' => $activityParts['title_raw'],
            'title' => $title,
            'display_title' => $displayTitle,
            'description' => $activityParts['title_raw'],
            'owner_alias' => $owner['owner_alias'],
            'owner_alias_matches' => $owner['matches'],
            'member_key' => $owner['member_key'],
            'member_name' => $owner['member_name'],
            'consortium_code' => $owner['consortium_code'],
            'source_consortium_code' => $owner['source_consortium_code'],
            'fiscal_year' => isset($workbookDefinition['fiscal_year'])
                ? (string) $workbookDefinition['fiscal_year']
                : null,
            'source_in_process' => $sourceInProcess,
            'loan_credit_no' => $this->nullableString($values['loan_credit_no'] ?? null),
            'component' => $this->nullableString($values['component'] ?? null),
            'review_type' => $reviewType,
            'procurement_category' => $category,
            'procurement_category_raw' => $values['procurement_category'] ?? null,
            'procurement_method' => $method['label'],
            'procurement_method_code' => $method['code'],
            'procurement_method_source' => $method['source'],
            'market_approach' => $marketApproach,
            'market_approach_raw' => $values['market_approach'] ?? null,
            'quantity' => $this->nullableString($values['quantity'] ?? null),
            'unit' => $this->nullableString($values['unit'] ?? null),
            'estimated_unit_cost' => $this->nullableString($values['estimated_unit_cost'] ?? null),
            'estimated_amount' => $amount['decimal'] ?? null,
            'estimated_amount_minor' => $amount['minor'] ?? null,
            'currency' => $currency['value'],
            'currency_source' => $currency['source'],
            'threshold_band' => $thresholdBand,
            'source_process_status' => $values['source_process_status'] ?? null,
            'source_activity_status' => $values['source_activity_status'] ?? null,
            'workflow_status' => $this->workflowStatus($values['source_activity_status'] ?? null),
            'source_document_type' => $this->nullableString($values['source_document_type'] ?? null),
            'source_sea_sh_risk' => $seaShRisk,
            'source_prequalification' => $this->nullableString($values['source_prequalification'] ?? null),
            'source_procurement_process' => $this->nullableString($values['source_procurement_process'] ?? null),
            'source_evaluation_options' => $this->nullableString($values['source_evaluation_options'] ?? null),
            'budget_reference' => $this->nullableString($values['budget_reference'] ?? null),
            'limited_selection_justification' => $this->nullableString($values['limited_selection_justification'] ?? null),
            'bank_comment' => $this->nullableString($values['bank_comment'] ?? null),
            'action_taken' => $this->nullableString($values['action_taken'] ?? null),
            'planned_quarter' => $this->nullableString($values['planned_quarter'] ?? null),
            'planned_start_date' => $plannedDates[0] ?? $this->date($values['planned_start_date'] ?? null),
            'planned_end_date' => $plannedDates !== []
                ? $plannedDates[array_key_last($plannedDates)]
                : $this->date($values['planned_end_date'] ?? null),
            'planned_milestones' => $milestones,
            'timeline_anomalies' => $timelineAnomalies,
            'review_flags' => $reviewFlags,
            'is_seedable' => $exclusionReason === null,
            'exclusion_reason' => $exclusionReason,
            'exclusion_reason_code' => $exclusionReasonCode,
            'source_fields' => $sourceFields,
            'source_field_payloads' => $fieldPayloads,
            'source_field_provenance' => $fieldProvenance,
            'row_payload' => $rowPayload,
            'header_payload' => $headerPayload,
            'subheader_payload' => $subheaderPayload,
            'normalizations' => $normalizations,
            'provenance' => $provenance,
            '_sequence' => $sequence,
        ];

        foreach ($normalizations as $normalization) {
            $diagnostics['normalizations'][] = [
                ...$normalization,
                'reference' => $activityParts['source_reference'],
                'workbook' => $workbookKey,
                'sheet' => $sheetName,
                'row' => $rowNumber,
            ];
        }
        if ($activityParts['source_reference'] === null) {
            $diagnostics['warnings'][] = [
                'code' => 'unresolved_reference',
                'message' => 'No procurement reference could be canonicalized.',
                'provenance' => $provenance,
            ];
        }
        if (count($owner['matches']) !== 1) {
            $diagnostics['warnings'][] = [
                'code' => $owner['matches'] === [] ? 'unresolved_owner' : 'ambiguous_owner',
                'message' => $owner['matches'] === []
                    ? 'No Think Tank owner alias was found.'
                    : 'More than one Think Tank owner alias was found.',
                'matches' => $owner['matches'],
                'provenance' => $provenance,
            ];
        }
        if ($reviewType === null) {
            $diagnostics['warnings'][] = [
                'code' => 'invalid_review_type',
                'message' => 'Review Type is not a controlled Prior/Post value.',
                'provenance' => $provenance,
            ];
        }
        if ($marketApproach === null) {
            $diagnostics['warnings'][] = [
                'code' => 'invalid_market_approach',
                'message' => 'Market Approach could not be normalized to a portal value.',
                'provenance' => $provenance,
            ];
        }
        if ($amount === null) {
            $diagnostics['warnings'][] = [
                'code' => 'invalid_amount',
                'message' => 'Estimated amount is blank or not numeric.',
                'provenance' => $provenance,
            ];
        }

        return $record;
    }

    /**
     * @param  array<string, mixed>  $physical
     * @param  array<int, array<string, mixed>>  $mergeRanges
     * @return array<string, mixed>
     */
    private function resolvedMergedPayload(
        Worksheet $worksheet,
        array $physical,
        string $column,
        int $row,
        array $mergeRanges,
    ): array {
        if (! $this->blank($physical['formatted'] ?? null)) {
            return $physical;
        }
        $columnIndex = Coordinate::columnIndexFromString($column);
        foreach ($mergeRanges as $range) {
            if ($columnIndex < $range['start_column'] || $columnIndex > $range['end_column']
                || $row < $range['start_row'] || $row > $range['end_row']) {
                continue;
            }
            if ($physical['coordinate'] === $range['anchor']) {
                return $physical;
            }
            $anchor = $this->cellPayload($worksheet, $range['anchor']);
            if ($this->blank($anchor['formatted'] ?? null)) {
                return $physical;
            }

            return [
                ...$anchor,
                'coordinate' => $physical['coordinate'],
                'physical' => $physical,
                'inherited_from' => $range['anchor'],
                'merged_range' => $range['range'],
            ];
        }

        return $physical;
    }

    /** @return array{source_reference_raw: ?string, source_reference: ?string, reference_parts: array<string, ?string>, title_raw: string, normalizations: array<int, array<string, mixed>>} */
    private function activityParts(string $activity, ?string $explicitReference, ?string $separateTitle): array
    {
        $normalizations = [];
        $referenceRaw = $explicitReference !== null && trim($explicitReference) !== ''
            ? trim($explicitReference)
            : null;
        $titleRaw = $separateTitle !== null && trim($separateTitle) !== ''
            ? trim($separateTitle)
            : '';

        if ($referenceRaw === null && str_contains($activity, '/')) {
            [$before, $after] = explode('/', $activity, 2);
            $referenceRaw = trim($before);
            if ($titleRaw === '') {
                $titleRaw = trim($after);
            }
        }

        $parsed = $this->canonicalReference($referenceRaw ?? $activity);
        if ($referenceRaw === null && $parsed['matched'] !== null) {
            $referenceRaw = $parsed['matched'];
        }
        if ($titleRaw === '') {
            if ($parsed['offset'] !== null && $parsed['length'] !== null) {
                $titleRaw = trim(substr($activity, $parsed['offset'] + $parsed['length']));
            } else {
                $titleRaw = trim($activity);
            }
        }
        $titleRaw = trim((string) preg_replace('/^[\s\-\x{2010}-\x{2015}_:;]+/u', '', $titleRaw));

        if ($parsed['canonical'] !== null && $referenceRaw !== $parsed['canonical']) {
            $normalizations[] = [
                'type' => 'reference_canonicalization',
                'field' => 'source_reference',
                'from' => $referenceRaw,
                'to' => $parsed['canonical'],
                'note' => 'Canonicalized spacing, missing ET prefix character, separators, and known reference segments without inventing absent segments.',
            ];
        }

        return [
            'source_reference_raw' => $referenceRaw,
            'source_reference' => $parsed['canonical'],
            'reference_parts' => [
                'number' => $parsed['number'],
                'category' => $parsed['category'],
                'method' => $parsed['method'],
            ],
            'title_raw' => $titleRaw,
            'normalizations' => $normalizations,
        ];
    }

    /** @return array{canonical: ?string, matched: ?string, number: ?string, category: ?string, method: ?string, offset: ?int, length: ?int} */
    private function canonicalReference(string $value): array
    {
        $value = str_replace(["\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2212}"], '-', $value);
        $pattern = '/(?<![A-Z0-9])(?:(?:E\s*T)|T)\s*-\s*AUC\s*-\s*(\d+)'.
            '(?:\s*(?:-+|\s+)\s*(GO|CS|NC|CW))?'.
            '(?:\s*(?:-+|\s+)\s*(RFB|RFQ|DIR|LCS|FBS|QCBS|CQS|CDS|INDV))?/iu';
        if (! preg_match($pattern, $value, $match, PREG_OFFSET_CAPTURE)) {
            return [
                'canonical' => null,
                'matched' => null,
                'number' => null,
                'category' => null,
                'method' => null,
                'offset' => null,
                'length' => null,
            ];
        }

        $matched = $match[0][0];
        $number = $match[1][0];
        $category = isset($match[2][0]) && $match[2][0] !== '' ? Str::upper($match[2][0]) : null;
        $method = isset($match[3][0]) && $match[3][0] !== '' ? Str::upper($match[3][0]) : null;
        $canonical = 'ET-AUC-'.$number;
        if ($category !== null) {
            $canonical .= '-'.$category;
        }
        if ($method !== null) {
            $canonical .= '-'.$method;
        }

        return [
            'canonical' => $canonical,
            'matched' => $matched,
            'number' => $number,
            'category' => $category,
            'method' => $method,
            'offset' => $match[0][1],
            'length' => strlen($matched),
        ];
    }

    /**
     * @param  array<string, array{member_key: string, consortium_code: string, member_name: string}>  $ownerAliases
     * @return array{owner_alias: ?string, member_key: ?string, member_name: ?string, consortium_code: ?string, source_consortium_code: ?string, matches: array<int, string>}
     */
    private function resolveOwner(string $text, array $ownerAliases): array
    {
        $search = ' '.$this->searchText($text).' ';
        $matchedAliases = [];
        $matches = [];
        foreach ($ownerAliases as $alias => $definition) {
            $needle = ' '.$this->searchText($alias).' ';
            if (str_contains($search, $needle)) {
                $matchedAliases[$definition['member_key']][] = $alias;
                $matches[$definition['member_key']] = $definition;
            }
        }
        $sourceConsortium = null;
        foreach (self::CONSORTIUM_ALIASES as $alias => $code) {
            if (str_contains($search, ' '.$this->searchText($alias).' ')) {
                $sourceConsortium = $code;
                break;
            }
        }

        if (count($matches) !== 1) {
            return [
                'owner_alias' => null,
                'member_key' => null,
                'member_name' => null,
                'consortium_code' => $sourceConsortium,
                'source_consortium_code' => $sourceConsortium,
                'matches' => array_keys($matches),
            ];
        }

        $memberKey = array_key_first($matches);
        $definition = $matches[$memberKey];
        $aliases = $matchedAliases[$memberKey];
        usort($aliases, static fn (string $left, string $right): int => strlen($left) <=> strlen($right));
        $alias = $aliases[0];

        return [
            'owner_alias' => $alias,
            'member_key' => $definition['member_key'],
            'member_name' => $definition['member_name'],
            'consortium_code' => $definition['consortium_code'],
            'source_consortium_code' => $sourceConsortium,
            'matches' => [$memberKey],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $overrides
     * @return array{owner_key: string, note: string}|null
     */
    private function matchingOwnerOverride(
        array $overrides,
        string $workbook,
        string $sheet,
        int $row,
        ?string $reference,
    ): ?array {
        foreach ($overrides as $override) {
            if (! is_array($override)) {
                continue;
            }
            if (isset($override['workbook']) && (string) $override['workbook'] !== $workbook) {
                continue;
            }
            if (isset($override['sheet']) && (string) $override['sheet'] !== $sheet) {
                continue;
            }
            if (isset($override['row']) && (int) $override['row'] !== $row) {
                continue;
            }
            if (isset($override['reference'])) {
                $expected = $this->canonicalReference((string) $override['reference'])['canonical']
                    ?? trim((string) $override['reference']);
                if ($expected !== $reference) {
                    continue;
                }
            }
            $ownerKey = trim((string) ($override['owner_key'] ?? ''));
            if ($ownerKey !== '') {
                return [
                    'owner_key' => $ownerKey,
                    'note' => trim((string) ($override['notes'] ?? $override['note'] ?? 'Explicit workbook owner override.')),
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, array{member_key: string, consortium_code: string, member_name: string}>  $ownerAliases
     * @return array{owner_alias: string, member_key: string, member_name: string, consortium_code: string, source_consortium_code: null, matches: array<int, string>}|null
     */
    private function ownerForMemberKey(string $memberKey, array $ownerAliases): ?array
    {
        $aliases = [];
        $definition = null;
        foreach ($ownerAliases as $alias => $candidate) {
            if ($candidate['member_key'] !== $memberKey) {
                continue;
            }
            $aliases[] = $alias;
            $definition = $candidate;
        }
        if ($definition === null) {
            return null;
        }
        usort($aliases, static fn (string $left, string $right): int => strlen($left) <=> strlen($right));

        return [
            'owner_alias' => $aliases[0],
            'member_key' => $definition['member_key'],
            'member_name' => $definition['member_name'],
            'consortium_code' => $definition['consortium_code'],
            'source_consortium_code' => null,
            'matches' => [$definition['member_key']],
        ];
    }

    private function searchText(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/u', ' ', Str::lower($value)));
    }

    private function cleanTitle(string $title, ?string $ownerAlias): string
    {
        $title = str_replace("\u{00A0}", ' ', $title);
        $separator = '[\s\-\x{2010}-\x{2015}_:;]*';
        $consortia = array_keys(self::CONSORTIUM_ALIASES);
        usort($consortia, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($consortia as $alias) {
            $pattern = '/^'.preg_quote($alias, '/').'(?=$|[\s\-\x{2010}-\x{2015}_:;])'.$separator.'/iu';
            if (preg_match($pattern, $title)) {
                $title = (string) preg_replace($pattern, '', $title, 1);
                break;
            }
        }
        if ($ownerAlias !== null) {
            $pattern = '/^'.preg_quote($ownerAlias, '/').'(?=$|[\s\-\x{2010}-\x{2015}_:;])'.$separator.'/iu';
            $title = (string) preg_replace($pattern, '', $title, 1);
        }
        $title = trim((string) preg_replace('/^[\s\-\x{2010}-\x{2015}_:;]+/u', '', $title));

        return trim((string) preg_replace('/\s+/u', ' ', $title));
    }

    /**
     * @param  array<int|string, mixed>  $normalizations
     * @return array<int, array{set: array<string, mixed>, note: string}>
     */
    private function matchingNormalizations(
        array $normalizations,
        string $workbook,
        string $path,
        string $sheet,
        int $row,
        ?string $reference,
        ?string $memberKey,
    ): array {
        $matches = [];
        foreach ($normalizations as $key => $definition) {
            if (! is_array($definition)) {
                continue;
            }
            if (! array_is_list($normalizations) && (is_int($key) || ctype_digit((string) $key))) {
                $definition = ['row' => (int) $key, 'set' => $definition];
            } elseif (! array_is_list($normalizations) && is_string($key) && str_starts_with(Str::upper($key), 'ET')) {
                $definition = ['reference' => $key, 'set' => $definition];
            }

            $expectedPath = trim((string) ($definition['path'] ?? ''));
            if ($expectedPath !== '' && $this->normalizedPath($expectedPath) !== $this->normalizedPath($path)) {
                continue;
            }
            if (isset($definition['workbook']) && (string) $definition['workbook'] !== $workbook) {
                continue;
            }
            if (isset($definition['sheet']) && (string) $definition['sheet'] !== $sheet) {
                continue;
            }
            if (isset($definition['row']) && (int) $definition['row'] !== $row) {
                continue;
            }
            if (isset($definition['reference'])) {
                $expected = $this->canonicalReference((string) $definition['reference'])['canonical']
                    ?? trim((string) $definition['reference']);
                if ($expected !== $reference) {
                    continue;
                }
            }
            if (isset($definition['owner_key']) && (string) $definition['owner_key'] !== (string) $memberKey) {
                continue;
            }

            $set = $definition['set'] ?? $definition['fields'] ?? null;
            if (! is_array($set) && isset($definition['field'])) {
                $set = [(string) $definition['field'] => $definition['value'] ?? null];
            }
            if (! is_array($set)) {
                continue;
            }
            $matches[] = [
                'set' => $set,
                'note' => trim((string) ($definition['audit_note'] ?? $definition['notes'] ?? $definition['note'] ?? 'Explicit workbook manifest normalization.')),
                'rule_type' => (string) ($definition['_rule_type'] ?? 'normalization'),
            ];
        }

        return $matches;
    }

    private function normalizedPath(string $path): string
    {
        return Str::lower(str_replace('\\', '/', trim($path)));
    }

    /** @return array<int, array<string, mixed>> */
    private function matchingReviewFlags(
        string $workbook,
        string $sheet,
        int $row,
        ?string $reference,
        ?string $memberKey,
        mixed $sourceActivityStatus,
    ): array {
        $matches = [];
        foreach ($this->reviewRequirements as $definition) {
            if (isset($definition['workbook']) && (string) $definition['workbook'] !== $workbook) {
                continue;
            }
            if (isset($definition['sheet']) && (string) $definition['sheet'] !== $sheet) {
                continue;
            }
            if (isset($definition['owner_key']) && (string) $definition['owner_key'] !== (string) $memberKey) {
                continue;
            }
            if (isset($definition['source_activity_status'])
                && trim((string) $definition['source_activity_status']) !== trim((string) $sourceActivityStatus)) {
                continue;
            }
            if (isset($definition['row']) && (int) $definition['row'] !== $row) {
                continue;
            }
            if (isset($definition['rows']) && ! in_array($row, $this->dataRows($definition['rows']), true)) {
                continue;
            }
            if (isset($definition['reference'])) {
                $expected = $this->canonicalReference((string) $definition['reference'])['canonical']
                    ?? trim((string) $definition['reference']);
                if ($expected !== $reference) {
                    continue;
                }
            }

            $matchedReferenceRule = null;
            if (isset($definition['references']) && is_array($definition['references'])) {
                foreach ($definition['references'] as $referenceRule) {
                    if (! is_array($referenceRule)) {
                        continue;
                    }
                    if (isset($referenceRule['row']) && (int) $referenceRule['row'] !== $row) {
                        continue;
                    }
                    $expected = isset($referenceRule['reference'])
                        ? ($this->canonicalReference((string) $referenceRule['reference'])['canonical']
                            ?? trim((string) $referenceRule['reference']))
                        : null;
                    if ($expected !== null && $expected !== $reference) {
                        continue;
                    }
                    $matchedReferenceRule = $referenceRule;
                    break;
                }
                if ($matchedReferenceRule === null) {
                    continue;
                }
            }

            $matches[] = [
                'type' => trim((string) ($definition['type'] ?? 'manual_review')),
                'notes' => trim((string) ($definition['notes'] ?? $definition['note'] ?? 'Manual review required by workbook manifest.')),
                'matched_rule' => $matchedReferenceRule,
            ];
        }

        return $matches;
    }

    private function workflowStatus(mixed $sourceActivityStatus): string
    {
        $literal = trim((string) $sourceActivityStatus);
        $workflowStatus = $this->activityStatusMapping[$literal] ?? null;
        if (! is_string($workflowStatus) || $workflowStatus === '') {
            throw new RuntimeException('Unsupported procurement Activity Status ['.$literal.']; workflow mapping is fail-closed.');
        }

        return $workflowStatus;
    }

    /** @param array<int, array<string, mixed>> $normalizations */
    private function noteControlledNormalization(array &$normalizations, string $field, mixed $from, mixed $to): void
    {
        if ($to === null || $from === $to || (is_string($from) && trim($from) === $to)) {
            return;
        }
        $normalizations[] = [
            'type' => 'controlled_value',
            'field' => $field,
            'from' => $from,
            'to' => $to,
            'note' => 'Normalized to the portal-controlled value while preserving the source cell.',
        ];
    }

    private function controlledYesNo(mixed $value): ?string
    {
        $normalized = $this->searchText((string) $value);

        return match ($normalized) {
            'yes', 'y', '1', 'true' => 'Yes',
            'no', 'n', '0', 'false' => 'No',
            default => null,
        };
    }

    private function controlledReviewType(mixed $value): ?string
    {
        return match ($this->searchText((string) $value)) {
            'prior' => 'Prior',
            'post' => 'Post',
            default => null,
        };
    }

    private function controlledCategory(mixed $value): string
    {
        $normalized = $this->searchText((string) $value);

        return match (true) {
            str_contains($normalized, 'non consulting') => 'non_consulting_services',
            str_contains($normalized, 'consult') => 'consulting_services',
            str_contains($normalized, 'work') => 'works',
            str_contains($normalized, 'training') => 'training',
            str_contains($normalized, 'good') => 'goods',
            default => 'other',
        };
    }

    private function controlledMarketApproach(mixed $value): ?string
    {
        $normalized = $this->searchText((string) $value);
        $map = [
            'open international' => 'Open - International',
            'international' => 'Open - International',
            'open national' => 'Open - National',
            'national' => 'Open - National',
            'limited international' => 'Limited - International',
            'limited national' => 'Limited - National',
            'direct international' => 'Direct - International',
            'direct national' => 'Direct - National',
        ];

        return $map[$normalized] ?? null;
    }

    private function controlledSeaShRisk(mixed $value): ?string
    {
        $normalized = $this->searchText((string) $value);

        return match ($normalized) {
            'yes', 'y' => 'Yes',
            'no', 'n' => 'No',
            'not applicable', 'na', 'n a' => 'Not Applicable',
            default => null,
        };
    }

    /** @return array{decimal: string, minor: int}|null */
    private function money(mixed $value): ?array
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $clean = trim((string) $value);
        $negative = str_contains($clean, '(') && str_contains($clean, ')');
        $clean = str_replace([',', '(', ')'], '', $clean);
        $clean = (string) preg_replace('/[^0-9.\-]/', '', $clean);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $clean)) {
            return null;
        }
        if (str_starts_with($clean, '-')) {
            $negative = true;
            $clean = substr($clean, 1);
        }
        [$whole, $fraction] = array_pad(explode('.', $clean, 2), 2, '');
        $fraction = preg_replace('/\D/', '', $fraction) ?? '';
        $cents = (int) str_pad(substr($fraction, 0, 2), 2, '0');
        if (isset($fraction[2]) && (int) $fraction[2] >= 5) {
            $cents++;
        }
        $minor = ((int) $whole * 100) + $cents;
        if ($negative) {
            $minor *= -1;
        }
        $absolute = abs($minor);
        $decimal = sprintf('%s%d.%02d', $minor < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);

        return ['decimal' => $decimal, 'minor' => $minor];
    }

    /**
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $headerPayload
     * @return array{value: ?string, source: string}
     */
    private function currency(mixed $explicit, ?string $amountColumn, array $headerPayload): array
    {
        $currency = Str::upper(trim((string) $explicit));
        if (preg_match('/^[A-Z]{3}$/', $currency)) {
            return ['value' => $currency, 'source' => 'currency_column'];
        }
        if ($amountColumn !== null) {
            $header = (string) ($headerPayload['cells'][$amountColumn]['formatted'] ?? '');
            if (preg_match('/(?:US\s*\$|U\.?S\.?\s*\$|USD)/i', $header)) {
                return ['value' => 'USD', 'source' => 'amount_header'];
            }
        }

        return ['value' => null, 'source' => 'unresolved'];
    }

    /** @return array{code: ?string, label: string, source: string, input: mixed} */
    private function method(mixed $override, ?string $referenceMethod, mixed $sourceValue, string $sheetName): array
    {
        $input = null;
        $source = 'unresolved';
        if ($override !== null && trim((string) $override) !== '') {
            $input = $override;
            $source = 'manifest_override';
        } elseif ($referenceMethod !== null) {
            $input = $referenceMethod;
            $source = 'reference';
        } elseif ($sourceValue !== null && trim((string) $sourceValue) !== '') {
            $input = $sourceValue;
            $source = 'source_column';
        } else {
            $input = $sheetName;
            $source = 'sheet';
        }

        $normalized = Str::upper(trim((string) preg_replace('/[^a-z0-9]+/i', '_', (string) $input), '_'));
        if (isset($this->methodValues[$normalized])) {
            $definition = $this->methodValues[$normalized];

            return [
                'code' => $definition['code'],
                'label' => $definition['label'],
                'source' => $source,
                'input' => $input,
            ];
        }
        $keys = array_keys($this->methodValues);
        usort($keys, static fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($keys as $key) {
            $definition = $this->methodValues[$key];
            if ($normalized === $key || str_contains($normalized, $key)) {
                return [
                    'code' => $definition['code'],
                    'label' => $definition['label'],
                    'source' => $source,
                    'input' => $input,
                ];
            }
        }

        return [
            'code' => null,
            'label' => Str::headline((string) $input),
            'source' => $source,
            'input' => $input,
        ];
    }

    /**
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $header
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}|null  $subheader
     * @param  array{row_number: int, cells: array<string, array<string, mixed>>}  $row
     * @return array<int, array<string, mixed>>
     */
    private function milestones(array $header, ?array $subheader, array $row): array
    {
        if ($subheader === null) {
            return [];
        }
        $columns = array_unique([
            ...array_keys($header['cells']),
            ...array_keys($subheader['cells']),
            ...array_keys($row['cells']),
        ]);
        usort($columns, static fn (string $left, string $right): int => Coordinate::columnIndexFromString($left) <=> Coordinate::columnIndexFromString($right)
        );

        $milestones = [];
        $currentHeading = null;
        foreach ($columns as $column) {
            $heading = trim((string) ($header['cells'][$column]['formatted'] ?? ''));
            if ($heading !== '') {
                $currentHeading = $heading;
            }
            $timing = $this->searchText((string) ($subheader['cells'][$column]['formatted'] ?? ''));
            if (! in_array($timing, ['planned', 'actual'], true)) {
                continue;
            }
            $payload = $row['cells'][$column] ?? null;
            if (! is_array($payload) || $this->blank($payload['formatted'] ?? null)) {
                continue;
            }
            $milestones[] = [
                'column' => $column,
                'milestone' => $currentHeading,
                'milestone_key' => $this->slug((string) $currentHeading),
                'timing' => $timing,
                'raw' => $payload['raw'] ?? null,
                'value' => $payload['formatted'] ?? null,
                'formula' => $payload['formula'] ?? null,
                'date' => $this->date($payload['raw'] ?? null)
                    ?? $this->date($payload['formatted'] ?? null),
            ];
        }

        return $milestones;
    }

    private function slug(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '_', Str::lower($value)), '_');
    }

    /** @param array<int, array<string, mixed>> $milestones @return array<int, array<string, mixed>> */
    private function timelineAnomalies(array $milestones): array
    {
        $anomalies = [];
        foreach (['planned', 'actual'] as $timing) {
            $previous = null;
            foreach ($milestones as $milestone) {
                if ($milestone['timing'] !== $timing || $milestone['date'] === null) {
                    continue;
                }
                if ($previous !== null && $milestone['date'] < $previous['date']) {
                    $anomalies[] = [
                        'code' => 'backward_milestone_timeline',
                        'timing' => $timing,
                        'previous' => $previous,
                        'current' => [
                            'column' => $milestone['column'],
                            'milestone' => $milestone['milestone'],
                            'date' => $milestone['date'],
                        ],
                        'message' => 'Milestone date moves backward in source column order; source order and date were preserved.',
                    ];
                }
                $previous = [
                    'column' => $milestone['column'],
                    'milestone' => $milestone['milestone'],
                    'date' => $milestone['date'],
                ];
            }
        }

        return $anomalies;
    }

    private function date(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        if (is_numeric($value) && (float) $value > 10_000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $value)->format('Y-m-d');
            } catch (Throwable) {
                return null;
            }
        }
        $value = trim((string) $value);
        foreach (['!Y/m/d', '!Y-m-d', '!m/d/Y', '!d/m/Y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return trim((string) $value);
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, array<string, mixed>>, 2: array<int, array<string, mixed>>}
     */
    private function deduplicate(array $records, string $priorityOrder): array
    {
        $seedable = [];
        $excluded = [];
        foreach ($records as $record) {
            $reference = (string) ($record['source_reference'] ?? '');
            $fallback = $record['record_key'];
            if ($record['is_seedable']) {
                $key = ($record['member_key'] ?? 'unresolved').'|'.($reference !== '' ? $reference : $fallback);
                $seedable[$key][] = $record;
            } else {
                $owner = $record['member_key'] ?? 'ownerless';
                $key = $owner.'|'.($reference !== '' ? $reference : $fallback);
                $excluded[$key][] = $record;
            }
        }

        $diagnostics = [];
        $uniqueRecords = $this->deduplicatedGroups($seedable, $priorityOrder, $diagnostics, false);
        $excludedRecords = $this->deduplicatedGroups($excluded, $priorityOrder, $diagnostics, true);

        return [$uniqueRecords, $excludedRecords, $diagnostics];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $groups
     * @param  array<int, array<string, mixed>>  $diagnostics
     * @return array<int, array<string, mixed>>
     */
    private function deduplicatedGroups(
        array $groups,
        string $priorityOrder,
        array &$diagnostics,
        bool $excluded,
    ): array {
        $output = [];
        foreach ($groups as $identity => $group) {
            usort($group, static function (array $left, array $right) use ($priorityOrder): int {
                $priorityComparison = ((int) $left['provenance']['source_priority'])
                    <=> ((int) $right['provenance']['source_priority']);
                if ($priorityOrder === 'higher_wins') {
                    $priorityComparison *= -1;
                }

                return $priorityComparison !== 0
                    ? $priorityComparison
                    : ((int) $left['_sequence'] <=> (int) $right['_sequence']);
            });
            $winner = $group[0];
            $provenance = array_map(static fn (array $record): array => $record['provenance'], $group);
            $winner['duplicate_count'] = count($group) - 1;
            $winner['all_provenance'] = $provenance;
            $winner['duplicate_provenance'] = array_slice($provenance, 1);
            unset($winner['_sequence']);
            $output[] = $winner;

            if (count($group) > 1) {
                $conflicts = $this->duplicateConflicts($group);
                $diagnostics[] = [
                    'identity' => $identity,
                    'reference' => $winner['source_reference'],
                    'member_key' => $winner['member_key'],
                    'excluded' => $excluded,
                    'occurrences' => count($group),
                    'winner' => $winner['provenance'],
                    'all_provenance' => $provenance,
                    'conflicts' => $conflicts,
                ];
            }
        }

        usort($output, static fn (array $left, array $right): int => ((int) ($left['provenance']['source_priority'] ?? 0) <=> (int) ($right['provenance']['source_priority'] ?? 0))
            ?: strcmp((string) $left['record_key'], (string) $right['record_key'])
        );

        return $output;
    }

    /** @param array<int, array<string, mixed>> $group @return array<string, array<int, mixed>> */
    private function duplicateConflicts(array $group): array
    {
        $fields = ['member_key', 'consortium_code', 'title', 'estimated_amount_minor', 'currency', 'procurement_method_code'];
        $conflicts = [];
        foreach ($fields as $field) {
            $values = array_values(array_unique(array_map(
                static fn (array $record): string => json_encode($record[$field] ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $group,
            )));
            if (count($values) > 1) {
                $conflicts[$field] = array_map(static fn (string $value): mixed => json_decode($value, true), $values);
            }
        }

        return $conflicts;
    }

    /**
     * @param  array<string, array<string, mixed>>  $workbooks
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $uniqueRecords
     * @param  array<int, array<string, mixed>>  $excludedRecords
     * @return array<string, int>
     */
    private function counts(array $workbooks, array $records, array $uniqueRecords, array $excludedRecords): array
    {
        return [
            'workbooks' => count($workbooks),
            'physical_records' => count($records),
            'unique_seedable_records' => count($uniqueRecords),
            'excluded_records' => count($excludedRecords),
            'physical_below_10000' => count(array_filter($records, static fn (array $record): bool => $record['threshold_band'] === self::BAND_BELOW)),
            'physical_at_or_above_10000' => count(array_filter($records, static fn (array $record): bool => $record['threshold_band'] === self::BAND_AT_OR_ABOVE)),
            'unique_below_10000' => count(array_filter($uniqueRecords, static fn (array $record): bool => $record['threshold_band'] === self::BAND_BELOW)),
            'unique_at_or_above_10000' => count(array_filter($uniqueRecords, static fn (array $record): bool => $record['threshold_band'] === self::BAND_AT_OR_ABOVE)),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<int, array<string, mixed>>  $uniqueRecords
     * @param  array<int, array<string, mixed>>  $excludedRecords
     * @return array<string, string>
     */
    private function totals(array $records, array $uniqueRecords, array $excludedRecords): array
    {
        return [
            'physical_usd' => $this->minorTotal($records),
            'unique_seedable_usd' => $this->minorTotal($uniqueRecords),
            'excluded_usd' => $this->minorTotal($excludedRecords),
            'unique_below_10000_usd' => $this->minorTotal(array_values(array_filter(
                $uniqueRecords,
                static fn (array $record): bool => $record['threshold_band'] === self::BAND_BELOW,
            ))),
            'unique_at_or_above_10000_usd' => $this->minorTotal(array_values(array_filter(
                $uniqueRecords,
                static fn (array $record): bool => $record['threshold_band'] === self::BAND_AT_OR_ABOVE,
            ))),
        ];
    }

    /** @param array<int, array<string, mixed>> $records */
    private function minorTotal(array $records): string
    {
        $minor = array_sum(array_map(
            static fn (array $record): int => $record['currency'] === 'USD'
                ? (int) ($record['estimated_amount_minor'] ?? 0)
                : 0,
            $records,
        ));
        $absolute = abs($minor);

        return sprintf('%s%d.%02d', $minor < 0 ? '-' : '', intdiv($absolute, 100), $absolute % 100);
    }
}
