<?php

namespace App\Services;

use App\Models\DiscussionParticipant;
use App\Models\DiscussionParticipantPasswordReset;
use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DiscussionParticipantPasswordResetService
{
    public const LIFETIME_MINUTES = 60;

    /**
     * @return array{plain_text_token: string, reset: DiscussionParticipantPasswordReset}
     */
    public function issue(DiscussionParticipant $participant): array
    {
        $plainTextToken = Str::random(80);
        $now = now();

        $reset = DB::transaction(function () use ($participant, $plainTextToken, $now): DiscussionParticipantPasswordReset {
            DiscussionParticipant::query()
                ->whereKey($participant->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            return DiscussionParticipantPasswordReset::query()->updateOrCreate(
                ['participant_id' => $participant->getKey()],
                [
                    'token_hash' => hash('sha256', $plainTextToken),
                    'expires_at' => $now->copy()->addMinutes(self::LIFETIME_MINUTES),
                    'used_at' => null,
                ]
            );
        });

        return [
            'plain_text_token' => $plainTextToken,
            'reset' => $reset,
        ];
    }

    public function consume(string $email, string $plainTextToken, string $newPassword): ?DiscussionParticipant
    {
        if (strlen($plainTextToken) < 40 || strlen($plainTextToken) > 255) {
            return null;
        }

        $normalisedEmail = DiscussionAccountEmailPolicy::normalize($email);
        $tokenHash = hash('sha256', $plainTextToken);
        $participantId = DiscussionParticipantPasswordReset::query()
            ->where('token_hash', $tokenHash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->value('participant_id');

        if (! is_string($participantId)) {
            return null;
        }

        return DB::transaction(function () use ($participantId, $normalisedEmail, $tokenHash, $newPassword): ?DiscussionParticipant {
            // Keep the participant -> reset-token lock order consistent with
            // issue() so simultaneous reset requests cannot deadlock.
            $participant = DiscussionParticipant::query()
                ->whereKey($participantId)
                ->lockForUpdate()
                ->first();

            if (! $participant || ! hash_equals($participant->email, $normalisedEmail)) {
                return null;
            }

            $reset = DiscussionParticipantPasswordReset::query()
                ->where('participant_id', $participantId)
                ->where('token_hash', $tokenHash)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->first();

            if (! $reset) {
                return null;
            }

            $participant->forceFill(['password' => $newPassword])->save();
            $participant->tokens()->delete();
            $reset->forceFill(['used_at' => now()])->save();

            return $participant;
        });
    }
}
