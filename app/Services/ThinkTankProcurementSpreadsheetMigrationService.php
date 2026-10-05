<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Consortium;
use App\Models\ConsortiumThinkTank;
use App\Models\ThinkTankProcurementImportBatch;
use App\Models\ThinkTankProcurementImportRow;
use App\Models\ThinkTankProcurementItem;
use App\Models\ThinkTankProcurementPlan;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Persists the audited FY2026 Excel dataset and its source-declared workflow position.
 *
 * The workbook reader is deliberately separate and side-effect free. This class is
 * the guarded persistence boundary: it pins the audit invariants, archives every
 * source workbook privately, preserves every physical source record, and only
 * creates or updates records owned by this migration without erasing later staff work.
 */
final class ThinkTankProcurementSpreadsheetMigrationService
{
    public const BAND_BELOW = ThinkTankProcurementWorkbookDataset::BAND_BELOW;

    public const BAND_AT_OR_ABOVE = ThinkTankProcurementWorkbookDataset::BAND_AT_OR_ABOVE;

    public const FISCAL_YEAR = '2026';

    public const MIGRATION_KEY = 'think_tank_procurement_fy2026_excel';

    public const MIGRATION_VERSION = 1;

    private const EXPECTED_MANIFEST_SHA256 = '6da2cfddb0c372adcf958c9c8028a094582cdf41967b492bae140ac9bc271c14';

    /** @var array<string, int> */
    private const EXPECTED_COUNTS = [
        'workbooks' => 4,
        'physical_records' => 123,
        'unique_seedable_records' => 117,
        'excluded_records' => 3,
        'physical_below_10000' => 66,
        'physical_at_or_above_10000' => 57,
        'unique_below_10000' => 66,
        'unique_at_or_above_10000' => 51,
    ];

    /** @var array<string, int> */
    private const EXPECTED_PHYSICAL_ACTIVITY_STATUSES = [
        'New' => 67,
        'Returned' => 8,
        'Cleared' => 48,
    ];

    /** @var array<string, int> */
    private const EXPECTED_UNIQUE_ACTIVITY_STATUSES = [
        'New' => 67,
        'Returned' => 5,
        'Cleared' => 45,
    ];

    /** @var array<string, int> */
    private const EXPECTED_UNIQUE_WORKFLOW_STATUSES = [
        ThinkTankProcurementItem::STATUS_DRAFT => 67,
        ThinkTankProcurementItem::STATUS_REVISION_REQUESTED => 5,
        ThinkTankProcurementItem::STATUS_NO_OBJECTION => 45,
    ];

    /** @var array<string, string> */
    private const EXPECTED_TOTALS = [
        'physical_usd' => '1964271.38',
        'unique_seedable_usd' => '1804958.38',
        'excluded_usd' => '115213.00',
        'unique_below_10000_usd' => '238573.38',
        'unique_at_or_above_10000_usd' => '1566385.00',
    ];

    /** @var array<string, array{path: string, bytes: int, sha256: string}> */
    private const EXPECTED_WORKBOOKS = [
        'above_bridge' => [
            'path' => 'database/data/procurement/above_10k/bridge edit.xlsx',
            'bytes' => 29894,
            'sha256' => '8df0633d4f541bbba5c20076e0f7e57aedd30ab4f4de754575f8caa9ed2f54ed',
        ],
        'above_caceps' => [
            'path' => 'database/data/procurement/above_10k/CACEPS Edit.xlsx',
            'bytes' => 31746,
            'sha256' => 'fb832c1d7cd9ea8bcb569e6db342056189f1e0cc8cb9a03659b1e845ab0f46d9',
        ],
        'below_bridge' => [
            'path' => 'database/data/procurement/below_10k/Bridge Less than 10 K revised sent to bank .xlsx',
            'bytes' => 53964,
            'sha256' => '94d4af0303b25a00077aa663252fb29240be0d5c3ba60f1ffae3d53fc20b437f',
        ],
        'below_caceps' => [
            'path' => 'database/data/procurement/below_10k/CACEPS-Procurement-Plan-below10k-revised 01 september 2026.xlsx',
            'bytes' => 100806,
            'sha256' => 'b017879bf9e79fcf691d1d7678f762b1f26aa633021105ca35a55a6033c456a3',
        ],
    ];

    /** @var array<int, string> */
    private const EXPECTED_EXCLUDED_REFERENCES = [
        'ET-AUC-494922-GO-RFQ',
        'ET-AUC-570606-CS-QCBS',
        'ET-AUC-570613-CS-CDS',
    ];

    /** @var array<string, array<string, array{records: int, amount_minor: int}>> */
    private const EXPECTED_MEMBER_BANDS = [
        'acet' => [
            self::BAND_BELOW => ['records' => 6, 'amount_minor' => 4_490_000],
            self::BAND_AT_OR_ABOVE => ['records' => 9, 'amount_minor' => 42_337_000],
        ],
        'afidep' => [
            self::BAND_BELOW => ['records' => 5, 'amount_minor' => 3_559_819],
            self::BAND_AT_OR_ABOVE => ['records' => 10, 'amount_minor' => 19_694_000],
        ],
        'nkafu' => [
            self::BAND_BELOW => ['records' => 9, 'amount_minor' => 2_486_919],
            self::BAND_AT_OR_ABOVE => ['records' => 2, 'amount_minor' => 3_750_500],
        ],
        'pcns' => [
            self::BAND_BELOW => ['records' => 1, 'amount_minor' => 465_000],
            self::BAND_AT_OR_ABOVE => ['records' => 9, 'amount_minor' => 15_030_000],
        ],
        'saiia' => [
            self::BAND_BELOW => ['records' => 19, 'amount_minor' => 5_530_000],
            self::BAND_AT_OR_ABOVE => ['records' => 2, 'amount_minor' => 6_378_000],
        ],
        'aphrc' => [
            self::BAND_BELOW => ['records' => 13, 'amount_minor' => 987_200],
            self::BAND_AT_OR_ABOVE => ['records' => 6, 'amount_minor' => 30_000_000],
        ],
        'cip' => [
            self::BAND_BELOW => ['records' => 4, 'amount_minor' => 2_339_200],
            self::BAND_AT_OR_ABOVE => ['records' => 7, 'amount_minor' => 29_124_000],
        ],
        'cped' => [
            self::BAND_BELOW => ['records' => 2, 'amount_minor' => 1_199_800],
            self::BAND_AT_OR_ABOVE => ['records' => 0, 'amount_minor' => 0],
        ],
        'eces' => [
            self::BAND_BELOW => ['records' => 4, 'amount_minor' => 1_179_400],
            self::BAND_AT_OR_ABOVE => ['records' => 0, 'amount_minor' => 0],
        ],
        'ipar' => [
            self::BAND_BELOW => ['records' => 3, 'amount_minor' => 1_620_000],
            self::BAND_AT_OR_ABOVE => ['records' => 4, 'amount_minor' => 6_500_000],
        ],
        'reprc' => [
            self::BAND_BELOW => ['records' => 0, 'amount_minor' => 0],
            self::BAND_AT_OR_ABOVE => ['records' => 2, 'amount_minor' => 3_825_000],
        ],
    ];

