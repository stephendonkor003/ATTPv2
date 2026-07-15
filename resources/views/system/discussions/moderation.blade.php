@extends('layouts.app')

@section('title', 'Discussion Moderation History')

@push('styles')
    @include('system.discussions.partials.styles')
@endpush

@section('content')
    <div class="discussion-admin nxl-container">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-shield"></i> Discussion Controls</div>
                    <h1>Moderation History</h1>
                    <p>Contributions publish immediately. Moderators may remove rule violations while preserving the decision and reason here.</p>
                </div>
                <a href="{{ route('system.discussions.moderation.live') }}" class="btn forum-primary-btn text-nowrap">
                    <i class="feather-radio me-1"></i> Open Live Monitor
                </a>
            </div>
        </section>

        @include('system.discussions.partials.navigation')
        @include('system.discussions.partials.alerts')

        <div class="row g-3 mb-4">
            <div class="col-6">
                <a href="{{ route('system.discussions.moderation.index', ['status' => 'published']) }}"
                    class="forum-stat d-block text-decoration-none {{ $status === 'published' ? 'border-success' : '' }}">
                    <span class="stat-icon"><i class="feather-radio"></i></span>
                    <div class="stat-value">{{ number_format($moderationStats['published']) }}</div>
                    <div class="stat-label">Live contributions</div>
                </a>
            </div>
            <div class="col-6">
                <a href="{{ route('system.discussions.moderation.index', ['status' => 'removed']) }}"
                    class="forum-stat d-block text-decoration-none {{ $status === 'removed' ? 'border-danger' : '' }}">
                    <span class="stat-icon" style="color: #b42332; background: #fdecef;"><i class="feather-eye-off"></i></span>
                    <div class="stat-value">{{ number_format($moderationStats['removed']) }}</div>
                    <div class="stat-label">Removed for rule violations</div>
                </a>
            </div>
        </div>

        <section class="forum-filter mb-4">
            <form method="GET" action="{{ route('system.discussions.moderation.index') }}" class="row g-3 align-items-end">
                <input type="hidden" name="status" value="{{ $status }}">
                <div class="col-lg-5">
                    <label for="moderation-search" class="form-label">Search contributions</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="feather-search"></i></span>
                        <input id="moderation-search" type="search" name="q" value="{{ request('q') }}"
                            class="form-control border-start-0" placeholder="Post content, participant, or discussion">
                    </div>
                </div>
                <div class="col-lg-4">
                    <label for="moderation-topic" class="form-label">Discussion</label>
                    <select id="moderation-topic" name="topic_id" class="form-select">
                        <option value="">All discussions</option>
                        @foreach ($topicOptions as $topicOption)
                            <option value="{{ $topicOption->id }}" @selected(request('topic_id') === $topicOption->id)>
                                {{ $topicOption->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3 d-flex gap-2">
                    <button class="btn btn-success flex-grow-1"><i class="feather-filter me-1"></i> Apply</button>
                    <a href="{{ route('system.discussions.moderation.index', ['status' => $status]) }}"
                        class="btn btn-light border">Clear</a>
                </div>
            </form>
        </section>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="forum-section-title mb-1">{{ $status === 'published' ? 'Live' : 'Removed' }} Contributions</h2>
                <div class="small forum-muted">{{ number_format($posts->total()) }} contribution(s) found</div>
            </div>
        </div>

        @forelse ($posts as $post)
            <article class="forum-contribution mb-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-start justify-content-between gap-3">
                    <div class="d-flex align-items-center gap-3 min-w-0">
                        <span class="forum-avatar">{{ mb_substr($post->participant?->display_name ?? '?', 0, 1) }}</span>
                        <div class="min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <strong class="text-dark">{{ $post->participant?->display_name ?? 'Deleted participant' }}</strong>
                                @if ($post->participant?->status === 'blocked')
                                    <span class="forum-status status-blocked">blocked</span>
                                @endif
                                @if ($post->parent_id)
                                    <span class="badge bg-light text-dark border">Reply</span>
                                @endif
                            </div>
                            <div class="small forum-muted mt-1">
                                {{ $post->participant?->email }}
                                <span class="mx-1">&middot;</span>
                                Published {{ $post->created_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                    </div>
                    <span class="forum-status status-{{ $post->status }}">
                        {{ $post->status === 'published' ? 'Live' : 'Removed' }}
                    </span>
                </div>

                <div class="rounded p-3 my-3" style="background: #f7faf8; border-left: 3px solid {{ $post->topic?->theme?->color ?? '#006B3F' }};">
                    <div class="small forum-muted mb-1">{{ $post->topic?->theme?->name ?? 'General discussion' }}</div>
                    <span class="fw-bold text-dark">{{ $post->topic?->title ?? 'Deleted discussion' }}</span>
                </div>

                <div class="forum-body-preview">{!! nl2br(e($post->body)) !!}</div>

                @if ($post->status === 'removed')
                    <div class="alert alert-danger mt-3 mb-0 py-2">
                        <div class="small fw-bold"><i class="feather-eye-off me-1"></i> Removal reason</div>
                        <div class="small">{{ $post->moderation_reason }}</div>
                        <div class="small opacity-75 mt-1">
                            {{ $post->moderator?->name ?? 'Authorized moderator' }}
                            &middot; {{ $post->moderated_at?->format('d M Y, H:i') }}
                        </div>
                    </div>
                @endif

                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mt-3 pt-3 border-top">
                    <div class="small forum-muted">
                        <i class="feather-heart me-1"></i> {{ number_format($post->reactions_count) }} reaction(s)
                        <span class="mx-2">&middot;</span> ID {{ $post->id }}
                    </div>
                    @if ($post->status === 'published')
                        <details class="d-inline-block">
                            <summary class="btn btn-outline-danger btn-sm" style="list-style: none;">
                                <i class="feather-eye-off me-1"></i> Remove rule violation
                            </summary>
                            <div class="border rounded bg-white shadow-sm p-3 mt-2" style="width: min(340px, 80vw);">
                                <form method="POST" action="{{ route('system.discussions.moderation.remove', $post) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label for="remove-reason-{{ $post->id }}" class="form-label">ATTP rule violated</label>
                                    <textarea id="remove-reason-{{ $post->id }}" name="reason" rows="3" minlength="5"
                                        maxlength="2000" required class="form-control form-control-sm mb-2"
                                        placeholder="State the specific rule violation"></textarea>
                                    <button class="btn btn-danger btn-sm w-100">Confirm Removal</button>
                                </form>
                            </div>
                        </details>
                    @endif
                </div>
            </article>
        @empty
            <section class="card forum-panel">
                <div class="forum-empty">
                    <span class="empty-icon"><i class="feather-inbox"></i></span>
                    <div class="fw-bold text-dark">No contributions found</div>
                    <div class="small forum-muted">Try another status or clear the search filters.</div>
                </div>
            </section>
        @endforelse

        @if ($posts->hasPages())
            <div class="card forum-panel mt-3"><div class="card-body py-3">{{ $posts->links() }}</div></div>
        @endif
    </div>
@endsection
