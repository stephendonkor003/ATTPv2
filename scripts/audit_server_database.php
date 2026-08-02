<?php

use App\Models\ProcurementDisbursement;
use App\Models\ProcurementPurchaseOrder;
use App\Services\FundingToThinkTanksAllocationReconciler;
use App\Services\MeReportingReadinessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if (! str_starts_with($database, 'attp_server_audit_')) {
    throw new RuntimeException("Safety stop: expected an isolated server-audit database, connected to [{$database}].");
}

$round = static fn ($value): float => round((float) $value, 2);
$exists = static fn (string $table): bool => Schema::hasTable($table);
$count = static fn (string $table): int => Schema::hasTable($table) ? DB::table($table)->count() : 0;
$grouped = static function (string $table, string $column): array {
    if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
        return [];
    }

    return DB::table($table)
        ->selectRaw("COALESCE(CAST({$column} AS TEXT), '[null]') AS key, COUNT(*) AS records")
        ->groupBy($column)
        ->orderBy($column)
        ->get()
        ->mapWithKeys(fn ($row): array => [(string) $row->key => (int) $row->records])
        ->all();
};

$result = [
    'audit_meta' => [
        'database' => $database,
        'generated_at' => now()->toIso8601String(),
        'public_tables' => (int) DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->count(),
        'migration_count' => $count('migrations'),
        'latest_migration_batch' => $exists('migrations') ? (int) DB::table('migrations')->max('batch') : null,
    ],
];

if ($exists('procurement_disbursements')) {
    $base = ProcurementDisbursement::query();
    $paidStatuses = ProcurementPurchaseOrder::PAID_DISBURSEMENT_STATUSES;
    $nonPayingStatuses = ProcurementPurchaseOrder::NON_PAYING_DISBURSEMENT_STATUSES;
    $pending = ProcurementDisbursement::query()
        ->where(function (Builder $query) use ($paidStatuses): void {
            $query->whereNull('paid_at')->orWhereNull('status')->orWhereNotIn('status', $paidStatuses);
        })
        ->where(function (Builder $query) use ($nonPayingStatuses): void {
            $query->whereNull('status')->orWhereNotIn('status', $nonPayingStatuses);
        });
    $unsupported = ProcurementDisbursement::query()
        ->whereNotNull('paid_at')
        ->whereIn('status', $paidStatuses)
        ->whereDoesntHave('purchaseOrder')
        ->whereDoesntHave('procurement')
        ->whereDoesntHave('fundAllocation')
        ->whereDoesntHave('consortiumDisbursementRequest');

    $result['procurement_disbursements'] = [
        'records' => (clone $base)->count(),
        'gross_recorded_amount' => $round((clone $base)->sum('amount')),
        'recognized_payment_records' => ProcurementDisbursement::recognizedPayment()->count(),
        'recognized_payment_amount' => $round(ProcurementDisbursement::recognizedPayment()->sum('amount')),
        'pending_other_records_under_current_screen_rule' => (clone $pending)->count(),
        'pending_other_amount_under_current_screen_rule' => $round((clone $pending)->sum('amount')),
        'unsupported_paid_records' => (clone $unsupported)->count(),
        'unsupported_paid_amount' => $round((clone $unsupported)->sum('amount')),
        'status_totals' => DB::table('procurement_disbursements')
            ->selectRaw("COALESCE(status, '[null]') AS status, COUNT(*) AS records, SUM(amount) AS amount")
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => [
                'status' => $row->status,
                'records' => (int) $row->records,
                'amount' => $round($row->amount),
            ])->all(),
        'unsupported_paid_detail' => (clone $unsupported)
            ->orderByDesc('amount')
            ->get(['id', 'reference_no', 'amount', 'status', 'paid_at', 'sub_activity_id'])
            ->map(fn ($row): array => [
                'id' => (string) $row->id,
                'reference' => $row->reference_no,
                'amount' => $round($row->amount),
                'status' => $row->status,
                'paid_at' => $row->paid_at?->toDateString(),
                'sub_activity_id' => (string) $row->sub_activity_id,
            ])->all(),
        'duplicate_references' => DB::table('procurement_disbursements')
            ->whereNotNull('reference_no')
            ->selectRaw('reference_no, COUNT(*) AS records, SUM(amount) AS amount')
            ->groupBy('reference_no')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('records')
            ->get()
            ->map(fn ($row): array => [
                'reference' => $row->reference_no,
                'records' => (int) $row->records,
                'amount' => $round($row->amount),
            ])->all(),
        'duplicate_payment_signatures' => DB::table('procurement_disbursements')
            ->whereNotNull('paid_at')
            ->selectRaw('purchase_order_id, purchase_request_item_id, amount, paid_at::date AS paid_date, COUNT(*) AS records')
            ->groupBy('purchase_order_id', 'purchase_request_item_id', 'amount', DB::raw('paid_at::date'))
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('records')
            ->limit(25)
            ->get()
            ->map(fn ($row): array => [
                'purchase_order_id' => $row->purchase_order_id,
                'purchase_request_item_id' => $row->purchase_request_item_id,
                'amount' => $round($row->amount),
                'paid_date' => $row->paid_date,
                'records' => (int) $row->records,
            ])->all(),
    ];
}

