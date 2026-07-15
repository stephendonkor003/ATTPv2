<?php

use App\Models\DiscussionParticipant;
use App\Models\DiscussionPost;
use App\Models\DiscussionReaction;
use App\Models\DiscussionTopic;
use App\Models\User;
use App\Services\DiscussionParticipantTokenService;
use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$kernel = $app->make(HttpKernel::class);
$smokeIp = '198.18.'.random_int(1, 254).'.'.random_int(1, 254);

$requestJson = function (string $method, string $uri, array $payload = [], ?string $token = null) use ($kernel, $smokeIp) {
    $server = [
        'HTTP_HOST' => '127.0.0.1:8000',
        'SERVER_PORT' => 8000,
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'REMOTE_ADDR' => $smokeIp,
    ];

    if ($token) {
        $server['HTTP_AUTHORIZATION'] = "Bearer {$token}";
    }

    $request = Request::create(
        $uri,
        $method,
        [],
        [],
        [],
        $server,
        $payload === [] ? null : json_encode($payload, JSON_THROW_ON_ERROR)
    );

    $response = $kernel->handle($request);
    $data = json_decode((string) $response->getContent(), true);
    $kernel->terminate($request, $response);

    return [$response, is_array($data) ? $data : []];
};

$assertStatus = function ($response, int $expected, string $context): void {
    if ($response->getStatusCode() !== $expected) {
        fwrite(STDERR, sprintf(
            "%s: expected HTTP %d, received %d.\n%s\n",
            $context,
            $expected,
            $response->getStatusCode(),
            (string) $response->getContent()
        ));
        exit(1);
    }
};

$assertUnavailableEmail = function ($response, array $payload, string $context) use ($assertStatus): void {
    $assertStatus($response, 422, $context);

    $message = $payload['errors']['email'][0] ?? $payload['message'] ?? null;
    if ($message !== DiscussionAccountEmailPolicy::UNAVAILABLE_MESSAGE) {
        throw new RuntimeException("{$context} did not return the neutral unavailable-email message.");
    }

    $publicResponse = Str::lower(json_encode($payload, JSON_THROW_ON_ERROR));
    foreach (['admin', 'staff', 'think tank', 'back office', 'system user', 'already exists', 'already registered'] as $disclosure) {
        if (str_contains($publicResponse, $disclosure)) {
            throw new RuntimeException("{$context} disclosed internal account information.");
        }
    }
};

DB::beginTransaction();

