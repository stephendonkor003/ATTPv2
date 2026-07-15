<?php

use App\Http\Controllers\System\DiscussionAdminController;
use App\Http\Controllers\Api\DiscussionForumController;
use App\Models\DiscussionModerationAction;
use App\Models\DiscussionParticipant;
use App\Models\DiscussionPost;
use App\Models\DiscussionReaction;
use App\Models\DiscussionTopic;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();

foreach ([
    'system.discussions.moderation.live',
    'system.discussions.moderation.live.feed',
    'system.discussions.moderation.remove',
] as $routeName) {
    if (! Route::has($routeName)) {
        throw new RuntimeException("Missing live moderation route: {$routeName}");
    }
}

foreach (['system.discussions.moderation.live', 'system.discussions.moderation.live.feed', 'system.discussions.moderation.remove'] as $routeName) {
    $middleware = Route::getRoutes()->getByName($routeName)?->gatherMiddleware() ?? [];
    if (! in_array('permission:discussions.moderate', $middleware, true)) {
        throw new RuntimeException("Live moderation route {$routeName} lost its moderation permission guard.");
    }
}

$feedMiddleware = Route::getRoutes()->getByName('system.discussions.moderation.live.feed')?->gatherMiddleware() ?? [];
if (! in_array('throttle:60,1', $feedMiddleware, true)) {
    throw new RuntimeException('The live moderation feed lost its per-user request throttle.');
}

$liveScript = file_get_contents(public_path('assets/js/discussion-live-moderation.js'));
if (! is_string($liveScript)) {
    throw new RuntimeException('The bounded live moderation client script is missing.');
}

foreach ([
    'AbortController',
    'if (inFlight)',
    'sync_cursor',
    'visible_ids',
    'pagehide',
    'statsRefreshInterval',
    "endpoint.searchParams.set('include_stats', '1')",
    'fullRefreshInterval',
    'maxImmediateRecoveryAttempts = 2',
    'if (actionInFlight)',
    'card === activeCard',
    'topicFilter.value = selectedTopicValue',
] as $safetyMarker) {
    if (! str_contains($liveScript, $safetyMarker)) {
        throw new RuntimeException("The live moderation client is missing the {$safetyMarker} safety control.");
    }
}

if (str_contains($liveScript, 'setInterval(')) {
    throw new RuntimeException('The live moderation client uses overlapping interval polling.');
}

if (str_contains($liveScript, 'createDocumentFragment')) {
    throw new RuntimeException('The live moderation client can move the active editor through a document fragment.');
}

$admin = User::query()
    ->whereHas('role', fn ($query) => $query->where('name', 'System Admin'))
    ->firstOrFail();

$app['auth']->guard()->setUser($admin);
$controller = $app->make(DiscussionAdminController::class);

DB::beginTransaction();

