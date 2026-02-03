@extends('layouts.app')
@section('title', 'Financial Evaluation Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- Header --}}
            <div class="page-header d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5>Financial Evaluation</h5>
                    <small class="text-muted">
                        {{ $evaluation->applicant->think_tank_name ?? 'Unknown Applicant' }}
                        ({{ $evaluation->applicant->country ?? 'N/A' }})
                    </small>
                </div>
                <a href="{{ route('financial.index') }}" class="btn btn-secondary">← Back</a>
            </div>

            {{-- Evaluation Card --}}
            <div class="card watermark">
                <div class="card-body">

                    {{-- General Info --}}
                    <div class="mb-4">
                        <p><strong>Evaluator:</strong> {{ $evaluation->evaluator?->name ?? 'N/A' }}</p>
                        <p><strong>Status:</strong>
                            @if ($evaluation->status === 'submitted')
                                <span class="badge bg-success">Submitted</span>
                            @elseif ($evaluation->status === 'draft')
                                <span class="badge bg-warning text-dark">Draft</span>
                            @else
                                <span class="badge bg-secondary">Not Started</span>
                            @endif
                        </p>
                    </div>

                    {{-- Strengths & Gaps Table --}}
                    <h6 class="mb-3">Evaluation Breakdown</h6>
                    <table class="table table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40%">Category</th>
                                <th style="width: 30%">Strength</th>
                                <th style="width: 30%">Gap</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Financial Health</td>
                                <td>{{ $evaluation->strength_financial_health ?? '-' }}</td>
                                <td>{{ $evaluation->gap_financial_health ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Accuracy</td>
                                <td>{{ $evaluation->strength_accuracy ?? '-' }}</td>
                                <td>{{ $evaluation->gap_accuracy ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Revenue</td>
                                <td>{{ $evaluation->strength_revenue ?? '-' }}</td>
                                <td>{{ $evaluation->gap_revenue ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Fund Utilization</td>
                                <td>{{ $evaluation->strength_fund_use ?? '-' }}</td>
                                <td>{{ $evaluation->gap_fund_use ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Liabilities</td>
                                <td>{{ $evaluation->strength_liabilities ?? '-' }}</td>
                                <td>{{ $evaluation->gap_liabilities ?? '-' }}</td>
                            </tr>
                            <tr>
                                <td>Compliance</td>
                                <td>{{ $evaluation->strength_compliance ?? '-' }}</td>
                                <td>{{ $evaluation->gap_compliance ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>

                    {{-- Overall Assessment --}}
                    <div class="mt-4">
                        <h6>Overall Financial Assessment</h6>
                        <p>{!! nl2br(e($evaluation->overall_financial_assessment ?? 'No remarks provided.')) !!}</p>
                    </div>

                </div>
            </div>
        </div>
    </main>
@endsection
