<?php

namespace App\Jobs;

use App\Mail\GrmAutoResponseMail;
use App\Models\GrmGrievance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendGrmAutoResponse implements ShouldQueue
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
        $grievance = GrmGrievance::with(['program', 'level', 'escalationRule'])->find($this->grievanceId);

        if (! $grievance || blank($grievance->submitter_email)) {
            return;
        }

        try {
            Mail::to($grievance->submitter_email, $grievance->submitter_name)
                ->send(new GrmAutoResponseMail($grievance, $grievance->escalationRule));
        } catch (\Throwable $exception) {
            Log::warning('GRM auto-response email failed.', [
                'grievance_id' => $this->grievanceId,
                'email' => $grievance->submitter_email,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