try {
    $suffix = Str::lower(Str::random(12));
    $participant = DiscussionParticipant::query()->create([
        'display_name' => 'Live Moderation Smoke',
        'email' => "live-moderation-{$suffix}@example.test",
        'password' => 'LiveModerationPass2026',
        'country' => 'Kenya',
        'status' => 'active',
        'terms_accepted_at' => now(),
    ]);
    $topic = DiscussionTopic::query()->create([
        'created_by' => $admin->id,
        'title' => 'Live Moderation Smoke '.Str::upper($suffix),
        'slug' => "live-moderation-{$suffix}",
        'summary' => 'A temporary topic used to verify the live moderation stream.',
        'status' => 'open',
        'is_featured' => false,
        'requires_moderation' => false,
        'allow_replies' => true,
    ]);
    $previewTailMarker = 'LIVE_PREVIEW_TAIL_MUST_NOT_BE_RENDERED';
    $post = DiscussionPost::query()->create([
        'topic_id' => $topic->id,
        'participant_id' => $participant->id,
        'body' => str_repeat('Temporary contribution for live moderation smoke coverage. ', 40).$previewTailMarker,
        'status' => 'published',
    ]);
    $stableTimestamp = now()->subMinute()->startOfSecond();
    DB::table('discussion_posts')->where('id', $post->id)->update([
        'created_at' => $stableTimestamp,
        'updated_at' => $stableTimestamp,
    ]);
    $post->refresh();

    $feedRequest = Request::create('/system/discussions/moderation/live/feed', 'GET', [
        'topic_id' => $topic->id,
    ]);
    $feedRequest->headers->set('Accept', 'application/json');
    $feedRequest->setUserResolver(fn () => $admin);
    $app->instance('request', $feedRequest);

    $feedPayload = $controller->liveModerationFeed($feedRequest)->getData(true);
    $feedItem = collect($feedPayload['items'] ?? [])->firstWhere('id', $post->id);

    if (! $feedItem || ! str_contains($feedItem['html'] ?? '', 'Remove rule violation')) {
        throw new RuntimeException('The live feed did not include the published contribution removal control.');
    }

    if (! str_contains($feedItem['html'], 'Preview limited for live-monitor performance')
        || str_contains($feedItem['html'], $previewTailMarker)
        || ! str_contains($feedItem['html'], 'data-live-version="'.$feedItem['version'].'"')) {
        throw new RuntimeException('The live card preview or relation-aware version is not bounded and synchronized.');
    }

    if (($feedPayload['stats']['live'] ?? 0) !== 1) {
        throw new RuntimeException('The topic-filtered live contribution count is incorrect.');
    }

    if (($feedPayload['meta']['limit'] ?? null) !== 40
        || ($feedPayload['meta']['is_delta'] ?? null) !== false
        || ! in_array($post->id, $feedPayload['visible_ids'] ?? [], true)
        || empty($feedPayload['sync_cursor'])) {
        throw new RuntimeException('The initial live feed is missing its bounded snapshot metadata.');
    }

    $deltaRequest = Request::create('/system/discussions/moderation/live/feed', 'GET', [
        'topic_id' => $topic->id,
        'cursor' => $feedPayload['sync_cursor'],
    ]);
    $deltaRequest->headers->set('Accept', 'application/json');
    $deltaRequest->setUserResolver(fn () => $admin);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $unchangedDeltaResponse = $controller->liveModerationFeed($deltaRequest);
    $unchangedDeltaPayload = $unchangedDeltaResponse->getData(true);
    $deltaQueryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    if (($unchangedDeltaPayload['meta']['is_delta'] ?? null) !== true
        || count($unchangedDeltaPayload['items'] ?? []) !== 0
        || ($unchangedDeltaPayload['stats'] ?? null) !== null
        || ! in_array($post->id, $unchangedDeltaPayload['visible_ids'] ?? [], true)) {
        throw new RuntimeException('An unchanged live feed did not return a metadata-only delta.');
    }

    if (strlen((string) $unchangedDeltaResponse->getContent()) > 20000 || $deltaQueryCount > 3) {
        throw new RuntimeException('The unchanged live delta exceeded its payload or query budget.');
    }

    $statsDeltaRequest = Request::create('/system/discussions/moderation/live/feed', 'GET', [
        'topic_id' => $topic->id,
        'cursor' => $feedPayload['sync_cursor'],
        'include_stats' => '1',
    ]);
    $statsDeltaRequest->headers->set('Accept', 'application/json');
    $statsDeltaRequest->setUserResolver(fn () => $admin);
    $statsDelta = $controller->liveModerationFeed($statsDeltaRequest)->getData(true);
    if (($statsDelta['stats']['live'] ?? null) !== 1
        || ($statsDelta['meta']['stats_included'] ?? null) !== true) {
        throw new RuntimeException('The slower live totals cadence did not return cached statistics on request.');
    }

    $initialVersion = $feedItem['version'];
    $participant->update(['display_name' => 'Live Relation Refresh']);
    $relationDelta = $controller->liveModerationFeed($deltaRequest)->getData(true);
    if (count($relationDelta['items'] ?? []) !== 0) {
        throw new RuntimeException('A relation-only edit made the lightweight post delta unexpectedly render cards.');
    }

    $relationSnapshot = $controller->liveModerationFeed($feedRequest)->getData(true);
    $relationItem = collect($relationSnapshot['items'] ?? [])->firstWhere('id', $post->id);
    if (! $relationItem
        || $relationItem['version'] === $initialVersion
        || ! str_contains($relationItem['html'] ?? '', 'Live Relation Refresh')) {
        throw new RuntimeException('A periodic full snapshot did not expose a relation-only live card change.');
    }

    $reaction = DiscussionReaction::query()->create([
        'post_id' => $post->id,
        'participant_id' => $participant->id,
        'type' => 'like',
    ]);
    $reactionDelta = $controller->liveModerationFeed($deltaRequest)->getData(true);
    if (($reactionDelta['reaction_counts'][$post->id] ?? null) !== 1) {
        throw new RuntimeException('The live delta did not refresh a newly added reaction count.');
    }

    $reaction->delete();
    $reactionRemovalDelta = $controller->liveModerationFeed($deltaRequest)->getData(true);
    if (($reactionRemovalDelta['reaction_counts'][$post->id] ?? null) !== 0) {
        throw new RuntimeException('The live delta did not refresh a removed reaction count to zero.');
    }

    // Database timestamps are second-precision on supported deployments. Simulate
    // an update in the cursor's second; the two-second overlap must still return it.
    $cursorSecond = Carbon::parse($feedPayload['sync_cursor'])->startOfSecond();
    DB::table('discussion_posts')->where('id', $post->id)->update([
        'body' => 'Contribution updated within the cursor precision overlap.',
        'updated_at' => $cursorSecond,
    ]);
    $sameSecondDelta = $controller->liveModerationFeed($deltaRequest)->getData(true);
    if (! collect($sameSecondDelta['items'] ?? [])->contains('id', $post->id)) {
        throw new RuntimeException('A same-second contribution update was missed by live delta polling.');
    }
    $post->refresh();

    $invalidRequest = Request::create('/system/discussions/moderation/'.$post->id.'/remove', 'PATCH');
    $invalidRequest->headers->set('Accept', 'application/json');
    $invalidRequest->setUserResolver(fn () => $admin);
    $app->instance('request', $invalidRequest);

    try {
        $controller->removePost($invalidRequest, $post);
        throw new RuntimeException('A contribution was removable without a rule-violation reason.');
    } catch (ValidationException $exception) {
        if (empty($exception->errors()['reason'])) {
            throw $exception;
        }
    }

    $reason = 'Contains a personal attack prohibited by the ATTP community rules.';
    $removeRequest = Request::create('/system/discussions/moderation/'.$post->id.'/remove', 'PATCH', [
        'reason' => $reason,
    ]);
    $removeRequest->headers->set('Accept', 'application/json');
    $removeRequest->headers->set('X-Requested-With', 'XMLHttpRequest');
    $removeRequest->setUserResolver(fn () => $admin);
    $app->instance('request', $removeRequest);

    $removeResponse = $controller->removePost($removeRequest, $post);
    if ($removeResponse->getStatusCode() !== 200) {
        throw new RuntimeException('The authorized live removal request did not succeed.');
    }

    $post->refresh();
    if ($post->status !== 'removed' || $post->moderation_reason !== $reason || $post->moderated_by !== $admin->id) {
        throw new RuntimeException('The removed contribution did not retain its moderator audit details.');
    }

    if (! DiscussionModerationAction::query()
        ->where('subject_type', 'post')
        ->where('subject_id', $post->id)
        ->where('action', 'removed')
        ->where('reason', $reason)
        ->exists()) {
        throw new RuntimeException('The live removal did not create a moderation audit action.');
    }

    $publicRequest = Request::create("/api/discussions/topics/{$topic->slug}", 'GET');
    $publicPayload = $app->make(DiscussionForumController::class)
        ->show($publicRequest, $topic)
        ->getData(true);
    if (collect($publicPayload['data']['posts'] ?? [])->contains('id', $post->id)) {
        throw new RuntimeException('A moderator-removed contribution remained visible in the public discussion API.');
    }

    $activityPayload = $app->make(DiscussionForumController::class)
        ->topicActivity($publicRequest, $topic)
        ->getData(true);
    if (($activityPayload['data']['contributions_count'] ?? null) !== 0) {
        throw new RuntimeException('The live public activity count still included a removed contribution.');
    }

    $updatedFeedPayload = $controller->liveModerationFeed($feedRequest)->getData(true);
    $updatedItem = collect($updatedFeedPayload['items'] ?? [])->firstWhere('id', $post->id);
    if (! $updatedItem || ! str_contains($updatedItem['html'] ?? '', 'Removed from the public discussion')) {
        throw new RuntimeException('The live feed did not clearly show the removed contribution state.');
    }
    if (($updatedFeedPayload['stats']['live'] ?? null) !== 0
        || ($updatedFeedPayload['stats']['removed'] ?? null) !== 1) {
        throw new RuntimeException('Moderation did not invalidate the cached live statistics.');
    }

    $burstTimestamp = now()->subMinute()->startOfSecond();
    $burstRows = collect(range(1, 45))->map(fn (int $number): array => [
        'id' => (string) Str::uuid(),
        'topic_id' => $topic->id,
        'participant_id' => $participant->id,
        'parent_id' => null,
        'body' => "Bounded live-feed contribution {$number}.",
        'status' => 'published',
        'created_at' => $burstTimestamp->copy()->addSeconds($number),
        'updated_at' => $burstTimestamp->copy()->addSeconds($number),
    ])->all();
    DB::table('discussion_posts')->insert($burstRows);

    $boundedFeed = $controller->liveModerationFeed($feedRequest)->getData(true);
    if (count($boundedFeed['items'] ?? []) !== 40
        || count($boundedFeed['visible_ids'] ?? []) !== 40
        || ($boundedFeed['meta']['visible_count'] ?? null) !== 40) {
        throw new RuntimeException('The live moderation snapshot exceeded or failed to fill its 40-card bound.');
    }

    echo "DISCUSSION_LIVE_MODERATION_OK\n";
} finally {
    DB::rollBack();
}
