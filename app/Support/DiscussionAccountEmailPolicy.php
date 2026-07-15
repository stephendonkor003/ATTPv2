<?php

namespace App\Support;

use App\Models\DiscussionParticipant;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class DiscussionAccountEmailPolicy
{
    public const UNAVAILABLE_MESSAGE = 'This email address cannot be used.';

    public static function normalize(?string $email): string
    {
        return Str::lower(trim((string) $email));
    }

    public static function unavailableForParticipant(?string $email, ?string $ignoreParticipantId = null): bool
    {
        return self::systemUserEmailExists($email)
            || self::participantEmailExists($email, $ignoreParticipantId);
    }

    public static function systemUserEmailExists(?string $email): bool
    {
        $normalizedEmail = self::normalize($email);

        if ($normalizedEmail === '' || ! Schema::hasTable('users')) {
            return false;
        }

        return User::query()
            ->whereNotNull('email')
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->exists();
    }

    public static function participantEmailExists(?string $email, ?string $ignoreParticipantId = null): bool
    {
        $normalizedEmail = self::normalize($email);

        if ($normalizedEmail === '' || ! Schema::hasTable('discussion_participants')) {
            return false;
        }

        return DiscussionParticipant::query()
            ->when($ignoreParticipantId, fn ($query) => $query->whereKeyNot($ignoreParticipantId))
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalizedEmail])
            ->exists();
    }
}
