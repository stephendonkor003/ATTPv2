<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuMemberState;
use App\Models\DiscussionParticipant;
use App\Models\DiscussionPost;
use App\Models\DiscussionReaction;
use App\Models\DiscussionTheme;
use App\Models\DiscussionTopic;
use App\Models\DiscussionTopicDocument;
use App\Models\DiscussionTopicParticipant;
use App\Services\DiscussionParticipantTokenService;
use App\Support\DiscussionAccountEmailPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class DiscussionForumController extends Controller
{
    public function __construct(private readonly DiscussionParticipantTokenService $tokens) {}

    public function overview(): JsonResponse
    {
        return response()->json([
            'data' => [
                'themes' => DiscussionTheme::query()->where('is_active', true)->count(),
                'active_discussions' => DiscussionTopic::query()
                    ->publiclyVisible()
                    ->where('status', 'open')
                    ->where(fn (Builder $query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
                    ->count(),
                'participants' => DiscussionParticipant::query()->where('status', 'active')->count(),
                'published_contributions' => DiscussionPost::query()->where('status', 'published')->count(),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function themes(): JsonResponse
    {
        $themes = DiscussionTheme::query()
            ->where('is_active', true)
            ->withCount([
                'topics as active_discussions_count' => fn (Builder $query) => $query
                    ->where('status', 'open')
                    ->where(fn (Builder $builder) => $builder->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
                    ->where(fn (Builder $builder) => $builder->whereNull('closes_at')->orWhere('closes_at', '>', now())),
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get()
            ->map(fn (DiscussionTheme $theme) => [
                'id' => $theme->id,
                'name' => $theme->name,
                'slug' => $theme->slug,
                'description' => $theme->description,
                'icon' => $theme->icon,
                'color' => $theme->color,
                'active_discussions_count' => (int) $theme->active_discussions_count,
            ]);

        return response()->json(['data' => $themes]);
    }

    public function countries(): JsonResponse
    {
        $participantCounts = DiscussionParticipant::query()
            ->where('status', 'active')
            ->whereNotNull('country')
            ->where('country', '<>', '')
            ->selectRaw('LOWER(TRIM(country)) as normalized_country, COUNT(*) as participants_count')
            ->groupByRaw('LOWER(TRIM(country))')
            ->pluck('participants_count', 'normalized_country');

        $countries = AuMemberState::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'code', 'code_alpha2', 'flag_path'])
            ->map(function (AuMemberState $country) use ($participantCounts): array {
                $iso2 = Str::upper(trim((string) $country->code_alpha2));

                return [
                    'id' => $country->id,
                    'name' => $country->name,
                    'iso2' => $iso2,
                    'iso3' => Str::upper(trim((string) $country->code)),
                    'flag' => $this->countryFlagEmoji($iso2),
                    'flag_url' => asset('admin/assets/vendors/img/flags/4x3/'.Str::lower($iso2).'.svg'),
                    'participants_count' => (int) ($participantCounts[Str::lower(trim($country->name))] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'data' => $countries,
            'meta' => [
                'represented_countries' => $countries->where('participants_count', '>', 0)->count(),
                'participants_with_country' => $countries->sum('participants_count'),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function topics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['nullable', 'string', 'max:255'],
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['open', 'closed', 'all'])],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $query = DiscussionTopic::query()
            ->publiclyVisible()
            ->with('theme:id,name,slug,color,icon')
            ->withCount(['posts as contributions_count' => fn (Builder $builder) => $builder->where('status', 'published')])
            ->withMax(['posts as last_contribution_at' => fn (Builder $builder) => $builder->where('status', 'published')], 'created_at')
            ->withExists(['posts as has_published_contributions' => fn (Builder $builder) => $builder->where('status', 'published')]);

        $status = $validated['status'] ?? 'open';
        if ($status === 'open') {
            $query->where('status', $status);
            $query->where(fn (Builder $builder) => $builder->whereNull('closes_at')->orWhere('closes_at', '>', now()));
        } elseif ($status === 'closed') {
            $query->where(function (Builder $builder): void {
                $builder->where('status', 'closed')
                    ->orWhere(fn (Builder $expired) => $expired->where('status', 'open')->where('closes_at', '<=', now()));
            });
        }

        if (! empty($validated['theme'])) {
            $theme = $validated['theme'];
            $query->whereHas('theme', function (Builder $builder) use ($theme): void {
                if (Str::isUuid($theme)) {
                    $builder->whereKey($theme);

                    return;
                }

                $builder->where('slug', $theme);
            });
        }

        if (! empty($validated['search'])) {
            $search = trim($validated['search']);
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', "%{$search}%")
                    ->orWhere('summary', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%");
            });
        }

        $paginator = $query
            ->orderByDesc('is_featured')
            ->orderByDesc('has_published_contributions')
            ->orderByDesc('last_contribution_at')
            ->orderByDesc('created_at')
            ->paginate(12)
            ->withQueryString();

        return response()->json([
            'data' => collect($paginator->items())->map(fn (DiscussionTopic $topic) => $this->topicSummary($topic))->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function show(Request $request, DiscussionTopic $topic): JsonResponse
    {
        abort_unless(
            DiscussionTopic::query()->publiclyVisible()->whereKey($topic->getKey())->exists(),
            404
        );

        $topic->load(['theme:id,name,slug,color,icon', 'uploadedDocuments'])
            ->loadCount(['posts as contributions_count' => fn (Builder $builder) => $builder->where('status', 'published')])
            ->loadMax(['posts as last_contribution_at' => fn (Builder $builder) => $builder->where('status', 'published')], 'created_at');
        $participantToken = $this->tokens->resolve($request, false);
        $participantId = $participantToken?->participant?->id;

        $posts = DiscussionPost::query()
            ->where('topic_id', $topic->id)
            ->where('status', 'published')
            ->with('participant:id,display_name,country,organization')
            ->oldest()
            ->limit(250)
            ->get();

        [$reactionCounts, $viewerReactions] = $this->reactionState($posts, $participantId);

        $resources = $topic->resourceCollections();
        $resources['documents'] = collect($resources['documents'])
            ->concat($topic->uploadedDocuments->map(fn (DiscussionTopicDocument $document) => $this->uploadedDocumentData($document)))
            ->values()
            ->all();

        return response()->json([
            'data' => [
                ...$this->topicSummary($topic),
                'body' => $topic->body,
                'accepts_posts' => $topic->acceptsPosts(),
                'activity_revision' => DiscussionPost::query()
                    ->where('topic_id', $topic->id)
                    ->max('updated_at'),
                ...$resources,
                'participation' => $this->topicParticipationData($topic, $participantId),
                'posts' => $posts->map(fn (DiscussionPost $post) => $this->postData(
                    $post,
                    $reactionCounts[$post->id] ?? [],
                    $viewerReactions[$post->id] ?? []
                ))->values(),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ]);
    }

    public function topicActivity(Request $request, DiscussionTopic $topic): JsonResponse
    {
        abort_unless(
            DiscussionTopic::query()->publiclyVisible()->whereKey($topic->getKey())->exists(),
            404
        );

        $participantToken = $this->tokens->resolve($request, false);
        $participantId = $participantToken?->participant?->id;

        $response = response()->json([
            'data' => [
                'participation' => $this->topicParticipationData($topic, $participantId),
                'contributions_count' => DiscussionPost::query()
                    ->where('topic_id', $topic->id)
                    ->where('status', 'published')
                    ->count(),
                'activity_revision' => DiscussionPost::query()
                    ->where('topic_id', $topic->id)
                    ->max('updated_at'),
            ],
            'refreshed_at' => now()->toIso8601String(),
        ]);

        return $response->header('Cache-Control', 'no-store, private');
    }

    public function joinTopic(Request $request, DiscussionTopic $topic): JsonResponse
    {
        abort_unless(
            DiscussionTopic::query()->publiclyVisible()->whereKey($topic->getKey())->exists(),
            404
        );

        $participant = $this->participant($request);
        $presence = $this->touchTopicParticipation($topic, $participant);

        return response()->json([
            'message' => $presence->wasRecentlyCreated
                ? "{$participant->display_name} joined this discussion."
                : 'Your discussion presence is active.',
            'joined_now' => $presence->wasRecentlyCreated,
            'data' => $this->topicParticipationData($topic, $participant->id),
            'refreshed_at' => now()->toIso8601String(),
        ], $presence->wasRecentlyCreated ? 201 : 200);
    }

    public function register(Request $request): JsonResponse
    {
        $request->merge([
            'display_name' => trim((string) $request->input('display_name')),
            'email' => Str::lower(trim((string) $request->input('email'))),
        ]);

        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (DiscussionAccountEmailPolicy::unavailableForParticipant((string) $value)) {
                        $fail(DiscussionAccountEmailPolicy::UNAVAILABLE_MESSAGE);
                    }
                },
            ],
            'country' => [
                'required',
                'string',
                'max:120',
                Rule::exists('myb_au_member_states', 'name')->where('is_active', true),
            ],
            'organization' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ]);

        try {
            $result = DB::transaction(function () use ($validated, $request): array {
                $participant = DiscussionParticipant::query()->create([
                    'display_name' => trim($validated['display_name']),
                    'email' => DiscussionAccountEmailPolicy::normalize($validated['email']),
                    'password' => $validated['password'],
                    'country' => filled($validated['country'] ?? null) ? trim($validated['country']) : null,
                    'organization' => filled($validated['organization'] ?? null) ? trim($validated['organization']) : null,
                    'status' => 'active',
                    'terms_accepted_at' => now(),
                    'last_login_at' => now(),
                    'last_seen_at' => now(),
                ]);

                return [
                    'participant' => $participant,
                    'token' => $this->tokens->issue($participant, $request->userAgent() ?: 'forum-browser'),
                ];
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'email' => [DiscussionAccountEmailPolicy::UNAVAILABLE_MESSAGE],
            ]);
        }

        $response = response()->json([
            'message' => 'Your participant account is ready. This device will keep you signed in until you sign out.',
            'token' => $result['token']['plain_text_token'],
            'token_type' => 'Bearer',
            'expires_at' => $result['token']['token']->expires_at->toIso8601String(),
            'remembered_device' => true,
            'participant' => $this->participantData($result['participant']),
        ], 201);

        return $response->withCookie($this->tokens->rememberedDeviceCookie(
            $request,
            $result['token']['plain_text_token'],
            $result['token']['token']->expires_at
        ));
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $participant = DiscussionParticipant::query()
            ->where('email', Str::lower(trim($validated['email'])))
            ->first();

        if (! $participant || ! Hash::check($validated['password'], $participant->password)) {
            return response()->json([
                'message' => 'The email address or password is incorrect.',
                'code' => 'invalid_credentials',
            ], 422);
        }

        if ($participant->isBlocked()) {
            return response()->json([
                'message' => 'This participant account has been blocked from discussions.',
                'code' => 'participant_blocked',
                'reason' => $participant->blocked_reason,
            ], 403);
        }

        $participant->forceFill([
            'last_login_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        $token = $this->tokens->issue($participant, $request->userAgent() ?: 'forum-browser');

        $response = response()->json([
            'message' => 'Welcome back to the discussion forum. This device will keep you signed in until you sign out.',
            'token' => $token['plain_text_token'],
            'token_type' => 'Bearer',
            'expires_at' => $token['token']->expires_at->toIso8601String(),
            'remembered_device' => true,
            'participant' => $this->participantData($participant),
        ]);

        return $response->withCookie($this->tokens->rememberedDeviceCookie(
            $request,
            $token['plain_text_token'],
            $token['token']->expires_at
        ));
    }

    public function me(Request $request): JsonResponse
    {
        /** @var \App\Models\DiscussionParticipantToken|null $token */
        $token = $request->attributes->get('discussion_participant_token');

        return response()->json([
            'participant' => $this->participantData($this->participant($request)),
            'session' => [
                'remembered_device' => true,
                'expires_at' => $token?->expires_at?->toIso8601String(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revokeCurrent($request);

        $response = response()->json(['message' => 'You have been signed out.']);

        return $response->withCookie($this->tokens->expiredRememberedDeviceCookie($request));
    }

    public function storePost(Request $request, DiscussionTopic $topic): JsonResponse
    {
        abort_unless(
            DiscussionTopic::query()->publiclyVisible()->whereKey($topic->getKey())->exists(),
            404
        );

        if (! $topic->acceptsPosts()) {
            return response()->json([
                'message' => 'This discussion is not currently accepting contributions.',
                'code' => 'discussion_closed',
            ], 422);
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => [
                'nullable',
                'uuid',
                Rule::exists('discussion_posts', 'id')->where(fn ($query) => $query
                    ->where('topic_id', $topic->id)
                    ->where('status', 'published')),
            ],
        ]);

        $participant = $this->participant($request);
        $this->touchTopicParticipation($topic, $participant);
        $post = $topic->posts()->create([
            'participant_id' => $participant->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'body' => trim($validated['body']),
            'status' => 'published',
        ]);

        $post->load('participant:id,display_name,country,organization');

        return response()->json([
            'message' => 'Your contribution is now visible to the discussion community.',
            'moderation_status' => 'published',
            'data' => $this->postData($post),
        ], 201);
    }

    public function toggleReaction(Request $request, DiscussionPost $post): JsonResponse
    {
        $post->loadMissing('topic');

        abort_unless(
            $post->status === 'published'
                && $post->topic
                && DiscussionTopic::query()->publiclyVisible()->whereKey($post->topic_id)->exists(),
            404
        );

        if (! $post->topic->acceptsPosts()) {
            return response()->json([
                'message' => 'This discussion is no longer accepting reactions.',
                'code' => 'discussion_closed',
            ], 422);
        }

        $validated = $request->validate([
            'type' => ['nullable', 'string', Rule::in(DiscussionReaction::ALLOWED_TYPES)],
        ]);
        $type = $validated['type'] ?? 'like';
        $participant = $this->participant($request);
        $reacted = DB::transaction(function () use ($post, $participant, $type): bool {
            // Serialize toggles for a post so rapid duplicate requests cannot
            // race the post/participant/type unique constraint.
            DiscussionPost::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();

            $existing = DiscussionReaction::query()
                ->where('post_id', $post->id)
                ->where('participant_id', $participant->id)
                ->where('type', $type)
                ->first();

            if ($existing) {
                $existing->delete();

                return false;
            }

            DiscussionReaction::query()->create([
                'post_id' => $post->id,
                'participant_id' => $participant->id,
                'type' => $type,
            ]);

            return true;
        });

        [$reactionCounts, $viewerReactions] = $this->reactionState(collect([$post]), $participant->id);
        $counts = $this->normaliseReactionCounts($reactionCounts[$post->id] ?? []);
        $viewerTypes = $this->normaliseViewerReactions($viewerReactions[$post->id] ?? []);

        return response()->json([
            'type' => $type,
            'reacted' => $reacted,
            'reactions' => $counts,
            'viewer_reactions' => $viewerTypes,
            'reactions_count' => array_sum($counts),
            'viewer_reacted' => in_array('like', $viewerTypes, true),
        ]);
    }

    private function participant(Request $request): DiscussionParticipant
    {
        /** @var DiscussionParticipant $participant */
        $participant = $request->attributes->get('discussion_participant');

        return $participant;
    }

    private function participantData(DiscussionParticipant $participant): array
    {
        return [
            'id' => $participant->id,
            'display_name' => $participant->display_name,
            'email' => $participant->email,
            'country' => $participant->country,
            'organization' => $participant->organization,
            'status' => $participant->status,
            'joined_at' => $participant->created_at?->toIso8601String(),
        ];
    }

    private function topicSummary(DiscussionTopic $topic): array
    {
        return [
            'id' => $topic->id,
            'title' => $topic->title,
            'slug' => $topic->slug,
            'summary' => $topic->summary,
            'status' => $topic->status,
            'accepts_posts' => $topic->acceptsPosts(),
            'is_featured' => $topic->is_featured,
            'theme' => $topic->theme ? [
                'id' => $topic->theme->id,
                'name' => $topic->theme->name,
                'slug' => $topic->theme->slug,
                'color' => $topic->theme->color,
                'icon' => $topic->theme->icon,
            ] : null,
            'contributions_count' => (int) ($topic->contributions_count ?? $topic->posts_count ?? 0),
            'starts_at' => $topic->starts_at?->toIso8601String(),
            'closes_at' => $topic->closes_at?->toIso8601String(),
            'created_at' => $topic->created_at?->toIso8601String(),
            'last_activity_at' => $topic->last_contribution_at
                ? Carbon::parse($topic->last_contribution_at)->toIso8601String()
                : $topic->created_at?->toIso8601String(),
        ];
    }

    private function touchTopicParticipation(
        DiscussionTopic $topic,
        DiscussionParticipant $participant
    ): DiscussionTopicParticipant {
        $now = now();

        try {
            $presence = DiscussionTopicParticipant::query()->firstOrCreate(
                [
                    'topic_id' => $topic->id,
                    'participant_id' => $participant->id,
                ],
                [
                    'joined_at' => $now,
                    'last_seen_at' => $now,
                ]
            );
        } catch (UniqueConstraintViolationException) {
            $presence = DiscussionTopicParticipant::query()
                ->where('topic_id', $topic->id)
                ->where('participant_id', $participant->id)
                ->firstOrFail();
        }

        if (! $presence->wasRecentlyCreated
            && (! $presence->last_seen_at || $presence->last_seen_at->lt($now->copy()->subSeconds(15)))) {
            $presence->forceFill(['last_seen_at' => $now])->save();
        }

        return $presence;
    }

    /**
     * @return array<string, mixed>
     */
    private function topicParticipationData(DiscussionTopic $topic, ?string $viewerId = null): array
    {
        $baseQuery = DiscussionTopicParticipant::query()
            ->where('discussion_topic_participants.topic_id', $topic->id)
            ->whereHas('participant', fn (Builder $query) => $query->where('status', 'active'));

        $countries = (clone $baseQuery)
            ->join(
                'discussion_participants as topic_participants',
                'topic_participants.id',
                '=',
                'discussion_topic_participants.participant_id'
            )
            ->whereNotNull('topic_participants.country')
            ->where('topic_participants.country', '<>', '')
            ->distinct()
            ->pluck('topic_participants.country')
            ->map(fn (string $country) => trim($country))
            ->filter()
            ->values();

        $memberStates = AuMemberState::query()
            ->active()
            ->whereIn('name', $countries)
            ->get(['name', 'code_alpha2'])
            ->keyBy(fn (AuMemberState $country) => Str::lower(trim($country->name)));

        $recentJoiners = (clone $baseQuery)
            ->with('participant:id,display_name,country')
            ->latest('joined_at')
            ->limit(8)
            ->get()
            ->map(function (DiscussionTopicParticipant $presence) use ($memberStates): array {
                $countryName = trim((string) $presence->participant?->country);
                /** @var AuMemberState|null $memberState */
                $memberState = $memberStates->get(Str::lower($countryName));
                $iso2 = Str::upper(trim((string) $memberState?->code_alpha2));

                return [
                    'id' => $presence->id,
                    'display_name' => $presence->participant?->display_name ?: 'ATTP participant',
                    'country' => $countryName ?: null,
                    'flag' => $this->countryFlagEmoji($iso2),
                    'flag_url' => preg_match('/^[A-Z]{2}$/', $iso2)
                        ? asset('admin/assets/vendors/img/flags/4x3/'.Str::lower($iso2).'.svg')
                        : null,
                    'joined_at' => $presence->joined_at?->toIso8601String(),
                ];
            })
            ->values();

        return [
            'participants_count' => (clone $baseQuery)->count(),
            'countries_count' => $countries->map(fn (string $country) => Str::lower($country))->unique()->count(),
            'active_now_count' => (clone $baseQuery)->where('last_seen_at', '>=', now()->subMinutes(2))->count(),
            'viewer_joined' => $viewerId
                ? (clone $baseQuery)->where('participant_id', $viewerId)->exists()
                : false,
            'recent_joiners' => $recentJoiners,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadedDocumentData(DiscussionTopicDocument $document): array
    {
        $downloadUrl = route('discussion.documents.download', $document);

        return [
            'id' => $document->id,
            'title' => $document->title,
            'description' => $document->description,
            'type' => $document->type,
            'source' => 'upload',
            'filename' => $document->file_name,
            'extension' => $document->extension(),
            'mime_type' => $document->mime_type,
            'size_bytes' => $document->size_bytes,
            'size' => $document->humanReadableSize(),
            'can_preview' => $document->canPreview(),
            'view_url' => $document->canPreview()
                ? route('discussion.documents.read', $document)
                : null,
            'download_url' => $downloadUrl,
            'url' => $downloadUrl,
        ];
    }

    /**
     * @param  array<string, int>  $reactionCounts
     * @param  list<string>  $viewerReactions
     */
    private function postData(DiscussionPost $post, array $reactionCounts = [], array $viewerReactions = []): array
    {
        $reactions = $this->normaliseReactionCounts($reactionCounts);
        $viewerTypes = $this->normaliseViewerReactions($viewerReactions);

        return [
            'id' => $post->id,
            'parent_id' => $post->parent_id,
            'body' => $post->body,
            'status' => $post->status,
            'author' => [
                'display_name' => $post->participant->display_name,
                'country' => $post->participant->country,
                'organization' => $post->participant->organization,
            ],
            'reactions' => $reactions,
            'viewer_reactions' => $viewerTypes,
            'reactions_count' => array_sum($reactions),
            'viewer_reacted' => in_array('like', $viewerTypes, true),
            'created_at' => $post->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, DiscussionPost>  $posts
     * @return array{0: array<string, array<string, int>>, 1: array<string, list<string>>}
     */
    private function reactionState(Collection $posts, ?string $participantId): array
    {
        $postIds = $posts->pluck('id')->filter()->values();

        if ($postIds->isEmpty()) {
            return [[], []];
        }

        $reactionCounts = [];
        DiscussionReaction::query()
            ->whereIn('post_id', $postIds)
            ->whereIn('type', DiscussionReaction::ALLOWED_TYPES)
            ->select(['post_id', 'type'])
            ->selectRaw('COUNT(*) AS reactions_count')
            ->groupBy('post_id', 'type')
            ->get()
            ->each(function (DiscussionReaction $reaction) use (&$reactionCounts): void {
                $reactionCounts[$reaction->post_id][$reaction->type] = (int) $reaction->reactions_count;
            });

        $viewerReactions = [];
        if ($participantId) {
            DiscussionReaction::query()
                ->where('participant_id', $participantId)
                ->whereIn('post_id', $postIds)
                ->whereIn('type', DiscussionReaction::ALLOWED_TYPES)
                ->get(['post_id', 'type'])
                ->each(function (DiscussionReaction $reaction) use (&$viewerReactions): void {
                    $viewerReactions[$reaction->post_id][] = $reaction->type;
                });
        }

        return [$reactionCounts, $viewerReactions];
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, int>
     */
    private function normaliseReactionCounts(array $counts): array
    {
        $normalised = [];

        foreach (DiscussionReaction::ALLOWED_TYPES as $type) {
            $normalised[$type] = max(0, (int) ($counts[$type] ?? 0));
        }

        return $normalised;
    }

    /**
     * @param  list<string>  $viewerReactions
     * @return list<string>
     */
    private function normaliseViewerReactions(array $viewerReactions): array
    {
        return array_values(array_filter(
            DiscussionReaction::ALLOWED_TYPES,
            fn (string $type): bool => in_array($type, $viewerReactions, true)
        ));
    }

    private function countryFlagEmoji(string $iso2): string
    {
        if (! preg_match('/^[A-Z]{2}$/', $iso2)) {
            return '🌍';
        }

        return implode('', array_map(
            fn (string $letter): string => mb_chr(127397 + ord($letter), 'UTF-8'),
            str_split($iso2)
        ));
    }
}
