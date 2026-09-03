<?php

namespace App\Console\Commands;

use App\Models\UserLoginOtp;
use Illuminate\Console\Command;

class PurgeThinkTankLoginOtps extends Command
{
    protected $signature = 'think-tank:otp:purge {--hours=24 : Retention after challenge expiry} {--limit=5000 : Maximum rows per run}';

    protected $description = 'Delete expired Think Tank login OTP metadata after its short retention window';

    public function handle(): int
    {
        $hours = max(1, min((int) $this->option('hours'), 168));
        $limit = max(1, min((int) $this->option('limit'), 50000));
        $cutoff = now()->subHours($hours);
        $ids = UserLoginOtp::query()
            ->where('expires_at', '<=', $cutoff)
            ->orderBy('expires_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $deleted = $ids->isEmpty()
            ? 0
            : UserLoginOtp::query()->whereKey($ids)->delete();

        $this->info("Purged {$deleted} expired login verification challenge(s).");

        return self::SUCCESS;
    }
}
