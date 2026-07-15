@extends('layouts.app')

@section('title', 'Discussion Participants')

@push('styles')
    @include('system.discussions.partials.styles')
@endpush

@section('content')
    <div class="discussion-admin nxl-container">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-users"></i> Discussion Controls</div>
                    <h1>Participants &amp; Access</h1>
                    <p>Review registered forum participants, revoke active sessions, and restrict accounts when necessary.</p>
                </div>
                <span class="btn btn-light border text-nowrap disabled">
                    <i class="feather-user-check me-1"></i> {{ number_format($participantStats['total']) }} Registered
                </span>
            </div>
        </section>

        @include('system.discussions.partials.navigation')
        @include('system.discussions.partials.alerts')

        <div class="row g-3 mb-4">
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-users"></i></span>
                    <div class="stat-value">{{ number_format($participantStats['total']) }}</div>
                    <div class="stat-label">Registered participants</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-user-check"></i></span>
                    <div class="stat-value">{{ number_format($participantStats['active']) }}</div>
                    <div class="stat-label">Active accounts</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-activity"></i></span>
                    <div class="stat-value">{{ number_format($participantStats['seen_recently']) }}</div>
                    <div class="stat-label">Seen in the last 7 days</div>
                </div>
            </div>
            <div class="col-6 col-xl-3">
                <div class="forum-stat">
                    <span class="stat-icon" style="color: #b42332; background: #fdecef;"><i class="feather-user-x"></i></span>
                    <div class="stat-value">{{ number_format($participantStats['blocked']) }}</div>
                    <div class="stat-label">Blocked accounts</div>
                </div>
            </div>
        </div>

        <section class="forum-filter mb-4">
            <form method="GET" action="{{ route('system.discussions.participants.index') }}" class="row g-3 align-items-end">
                <div class="col-lg-6">
                    <label for="participant-search" class="form-label">Search participants</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="feather-search"></i></span>
                        <input id="participant-search" type="search" name="q" value="{{ request('q') }}"
                            class="form-control border-start-0" placeholder="Name, email, country, or organisation">
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <label for="participant-status" class="form-label">Account status</label>
                    <select id="participant-status" name="status" class="form-select">
                        <option value="">All participants</option>
                        <option value="active" @selected(request('status') === 'active')>Active</option>
                        <option value="blocked" @selected(request('status') === 'blocked')>Blocked</option>
                    </select>
                </div>
                <div class="col-sm-6 col-lg-3 d-flex gap-2">
                    <button class="btn btn-success flex-grow-1"><i class="feather-filter me-1"></i> Apply</button>
                    <a href="{{ route('system.discussions.participants.index') }}" class="btn btn-light border">Clear</a>
                </div>
            </form>
        </section>

        <section class="card forum-panel">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div>
                    <h2><i class="feather-user-check me-2 text-success"></i>Participant Register</h2>
                    <small class="forum-muted">{{ number_format($participants->total()) }} result(s)</small>
                </div>
                <small class="forum-muted"><i class="feather-info me-1"></i> Blocking signs a participant out immediately.</small>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover forum-table mb-0">
                        <thead>
                            <tr>
                                <th>Participant</th>
                                <th>Activity</th>
                                <th>Access</th>
                                <th>Status</th>
                                <th class="text-end">Controls</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($participants as $participant)
                                <tr>
                                    <td style="min-width: 255px;">
                                        <div class="d-flex align-items-center gap-3">
                                            <span class="forum-avatar">
                                                {{ mb_substr($participant->display_name, 0, 1) }}
                                            </span>
                                            <div class="min-w-0">
                                                <div class="fw-bold text-dark">{{ $participant->display_name }}</div>
                                                <div class="small forum-muted">{{ $participant->email }}</div>
                                                @if ($participant->organization || $participant->country)
                                                    <div class="small forum-muted mt-1">
                                                        {{ collect([$participant->organization, $participant->country])->filter()->join(' · ') }}
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td style="min-width: 150px;">
                                        <div><strong>{{ number_format($participant->published_posts_count) }}</strong>
                                            <span class="small forum-muted">published posts</span>
                                        </div>
                                        <div class="small forum-muted mt-1">
                                            Last seen {{ $participant->last_seen_at?->diffForHumans() ?? 'never' }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">{{ number_format($participant->active_tokens_count) }}</div>
                                        <small class="forum-muted">active session(s)</small>
                                    </td>
                                    <td style="min-width: 160px;">
                                        <span class="forum-status status-{{ $participant->status }}">{{ $participant->status }}</span>
                                        @if ($participant->isBlocked())
                                            <div class="small text-danger mt-2" title="{{ $participant->blocked_reason }}">
                                                {{ \Illuminate\Support\Str::limit($participant->blocked_reason, 55) }}
                                            </div>
                                            <div class="small forum-muted mt-1">
                                                {{ $participant->blocked_at?->format('d M Y, H:i') }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end" style="min-width: 300px;">
                                        @if ($participant->isBlocked())
                                            <form method="POST"
                                                action="{{ route('system.discussions.participants.unblock', $participant) }}"
                                                class="d-inline-flex align-items-center gap-1">
                                                @csrf
                                                @method('PATCH')
                                                <input type="text" name="reason" maxlength="2000" class="form-control form-control-sm"
                                                    style="width: 150px;" placeholder="Unblock note (optional)"
                                                    aria-label="Unblock note for {{ $participant->display_name }}">
                                                <button class="btn btn-sm btn-success text-nowrap">
                                                    <i class="feather-user-check me-1"></i> Unblock
                                                </button>
                                            </form>
                                        @else
                                            <details class="d-inline-block text-start">
                                                <summary class="btn btn-sm btn-outline-danger" style="list-style: none;">
                                                    <i class="feather-user-x me-1"></i> Block User
                                                </summary>
                                                <div class="border rounded bg-white shadow-sm p-3 mt-2" style="width: 270px;">
                                                    <form method="POST"
                                                        action="{{ route('system.discussions.participants.block', $participant) }}">
                                                        @csrf
                                                        @method('PATCH')
                                                        <label for="block-reason-{{ $participant->id }}" class="form-label">Reason for blocking</label>
                                                        <textarea id="block-reason-{{ $participant->id }}" name="reason" rows="3" required minlength="5"
                                                            maxlength="2000" class="form-control form-control-sm mb-2"
                                                            placeholder="State the moderation reason"></textarea>
                                                        <button class="btn btn-sm btn-danger w-100"
                                                            onclick="return confirm('Block this participant and revoke all active sessions?');">
                                                            Confirm Block
                                                        </button>
                                                    </form>
                                                </div>
                                            </details>
                                        @endif

                                        @if ($participant->active_tokens_count > 0 && !$participant->isBlocked())
                                            <form method="POST"
                                                action="{{ route('system.discussions.participants.revoke', $participant) }}"
                                                class="d-inline-block ms-1"
                                                onsubmit="return confirm('Sign this participant out on every device?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-light border" title="Revoke active sessions"
                                                    aria-label="Revoke active sessions for {{ $participant->display_name }}">
                                                    <i class="feather-log-out"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="forum-empty">
                                        <span class="empty-icon"><i class="feather-users"></i></span>
                                        <div class="fw-bold text-dark">No participants found</div>
                                        <div class="small forum-muted">Try a different search or clear the status filter.</div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if ($participants->hasPages())
                <div class="card-footer bg-white border-top py-3">
                    {{ $participants->links() }}
                </div>
            @endif
        </section>
    </div>
@endsection
