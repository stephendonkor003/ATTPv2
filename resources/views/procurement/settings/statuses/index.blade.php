@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Procurement Statuses</h4>
                <p class="text-muted mb-0">
                    Status definitions for procurement tracking
                </p>
            </div>

            <a href="{{ route('procurement.settings.statuses.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Status
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
                <form id="statusImportForm" action="{{ route('procurement.settings.statuses.import') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Import Statuses (CSV/Excel)</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required>
                            @error('file')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-auto d-flex">
                            <button class="btn btn-outline-primary btn-sm me-2">Upload</button>
                            <a href="{{ route('procurement.settings.statuses.template') }}"
                                class="btn btn-outline-secondary btn-sm">
                                <i class="feather-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-muted small">
                            Required headers: <code>name</code>, <code>description</code>, <code>color</code>,
                            <code>sort_order</code>, <code>is_active</code>. Leave <code>is_active</code> blank for
                            inactive or enter <strong>yes</strong>/<strong>true</strong>.
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="statusesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" width="60">#</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th class="text-center" width="100">Color</th>
                            <th class="text-center" width="100">Order</th>
                            <th class="text-center" width="100">Status</th>
                            <th width="150">Created By</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($statuses as $index => $status)
                            <tr>
                                <td class="ps-4">{{ $index + 1 }}</td>
                                <td class="fw-semibold">{{ $status->name }}</td>
                                <td>
                                    <span class="text-muted">{{ Str::limit($status->description, 50) ?? '—' }}</span>
                                </td>
                                <td class="text-center">
                                    @if($status->color)
                                        <span class="badge px-3 py-1" style="background-color: {{ $status->color }}; color: white;">
                                            {{ $status->color }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark px-3 py-1">{{ $status->sort_order }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $status->is_active ? 'success' : 'secondary' }} px-3 py-1">
                                        {{ $status->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $status->creator->name ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('procurement.settings.statuses.edit', $status) }}"
                                            class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('procurement.settings.statuses.destroy', $status) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this status?')">
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
    <div class="modal fade" id="statusImportModal" tabindex="-1" aria-labelledby="statusImportModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3 border-0 shadow-sm">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0 fw-semibold">Uploading statuses… please wait.</p>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('statusImportForm');
            const modalEl = document.getElementById('statusImportModal');

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
