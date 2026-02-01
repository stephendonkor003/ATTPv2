@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Step Approval Processes</h4>
                <p class="text-muted mb-0">
                    Approval processes linked to governance structure
                </p>
            </div>

            <a href="{{ route('procurement.settings.step-approvals.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Approval Process
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-2">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger mt-2">
                {{ session('error') }}
            </div>
        @endif

        @if (session('import_errors'))
            <div class="alert alert-warning mt-2">
                <p class="mb-2 fw-semibold">Import issues</p>
                <ul class="mb-0">
                    @foreach (session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="card border border-secondary-subtle bg-light mb-3">
            <div class="card-body py-3">
                <form id="stepApprovalImportForm" action="{{ route('procurement.settings.step-approvals.import') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Import Step Approvals (CSV/Excel)</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required>
                            @error('file')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-auto d-flex">
                            <button class="btn btn-outline-primary btn-sm me-2">Upload</button>
                            <a href="{{ route('procurement.settings.step-approvals.template') }}"
                                class="btn btn-outline-secondary btn-sm">
                                <i class="feather-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-muted small">
                            Required headers: <code>name</code>, <code>step_stage</code>, <code>governance_node</code>,
                            <code>description</code>, <code>approval_order</code>, <code>is_required</code>, <code>is_active</code>.
                            Use <strong>yes</strong>/<strong>true</strong> for true flags.
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="stepApprovalsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" width="60">#</th>
                            <th>Name</th>
                            <th>Step Stage</th>
                            <th>Governance Node</th>
                            <th class="text-center" width="80">Order</th>
                            <th class="text-center" width="90">Required</th>
                            <th class="text-center" width="90">Status</th>
                            <th width="130">Created By</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($approvals as $index => $approval)
                            <tr>
                                <td class="ps-4">{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $approval->name }}</div>
                                    @if($approval->description)
                                        <small class="text-muted">{{ Str::limit($approval->description, 35) }}</small>
                                    @endif
                                </td>
                                <td>
                                    @if($approval->stepStage)
                                        <span class="badge bg-info px-2 py-1">{{ $approval->stepStage->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if($approval->governanceNode)
                                        <span class="badge bg-primary px-2 py-1">{{ $approval->governanceNode->name }}</span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark px-3 py-1">{{ $approval->approval_order }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $approval->is_required ? 'warning text-dark' : 'light text-dark' }} px-2 py-1">
                                        {{ $approval->is_required ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $approval->is_active ? 'success' : 'secondary' }} px-2 py-1">
                                        {{ $approval->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $approval->creator->name ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('procurement.settings.step-approvals.edit', $approval) }}"
                                            class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('procurement.settings.step-approvals.destroy', $approval) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this approval process?')">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>
@endsection

@push('modals')
    <div class="modal fade" id="stepApprovalImportModal" tabindex="-1"
        aria-labelledby="stepApprovalImportModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3 border-0 shadow-sm">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0 fw-semibold">Uploading step approvals… please wait.</p>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('stepApprovalImportForm');
            const modalEl = document.getElementById('stepApprovalImportModal');

            if (!form || !modalEl || typeof bootstrap === 'undefined') {
                return;
            }

            const modal = new bootstrap.Modal(modalEl, {
                backdrop: 'static',
                keyboard: false,
            });

            form.addEventListener('submit', () => {
                modal.show();
            });

            modalEl.addEventListener('shown.bs.modal', () => {
                document.body.classList.add('modal-open');
            });

            modalEl.addEventListener('hidden.bs.modal', () => {
                document.body.classList.remove('modal-open');
            });
        });
    </script>
@endpush
