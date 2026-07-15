<?php

namespace App\Services;

use App\Models\DiscussionParticipant;
use App\Models\DiscussionParticipantToken;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class DiscussionParticipantTokenService
{
    public const COOKIE_NAME = 'attp_discussion_device';

    public const REMEMBERED_DEVICE_LIFETIME_DAYS = 365;

    private const RENEWAL_WINDOW_DAYS = 45;

    private const ACTIVITY_WRITE_INTERVAL_MINUTES = 5;

    private const MAX_ACTIVE_TOKENS = 10;

    private const REQUEST_PLAIN_TOKEN = 'discussion_participant_plain_token';

    private const REQUEST_REFRESH_COOKIE = 'discussion_participant_refresh_cookie';

    /**
     * @return array{plain_text_token: string, token: DiscussionParticipantToken}
     */
    public function issue(DiscussionParticipant $participant, string $name = 'forum-browser'): array
    {
        $now = now();
        $plainTextToken = Str::random(80);

        $participant->tokens()->where('expires_at', '<=', $now)->delete();

        $token = $participant->tokens()->create([
            'token_hash' => hash('sha256', $plainTextToken),
            'name' => $this->normaliseDeviceName($name),
            'last_used_at' => $now,
            'expires_at' => $now->copy()->addDays(self::REMEMBERED_DEVICE_LIFETIME_DAYS),
        ]);

        $this->pruneExcessTokens($participant, (string) $token->getKey());

        return [
            'plain_text_token' => $plainTextToken,
            'token' => $token,
        ];
    }

    public function resolve(Request $request, bool $touch = true): ?DiscussionParticipantToken
    {
        $bearerToken = $request->bearerToken();
        $cookieToken = $request->cookie(self::COOKIE_NAME);
        $plainTextToken = $bearerToken ?: $cookieToken;

        if (! is_string($plainTextToken) || strlen($plainTextToken) < 40 || strlen($plainTextToken) > 255) {
            return null;
        }

        $token = DiscussionParticipantToken::query()
            ->with('participant')
            ->where('token_hash', hash('sha256', $plainTextToken))
            ->where('expires_at', '>', now())
            ->first();

        if (! $token || ! $token->participant) {
            return null;
        }

        $request->attributes->set(self::REQUEST_PLAIN_TOKEN, $plainTextToken);

        if ($touch) {
            $now = now();
            $recordActivity = ! $token->last_used_at
                || $token->last_used_at->lt($now->copy()->subMinutes(self::ACTIVITY_WRITE_INTERVAL_MINUTES));
            $renewExpiry = $token->expires_at
                && $token->expires_at->lte($now->copy()->addDays(self::RENEWAL_WINDOW_DAYS));

            if ($recordActivity || $renewExpiry) {
                $attributes = [];

                if ($recordActivity) {
                    $attributes['last_used_at'] = $now;
                }

                if ($renewExpiry) {
                    $attributes['expires_at'] = $now->copy()->addDays(self::REMEMBERED_DEVICE_LIFETIME_DAYS);
                }

                $token->forceFill($attributes)->saveQuietly();

                if ($recordActivity) {
                    $token->participant->forceFill(['last_seen_at' => $now])->saveQuietly();
                }
            }

            // A bearer-authenticated browser may be an existing installation from
            // before remembered-device cookies were introduced. Seed or refresh
            // its HttpOnly cookie without changing Bearer API compatibility.
            if ($renewExpiry || ! is_string($cookieToken) || ! hash_equals($plainTextToken, $cookieToken)) {
                $request->attributes->set(self::REQUEST_REFRESH_COOKIE, true);
            }
        }

        return $token;
    }

    public function revokeCurrent(Request $request): bool
    {
        $plainTextTokens = array_values(array_unique(array_filter([
            $request->bearerToken(),
            $request->cookie(self::COOKIE_NAME),
        ], fn (mixed $value): bool => is_string($value)
            && strlen($value) >= 40
            && strlen($value) <= 255)));

        if ($plainTextTokens !== []) {
            $tokenHashes = array_map(
                fn (string $plainTextToken): string => hash('sha256', $plainTextToken),
                $plainTextTokens
            );

            return DiscussionParticipantToken::query()
                ->whereIn('token_hash', $tokenHashes)
                ->delete() > 0;
        }

        $token = $request->attributes->get('discussion_participant_token');

        return $token instanceof DiscussionParticipantToken && (bool) $token->delete();
    }

    public function rememberedDeviceCookie(
        Request $request,
        string $plainTextToken,
        \DateTimeInterface $expiresAt
    ): Cookie {
        return Cookie::create(
            name: self::COOKIE_NAME,
            value: $plainTextToken,
            expire: $expiresAt,
            path: '/api/discussions',
            domain: null,
            secure: $this->cookieIsSecure($request),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    public function expiredRememberedDeviceCookie(Request $request): Cookie
    {
        return Cookie::create(
            name: self::COOKIE_NAME,
            value: '',
            expire: 1,
            path: '/api/discussions',
            domain: null,
            secure: $this->cookieIsSecure($request),
            httpOnly: true,
            raw: false,
            sameSite: Cookie::SAMESITE_STRICT,
        );
    }

    public function refreshedRememberedDeviceCookie(
        Request $request,
        DiscussionParticipantToken $token
    ): ?Cookie {
        $plainTextToken = $request->attributes->get(self::REQUEST_PLAIN_TOKEN);

        if (! $request->attributes->getBoolean(self::REQUEST_REFRESH_COOKIE)
            || ! is_string($plainTextToken)
            || ! $token->expires_at) {
            return null;
        }

        return $this->rememberedDeviceCookie($request, $plainTextToken, $token->expires_at);
    }

    private function normaliseDeviceName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $name) ?: 'forum-browser';
        $name = Str::squish($name);

        return Str::limit($name ?: 'forum-browser', 100, '');
    }

    private function pruneExcessTokens(DiscussionParticipant $participant, string $issuedTokenId): void
    {
        $excessTokenIds = $participant->tokens()
            ->where('expires_at', '>', now())
            ->whereKeyNot($issuedTokenId)
            ->latest('created_at')
            ->latest('id')
            ->pluck('id')
            ->slice(self::MAX_ACTIVE_TOKENS - 1)
            ->values();

        if ($excessTokenIds->isNotEmpty()) {
            $participant->tokens()->whereIn('id', $excessTokenIds)->delete();
        }
    }

    private function cookieIsSecure(Request $request): bool
    {
        return $request->isSecure() || (bool) config('session.secure', false);
    }
}
