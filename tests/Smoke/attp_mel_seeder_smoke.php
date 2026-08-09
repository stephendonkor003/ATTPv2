<?php

use App\Models\Indicator;
use App\Models\IndicatorTarget;
use App\Models\MeDataCollection;
use App\Models\MeDataCollectionAssignment;
use App\Models\MeDataEntryForm;
use App\Models\MeReportingPeriod;
use App\Services\AttpMelFrameworkInstaller;
use Database\Seeders\AttpMelThinkTankReportingSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

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
    app(AttpMelFrameworkInstaller::class)->install();
    (new AttpMelThinkTankReportingSeeder)->run();

    $codes = [
        'PDO 1', 'PDO 2', 'PDO 3', 'PDO 3-CE', 'PDO 4', 'INTC1',
        'INTC2.1', 'INTC2.2', 'INTC2.3', 'INTC2.4', 'INTC2.5', 'INTC2.7',
        'INTC2.8', 'INTC2.9', 'INTC2.10', 'INTC2.11', 'INTC3.1', 'INTC3.2',
    ];
    $assert(Indicator::query()->whereIn('indicator_code', $codes)->count() === 18, 'The official 18-indicator set was not installed.');
    $assert(! Indicator::query()->where('indicator_code', 'INTC2.6')->exists(), 'INTC2.6 must not be invented.');
    $assert(
        IndicatorTarget::query()->whereHas('framework', fn ($query) => $query->where('version', AttpMelFrameworkInstaller::FRAMEWORK_VERSION))->count() === 72,
        'The 72 official numeric and qualitative target records were not installed.'
    );

    $forms = MeDataEntryForm::query()->where('code', 'like', 'ATTP-TT-INTC%')->get();
    $assert($forms->count() === 7, 'The seven standardized Think Tank reporting forms were not installed.');
    $assert(! $forms->contains('code', 'ATTP-TT-INTC2-11'), 'INTC2.11 must remain an AUC policy-community survey.');
    foreach ($forms as $form) {
        $assert($form->fields()->whereNotNull('indicator_id')->count() === 1, "{$form->code} must materialize exactly one indicator result.");
    }

    $periodCount = MeReportingPeriod::query()->where('code', 'like', 'ATTP-MEL-%')->count();
    $collectionCount = MeDataCollection::query()->whereIn('form_id', $forms->pluck('id'))->count();
    $assignmentCount = MeDataCollectionAssignment::query()
        ->whereIn('collection_id', MeDataCollection::query()->whereIn('form_id', $forms->pluck('id'))->select('id'))
        ->count();

    $period = MeReportingPeriod::query()->where('code', 'like', 'ATTP-MEL-%')->orderBy('period_start')->firstOrFail();
    $collection = MeDataCollection::query()->where('reporting_period_id', $period->id)->whereIn('form_id', $forms->pluck('id'))->firstOrFail();
    $form = $collection->form()->firstOrFail();
    $period->update(['status' => MeReportingPeriod::STATUS_ACTIVE, 'lifecycle_status' => MeReportingPeriod::LIFECYCLE_OPEN]);
    $collection->update(['status' => MeDataCollection::STATUS_OPEN]);
    $form->update(['status' => MeDataEntryForm::STATUS_ARCHIVED]);

    (new AttpMelThinkTankReportingSeeder)->run();

    $assert(MeReportingPeriod::query()->where('code', 'like', 'ATTP-MEL-%')->count() === $periodCount, 'A repeat run duplicated reporting periods.');
    $assert(MeDataCollection::query()->whereIn('form_id', $forms->pluck('id'))->count() === $collectionCount, 'A repeat run duplicated collections.');
    $assert(
        MeDataCollectionAssignment::query()->whereIn('collection_id', MeDataCollection::query()->whereIn('form_id', $forms->pluck('id'))->select('id'))->count() === $assignmentCount,
        'A repeat run duplicated assignments.'
    );
    $assert($period->fresh()->status === MeReportingPeriod::STATUS_ACTIVE, 'A repeat run reset an active reporting period.');
    $assert($period->fresh()->lifecycle_status === MeReportingPeriod::LIFECYCLE_OPEN, 'A repeat run reset an open period lifecycle.');
    $assert($collection->fresh()->status === MeDataCollection::STATUS_OPEN, 'A repeat run reset an open collection.');
    $assert($form->fresh()->status === MeDataEntryForm::STATUS_ARCHIVED, 'A repeat run republished an archived form.');

    echo "ATTP_MEL_SEEDER_OK\n";
} finally {
    DB::rollBack();
}
