@extends('layouts.app')

@php
    use Carbon\Carbon;
    use Illuminate\Support\Str;
@endphp

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">{{ $programPlan->name }} - Procurement Sheet</h4>
                <p class="text-muted mb-0">This view lists every procurement attached to the program plan in an Excel-style layout.</p>
            </div>
            <a href="{{ route('procurement.plans.sheet') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back to Program Plans
            </a>
        </div>

        <div class="card shadow-sm">
            <div class="card-body">
                <div class="table-responsive">
                    <x-data-table id="programPlanSheetTable">
                        <thead class="table-light">
                            <tr>
                                <th>PR #</th>
                                <th>Item Name</th>
                                <th>Item Description</th>
                                <th>Method Planned</th>
                                <th>Geographic Location</th>
                                <th>Estimated Value (USD)</th>
                                <th class="text-center">Launched</th>
                                <th class="text-center">Estimated Start Date</th>
                                <th class="text-center">If Not Launched (days delay)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($plans as $plan)
                                @php
                                    $delayDays = null;
                                    if (!$plan->is_launched && $plan->estimated_end_date) {
                                        $delta = Carbon::now()->diffInDays($plan->estimated_end_date, false);
                                        $delayDays = $delta > 0 ? $delta : 0;
                                    }
                                @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $plan->procurement_code }}</td>
                                    <td>{{ $plan->title }}</td>
                                    <td>{{ Str::limit($plan->description ?? '—', 120) }}</td>
                                    <td>{{ $plan->methodPlanned->method_name ?? '—' }}</td>
                                    <td>{{ $plan->geographic->name ?? '—' }}</td>
                                    <td>${{ number_format($plan->estimated_budget ?? 0, 2) }}</td>
                                    <td class="text-center">
                                        @if($plan->is_launched)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-warning text-dark">No</span>
                                        @endif
                                    </td>
                                    <td class="text-center">{{ $plan->estimated_start_date?->format('M d, Y') ?? '—' }}</td>
                                    <td class="text-center">
                                        @if(is_null($delayDays))
                                            <span class="text-muted">—</span>
                                        @elseif($delayDays === 0)
                                            <span class="badge bg-success text-dark">0</span>
                                        @else
                                            <span class="badge bg-danger">{{ $delayDays }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">
                                        No procurements yet attached to this plan.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-data-table>
                </div>
            </div>
        </div>
    </div>
@endsection
