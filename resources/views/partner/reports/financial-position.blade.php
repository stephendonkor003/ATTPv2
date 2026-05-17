@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <div class="page-header">
        <div class="d-flex align-items-center justify-content-between gap-3">
            <div>
                <h4 class="fw-bold mb-1">Financial Position</h4>
                <p class="text-muted mb-0">Full program financial stage by project, activity, and sub-activity.</p>
            </div>
            <a href="{{ route('partner.reports.index') }}" class="btn btn-light btn-sm">
                <i class="feather-arrow-left me-1"></i> Reports
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm mt-3">
        <div class="card-body">
            <form method="GET" action="{{ route('partner.reports.financial-position') }}" class="row g-3 align-items-end">
                <div class="col-lg-9">
                    <label class="form-label">Program</label>
                    <select name="program_id" class="form-select">
                        @foreach ($programs as $programOption)
                            <option value="{{ $programOption->id }}" @selected((string) $selectedProgramId === (string) $programOption->id)>
                                {{ $programOption->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-lg-3">
                    <button class="btn btn-primary w-100">
                        <i class="feather-filter me-1"></i> View Position
                    </button>
                </div>
            </form>
        </div>
    </div>

    @if (! $program || ! $position)
        <div class="alert alert-info mt-3">No funded program is available for this partner account.</div>
    @else
        @php
            $currency = $position['currency'] ?? 'USD';
            $totals = $position['totals'];
        @endphp

        <div class="row g-3 mt-1">
            @foreach ([
                ['label' => 'Approved Funding', 'value' => $currency . ' ' . number_format($totals['approved_funding'] ?? 0, 2), 'icon' => 'feather-award', 'class' => 'text-success'],
                ['label' => 'Program Budget', 'value' => $currency . ' ' . number_format($totals['budget'] ?? 0, 2), 'icon' => 'feather-briefcase', 'class' => 'text-primary'],
                ['label' => 'Committed', 'value' => $currency . ' ' . number_format($totals['committed'] ?? 0, 2), 'icon' => 'feather-lock', 'class' => 'text-warning'],
                ['label' => 'Actually Disbursed', 'value' => $currency . ' ' . number_format($totals['disbursed'] ?? 0, 2), 'icon' => 'feather-send', 'class' => 'text-info'],
                ['label' => 'Remaining to Disburse', 'value' => $currency . ' ' . number_format($totals['funding_remaining'] ?? 0, 2), 'icon' => 'feather-pocket', 'class' => 'text-danger'],
            ] as $card)
                <div class="col-md-6 col-xl">
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
            <div class="card-header bg-light d-flex justify-content-between align-items-center gap-3">
                <div>
                    <h5 class="mb-0 fw-bold">{{ $program->name }}</h5>
                    <div class="small text-muted">Financial position by implementation hierarchy</div>
                </div>
                <span class="badge bg-primary-subtle text-primary">{{ $currency }}</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="min-width: 360px;">Program Structure</th>
                                <th class="text-end">Budget</th>
                                <th class="text-end">Committed</th>
                                <th class="text-end">Actually Disbursed</th>
                                <th class="text-end">Budget Balance</th>
                                <th class="text-end">Commitment Balance</th>
                                <th class="text-end">Commitment %</th>
                                <th class="text-end">Disbursement %</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($position['rows'] as $projectRow)
                                @include('partner.reports.partials.financial-position-row', ['row' => $projectRow, 'currency' => $currency, 'depth' => 0])
                                @foreach ($projectRow['children'] as $activityRow)
                                    @include('partner.reports.partials.financial-position-row', ['row' => $activityRow, 'currency' => $currency, 'depth' => 1])
                                    @foreach ($activityRow['children'] as $subRow)
                                        @include('partner.reports.partials.financial-position-row', ['row' => $subRow, 'currency' => $currency, 'depth' => 2])
                                    @endforeach
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No project hierarchy found for this program.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif
</div>
@endsection
