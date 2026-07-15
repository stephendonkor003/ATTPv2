@extends('layouts.app')

@section('title', 'Live Discussion Monitor')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('system.discussions.partials.styles')
    <style>
        .discussion-admin .live-monitor-indicator {
            display: inline-flex;
            align-items: center;
            gap: .55rem;
            color: #146c43;
            background: #eaf8f0;
            border: 1px solid #b8e0c9;
            border-radius: 999px;
            padding: .55rem .85rem;
            font-weight: 700;
            font-size: .8rem;
        }

        .discussion-admin .live-monitor-indicator::before {
            content: '';
            width: .58rem;
            height: .58rem;
            border-radius: 50%;
            background: #1a9f5a;
            box-shadow: 0 0 0 0 rgba(26, 159, 90, .45);
            animation: live-monitor-pulse 1.8s infinite;
        }

        .discussion-admin .live-monitor-indicator.is-offline {
            color: #9c2f3f;
            background: #fdecef;
            border-color: #f4bcc5;
        }

        .discussion-admin .live-monitor-indicator.is-offline::before {
            background: #c23d52;
            animation: none;
        }

        .discussion-admin .live-topic-strip {
            background: #f7faf8;
            border-left: 3px solid #006b3f;
        }

        .discussion-admin .status-removed {
            color: #b42332;
            background: #fdecef;
            border-color: #f6c6cd;
        }

        .discussion-admin .live-contribution.is-new {
            animation: live-contribution-arrival .7s ease both;
        }

        .discussion-admin .live-contribution-body {
            white-space: normal;
            overflow-wrap: anywhere;
        }

        .discussion-admin .live-remove-panel {
            position: relative;
        }

        .discussion-admin .live-remove-panel > summary {
            list-style: none;
        }

        .discussion-admin .live-remove-panel > summary::-webkit-details-marker {
            display: none;
        }

        .discussion-admin .live-remove-form-wrap {
            position: absolute;
            z-index: 20;
            right: 0;
            width: min(360px, 85vw);
        }

        .discussion-admin .live-feed-message {
            position: sticky;
            top: 1rem;
            z-index: 30;
        }

        @keyframes live-monitor-pulse {
            70% { box-shadow: 0 0 0 8px rgba(26, 159, 90, 0); }
            100% { box-shadow: 0 0 0 0 rgba(26, 159, 90, 0); }
        }

        @keyframes live-contribution-arrival {
            from { opacity: 0; transform: translateY(-8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 767.98px) {
            .discussion-admin .live-remove-form-wrap {
                position: static;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .discussion-admin .live-monitor-indicator::before,
            .discussion-admin .live-contribution.is-new { animation: none; }
        }
    </style>
@endpush

@section('content')
    <div class="discussion-admin nxl-container" id="live-discussion-monitor"
        data-feed-url="{{ route('system.discussions.moderation.live.feed') }}"
        data-poll-interval="4000"
        data-hidden-poll-interval="15000"
        data-request-timeout="8000"
        data-stats-refresh-interval="15000"
        data-full-refresh-interval="60000"
        data-max-cards="40">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-radio"></i> Discussion Controls</div>
                    <h1>Live Discussion Monitor</h1>
                    <p>Watch public contributions as they arrive and remove only content that violates ATTP community rules.</p>
                </div>
                <span class="live-monitor-indicator" id="live-monitor-indicator" role="status" aria-live="polite">
                    Monitoring live
                </span>
            </div>
        </section>

        @include('system.discussions.partials.navigation')
        @include('system.discussions.partials.alerts')

        <div id="live-feed-message" class="live-feed-message" role="status" aria-live="polite"></div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-radio"></i></span>
                    <div class="stat-value" id="live-count">&mdash;</div>
                    <div class="stat-label">Live contributions</div>
                </div>
            </div>
            <div class="col-6 col-lg-4">
                <div class="forum-stat">
                    <span class="stat-icon" style="color: #b42332; background: #fdecef;"><i class="feather-eye-off"></i></span>
                    <div class="stat-value" id="removed-count">&mdash;</div>
                    <div class="stat-label">Removed contributions</div>
                </div>
            </div>
            <div class="col-12 col-lg-4">
                <div class="forum-stat">
                    <span class="stat-icon"><i class="feather-zap"></i></span>
                    <div class="stat-value" id="recent-count">&mdash;</div>
                    <div class="stat-label">Published in the last hour</div>
                </div>
            </div>
        </div>

        <section class="forum-filter mb-4">
            <div class="row g-3 align-items-end">
                <div class="col-lg-7">
                    <label for="live-topic-filter" class="form-label">Monitor a discussion</label>
                    <select id="live-topic-filter" class="form-select">
                        <option value="">All active discussions</option>
                        @foreach ($topicOptions as $topicOption)
                            <option value="{{ $topicOption->id }}">{{ $topicOption->title }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-5 text-lg-end">
                    <div class="small forum-muted">
                        <i class="feather-refresh-cw me-1"></i>
                        Updates automatically every 4 seconds. Last sync: <span id="last-live-sync">starting&hellip;</span>
                    </div>
                </div>
            </div>
        </section>

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <div>
                <h2 class="forum-section-title mb-1">Contribution Stream</h2>
                <div class="small forum-muted">Newest public activity appears first. Removed content remains visible here for audit purposes.</div>
            </div>
            <a href="{{ route('system.discussions.moderation.index') }}" class="btn btn-light border btn-sm">
                <i class="feather-archive me-1"></i> Moderation history
            </a>
        </div>

        <section id="live-contribution-feed" aria-label="Live contribution stream" aria-busy="true">
            <div class="card forum-panel" id="live-feed-empty">
                <div class="forum-empty">
                    <span class="empty-icon"><i class="feather-radio"></i></span>
                    <div class="fw-bold text-dark">Connecting to the live contribution stream&hellip;</div>
                    <div class="small forum-muted">New contributions will appear here automatically.</div>
                </div>
            </div>
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/js/discussion-live-moderation.js') }}?v={{ filemtime(public_path('assets/js/discussion-live-moderation.js')) }}" defer></script>
@endpush
