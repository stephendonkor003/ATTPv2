@extends('layouts.app')

@section('title', 'Member State Dashboard')

@push('styles')
    <style>
        .ms-dashboard-shell {
            position: relative;
            isolation: isolate;
        }

        .ms-dashboard-shell::before,
        .ms-dashboard-shell::after {
            content: "";
            position: absolute;
            z-index: -1;
            border-radius: 50%;
            pointer-events: none;
        }

        .ms-dashboard-shell::before {
            width: 260px;
            height: 260px;
            top: -50px;
            right: -40px;
            background: radial-gradient(circle, rgba(14, 165, 233, 0.18), transparent 70%);
        }

        .ms-dashboard-shell::after {
            width: 300px;
            height: 300px;
            bottom: -60px;
            left: -70px;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.16), transparent 70%);
        }

        .ms-hero {
            border: 1px solid #d1e8ff;
            border-radius: 16px;
            background: linear-gradient(125deg, #0f172a 0%, #0f766e 52%, #0ea5e9 100%);
            color: #f8fafc;
            padding: 1.15rem 1.25rem;
            overflow: hidden;
            position: relative;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.2);
        }

        .ms-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 15% 20%, rgba(255, 255, 255, 0.2), transparent 42%),
                radial-gradient(circle at 85% 0%, rgba(255, 255, 255, 0.16), transparent 36%);
            pointer-events: none;
        }

        .ms-hero > * {
            position: relative;
            z-index: 1;
        }

        .ms-hero-kicker {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(248, 250, 252, 0.82);
        }

        .ms-hero-title {
            font-weight: 700;
            margin: 0.2rem 0;
            color: #ffffff;
        }

        .ms-chip-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.75rem;
        }

        .ms-chip {
            border: 1px solid rgba(248, 250, 252, 0.34);
            background: rgba(248, 250, 252, 0.13);
            color: #f8fafc;
            border-radius: 999px;
            font-size: 0.78rem;
            padding: 0.28rem 0.72rem;
            display: inline-flex;
            align-items: center;
            gap: 0.38rem;
        }

        .ms-flag-panel {
            border: 1px solid #d4ecff;
            border-radius: 14px;
            background: linear-gradient(120deg, #f8fbff 0%, #e8f6ff 65%, #ecfeff 100%);
            padding: 0.8rem;
            height: 100%;
        }

        .ms-flag-frame {
            width: 100%;
            height: 165px;
            border-radius: 12px;
            overflow: hidden;
            border: 1px solid #bfdbfe;
            background: #0f172a;
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.18);
            margin-bottom: 0.65rem;
        }

        .ms-flag-wave {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transform-origin: left center;
            animation: msFlagWave 4.7s ease-in-out infinite;
        }

        @keyframes msFlagWave {
            0%,
            100% {
                transform: perspective(900px) rotateY(0deg) skewY(0deg) scaleX(1);
            }
            25% {
                transform: perspective(900px) rotateY(-10deg) skewY(1.3deg) scaleX(1.02);
            }
            50% {
                transform: perspective(900px) rotateY(5deg) skewY(-1deg) scaleX(0.99);
            }
            75% {
                transform: perspective(900px) rotateY(-7deg) skewY(0.7deg) scaleX(1.01);
            }
        }

        .ms-stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 14px;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);
            transition: transform 0.25s ease, box-shadow 0.25s ease;
            height: 100%;
        }

        .ms-stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.12);
        }

        .ms-stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            color: #ffffff;
        }

        .ms-stat-icon.blue {
            background: linear-gradient(135deg, #0284c7, #0ea5e9);
        }

        .ms-stat-icon.green {
            background: linear-gradient(135deg, #059669, #10b981);
        }

        .ms-stat-icon.orange {
            background: linear-gradient(135deg, #ea580c, #f59e0b);
        }

        .ms-stat-icon.indigo {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
        }
    </style>
@endpush

@section('content')
    @php
        $flagUrl = $memberState?->flag_url ?: asset('assets/images/au.png');
        $totalTreaties = (int) ($treatyStats->total_records ?? 0);
        $signedCount = (int) ($treatyStats->signed_count ?? 0);
        $ratifiedCount = (int) ($treatyStats->ratified_count ?? 0);
        $signedRate = $totalTreaties > 0 ? ($signedCount / $totalTreaties) * 100 : 0;
        $ratifiedRate = $totalTreaties > 0 ? ($ratifiedCount / $totalTreaties) * 100 : 0;
    @endphp

    <main class="nxl-container ms-dashboard-shell">
        @if (isset($missingTables) && $missingTables->isNotEmpty())
            <div class="alert alert-warning border-0 shadow-sm mb-4">
                <strong>Setup needed:</strong> Some member-state portal tables are missing, so this dashboard uses
                placeholder values.
                <div class="mt-2"><code>php artisan migrate</code></div>
            </div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-xl-8">
                <div class="ms-hero h-100">
                    <div class="ms-hero-kicker">Member State Intelligence Dashboard</div>
                    <h3 class="ms-hero-title">{{ $memberState?->name ?? 'Member State' }}</h3>
                    <p class="mb-0">
                        Track detailed cooperation performance for Agenda 2063, review treaty status, and submit national
                        evidence day-by-day, month-by-month, and year-by-year.
                    </p>

                    <div class="ms-chip-row">
                        <span class="ms-chip"><i class="feather-target"></i> Avg Cooperation: {{ number_format((float) $summary['avg_cooperation_score'], 2) }}%</span>
                        <span class="ms-chip"><i class="feather-award"></i> Treaty Ratification: {{ number_format($ratifiedRate, 1) }}%</span>
                        <span class="ms-chip"><i class="feather-trending-up"></i> Commodity Growth: {{ number_format((float) $summary['avg_commodity_growth'], 2) }}%</span>
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <a href="{{ route('member-state.national-data.index') }}" class="btn btn-light btn-sm">
                            <i class="feather-database me-1"></i> Submit National Data
                        </a>
                        <a href="{{ route('member-state.comparisons.index') }}" class="btn btn-outline-light btn-sm">
                            <i class="feather-bar-chart-2 me-1"></i> Compare Performance
                        </a>
                        <a href="{{ route('member-state.questions.index') }}" class="btn btn-outline-light btn-sm">
                            <i class="feather-help-circle me-1"></i> Ask the AU
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="ms-flag-panel">
                    <div class="ms-flag-frame">
                        <img src="{{ $flagUrl }}" alt="{{ $memberState?->name ?? 'Member State' }} flag" class="ms-flag-wave">
                    </div>
                    <div class="fw-semibold text-dark">{{ $memberState?->name ?? 'Member State' }} Flag</div>
                    <small class="text-muted d-block mb-2">Live symbol of your national commitment in the AU platform.</small>
                    <div class="d-flex justify-content-between small">
                        <span class="text-muted">Signed Treaties</span>
                        <strong>{{ $signedCount }} / {{ $totalTreaties }}</strong>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                        <div class="progress-bar bg-info" style="width: {{ number_format($signedRate, 2, '.', '') }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between small mt-2">
                        <span class="text-muted">Ratified Treaties</span>
                        <strong>{{ $ratifiedCount }} / {{ $totalTreaties }}</strong>
                    </div>
                    <div class="progress mt-1" style="height: 6px;">
                        <div class="progress-bar bg-success" style="width: {{ number_format($ratifiedRate, 2, '.', '') }}%"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="ms-stat-card p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="ms-stat-icon blue"><i class="feather-database"></i></div>
                        <small class="text-muted">Records</small>
                    </div>
                    <h3 class="mb-1">{{ number_format($summary['national_data_count']) }}</h3>
                    <small class="text-muted">National data points submitted</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ms-stat-card p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="ms-stat-icon green"><i class="feather-target"></i></div>
                        <small class="text-muted">Score</small>
                    </div>
                    <h3 class="mb-1">{{ number_format((float) $summary['avg_cooperation_score'], 2) }}%</h3>
                    <small class="text-muted">Average Agenda 2063 cooperation</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ms-stat-card p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="ms-stat-icon orange"><i class="feather-help-circle"></i></div>
                        <small class="text-muted">Open</small>
                    </div>
                    <h3 class="mb-1">{{ number_format($summary['open_questions']) }}</h3>
                    <small class="text-muted">Questions awaiting AU feedback</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="ms-stat-card p-3">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="ms-stat-icon indigo"><i class="feather-package"></i></div>
                        <small class="text-muted">Trends</small>
                    </div>
                    <h3 class="mb-1">{{ number_format($summary['commodity_trend_count']) }}</h3>
                    <small class="text-muted">Commodity trend entries logged</small>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <strong>Latest National Data Entries</strong>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Indicator</th>
                                    <th>Score</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($latestNationalData as $row)
                                    <tr>
                                        <td>{{ optional($row->recorded_on)->format('d M Y') }}</td>
                                        <td>
                                            <div class="fw-semibold">{{ $row->indicator_name }}</div>
                                            <small class="text-muted">
                                                @if($row->aspiration) Asp {{ $row->aspiration->number }} @endif
                                                @if($row->goal) | Goal {{ $row->goal->number }} @endif
                                            </small>
                                        </td>
                                        <td>{{ number_format((float) $row->cooperation_score, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No national data submitted yet.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <strong>Performance Snapshot (Last 6 Months)</strong>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm mb-0">
                                <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Member State</th>
                                    <th>Avg Score</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($comparisonRows as $row)
                                    <tr @if($row->is_current) class="table-primary" @endif>
                                        <td>{{ $row->rank }}</td>
                                        <td>{{ $row->member_state_name }}</td>
                                        <td>{{ number_format((float) $row->avg_score, 2) }}%</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No comparison data yet.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Latest Commodity Trend Inputs</strong>
                        <a href="{{ route('member-state.commodities.index') }}" class="btn btn-sm btn-outline-primary">Open</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Commodity</th>
                                    <th>Growth</th>
                                </tr>
                                </thead>
                                <tbody>
                                @forelse($latestCommodityTrends as $trend)
                                    <tr>
                                        <td>{{ optional($trend->recorded_on)->format('d M Y') }}</td>
                                        <td>{{ $trend->commodity?->name ?? 'N/A' }}</td>
                                        <td>
                                            @if($trend->growth_rate_pct !== null)
                                                {{ number_format((float) $trend->growth_rate_pct, 2) }}%
                                            @else
                                                <span class="text-muted">-</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-3">No commodity trends yet.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white">
                        <strong>Action Board</strong>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2 mb-3">
                            <a href="{{ route('member-state.treaties.index') }}" class="btn btn-outline-dark btn-sm text-start">
                                <i class="feather-file-text me-2"></i> Update Treaty Sign/Ratification Status
                            </a>
                            <a href="{{ route('member-state.communications.index') }}" class="btn btn-outline-dark btn-sm text-start">
                                <i class="feather-send me-2"></i> Send Official Communication to AU
                            </a>
                            <a href="{{ route('member-state.questions.index') }}" class="btn btn-outline-dark btn-sm text-start">
                                <i class="feather-help-circle me-2"></i> Submit Questions and Follow Responses
                            </a>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Treaty Signing Completion</span>
                            <strong>{{ number_format($signedRate, 1) }}%</strong>
                        </div>
                        <div class="progress mb-2" style="height: 7px;">
                            <div class="progress-bar bg-info" style="width: {{ number_format($signedRate, 2, '.', '') }}%"></div>
                        </div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Treaty Ratification Completion</span>
                            <strong>{{ number_format($ratifiedRate, 1) }}%</strong>
                        </div>
                        <div class="progress" style="height: 7px;">
                            <div class="progress-bar bg-success" style="width: {{ number_format($ratifiedRate, 2, '.', '') }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
@endsection
