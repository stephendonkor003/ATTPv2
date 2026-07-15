@php
    // Keep each card payload bounded even when a participant submits a very
    // large contribution. Moderators can open the linked public discussion to
    // review the full text when the 1,600-byte preview is not sufficient.
    $liveBody = (string) $post->body;
    $liveBodyPreviewBytes = 1600;
    $liveBodyIsTruncated = strlen($liveBody) > $liveBodyPreviewBytes;
    $liveBodyPreview = mb_strcut($liveBody, 0, $liveBodyPreviewBytes, 'UTF-8');
@endphp

<article id="live-post-{{ $post->id }}" class="forum-contribution live-contribution mb-3"
    data-live-post-id="{{ $post->id }}"
    data-live-version="{{ $liveVersion }}">
    <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <span class="forum-avatar">{{ mb_substr($post->participant?->display_name ?? '?', 0, 1) }}</span>
            <div class="min-w-0">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <strong class="text-dark">{{ $post->participant?->display_name ?? 'Deleted participant' }}</strong>
                    @if ($post->participant?->status === 'blocked')
                        <span class="forum-status status-blocked">blocked participant</span>
                    @endif
                    @if ($post->parent_id)
                        <span class="badge bg-light text-dark border">Reply</span>
                    @endif
                </div>
                <div class="small forum-muted mt-1">
                    {{ $post->participant?->country ?? 'Country unavailable' }}
                    <span class="mx-1">&middot;</span>
                    {{ $post->created_at->format('d M Y, H:i:s') }}
                    <span class="mx-1">&middot;</span>
                    {{ $post->created_at->diffForHumans() }}
                </div>
            </div>
        </div>
        <span class="forum-status status-{{ $post->status }}">
            {{ $post->status === 'published' ? 'Live' : 'Removed' }}
        </span>
    </div>

    <div class="rounded p-3 my-3 live-topic-strip"
        style="border-left-color: {{ $post->topic?->theme?->color ?? '#006B3F' }};">
        <div class="small forum-muted mb-1">{{ $post->topic?->theme?->name ?? 'General discussion' }}</div>
        @if ($post->topic)
            <a href="{{ route('discussion.current', ['topic' => $post->topic->slug]) }}" target="_blank" rel="noopener"
                class="fw-bold text-dark">
                {{ $post->topic->title }} <i class="feather-external-link ms-1"></i>
            </a>
        @else
            <strong class="text-dark">Deleted discussion</strong>
        @endif
    </div>

    <div class="forum-body-preview live-contribution-body">{!! nl2br(e($liveBodyPreview)) !!}</div>
    @if ($liveBodyIsTruncated)
        <div class="small forum-muted mt-2">
            <i class="feather-info me-1"></i>
            Preview limited for live-monitor performance. Open the discussion above to review the full contribution.
        </div>
    @endif

    @if ($post->status === 'removed')
        <div class="alert alert-danger mt-3 mb-0 py-2" role="status">
            <div class="d-flex align-items-center gap-2 fw-bold">
                <i class="feather-eye-off"></i> Removed from the public discussion
            </div>
            <div class="small mt-1">{{ $post->moderation_reason }}</div>
            <div class="small opacity-75 mt-1">
                {{ $post->moderator?->name ?? 'Authorized moderator' }}
                @if ($post->moderated_at)
                    &middot; {{ $post->moderated_at->format('d M Y, H:i:s') }}
                @endif
            </div>
        </div>
    @endif

    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-3 pt-3 border-top">
        <div class="small forum-muted">
            <i class="feather-heart me-1"></i>
            <span data-live-reactions-count="{{ $post->id }}">{{ number_format($post->reactions_count) }}</span> reaction(s)
            <span class="mx-2">&middot;</span>
            ID {{ $post->id }}
        </div>

        @if ($post->status === 'published')
            <details class="live-remove-panel">
                <summary class="btn btn-outline-danger btn-sm">
                    <i class="feather-eye-off me-1"></i> Remove rule violation
                </summary>
                <div class="border rounded bg-white shadow-sm p-3 mt-2 live-remove-form-wrap">
                    <form method="POST" action="{{ route('system.discussions.moderation.remove', $post) }}"
                        class="js-live-remove-form">
                        @csrf
                        @method('PATCH')
                        <label for="live-remove-reason-{{ $post->id }}" class="form-label">
                            ATTP rule violated <span class="text-danger">*</span>
                        </label>
                        <textarea id="live-remove-reason-{{ $post->id }}" name="reason" rows="3" minlength="5"
                            maxlength="2000" required class="form-control form-control-sm js-remove-reason"
                            placeholder="State the specific rule violation (at least 5 characters)"></textarea>
                        <div class="small forum-muted my-2">The reason is retained in the moderation audit record.</div>
                        <button class="btn btn-danger btn-sm w-100" type="submit">
                            <i class="feather-eye-off me-1"></i> Confirm removal
                        </button>
                    </form>
                </div>
            </details>
        @endif
    </div>
</article>