$targetSubActivityId = FundingToThinkTanksAllocationReconciler::TARGET_SUB_ACTIVITY_ID;
$targetSubActivity = $exists('myb_sub_activities')
    ? App\Models\SubActivity::query()->with('activity.project')->find($targetSubActivityId)
    : null;
$allocationPreview = $targetSubActivity
    ? app(FundingToThinkTanksAllocationReconciler::class)->preview($targetSubActivity)
    : ['status' => 'missing'];

$projectOverruns = $exists('myb_project_allocations') && $exists('myb_activity_allocations')
    ? DB::select(<<<'SQL'
        WITH project_budget AS (
            SELECT project_id, year, SUM(amount) AS project_amount
            FROM myb_project_allocations
            GROUP BY project_id, year
        ), activity_budget AS (
            SELECT a.project_id, aa.year, SUM(aa.amount) AS activity_amount
            FROM myb_activity_allocations aa
            JOIN myb_activities a ON a.id = aa.activity_id
            GROUP BY a.project_id, aa.year
        )
        SELECT ab.project_id, ab.year, COALESCE(pb.project_amount, 0) AS parent_amount,
               ab.activity_amount AS child_amount,
               ab.activity_amount - COALESCE(pb.project_amount, 0) AS excess
        FROM activity_budget ab
        LEFT JOIN project_budget pb ON pb.project_id = ab.project_id AND pb.year = ab.year
        WHERE ab.activity_amount > COALESCE(pb.project_amount, 0) + 0.004
        ORDER BY excess DESC
        SQL)
    : [];

$activityOverruns = $exists('myb_activity_allocations') && $exists('myb_sub_activity_allocations')
    ? DB::select(<<<'SQL'
        WITH activity_budget AS (
            SELECT activity_id, year, SUM(amount) AS activity_amount
            FROM myb_activity_allocations
            GROUP BY activity_id, year
        ), sub_budget AS (
            SELECT s.activity_id, sa.year, SUM(sa.amount) AS sub_amount
            FROM myb_sub_activity_allocations sa
            JOIN myb_sub_activities s ON s.id = sa.sub_activity_id
            GROUP BY s.activity_id, sa.year
        )
        SELECT sb.activity_id, sb.year, COALESCE(ab.activity_amount, 0) AS parent_amount,
               sb.sub_amount AS child_amount,
               sb.sub_amount - COALESCE(ab.activity_amount, 0) AS excess
        FROM sub_budget sb
        LEFT JOIN activity_budget ab ON ab.activity_id = sb.activity_id AND ab.year = sb.year
        WHERE sb.sub_amount > COALESCE(ab.activity_amount, 0) + 0.004
        ORDER BY excess DESC
        SQL)
    : [];

$mapOverruns = static fn (array $rows): array => collect($rows)->map(fn ($row): array => [
    'parent_id' => (string) ($row->project_id ?? $row->activity_id),
    'year' => (int) $row->year,
    'parent_amount' => $round($row->parent_amount),
    'child_amount' => $round($row->child_amount),
    'excess' => $round($row->excess),
])->all();

$result['budget_hierarchy'] = [
    'target_sub_activity' => $targetSubActivity ? [
        'id' => (string) $targetSubActivity->id,
        'name' => $targetSubActivity->name,
        'activity_id' => (string) $targetSubActivity->activity_id,
        'activity' => $targetSubActivity->activity?->name,
        'project_id' => (string) $targetSubActivity->activity?->project_id,
        'project' => $targetSubActivity->activity?->project?->name,
    ] : null,
    'target_reconciliation_preview' => $allocationPreview,
    'project_year_overruns' => $mapOverruns($projectOverruns),
    'activity_year_overruns' => $mapOverruns($activityOverruns),
];

$meTables = [
    'myb_indicators', 'me_indicator_results', 'me_data_entry_forms', 'me_data_entry_form_indicators',
    'me_reporting_periods', 'me_data_collections', 'me_data_collection_assignments',
    'me_performance_reports', 'me_performance_report_indicator_results', 'me_performance_report_documents',
    'me_indicator_achievements', 'me_indicator_achievement_disaggregations',
    'me_knowledge_evidence_items', 'me_repository_document_versions', 'me_repository_document_links',
    'me_matrix_versions', 'me_focal_unit_contacts',
];

$meCounts = [];
foreach ($meTables as $table) {
    $meCounts[$table] = $count($table);
}

$activeThinkTanks = $exists('attp_consortium_think_tanks')
    ? DB::table('attp_consortium_think_tanks')->where('status', 'active')->count()
    : 0;
$mappedOrganizations = $exists('me_focal_unit_contacts')
    ? DB::table('me_focal_unit_contacts')->where('is_active', true)->whereNotNull('think_tank_member_id')->distinct()->count('think_tank_member_id')
    : 0;

