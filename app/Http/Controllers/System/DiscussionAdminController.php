<?php

namespace App\Http\Controllers\System;

use App\Http\Controllers\Controller;
use App\Models\DiscussionModerationAction;
use App\Models\DiscussionParticipant;
use App\Models\DiscussionPost;
use App\Models\DiscussionReaction;
use App\Models\DiscussionTheme;
use App\Models\DiscussionTopic;
use App\Models\DiscussionTopicDocument;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DiscussionAdminController extends Controller
{
    private const TOPIC_STATUSES = ['draft', 'open', 'closed', 'archived'];

    private const POST_STATUSES = ['published', 'removed'];

    private const TOPIC_RESOURCE_TYPES = [
        'related_links' => ['link'],
        'materials' => ['website', 'article', 'brief', 'dataset', 'report', 'toolkit', 'video', 'guidance', 'other'],
        'documents' => ['pdf', 'word', 'spreadsheet', 'presentation', 'text', 'guide', 'archive', 'other'],
    ];

    private const DOCUMENT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png', 'zip',
    ];

    private const MAX_UPLOADED_DOCUMENTS = 20;

    private const LIVE_FEED_LIMIT = 40;

    private const LIVE_CURSOR_OVERLAP_SECONDS = 2;

    private const LIVE_TOPIC_FILTER_LIMIT = 150;

    public function dashboard(): View
    {
        $topicQuery = DiscussionTopic::query();
        $postQuery = DiscussionPost::query();
        $participantQuery = DiscussionParticipant::query();

        $stats = [
            'topics' => (clone $topicQuery)->count(),
            'open_topics' => (clone $topicQuery)->where('status', 'open')->count(),
            'draft_topics' => (clone $topicQuery)->where('status', 'draft')->count(),
            'published_posts' => (clone $postQuery)->where('status', 'published')->count(),
            'removed_posts' => (clone $postQuery)->where('status', 'removed')->count(),
            'participants' => (clone $participantQuery)->count(),
            'active_participants' => (clone $participantQuery)->where('status', 'active')->count(),
            'blocked_participants' => (clone $participantQuery)->where('status', 'blocked')->count(),
            'active_themes' => DiscussionTheme::query()->where('is_active', true)->count(),
        ];

        $recentTopics = DiscussionTopic::query()
            ->with(['theme:id,name,color', 'creator:id,name'])
            ->withCount([
                'posts',
                'posts as published_posts_count' => fn (Builder $query) => $query->where('status', 'published'),
                'posts as removed_posts_count' => fn (Builder $query) => $query->where('status', 'removed'),
            ])
            ->latest('updated_at')
            ->limit(6)
            ->get();

        $recentPosts = DiscussionPost::query()
            ->where('status', 'published')
            ->with(['participant:id,display_name,email', 'topic:id,title'])
            ->latest('created_at')
            ->limit(6)
            ->get();

        $recentActions = DiscussionModerationAction::query()
            ->with('moderator:id,name')
            ->latest()
            ->limit(8)
            ->get();

        return view('system.discussions.dashboard', compact(
            'stats',
            'recentTopics',
            'recentPosts',
            'recentActions'
        ));
    }

    public function index(Request $request): View
    {
        $topics = DiscussionTopic::query()
            ->with(['theme:id,name,color', 'creator:id,name'])
            ->withCount([
                'posts',
                'posts as published_posts_count' => fn (Builder $query) => $query->where('status', 'published'),
                'posts as removed_posts_count' => fn (Builder $query) => $query->where('status', 'removed'),
            ])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('summary', 'like', "%{$term}%")
                        ->orWhere('body', 'like', "%{$term}%");
                });
            })
            ->when(
                in_array($request->input('status'), self::TOPIC_STATUSES, true),
                fn (Builder $query) => $query->where('status', $request->input('status'))
            )
            ->when(
                $request->filled('theme_id'),
                fn (Builder $query) => $query->where('theme_id', $request->input('theme_id'))
            )
            ->when(
                $request->input('featured') === '1',
                fn (Builder $query) => $query->where('is_featured', true)
            )
            ->orderByDesc('is_featured')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        $themes = DiscussionTheme::query()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('system.discussions.topics.index', [
            'topics' => $topics,
            'themes' => $themes,
            'topicStatuses' => self::TOPIC_STATUSES,
        ]);
    }

    public function create(): View
    {
        return view('system.discussions.topics.form', [
            'topic' => new DiscussionTopic([
                'status' => 'draft',
                'requires_moderation' => false,
                'allow_replies' => true,
            ]),
            'themes' => $this->themeOptions(),
            'topicStatuses' => self::TOPIC_STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $uploadedDocumentData = $this->validateUploadedDocuments($request);
        $data = $this->validateTopic($request);
        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug(DiscussionTopic::class, $data['title']);
        $data['created_by'] = $request->user()->id;

        $newPaths = [];

        try {
            $topic = DB::transaction(function () use ($request, $data, $uploadedDocumentData, &$newPaths): DiscussionTopic {
                $topic = DiscussionTopic::query()->create($data);
                $obsoletePaths = [];
                $this->synchronizeUploadedDocuments(
                    $request,
                    $topic,
                    $uploadedDocumentData,
                    $newPaths,
                    $obsoletePaths
                );

                return $topic;
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }

        $this->recordAction($request, 'topic', $topic->id, 'created', null, [
            'title' => $topic->title,
            'status' => $topic->status,
            'resource_counts' => [
                ...array_map('count', $topic->resourceCollections()),
                'uploaded_documents' => $topic->uploadedDocuments()->count(),
            ],
        ]);

        return redirect()
            ->route('system.discussions.topics.edit', $topic)
            ->with('success', 'Discussion created successfully.');
    }

    public function edit(DiscussionTopic $topic): View
    {
        $topic->load('uploadedDocuments');
        $topic->loadCount([
            'posts',
            'posts as removed_posts_count' => fn (Builder $query) => $query->where('status', 'removed'),
        ]);

        return view('system.discussions.topics.form', [
            'topic' => $topic,
            'themes' => $this->themeOptions(),
            'topicStatuses' => self::TOPIC_STATUSES,
        ]);
    }

    public function update(Request $request, DiscussionTopic $topic): RedirectResponse
    {
        $topic->load('uploadedDocuments');
        $uploadedDocumentData = $this->validateUploadedDocuments($request, $topic);
        $previous = Arr::only($topic->getAttributes(), [
            'title',
            'theme_id',
            'status',
            'starts_at',
            'closes_at',
            'is_featured',
            'allow_replies',
            'related_links',
            'materials',
            'documents',
        ]);

        $data = $this->validateTopic($request, $topic);
        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug(DiscussionTopic::class, $data['title'], $topic->id);

        $newPaths = [];
        $obsoletePaths = [];

        try {
            DB::transaction(function () use (
                $request,
                $topic,
                $data,
                $uploadedDocumentData,
                &$newPaths,
                &$obsoletePaths
            ): void {
                $topic->update($data);
                $this->synchronizeUploadedDocuments(
                    $request,
                    $topic,
                    $uploadedDocumentData,
                    $newPaths,
                    $obsoletePaths
                );
            });
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }

        Storage::disk('local')->delete(array_values(array_unique($obsoletePaths)));

        $this->recordAction($request, 'topic', $topic->id, 'updated', null, [
            'title' => $topic->title,
            'before' => $previous,
            'after' => Arr::only($topic->fresh()->getAttributes(), array_keys($previous)),
            'uploaded_documents' => $topic->uploadedDocuments()->count(),
        ]);

        return back()->with('success', 'Discussion details updated.');
    }

    public function updateStatus(Request $request, DiscussionTopic $topic): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::TOPIC_STATUSES)],
        ]);

        $previousStatus = $topic->status;
        $topic->update(['status' => $validated['status']]);

        $this->recordAction($request, 'topic', $topic->id, 'status_changed', null, [
            'title' => $topic->title,
            'from' => $previousStatus,
            'to' => $topic->status,
        ]);

        return back()->with('success', "Discussion status changed to {$topic->status}.");
    }

    public function openUploadedDocument(
        Request $request,
        DiscussionTopic $topic,
        DiscussionTopicDocument $document
    ): StreamedResponse {
        abort_unless($document->topic_id === $topic->id, 404);
        abort_unless(Storage::disk('local')->exists($document->storage_path), 404);

        $inline = $document->canPreview() && ! $request->boolean('download');
        $headers = [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
        ];

        if ($inline) {
            $headers['Content-Security-Policy'] = "default-src 'none'; img-src 'self' data:; style-src 'unsafe-inline'; sandbox";
        }

        return Storage::disk('local')->response(
            $document->storage_path,
            $document->file_name,
            $headers,
            $inline ? 'inline' : 'attachment'
        );
    }

    public function themes(): View
    {
        $themes = DiscussionTheme::query()
            ->withCount([
                'topics',
                'topics as open_topics_count' => fn (Builder $query) => $query->where('status', 'open'),
            ])
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        return view('system.discussions.themes', compact('themes'));
    }

    public function storeTheme(Request $request): RedirectResponse
    {
        $data = $this->validateTheme($request);
        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug(DiscussionTheme::class, $data['name']);

        $theme = DiscussionTheme::query()->create($data);

        $this->recordAction($request, 'theme', $theme->id, 'created', null, [
            'name' => $theme->name,
        ]);

        return back()->with('success', 'Thematic area created.');
    }

    public function updateTheme(Request $request, DiscussionTheme $theme): RedirectResponse
    {
        $data = $this->validateTheme($request, $theme);
        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug(DiscussionTheme::class, $data['name'], $theme->id);
        $theme->update($data);

        $this->recordAction($request, 'theme', $theme->id, 'updated', null, [
            'name' => $theme->name,
            'is_active' => $theme->is_active,
        ]);

        return back()->with('success', 'Thematic area updated.');
    }

    public function destroyTheme(Request $request, DiscussionTheme $theme): RedirectResponse
    {
        if ($theme->topics()->exists()) {
            return back()->with('error', 'This thematic area contains discussions. Deactivate it instead of deleting it.');
        }

        $snapshot = ['name' => $theme->name, 'slug' => $theme->slug];
        $themeId = $theme->id;
        $theme->delete();

        $this->recordAction($request, 'theme', $themeId, 'deleted', null, $snapshot);

        return back()->with('success', 'Empty thematic area deleted.');
    }

    public function participants(Request $request): View
    {
        $participants = DiscussionParticipant::query()
            ->withCount([
                'posts',
                'posts as published_posts_count' => fn (Builder $query) => $query->where('status', 'published'),
                'tokens as active_tokens_count' => fn (Builder $query) => $query->where('expires_at', '>', now()),
            ])
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('display_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('country', 'like', "%{$term}%")
                        ->orWhere('organization', 'like', "%{$term}%");
                });
            })
            ->when(
                in_array($request->input('status'), ['active', 'blocked'], true),
                fn (Builder $query) => $query->where('status', $request->input('status'))
            )
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        $participantStats = [
            'total' => DiscussionParticipant::query()->count(),
            'active' => DiscussionParticipant::query()->where('status', 'active')->count(),
            'blocked' => DiscussionParticipant::query()->where('status', 'blocked')->count(),
            'seen_recently' => DiscussionParticipant::query()->where('last_seen_at', '>=', now()->subDays(7))->count(),
        ];

        return view('system.discussions.participants', compact('participants', 'participantStats'));
    }

    public function blockParticipant(Request $request, DiscussionParticipant $participant): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);

        $participant->update([
            'status' => 'blocked',
            'blocked_at' => now(),
            'blocked_reason' => $validated['reason'],
        ]);
        $revokedTokens = $participant->tokens()->delete();

        $this->recordAction($request, 'participant', $participant->id, 'blocked', $validated['reason'], [
            'display_name' => $participant->display_name,
            'email' => $participant->email,
            'revoked_tokens' => $revokedTokens,
        ]);

        return back()->with('success', "{$participant->display_name} was blocked and signed out on all devices.");
    }

    public function unblockParticipant(Request $request, DiscussionParticipant $participant): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $previousReason = $participant->blocked_reason;
        $participant->update([
            'status' => 'active',
            'blocked_at' => null,
            'blocked_reason' => null,
        ]);

        $this->recordAction($request, 'participant', $participant->id, 'unblocked', $validated['reason'] ?? null, [
            'display_name' => $participant->display_name,
            'email' => $participant->email,
            'previous_block_reason' => $previousReason,
        ]);

        return back()->with('success', "{$participant->display_name} can participate again.");
    }

    public function revokeParticipantTokens(Request $request, DiscussionParticipant $participant): RedirectResponse
    {
        $revokedTokens = $participant->tokens()->delete();

        $this->recordAction($request, 'participant', $participant->id, 'sessions_revoked', null, [
            'display_name' => $participant->display_name,
            'email' => $participant->email,
            'revoked_tokens' => $revokedTokens,
        ]);

        return back()->with('success', "{$revokedTokens} active session(s) revoked for {$participant->display_name}.");
    }

    public function moderation(Request $request): View
    {
        $requestedStatus = (string) $request->input('status', 'published');
        $status = in_array($requestedStatus, self::POST_STATUSES, true) ? $requestedStatus : 'published';

        $posts = DiscussionPost::query()
            ->with([
                'participant:id,display_name,email,status',
                'topic:id,title,theme_id',
                'topic.theme:id,name,color',
                'moderator:id,name',
            ])
            ->withCount('reactions')
            ->where('status', $status)
            ->when($request->filled('q'), function (Builder $query) use ($request): void {
                $term = trim((string) $request->input('q'));
                $query->where(function (Builder $inner) use ($term): void {
                    $inner->where('body', 'like', "%{$term}%")
                        ->orWhereHas('participant', function (Builder $participantQuery) use ($term): void {
                            $participantQuery->where('display_name', 'like', "%{$term}%")
                                ->orWhere('email', 'like', "%{$term}%");
                        })
                        ->orWhereHas('topic', fn (Builder $topicQuery) => $topicQuery->where('title', 'like', "%{$term}%"));
                });
            })
            ->when(
                $request->filled('topic_id'),
                fn (Builder $query) => $query->where('topic_id', $request->input('topic_id'))
            )
            ->when($status === 'published', fn (Builder $query) => $query->latest('created_at'))
            ->when($status === 'removed', fn (Builder $query) => $query->latest('moderated_at'))
            ->paginate(15)
            ->withQueryString();

        $moderationStats = [
            'published' => DiscussionPost::query()->where('status', 'published')->count(),
            'removed' => DiscussionPost::query()->where('status', 'removed')->count(),
        ];

        $topicOptions = DiscussionTopic::query()
            ->whereHas('posts')
            ->orderBy('title')
            ->get(['id', 'title']);

        return view('system.discussions.moderation', compact(
            'posts',
            'moderationStats',
            'topicOptions',
            'status'
        ));
    }

    public function liveModeration(): View
    {
        $topicOptions = DiscussionTopic::query()
            ->whereHas('posts', fn (Builder $query) => $query->whereIn('status', self::POST_STATUSES))
            ->withMax([
                'posts as latest_live_activity_at' => fn (Builder $query) => $query->whereIn('status', self::POST_STATUSES),
            ], 'created_at')
            ->orderByDesc('latest_live_activity_at')
            ->orderBy('title')
            ->limit(self::LIVE_TOPIC_FILTER_LIMIT)
            ->get(['id', 'title']);

        return view('system.discussions.live-moderation', compact('topicOptions'));
    }

    public function liveModerationFeed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'topic_id' => ['nullable', 'uuid', Rule::exists('discussion_topics', 'id')],
            'cursor' => ['nullable', 'string', 'max:80', 'date'],
            'include_stats' => ['nullable', 'boolean'],
        ]);
        $topicId = $validated['topic_id'] ?? null;
        $snapshotAt = now();
        $cursor = isset($validated['cursor']) ? Carbon::parse($validated['cursor']) : null;

        // A stale or future cursor cannot safely represent the current bounded window.
        // Resetting it produces one complete snapshot and then resumes delta polling.
        if ($cursor && ($cursor->gt($snapshotAt) || $cursor->lt($snapshotAt->copy()->subHours(12)))) {
            $cursor = null;
        }

        $baseQuery = DiscussionPost::query()
            ->whereIn('status', self::POST_STATUSES)
            ->when($topicId, fn (Builder $query) => $query->where('topic_id', $topicId));

        $snapshot = (clone $baseQuery)
            ->select(['id', 'created_at', 'updated_at'])
            ->where('created_at', '<=', $snapshotAt)
            ->latest('created_at')
            ->latest('id')
            ->limit(self::LIVE_FEED_LIMIT)
            ->get();

        $deltaFloor = $cursor?->copy()->subSeconds(self::LIVE_CURSOR_OVERLAP_SECONDS);
        $changedIds = $snapshot
            ->when($deltaFloor, fn ($items) => $items->filter(
                fn (DiscussionPost $post): bool => (bool) $post->updated_at?->gte($deltaFloor)
            ))
            ->pluck('id')
            ->values();

        $visibleIds = $snapshot->pluck('id')->values();
        $storedReactionCounts = $visibleIds->isEmpty()
            ? collect()
            : DiscussionReaction::query()
                ->whereIn('post_id', $visibleIds->all())
                ->select('post_id')
                ->selectRaw('COUNT(*) AS reaction_total')
                ->groupBy('post_id')
                ->pluck('reaction_total', 'post_id')
                ->map(fn ($count): int => (int) $count);
        $reactionCounts = $visibleIds->mapWithKeys(
            fn (string $id): array => [$id => (int) ($storedReactionCounts[$id] ?? 0)]
        );

        $changedPosts = collect();
        if ($changedIds->isNotEmpty()) {
            $postsById = DiscussionPost::query()
                ->whereIn('id', $changedIds->all())
                ->with([
                    'participant:id,display_name,country,status',
                    'topic:id,title,slug,theme_id',
                    'topic.theme:id,name,color',
                    'moderator:id,name',
                ])
                ->get()
                ->keyBy('id');

            $changedPosts = $changedIds
                ->map(fn (string $id) => $postsById->get($id))
                ->filter();

            $changedPosts->each(fn (DiscussionPost $post) => $post->setAttribute(
                'reactions_count',
                (int) ($reactionCounts[$post->id] ?? 0)
            ));
        }

        // Full-history totals are intentionally slower than the contribution
        // stream. A short shared cache also coalesces work across monitor tabs.
        $includeStats = $cursor === null || $request->boolean('include_stats');
        $stats = $includeStats
            ? Cache::remember(
                $this->liveModerationStatsCacheKey($topicId),
                now()->addSeconds(10),
                function () use ($baseQuery, $snapshotAt): array {
                    $aggregate = (clone $baseQuery)
                        ->selectRaw("SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS live_count")
                        ->selectRaw("SUM(CASE WHEN status = 'removed' THEN 1 ELSE 0 END) AS removed_count")
                        ->selectRaw(
                            "SUM(CASE WHEN status = 'published' AND created_at >= ? THEN 1 ELSE 0 END) AS recent_count",
                            [$snapshotAt->copy()->subHour()]
                        )
                        ->first();

                    return [
                        'live' => (int) ($aggregate?->live_count ?? 0),
                        'removed' => (int) ($aggregate?->removed_count ?? 0),
                        'last_hour' => (int) ($aggregate?->recent_count ?? 0),
                    ];
                }
            )
            : null;

        return response()->json([
            'items' => $changedPosts->map(function (DiscussionPost $post): array {
                $liveVersion = $this->liveModerationVersion($post);

                return [
                    'id' => $post->id,
                    'version' => $liveVersion,
                    'html' => view('system.discussions.partials.live-contribution', compact('post', 'liveVersion'))->render(),
                ];
            })->values(),
            'visible_ids' => $visibleIds,
            'reaction_counts' => $reactionCounts,
            'stats' => $stats,
            'sync_cursor' => $snapshotAt->toIso8601String(),
            'synced_at' => $snapshotAt->toIso8601String(),
            'meta' => [
                'is_delta' => $cursor !== null,
                'limit' => self::LIVE_FEED_LIMIT,
                'visible_count' => $snapshot->count(),
                'returned_count' => $changedPosts->count(),
                'cursor_overlap_seconds' => self::LIVE_CURSOR_OVERLAP_SECONDS,
                'stats_included' => $stats !== null,
            ],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    /**
     * Version every value rendered in a live card except reactions, which are
     * synchronized independently. A periodic full snapshot can therefore pick
     * up participant, topic, theme, and moderator edits without replacing all
     * cards or making the frequent delta query join those tables.
     */
    private function liveModerationVersion(DiscussionPost $post): string
    {
        return hash('sha256', json_encode([
            'post' => [
                'updated_at' => $post->updated_at?->format('Y-m-d\TH:i:s.uP'),
                'created_at' => $post->created_at?->format('Y-m-d\TH:i:s.uP'),
                'parent_id' => $post->parent_id,
                'body' => $post->body,
                'status' => $post->status,
                'moderation_reason' => $post->moderation_reason,
                'moderated_at' => $post->moderated_at?->format('Y-m-d\TH:i:s.uP'),
            ],
            'participant' => [
                'display_name' => $post->participant?->display_name,
                'country' => $post->participant?->country,
                'status' => $post->participant?->status,
            ],
            'topic' => [
                'title' => $post->topic?->title,
                'slug' => $post->topic?->slug,
                'theme_name' => $post->topic?->theme?->name,
                'theme_color' => $post->topic?->theme?->color,
            ],
            'moderator_name' => $post->moderator?->name,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function liveModerationStatsCacheKey(?string $topicId): string
    {
        return 'discussion:live-moderation:stats:'.($topicId ?? 'all');
    }

    public function removePost(Request $request, DiscussionPost $post): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $reason = trim($validated['reason']);

        $removedPost = DB::transaction(function () use ($request, $post, $reason): ?DiscussionPost {
            $lockedPost = DiscussionPost::query()
                ->whereKey($post->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPost->status !== 'published') {
                return null;
            }

            $lockedPost->update([
                'status' => 'removed',
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
                'moderation_reason' => $reason,
            ]);

            $this->recordAction($request, 'post', $lockedPost->id, 'removed', $reason, [
                'topic_id' => $lockedPost->topic_id,
                'participant_id' => $lockedPost->participant_id,
                'from' => 'published',
                'to' => 'removed',
            ]);

            return $lockedPost;
        });

        if (! $removedPost) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'This contribution is no longer live and cannot be removed again.',
                ], 409);
            }

            return back()->with('error', 'This contribution is no longer live and cannot be removed again.');
        }

        Cache::forget($this->liveModerationStatsCacheKey(null));
        Cache::forget($this->liveModerationStatsCacheKey($removedPost->topic_id));

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Contribution removed from the public discussion.',
                'data' => [
                    'id' => $removedPost->id,
                    'status' => $removedPost->status,
                ],
            ]);
        }

        return back()->with('success', 'Contribution removed from the public discussion.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTopic(Request $request, ?DiscussionTopic $topic = null): array
    {
        $this->prepareTopicResourceInputs($request);

        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        }

        $closeRules = ['nullable', 'date'];
        if ($request->filled('starts_at')) {
            $closeRules[] = 'after:starts_at';
        }

        $rules = [
            'theme_id' => ['nullable', 'uuid', Rule::exists('discussion_themes', 'id')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('discussion_topics', 'slug')->ignore($topic?->id),
            ],
            'summary' => ['required', 'string', 'max:2000'],
            'body' => ['nullable', 'string', 'max:50000'],
            'status' => ['required', Rule::in(self::TOPIC_STATUSES)],
            'is_featured' => ['sometimes', 'boolean'],
            'allow_replies' => ['sometimes', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'closes_at' => $closeRules,
        ];

        foreach (self::TOPIC_RESOURCE_TYPES as $field => $types) {
            $rules[$field] = ['nullable', 'array', 'max:20'];
            $rules["{$field}.*"] = ['array:title,url,description,type'];
            $rules["{$field}.*.title"] = ['required', 'string', 'max:180'];
            $rules["{$field}.*.url"] = [
                'required',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! DiscussionTopic::isSafeResourceUrl($value)) {
                        $fail('The resource URL must use http://, https://, or a safe same-site path beginning with /.');
                    }
                },
            ];
            $rules["{$field}.*.description"] = ['nullable', 'string', 'max:500'];
            $rules["{$field}.*.type"] = ['nullable', 'string', Rule::in($types)];
        }

        $validated = $request->validate($rules);

        $validated['is_featured'] = $request->boolean('is_featured');
        // Discussion contributions are published immediately. Moderation is
        // retrospective and removes only contributions that violate ATTP rules.
        $validated['requires_moderation'] = false;
        $validated['allow_replies'] = $request->boolean('allow_replies');

        foreach (array_keys(self::TOPIC_RESOURCE_TYPES) as $field) {
            $validated[$field] = DiscussionTopic::normalizeResourceItems($validated[$field] ?? [], $field);
        }

        return $validated;
    }

    private function prepareTopicResourceInputs(Request $request): void
    {
        foreach (array_keys(self::TOPIC_RESOURCE_TYPES) as $field) {
            $items = $request->input($field, []);

            if (! is_array($items)) {
                continue;
            }

            $request->merge([
                $field => array_values(array_filter($items, function (mixed $item): bool {
                    if (! is_array($item)) {
                        return true;
                    }

                    return filled($item['title'] ?? null)
                        || filled($item['url'] ?? null)
                        || filled($item['description'] ?? null);
                })),
            ]);
        }
    }

    /**
     * @return array{document_uploads: array<int, UploadedFile>, uploaded_documents: array<string, array<string, mixed>>}
     */
    private function validateUploadedDocuments(Request $request, ?DiscussionTopic $topic = null): array
    {
        $documentRules = $this->uploadedDocumentFileRules();
        $rules = [
            'document_uploads' => ['nullable', 'array', 'max:10'],
            'document_uploads.*' => ['bail', ...$documentRules],
        ];

        if ($topic) {
            $rules += [
                'uploaded_documents' => ['nullable', 'array', 'max:'.self::MAX_UPLOADED_DOCUMENTS],
                'uploaded_documents.*' => ['array:title,description,remove,replacement'],
                'uploaded_documents.*.title' => ['required', 'string', 'max:180'],
                'uploaded_documents.*.description' => ['nullable', 'string', 'max:500'],
                'uploaded_documents.*.remove' => ['nullable', 'boolean'],
                'uploaded_documents.*.replacement' => ['nullable', 'bail', ...$documentRules],
            ];
        }

        $validated = $request->validate($rules, [
            'document_uploads.max' => 'Upload no more than 10 documents at a time.',
            'document_uploads.*.max' => 'Each document must be no larger than 2 MB.',
            'uploaded_documents.*.replacement.max' => 'Each replacement document must be no larger than 2 MB.',
        ]);

        $newFiles = array_values(array_filter(
            $validated['document_uploads'] ?? [],
            fn (mixed $file): bool => $file instanceof UploadedFile
        ));
        $existingInput = $validated['uploaded_documents'] ?? [];

        if ($topic) {
            $documents = $topic->uploadedDocuments->keyBy('id');

            foreach (array_keys($existingInput) as $documentId) {
                if (! $documents->has($documentId)) {
                    throw ValidationException::withMessages([
                        'uploaded_documents' => ['One of the uploaded documents no longer belongs to this discussion. Refresh the page and try again.'],
                    ]);
                }
            }

            $removedCount = collect($existingInput)
                ->filter(fn (array $item): bool => filter_var($item['remove'] ?? false, FILTER_VALIDATE_BOOL))
                ->count();
            $resultingCount = $documents->count() - $removedCount + count($newFiles);

            if ($resultingCount > self::MAX_UPLOADED_DOCUMENTS) {
                throw ValidationException::withMessages([
                    'document_uploads' => ['A discussion can contain no more than '.self::MAX_UPLOADED_DOCUMENTS.' uploaded documents.'],
                ]);
            }
        } elseif (count($newFiles) > self::MAX_UPLOADED_DOCUMENTS) {
            throw ValidationException::withMessages([
                'document_uploads' => ['A discussion can contain no more than '.self::MAX_UPLOADED_DOCUMENTS.' uploaded documents.'],
            ]);
        }

        return [
            'document_uploads' => $newFiles,
            'uploaded_documents' => $existingInput,
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private function uploadedDocumentFileRules(): array
    {
        return [
            File::types(self::DOCUMENT_EXTENSIONS)->max(2 * 1024),
            function (string $attribute, mixed $value, \Closure $fail): void {
                if (! $value instanceof UploadedFile) {
                    return;
                }

                $extension = Str::lower($value->getClientOriginalExtension());
                if (! in_array($extension, self::DOCUMENT_EXTENSIONS, true)) {
                    $fail('The document extension is not supported.');
                }
            },
        ];
    }

    /**
     * @param  array{document_uploads: array<int, UploadedFile>, uploaded_documents: array<string, array<string, mixed>>}  $validated
     * @param  array<int, string>  $newPaths
     * @param  array<int, string>  $obsoletePaths
     */
    private function synchronizeUploadedDocuments(
        Request $request,
        DiscussionTopic $topic,
        array $validated,
        array &$newPaths,
        array &$obsoletePaths
    ): void {
        $documents = $topic->uploadedDocuments()->get()->keyBy('id');

        foreach ($validated['uploaded_documents'] as $documentId => $input) {
            /** @var DiscussionTopicDocument $document */
            $document = $documents->get($documentId);
            if (! $document) {
                continue;
            }

            if (filter_var($input['remove'] ?? false, FILTER_VALIDATE_BOOL)) {
                $obsoletePaths[] = $document->storage_path;
                $document->delete();
                continue;
            }

            $updates = [
                'title' => $this->cleanDocumentText($input['title'] ?? null, 180) ?: $document->title,
                'description' => $this->cleanDocumentText($input['description'] ?? null, 500),
            ];

            $replacement = $input['replacement'] ?? null;
            if ($replacement instanceof UploadedFile) {
                $fileData = $this->storeUploadedDocumentFile($topic, $replacement);
                $newPaths[] = $fileData['storage_path'];
                $obsoletePaths[] = $document->storage_path;
                $updates += $fileData;
            }

            $document->update($updates);
        }

        $nextOrder = (int) $topic->uploadedDocuments()->max('display_order') + 1;
        foreach ($validated['document_uploads'] as $file) {
            $fileData = $this->storeUploadedDocumentFile($topic, $file);
            $newPaths[] = $fileData['storage_path'];

            $topic->uploadedDocuments()->create([
                ...$fileData,
                'uploaded_by' => $request->user()?->id,
                'title' => $this->documentTitleFromFileName($fileData['file_name']),
                'description' => null,
                'display_order' => $nextOrder++,
            ]);
        }
    }

    /**
     * @return array{file_name: string, storage_path: string, mime_type: string, size_bytes: int, type: string}
     */
    private function storeUploadedDocumentFile(DiscussionTopic $topic, UploadedFile $file): array
    {
        $storagePath = $file->store("discussion-documents/{$topic->id}", 'local');

        if (! is_string($storagePath) || $storagePath === '') {
            throw ValidationException::withMessages([
                'document_uploads' => ['The document could not be stored. Please try again.'],
            ]);
        }

        $fileName = $this->cleanUploadedFileName($file->getClientOriginalName());
        $extension = Str::lower((string) pathinfo($fileName, PATHINFO_EXTENSION));

        return [
            'file_name' => $fileName,
            'storage_path' => $storagePath,
            'mime_type' => Str::lower($file->getMimeType() ?: 'application/octet-stream'),
            'size_bytes' => max(0, (int) $file->getSize()),
            'type' => $this->documentTypeForExtension($extension),
        ];
    }

    private function cleanUploadedFileName(string $fileName): string
    {
        $baseName = basename(str_replace('\\', '/', $fileName));
        $baseName = preg_replace('/[\x00-\x1F\x7F]+/u', '', $baseName) ?: '';
        $baseName = Str::limit(Str::squish(strip_tags($baseName)), 240, '');

        return $baseName !== '' ? $baseName : 'discussion-document';
    }

    private function documentTitleFromFileName(string $fileName): string
    {
        $title = (string) pathinfo($fileName, PATHINFO_FILENAME);
        $title = str_replace(['-', '_'], ' ', $title);

        return $this->cleanDocumentText($title, 180) ?: 'Discussion document';
    }

    private function cleanDocumentText(mixed $value, int $limit): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $decoded = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = Str::squish(strip_tags($decoded));

        return $text === '' ? null : Str::limit($text, $limit, '');
    }

    private function documentTypeForExtension(string $extension): string
    {
        return match ($extension) {
            'pdf' => 'pdf',
            'doc', 'docx' => 'word',
            'xls', 'xlsx', 'csv' => 'spreadsheet',
            'ppt', 'pptx' => 'presentation',
            'txt' => 'text',
            'jpg', 'jpeg', 'png' => 'image',
            'zip' => 'archive',
            default => 'document',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTheme(Request $request, ?DiscussionTheme $theme = null): array
    {
        if ($request->filled('slug')) {
            $request->merge(['slug' => Str::slug((string) $request->input('slug'))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('discussion_themes', 'slug')->ignore($theme?->id),
            ],
            'description' => ['nullable', 'string', 'max:4000'],
            'icon' => ['nullable', 'string', 'max:40', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'display_order' => ['required', 'integer', 'min:0', 'max:9999'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $validated['icon'] = ($validated['icon'] ?? null) ?: 'message-circle';
        $validated['is_active'] = $request->boolean('is_active');

        return $validated;
    }

    private function uniqueSlug(string $modelClass, string $source, ?string $ignoreId = null): string
    {
        $base = Str::slug($source) ?: 'discussion';
        $slug = $base;
        $suffix = 2;

        while ($modelClass::query()
            ->when($ignoreId, fn (Builder $query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function themeOptions()
    {
        return DiscussionTheme::query()
            ->orderByDesc('is_active')
            ->orderBy('display_order')
            ->orderBy('name')
            ->get(['id', 'name', 'is_active']);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function recordAction(
        Request $request,
        string $subjectType,
        string $subjectId,
        string $action,
        ?string $reason = null,
        array $metadata = []
    ): void {
        DiscussionModerationAction::query()->create([
            'moderator_id' => $request->user()?->id,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'action' => $action,
            'reason' => $reason,
            'metadata' => $metadata ?: null,
        ]);
    }
}
