<?php

use App\Services\MeReportingReadinessService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$database = DB::connection()->getDatabaseName();
if (! str_starts_with($database, 'attp_server_audit_')) {
    throw new RuntimeException("Safety stop: expected an isolated server-audit database, connected to [{$database}].");
}

$assessment = $app->make(MeReportingReadinessService::class)->assess(
    DB::table('myb_sectors')->pluck('id')
);
$gates = collect($assessment['gates'] ?? [])->keyBy('key');

if (($assessment['active_think_tanks'] ?? null) !== 13
    || ($assessment['ready'] ?? true) !== false
    || $gates->keys()->sort()->values()->all() !== collect([
        'access', 'matrix', 'indicators', 'forms', 'periods', 'collections',
    ])->sort()->values()->all()
    || ($gates->get('access')['value'] ?? null) !== '0 / 13 organizations'
    || ($gates->get('collections')['value'] ?? null) !== 'Best coverage 0 / 13') {
    throw new RuntimeException('The downloaded server reporting blockers were not assessed correctly.');
}

echo "SERVER_ME_REPORTING_READINESS_SMOKE_OK\n";