try {
    [$overviewResponse, $overview] = $requestJson('GET', '/api/discussions/overview');
    $assertStatus($overviewResponse, 200, 'Forum overview');

    if (! isset($overview['data']['themes'], $overview['data']['active_discussions'])) {
        throw new RuntimeException('Forum overview is missing its summary counts.');
    }

    [$themesResponse, $themes] = $requestJson('GET', '/api/discussions/themes');
    $assertStatus($themesResponse, 200, 'Thematic areas');

    if (count($themes['data'] ?? []) < 1) {
        throw new RuntimeException('The forum did not return any active thematic areas.');
    }

    $firstTheme = $themes['data'][0];
    foreach (['slug', 'id'] as $themeIdentifier) {
        [$filteredTopicsResponse] = $requestJson(
            'GET',
            '/api/discussions/topics?status=open&theme='.rawurlencode((string) $firstTheme[$themeIdentifier])
        );
        $assertStatus($filteredTopicsResponse, 200, "Active discussions filtered by theme {$themeIdentifier}");
    }

    [$countriesResponse, $countries] = $requestJson('GET', '/api/discussions/countries');
    $assertStatus($countriesResponse, 200, 'Participant countries');

    $countryRows = collect($countries['data'] ?? []);
    if ($countryRows->count() < 50 || $countryRows->contains(fn (array $country) => empty($country['name']) || empty($country['iso2']) || empty($country['flag']) || empty($country['flag_url']))) {
        throw new RuntimeException('The participant country selector is missing member states or flags.');
    }

    if ($countryRows->contains(fn (array $country) => ! is_file(public_path('admin/assets/vendors/img/flags/4x3/'.Str::lower($country['iso2']).'.svg')))) {
        throw new RuntimeException('One or more participant countries is missing its bundled flag artwork.');
    }

    $kenyaParticipantsBefore = (int) ($countryRows->firstWhere('name', 'Kenya')['participants_count'] ?? 0);

    [$topicsResponse, $topics] = $requestJson('GET', '/api/discussions/topics?status=open');
    $assertStatus($topicsResponse, 200, 'Active discussion list');

    if (count($topics['data'] ?? []) < 1) {
        throw new RuntimeException('The forum did not return any active discussions.');
    }

    $sourceTopic = DiscussionTopic::query()->where('status', 'open')->firstOrFail();
    $topic = DiscussionTopic::query()->create([
        'theme_id' => $sourceTopic->theme_id,
        'created_by' => $sourceTopic->created_by,
        'title' => 'Open publishing smoke '.Str::upper(Str::random(6)),
        'slug' => 'open-publishing-smoke-'.Str::lower(Str::random(12)),
        'summary' => 'An isolated discussion for immediate-publishing and topic-presence checks.',
        'body' => 'Contributions to this test discussion must be visible immediately.',
        'status' => 'open',
        'is_featured' => false,
        // Even a legacy topic flag must not put new contributions into an approval queue.
        'requires_moderation' => true,
        'allow_replies' => true,
    ]);

    [$anonymousPostResponse] = $requestJson(
        'POST',
        "/api/discussions/topics/{$topic->slug}/posts",
        ['body' => 'This anonymous contribution must be rejected.']
    );
    $assertStatus($anonymousPostResponse, 401, 'Anonymous contribution');

    $systemUser = User::query()->whereNotNull('email')->where('email', '<>', '')->firstOrFail();
    $participantCountBeforeCollision = DiscussionParticipant::query()->count();
    [$systemEmailResponse, $systemEmailPayload] = $requestJson('POST', '/api/discussions/participants/register', [
        'display_name' => 'Email Collision Test',
        'email' => '  '.Str::upper($systemUser->email).'  ',
        'country' => 'Kenya',
        'password' => 'ForumPass2026',
        'password_confirmation' => 'ForumPass2026',
        'terms' => true,
    ]);
    $assertUnavailableEmail($systemEmailResponse, $systemEmailPayload, 'System-account email collision');

    if (DiscussionParticipant::query()->count() !== $participantCountBeforeCollision) {
        throw new RuntimeException('A participant was created with an internal system email address.');
    }

    $suffix = Str::lower(Str::random(10));
    $participantEmail = "forum-smoke-{$suffix}@example.test";
    [$invalidCountryResponse, $invalidCountryPayload] = $requestJson('POST', '/api/discussions/participants/register', [
        'display_name' => 'Invalid Country Test',
        'email' => "forum-country-{$suffix}@example.test",
        'country' => 'Canada',
        'password' => 'ForumPass2026',
        'password_confirmation' => 'ForumPass2026',
        'terms' => true,
    ]);
    $assertStatus($invalidCountryResponse, 422, 'Non-member country registration');

    if (empty($invalidCountryPayload['errors']['country'])) {
        throw new RuntimeException('A non-member country was not rejected by participant registration.');
    }

    [$registerResponse, $registration] = $requestJson('POST', '/api/discussions/participants/register', [
        'display_name' => 'Forum Smoke Participant',
        'email' => $participantEmail,
        'country' => 'Kenya',
        'organization' => 'ATTP Test',
        'password' => 'ForumPass2026',
        'password_confirmation' => 'ForumPass2026',
        'terms' => true,
    ]);
    $assertStatus($registerResponse, 201, 'Participant registration');

    $token = $registration['token'] ?? null;
    if (! is_string($token) || $token === '') {
        throw new RuntimeException('Participant registration did not return an API token.');
    }

    [$presenceResponse, $presence] = $requestJson(
        'POST',
        "/api/discussions/topics/{$topic->slug}/presence",
        [],
        $token
    );
    $assertStatus($presenceResponse, 201, 'First topic participation');

    if (($presence['joined_now'] ?? null) !== true
        || ($presence['data']['participants_count'] ?? null) !== 1
        || ($presence['data']['countries_count'] ?? null) !== 1
        || ($presence['data']['viewer_joined'] ?? null) !== true) {
        throw new RuntimeException('The first topic participation did not return its live country and participant state.');
    }

    [$repeatPresenceResponse, $repeatPresence] = $requestJson(
        'POST',
        "/api/discussions/topics/{$topic->slug}/presence",
        [],
        $token
    );
    $assertStatus($repeatPresenceResponse, 200, 'Repeated topic participation');

    if (($repeatPresence['joined_now'] ?? null) !== false
        || ($repeatPresence['data']['participants_count'] ?? null) !== 1) {
        throw new RuntimeException('Topic participation was not idempotent for the same participant.');
    }

    $secondParticipant = DiscussionParticipant::query()->create([
        'display_name' => 'Forum Ghana Participant',
        'email' => "forum-ghana-{$suffix}@example.test",
        'password' => 'ForumPass2026',
        'country' => 'Ghana',
        'status' => 'active',
        'terms_accepted_at' => now(),
    ]);
    $secondToken = $app->make(DiscussionParticipantTokenService::class)
        ->issue($secondParticipant, 'Forum API smoke second country')['plain_text_token'];
    [$secondPresenceResponse] = $requestJson(
        'POST',
        "/api/discussions/topics/{$topic->slug}/presence",
        [],
        $secondToken
    );
    $assertStatus($secondPresenceResponse, 201, 'Second-country topic participation');

    [$activityResponse, $activity] = $requestJson('GET', "/api/discussions/topics/{$topic->slug}/activity");
    $assertStatus($activityResponse, 200, 'Live topic participation activity');

    if (($activity['data']['participation']['participants_count'] ?? null) !== 2
        || ($activity['data']['participation']['countries_count'] ?? null) !== 2
        || count($activity['data']['participation']['recent_joiners'] ?? []) !== 2) {
        throw new RuntimeException('Live topic activity did not aggregate participants and countries for this discussion only.');
    }

    $recentJoiners = collect($activity['data']['participation']['recent_joiners']);
    if ($recentJoiners->contains(fn (array $joiner) => empty($joiner['flag']) || empty($joiner['flag_url']))) {
        throw new RuntimeException('A live topic join notification is missing its participant country flag.');
    }

    [$updatedCountriesResponse, $updatedCountries] = $requestJson('GET', '/api/discussions/countries');
    $assertStatus($updatedCountriesResponse, 200, 'Updated country participation');
    $kenyaParticipantsAfter = (int) (collect($updatedCountries['data'] ?? [])->firstWhere('name', 'Kenya')['participants_count'] ?? 0);

    if ($kenyaParticipantsAfter !== $kenyaParticipantsBefore + 1) {
        throw new RuntimeException('The country participation count did not update after registration.');
    }

    [$duplicateResponse, $duplicatePayload] = $requestJson('POST', '/api/discussions/participants/register', [
        'display_name' => 'Duplicate Email Test',
        'email' => Str::upper($participantEmail),
        'country' => 'Kenya',
        'password' => 'AnotherPass2026',
        'password_confirmation' => 'AnotherPass2026',
        'terms' => true,
    ]);
    $assertUnavailableEmail($duplicateResponse, $duplicatePayload, 'Participant email collision');

    try {
        User::query()->create([
            'name' => 'Reverse Collision Test',
            'email' => Str::upper($participantEmail),
            'password' => 'InternalPass2026',
            'user_type' => 'think_tank',
        ]);

        throw new RuntimeException('An internal user was created with a participant email address.');
    } catch (ValidationException $exception) {
        if (($exception->errors()['email'][0] ?? null) !== DiscussionAccountEmailPolicy::UNAVAILABLE_MESSAGE) {
            throw new RuntimeException('The reverse email-collision guard returned a revealing message.');
        }
    }

    [$meResponse, $me] = $requestJson('GET', '/api/discussions/participants/me', [], $token);
    $assertStatus($meResponse, 200, 'Participant profile');

    if (($me['participant']['display_name'] ?? null) !== 'Forum Smoke Participant') {
        throw new RuntimeException('Authenticated participant profile was not returned correctly.');
    }

    [$postResponse, $posted] = $requestJson(
        'POST',
        "/api/discussions/topics/{$topic->slug}/posts",
        ['body' => 'A published API smoke-test contribution.'],
        $token
    );
    $assertStatus($postResponse, 201, 'Authenticated contribution');

    $postId = $posted['data']['id'] ?? null;
    if (! is_string($postId)
        || ($posted['moderation_status'] ?? null) !== 'published'
        || DiscussionPost::query()->whereKey($postId)->value('status') !== 'published') {
        throw new RuntimeException('A contribution was not published immediately without prior approval.');
    }

    [$replyResponse, $reply] = $requestJson(
        'POST',
        "/api/discussions/topics/{$topic->slug}/posts",
        [
            'body' => 'A threaded reply from the API smoke test.',
            'parent_id' => $postId,
        ],
        $token
    );
    $assertStatus($replyResponse, 201, 'Threaded participant reply');

    if (($reply['data']['parent_id'] ?? null) !== $postId) {
        throw new RuntimeException('Threaded contribution did not retain its parent identifier.');
    }

    [$reactionResponse, $reaction] = $requestJson(
        'POST',
        "/api/discussions/posts/{$postId}/reaction",
        [],
        $token
    );
    $assertStatus($reactionResponse, 200, 'Contribution reaction');

    if (($reaction['reacted'] ?? null) !== true) {
        throw new RuntimeException('Contribution reaction was not recorded.');
    }

    if (($reaction['type'] ?? null) !== 'like'
        || ($reaction['reactions']['like'] ?? null) !== 1
        || ! in_array('like', $reaction['viewer_reactions'] ?? [], true)
        || ($reaction['reactions_count'] ?? null) !== 1
        || ($reaction['viewer_reacted'] ?? null) !== true) {
        throw new RuntimeException('The default like reaction did not return the typed and legacy reaction state.');
    }

    foreach (array_diff(DiscussionReaction::ALLOWED_TYPES, ['like']) as $reactionType) {
        [$typedReactionResponse, $typedReaction] = $requestJson(
            'POST',
            "/api/discussions/posts/{$postId}/reaction",
            ['type' => $reactionType],
            $token
        );
        $assertStatus($typedReactionResponse, 200, "{$reactionType} contribution reaction");

        if (($typedReaction['type'] ?? null) !== $reactionType
            || ($typedReaction['reacted'] ?? null) !== true
            || ($typedReaction['reactions'][$reactionType] ?? null) !== 1
            || ! in_array($reactionType, $typedReaction['viewer_reactions'] ?? [], true)) {
            throw new RuntimeException("The {$reactionType} reaction was not represented in the typed reaction state.");
        }
    }

    [$removeSupportResponse, $removedSupport] = $requestJson(
        'POST',
        "/api/discussions/posts/{$postId}/reaction",
        ['type' => 'support'],
        $token
    );
    $assertStatus($removeSupportResponse, 200, 'Remove support reaction');

    if (($removedSupport['reacted'] ?? null) !== false
        || ($removedSupport['reactions']['support'] ?? null) !== 0
        || in_array('support', $removedSupport['viewer_reactions'] ?? [], true)) {
        throw new RuntimeException('The support reaction was not toggled off cleanly.');
    }

    [$invalidReactionResponse, $invalidReaction] = $requestJson(
        'POST',
        "/api/discussions/posts/{$postId}/reaction",
        ['type' => 'applause'],
        $token
    );
    $assertStatus($invalidReactionResponse, 422, 'Invalid reaction type');

    if (empty($invalidReaction['errors']['type'])
        || DiscussionReaction::query()->where('post_id', $postId)->where('type', 'applause')->exists()) {
        throw new RuntimeException('An unsupported reaction type was not rejected safely.');
    }

    [$topicResponse, $topicPayload] = $requestJson('GET', "/api/discussions/topics/{$topic->slug}", [], $token);
    $assertStatus($topicResponse, 200, 'Discussion detail');

    $serializedPost = collect($topicPayload['data']['posts'] ?? [])->firstWhere('id', $postId);
    if (! $serializedPost) {
        throw new RuntimeException('Discussion detail did not include the published contribution.');
    }

    $expectedReactionCounts = [
        'like' => 1,
        'insightful' => 1,
        'agree' => 1,
        'support' => 0,
        'celebrate' => 1,
    ];

    if (($serializedPost['reactions'] ?? null) !== $expectedReactionCounts
        || ($serializedPost['viewer_reactions'] ?? null) !== ['like', 'insightful', 'agree', 'celebrate']
        || ($serializedPost['reactions_count'] ?? null) !== 4
        || ($serializedPost['viewer_reacted'] ?? null) !== true) {
        throw new RuntimeException('Discussion detail did not expose the expected aggregate and viewer reaction state.');
    }

    DiscussionParticipant::query()->whereKey($me['participant']['id'])->update([
        'status' => 'blocked',
        'blocked_at' => now(),
        'blocked_reason' => 'Automated smoke test',
    ]);

    [$blockedResponse] = $requestJson('GET', '/api/discussions/participants/me', [], $token);
    $assertStatus($blockedResponse, 403, 'Blocked participant access');

    echo "DISCUSSION_FORUM_API_OK\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage()."\n");
    exit(1);
} finally {
    DB::rollBack();
}
