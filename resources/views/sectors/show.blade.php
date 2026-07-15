@extends('layouts.app')

@section('title', 'Portfolio Details')

@push('styles')
    <style>
        .portfolio-show {
            color: #0f172a;
        }

        .portfolio-show-hero {
            border-radius: 8px;
            padding: 20px;
            color: #ffffff;
            background: linear-gradient(135deg, #063f36 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 16px 32px rgba(6, 63, 54, 0.14);
        }

        .portfolio-show-hero h4,
        .portfolio-show-hero p {
            color: #ffffff;
        }

        .portfolio-show-kicker {
            color: #d9fff4;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .portfolio-show-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            color: #effff9;
            background: rgba(255, 255, 255, 0.1);
            font-size: 0.82rem;
            font-weight: 700;
        }

        .portfolio-show-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .portfolio-show-card,
        .portfolio-show-panel {
            border: 1px solid #dbe3ea;
            border-radius: 8px;
            background: #ffffff;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .portfolio-show-card {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
        }

        .portfolio-show-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #065f46;
            background: #d1fae5;
            flex: 0 0 auto;
        }

        .portfolio-show-icon.blue {
            color: #075985;
            background: #e0f2fe;
        }

        .portfolio-show-icon.amber {
            color: #92400e;
            background: #fef3c7;
        }

        .portfolio-show-icon.wine {
            color: #522b39;
            background: #f8e8ef;
        }

        .portfolio-show-label {
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .portfolio-show-value {
            color: #0f172a;
            font-size: 1.3rem;
            font-weight: 800;
            line-height: 1.1;
        }

        .portfolio-show-layout {
            display: grid;
            grid-template-columns: minmax(0, 0.72fr) minmax(0, 1.28fr);
            gap: 14px;
            align-items: start;
        }

        .portfolio-show-panel {
            padding: 16px;
        }

        .portfolio-info-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 0;
        }

        .portfolio-info-row:last-child {
            border-bottom: 0;
        }

        .portfolio-info-row span {
            color: #64748b;
            font-size: 0.82rem;
            font-weight: 800;
        }

        .portfolio-info-row strong {
            text-align: right;
        }

        .portfolio-program-card {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #f8fafc;
        }

        .portfolio-program-card + .portfolio-program-card {
            margin-top: 10px;
        }

        @media (max-width: 1199.98px) {
            .portfolio-show-grid,
            .portfolio-show-layout {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 767.98px) {
            .portfolio-show-grid,
            .portfolio-show-layout {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container portfolio-show">
        <div class="portfolio-show-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="portfolio-show-kicker mb-2">Portfolio Detail</div>
                    <h4 class="fw-bold mb-2">{{ $sector->name }}</h4>
                    <p class="mb-0">{{ $sector->description ?: 'No portfolio description has been added yet.' }}</p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="portfolio-show-chip"><i class="feather-user-check"></i> {{ $sector->portfolio_manager_role ?: 'Leadership role pending' }}</span>
                        <span class="portfolio-show-chip"><i class="feather-shield"></i> {{ $sector->governanceNode->name ?? 'No governance node' }}</span>
                        <span class="portfolio-show-chip"><i class="feather-check-circle"></i> {{ ucfirst($sector->status ?? 'active') }}</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    <a href="{{ route('budget.portfolios.index') }}" class="btn btn-light">
                        <i class="feather-arrow-left me-1"></i> Portfolios
                    </a>
                    @can('sector.edit')
                        <a href="{{ route('budget.portfolios.edit', $sector) }}" class="btn btn-success">
                            <i class="feather-edit-2 me-1"></i> Edit Portfolio
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="portfolio-show-grid mt-3">
            <div class="portfolio-show-card">
                <span class="portfolio-show-icon"><i class="feather-layers"></i></span>
                <div>
                    <div class="portfolio-show-label">Programs</div>
                    <div class="portfolio-show-value">{{ number_format($portfolioStats['programs']) }}</div>
                </div>
            </div>
            <div class="portfolio-show-card">
                <span class="portfolio-show-icon blue"><i class="feather-briefcase"></i></span>
                <div>
                    <div class="portfolio-show-label">Projects</div>
                    <div class="portfolio-show-value">{{ number_format($portfolioStats['projects']) }}</div>
                </div>
            </div>
            <div class="portfolio-show-card">
                <span class="portfolio-show-icon amber"><i class="feather-git-branch"></i></span>
                <div>
                    <div class="portfolio-show-label">Activities</div>
                    <div class="portfolio-show-value">{{ number_format($portfolioStats['activities']) }}</div>
                    <small class="text-muted">{{ number_format($portfolioStats['sub_activities']) }} sub-activities</small>
                </div>
            </div>
            <div class="portfolio-show-card">
                <span class="portfolio-show-icon wine"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="portfolio-show-label">Budget Envelope</div>
                    <div class="portfolio-show-value">{{ $portfolioStats['currency'] }} {{ number_format((float) $portfolioStats['budget'], 2) }}</div>
                </div>
            </div>
        </div>

        <div class="portfolio-show-layout mt-3">
            <section class="portfolio-show-panel">
                <h6 class="fw-bold mb-3">Leadership & Governance</h6>

                <div class="portfolio-info-row">
                    <span>Portfolio Leader</span>
                    <strong>{{ $sector->portfolio_manager_name ?: optional($sector->portfolioManager)->name ?: 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>Leader Email</span>
                    <strong>{{ $sector->portfolio_manager_email ?: optional($sector->portfolioManager)->email ?: 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>Leader Role</span>
                    <strong>{{ $sector->portfolio_manager_role ?: optional(optional($sector->portfolioManager)->role)->name ?: 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>TTL</span>
                    <strong>{{ $sector->ttl_name ?: 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>TTL Email</span>
                    <strong>{{ $sector->ttl_email ?: 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>Governance Node</span>
                    <strong>{{ $sector->governanceNode->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>Governance Level</span>
                    <strong>{{ $sector->governanceNode->level->name ?? 'Not assigned' }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>Portfolio Currency</span>
                    <strong>{{ $sector->currency ?: $portfolioStats['currency'] }}</strong>
                </div>
                <div class="portfolio-info-row">
                    <span>Last Update</span>
                    <strong>{{ $portfolioStats['latest_update'] ? $portfolioStats['latest_update']->format('d M Y H:i') : 'Not logged' }}</strong>
                </div>
            </section>

            <section class="portfolio-show-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Programs Under This Portfolio</h6>
                        <p class="text-muted small mb-0">Programs, projects, activities, and sub-activities tied to this portfolio.</p>
                    </div>
                    <a href="{{ route('budget.programs.create') }}" class="btn btn-outline-success btn-sm">
                        <i class="feather-plus me-1"></i> Program
                    </a>
                </div>

                @forelse ($sector->programs as $program)
                    @php
                        $projects = $program->projects;
                        $activities = $projects->flatMap->activities;
                    @endphp
                    <div class="portfolio-program-card">
                        <div class="d-flex flex-column flex-lg-row justify-content-between gap-2">
                            <div>
                                <div class="fw-bold text-dark">{{ $program->name }}</div>
                                <div class="text-muted small">{{ Str::limit($program->description ?: 'No program description.', 120) }}</div>
                            </div>
                            <div class="d-flex flex-wrap gap-1 align-content-start">
                                <span class="badge bg-primary-subtle text-primary">{{ number_format($projects->count()) }} projects</span>
                                <span class="badge bg-warning-subtle text-warning">{{ number_format($activities->count()) }} activities</span>
                                <span class="badge bg-light text-dark border">{{ number_format($activities->flatMap->subActivities->count()) }} sub</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center text-muted py-4">
                        No programs have been linked to this portfolio yet.
                    </div>
                @endforelse
            </section>
        </div>
    </div>
@endsection
