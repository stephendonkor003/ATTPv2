<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SUB_ACTIVITY_ID = '019ea974-4ab8-71f8-b329-e73d161c84dd';

    private const PURCHASE_REQUEST_IDS = [
        '019ecfc6-c099-70f0-8137-573ca8de3c40',
        '019f33dc-1b00-72e5-85c8-99c354c02699',
    ];

    private const COMMITMENT_IDS = [
        '019ecfc6-c0b4-736c-b52a-dcc136779b05',
        '019f33dc-1b67-71af-b4ce-df06f3c5d3f8',
    ];

    private const PURCHASE_ORDER_IDS = [
        '019ecfca-af9e-7260-ac9c-087c8205e941',
        '019f33de-e6b8-7392-9898-0a42cab9975f',
    ];

    /**
     * Restore rows that PostgreSQL cascaded when the historical GRM
     * sub-activity was deleted. The immediately preceding migration restores
     * the classification itself, without restoring superseded allocations.
     *
     * Every restored value comes from the append-only system audit payloads.
     * File metadata is restored as recorded; this migration deliberately does
     * not create, delete, or alter any uploaded file.
     */
    public function up(): void
    {
        $requiredTables = [
            'system_audit_logs',
            'myb_sub_activities',
            'myb_purchase_requests',
            'myb_purchase_request_items',
            'myb_purchase_request_attachments',
            'myb_budget_commitments',
            'procurement_purchase_orders',
            'procurement_purchase_order_item_evidence',
            'procurement_disbursements',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                return;
            }
        }

        $affectedCommitmentCount = DB::table('myb_budget_commitments')
            ->whereIn('id', self::COMMITMENT_IDS)
            ->count();

        // Fresh databases and installations that never contained the damaged
        // live records do not need this targeted data repair.
        if ($affectedCommitmentCount === 0) {
            return;
        }

        if ($affectedCommitmentCount !== count(self::COMMITMENT_IDS)) {
            throw new RuntimeException(
                'The GRM lineage repair found only part of the expected commitment set; no repair was applied.'
            );
        }

        if (DB::connection()->getDriverName() !== 'pgsql') {
            throw new RuntimeException(
                'The GRM lineage repair requires PostgreSQL JSON audit operators.'
            );
        }

        if (! DB::table('myb_sub_activities')->where('id', self::SUB_ACTIVITY_ID)->exists()) {
            throw new RuntimeException(
                'The historical GRM classification must be restored before its purchase-request lineage.'
            );
        }

        DB::transaction(function (): void {
            $commitments = $this->auditedRows('myb_budget_commitments', self::COMMITMENT_IDS);
            $purchaseOrders = $this->auditedRows('procurement_purchase_orders', self::PURCHASE_ORDER_IDS);
            $purchaseRequests = $this->auditedRows('myb_purchase_requests', self::PURCHASE_REQUEST_IDS);

            $itemIds = $this->createdModelIdsByParent(
                'myb_purchase_request_items',
                'purchase_request_id',
                self::PURCHASE_REQUEST_IDS
            );
            $items = $this->restoreHistoricalItemDefaults(
                $this->auditedRows('myb_purchase_request_items', $itemIds)
            );

            $attachmentIds = $this->createdModelIdsByParent(
                'myb_purchase_request_attachments',
                'purchase_request_id',
                self::PURCHASE_REQUEST_IDS
            );
            $attachments = $this->auditedRows('myb_purchase_request_attachments', $attachmentIds);

            $evidenceIds = $this->createdModelIdsByParent(
                'procurement_purchase_order_item_evidence',
                'purchase_order_id',
                self::PURCHASE_ORDER_IDS
            );
            $evidence = $this->auditedRows('procurement_purchase_order_item_evidence', $evidenceIds);

            $disbursementIds = DB::table('procurement_disbursements')
                ->whereIn('purchase_order_id', self::PURCHASE_ORDER_IDS)
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
            $disbursements = $this->auditedRows('procurement_disbursements', $disbursementIds);

            $this->assertExpectedAuditSet($purchaseRequests, self::PURCHASE_REQUEST_IDS, 'purchase requests');
            $this->assertExpectedAuditSet($commitments, self::COMMITMENT_IDS, 'budget commitments');
            $this->assertExpectedAuditSet($purchaseOrders, self::PURCHASE_ORDER_IDS, 'purchase orders');

            if (count($items) !== 14 || count($evidence) !== 14 || count($disbursements) !== 14) {
                throw new RuntimeException(
                    'The GRM lineage audit trail is incomplete: expected 14 items, evidence rows, and disbursements.'
                );
            }

            // Five attachment records were created historically; one was
            // explicitly deleted before the cascade. Audit replay must leave
            // exactly the four records that were live at deletion time.
            if (count($attachmentIds) !== 5 || count($attachments) !== 4) {
                throw new RuntimeException(
                    'The GRM attachment audit trail is incomplete or does not replay to four live records.'
                );
            }

            $this->assertMoneyTotal($commitments, 'commitment_amount', '55208.11', 'commitments');
            $this->assertMoneyTotal($purchaseOrders, 'amount', '55208.11', 'purchase orders');
            $this->assertMoneyTotal($items, 'amount', '55208.11', 'purchase-request items');
            $this->assertMoneyTotal($disbursements, 'amount', '55208.11', 'disbursements');

            foreach ($purchaseRequests as $row) {
                if (($row['allocation_id'] ?? null) !== self::SUB_ACTIVITY_ID) {
                    throw new RuntimeException('A GRM purchase request has an unexpected audited allocation.');
                }
            }

            $this->insertMissingAuditedRows('myb_purchase_requests', $purchaseRequests, [
                'reference_no',
                'program_funding_id',
                'allocation_level',
                'allocation_id',
                'start_year',
                'total_amount',
                'status',
            ]);
            $this->insertMissingAuditedRows('myb_purchase_request_items', $items, [
                'purchase_request_id',
                'amount',
                'unit_price',
                'quantity',
            ]);
            $this->insertMissingAuditedRows('myb_purchase_request_attachments', $attachments, [
                'purchase_request_id',
                'file_path',
                'file_name',
            ]);
            $this->insertMissingAuditedRows('procurement_purchase_order_item_evidence', $evidence, [
                'purchase_order_id',
                'purchase_request_item_id',
                'is_met',
            ]);

            foreach ($commitments as $id => $audited) {
                $this->restoreNullableReference(
                    'myb_budget_commitments',
                    $id,
                    'purchase_request_id',
                    (string) ($audited['purchase_request_id'] ?? '')
                );
            }

            foreach ($purchaseOrders as $id => $audited) {
                $this->restoreNullableReference(
                    'procurement_purchase_orders',
                    $id,
                    'purchase_request_id',
                    (string) ($audited['purchase_request_id'] ?? '')
                );
            }

            foreach ($disbursements as $id => $audited) {
                $this->restoreNullableReference(
                    'procurement_disbursements',
                    $id,
                    'purchase_request_item_id',
                    (string) ($audited['purchase_request_item_id'] ?? '')
                );
            }
        });
    }

    public function down(): void
    {
        // The restored rows anchor posted commitments, purchase orders,
        // invoices, disbursements, and audit evidence. Deleting them during a
        // rollback would recreate the production data loss.
    }

    /**
     * Replay create/update/delete model audit events to their last recorded
     * state. Database-level cascade deletes emitted no model events, which is
     * why these rows remain present after replay.
     *
     * @param  array<int, string>  $ids
     * @return array<string, array<string, mixed>>
     */
    private function auditedRows(string $table, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $events = DB::table('system_audit_logs')
            ->whereRaw("payload->>'table' = ?", [$table])
            ->whereRaw("(payload->>'id') in ({$placeholders})", $ids)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['action', 'payload']);

        $states = [];

        foreach ($events as $event) {
            $payload = $this->decodePayload($event->payload);
            $id = (string) ($payload['id'] ?? '');

            if ($id === '' || ! in_array($id, $ids, true)) {
                continue;
            }

            if ($event->action === 'model_created') {
                $attributes = $payload['attributes'] ?? null;
                if (! is_array($attributes)) {
                    throw new RuntimeException("The {$table} creation audit payload is incomplete.");
                }

                $states[$id] = $attributes;
            } elseif ($event->action === 'model_updated') {
                $changes = $payload['changes'] ?? null;
                if (! isset($states[$id]) || ! is_array($changes)) {
                    throw new RuntimeException("The {$table} update audit payload cannot be replayed safely.");
                }

                $states[$id] = array_replace($states[$id], $changes);
            } elseif ($event->action === 'model_deleted') {
                unset($states[$id]);
            }
        }

        ksort($states);

        return $states;
    }

    /**
     * @param  array<int, string>  $parentIds
     * @return array<int, string>
     */
    private function createdModelIdsByParent(
        string $table,
        string $parentColumn,
        array $parentIds
    ): array {
        $allowedParentColumns = ['purchase_request_id', 'purchase_order_id'];
        if (! in_array($parentColumn, $allowedParentColumns, true)) {
            throw new RuntimeException('Unsupported audit parent column.');
        }

        $placeholders = implode(', ', array_fill(0, count($parentIds), '?'));
        $events = DB::table('system_audit_logs')
            ->where('action', 'model_created')
            ->whereRaw("payload->>'table' = ?", [$table])
            ->whereRaw(
                "(payload->'attributes'->>'{$parentColumn}') in ({$placeholders})",
                $parentIds
            )
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['payload']);

        return $events
            ->map(function ($event): string {
                $payload = $this->decodePayload($event->payload);

                return (string) ($payload['id'] ?? '');
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<int, string>  $ids
     */
    private function assertExpectedAuditSet(array $rows, array $ids, string $label): void
    {
        $actual = array_keys($rows);
        sort($actual);
        sort($ids);

        if ($actual !== $ids) {
            throw new RuntimeException("The audited {$label} do not match the expected production records.");
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     */
    private function assertMoneyTotal(array $rows, string $column, string $expected, string $label): void
    {
        $totalCents = 0;
        foreach ($rows as $row) {
            $totalCents += $this->moneyToCents($row[$column] ?? null);
        }

        if ($totalCents !== $this->moneyToCents($expected)) {
            throw new RuntimeException("The audited GRM {$label} do not total {$expected}.");
        }
    }

    private function moneyToCents(mixed $value): int
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw new RuntimeException('An audited money value is missing or invalid.');
        }

        $normalised = trim((string) $value);
        if (! preg_match('/^-?\d+(?:\.\d+)?$/', $normalised)) {
            throw new RuntimeException('An audited money value is not decimal.');
        }

        $negative = str_starts_with($normalised, '-');
        $normalised = ltrim($normalised, '-');
        [$whole, $fraction] = array_pad(explode('.', $normalised, 2), 2, '');
        $fraction = substr(str_pad($fraction, 2, '0'), 0, 2);
        $cents = ((int) $whole * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    /**
     * Four of the audited items were created immediately before the historical
     * migration that introduced unit_price and quantity, so those keys are not
     * present in their model_created payloads. Reapply that migration's exact
     * deterministic backfill rather than allowing today's column defaults to
     * replace the values that existed when the rows were cascaded.
     *
     * @param  array<string, array<string, mixed>>  $items
     * @return array<string, array<string, mixed>>
     */
    private function restoreHistoricalItemDefaults(array $items): array
    {
        foreach ($items as $id => $item) {
            if (! array_key_exists('unit_price', $item)) {
                if (! array_key_exists('amount', $item)) {
                    throw new RuntimeException(
                        "The audited purchase-request item {$id} has no amount for the historical unit-price backfill."
                    );
                }

                $item['unit_price'] = $item['amount'];
            }

            if (! array_key_exists('quantity', $item)) {
                $item['quantity'] = 1;
            }

            $items[$id] = $item;
        }

        return $items;
    }

    /**
     * @param  array<string, array<string, mixed>>  $rows
     * @param  array<int, string>  $requiredColumns
     */
    private function insertMissingAuditedRows(string $table, array $rows, array $requiredColumns): void
    {
        $availableColumns = array_flip(Schema::getColumnListing($table));

        foreach ($rows as $id => $auditedRow) {
            $auditedRow['id'] = $id;

            foreach ($requiredColumns as $column) {
                if (! array_key_exists($column, $auditedRow) || $auditedRow[$column] === null || $auditedRow[$column] === '') {
                    throw new RuntimeException("The audited {$table}.{$column} value is missing.");
                }
            }

            $existingRow = DB::table($table)
                ->where('id', $id)
                ->first($requiredColumns);

            if ($existingRow !== null) {
                foreach ($requiredColumns as $column) {
                    if (! $this->auditedValuesEquivalent($existingRow->{$column}, $auditedRow[$column])) {
                        throw new RuntimeException(
                            "The existing {$table}.{$column} value conflicts with the audited GRM lineage."
                        );
                    }
                }

                continue;
            }

            $row = array_intersect_key($auditedRow, $availableColumns);
            foreach ($row as $column => $value) {
                if (is_array($value) || is_object($value)) {
                    $row[$column] = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                }
            }

            DB::table($table)->insert($row);
        }
    }

    private function auditedValuesEquivalent(mixed $current, mixed $audited): bool
    {
        if ($current === null || $audited === null) {
            return $current === $audited;
        }

        if (is_bool($current) || is_bool($audited)) {
            $normaliseBoolean = static function (mixed $value): ?bool {
                if (is_bool($value)) {
                    return $value;
                }

                return match (strtolower(trim((string) $value))) {
                    '1', 'true', 't' => true,
                    '0', 'false', 'f' => false,
                    default => null,
                };
            };

            $currentBoolean = $normaliseBoolean($current);
            $auditedBoolean = $normaliseBoolean($audited);

            return $currentBoolean !== null
                && $auditedBoolean !== null
                && $currentBoolean === $auditedBoolean;
        }

        if (is_numeric($current) && is_numeric($audited)) {
            $normaliseDecimal = static function (mixed $value): string {
                $value = trim((string) $value);
                if (! preg_match('/^([+-]?)(\d+)(?:\.(\d+))?$/', $value, $matches)) {
                    return $value;
                }

                $whole = ltrim($matches[2], '0');
                $whole = $whole === '' ? '0' : $whole;
                $fraction = rtrim($matches[3] ?? '', '0');
                $normalised = $whole.($fraction === '' ? '' : ".{$fraction}");

                return $matches[1] === '-' && $normalised !== '0'
                    ? "-{$normalised}"
                    : $normalised;
            };

            return $normaliseDecimal($current) === $normaliseDecimal($audited);
        }

        return (string) $current === (string) $audited;
    }

    private function restoreNullableReference(
        string $table,
        string $id,
        string $column,
        string $expectedValue
    ): void {
        if ($expectedValue === '') {
            throw new RuntimeException("The audited {$table}.{$column} reference is missing.");
        }

        $currentValue = DB::table($table)->where('id', $id)->value($column);
        if ($currentValue !== null && (string) $currentValue !== $expectedValue) {
            throw new RuntimeException(
                "The existing {$table}.{$column} reference conflicts with the audited GRM lineage."
            );
        }

        if ($currentValue === null) {
            DB::table($table)->where('id', $id)->update([$column => $expectedValue]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(mixed $payload): array
    {
        if (is_array($payload)) {
            return $payload;
        }

        if (is_object($payload)) {
            return (array) $payload;
        }

        if (! is_string($payload) || $payload === '') {
            throw new RuntimeException('A required system audit payload is missing.');
        }

        $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($decoded)) {
            throw new RuntimeException('A required system audit payload is invalid.');
        }

        return $decoded;
    }
};
