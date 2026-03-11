@extends('layouts.app')

@push('styles')
    @include('finance.funders.partials.crm-styles')
@endpush

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Partner Profile</h4>
                <p class="text-muted mb-0">A full CRM-style view of the partner relationship, funding portfolio, and communication history.</p>
            </div>

            <div class="d-flex flex-wrap gap-2">
                <a href="{{ route('finance.funders.index') }}" class="btn btn-light">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
                <a href="{{ route('finance.funders.pdf', $funder) }}" class="btn btn-primary">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
            </div>
        </div>

        <div class="mt-4">
            @include('finance.funders.partials.crm-body', ['funder' => $funder])
        </div>
    </div>
@endsection
