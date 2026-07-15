@extends('layouts.app')

@section('title', 'Discussion Controls')

@push('styles')
    @include('system.discussions.partials.styles')
@endpush

@section('content')
    <div class="discussion-admin nxl-container">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-radio"></i> Community Operations</div>
                    <h1>Discussion Controls</h1>
                    <p>Open purposeful conversations, protect participants, and keep every contribution constructive.</p>
                </div>
                @can('discussions.create')
                    <a href="{{ route('system.discussions.topics.create') }}" class="btn forum-primary-btn text-nowrap">
                        <i class="feather-plus-circle me-1"></i> Create Discussion
                    </a>
                @endcan
            </div>
        </section>

        @include('system.discussions.partials.navigation')
        @include('system.discussions.partials.alerts')

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-message-circle"></i></span>
                    <div class="stat-value">{{ number_format($stats['open_topics']) }}</div>
                    <div class="stat-label">Open discussions</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-eye-off"></i></span>
                    <div class="stat-value">{{ number_format($stats['removed_posts']) }}</div>
                    <div class="stat-label">Removed contributions</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-users"></i></span>
                    <div class="stat-value">{{ number_format($stats['active_participants']) }}</div>
                    <div class="stat-label">Active participants</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-layers"></i></span>
                    <div class="stat-value">{{ number_format($stats['active_themes']) }}</div>
                    <div class="stat-label">Active thematic areas</div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <section class="card forum-panel h-100">
                    <div class="card-header d-flex align-items-center justify-content-between gap-3">
                        <div>
                            <h2><i class="feather-message-square me-2 text-success"></i>Recently Managed Discussions</h2>
                            <small class="forum-muted">Latest discussions by administrative activity</small>
                        </div>
                        @canany(['discussions.view', 'discussions.create', 'discussions.manage'])
                            <a href="{{ route('system.discussions.topics.index') }}" class="btn btn-sm btn-light border">
                                View all
                            </a>
                        @endcanany
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table forum-table mb-0">
                                <thead>
                                    <tr>
                                        <th>Discussion</th>
                                        <th>Status</th>
                                        <th>Contributions</th>
                                        <th class="text-end">Manage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($recentTopics as $topic)
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark">{{ $topic->title }}</div>
                                                <div class="small forum-muted mt-1">
                                                    @if ($topic->theme)
                                                        <span class="theme-swatch me-1"
                                                            style="background: {{ $topic->theme->color }}"></span>
                                                        {{ $topic->theme->name }}
                                                    @else
                                                        General discussion
                                                    @endif
                                                    <span class="mx-1">&middot;</span>
                                                    {{ $topic->updated_at->diffForHumans() }}
                                                </div>
                                            </td>
                                            <td>
                                                <span class="forum-status status-{{ $topic->status }}">{{ $topic->status }}</span>
                                            </td>
                                            <td>
                                                <strong>{{ number_format($topic->published_posts_count) }}</strong>
                                                <span class="small forum-muted">published</span>
                                                @if ($topic->removed_posts_count)
                                                    <div class="small text-danger mt-1">
                                                        {{ $topic->removed_posts_count }} removed
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                @can('discussions.manage')
                                                    <a href="{{ route('system.discussions.topics.edit', $topic) }}"
                                                        class="btn btn-sm btn-light border" aria-label="Edit {{ $topic->title }}">
                                                        <i class="feather-edit-2"></i>
                                                    </a>
                                                @else
                                                    <span class="small forum-muted">Read only</span>
                                                @endcan
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="forum-empty">
                                                <span class="empty-icon"><i class="feather-message-circle"></i></span>
                                                <div class="fw-bold text-dark">No discussions yet</div>
                                                <div class="small forum-muted">Create the first discussion to get started.</div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-xl-4">
                <section class="card forum-panel h-100">
                    <div class="card-header d-flex align-items-center justify-content-between gap-2">
                        <div>
                            <h2><i class="feather-radio me-2 text-success"></i>Live Activity</h2>
                            <small class="forum-muted">Latest contributions</small>
                        </div>
                        <span class="badge rounded-pill bg-success">{{ $stats['published_posts'] }}</span>
                    </div>
                    <div class="card-body">
                        @forelse ($recentPosts as $post)
                            <article class="pb-3 mb-3 border-bottom">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="forum-avatar" style="width: 32px; height: 32px; flex-basis: 32px; border-radius: 9px;">
                                        {{ mb_substr($post->participant?->display_name ?? '?', 0, 1) }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="small fw-bold text-dark text-truncate">
                                            {{ $post->participant?->display_name ?? 'Deleted participant' }}
                                        </div>
                                        <div class="small forum-muted text-truncate">{{ $post->topic?->title }}</div>
                                    </div>
                                </div>
                                <p class="small mb-2 text-dark">{{ \Illuminate\Support\Str::limit($post->body, 120) }}</p>
                                <small class="forum-muted">Published {{ $post->created_at->diffForHumans() }}</small>
                            </article>
                        @empty
                            <div class="forum-empty px-0 py-4">
                                <span class="empty-icon"><i class="feather-check-circle"></i></span>
                                <div class="fw-bold text-dark">No live contributions yet</div>
                                <div class="small forum-muted">Public contributions will appear here immediately.</div>
                            </div>
                        @endforelse

                        @if (auth()->user()?->can('discussions.moderate'))
                            <a href="{{ route('system.discussions.moderation.live') }}" class="btn btn-light border w-100">
                                <i class="feather-radio me-1"></i> Open Live Monitor
                            </a>
                        @endif
                    </div>
                </section>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <section class="card forum-panel h-100">
                    <div class="card-header">
                        <h2><i class="feather-activity me-2 text-success"></i>Forum Snapshot</h2>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="forum-muted">All discussions</span>
                            <strong class="text-dark">{{ number_format($stats['topics']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="forum-muted">Draft discussions</span>
                            <strong class="text-dark">{{ number_format($stats['draft_topics']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="forum-muted">Live contributions</span>
                            <strong class="text-dark">{{ number_format($stats['published_posts']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between py-2 border-bottom">
                            <span class="forum-muted">Registered participants</span>
                            <strong class="text-dark">{{ number_format($stats['participants']) }}</strong>
                        </div>
                        <div class="d-flex justify-content-between pt-2">
                            <span class="forum-muted">Blocked participants</span>
                            <strong class="text-danger">{{ number_format($stats['blocked_participants']) }}</strong>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-lg-7">
                <section class="card forum-panel h-100">
                    <div class="card-header">
                        <h2><i class="feather-list me-2 text-success"></i>Recent Control Activity</h2>
                    </div>
                    <div class="card-body">
                        @forelse ($recentActions as $action)
                            <div class="d-flex align-items-start gap-3 {{ !$loop->last ? 'pb-3 mb-3 border-bottom' : '' }}">
                                <span class="stat-icon flex-shrink-0" style="width: 36px; height: 36px; border-radius: 10px;">
                                    <i class="feather-activity"></i>
                                </span>
                                <div class="flex-grow-1 min-w-0">
                                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-1">
                                        <strong class="text-dark text-capitalize">
                                            {{ str_replace('_', ' ', $action->action) }}
                                        </strong>
                                        <small class="forum-muted">{{ $action->created_at->diffForHumans() }}</small>
                                    </div>
                                    <div class="small forum-muted">
                                        {{ ucfirst($action->subject_type) }} &middot;
                                        {{ $action->moderator?->name ?? 'System administrator' }}
                                    </div>
                                    @if ($action->reason)
                                        <div class="small text-dark mt-1">{{ \Illuminate\Support\Str::limit($action->reason, 120) }}</div>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <div class="forum-empty py-4">
                                <span class="empty-icon"><i class="feather-activity"></i></span>
                                <div class="fw-bold text-dark">No control activity yet</div>
                            </div>
                        @endforelse
                    </div>
                </section>
            </div>
        </div>
    </div>
@endsection
