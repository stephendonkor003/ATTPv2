@extends('layouts.app')

@push('styles')
    @include('finance.funders.partials.crm-styles')
@endpush

@section('content')
    @php
        $totalPartners = $funders->count();
        $totalPrograms = $funders->sum('total_programs_supported');
        $totalUsd = $funders->sum('total_amount_usd');
        $activePartners = $funders->where('partnership_status', 'active')->count();
        $atRiskPartners = $funders->where('partnership_status', 'at_risk')->count();
        $portalEnabled = $funders->filter(fn ($partner) => $partner->hasPortalAccess())->count();
        $fundingRecords = $funders->sum(fn ($partner) => $partner->programFundings->count());
        $needsFollowUp = $funders->filter(fn ($partner) => $partner->next_follow_up_at && $partner->next_follow_up_at->lte(now()->startOfDay()))->count();
        $topPartners = $funders
            ->sortByDesc(fn ($partner) => (float) ($partner->total_amount_usd ?? 0))
            ->take(5)
            ->values();
        $maxUsd = max(1, (float) $topPartners->max(fn ($partner) => (float) ($partner->total_amount_usd ?? 0)));
        $typeBreakdown = $funders
            ->groupBy(fn ($partner) => $partner->formatStatusLabel($partner->type))
            ->map->count()
            ->sortDesc();
        $statusBreakdown = $funders
            ->groupBy(fn ($partner) => $partner->formatStatusLabel($partner->partnership_status))
            ->map->count()
            ->sortDesc();
        $latestEngagements = $funders
            ->filter(fn ($partner) => $partner->last_engagement_at)
            ->sortByDesc(fn ($partner) => $partner->last_engagement_at)
            ->take(4)
            ->values();
    @endphp

    <div class="nxl-container funders-workspace">
        <div class="funders-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                <div>
                    <div class="funders-kicker mb-2">Funding Partner Intelligence</div>
                    <h4 class="fw-bold mb-2">Partner Portfolio Command Center</h4>
                    <p class="mb-0">
                        Track funding relationships, portal access, communication follow-ups, and portfolio value across ATTP programs.
                    </p>
                    <div class="d-flex flex-wrap gap-2 mt-3">
                        <span class="funders-hero-chip"><i class="feather-users"></i> {{ number_format($totalPartners) }} partners</span>
                        <span class="funders-hero-chip"><i class="feather-briefcase"></i> {{ number_format($totalPrograms) }} supported programs</span>
                        <span class="funders-hero-chip"><i class="feather-shield"></i> {{ number_format($portalEnabled) }} portal-enabled</span>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-content-start justify-content-xl-end gap-2">
                    @can('finance.program_funding.view')
                        <a href="{{ route('finance.program-funding.index') }}" class="btn btn-light">
                            <i class="feather-file-text me-1"></i> Funding Records
                        </a>
                    @endcan
                    @can('finance.funders.create')
                        <a href="{{ route('finance.funders.create') }}" class="btn btn-success">
                            <i class="feather-plus-circle me-1"></i> Add Partner
                        </a>
                    @endcan
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="funders-stat-grid mt-3">
            <div class="partner-page-stat">
                <span class="funders-stat-icon"><i class="feather-users"></i></span>
                <div>
                    <div class="label">Partner Organizations</div>
                    <div class="value">{{ number_format($totalPartners) }}</div>
                    <small>{{ number_format($portalEnabled) }} have portal access</small>
                </div>
            </div>
            <div class="partner-page-stat">
                <span class="funders-stat-icon amber"><i class="feather-activity"></i></span>
                <div>
                    <div class="label">Active Partnerships</div>
                    <div class="value">{{ number_format($activePartners) }}</div>
                    <small>{{ number_format($atRiskPartners) }} marked at risk</small>
                </div>
            </div>
            <div class="partner-page-stat">
                <span class="funders-stat-icon wine"><i class="feather-dollar-sign"></i></span>
                <div>
                    <div class="label">USD Portfolio</div>
                    <div class="value value-money">USD {{ number_format((float) $totalUsd, 2) }}</div>
                    <small>{{ number_format($fundingRecords) }} funding records</small>
                </div>
            </div>
            <div class="partner-page-stat">
                <span class="funders-stat-icon blue"><i class="feather-calendar"></i></span>
                <div>
                    <div class="label">Follow-Up Queue</div>
                    <div class="value">{{ number_format($needsFollowUp) }}</div>
                    <small>Due or overdue relationship actions</small>
                </div>
            </div>
        </div>

        <div class="funders-insight-grid mt-3">
            <div class="funders-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Portfolio Concentration</h6>
                        <p class="text-muted small mb-0">Largest partners by USD funding value.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">Top {{ number_format($topPartners->count()) }}</span>
                </div>

                <div class="d-grid gap-3">
                    @forelse ($topPartners as $partner)
                        @php
                            $partnerUsd = (float) ($partner->total_amount_usd ?? 0);
                            $width = max(3, min(100, ($partnerUsd / $maxUsd) * 100));
                        @endphp
                        <div class="funders-progress-item">
                            <div class="d-flex justify-content-between align-items-center gap-3 mb-1">
                                <div class="fw-semibold text-dark text-truncate">{{ $partner->name }}</div>
                                <div class="small fw-bold text-nowrap">USD {{ number_format($partnerUsd, 2) }}</div>
                            </div>
                            <div class="funders-progress">
                                <span style="width: {{ $width }}%;"></span>
                            </div>
                        </div>
                    @empty
                        <div class="partner-empty-state">No partner funding records have been added yet.</div>
                    @endforelse
                </div>
            </div>

            <div class="funders-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Relationship Health</h6>
                        <p class="text-muted small mb-0">Status and institution mix at a glance.</p>
                    </div>
                    <span class="badge bg-light text-dark border">{{ number_format($totalPartners) }} total</span>
                </div>

                <div class="funders-status-cloud mb-3">
                    @forelse ($statusBreakdown as $status => $count)
                        <span><strong>{{ number_format($count) }}</strong> {{ $status }}</span>
                    @empty
                        <span><strong>0</strong> No lifecycle status</span>
                    @endforelse
                </div>

                <div class="funders-type-grid">
                    @forelse ($typeBreakdown as $type => $count)
                        <div class="funders-type-card">
                            <span>{{ $type }}</span>
                            <strong>{{ number_format($count) }}</strong>
                        </div>
                    @empty
                        <div class="partner-empty-state">No partner types have been configured.</div>
                    @endforelse
                </div>
            </div>

            <div class="funders-panel">
                <div class="d-flex justify-content-between align-items-start gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Latest Engagements</h6>
                        <p class="text-muted small mb-0">Recent CRM activity and partner requests.</p>
                    </div>
                    <i class="feather-clock text-success"></i>
                </div>

                <div class="d-grid gap-2">
                    @forelse ($latestEngagements as $partner)
                        <div class="funders-engagement-row">
                            <div>
                                <div class="fw-semibold text-dark">{{ $partner->name }}</div>
                                <small class="text-muted">
                                    {{ $partner->latest_communication['subject'] ?? 'Partner engagement logged' }}
                                </small>
                            </div>
                            <span>{{ $partner->last_engagement_at->format('d M Y') }}</span>
                        </div>
                    @empty
                        <div class="partner-empty-state">No engagement activity has been logged yet.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4 border-0 funders-table-card">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-2 mb-3">
                    <div>
                        <h6 class="fw-bold mb-1">Partner Registry</h6>
                        <p class="text-muted small mb-0">Open a CRM profile, review access, and update partner records.</p>
                    </div>
                    <span class="badge bg-success-subtle text-success">
                        {{ number_format($funders->count()) }} records
                    </span>
                </div>

                <x-data-table id="fundersTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Partner</th>
                            <th>Relationship</th>
                            <th>Correspondent</th>
                            <th>Portfolio</th>
                            <th>Engagement</th>
                            <th>Requests</th>
                            <th width="140" class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($funders as $funder)
                            @php
                                $initials = \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($funder->name ?? 'P', 0, 2));
                                $latestCommunication = $funder->latest_communication;
                            @endphp
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="funders-partner-cell">
                                        <div class="funders-avatar">
                                            @if($funder->hasLogo())
                                                <img src="{{ $funder->getLogoUrl() }}" alt="{{ $funder->name }}">
                                            @else
                                                {{ $initials }}
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <div class="fw-semibold text-dark">{{ $funder->name }}</div>
                                            <div class="small text-muted text-truncate">
                                                {{ $funder->contact_email ?: ($funder->portalUser?->email ?: 'No contact email') }}
                                            </div>
                                            <div class="d-flex flex-wrap gap-1 mt-1">
                                                <span class="badge {{ $funder->hasPortalAccess() ? 'bg-success-subtle text-success' : 'bg-light text-muted border' }}">
                                                    {{ $funder->hasPortalAccess() ? 'Portal enabled' : 'Portal disabled' }}
                                                </span>
                                                @if($funder->portalUsers->count() > 1)
                                                    <span class="badge bg-info-subtle text-info">
                                                        {{ number_format($funder->portalUsers->count()) }} users
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex flex-wrap gap-1">
                                        <span class="badge {{ $funder->getTypeBadgeClass() }}">
                                            {{ $funder->formatStatusLabel($funder->type) }}
                                        </span>
                                        <span class="badge {{ $funder->getPartnershipStatusBadgeClass() }}">
                                            {{ $funder->formatStatusLabel($funder->partnership_status) }}
                                        </span>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        Since {{ $funder->partnership_started_at?->format('d M Y') ?? 'not set' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $funder->relationshipManager?->name ?: 'Unassigned' }}</div>
                                    <div class="small text-muted">
                                        {{ $funder->contact_person ?: 'No focal person' }}
                                    </div>
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">USD {{ number_format((float) ($funder->total_amount_usd ?? 0), 2) }}</div>
                                    <div class="small text-muted">
                                        {{ number_format((int) ($funder->total_programs_supported ?? 0)) }} programs,
                                        {{ number_format($funder->programFundings->count()) }} records
                                    </div>
                                </td>
                                <td>
                                    @if($funder->last_engagement_at)
                                        <div class="fw-semibold text-dark">{{ $funder->last_engagement_at->format('d M Y') }}</div>
                                        <div class="small text-muted">
                                            {{ $latestCommunication['label'] ?? 'CRM activity' }}
                                        </div>
                                    @else
                                        <span class="text-muted">Not logged</span>
                                    @endif

                                    @if($funder->next_follow_up_at)
                                        <div class="small {{ $funder->next_follow_up_at->lte(now()->startOfDay()) ? 'text-danger fw-semibold' : 'text-muted' }}">
                                            Next: {{ $funder->next_follow_up_at->format('d M Y') }}
                                        </div>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-bold text-dark">{{ number_format((int) ($funder->open_requests_count ?? 0)) }}</div>
                                    <small class="text-muted">open requests</small>
                                </td>
                                <td class="text-end">
                                    <div class="funders-table-actions">
                                    <button type="button"
                                        class="funders-icon-action partner-crm-trigger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#partnerCrmModal"
                                        data-partner-url="{{ route('finance.funders.show', ['funder' => $funder, 'modal' => 1]) }}"
                                        data-partner-name="{{ $funder->name }}"
                                        title="View Partner CRM">
                                        <i class="feather-eye"></i>
                                    </button>

                                    @can('finance.funders.edit')
                                        <a href="{{ route('finance.funders.edit', $funder) }}"
                                            class="funders-icon-action warning"
                                            title="Edit Partner">
                                            <i class="feather-edit"></i>
                                        </a>
                                    @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>
    </div>

    <div class="modal fade partner-crm-modal" id="partnerCrmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content border-0">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="text-muted small text-uppercase">Partner CRM</div>
                        <h5 class="modal-title fw-bold mb-0" data-partner-modal-title>Partner Profile</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3" data-partner-modal-body>
                    <div class="partner-crm-loader">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <div>Loading partner CRM snapshot...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('partnerCrmModal');

            if (!modalEl) {
                return;
            }

            const modalTitle = modalEl.querySelector('[data-partner-modal-title]');
            const modalBody = modalEl.querySelector('[data-partner-modal-body]');

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                const url = trigger.getAttribute('data-partner-url');
                const partnerName = trigger.getAttribute('data-partner-name') || 'Partner Profile';

                modalTitle.textContent = partnerName;
                modalBody.innerHTML = `
                    <div class="partner-crm-loader">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <div>Loading partner CRM snapshot...</div>
                        </div>
                    </div>
                `;

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Unable to load the partner profile.');
                        }

                        return response.text();
                    })
                    .then((html) => {
                        modalBody.innerHTML = html;
                    })
                    .catch((error) => {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger mb-0">
                                ${error.message || 'Unable to load the partner profile at the moment.'}
                            </div>
                        `;
                    });
            });
        });
    </script>
@endpush
