@extends('layouts.app')

@section('title', 'Manage Discussions')

@push('styles')
    @include('system.discussions.partials.styles')
@endpush

@section('content')
    <div class="discussion-admin nxl-container">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-message-square"></i> Discussion Controls</div>
                    <h1>Manage Discussions</h1>
                    <p>Draft, schedule, open, close, and feature conversations shown in the public forum.</p>
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

        <section class="forum-filter mb-4">
            <form method="GET" action="{{ route('system.discussions.topics.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-4">
                    <label class="form-label" for="discussion-search">Search discussions</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="feather-search"></i></span>
                        <input id="discussion-search" type="search" name="q" value="{{ request('q') }}"
                            class="form-control border-start-0" placeholder="Title, summary, or content">
                    </div>
                </div>
                <div class="col-sm-6 col-lg-2">
                    <label class="form-label" for="discussion-status">Status</label>
                    <select id="discussion-status" name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($topicStatuses as $topicStatus)
                            <option value="{{ $topicStatus }}" @selected(request('status') === $topicStatus)>
                                {{ ucfirst($topicStatus) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label class="form-label" for="discussion-theme">Thematic area</label>
                    <select id="discussion-theme" name="theme_id" class="form-select">
                        <option value="">All thematic areas</option>
                        @foreach ($themes as $theme)
                            <option value="{{ $theme->id }}" @selected(request('theme_id') === $theme->id)>
                                {{ $theme->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-sm-6 col-lg-auto">
                    <div class="form-check mb-2">
                        <input class="form-check-input" id="featured-only" type="checkbox" name="featured" value="1"
                            @checked(request('featured') === '1')>
                        <label class="form-check-label small fw-semibold" for="featured-only">Featured only</label>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-auto ms-lg-auto d-flex gap-2">
                    <button class="btn btn-success"><i class="feather-filter me-1"></i> Apply</button>
                    <a href="{{ route('system.discussions.topics.index') }}" class="btn btn-light border">Clear</a>
                </div>
            </form>
        </section>

        <section class="card forum-panel">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2><i class="feather-list me-2 text-success"></i>Discussion Register</h2>
                    <small class="forum-muted">{{ number_format($topics->total()) }} discussion(s) found</small>
                </div>
                @can('discussions.moderate')
                    <a href="{{ route('system.discussions.moderation.index') }}" class="btn btn-sm btn-light border">
                        <i class="feather-shield me-1"></i> Moderation Center
                    </a>
                @endcan
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover forum-table mb-0">
                        <thead>
                            <tr>
                                <th>Discussion</th>
                                <th>Schedule</th>
                                <th>Contributions</th>
                                <th>Status</th>
                                <th class="text-end">Controls</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($topics as $topic)
                                <tr>
                                    <td style="min-width: 290px;">
                                        <div class="d-flex align-items-start gap-2">
                                            @if ($topic->is_featured)
                                                <i class="feather-star text-warning mt-1" title="Featured"></i>
                                            @else
                                                <i class="feather-message-circle text-success mt-1"></i>
                                            @endif
                                            <div>
                                                @can('discussions.manage')
                                                    <a href="{{ route('system.discussions.topics.edit', $topic) }}"
                                                        class="fw-bold text-dark">{{ $topic->title }}</a>
                                                @else
                                                    <span class="fw-bold text-dark">{{ $topic->title }}</span>
                                                @endcan
                                                <div class="small forum-muted mt-1">
                                                    @if ($topic->theme)
                                                        <span class="theme-swatch me-1"
                                                            style="background: {{ $topic->theme->color }}"></span>
                                                        {{ $topic->theme->name }}
                                                    @else
                                                        General discussion
                                                    @endif
                                                </div>
                                                <div class="small forum-muted mt-1">
                                                    Updated {{ $topic->updated_at->diffForHumans() }}
                                                    @if ($topic->creator)
                                                        by {{ $topic->creator->name }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="small" style="min-width: 155px;">
                                        <div>
                                            <span class="forum-muted">Starts:</span>
                                            <strong>{{ $topic->starts_at?->format('d M Y, H:i') ?? 'Immediately' }}</strong>
                                        </div>
                                        <div class="mt-1">
                                            <span class="forum-muted">Closes:</span>
                                            <strong>{{ $topic->closes_at?->format('d M Y, H:i') ?? 'No deadline' }}</strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ number_format($topic->published_posts_count) }}</div>
                                        <small class="forum-muted">published</small>
                                        @if ($topic->removed_posts_count)
                                            <div class="small text-danger mt-1">{{ $topic->removed_posts_count }} removed</div>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="forum-status status-{{ $topic->status }}">{{ $topic->status }}</span>
                                    </td>
                                    <td class="text-end" style="min-width: 245px;">
                                        @can('discussions.manage')
                                            <div class="d-flex justify-content-end align-items-center gap-2">
                                            <form method="POST"
                                                action="{{ route('system.discussions.topics.status', $topic) }}"
                                                class="d-flex align-items-center gap-1">
                                                @csrf
                                                @method('PATCH')
                                                <label for="status-{{ $topic->id }}" class="visually-hidden">Change status</label>
                                                <select id="status-{{ $topic->id }}" name="status"
                                                    class="form-select form-select-sm" style="width: 105px;">
                                                    @foreach ($topicStatuses as $topicStatus)
                                                        <option value="{{ $topicStatus }}" @selected($topic->status === $topicStatus)>
                                                            {{ ucfirst($topicStatus) }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <button class="btn btn-sm btn-light border" title="Save status"
                                                    aria-label="Save status for {{ $topic->title }}">
                                                    <i class="feather-check"></i>
                                                </button>
                                            </form>
                                            <a href="{{ route('system.discussions.topics.edit', $topic) }}"
                                                class="btn btn-sm btn-success">
                                                <i class="feather-edit-2 me-1"></i> Edit
                                            </a>
                                            </div>
                                        @else
                                            <span class="small forum-muted">Read only</span>
                                        @endcan
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="forum-empty">
                                        <span class="empty-icon"><i class="feather-message-circle"></i></span>
                                        <div class="fw-bold text-dark">No discussions match these filters</div>
                                        <div class="small forum-muted mb-3">Clear the filters or create a new conversation.</div>
                                        @can('discussions.create')
                                            <a href="{{ route('system.discussions.topics.create') }}" class="btn btn-success btn-sm">
                                                Create Discussion
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($topics->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $topics->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
