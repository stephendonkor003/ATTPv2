<?php

use App\Models\ConsortiumThinkTank;
use App\Models\Indicator;
use App\Models\IndicatorResult;
use App\Models\MeReportingPeriod;
use App\Models\Sector;
use App\Services\AttpMelResultsService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

DB::beginTransaction();
try {
    $sector = Sector::query()->firstOrFail();
    $tanks = ConsortiumThinkTank::query()->where('status', 'active')->take(2)->get();
    $assert($tanks->count() === 2, 'Two active Think Tanks are required for the weighted aggregation test.');
    $indicator = Indicator::query()->where('indicator_code', 'INTC2.8')->firstOrFail();

    $period = MeReportingPeriod::query()->create([
        'portfolio_id' => $sector->id,
        'code' => 'MEL-SMOKE-'.Str::upper(Str::random(8)),
        'label' => 'MEL completion smoke period',
        'period_type' => MeReportingPeriod::TYPE_SEMI_ANNUAL,
        'period_start' => '2026-01-01',
        'period_end' => '2026-06-30',
        'reporting_year' => 2026,
        'status' => MeReportingPeriod::STATUS_ACTIVE,
        'lifecycle_status' => MeReportingPeriod::LIFECYCLE_OPEN,
    ]);

    $createResult = static function (
        ConsortiumThinkTank $tank,
        float $actual,
        float $numerator,
        float $denominator,
        string $status,
        string $approvedAt
    ) use ($indicator, $period): IndicatorResult {
        return IndicatorResult::query()->create([
            'indicator_id' => $indicator->id,
            'reporting_period_id' => $period->id,
            'think_tank_member_id' => $tank->id,
            'period_type' => $period->period_type,
            'period_label' => $period->label,
            'period_start' => $period->period_start,
            'period_end' => $period->period_end,
            'actual_value' => $actual,
            'rollup_numerator' => $numerator,
            'rollup_denominator' => $denominator,
            'unit_id' => $indicator->unit_id,
            'review_status' => $status,
            'approved_at' => $status === 'approved' ? $approvedAt : null,
            'data_source' => 'Automated MEL smoke test',
        ]);
    };

    $createResult($tanks[0], 80, 8, 10, 'approved', '2026-07-01 09:00:00');
    $createResult($tanks[1], 25, 5, 20, 'approved', '2026-07-01 09:05:00');
    $createResult($tanks[1], 100, 100, 100, 'submitted', '2026-07-01 09:10:00');

    $data = app(AttpMelResultsService::class)->build([
        'project_year' => 1,
        'reporting_period_id' => $period->id,
        'indicator_id' => $indicator->id,
    ]);
    $row = $data['rows']->first();
    $assert($row !== null, 'The approved-results service returned no indicator row.');
    $assert(abs((float) $row['actual'] - 43.3333) < 0.0001, 'Weighted percentage aggregation is incorrect.');
    $assert($row['result_count'] === 2, 'Submitted/unapproved data leaked into the official result count.');

    $own = app(AttpMelResultsService::class)->build([
        'project_year' => 1,
        'reporting_period_id' => $period->id,
        'indicator_id' => $indicator->id,
    ], $tanks[0]->id)['rows']->first();
    $assert(abs((float) $own['actual'] - 80.0) < 0.0001, 'Think Tank organization isolation failed.');

    echo "ATTP_MEL_COMPLETION_OK\n";
} finally {
    DB::rollBack();
}
