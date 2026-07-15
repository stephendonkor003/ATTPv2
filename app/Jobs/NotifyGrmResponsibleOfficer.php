<?php

namespace App\Jobs;

use App\Mail\GrmResponsibleOfficerMail;
use App\Models\GrmGrievance;
use App\Models\GrmGrievanceEvent;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyGrmResponsibleOfficer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public string $grievanceId)
    {
    }

    public function handle(): void
    {
        $grievance = GrmGrievance::with([
            'program.sector',
            'level',
            'assignee.role',
            'submitter',
        ])
            ->withCount('attachments')
            ->find($this->grievanceId);

        if (! $grievance) {
            Log::warning('GRM responsible officer notification skipped; grievance not found.', [
                'grievance_id' => $this->grievanceId,
            ]);

            return;
        }

        $officer = $grievance->assignee;

        if (! $officer || ! $this->canReceiveEmail($officer)) {
            Log::warning('GRM responsible officer notification skipped; no active email recipient.', [
                'grievance_id' => $grievance->id,
                'assigned_to' => $grievance->assigned_to,
            ]);

            return;
        }

        try {
            Mail::to($officer->email, $officer->name)->send(new GrmResponsibleOfficerMail(
                grievance: $grievance,
                officer: $officer,
                caseUrl: route('grm.logs.show', $grievance)
            ));

            GrmGrievanceEvent::create([
                'grievance_id' => $grievance->id,
                'user_id' => null,
                'event_type' => 'responsible_notified',
                'notes' => 'GRM responsible officer notified by email.',
                'metadata' => [
                    'officer_id' => $officer->id,
                    'officer_email' => $officer->email,
                ],
            ]);
        } catch (Throwable $exception) {
            Log::warning('GRM responsible officer email failed.', [
                'grievance_id' => $grievance->id,
                'assigned_to' => $officer->id,
                'email' => $officer->email,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function canReceiveEmail(User $user): bool
    {
        return filled($user->email)
            && ! (bool) $user->is_disabled
            && ! (bool) $user->is_blacklisted;
    }
}