$indicatorCompleteness = [];
if ($exists('myb_indicators')) {
    $indicatorCompleteness = [
        'total' => DB::table('myb_indicators')->count(),
        'missing_code' => DB::table('myb_indicators')->whereNull('indicator_code')->orWhere('indicator_code', '')->count(),
        'missing_frequency' => DB::table('myb_indicators')->whereNull('frequency_of_reporting_id')->count(),
        'missing_unit' => DB::table('myb_indicators')->whereNull('unit_id')->count(),
        'missing_baseline' => DB::table('myb_indicators')->whereNull('baseline_value')->count(),
        'missing_annual_target' => DB::table('myb_indicators')->whereNull('annual_target')->count(),
        'missing_life_target' => DB::table('myb_indicators')->whereNull('life_of_programme_target')->count(),
        'missing_mov' => DB::table('myb_indicators')->whereNull('means_of_verification_id')->count(),
        'missing_responsible_user' => DB::table('myb_indicators')->whereNull('responsible_user_id')->count(),
        'duplicate_codes' => DB::table('myb_indicators')
            ->whereNotNull('indicator_code')
            ->selectRaw('indicator_code, COUNT(*) AS records')
            ->groupBy('indicator_code')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($row): array => ['code' => $row->indicator_code, 'records' => (int) $row->records])
            ->all(),
    ];
}

$result['monitoring_and_reporting'] = [
    'table_counts' => $meCounts,
    'active_think_tanks' => $activeThinkTanks,
    'focal_organizations_mapped' => $mappedOrganizations,
    'focal_contacts_linked_to_accounts' => $exists('me_focal_unit_contacts')
        ? DB::table('me_focal_unit_contacts')->whereNotNull('user_id')->count()
        : 0,
    'indicator_completeness' => $indicatorCompleteness,
    'form_statuses' => $grouped('me_data_entry_forms', 'status'),
    'collection_statuses' => $grouped('me_data_collections', 'status'),
    'performance_report_statuses' => $grouped('me_performance_reports', 'status'),
    'indicator_result_review_statuses' => $grouped('me_indicator_results', 'review_status'),
    'think_tank_access_levels' => $exists('users') && Schema::hasColumn('users', 'think_tank_access_level')
        ? DB::table('users')
            ->where('user_type', 'think_tank')
            ->selectRaw("COALESCE(think_tank_access_level, '[null]') AS access_level, COUNT(*) AS records")
            ->groupBy('think_tank_access_level')
            ->get()
            ->mapWithKeys(fn ($row): array => [$row->access_level => (int) $row->records])
            ->all()
        : [],
    'think_tank_reporting_account_diagnostics' => $exists('users') ? [
        'think_tank_users' => DB::table('users')->where('user_type', 'think_tank')->count(),
        'with_direct_organization_link' => DB::table('users')->whereNotNull('think_tank_member_id')->count(),
        'with_me_or_admin_access' => DB::table('users')->whereIn('think_tank_access_level', ['think_tank_admin', 'me_officer'])->count(),
        'enabled_me_or_admin_with_direct_link' => DB::table('users')
            ->whereNotNull('think_tank_member_id')
            ->whereIn('think_tank_access_level', ['think_tank_admin', 'me_officer'])
            ->where(fn ($query) => $query->whereNull('is_disabled')->orWhere('is_disabled', false))
            ->where(fn ($query) => $query->whereNull('is_blacklisted')->orWhere('is_blacklisted', false))
            ->count(),
        'direct_link_disabled_states' => DB::table('users')
            ->whereNotNull('think_tank_member_id')
            ->selectRaw("COALESCE(CAST(is_disabled AS TEXT), '[null]') AS state, COUNT(*) AS records")
            ->groupBy('is_disabled')
            ->pluck('records', 'state'),
        'direct_link_blacklisted_states' => DB::table('users')
            ->whereNotNull('think_tank_member_id')
            ->selectRaw("COALESCE(CAST(is_blacklisted AS TEXT), '[null]') AS state, COUNT(*) AS records")
            ->groupBy('is_blacklisted')
            ->pluck('records', 'state'),
        'direct_link_disabled_reasons' => DB::table('users')
            ->whereNotNull('think_tank_member_id')
            ->where('is_disabled', true)
            ->selectRaw("COALESCE(disabled_reason, '[no reason recorded]') AS reason, COUNT(*) AS records")
            ->groupBy('disabled_reason')
            ->pluck('records', 'reason'),
    ] : [],
    'active_think_tanks_without_any_performance_report' => $exists('me_performance_reports')
        ? DB::table('attp_consortium_think_tanks as t')
            ->leftJoin('me_performance_reports as r', 'r.think_tank_member_id', '=', 't.id')
            ->where('t.status', 'active')
            ->whereNull('r.id')
            ->orderBy('t.name')
            ->pluck('t.name')
            ->all()
        : [],
    'reporting_readiness' => $app->make(MeReportingReadinessService::class)->assess(
        $exists('myb_sectors') ? DB::table('myb_sectors')->pluck('id') : collect()
    ),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE).PHP_EOL;
