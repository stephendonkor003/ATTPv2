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

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

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
                                {!! nl2br(e(strip_tags($procurement->description))) !!}
                            @else
                                <span class="text-muted">No description provided.</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

        {{-- ================= PROCUREMENT STATUS ACTIONS ================= --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Procurement Status Actions</h6>
                <span class="badge bg-light text-dark border">
                    Current: {{ ucfirst($procurement->status ?? 'draft') }}
                </span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-wrap gap-2">
                    @if ($procurement->status === 'draft')
                        <form method="POST" action="{{ route('statusProcurement.submit', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-warning"
                                onclick="return confirm('Submit this procurement for approval?')">
                                <i class="feather-send me-1"></i> Submit for Approval
                            </button>
                        </form>
                    @endif

                    @if ($procurement->status === 'rejected')
                        <form method="POST" action="{{ route('statusProcurement.submit', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-warning"
                                onclick="return confirm('Resubmit this procurement for approval?')">
                                <i class="feather-send me-1"></i> Resubmit
                            </button>
                        </form>
                    @endif

                    @if ($procurement->status === 'submitted')
                        <form method="POST" action="{{ route('statusProcurement.approve', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-success"
                                onclick="return confirm('Approve this procurement?')">
                                <i class="feather-check me-1"></i> Approve
                            </button>
                        </form>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal"
                            data-bs-target="#rejectProcurementModal">
                            <i class="feather-x me-1"></i> Reject
                        </button>
                    @endif

                    @if ($procurement->status === 'approved')
                        <form method="POST" action="{{ route('statusProcurement.publish', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-primary"
                                onclick="return confirm('Publish this procurement?')">
                                <i class="feather-globe me-1"></i> Publish
                            </button>
                        </form>
                    @endif

                    @if ($procurement->status === 'published')
                        <form method="POST" action="{{ route('statusProcurement.close', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-dark"
                                onclick="return confirm('Close this procurement?')">
                                <i class="feather-lock me-1"></i> Close
                            </button>
                        </form>
                    @endif

                    @if ($procurement->status === 'closed')
                        <form method="POST" action="{{ route('statusProcurement.award', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-success"
                                onclick="return confirm('Award this procurement? This is final.')">
                                <i class="feather-award me-1"></i> Award
                            </button>
                        </form>

                        <form method="POST" action="{{ route('statusProcurement.draft', $procurement) }}">
                            @csrf
                            <button class="btn btn-outline-secondary"
                                onclick="return confirm('Move this procurement back to draft?')">
                                <i class="feather-rotate-ccw me-1"></i> Move to Draft
                            </button>
                        </form>
                    @endif
                </div>

                @if ($procurement->status === 'rejected' && $procurement->rejection_reason)
                    <div class="alert alert-danger mt-3 mb-0">
                        <strong>Rejection reason:</strong> {{ $procurement->rejection_reason }}
                    </div>
                @endif
            </div>
        </div>

        @if ($procurement->status === 'submitted')
            <div class="modal fade" id="rejectProcurementModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <form method="POST" action="{{ route('statusProcurement.reject', $procurement) }}" class="w-100">
                        @csrf
                        <div class="modal-content border-0 shadow">
                            <div class="modal-header">
                                <h5 class="fw-bold mb-0">
                                    <i class="feather-x-circle text-danger me-2"></i>
                                    Reject Procurement
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <label class="form-label fw-semibold">
                                    Reason for rejection <span class="text-danger">*</span>
                                </label>
                                <textarea name="rejection_reason" class="form-control" rows="3" required
                                    placeholder="Enter the reason for rejection...">{{ old('rejection_reason') }}</textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" class="btn btn-danger">
                                    <i class="feather-x me-1"></i> Reject
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        @canany(['vendor.outreach.send', 'procurement.manage'])
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-semibold">Notify Vendor Groups</h6>
                    <span class="badge bg-info">Vendor Outreach</span>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('procurements.notify-vendors', $procurement) }}" class="row g-3 align-items-end">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Vendor Category</label>
                            <select name="vendor_category" class="form-control">
                                <option value="">All Vendors</option>
                                @forelse ($vendorCategories as $category)
                                    <option value="{{ $category }}">{{ $category }}</option>
                                @empty
                                    <option value="" disabled>No vendor categories configured</option>
                                @endforelse
                            </select>
                            <small class="text-muted">Choose a vendor group or send to everyone.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Message (Optional)</label>
                            <input type="text" name="message" class="form-control" maxlength="1000"
                                placeholder="Add a short note to vendors (optional)">
                        </div>
                        <div class="col-md-2 text-md-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="feather-send me-1"></i> Notify
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endcanany

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
