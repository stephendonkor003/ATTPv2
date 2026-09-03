<?php

namespace App\Console\Commands;

use App\Exceptions\ThinkTankApiException;
use App\Services\ThinkTank\ThinkTankMailSecurityService;
use App\Services\ThinkTank\ThinkTankProductionSecurityService;
use App\Services\ThinkTank\ThinkTankSessionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VerifyThinkTankPortalSecurity extends Command
{
    protected $signature = 'think-tank:security:preflight';

    protected $description = 'Fail closed unless the Think Tank portal production security prerequisites are satisfied';

    public function handle(
        ThinkTankProductionSecurityService $production,
        ThinkTankSessionService $sessions,
        ThinkTankMailSecurityService $mail,
    ): int {
        $problems = $production->problems();

        foreach ([
            fn () => $sessions->assertProductionSecurityStores(),
            fn () => $mail->assertCredentialDeliveryIsSecure(),
            fn () => $mail->assertEncryptedResetQueueIsDurable(),
        ] as $assertion) {
            try {
                $assertion();
            } catch (ThinkTankApiException $exception) {
                $problems[] = $exception->getMessage();
            }
        }

        foreach ($this->requiredTables() as $table) {
            try {
                if (! Schema::hasTable($table)) {
                    $problems[] = "Required table [{$table}] is missing.";
                }
            } catch (Throwable) {
                $problems[] = 'The database could not be inspected.';
                break;
            }
        }

        $problems = array_values(array_unique($problems));

        if ($problems !== []) {
            $this->error('Think Tank portal security preflight failed:');

            foreach ($problems as $problem) {
                $this->line(' - '.$problem);
            }

            return self::FAILURE;
        }

        $this->info('Think Tank portal security preflight passed.');

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function requiredTables(): array
    {
        $tables = [
            (string) config('session.table', 'sessions'),
            'password_reset_tokens',
            'user_login_otps',
        ];
        $limiterStore = (string) (config('cache.limiter') ?: config('cache.default'));

        if (config("cache.stores.{$limiterStore}.driver") === 'database') {
            $tables[] = (string) config("cache.stores.{$limiterStore}.table", 'cache');
            $tables[] = (string) config("cache.stores.{$limiterStore}.lock_table", 'cache_locks');
        }

        $queue = (string) config('queue.default');

        if (config("queue.connections.{$queue}.driver") === 'database') {
            $tables[] = (string) config("queue.connections.{$queue}.table", 'jobs');
        }

        return array_values(array_unique(array_filter($tables)));
    }
}
