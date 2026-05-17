@php
    $overview = $reportingOverview;
    $currency = $overview['currency'] ?? ($funder->currency ?? 'USD');
    $performanceRows = $overview['think_tank_performance'] ?? collect();
@endphp

<div class="row g-3">
    @foreach ([
        ['label' => 'Funds Approved', 'value' => $currency . ' ' . number_format($overview['total_approved'] ?? 0, 2), 'icon' => 'feather-dollar-sign', 'class' => 'text-success'],
        ['label' => 'Funds Remaining to Disburse', 'value' => $currency . ' ' . number_format($overview['funds_remaining'] ?? 0, 2), 'icon' => 'feather-credit-card', 'class' => 'text-primary'],
        ['label' => 'Amount Left to Allocate', 'value' => $currency . ' ' . number_format($overview['unallocated_balance'] ?? 0, 2), 'icon' => 'feather-pocket', 'class' => 'text-warning'],
        ['label' => 'Think Tanks', 'value' => number_format($overview['think_tank_count'] ?? 0), 'icon' => 'feather-users', 'class' => 'text-info'],
    ] as $card)
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center gap-3">
                        <div class="fs-2 {{ $card['class'] }}"><i class="{{ $card['icon'] }}"></i></div>
                        <div>
                            <div class="small text-muted fw-semibold">{{ $card['label'] }}</div>
                            <div class="fs-5 fw-bold">{{ $card['value'] }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>

<div class="row g-3 mt-1">
    <div class="col-lg-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">Funding Position</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Approved funding</td>
                                <td class="text-end fw-semibold">{{ $currency }} {{ number_format($overview['total_approved'] ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Allocated to think tanks and budget lines</td>
                                <td class="text-end fw-semibold">{{ $currency }} {{ number_format($overview['total_allocated'] ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Committed</td>
                                <td class="text-end fw-semibold">{{ $currency }} {{ number_format($overview['total_committed'] ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Actually disbursed / spent by ATTP</td>
                                <td class="text-end fw-semibold">{{ $currency }} {{ number_format($overview['total_disbursed'] ?? 0, 2) }}</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Reported spent by think tanks</td>
                                <td class="text-end fw-semibold">{{ $currency }} {{ number_format($overview['total_spent'] ?? 0, 2) }}</td>
                            </tr>
                            <tr class="table-light">
                                <td class="fw-bold">Funds remaining to disburse</td>
                                <td class="text-end fw-bold">{{ $currency }} {{ number_format($overview['funds_remaining'] ?? 0, 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">Secretariat Reporting</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Consortia</span>
                    <strong>{{ number_format($overview['consortium_count'] ?? 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Think tanks</span>
                    <strong>{{ number_format($overview['think_tank_count'] ?? 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Reports submitted</span>
                    <strong>{{ number_format($overview['reports_submitted'] ?? 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2 border-bottom">
                    <span class="text-muted">Awaiting Secretariat review</span>
                    <strong>{{ number_format($overview['reports_pending_secretariat'] ?? 0) }}</strong>
                </div>
                <div class="d-flex justify-content-between py-2">
                    <span class="text-muted">Average progress</span>
                    <strong>{{ number_format($overview['average_progress'] ?? 0, 1) }}%</strong>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mt-3">
    <div class="card-header bg-light">
        <h5 class="mb-0 fw-bold">Think Tank Performance</h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Think Tank</th>
                        <th>Consortium</th>
                        <th class="text-end">Allocated</th>
                        <th class="text-end">Paid to Think Tank</th>
                        <th class="text-end">Reported Spent</th>
                        <th class="text-end">In Custody</th>
                        <th class="text-end">Remaining to Disburse</th>
                        <th class="text-center">Submitted</th>
                        <th class="text-center">Approved</th>
                        <th class="text-center">Pending Review</th>
                        <th class="text-end">Progress</th>
                        <th>Last Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($performanceRows as $row)
                        <tr>
                            <td>
                                <strong>{{ $row['name'] }}</strong>
                                @if ($row['country'])
                                    <div class="small text-muted">{{ $row['country'] }}</div>
                                @endif
                            </td>
                            <td>{{ $row['consortium'] ?? '-' }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format($row['allocated'], 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format($row['disbursed'], 2) }}</td>
                            <td class="text-end">{{ $currency }} {{ number_format($row['spent'], 2) }}</td>
                            <td class="text-end fw-semibold">{{ $currency }} {{ number_format($row['custody_remaining'], 2) }}</td>
                            <td class="text-end fw-semibold">{{ $currency }} {{ number_format($row['remaining'], 2) }}</td>
                            <td class="text-center">{{ number_format($row['submitted_reports']) }}/{{ number_format($row['total_reports']) }}</td>
                            <td class="text-center">{{ number_format($row['approved_reports']) }}</td>
                            <td class="text-center">{{ number_format($row['pending_secretariat_reports']) }}</td>
                            <td class="text-end">{{ number_format($row['average_progress'], 1) }}%</td>
                            <td>{{ $row['last_submitted_at'] ? $row['last_submitted_at']->format('M d, Y') : '-' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">
                                No think tank reporting data is linked to this funding partner yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
