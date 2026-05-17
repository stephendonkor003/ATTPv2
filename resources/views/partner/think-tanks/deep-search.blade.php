@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <h4 class="fw-bold mb-1">Think Tank Deep Search</h4>
                <p class="text-muted mb-0">Select a funded think tank and view its funding, disbursement, workplan, and report record.</p>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <form method="GET" action="{{ route('partner.think-tanks.deep-search') }}" class="row g-3 align-items-end">
                <div class="col-lg-9">
                    <label class="form-label">Think Tank</label>
                    <select name="think_tank_id" class="form-select" required>
                        <option value="">Select think tank</option>
                        @foreach ($thinkTanks as $thinkTank)
                            <option value="{{ $thinkTank->id }}" @selected(request('think_tank_id') === (string) $thinkTank->id)>
                                {{ $thinkTank->name }} @if ($thinkTank->consortium) - {{ $thinkTank->consortium->name }} @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="feather-search me-1"></i> Get Info
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if (! $selectedThinkTank)
        <div class="alert alert-info mt-3">
            Choose a think tank from the dropdown to see its full partner-funded record.
        </div>
    @else
        @php
            $currency = $deepSearch['currency'];
        @endphp

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-body">
                <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                    <div>
                        <h5 class="fw-bold mb-1">{{ $selectedThinkTank->name }}</h5>
                        <div class="text-muted">
                            {{ $selectedThinkTank->country ?? 'Country not set' }}
                            @if ($selectedThinkTank->consortium)
                                <span class="mx-2">|</span>{{ $selectedThinkTank->consortium->name }}
                            @endif
                        </div>
                    </div>
                    <div class="text-lg-end">
                        <span class="badge bg-primary-subtle text-primary text-capitalize">{{ $selectedThinkTank->status }}</span>
                        <div class="small text-muted mt-1">{{ $selectedThinkTank->email ?? 'No email recorded' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            @foreach ([
                ['label' => 'Allocated', 'value' => $currency . ' ' . number_format($deepSearch['allocated'], 2), 'icon' => 'feather-pocket', 'class' => 'text-primary'],
                ['label' => 'Paid to Think Tank', 'value' => $currency . ' ' . number_format($deepSearch['disbursed'], 2), 'icon' => 'feather-send', 'class' => 'text-success'],
                ['label' => 'Reported Spent', 'value' => $currency . ' ' . number_format($deepSearch['spent'], 2), 'icon' => 'feather-credit-card', 'class' => 'text-warning'],
                ['label' => 'In Custody', 'value' => $currency . ' ' . number_format($deepSearch['custody_remaining'], 2), 'icon' => 'feather-archive', 'class' => 'text-info'],
                ['label' => 'Remaining to Disburse', 'value' => $currency . ' ' . number_format($deepSearch['remaining'], 2), 'icon' => 'feather-repeat', 'class' => 'text-secondary'],
                ['label' => 'Reports Submitted', 'value' => number_format($deepSearch['reports_submitted']), 'icon' => 'feather-file-text', 'class' => 'text-danger'],
            ] as $card)
                <div class="col-md-6 col-xl-2">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="fs-3 {{ $card['class'] }}"><i class="{{ $card['icon'] }}"></i></div>
                            <div class="small text-muted fw-semibold mt-2">{{ $card['label'] }}</div>
                            <div class="fw-bold">{{ $card['value'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">Disbursements Sent To Think Tank</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Purchase Order</th>
                                <th>Status</th>
                                <th>Payment Method</th>
                                <th class="text-end">Amount Paid</th>
                                <th>Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deepSearch['actual_payments'] as $disbursement)
                                <tr>
                                    <td><strong>{{ $disbursement->reference_no ?? 'N/A' }}</strong></td>
                                    <td>{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ str_replace('_', ' ', $disbursement->status) }}</span></td>
                                    <td>{{ $disbursement->payment_method ?? '-' }}</td>
                                    <td class="text-end fw-semibold">{{ $disbursement->currency }} {{ number_format($disbursement->amount, 2) }}</td>
                                    <td>{{ $disbursement->paid_at?->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No actual payment/disbursement record found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">How Funds Were Sent Out / Spent</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Expense Code</th>
                                <th>Description</th>
                                <th>Vendor</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deepSearch['expenses'] as $expense)
                                <tr>
                                    <td><strong>{{ $expense->expense_code }}</strong></td>
                                    <td>{{ $expense->description }}</td>
                                    <td>{{ $expense->vendor_name ?: '-' }}</td>
                                    <td class="text-end fw-semibold">{{ $expense->currency }} {{ number_format($expense->amount, 2) }}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ str_replace('_', ' ', $expense->status) }}</span></td>
                                    <td>{{ $expense->submitted_at?->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No expense report found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">Work Plans</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Period</th>
                                <th class="text-end">Planned Budget</th>
                                <th>Status</th>
                                <th class="text-center">Reports Linked</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deepSearch['workplans'] as $workplan)
                                <tr>
                                    <td><strong>{{ $workplan->title }}</strong></td>
                                    <td>{{ $workplan->period_label ?: (($workplan->starts_on?->format('M d, Y') ?? '-') . ' - ' . ($workplan->ends_on?->format('M d, Y') ?? '-')) }}</td>
                                    <td class="text-end">{{ $currency }} {{ number_format($workplan->planned_budget, 2) }}</td>
                                    <td><span class="badge bg-primary-subtle text-primary text-capitalize">{{ str_replace('_', ' ', $workplan->status) }}</span></td>
                                    <td class="text-center">{{ number_format($workplan->reports->where('think_tank_member_id', $selectedThinkTank->id)->count()) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No workplan found for this think tank consortium.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mt-3">
            <div class="card-header bg-light">
                <h5 class="mb-0 fw-bold">Reports Submitted To ATTP Secretariat</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Report</th>
                                <th>Workplan</th>
                                <th>Period</th>
                                <th class="text-end">Progress</th>
                                <th class="text-end">Funds Spent</th>
                                <th>Status</th>
                                <th>Submitted</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($deepSearch['reports'] as $report)
                                <tr>
                                    <td><strong>{{ $report->title }}</strong></td>
                                    <td>{{ $report->workplan?->title ?? '-' }}</td>
                                    <td>{{ $report->reporting_period_start?->format('M d, Y') ?? '-' }} - {{ $report->reporting_period_end?->format('M d, Y') ?? '-' }}</td>
                                    <td class="text-end">{{ number_format($report->progress_percent, 1) }}%</td>
                                    <td class="text-end">{{ $currency }} {{ number_format($report->funds_spent, 2) }}</td>
                                    <td><span class="badge bg-secondary-subtle text-secondary text-capitalize">{{ str_replace('_', ' ', $report->status) }}</span></td>
                                    <td>{{ $report->submitted_at?->format('M d, Y') ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center text-muted py-4">No report submitted by this think tank yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
