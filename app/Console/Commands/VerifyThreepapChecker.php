<?php

namespace App\Console\Commands;

use App\Services\ProcurementSubmissionScreeningService;
use Illuminate\Console\Command;

final class VerifyThreepapChecker extends Command
{
    protected $signature = 'threepap:verify';

    protected $description = 'Verify the configured 3PAP token, sanctions scope, and monthly quota without running a screening';

    public function handle(ProcurementSubmissionScreeningService $screeningService): int
    {
        $status = $screeningService->accountStatus();
        $usage = $status['usage'];

        $this->table(['Check', 'Result'], [
            ['Configuration', $status['configured'] ? 'present' : 'missing'],
            ['Authentication', $status['authenticated'] ? 'valid' : 'not verified'],
            ['Subscription plan', $status['plan'] ?: 'not reported'],
            ['sanctions_search scope', $status['scope_enabled'] ? 'enabled' : 'missing'],
            ['Monthly usage', $this->formatUsage($usage['used'], $usage['limit'], $usage['remaining'])],
        ]);

        if (! $status['ok']) {
            $this->error($status['message']);

            return self::FAILURE;
        }

        $this->info($status['message']);
        $this->line('No applicant was screened and no sanctions-search credit was consumed.');

        return self::SUCCESS;
    }

    private function formatUsage(?int $used, ?int $limit, ?int $remaining): string
    {
        if ($used === null && $limit === null && $remaining === null) {
            return 'not reported';
        }

        return sprintf(
            '%s used / %s limit / %s remaining',
            $used === null ? '?' : number_format($used),
            $limit === null ? '?' : number_format($limit),
            $remaining === null ? '?' : number_format($remaining)
        );
    }
}
