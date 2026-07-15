<?php

namespace App\Jobs;

use App\Mail\GrmReminderMail;
use App\Models\GrmGrievance;
use App\Models\GrmGrievanceEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ProcessGrmEscalations implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function handle(): void
    {
        GrmGrievance::with(['assignee', 'program', 'level', 'escalationRule'])
            ->whereIn('status', ['submitted', 'acknowledged', 'under_review', 'escalated'])
            ->whereNotNull('submitted_at')
            ->chunkById(50, function ($cases) {
                foreach ($cases as $case) {
                    $this->processCase($case);
                }
            });
    }

    private function processCase(GrmGrievance $case): void
    {
        $rule = $case->escalationRule;
        if (! $rule || ! $rule->is_active) {
            return;
        }

        $submittedAt = $case->submitted_at ?: $case->created_at;
        $reminderAfter = max(1, (int) $rule->reminder_after_hours);
        $reminderInterval = max(1, (int) $rule->reminder_interval_hours);
        $escalateAfter = max(1, (int) $rule->escalate_after_hours);

        $shouldRemind = ! $case->last_reminder_sent_at
            ? $submittedAt->copy()->addHours($reminderAfter)->isPast()
            : $case->last_reminder_sent_at->copy()->addHours($reminderInterval)->isPast();

        if ($shouldRemind) {
            $this->sendNotice($case, 'reminder');
            $case->forceFill(['last_reminder_sent_at' => now()])->save();
            $this->recordEvent($case, 'reminder_sent', 'GRM reminder email sent based on escalation configuration.');
        }

        if (! $case->last_escalated_at && $submittedAt->copy()->addHours($escalateAfter)->isPast()) {
            $this->sendNotice($case, 'escalation');
            $case->forceFill([
                'status' => 'escalated',
                'last_escalated_at' => now(),
            ])->save();
            $this->recordEvent($case, 'escalated', 'GRM case escalated based on configured timing.');
        }
    }

    private function sendNotice(GrmGrievance $case, string $type): void
    {
        $recipients = $this->recipients($case);

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $email => $name) {
            try {
                Mail::to($email, $name)->send(new GrmReminderMail($case, $case->escalationRule, $type));
            } catch (\Throwable $exception) {
                Log::warning('GRM reminder/escalation email failed.', [
                    'grievance_id' => $case->id,
                    'case_number' => $case->case_number,
                    'email' => $email,
                    'type' => $type,
                    'error' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function recipients(GrmGrievance $case): Collection
    {
        $recipients = collect();

        if (filled($case->assignee?->email)) {
            $recipients->put($case->assignee->email, $case->assignee->name);
        }

        if (filled($case->escalationRule?->escalation_email)) {
            $recipients->put($case->escalationRule->escalation_email, 'GRM Escalation Officer');
        }

        return $recipients;
    }

    private function recordEvent(GrmGrievance $case, string $type, string $notes): void
    {
        GrmGrievanceEvent::create([
            'grievance_id' => $case->id,
            'event_type' => $type,
            'notes' => $notes,
        ]);
    }
}