    public function __construct(
        private readonly ThinkTankProcurementWorkbookDataset $dataset,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function seed(string $band): array
    {
        $this->assertBand($band);

        $dataset = $this->dataset->load();
        $this->assertAuditedDataset($dataset);
        $archives = $this->archiveWorkbooks($dataset['workbooks']);
        $manifest = $this->manifest($dataset);

        return Model::withoutEvents(fn (): array => DB::transaction(
            fn (): array => $this->persist($dataset, $manifest, $archives, $band),
            1,
        ));
    }

    private function assertBand(string $band): void
    {
        if (! in_array($band, [self::BAND_BELOW, self::BAND_AT_OR_ABOVE], true)) {
            throw new RuntimeException("Unsupported procurement migration band [{$band}].");
        }
    }

    /** @param array<string, mixed> $dataset */
    private function assertAuditedDataset(array $dataset): void
    {
        if (($dataset['manifest']['schema_version'] ?? null) !== self::MIGRATION_VERSION) {
            throw new RuntimeException('The procurement workbook manifest schema version is not the audited version.');
        }

        $errors = $dataset['diagnostics']['errors'] ?? null;
        if (! is_array($errors) || $errors !== []) {
            throw new RuntimeException('The procurement workbook parser reported errors: '.json_encode($errors));
        }

        foreach (self::EXPECTED_COUNTS as $key => $expected) {
            $actual = $dataset['diagnostics']['counts'][$key] ?? null;
            if ($actual !== $expected) {
                throw new RuntimeException("Procurement audit count [{$key}] drifted: expected {$expected}, got ".json_encode($actual).'.');
            }
        }
        foreach (self::EXPECTED_TOTALS as $key => $expected) {
            $actual = $dataset['diagnostics']['totals'][$key] ?? null;
            if ($actual !== $expected) {
                throw new RuntimeException("Procurement audit total [{$key}] drifted: expected {$expected}, got ".json_encode($actual).'.');
            }
        }

        $workbooks = $dataset['workbooks'] ?? null;
        if (! is_array($workbooks) || array_keys($workbooks) !== array_keys(self::EXPECTED_WORKBOOKS)) {
            throw new RuntimeException('The audited procurement workbook set or ordering has drifted.');
        }
        foreach (self::EXPECTED_WORKBOOKS as $key => $expected) {
            $workbook = $workbooks[$key] ?? [];
            $verification = $workbook['verification'] ?? [];
            foreach ($expected as $field => $value) {
                $actual = $field === 'path' ? ($workbook['path'] ?? null) : ($verification[$field] ?? null);
                if ($actual !== $value) {
                    throw new RuntimeException("Audited workbook [{$key}] {$field} drifted.");
                }
            }
            if (($verification['bytes_verified'] ?? false) !== true || ($verification['sha256_verified'] ?? false) !== true) {
                throw new RuntimeException("Audited workbook [{$key}] did not pass byte and SHA-256 verification.");
            }
        }

        $records = $dataset['records'] ?? null;
        $unique = $dataset['unique_records'] ?? null;
        $excluded = $dataset['excluded_records'] ?? null;
        if (! is_array($records) || ! is_array($unique) || ! is_array($excluded)) {
            throw new RuntimeException('The procurement workbook parser returned an incomplete dataset contract.');
        }

        $physicalKeys = [];
        $physicalExcluded = 0;
        foreach ($records as $record) {
            $this->assertPhysicalRecord($record, $workbooks);
            $provenance = $record['provenance'];
            $physicalKey = implode('|', [
                $provenance['workbook_key'],
                $provenance['source_sheet'],
                (string) $provenance['source_row'],
            ]);
            if (isset($physicalKeys[$physicalKey])) {
                throw new RuntimeException("Physical procurement source coordinate [{$physicalKey}] is duplicated in the parser output.");
            }
            $physicalKeys[$physicalKey] = true;
            $physicalExcluded += ($record['is_seedable'] ?? false) === true ? 0 : 1;
        }
        if ($physicalExcluded !== 4) {
            throw new RuntimeException("Expected four physical hard-exclusion rows, got {$physicalExcluded}.");
        }
        $this->assertStatusDistribution(
            $records,
            'source_activity_status',
            self::EXPECTED_PHYSICAL_ACTIVITY_STATUSES,
            'physical Activity Status',
        );

        $identities = [];
        foreach ($unique as $record) {
            if (($record['is_seedable'] ?? false) !== true) {
                throw new RuntimeException('A deduplicated seedable procurement record is marked as excluded.');
            }
            foreach (['member_key', 'member_name', 'consortium_code', 'source_reference'] as $key) {
                if (! is_string($record[$key] ?? null) || trim($record[$key]) === '') {
                    throw new RuntimeException("A seedable procurement record has no {$key}.");
                }
            }
            if (($record['fiscal_year'] ?? null) !== self::FISCAL_YEAR || ($record['currency'] ?? null) !== 'USD') {
                throw new RuntimeException('Every seedable record must resolve explicitly to FY2026 and USD.');
            }
            if (! in_array($record['threshold_band'] ?? null, [self::BAND_BELOW, self::BAND_AT_OR_ABOVE], true)) {
                throw new RuntimeException('Every seedable record must resolve to one audited USD threshold band.');
            }
            $identity = $this->recordIdentity($record);
            if (isset($identities[$identity])) {
                throw new RuntimeException("Canonical procurement identity [{$identity}] is not unique after deduplication.");
            }
            $identities[$identity] = true;
        }
        $this->assertMemberBandDistribution($unique);
        $this->assertStatusDistribution(
            $unique,
            'source_activity_status',
            self::EXPECTED_UNIQUE_ACTIVITY_STATUSES,
            'mapped Activity Status',
        );
        $this->assertStatusDistribution(
            $unique,
            'workflow_status',
            self::EXPECTED_UNIQUE_WORKFLOW_STATUSES,
            'mapped workflow status',
        );

        $excludedReferences = array_values(array_unique(array_map(
            static fn (array $record): string => (string) ($record['source_reference'] ?? ''),
            $excluded,
        )));
        sort($excludedReferences);
        $expectedExcluded = self::EXPECTED_EXCLUDED_REFERENCES;
        sort($expectedExcluded);
        if ($excludedReferences !== $expectedExcluded) {
            throw new RuntimeException('The audited hard-exclusion reference set has drifted.');
        }

        $manifestExpected = $dataset['diagnostics']['expected']['mapped_bands'] ?? [];
        foreach ([
            self::BAND_BELOW => ['records' => 66, 'amount_usd' => '238573.38'],
            self::BAND_AT_OR_ABOVE => ['records' => 51, 'amount_usd' => '1566385.00'],
        ] as $band => $expected) {
            if (($manifestExpected[$band] ?? null) !== $expected) {
                throw new RuntimeException("The manifest invariant for procurement band [{$band}] has drifted.");
            }
        }
        foreach ([
            'physical_activity_statuses' => self::EXPECTED_PHYSICAL_ACTIVITY_STATUSES,
            'mapped_activity_statuses' => self::EXPECTED_UNIQUE_ACTIVITY_STATUSES,
            'mapped_workflow_statuses' => self::EXPECTED_UNIQUE_WORKFLOW_STATUSES,
        ] as $key => $expected) {
            if (($dataset['diagnostics']['expected'][$key] ?? null) !== $expected) {
                throw new RuntimeException("The manifest invariant for procurement status distribution [{$key}] has drifted.");
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, int>  $expected
     */
    private function assertStatusDistribution(array $records, string $field, array $expected, string $label): void
    {
        $actual = [];
        foreach ($records as $record) {
            $value = $record[$field] ?? null;
            if (! is_string($value) || $value === '') {
                throw new RuntimeException("A procurement record has no {$label} value.");
            }
            $actual[$value] = ($actual[$value] ?? 0) + 1;
        }
        ksort($actual);
        ksort($expected);

        if ($actual !== $expected) {
            throw new RuntimeException("Procurement {$label} distribution drifted: expected ".json_encode($expected).', got '.json_encode($actual).'.');
        }
    }

    /** @param array<int, array<string, mixed>> $records */
    private function assertMemberBandDistribution(array $records): void
    {
        $actual = [];
        foreach ($records as $record) {
            $member = (string) $record['member_key'];
            $band = (string) $record['threshold_band'];
            $actual[$member][$band] ??= ['records' => 0, 'amount_minor' => 0];
            $actual[$member][$band]['records']++;
            $actual[$member][$band]['amount_minor'] += (int) $record['estimated_amount_minor'];
        }

        foreach (self::EXPECTED_MEMBER_BANDS as $member => $bands) {
            foreach ($bands as $band => $expected) {
                $observed = $actual[$member][$band] ?? ['records' => 0, 'amount_minor' => 0];
                if ($observed !== $expected) {
                    throw new RuntimeException(
                        "Procurement tenant distribution [{$member}/{$band}] drifted: expected ".json_encode($expected).', got '.json_encode($observed).'.'
                    );
                }
            }
            unset($actual[$member]);
        }

        if ($actual !== []) {
            throw new RuntimeException('The procurement dataset contains an unexpected Think Tank owner distribution: '.json_encode($actual).'.');
        }
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $workbooks
     */
    private function assertPhysicalRecord(array $record, array $workbooks): void
    {
        $provenance = $record['provenance'] ?? null;
        if (! is_array($provenance)
            || ! isset($workbooks[$provenance['workbook_key'] ?? ''])
            || ! is_string($provenance['source_sheet'] ?? null)
            || ! is_int($provenance['source_row'] ?? null)
            || ($provenance['source_row'] ?? 0) < 1) {
            throw new RuntimeException('A physical procurement record has invalid workbook provenance.');
        }
        if (($record['currency'] ?? null) !== 'USD'
            || ! is_string($record['estimated_amount'] ?? null)
            || ! is_int($record['estimated_amount_minor'] ?? null)) {
            throw new RuntimeException('A physical procurement record has an unresolved audited USD amount.');
        }
        if (! in_array($record['threshold_band'] ?? null, [self::BAND_BELOW, self::BAND_AT_OR_ABOVE], true)) {
            throw new RuntimeException('A physical procurement record is outside the two audited threshold bands.');
        }
        $this->seededWorkflowStatus($record);
    }

    /**
     * @param  array<string, array<string, mixed>>  $workbooks
     * @return array<string, string>
     */
    private function archiveWorkbooks(array $workbooks): array
    {
        $disk = Storage::disk('local');
        $archives = [];

        foreach ($workbooks as $key => $workbook) {
            $source = (string) ($workbook['absolute_path'] ?? '');
            $checksum = (string) ($workbook['verification']['sha256'] ?? '');
            $bytes = (int) ($workbook['verification']['bytes'] ?? -1);
            $name = basename(str_replace('\\', '/', (string) ($workbook['path'] ?? '')));
            $archive = "think-tank-procurement-imports/fy2026/{$checksum}/{$name}";

            if (! is_file($source) || hash_file('sha256', $source) !== $checksum || filesize($source) !== $bytes) {
                throw new RuntimeException("Audited workbook [{$key}] changed between parsing and private archiving.");
            }

            if (! $disk->exists($archive)) {
                $stream = fopen($source, 'rb');
                if (! is_resource($stream)) {
                    throw new RuntimeException("Audited workbook [{$key}] could not be opened for private archiving.");
                }
                try {
                    if (! $disk->put($archive, $stream)) {
                        throw new RuntimeException("Audited workbook [{$key}] could not be archived privately.");
                    }
                } finally {
                    fclose($stream);
                }
            }

            if ($disk->size($archive) !== $bytes || $this->storageHash($archive) !== $checksum) {
                throw new RuntimeException("Private archive [{$archive}] does not match audited workbook [{$key}].");
            }
            $archives[$key] = $archive;
        }

        return $archives;
    }

    private function storageHash(string $path): string
    {
        $stream = Storage::disk('local')->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException("Private archive [{$path}] could not be verified.");
        }
        try {
            $context = hash_init('sha256');
            hash_update_stream($context, $stream);

            return hash_final($context);
        } finally {
            fclose($stream);
        }
    }

    /** @param array<string, mixed> $dataset @return array<string, mixed> */
    private function manifest(array $dataset): array
    {
        $path = $dataset['manifest']['path'] ?? null;
        if (! is_string($path) || ! is_file($path)) {
            throw new RuntimeException('The audited procurement manifest is unavailable.');
        }
        $contents = file_get_contents($path);
        if (! is_string($contents)) {
            throw new RuntimeException('The audited procurement manifest could not be fingerprinted.');
        }
        $normalizedContents = str_replace(["\r\n", "\r"], "\n", $contents);
        if (! hash_equals(self::EXPECTED_MANIFEST_SHA256, hash('sha256', $normalizedContents))) {
            throw new RuntimeException('The audited procurement manifest checksum has changed; re-audit it before seeding.');
        }
        $manifest = require $path;
        if (! is_array($manifest) || ($manifest['schema_version'] ?? null) !== self::MIGRATION_VERSION) {
            throw new RuntimeException('The audited procurement manifest could not be loaded safely.');
        }

        return $manifest;
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @param  array<string, mixed>  $manifest
     * @param  array<string, string>  $archives
     * @return array<string, mixed>
     */
    private function persist(array $dataset, array $manifest, array $archives, string $band): array
    {
        $selectedRecords = array_values(array_filter(
            $dataset['unique_records'],
            static fn (array $record): bool => $record['threshold_band'] === $band,
        ));
        $members = $this->resolveActiveMembers($dataset['unique_records']);
        $this->assertAllExistingPlansSafe($members);
        $batches = $this->resolveBatches($dataset['workbooks'], $archives);
        $plans = [];
        $items = [];

        foreach ($this->groupRecordsByMember($selectedRecords) as $memberKey => $records) {
            $member = $members[$memberKey];
            $plan = $this->resolveSafePlan($memberKey, $member);
            $plans[$memberKey] = $plan;

            foreach ($records as $record) {
                $item = $this->persistItem($plan, $record, $dataset, $manifest, $band);
                $items[$this->recordIdentity($record)] = $item;
            }
        }

        $this->preservePhysicalRows($dataset['records'], $batches, $items, $band);

        foreach ($plans as $plan) {
            $this->synchronizePlanBudget($plan);
        }
        $this->finalizeBatches($dataset, $batches, $band);

        return [
            'migration' => self::MIGRATION_KEY,
            'version' => self::MIGRATION_VERSION,
            'fiscal_year' => self::FISCAL_YEAR,
            'band' => $band,
            'unique_items_in_band' => count($selectedRecords),
            'amount_usd' => $dataset['diagnostics']['totals'][
                $band === self::BAND_BELOW ? 'unique_below_10000_usd' : 'unique_at_or_above_10000_usd'
            ],
            'plans_touched' => count($plans),
            'plan_ids' => array_values(array_map(static fn (ThinkTankProcurementPlan $plan): string => $plan->id, $plans)),
            'workbook_batches' => array_map(
                static fn (ThinkTankProcurementImportBatch $batch): string => $batch->id,
                $batches,
            ),
            'private_archives' => $archives,
        ];
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, ConsortiumThinkTank> */
    private function resolveActiveMembers(array $records): array
    {
        $definitions = [];
        foreach ($records as $record) {
            $memberKey = (string) $record['member_key'];
            $definition = [
                'consortium_code' => (string) $record['consortium_code'],
                'member_name' => (string) $record['member_name'],
            ];
            if (isset($definitions[$memberKey]) && $definitions[$memberKey] !== $definition) {
                throw new RuntimeException("Owner mapping [{$memberKey}] is internally inconsistent.");
            }
            $definitions[$memberKey] = $definition;
        }

        $members = [];
        foreach ($definitions as $memberKey => $definition) {
            $consortia = Consortium::query()
                ->where('code', $definition['consortium_code'])
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();
            if ($consortia->count() !== 1) {
                throw new RuntimeException("Expected one active consortium with exact code [{$definition['consortium_code']}], found {$consortia->count()}.");
            }
            /** @var Consortium $consortium */
            $consortium = $consortia->first();
            $matches = ConsortiumThinkTank::query()
                ->where('consortium_id', $consortium->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get()
                ->filter(static fn (ConsortiumThinkTank $member): bool => $member->name === $definition['member_name'])
                ->values();
            if ($matches->count() !== 1) {
                throw new RuntimeException(
                    "Expected one active Think Tank named exactly [{$definition['member_name']}] under [{$definition['consortium_code']}], found {$matches->count()}."
                );
            }
            /** @var ConsortiumThinkTank $member */
            $member = $matches->first();
            $member->setRelation('consortium', $consortium);
            $members[$memberKey] = $member;
        }

        return $members;
    }

    /** @param array<string, ConsortiumThinkTank> $members */
    private function assertAllExistingPlansSafe(array $members): void
    {
        foreach ($members as $memberKey => $member) {
            $plans = ThinkTankProcurementPlan::query()
                ->where('think_tank_member_id', $member->id)
                ->where('fiscal_year', self::FISCAL_YEAR)
                ->lockForUpdate()
                ->get();

            if ($plans->count() > 1) {
                throw new RuntimeException("Think Tank [{$member->name}] has multiple FY2026 plans; migration requires one unambiguous annual plan.");
            }

            /** @var ThinkTankProcurementPlan|null $plan */
            $plan = $plans->first();
            if ($plan) {
                $this->assertSafePlan($plan, $member);

                continue;
            }

            $planCode = $this->deterministicPlanCode($memberKey);
            if (ThinkTankProcurementPlan::query()->where('plan_code', $planCode)->exists()) {
                throw new RuntimeException("Deterministic plan code [{$planCode}] is already used by another plan.");
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $workbooks
     * @param  array<string, string>  $archives
     * @return array<string, ThinkTankProcurementImportBatch>
     */
    private function resolveBatches(array $workbooks, array $archives): array
    {
        $batches = [];
        foreach ($workbooks as $key => $workbook) {
            $checksum = (string) $workbook['verification']['sha256'];
            $batch = ThinkTankProcurementImportBatch::query()
                ->where('source_checksum', $checksum)
                ->lockForUpdate()
                ->first();

            if ($batch && ! $this->isOwnedBatch($batch)) {
                throw new RuntimeException(
                    "Workbook [{$key}] already has an import batch not owned by the audited FY2026 migration; no existing import data was overwritten."
                );
            }
            if (! $batch) {
                $batch = new ThinkTankProcurementImportBatch;
                $batch->source_checksum = $checksum;
            }
            $batch->fill([
                'source_path' => $workbook['path'],
                'original_name' => basename(str_replace('\\', '/', (string) $workbook['path'])),
                'archive_path' => $archives[$key],
                'file_size' => (int) $workbook['verification']['bytes'],
                'status' => 'processing',
                'imported_by' => null,
                'summary' => [
                    'migration' => $this->migrationMarker(),
                    'workbook_key' => $key,
                    'seeded_bands' => $this->seededBands($batch),
                ],
            ]);
            $batch->save();
            $batches[$key] = $batch;
        }

        return $batches;
    }

    private function isOwnedBatch(ThinkTankProcurementImportBatch $batch): bool
    {
        return data_get($batch->summary, 'migration.key') === self::MIGRATION_KEY
            && data_get($batch->summary, 'migration.version') === self::MIGRATION_VERSION;
    }

    /** @return array<int, string> */
    private function seededBands(ThinkTankProcurementImportBatch $batch): array
    {
        $bands = data_get($batch->summary, 'seeded_bands', []);

        return is_array($bands) ? array_values(array_intersect($bands, [self::BAND_BELOW, self::BAND_AT_OR_ABOVE])) : [];
    }

    /** @param array<int, array<string, mixed>> $records @return array<string, array<int, array<string, mixed>>> */
    private function groupRecordsByMember(array $records): array
    {
        $grouped = [];
        foreach ($records as $record) {
            $grouped[(string) $record['member_key']][] = $record;
        }
        ksort($grouped);

        return $grouped;
    }

    private function resolveSafePlan(string $memberKey, ConsortiumThinkTank $member): ThinkTankProcurementPlan
    {
        $plans = ThinkTankProcurementPlan::query()
            ->where('think_tank_member_id', $member->id)
            ->where('fiscal_year', self::FISCAL_YEAR)
            ->lockForUpdate()
            ->get();
        if ($plans->count() > 1) {
            throw new RuntimeException("Think Tank [{$member->name}] has multiple FY2026 plans; migration requires one unambiguous annual plan.");
        }

        /** @var ThinkTankProcurementPlan|null $plan */
        $plan = $plans->first();
        if (! $plan) {
            $planCode = $this->deterministicPlanCode($memberKey);
            if (ThinkTankProcurementPlan::query()->where('plan_code', $planCode)->exists()) {
                throw new RuntimeException("Deterministic plan code [{$planCode}] is already used by another plan.");
            }
            $plan = ThinkTankProcurementPlan::query()->create([
                'consortium_id' => $member->consortium_id,
                'think_tank_member_id' => $member->id,
                'plan_code' => $planCode,
                'title' => "FY2026 Annual Procurement Plan - {$member->name}",
                'fiscal_year' => self::FISCAL_YEAR,
                'estimated_budget' => '0.00',
                'currency' => 'USD',
                'planned_publish_date' => null,
                'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
                'description' => $this->planMarkerText(),
                'created_by' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'review_notes' => null,
                'version' => 1,
                'submitted_at' => null,
                'last_resubmitted_at' => null,
                'approved_at' => null,
                'rejected_at' => null,
                'decision_reason' => null,
                'portal_lock_version' => 1,
            ]);

            return $plan;
        }

        $this->assertSafePlan($plan, $member);
        if ($plan->items()->count() === 0 && ! str_contains((string) $plan->description, $this->planMarkerText())) {
            $description = trim((string) $plan->description);
            $plan->forceFill([
                'description' => trim($description."\n\n".$this->planMarkerText()),
                'currency' => 'USD',
                'status' => ThinkTankProcurementPlan::STATUS_DRAFT,
            ])->save();
        }

        return $plan;
    }

    private function assertSafePlan(ThinkTankProcurementPlan $plan, ConsortiumThinkTank $member): void
    {
        if ($plan->consortium_id !== $member->consortium_id || $plan->think_tank_member_id !== $member->id) {
            throw new RuntimeException("FY2026 plan [{$plan->plan_code}] is attached to an inconsistent tenant boundary.");
        }
        if (! in_array($plan->currency, [null, '', 'USD'], true)) {
            throw new RuntimeException("FY2026 plan [{$plan->plan_code}] is not a USD plan and was not modified.");
        }

        $items = ThinkTankProcurementItem::query()->where('plan_id', $plan->id)->lockForUpdate()->get();
        $isMigrationPlan = str_contains((string) $plan->description, $this->planMarkerText())
            || $items->contains(fn (ThinkTankProcurementItem $item): bool => $this->isOwnedImportedItem($item));
        if ($isMigrationPlan) {
            return;
        }

        if ($plan->status !== ThinkTankProcurementPlan::STATUS_DRAFT
            || (int) ($plan->version ?? 1) > 1
            || (int) ($plan->portal_lock_version ?? 1) > 1
            || $plan->reviewed_by !== null
            || $plan->reviewed_at !== null
            || $plan->review_notes !== null
            || $plan->submitted_at !== null
            || $plan->last_resubmitted_at !== null
            || $plan->approved_at !== null
            || $plan->rejected_at !== null
            || $plan->decision_reason !== null
            || $plan->events()->exists()
            || $plan->procurements()->exists()) {
            throw new RuntimeException("FY2026 plan [{$plan->plan_code}] has workflow history and was not adopted or modified.");
        }
        if ($items->isEmpty() && abs((float) $plan->estimated_budget) > 0.00001) {
            throw new RuntimeException("Empty FY2026 plan [{$plan->plan_code}] has a non-zero manual budget and was not adopted.");
        }
        if ($items->isNotEmpty()) {
            throw new RuntimeException("FY2026 plan [{$plan->plan_code}] contains manual items and was not adopted or modified.");
        }
    }

    private function assertOwnedImportedItem(ThinkTankProcurementItem $item, ThinkTankProcurementPlan $plan): void
    {
        if (! $this->isOwnedImportedItem($item)) {
            throw new RuntimeException("FY2026 plan [{$plan->plan_code}] contains item [{$item->item_code}] not owned by this migration.");
        }
    }

    private function isOwnedImportedItem(ThinkTankProcurementItem $item): bool
    {
        $migration = data_get($item->source_payload, 'migration');

        return is_array($migration)
            && ($migration['key'] ?? null) === self::MIGRATION_KEY
            && ($migration['version'] ?? null) === self::MIGRATION_VERSION
            && ($migration['fiscal_year'] ?? null) === self::FISCAL_YEAR;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $dataset
     * @param  array<string, mixed>  $manifest
     */
    private function persistItem(
        ThinkTankProcurementPlan $plan,
        array $record,
        array $dataset,
        array $manifest,
        string $band,
    ): ThinkTankProcurementItem {
        $matches = ThinkTankProcurementItem::query()
            ->where('plan_id', $plan->id)
            ->where('source_reference', $record['source_reference'])
            ->lockForUpdate()
            ->get()
            ->filter(static fn (ThinkTankProcurementItem $item): bool => $item->source_reference === $record['source_reference'])
            ->values();
        if ($matches->count() > 1) {
            throw new RuntimeException("Plan [{$plan->plan_code}] has duplicate canonical reference [{$record['source_reference']}].");
        }

        /** @var ThinkTankProcurementItem|null $item */
        $item = $matches->first();
        $itemCode = $this->deterministicItemCode($plan, $record);
        $isNew = $item === null;
        $refreshImportedSnapshot = true;
        if ($item) {
            $this->assertOwnedImportedItem($item, $plan);
            $markerIdentity = data_get($item->source_payload, 'migration.identity');
            if ($markerIdentity !== $this->recordIdentity($record)) {
                throw new RuntimeException("Imported item [{$item->item_code}] has an inconsistent canonical migration identity.");
            }
            $refreshImportedSnapshot = $this->isPristineImportedItem($item);
        } else {
            $collision = ThinkTankProcurementItem::query()->where('item_code', $itemCode)->lockForUpdate()->first();
            if ($collision) {
                throw new RuntimeException("Deterministic item code [{$itemCode}] is already used by another procurement item.");
            }
            $item = new ThinkTankProcurementItem;
            $item->plan_id = $plan->id;
        }

        $provenance = $record['provenance'];
        $generatedSourcePayload = $this->itemSourcePayload($record, $dataset, $manifest, $band);
        $existingSourcePayload = is_array($item->source_payload) ? $item->source_payload : [];
        $existingMigrationMarker = is_array($existingSourcePayload['migration'] ?? null)
            ? $existingSourcePayload['migration']
            : [];
        $sourcePayload = [
            ...$existingSourcePayload,
            ...$generatedSourcePayload,
            'migration' => [
                ...$existingMigrationMarker,
                ...$generatedSourcePayload['migration'],
            ],
        ];
        $item->fill([
            'item_code' => $itemCode,
            'source_reference' => $record['source_reference'],
            'source_file' => $provenance['source_file'],
            'source_sheet' => $provenance['source_sheet'],
            'source_row' => $provenance['source_row'],
            'source_payload' => $sourcePayload,
        ]);
        if ($refreshImportedSnapshot) {
            $item->fill([
                'loan_credit_no' => $record['loan_credit_no'],
                'component' => $record['component'],
                'source_in_process' => $record['source_in_process'],
                'source_process_status' => $record['source_process_status'],
                'source_activity_status' => $record['source_activity_status'],
                'step_activity_status' => $record['source_activity_status'],
                'source_document_type' => $record['source_document_type'],
                'source_sea_sh_risk' => $record['source_sea_sh_risk'],
                'title' => $this->displayTitle($record),
                'description' => $record['description'],
                'procurement_category' => $record['procurement_category'],
                'procurement_method' => $record['procurement_method'],
                'market_approach' => $record['market_approach'],
                'review_type' => $record['review_type'],
                'quantity' => $this->decimalOrNull($record['quantity'], 4),
                'unit' => $record['unit'],
                'estimated_unit_cost' => $this->decimalOrNull($record['estimated_unit_cost'], 2),
                'estimated_amount' => $record['estimated_amount'],
                'currency' => 'USD',
                'planned_quarter' => $record['planned_quarter'],
                'planned_start_date' => $record['planned_start_date'],
                'planned_end_date' => $record['planned_end_date'],
                'planned_milestones' => $record['planned_milestones'],
                'limited_selection_justification' => $record['limited_selection_justification'],
                'budget_reference' => $record['budget_reference'],
                'bank_comment' => $record['bank_comment'],
                'action_taken' => $record['action_taken'],
            ]);
            $item->status = $this->seededWorkflowStatus($record);
        }
        if ($isNew) {
            $item->fill([
                'review_reason' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
                'step_reference' => null,
                'step_exported_at' => null,
                'step_exported_by' => null,
                'no_objection_reference' => null,
                'no_objection_date' => null,
                'no_objection_notes' => null,
                'no_objection_by' => null,
                'no_objection_recorded_at' => null,
                'procurement_id' => null,
                'created_by' => null,
                'updated_by' => null,
                'portal_lock_version' => 1,
            ]);
        }
        $item->save();

        return $item;
    }

    private function isPristineImportedItem(ThinkTankProcurementItem $item): bool
    {
        $importedWorkflowStatus = data_get($item->source_payload, 'migration.imported_workflow_status');
        $baselineStatus = is_string($importedWorkflowStatus) && $importedWorkflowStatus !== ''
            ? $importedWorkflowStatus
            : ThinkTankProcurementItem::STATUS_DRAFT;

        return $item->status === $baselineStatus
            && (int) ($item->portal_lock_version ?? 1) <= 1
            && $item->review_reason === null
            && $item->reviewed_by === null
            && $item->reviewed_at === null
            && $item->step_reference === null
            && $item->step_exported_at === null
            && $item->step_exported_by === null
            && $item->no_objection_reference === null
            && $item->no_objection_date === null
            && $item->no_objection_notes === null
            && $item->no_objection_by === null
            && $item->no_objection_recorded_at === null
            && $item->procurement_id === null
            && $item->updated_by === null
            && ! $item->documents()->exists()
            && ! $item->events()->exists();
    }

    /** @param array<string, mixed> $record */
    private function displayTitle(array $record): string
    {
        $title = trim((string) ($record['display_title'] ?? $record['title'] ?? ''));
        if ($title === '') {
            $title = (string) $record['source_reference'];
        }
        if (mb_strlen($title) <= 255) {
            return $title;
        }

        return rtrim(mb_substr($title, 0, 252)).'...';
    }

    private function decimalOrNull(mixed $value, int $scale): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $normalized = str_replace(',', '', trim((string) $value));
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return null;
        }

        return number_format((float) $normalized, $scale, '.', '');
    }

    /** @param array<string, mixed> $record */
    private function seededWorkflowStatus(array $record): string
    {
        $status = $record['workflow_status'] ?? null;
        if (! in_array($status, [
            ThinkTankProcurementItem::STATUS_DRAFT,
            ThinkTankProcurementItem::STATUS_REVISION_REQUESTED,
            ThinkTankProcurementItem::STATUS_NO_OBJECTION,
        ], true)) {
            throw new RuntimeException('A procurement import record has an unsupported seeded workflow status ['.json_encode($status).'].');
        }

        return $status;
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $dataset
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function itemSourcePayload(array $record, array $dataset, array $manifest, string $band): array
    {
        return [
            'migration' => [
                ...$this->migrationMarker(),
                'identity' => $this->recordIdentity($record),
                'member_key' => $record['member_key'],
                'threshold_band' => $band,
                'imported_workflow_status' => $this->seededWorkflowStatus($record),
            ],
            'canonical' => [
                'source_reference' => $record['source_reference'],
                'reference_parts' => $record['reference_parts'],
                'owner_alias' => $record['owner_alias'],
                'member_name' => $record['member_name'],
                'consortium_code' => $record['consortium_code'],
                'procurement_method_code' => $record['procurement_method_code'],
                'procurement_method_source' => $record['procurement_method_source'],
                'currency_source' => $record['currency_source'],
            ],
            'source' => [
                'activity_raw' => $record['activity_raw'],
                'title_raw' => $record['title_raw'],
                'source_reference_raw' => $record['source_reference_raw'],
                'source_fields' => $record['source_fields'],
                'source_field_payloads' => $record['source_field_payloads'],
                'source_field_provenance' => $record['source_field_provenance'],
                'row' => $record['row_payload'],
                'header' => $record['header_payload'],
                'subheader' => $record['subheader_payload'],
            ],
            'source_statuses' => [
                'in_process' => $record['source_in_process'],
                'process_status' => $record['source_process_status'],
                'activity_status' => $record['source_activity_status'],
                'document_type' => $record['source_document_type'],
                'sea_sh_risk' => $record['source_sea_sh_risk'],
                'review_type' => $record['review_type'],
            ],
            'normalizations' => $record['normalizations'],
            'review_flags' => $this->reviewFlags($record, $dataset, $manifest),
            'provenance' => [
                'selected' => $record['provenance'],
                'all' => $record['all_provenance'] ?? [$record['provenance']],
                'duplicates' => $record['duplicate_provenance'] ?? [],
                'duplicate_count' => $record['duplicate_count'] ?? 0,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $record
     * @param  array<string, mixed>  $dataset
     * @param  array<string, mixed>  $manifest
     * @return array<int, array<string, mixed>>
     */
    private function reviewFlags(array $record, array $dataset, array $manifest): array
    {
        $flags = [];
        foreach ($record['review_flags'] ?? [] as $review) {
            if (is_array($review)) {
                $flags[] = ['source' => 'manifest_review_required', ...$review];
            }
        }
        foreach ($record['timeline_anomalies'] ?? [] as $anomaly) {
            $flags[] = ['source' => 'timeline_audit', ...$anomaly];
        }
        foreach ($dataset['diagnostics']['warnings'] ?? [] as $warning) {
            if ($this->diagnosticMatchesRecord($warning, $record)) {
                $flags[] = ['source' => 'parser_diagnostic', ...$warning];
            }
        }
        if (($record['review_flags'] ?? []) === []) {
            foreach ($manifest['review_required'] ?? [] as $review) {
                if (is_array($review) && $this->manifestReviewMatchesRecord($review, $record)) {
                    $flags[] = ['source' => 'manifest_review_required', ...$review];
                }
            }
        }

        return $flags;
    }

    /** @param array<string, mixed> $diagnostic @param array<string, mixed> $record */
    private function diagnosticMatchesRecord(array $diagnostic, array $record): bool
    {
        $provenance = $diagnostic['provenance'] ?? [];
        $recordProvenance = $record['provenance'];
        $workbook = $provenance['workbook_key'] ?? $provenance['workbookKey'] ?? $diagnostic['workbook'] ?? null;
        $sheet = $provenance['source_sheet'] ?? $provenance['sheetName'] ?? $diagnostic['sheet'] ?? null;
        $row = $provenance['source_row'] ?? $provenance['rowNumber'] ?? $diagnostic['row'] ?? null;

        return $workbook === $recordProvenance['workbook_key']
            && $sheet === $recordProvenance['source_sheet']
            && (int) $row === (int) $recordProvenance['source_row'];
    }

    /** @param array<string, mixed> $review @param array<string, mixed> $record */
    private function manifestReviewMatchesRecord(array $review, array $record): bool
    {
        $provenance = $record['provenance'];
        if (isset($review['workbook']) && $review['workbook'] !== $provenance['workbook_key']) {
            return false;
        }
        if (isset($review['sheet']) && $review['sheet'] !== $provenance['source_sheet']) {
            return false;
        }
        if (isset($review['owner_key']) && $review['owner_key'] !== $record['member_key']) {
            return false;
        }
        if (isset($review['row']) && (int) $review['row'] !== (int) $provenance['source_row']) {
            return false;
        }
        if (isset($review['rows'])) {
            $rows = $review['rows'];
            if (is_array($rows) && count($rows) === 2 && is_int($rows[0]) && is_int($rows[1])) {
                if ($provenance['source_row'] < $rows[0] || $provenance['source_row'] > $rows[1]) {
                    return false;
                }
            }
        }
        if (isset($review['reference']) && $review['reference'] !== $record['source_reference']) {
            return false;
        }
        if (isset($review['references']) && is_array($review['references'])) {
            $references = array_map(
                static fn (mixed $entry): ?string => is_array($entry) ? ($entry['reference'] ?? null) : (is_string($entry) ? $entry : null),
                $review['references'],
            );
            if (! in_array($record['source_reference'], $references, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $records
     * @param  array<string, ThinkTankProcurementImportBatch>  $batches
     * @param  array<string, ThinkTankProcurementItem>  $items
     */
    private function preservePhysicalRows(array $records, array $batches, array $items, string $band): void
    {
        foreach ($records as $record) {
            $provenance = $record['provenance'];
            $batch = $batches[$provenance['workbook_key']];
            $row = ThinkTankProcurementImportRow::query()
                ->where('batch_id', $batch->id)
                ->where('sheet_name', $provenance['source_sheet'])
                ->where('row_number', $provenance['source_row'])
                ->lockForUpdate()
                ->first() ?? new ThinkTankProcurementImportRow;

            if ($row->exists && $row->batch_id !== $batch->id) {
                throw new RuntimeException('A preserved source row crossed its checksum-keyed batch boundary.');
            }
            $row->fill([
                'batch_id' => $batch->id,
                'sheet_name' => $provenance['source_sheet'],
                'row_number' => $provenance['source_row'],
                'row_payload' => $this->physicalRowPayload($record),
            ]);

            if (($record['is_seedable'] ?? false) !== true) {
                $row->fill([
                    'mapping_status' => 'excluded',
                    'mapping_message' => trim((string) ($record['exclusion_reason'] ?? 'Excluded by the audited manifest.')),
                    'plan_id' => null,
                    'item_id' => null,
                ]);
            } elseif ($record['threshold_band'] === $band) {
                $item = $items[$this->recordIdentity($record)] ?? null;
                if (! $item) {
                    throw new RuntimeException("No canonical item was created for physical record [{$record['record_key']}].");
                }
                $row->fill([
                    'mapping_status' => 'mapped',
                    'mapping_message' => 'Mapped by audited FY2026 migration using Think Tank plus canonical reference identity.',
                    'plan_id' => $item->plan_id,
                    'item_id' => $item->id,
                ]);
            } elseif ($row->mapping_status !== 'mapped' || $row->item_id === null) {
                $row->fill([
                    'mapping_status' => 'band_deferred',
                    'mapping_message' => "Preserved for the targeted [{$record['threshold_band']}] seeder.",
                    'plan_id' => null,
                    'item_id' => null,
                ]);
            }
            $row->save();
        }
    }

    /** @param array<string, mixed> $record @return array<string, mixed> */
    private function physicalRowPayload(array $record): array
    {
        return [
            'migration' => $this->migrationMarker(),
            'record_key' => $record['record_key'],
            'classification' => [
                'is_seedable' => $record['is_seedable'],
                'threshold_band' => $record['threshold_band'],
                'workflow_status' => $this->seededWorkflowStatus($record),
                'member_key' => $record['member_key'],
                'consortium_code' => $record['consortium_code'],
                'source_reference' => $record['source_reference'],
                'exclusion_reason_code' => $record['exclusion_reason_code'],
                'exclusion_reason' => $record['exclusion_reason'],
            ],
            'activity_raw' => $record['activity_raw'],
            'source_reference_raw' => $record['source_reference_raw'],
            'source_fields' => $record['source_fields'],
            'source_field_payloads' => $record['source_field_payloads'],
            'source_field_provenance' => $record['source_field_provenance'],
            'row' => $record['row_payload'],
            'header' => $record['header_payload'],
            'subheader' => $record['subheader_payload'],
            'normalizations' => $record['normalizations'],
            'review_flags' => $record['review_flags'] ?? [],
            'timeline_anomalies' => $record['timeline_anomalies'],
            'provenance' => $record['provenance'],
        ];
    }

    private function synchronizePlanBudget(ThinkTankProcurementPlan $plan): void
    {
        $nonUsd = $plan->items()->where(function ($query): void {
            $query->whereNull('currency')->orWhere('currency', '!=', 'USD');
        })->exists();
        if ($nonUsd) {
            throw new RuntimeException("FY2026 plan [{$plan->plan_code}] contains a non-USD item and its budget was not recalculated.");
        }
        $plan->forceFill([
            'estimated_budget' => $plan->items()->sum('estimated_amount'),
            'currency' => 'USD',
        ])->save();
    }

    /**
     * @param  array<string, mixed>  $dataset
     * @param  array<string, ThinkTankProcurementImportBatch>  $batches
     */
    private function finalizeBatches(array $dataset, array $batches, string $band): void
    {
        foreach ($batches as $key => $batch) {
            $records = array_values(array_filter(
                $dataset['records'],
                static fn (array $record): bool => $record['provenance']['workbook_key'] === $key,
            ));
            $statusCounts = ThinkTankProcurementImportRow::query()
                ->where('batch_id', $batch->id)
                ->selectRaw('mapping_status, COUNT(*) AS aggregate')
                ->groupBy('mapping_status')
                ->pluck('aggregate', 'mapping_status')
                ->map(static fn (mixed $count): int => (int) $count)
                ->all();
            $mappedItems = ThinkTankProcurementImportRow::query()
                ->where('batch_id', $batch->id)
                ->where('mapping_status', 'mapped')
                ->whereNotNull('item_id')
                ->distinct()
                ->count('item_id');
            $seededBands = array_values(array_unique([...$this->seededBands($batch), $band]));
            sort($seededBands);
            $warningCount = (int) ($statusCounts['excluded'] ?? 0)
                + (int) ($statusCounts['band_deferred'] ?? 0)
                + $this->workbookDiagnosticCount($dataset, $key);

            $batch->forceFill([
                'status' => $warningCount > 0 ? 'completed_with_warnings' : 'completed',
                'sheet_count' => count(array_unique(array_map(
                    static fn (array $record): string => $record['provenance']['source_sheet'],
                    $records,
                ))),
                'source_row_count' => count($records),
                'mapped_item_count' => $mappedItems,
                'warning_count' => $warningCount,
                'summary' => [
                    'migration' => $this->migrationMarker(),
                    'workbook_key' => $key,
                    'seeded_bands' => $seededBands,
                    'source' => [
                        'path' => $dataset['workbooks'][$key]['path'],
                        'sha256' => $dataset['workbooks'][$key]['verification']['sha256'],
                        'bytes' => $dataset['workbooks'][$key]['verification']['bytes'],
                        'private_archive' => $batch->archive_path,
                    ],
                    'physical_records' => count($records),
                    'row_statuses' => $statusCounts,
                    'distinct_mapped_items' => $mappedItems,
                    'parser_diagnostic_count' => $this->workbookDiagnosticCount($dataset, $key),
                    'audit_counts' => self::EXPECTED_COUNTS,
                    'audit_totals' => self::EXPECTED_TOTALS,
                ],
            ])->save();
        }
    }

    /** @param array<string, mixed> $dataset */
    private function workbookDiagnosticCount(array $dataset, string $workbookKey): int
    {
        $count = 0;
        foreach (['warnings', 'timeline_anomalies', 'review_required', 'ignored_sparse_cells'] as $type) {
            foreach ($dataset['diagnostics'][$type] ?? [] as $diagnostic) {
                $provenance = $diagnostic['provenance'] ?? [];
                $key = $provenance['workbook_key']
                    ?? $provenance['workbookKey']
                    ?? $diagnostic['workbook']
                    ?? null;
                $count += $key === $workbookKey ? 1 : 0;
            }
        }

        return $count;
    }

    /** @param array<string, mixed> $record */
    private function recordIdentity(array $record): string
    {
        return (string) $record['member_key'].'|'.(string) $record['source_reference'];
    }

    private function deterministicPlanCode(string $memberKey): string
    {
        return 'TT-PPL-2026-'.strtoupper((string) preg_replace('/[^a-z0-9]+/i', '-', $memberKey));
    }

    /** @param array<string, mixed> $record */
    private function deterministicItemCode(ThinkTankProcurementPlan $plan, array $record): string
    {
        return $plan->plan_code.'-'.strtoupper(substr(hash('sha256', $this->recordIdentity($record)), 0, 12));
    }

    /** @return array{key: string, version: int, fiscal_year: string} */
    private function migrationMarker(): array
    {
        return [
            'key' => self::MIGRATION_KEY,
            'version' => self::MIGRATION_VERSION,
            'fiscal_year' => self::FISCAL_YEAR,
        ];
    }

    private function planMarkerText(): string
    {
        return '[ATTP FY2026 audited Excel migration: '.self::MIGRATION_KEY.' v'.self::MIGRATION_VERSION.']';
    }
}
