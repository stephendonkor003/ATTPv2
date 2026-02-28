@extends('layouts.app')

@section('title', 'Member State Questions')

@push('styles')
    <style>
        .qa-hero {
            border-radius: 18px;
            padding: 1.2rem 1.2rem;
            border: 1px solid rgba(255, 255, 255, .22);
            background: linear-gradient(130deg, #0f172a 0%, #0f766e 50%, #0ea5e9 100%);
            color: #f8fafc;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .2);
            position: relative;
            overflow: hidden
        }

        .qa-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 12% 12%, rgba(255, 255, 255, .24), transparent 38%), radial-gradient(circle at 88% 8%, rgba(255, 255, 255, .15), transparent 36%);
            pointer-events: none
        }

        .qa-hero>.row {
            position: relative;
            z-index: 1
        }

        .qa-hero .kicker {
            font-size: .72rem;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: rgba(248, 250, 252, .85)
        }

        .qa-hero h4 {
            color: #fff !important;
            font-weight: 800;
            letter-spacing: .01em;
            text-shadow: 0 1px 2px rgba(2, 6, 23, .45)
        }

        .qa-hero p {
            color: rgba(248, 250, 252, .92)
        }

        .qa-glass-tag {
            display: inline-flex;
            align-items: center;
            gap: .42rem;
            padding: .35rem .65rem;
            border: 1px solid rgba(255, 255, 255, .35);
            border-radius: 999px;
            background: rgba(255, 255, 255, .14);
            font-size: .76rem;
            font-weight: 700;
            color: #ecfeff
        }

        .qa-hero-art {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: .7rem
        }

        .qa-art-card {
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 12px;
            background: rgba(15, 23, 42, .16);
            backdrop-filter: blur(3px);
            padding: .45rem;
            min-height: 118px;
            display: flex;
            align-items: center;
            justify-content: center
        }

        .qa-art-card svg {
            width: 100%;
            max-width: 170px;
            height: auto
        }

        .qa-stat {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            padding: .82rem .9rem;
            box-shadow: 0 4px 10px rgba(15, 23, 42, .05)
        }

        .qa-stat .label {
            font-size: .72rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: .05em
        }

        .qa-stat .value {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a
        }

        .qa-panel {
            border: 1px solid #dbeafe;
            border-radius: 14px;
            background: #fff;
            box-shadow: 0 6px 14px rgba(15, 23, 42, .06)
        }

        .qa-panel .card-header {
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(120deg, #f8fafc 0%, #eef2ff 100%)
        }

        .qa-panel-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            color: #0f172a;
            font-weight: 800
        }

        .qa-help-card {
            height: 100%;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            background: linear-gradient(145deg, #f0fdf4 0%, #ecfeff 100%);
            padding: .85rem
        }

        .qa-help-card h6 {
            margin: 0 0 .45rem 0;
            color: #0f172a
        }

        .qa-help-card ul {
            margin: 0;
            padding-left: 1rem
        }

        .qa-help-card li {
            font-size: .82rem;
            color: #334155;
            margin-bottom: .35rem
        }

        .qa-routing-pill {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            border-radius: 999px;
            padding: .26rem .58rem;
            font-size: .72rem;
            font-weight: 700;
            background: #dcfce7;
            color: #166534
        }

        .qa-routing-list {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .35rem
        }

        .qa-routing-user {
            font-size: .72rem;
            color: #065f46;
            border: 1px solid #86efac;
            border-radius: 999px;
            padding: .18rem .52rem;
            background: #f0fdf4
        }

        .qa-filter {
            border: 1px solid #dbeafe;
            border-radius: 12px;
            background: linear-gradient(120deg, #f8fbff 0%, #eff6ff 100%);
            padding: .75rem
        }

        .qa-item {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #fff;
            padding: .85rem .9rem;
            box-shadow: 0 4px 10px rgba(15, 23, 42, .05)
        }

        .qa-item+.qa-item {
            margin-top: .7rem
        }

        .qa-order {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            background: linear-gradient(140deg, #0f766e 0%, #16a34a 100%);
            color: #fff;
            font-weight: 800;
            font-size: .82rem;
            box-shadow: 0 8px 12px rgba(15, 23, 42, .16)
        }

        .qa-meta {
            font-size: .78rem;
            color: #64748b
        }

        .qa-response {
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            background: #f0fdf4;
            padding: .6rem .7rem
        }

        .qa-empty {
            border: 1px dashed #cbd5e1;
            border-radius: 12px;
            background: #f8fafc;
            padding: 1rem;
            text-align: center;
            color: #64748b
        }

        .qa-badge {
            border-radius: 999px;
            padding: .28rem .58rem;
            font-size: .72rem;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: .3rem
        }

        .status-open {
            background: #dbeafe;
            color: #1d4ed8
        }

        .status-in_review {
            background: #fef3c7;
            color: #92400e
        }

        .status-answered {
            background: #dcfce7;
            color: #166534
        }

        .status-closed {
            background: #e2e8f0;
            color: #334155
        }

        .priority-low {
            background: #f1f5f9;
            color: #334155
        }

        .priority-normal {
            background: #dbeafe;
            color: #1d4ed8
        }

        .priority-high {
            background: #fef3c7;
            color: #92400e
        }

        .priority-urgent {
            background: #fee2e2;
            color: #b91c1c
        }

        @media (max-width: 991px) {
            .qa-hero-art {
                margin-top: .7rem
            }
        }

        @media (max-width: 767px) {
            .qa-item .d-flex {
                flex-direction: column;
                align-items: flex-start !important
            }
        }
    </style>
@endpush

@section('content')
    @php
        $hasQuestionResponders = (int) ($questionResponderCount ?? 0) > 0;
    @endphp
    <main class="nxl-container">
        <div class="qa-hero mb-4">
            <div class="row g-3 align-items-center">
                <div class="col-lg-8">
                    <div class="kicker">Official Q&A Desk</div>
                    <h4 class="mb-1">Ask Questions to the African Union</h4>
                    <p class="mb-2">{{ $memberState?->name }} can submit policy and implementation questions. The system
                        auto-routes each submission to authorized AU responders defined by the superadmin.</p>
                    <span class="qa-glass-tag">
                        <i class="feather-send"></i>
                        Auto-routing active
                    </span>
                </div>
                <div class="col-lg-4">
                    <div class="qa-hero-art">
                        <div class="qa-art-card">
                            <svg viewBox="0 0 220 140" xmlns="http://www.w3.org/2000/svg" role="img"
                                aria-label="Question confusion illustration">
                                <defs>
                                    <linearGradient id="qaQGradient" x1="0%" y1="0%" x2="100%"
                                        y2="100%">
                                        <stop offset="0%" stop-color="#22d3ee" />
                                        <stop offset="100%" stop-color="#34d399" />
                                    </linearGradient>
                                </defs>
                                <rect x="8" y="8" width="204" height="124" rx="18" fill="rgba(15,23,42,0.28)"
                                    stroke="rgba(255,255,255,0.24)" />
                                <circle cx="72" cy="70" r="36" fill="url(#qaQGradient)" opacity=".95" />
                                <path
                                    d="M70 52c8 0 14 5 14 12 0 8-8 10-11 16h-8c1-9 9-10 9-15 0-3-2-5-5-5-4 0-6 2-7 6l-10-3c2-9 9-11 18-11zm-3 34h10v10H67z"
                                    fill="#0f172a" />
                                <text x="138" y="64" font-size="34" fill="#f8fafc" font-weight="800">?</text>
                                <text x="160" y="90" font-size="28" fill="#cffafe" font-weight="700">?</text>
                            </svg>
                        </div>
                        <div class="qa-art-card">
                            <svg viewBox="0 0 220 140" xmlns="http://www.w3.org/2000/svg" role="img"
                                aria-label="Routing illustration">
                                <defs>
                                    <linearGradient id="qaRouteGradient" x1="0%" y1="0%" x2="100%"
                                        y2="0%">
                                        <stop offset="0%" stop-color="#0ea5e9" />
                                        <stop offset="100%" stop-color="#16a34a" />
                                    </linearGradient>
                                </defs>
                                <rect x="8" y="8" width="204" height="124" rx="18" fill="rgba(15,23,42,0.28)"
                                    stroke="rgba(255,255,255,0.24)" />
                                <rect x="22" y="48" width="58" height="44" rx="10" fill="#e0f2fe" />
                                <rect x="140" y="48" width="58" height="44" rx="10" fill="#dcfce7" />
                                <path d="M83 70h50" stroke="url(#qaRouteGradient)" stroke-width="8"
                                    stroke-linecap="round" />
                                <path d="M123 58l16 12-16 12" fill="none" stroke="#16a34a" stroke-width="6"
                                    stroke-linecap="round" stroke-linejoin="round" />
                                <circle cx="51" cy="70" r="9" fill="#0284c7" />
                                <circle cx="168" cy="70" r="9" fill="#15803d" />
                                <text x="24" y="34" font-size="14" fill="#bae6fd" font-weight="700">Member State</text>
                                <text x="138" y="34" font-size="14" fill="#bbf7d0" font-weight="700">AU Desk</text>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm"><strong>Validation issue:</strong> please check your question
                details.</div>
        @endif
        @if (!$hasQuestionResponders)
            <div class="alert alert-warning border-0 shadow-sm">
                No AU responder is currently configured with question response permission. Please contact the superadmin.
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="qa-stat">
                    <div class="label">Total</div>
                    <div class="value">{{ $stats['total'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="qa-stat">
                    <div class="label">Open</div>
                    <div class="value text-primary">{{ $stats['open'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="qa-stat">
                    <div class="label">In Review</div>
                    <div class="value text-warning">{{ $stats['in_review'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="qa-stat">
                    <div class="label">Answered</div>
                    <div class="value text-success">{{ $stats['answered'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="qa-stat">
                    <div class="label">Closed</div>
                    <div class="value text-secondary">{{ $stats['closed'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="qa-stat">
                    <div class="label">Responders</div>
                    <div class="value text-info">{{ (int) ($questionResponderCount ?? 0) }}</div>
                </div>
            </div>
        </div>

        <div class="card qa-panel mb-4">
            <div class="card-header">
                <div class="qa-panel-title"><i class="feather-help-circle"></i> Submit a Question</div>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-lg-8">
                        <form action="{{ route('member-state.questions.store') }}" method="POST" class="row g-3">
                            @csrf
                            <div class="col-md-3">
                                <label class="form-label">Date</label>
                                <input type="date" name="asked_on"
                                    class="form-control @error('asked_on') is-invalid @enderror"
                                    value="{{ old('asked_on', now()->toDateString()) }}" required>
                                @error('asked_on')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Priority</label>
                                <select name="priority" class="form-select @error('priority') is-invalid @enderror"
                                    required>
                                    <option value="low" @selected(old('priority') === 'low')>Low</option>
                                    <option value="normal" @selected(old('priority', 'normal') === 'normal')>Normal</option>
                                    <option value="high" @selected(old('priority') === 'high')>High</option>
                                    <option value="urgent" @selected(old('priority') === 'urgent')>Urgent</option>
                                </select>
                                @error('priority')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Subject</label>
                                <input type="text" name="subject" value="{{ old('subject') }}"
                                    class="form-control @error('subject') is-invalid @enderror"
                                    placeholder="Question title" required>
                                @error('subject')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label">Question</label>
                                <textarea name="question_text" rows="5" class="form-control @error('question_text') is-invalid @enderror"
                                    placeholder="Write your detailed question for the AU..." required>{{ old('question_text') }}</textarea>
                                @error('question_text')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary" @disabled(!$hasQuestionResponders)><i
                                        class="feather-send me-1"></i>Submit Question</button>
                                <button type="reset" class="btn btn-light border">Reset</button>
                            </div>
                        </form>
                    </div>
                    <div class="col-lg-4">
                        <div class="qa-help-card">
                            <h6><i class="feather-navigation me-1"></i>Routing & Quality Guide</h6>
                            <span class="qa-routing-pill"><i class="feather-zap"></i>System auto-routes to AU
                                responders</span>
                            {{-- @if (($questionResponderNames ?? collect())->isNotEmpty())
                            <div class="qa-routing-list">
                                @foreach (($questionResponderNames ?? collect())->take(6) as $responderName)
                                    <span class="qa-routing-user">{{ $responderName }}</span>
                                @endforeach
                            </div>
                        @endif --}}
                            <ul class="mt-2">
                                <li>Use a precise subject with treaty, policy, or program reference when possible.</li>
                                <li>Set the right priority based on urgency and impact on implementation.</li>
                                <li>Add clear context, expected clarification, and the decision needed from AU.</li>
                                <li>You do not select officers manually; superadmin-defined responders receive the alert
                                    email automatically.</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card qa-panel">
            <div class="card-header">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="qa-panel-title"><i class="feather-inbox"></i> Question Register</div>
                    <form method="GET" action="{{ route('member-state.questions.index') }}"
                        class="qa-filter d-flex flex-wrap gap-2">
                        <select name="status" class="form-select form-select-sm" style="min-width:180px;">
                            <option value="">All statuses</option>
                            <option value="open" @selected(($filters['status'] ?? '') === 'open')>Open</option>
                            <option value="in_review" @selected(($filters['status'] ?? '') === 'in_review')>In Review</option>
                            <option value="answered" @selected(($filters['status'] ?? '') === 'answered')>Answered</option>
                            <option value="closed" @selected(($filters['status'] ?? '') === 'closed')>Closed</option>
                        </select>
                        <button class="btn btn-sm btn-outline-secondary">Filter</button>
                        <a href="{{ route('member-state.questions.index') }}"
                            class="btn btn-sm btn-light border">Clear</a>
                    </form>
                </div>
            </div>
            <div class="card-body">
                @if ($questions->isNotEmpty())
                    @foreach ($questions as $item)
                        @php
                            $rowNumber = ((int) ($questions->firstItem() ?? 1)) + $loop->index;
                            $statusClass = 'status-' . (string) $item->status;
                            $priorityClass = 'priority-' . (string) $item->priority;
                        @endphp
                        <article class="qa-item">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="d-flex align-items-start gap-2">
                                    <span class="qa-order">{{ $rowNumber }}</span>
                                    <div>
                                        <h6 class="mb-1 text-dark">{{ $item->subject }}</h6>
                                        <div class="qa-meta">
                                            {{ optional($item->asked_on)->format('d M Y') }} |
                                            Routed through AU Questions Desk
                                        </div>
                                    </div>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <span
                                        class="qa-badge {{ $priorityClass }}">{{ ucfirst((string) $item->priority) }}</span>
                                    <span
                                        class="qa-badge {{ $statusClass }}">{{ ucfirst(str_replace('_', ' ', (string) $item->status)) }}</span>
                                    @if ($item->status !== 'answered')
                                        <form action="{{ route('member-state.questions.destroy', $item) }}"
                                            method="POST" onsubmit="return confirm('Delete this question?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endif
                                </div>
                            </div>

                            <p class="small text-dark mt-2 mb-2">
                                {{ \Illuminate\Support\Str::limit((string) $item->question_text, 420) }}</p>

                            @if ($item->answer_text)
                                <div class="qa-response">
                                    <div class="small fw-semibold text-success mb-1">
                                        AU Response
                                        @if ($item->answered_at)
                                            <span class="text-muted fw-normal">|
                                                {{ $item->answered_at->format('d M Y H:i') }}</span>
                                        @endif
                                    </div>
                                    <div class="small text-dark">
                                        {{ \Illuminate\Support\Str::limit((string) $item->answer_text, 520) }}</div>
                                    @if ($item->answeredBy)
                                        <div class="small text-muted mt-1">Responded by {{ $item->answeredBy->name }}
                                            ({{ $item->answeredBy->email }})</div>
                                    @endif
                                </div>
                            @else
                                <div class="small text-muted">Awaiting AU response.</div>
                            @endif
                        </article>
                    @endforeach
                @else
                    <div class="qa-empty">No questions found for the selected filter.</div>
                @endif
            </div>
            @if ($questions->hasPages())
                <div class="card-footer bg-white">
                    {{ $questions->links() }}
                </div>
            @endif
        </div>
    </main>
@endsection
