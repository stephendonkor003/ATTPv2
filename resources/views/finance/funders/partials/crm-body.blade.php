@php
    $latestCommunication = $funder->latest_communication;
    $statusBreakdown = $funder->program_status_breakdown ?? collect();
@endphp

<div class="partner-crm-shell">
    <div class="partner-crm-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between gap-4">
            <div class="d-flex align-items-start gap-3">
                <div class="partner-crm-avatar">
                    @if($funder->hasLogo())
                        <img src="{{ $funder->getLogoUrl() }}" alt="{{ $funder->name }}">
                    @else
                        {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($funder->name ?? 'P', 0, 2)) }}
                    @endif
                </div>

                <div>
                    <div class="partner-crm-kicker">Partner CRM Snapshot</div>
                    <h3 class="fw-bold mb-2">{{ $funder->name }}</h3>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge {{ $funder->getTypeBadgeClass() }}">
                            {{ $funder->formatStatusLabel($funder->type) }}
                        </span>
                        <span class="badge {{ $funder->getPartnershipStatusBadgeClass() }}">
                            {{ $funder->formatStatusLabel($funder->partnership_status) }}
                        </span>
                        <span class="badge {{ $funder->hasPortalAccess() ? 'bg-light text-dark' : 'bg-secondary' }}">
                            {{ $funder->hasPortalAccess() ? 'Portal enabled' : 'Portal disabled' }}
                        </span>
                    </div>
                    <div class="small mt-3 text-white-50">
                        Added on {{ optional($funder->created_at)->format('d M Y') ?: 'N/A' }}
                        @if($funder->partnership_started_at)
                            | Partnership started {{ $funder->partnership_started_at->format('d M Y') }}
                        @endif
                    </div>
                </div>
            </div>

            <div class="text-lg-end">
                <a href="{{ route('finance.funders.pdf', $funder) }}" class="btn btn-light mb-2">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
                @can('finance.funders.edit')
                    <div>
                        <a href="{{ route('finance.funders.edit', $funder) }}" class="btn btn-outline-light">
                            <i class="feather-edit me-1"></i> Update Partner
                        </a>
                    </div>
                @endcan
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="partner-crm-metric p-3">
                <div class="metric-label mb-2">Programs Supported</div>
                <div class="metric-value">{{ number_format((int) ($funder->total_programs_supported ?? 0)) }}</div>
                <small class="text-muted">{{ number_format($funder->programFundings->count()) }} funding records linked</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="partner-crm-metric p-3">
                <div class="metric-label mb-2">Total Amount in USD</div>
                <div class="metric-value">USD {{ number_format((float) ($funder->total_amount_usd ?? 0), 2) }}</div>
                <small class="text-muted">USD-only records aggregated automatically</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="partner-crm-metric p-3">
                <div class="metric-label mb-2">Open Requests</div>
                <div class="metric-value">{{ number_format((int) ($funder->open_requests_count ?? 0)) }}</div>
                <small class="text-muted">{{ number_format((int) ($funder->resolved_requests_count ?? 0)) }} resolved requests</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="partner-crm-metric p-3">
                <div class="metric-label mb-2">Last Engagement</div>
                <div class="metric-value" style="font-size: 1.1rem;">
                    {{ optional($funder->last_engagement_at)->format('d M Y') ?: 'Not logged' }}
                </div>
                <small class="text-muted">
                    {{ optional($funder->last_engagement_at)->format('H:i') ?: 'Awaiting first touchpoint' }}
                </small>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-xl-4">
            <div class="partner-crm-card p-4 mb-4">
                <h5 class="card-title mb-3">Contact & Ownership</h5>

                <div class="partner-detail-row">
                    <span>Primary contact</span>
                    <strong>{{ $funder->contact_person ?: 'Not set' }}</strong>
                </div>
                <div class="partner-detail-row">
                    <span>Email</span>
                    <strong>{{ $funder->contact_email ?: 'Not set' }}</strong>
                </div>
                <div class="partner-detail-row">
                    <span>Phone</span>
                    <strong>{{ $funder->contact_phone ?: 'Not set' }}</strong>
                </div>
                <div class="partner-detail-row">
                    <span>Correspondent / manager</span>
                    <strong>{{ $funder->relationshipManager?->name ?: 'Unassigned' }}</strong>
                </div>
                <div class="partner-detail-row">
                    <span>Portal account</span>
                    <strong>{{ $funder->portalUser?->email ?: 'Portal disabled' }}</strong>
                </div>
                <div class="partner-detail-row">
                    <span>Default currency</span>
                    <strong>{{ strtoupper($funder->currency ?: 'N/A') }}</strong>
                </div>
            </div>

            <div class="partner-crm-card p-4 mb-4">
                <h5 class="card-title mb-3">Lifecycle Tracker</h5>
                <ul class="partner-timeline">
                    <li>
                        <div class="timeline-label">Record created</div>
                        <div class="timeline-value">{{ optional($funder->created_at)->format('d M Y H:i') ?: 'N/A' }}</div>
                    </li>
                    <li>
                        <div class="timeline-label">Partnership started</div>
                        <div class="timeline-value">{{ optional($funder->partnership_started_at)->format('d M Y') ?: 'Not set' }}</div>
                    </li>
                    <li>
                        <div class="timeline-label">Next follow-up</div>
                        <div class="timeline-value">{{ optional($funder->next_follow_up_at)->format('d M Y') ?: 'Not scheduled' }}</div>
                    </li>
                    <li>
                        <div class="timeline-label">Latest manual communication</div>
                        <div class="timeline-value">{{ optional($funder->last_contact_at)->format('d M Y H:i') ?: 'Not logged' }}</div>
                    </li>
                </ul>
            </div>

            <div class="partner-crm-card p-4 mb-4">
                <h5 class="card-title mb-3">Latest Communication</h5>

                @if($latestCommunication)
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge bg-dark">{{ data_get($latestCommunication, 'label') }}</span>
                        <span class="badge {{ $funder->getCommunicationStatusBadgeClass(data_get($latestCommunication, 'status')) }}">
                            {{ $funder->formatStatusLabel(data_get($latestCommunication, 'status')) }}
                        </span>
                    </div>

                    <div class="partner-detail-row">
                        <span>Subject</span>
                        <strong>{{ data_get($latestCommunication, 'subject', 'No subject') }}</strong>
                    </div>
                    <div class="partner-detail-row">
                        <span>When</span>
                        <strong>{{ optional(data_get($latestCommunication, 'occurred_at'))->format('d M Y H:i') ?: 'N/A' }}</strong>
                    </div>
                    <div class="partner-detail-row">
                        <span>Handled by</span>
                        <strong>{{ data_get($latestCommunication, 'owner_name', 'Not assigned') }}</strong>
                    </div>

                    <div class="partner-mini-note mt-3">
                        {!! nl2br(e(data_get($latestCommunication, 'notes', 'No supporting notes captured yet.'))) !!}
                    </div>
                @else
                    <div class="partner-empty-state">
                        No partner communication has been logged yet.
                    </div>
                @endif
            </div>

            <div class="partner-crm-card p-4">
                <h5 class="card-title mb-3">Internal Notes</h5>

                @if($funder->notes)
                    <div class="partner-mini-note">
                        {!! nl2br(e($funder->notes)) !!}
                    </div>
                @else
                    <div class="partner-empty-state">
                        No internal CRM notes have been added for this partner.
                    </div>
                @endif
            </div>
        </div>

        <div class="col-xl-8">
            <div class="partner-crm-card p-4 mb-4">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3 mb-3">
                    <div>
                        <h5 class="card-title mb-1">Funding Portfolio</h5>
                        <div class="text-muted">Programs, statuses, and portfolio totals linked to this partner.</div>
                    </div>

                    @if($statusBreakdown->isNotEmpty())
                        <div class="partner-status-pills">
                            @foreach($statusBreakdown as $status => $count)
                                <span class="pill">{{ $funder->formatStatusLabel($status) }}: {{ $count }}</span>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($funder->has_non_usd_funding)
                    <div class="alert alert-warning">
                        USD totals only include funding records already captured in USD. Other currencies are listed below for visibility.
                    </div>
                @endif

                <div class="row g-3 mb-3">
                    @foreach($funder->currency_breakdown as $currencyTotal)
                        <div class="col-md-4">
                            <div class="border rounded-4 p-3 h-100 bg-light">
                                <div class="small text-muted text-uppercase mb-1">{{ $currencyTotal['currency'] }}</div>
                                <div class="fw-bold fs-5">{{ number_format((float) $currencyTotal['amount'], 2) }}</div>
                                <div class="small text-muted">{{ $currencyTotal['count'] }} records</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Program</th>
                                <th>Department</th>
                                <th>Period</th>
                                <th>Status</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($funder->programFundings as $funding)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ optional($funding->program)->name ?: ($funding->program_name ?: 'Unnamed program') }}</div>
                                        <div class="small text-muted">{{ $funding->governanceNode?->name ?: 'No governance node linked' }}</div>
                                    </td>
                                    <td>{{ $funding->department?->name ?: 'N/A' }}</td>
                                    <td>{{ $funding->start_year ?: 'N/A' }} - {{ $funding->end_year ?: 'N/A' }}</td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            {{ $funder->formatStatusLabel($funding->status) }}
                                        </span>
                                    </td>
                                    <td class="text-end fw-semibold">
                                        {{ strtoupper($funding->currency ?: $funder->currency ?: 'USD') }}
                                        {{ number_format((float) ($funding->approved_amount ?? 0), 2) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5">
                                        <div class="partner-empty-state my-2">
                                            No program funding records are linked to this partner yet.
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="partner-crm-card p-4 mb-4">
                <h5 class="card-title mb-3">Partner Request History</h5>

                @if($funder->informationRequests->isEmpty())
                    <div class="partner-empty-state">
                        No information requests have been submitted by this partner yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Requested</th>
                                    <th>Status</th>
                                    <th>Responded By</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($funder->informationRequests->take(10) as $request)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $request->subject }}</div>
                                            <div class="small text-muted">
                                                {{ optional($request->programFunding?->program)->name ?: 'General request' }}
                                            </div>
                                        </td>
                                        <td>{{ $funder->formatStatusLabel($request->request_type) }}</td>
                                        <td>{{ optional($request->created_at)->format('d M Y H:i') ?: 'N/A' }}</td>
                                        <td>
                                            <span class="badge {{ $request->getStatusBadgeClass() }}">
                                                {{ $funder->formatStatusLabel($request->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ $request->responder?->name ?: 'Awaiting response' }}
                                            @if($request->responded_at)
                                                <div class="small text-muted">{{ $request->responded_at->format('d M Y H:i') }}</div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="partner-crm-card p-4">
                <h5 class="card-title mb-3">Recent Portal Activity</h5>

                @if($funder->activityLogs->isEmpty())
                    <div class="partner-empty-state">
                        No portal activity has been recorded for this partner yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Activity</th>
                                    <th>User</th>
                                    <th>When</th>
                                    <th>Metadata</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($funder->activityLogs as $activity)
                                    <tr>
                                        <td>{{ $activity->getActionDescription() }}</td>
                                        <td>{{ $activity->user?->name ?: 'System' }}</td>
                                        <td>{{ optional($activity->created_at)->format('d M Y H:i') ?: 'N/A' }}</td>
                                        <td class="small text-muted">
                                            @if(!empty($activity->metadata))
                                                {{ collect($activity->metadata)->map(fn ($value, $key) => \Illuminate\Support\Str::headline($key) . ': ' . $value)->implode(' | ') }}
                                            @else
                                                No extra metadata
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
