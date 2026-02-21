<?php

namespace App\Jobs;

use App\Mail\IndicatorReminderMail;
use App\Models\Indicator;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class IndicatorReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public ?string $indicatorId;

    public function __construct(?string $indicatorId = null)
    {
        $this->indicatorId = $indicatorId;
    }

    public function handle(): void
    {
        $query = $this->indicatorId
            ? Indicator::where('id', $this->indicatorId)
            : Indicator::query();

        $query->chunk(50, function ($batch) {
            foreach ($batch as $indicator) {
                $userIds = $this->extractUsers($indicator->responsible_party);
                if (empty($userIds)) {
                    continue;
                }
                $users = User::whereIn('id', $userIds)->get();
                foreach ($users as $user) {
                    Mail::to($user->email)
                        ->queue(new IndicatorReminderMail($indicator, $user));
                }
            }
        });
    }

    protected function extractUsers(?string $json): array
    {
        if (!$json) return [];
        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return array_filter($decoded);
        }
        // fallback: comma-separated ids
        return array_filter(explode(',', $json));
    }
}
