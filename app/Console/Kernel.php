<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\IndicatorReminderJob;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Send indicator reminder emails every 4 hours via queued job
        $schedule->job(new IndicatorReminderJob())->everyFourHours();

        // Refresh World Bank catalog + recent values for used indicators each day.
        $schedule->command('worldbank:sync --catalog --used')->dailyAt('02:15')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}
