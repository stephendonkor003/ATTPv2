<?php

namespace App\Jobs;

use App\Mail\ProgramTtlAssignedMail;
use App\Models\Program;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class NotifyProgramTtlAssigned implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public string $programId,
        public string $userId,
        public ?string $plainPassword = null
    ) {
    }

    public function handle(): void
    {
        $program = Program::with(['sector', 'governanceNode.level', 'ttlUser'])->find($this->programId);
        $user = User::with('role')->find($this->userId);

        if (! $program || ! $user) {
            Log::warning('TTL assignment email skipped; program or user not found.', [
                'program_id' => $this->programId,
                'user_id' => $this->userId,
            ]);

            return;
        }

        if (! $this->canReceiveEmail($user)) {
            Log::warning('TTL assignment email skipped; user cannot receive email.', [
                'program_id' => $program->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            return;
        }

        try {
            Mail::to($user->email, $user->name)->send(new ProgramTtlAssignedMail(
                user: $user,
                program: $program,
                plainPassword: $this->plainPassword,
                loginUrl: route('login'),
                programUrl: route('ttl.programs.show', $program)
            ));

            $program->forceFill(['ttl_notified_at' => now()])->save();

            Log::info('TTL assignment email sent.', [
                'program_id' => $program->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (Throwable $exception) {
            Log::warning('TTL assignment email failed.', [
                'program_id' => $program->id,
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    private function canReceiveEmail(User $user): bool
    {
        return filled($user->email)
            && ! (bool) $user->is_disabled
            && ! (bool) $user->is_blacklisted;
    }
}
