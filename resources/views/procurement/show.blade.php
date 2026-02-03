@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="page-title mb-1">Procurement Details</h4>
                <p class="text-muted mb-0">
                    View procurement information, workflow status, and configuration
                </p>
            </div>

            <a href="{{ route('procurements.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back to List
            </a>
        </div>

        {{-- ================= PROCUREMENT INFO ================= --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Procurement Information</h6>

                {{-- STATUS BADGE --}}
                <span
                    class="badge fs-6
                @if ($procurement->status === 'approved') bg-success
                @elseif($procurement->status === 'published') bg-primary
                @elseif($procurement->status === 'closed') bg-dark
                @elseif($procurement->status === 'awarded') bg-success
                @elseif($procurement->status === 'submitted') bg-warning text-dark
                @else bg-secondary @endif">
                    {{ ucfirst($procurement->status ?? 'draft') }}
                </span>
            </div>

            <div class="card-body">
                <div class="row g-4">

                    <div class="col-md-4">
                        <div class="text-muted small">Reference Number</div>
                        <div class="fw-semibold">
                            {{ $procurement->reference_no ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="text-muted small">Procurement Title</div>
                        <div class="fw-semibold fs-6">
                            {{ $procurement->title }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Category</div>
                        <div>
                            {{ $procurement->resource->name ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Fiscal Year</div>
                        <div>
                            {{ $procurement->fiscal_year ?? '—' }}
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="text-muted small">Estimated Budget</div>
                        <div>
                            {{ $procurement->estimated_budget ? number_format($procurement->estimated_budget, 2) : '—' }}
                        </div>
                    </div>

                </div>

                {{-- DESCRIPTION --}}
                <div class="mt-4">
                    <div class="text-muted small mb-1">Procurement Description</div>

                    <div class="border rounded p-3 bg-light" style="line-height:1.75;">
                        @if ($procurement->description)
                            {!! nl2br(e($procurement->description)) !!}
                        @else
                            <span class="text-muted">No description provided.</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= PROCUREMENT WORKFLOW ================= --}}


        {{-- ================= PRESCREENING CONFIGURATION ================= --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Prescreening Template (criteria) Configuration</h6>

                @if ($procurement->prescreeningTemplate)
                    <span class="badge bg-success">
                        Template Assigned
                    </span>
                @else
                    <span class="badge bg-secondary">
                        Not Configured
                    </span>
                @endif
            </div>

            <div class="card-body">

                @if ($procurement->prescreeningTemplate)
                    <p class="mb-2">
                        <strong>Template:</strong>
                        {{ $procurement->prescreeningTemplate->name }}
                    </p>

                    <p class="text-muted mb-3">
                        {{ $procurement->prescreeningTemplate->criteria->count() }}
                        criteria will be used during prescreening.
                    </p>
                @else
                    <p class="text-muted mb-3">
                        No prescreening template has been assigned to this procurement.
                    </p>
                @endif

                <a href="{{ route('procurements.prescreening.edit', $procurement) }}" class="btn btn-outline-primary"
                    {{ $procurement->submissions()->exists() ? 'disabled' : '' }}>
                    <i class="feather-check-square me-1"></i>
                    {{ $procurement->prescreeningTemplate ? 'View / Change Template' : 'Assign Prescreening Template' }}
                </a>

                @if ($procurement->submissions()->exists())
                    <div class="text-danger small mt-2">
                        Prescreening configuration is locked because submissions already exist.
                    </div>
                @endif

            </div>
        </div>

        {{-- ================= ATTACHED FORMS ================= --}}
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Attached Forms</h6>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-secondary">
                        {{ $procurement->forms->count() }} Forms
                    </span>
                    @can('forms.manage')
                        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="collapse"
                            data-bs-target="#attachFormDrawer" aria-expanded="false" aria-controls="attachFormDrawer">
                            <i class="feather-link me-1"></i> Attach Form
                        </button>
                    @endcan
                </div>
            </div>

            <div class="card-body p-0">
                @can('forms.manage')
                    <div class="collapse" id="attachFormDrawer">
                        <div class="p-3 border-bottom bg-light">
                            <form method="POST" action="{{ route('attach-form') }}" class="row g-3 align-items-end">
                                @csrf
                                <input type="hidden" name="procurement_id" value="{{ $procurement->id }}">

                                <div class="col-md-8">
                                    <label class="form-label fw-semibold">Select Approved Form</label>
                                    <select name="form_id" class="form-control" required>
                                        <option value="">-- Select Approved Form --</option>
                                        @foreach ($availableForms as $form)
                                            <option value="{{ $form->id }}">
                                                {{ $form->name }} ({{ ucfirst($form->applies_to) }})
                                            </option>
                                        @endforeach
                                    </select>
                                    @if ($availableForms->isEmpty())
                                        <div class="small text-danger mt-1">
                                            No approved, unassigned forms are available.
                                        </div>
                                    @endif
                                </div>

                                <div class="col-md-4 text-md-end">
                                    <button type="submit" class="btn btn-success"
                                        {{ $availableForms->isEmpty() ? 'disabled' : '' }}>
                                        <i class="feather-link me-1"></i>
                                        Attach Form
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endcan

                <table class="table table-hover table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Form</th>
                            <th>Stage</th>
                            <th>Status</th>
                            <th width="180" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($procurement->forms as $form)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $form->name }}</div>
                                    <small class="text-muted">
                                        {{ $form->resource->name ?? '—' }}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-info">
                                        {{ ucfirst($form->applies_to) }}
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="badge
                                    @if ($form->status === 'approved') bg-success
                                    @elseif($form->status === 'submitted') bg-warning text-dark
                                    @elseif($form->status === 'rejected') bg-danger
                                    @else bg-secondary @endif">
                                        {{ ucfirst($form->status) }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('forms.edit', $form) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="feather-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    No forms attached to this procurement yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
@endsection
