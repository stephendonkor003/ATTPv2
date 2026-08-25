<?php

namespace App\Services\ApiSync;

use App\Exceptions\ApiSyncException;
use App\Models\ApiSyncPairing;
use App\Models\ProcurementPurchaseOrder;
use App\Support\ApiSyncAllocationIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ApiSyncDatasetService
{
    private ?bool $commitmentsHaveCreatedAt = null;

    private ?bool $commitmentsHaveUpdatedAt = null;

    public const DATASETS = [
        'portfolios',
        'programmes',
        'projects',
        'activities',
        'sub_activities',
        'fiscal_years',
        'budget_allocations',
        'commitments',
        'executions',
    ];

    /**
     * @return array{id: string, name: string, code: string, api_version: string}
     */
    public function instance(): array
    {
        $configuredId = trim((string) config('api_sync.provider.instance_id'));
        $instanceId = $configuredId !== ''
            ? $configuredId
            : 'attp-'.substr(hash('sha256', strtolower((string) config('app.url'))), 0, 24);

        return [
            'id' => $instanceId,
            'name' => (string) config('api_sync.provider.name', config('app.name', 'ATTP')),
            'code' => (string) config('api_sync.provider.code', 'ATTP'),
            'api_version' => 'v1',
        ];
    }

    /**
     * Read a deterministic source chunk for snapshot materialization. This is
     * never called by an HTTP export endpoint; consumers read only frozen rows.
     *
     * @param  array<string, int|string>  $position
     * @return array{data: list<array<string, mixed>>, has_more: bool, next_position: array<string, int|string>|null}
     */
    public function materializationPage(
        ApiSyncPairing $pairing,
        string $dataset,
        array $position,
        int $requestedLimit,
    ): array {
        if (! in_array($dataset, self::DATASETS, true)) {
            throw new ApiSyncException('unknown_dataset', 'The requested synchronization dataset is not available.', 404);
        }

        $limit = max(1, min($requestedLimit, 1_000));

        [$rows, $hasMore, $nextPosition] = $dataset === 'fiscal_years'
            ? $this->fiscalYearPage($pairing, $position, $limit)
            : $this->databasePage($dataset, $pairing, $position, $limit);

        return [
            'data' => $rows->map(fn (object $row): array => $this->record($dataset, $row))->values()->all(),
            'has_more' => $hasMore,
            'next_position' => $hasMore ? $nextPosition : null,
        ];
    }

    /**
     * @param  array<string, int|string>  $position
     * @return array{Collection<int, object>, bool, array<string, int|string>|null}
     */
    private function databasePage(string $dataset, ApiSyncPairing $pairing, array $position, int $limit): array
    {
        $query = DB::query()->fromSub($this->query($dataset, $pairing), 'sync_rows');

        if ($dataset === 'budget_allocations') {
            $sourceOrder = (int) ($position['source_order'] ?? 0);
            $lastId = (string) ($position['id'] ?? '');
            if ($sourceOrder > 0 && $lastId !== '') {
                $query->where(function (Builder $cursorQuery) use ($sourceOrder, $lastId): void {
                    $cursorQuery
                        ->where('source_order', '>', $sourceOrder)
                        ->orWhere(function (Builder $sameSource) use ($sourceOrder, $lastId): void {
                            $sameSource->where('source_order', $sourceOrder)->where('id', '>', $lastId);
                        });
                });
            }
            $query->orderBy('source_order')->orderBy('id');
        } else {
            $lastId = (string) ($position['id'] ?? '');
            if ($lastId !== '') {
                $query->where('id', '>', $lastId);
            }
            $query->orderBy('id');
        }

        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $last = $rows->last();

        return [
            $rows,
            $hasMore,
            $last ? ($dataset === 'budget_allocations'
                ? ['source_order' => (int) $last->source_order, 'id' => (string) $last->id]
                : ['id' => (string) $last->id]) : null,
        ];
    }

    /**
     * @param  array<string, int|string>  $position
     * @return array{Collection<int, object>, bool, array<string, int|string>|null}
     */
    private function fiscalYearPage(ApiSyncPairing $pairing, array $position, int $limit): array
    {
        $lastYear = (int) ($position['year'] ?? 0);
        $years = collect($this->fiscalYears($pairing))
            ->filter(fn (int $year): bool => $year > $lastYear)
            ->values();
        $hasMore = $years->count() > $limit;
        $rows = $years->take($limit)->map(fn (int $year): object => (object) ['id' => 'FY-'.$year, 'year' => $year]);
        $last = $rows->last();

        return [$rows, $hasMore, $last ? ['year' => (int) $last->year] : null];
    }

    private function query(string $dataset, ApiSyncPairing $pairing): Builder
    {
        return match ($dataset) {
            'portfolios' => $this->portfolioQuery($pairing),
            'programmes' => $this->programmeQuery($pairing),
            'projects' => $this->projectQuery($pairing),
            'activities' => $this->activityQuery($pairing),
            'sub_activities' => $this->subActivityQuery($pairing),
            'budget_allocations' => $this->allocationQuery($pairing),
            'commitments' => $this->commitmentQuery($pairing),
            'executions' => $this->executionQuery($pairing),
            default => throw new ApiSyncException('unknown_dataset', 'The requested synchronization dataset is not available.', 404),
        };
    }

    private function portfolioQuery(ApiSyncPairing $pairing): Builder
    {
        return $this->atSnapshot(DB::table('myb_sectors as s'), 's', $pairing)
            ->select([
                's.id', 's.name', 's.description', 's.status', 's.currency',
                's.portfolio_manager_name', 's.portfolio_manager_role', 's.governance_node_id',
                's.created_at', 's.updated_at',
            ]);
    }

    private function programmeQuery(ApiSyncPairing $pairing): Builder
    {
        return $this->atSnapshot(DB::table('myb_programs as p'), 'p', $pairing)
            ->select([
                'p.id', 'p.program_id as source_code', 'p.sector_id as portfolio_id',
                'p.name', 'p.description', 'p.currency', 'p.start_year', 'p.end_year',
                'p.total_years', 'p.total_budget', 'p.expected_outcome_type',
                'p.expected_outcome_value', 'p.department_id', 'p.governance_node_id',
                'p.created_at', 'p.updated_at',
            ]);
    }

    private function projectQuery(ApiSyncPairing $pairing): Builder
    {
        return $this->atSnapshot(
            DB::table('myb_projects as p')->leftJoin('myb_programs as g', 'g.id', '=', 'p.program_id'),
            'p',
            $pairing,
        )->select([
            'p.id', 'p.project_id as source_code', 'p.program_id', 'g.sector_id as portfolio_id',
            'p.name', 'p.description', 'p.currency', 'p.start_year', 'p.end_year',
            'p.total_years', 'p.total_budget', 'p.expected_outcome_type',
            'p.expected_outcome_value', 'p.governance_node_id', 'p.created_at', 'p.updated_at',
        ]);
    }

    private function activityQuery(ApiSyncPairing $pairing): Builder
    {
        return $this->atSnapshot(
            DB::table('myb_activities as a')
                ->leftJoin('myb_projects as p', 'p.id', '=', 'a.project_id')
                ->leftJoin('myb_programs as g', 'g.id', '=', 'p.program_id'),
            'a',
            $pairing,
        )->select([
            'a.id', 'a.project_id', 'p.program_id', 'g.sector_id as portfolio_id',
            'a.name', 'a.description', 'a.expected_outcome_type', 'a.expected_outcome_value',
            'a.governance_node_id', 'a.created_at', 'a.updated_at',
        ]);
    }

    private function subActivityQuery(ApiSyncPairing $pairing): Builder
    {
        return $this->atSnapshot(
            DB::table('myb_sub_activities as s')
                ->leftJoin('myb_activities as a', 'a.id', '=', 's.activity_id')
                ->leftJoin('myb_projects as p', 'p.id', '=', 'a.project_id')
                ->leftJoin('myb_programs as g', 'g.id', '=', 'p.program_id'),
            's',
            $pairing,
        )->select([
            's.id', 's.activity_id', 'a.project_id', 'p.program_id', 'g.sector_id as portfolio_id',
            's.name', 's.description', 's.expected_outcome_type', 's.expected_outcome_value',
            's.governance_node_id', 's.created_at', 's.updated_at',
        ]);
    }

    private function allocationQuery(ApiSyncPairing $pairing): Builder
    {
        $project = DB::table('myb_project_allocations as a')->selectRaw(
            "1 as source_order, a.id, 'project' as level, a.project_id as target_id, "
            .'COALESCE(a.actual_year, a.year) as fiscal_year, a.amount, NULL as created_at, NULL as updated_at'
        );

        $activity = $this->atSnapshot(DB::table('myb_activity_allocations as a'), 'a', $pairing)->selectRaw(
            "2 as source_order, a.id, 'activity' as level, a.activity_id as target_id, "
            .'a.year as fiscal_year, a.amount, a.created_at, a.updated_at'
        );

        $subActivity = $this->atSnapshot(DB::table('myb_sub_activity_allocations as a'), 'a', $pairing)->selectRaw(
            "3 as source_order, a.id, 'sub_activity' as level, a.sub_activity_id as target_id, "
            .'a.year as fiscal_year, a.amount, a.created_at, a.updated_at'
        );

        return $project->unionAll($activity)->unionAll($subActivity);
    }

    private function commitmentQuery(ApiSyncPairing $pairing): Builder
    {
        $query = DB::table('myb_budget_commitments as c')
            ->leftJoin('myb_program_fundings as pf', 'pf.id', '=', 'c.program_funding_id')
            ->leftJoin('myb_programs as pg', 'pg.id', '=', 'pf.program_id')
            ->leftJoin('myb_resource_categories as rc', 'rc.id', '=', 'c.resource_category_id')
            ->leftJoin('myb_resources as r', 'r.id', '=', 'c.resource_id')
            ->select([
                'c.id', 'c.allocation_level', 'c.allocation_id', 'c.commitment_amount',
                'c.commitment_year', 'c.status', 'c.description', 'c.approved_at',
                'c.program_funding_id', 'c.resource_category_id', 'c.resource_id',
                'c.governance_node_id',
                'pf.program_id', 'rc.name as resource_category_name', 'r.name as resource_name',
                'r.reference_code as resource_reference_code',
                DB::raw('COALESCE(pf.currency, pg.currency) as currency'),
            ]);

        if ($this->commitmentsHaveCreatedAt()) {
            $query->addSelect('c.created_at');
        } else {
            $query->addSelect(DB::raw('NULL as created_at'));
        }

        if ($this->commitmentsHaveUpdatedAt()) {
            $query->addSelect('c.updated_at');
        } else {
            $query->addSelect(DB::raw('NULL as updated_at'));
        }

        return $this->applyCommitmentSnapshot($query, $pairing, 'c');
    }

    private function executionQuery(ApiSyncPairing $pairing): Builder
    {
        $query = $this->atSnapshot(
            DB::table('procurement_disbursements as d')
                ->leftJoin('procurement_purchase_orders as po', 'po.id', '=', 'd.purchase_order_id')
                ->leftJoin('myb_budget_commitments as bc', function (JoinClause $join) use ($pairing): void {
                    $join->on('bc.id', '=', 'po.budget_commitment_id');
                    $this->applyCommitmentSnapshot($join, $pairing, 'bc');
                }),
            'd',
            $pairing,
        )->whereNotNull('d.paid_at')
            ->where('d.paid_at', '<=', $pairing->snapshot_at)
            ->whereIn('d.status', ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES)
            ->where(function (Builder $source): void {
                $source
                    ->whereExists(fn (Builder $exists) => $exists->selectRaw('1')
                        ->from('procurement_purchase_orders as source_po')
                        ->whereColumn('source_po.id', 'd.purchase_order_id'))
                    ->orWhereExists(fn (Builder $exists) => $exists->selectRaw('1')
                        ->from('procurements as source_procurement')
                        ->whereColumn('source_procurement.id', 'd.procurement_id'))
                    ->orWhereExists(fn (Builder $exists) => $exists->selectRaw('1')
                        ->from('attp_fund_allocations as source_funding')
                        ->whereColumn('source_funding.id', 'd.fund_allocation_id'))
                    ->orWhereExists(fn (Builder $exists) => $exists->selectRaw('1')
                        ->from('attp_disbursement_requests as source_request')
                        ->whereColumn('source_request.id', 'd.consortium_disbursement_request_id'));
            });

        return $query->select([
            'd.id', 'd.reference_no', 'd.amount', 'd.currency', 'd.status', 'd.paid_at',
            'd.sub_activity_id', 'd.purchase_order_id', 'po.budget_commitment_id',
            'bc.allocation_level as commitment_allocation_level',
            'bc.allocation_id as commitment_allocation_id',
            'd.governance_node_id', 'd.created_at', 'd.updated_at',
        ]);
    }

    private function commitmentsHaveCreatedAt(): bool
    {
        return $this->commitmentsHaveCreatedAt ??= Schema::hasColumn('myb_budget_commitments', 'created_at');
    }

    private function commitmentsHaveUpdatedAt(): bool
    {
        return $this->commitmentsHaveUpdatedAt ??= Schema::hasColumn('myb_budget_commitments', 'updated_at');
    }

    private function applyCommitmentSnapshot(Builder $query, ApiSyncPairing $pairing, string $alias): Builder
    {
        if ($this->commitmentsHaveCreatedAt()) {
            $query->where(function (Builder $snapshot) use ($alias, $pairing): void {
                $snapshot
                    ->where(function (Builder $created) use ($alias, $pairing): void {
                        $created->whereNotNull("{$alias}.created_at")
                            ->where("{$alias}.created_at", '<=', $pairing->snapshot_at);
                    })
                    ->orWhere(function (Builder $legacy) use ($alias, $pairing): void {
                        $legacy->whereNull("{$alias}.created_at")
                            ->where(function (Builder $approval) use ($alias, $pairing): void {
                                $approval->whereNull("{$alias}.approved_at")
                                    ->orWhere("{$alias}.approved_at", '<=', $pairing->snapshot_at);
                            });
                    });
            });
        } else {
            $query->where(function (Builder $approval) use ($alias, $pairing): void {
                $approval->whereNull("{$alias}.approved_at")
                    ->orWhere("{$alias}.approved_at", '<=', $pairing->snapshot_at);
            });
        }

        if ($this->commitmentsHaveUpdatedAt()) {
            $query->where(function (Builder $updated) use ($alias, $pairing): void {
                $updated->whereNull("{$alias}.updated_at")
                    ->orWhere("{$alias}.updated_at", '<=', $pairing->snapshot_at);
            });
        }

        return $query;
    }

    private function atSnapshot(Builder $query, string $alias, ApiSyncPairing $pairing): Builder
    {
        return $query
            ->where(function (Builder $created) use ($alias, $pairing): void {
                $created->whereNull("{$alias}.created_at")
                    ->orWhere("{$alias}.created_at", '<=', $pairing->snapshot_at);
            })
            ->where(function (Builder $updated) use ($alias, $pairing): void {
                $updated->whereNull("{$alias}.updated_at")
                    ->orWhere("{$alias}.updated_at", '<=', $pairing->snapshot_at);
            });
    }

    /**
     * @return list<int>
     */
    private function fiscalYears(ApiSyncPairing $pairing): array
    {
        $years = collect();
        $sources = [
            $this->atSnapshot(DB::table('myb_programs as p'), 'p', $pairing)
                ->select('p.start_year as year')->whereNotNull('p.start_year'),
            $this->atSnapshot(DB::table('myb_programs as p'), 'p', $pairing)
                ->select('p.end_year as year')->whereNotNull('p.end_year'),
            $this->atSnapshot(DB::table('myb_projects as p'), 'p', $pairing)
                ->select('p.start_year as year')->whereNotNull('p.start_year'),
            $this->atSnapshot(DB::table('myb_projects as p'), 'p', $pairing)
                ->select('p.end_year as year')->whereNotNull('p.end_year'),
            DB::table('myb_project_allocations')->selectRaw('COALESCE(actual_year, year) as year'),
            $this->atSnapshot(DB::table('myb_activity_allocations as a'), 'a', $pairing)->select('a.year'),
            $this->atSnapshot(DB::table('myb_sub_activity_allocations as a'), 'a', $pairing)->select('a.year'),
            DB::query()->fromSub($this->commitmentQuery($pairing), 'commitments')->select('commitment_year as year'),
        ];

        foreach ($sources as $source) {
            $years = $years->merge($source->distinct()->pluck('year'));
        }

        $minimum = $years->filter(fn (mixed $year): bool => is_numeric($year) && (int) $year >= 1900 && (int) $year <= 2200)->min();
        $maximum = $years->filter(fn (mixed $year): bool => is_numeric($year) && (int) $year >= 1900 && (int) $year <= 2200)->max();

        return ($minimum && $maximum)
            ? range((int) $minimum, (int) $maximum)
            : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function record(string $dataset, object $row): array
    {
        [$attributes, $relationships, $updatedAt] = match ($dataset) {
            'portfolios' => [[
                'name' => $row->name,
                'description' => $this->text($row->description),
                'status' => $row->status,
                'currency' => $row->currency,
                'portfolio_manager_name' => $row->portfolio_manager_name,
                'portfolio_manager_role' => $row->portfolio_manager_role,
            ], ['governance_unit' => $row->governance_node_id], $row->updated_at],
            'programmes' => [[
                'source_code' => $row->source_code,
                'name' => $row->name,
                'description' => $this->text($row->description),
                'currency' => $row->currency,
                'start_year' => $row->start_year,
                'end_year' => $row->end_year,
                'duration_years' => $row->total_years,
                'total_budget' => $this->decimal($row->total_budget),
                'expected_outcome_type' => $row->expected_outcome_type,
                'expected_outcome_value' => $this->text($row->expected_outcome_value),
            ], [
                'portfolio' => $row->portfolio_id,
                'department' => $row->department_id,
                'governance_unit' => $row->governance_node_id,
            ], $row->updated_at],
            'projects' => [[
                'source_code' => $row->source_code,
                'name' => $row->name,
                'description' => $this->text($row->description),
                'currency' => $row->currency,
                'start_year' => $row->start_year,
                'end_year' => $row->end_year,
                'duration_years' => $row->total_years,
                'total_budget' => $this->decimal($row->total_budget),
                'expected_outcome_type' => $row->expected_outcome_type,
                'expected_outcome_value' => $this->text($row->expected_outcome_value),
            ], [
                'portfolio' => $row->portfolio_id,
                'programme' => $row->program_id,
                'governance_unit' => $row->governance_node_id,
            ], $row->updated_at],
            'activities' => [[
                'name' => $row->name,
                'description' => $this->text($row->description),
                'expected_outcome_type' => $row->expected_outcome_type,
                'expected_outcome_value' => $this->text($row->expected_outcome_value),
            ], [
                'portfolio' => $row->portfolio_id,
                'programme' => $row->program_id,
                'project' => $row->project_id,
                'governance_unit' => $row->governance_node_id,
            ], $row->updated_at],
            'sub_activities' => [[
                'name' => $row->name,
                'description' => $this->text($row->description),
                'expected_outcome_type' => $row->expected_outcome_type,
                'expected_outcome_value' => $this->text($row->expected_outcome_value),
            ], [
                'portfolio' => $row->portfolio_id,
                'programme' => $row->program_id,
                'project' => $row->project_id,
                'activity' => $row->activity_id,
                'governance_unit' => $row->governance_node_id,
            ], $row->updated_at],
            'fiscal_years' => [[
                'year' => (int) $row->year,
                'name' => 'Fiscal Year '.$row->year,
                'starts_on' => $row->year.'-01-01',
                'ends_on' => $row->year.'-12-31',
                'calendar_basis' => 'calendar_year',
            ], [], null],
            'budget_allocations' => [[
                'level' => ApiSyncAllocationIdentity::normalizeLevel($row->level),
                'year' => (int) $row->fiscal_year,
                'amount' => $this->decimal($row->amount),
                'currency' => null,
            ], $this->budgetAllocationRelationships($row), $row->updated_at],
            'commitments' => [[
                'allocation_level' => ApiSyncAllocationIdentity::normalizeLevel($row->allocation_level),
                'year' => $row->commitment_year ? (int) $row->commitment_year : null,
                'amount' => $this->decimal($row->commitment_amount),
                'currency' => $row->currency,
                'status' => $row->status,
                'description' => $this->text($row->description),
                'resource_category_name' => $row->resource_category_name,
                'resource_name' => $row->resource_name,
                'resource_reference_code' => $row->resource_reference_code,
                'approved_at' => $this->dateTime($row->approved_at),
            ], array_filter([
                'allocation_target' => ApiSyncAllocationIdentity::externalId($row->allocation_level, $row->allocation_id),
                'fiscal_year' => $row->commitment_year ? 'FY-'.$row->commitment_year : null,
                'program_funding' => $row->program_funding_id,
                'programme' => $row->program_id,
                'resource_category' => $row->resource_category_id,
                'resource' => $row->resource_id,
                'governance_unit' => $row->governance_node_id,
            ]), $row->updated_at ?: $row->approved_at ?: $row->created_at],
            'executions' => [[
                'reference' => $row->reference_no,
                'amount' => $this->decimal($row->amount),
                'currency' => $row->currency,
                'status' => $row->status,
                'paid_at' => $this->dateTime($row->paid_at),
            ], array_filter([
                'sub_activity' => $row->sub_activity_id,
                'commitment' => $row->budget_commitment_id,
                'allocation_target' => ApiSyncAllocationIdentity::externalId(
                    $row->commitment_allocation_level,
                    $row->commitment_allocation_id,
                ),
                'purchase_order' => $row->purchase_order_id,
                'fiscal_year' => $row->paid_at ? 'FY-'.CarbonImmutable::parse($row->paid_at)->year : null,
                'governance_unit' => $row->governance_node_id,
            ]), $row->updated_at ?: $row->paid_at],
        };

        $payload = [
            'attributes' => $attributes,
            'relationships' => array_filter($relationships, static fn (mixed $value): bool => $value !== null && $value !== ''),
        ];

        $recordId = $dataset === 'budget_allocations'
            ? ApiSyncAllocationIdentity::externalId($row->level, $row->id)
            : (string) $row->id;

        if ($recordId === null) {
            throw new \LogicException('An API Sync allocation row has no canonical external identifier.');
        }

        return [
            'id' => $recordId,
            'checksum' => hash('sha256', json_encode($this->sortRecursively($payload), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'updated_at' => $this->dateTime($updatedAt),
            ...$payload,
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    private function budgetAllocationRelationships(object $row): array
    {
        $level = ApiSyncAllocationIdentity::normalizeLevel($row->level);
        $relationships = [
            'allocation_target' => ApiSyncAllocationIdentity::externalId($level, $row->target_id),
            'fiscal_year' => 'FY-'.$row->fiscal_year,
        ];

        if ($level !== null) {
            $relationships[$level] = $row->target_id;
        }

        return array_filter($relationships, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    private function dateTime(mixed $value): ?string
    {
        return $value ? CarbonImmutable::parse($value)->utc()->format('Y-m-d\TH:i:s\Z') : null;
    }

    private function decimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $raw = trim((string) $value);
        if (! preg_match('/^(-?)(\d+)(?:\.(\d+))?$/', $raw, $matches)) {
            return $raw;
        }

        $integer = ltrim($matches[2], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad(substr($matches[3] ?? '', 0, 2), 2, '0');

        return $matches[1].$integer.'.'.$fraction;
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return mb_substr((string) $value, 0, 20_000);
    }

    /**
     * @return array<mixed>
     */
    private function sortRecursively(array $value): array
    {
        ksort($value);
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->sortRecursively($item);
            }
        }

        return $value;
    }
}
