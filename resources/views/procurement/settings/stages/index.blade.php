@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Procurement Stages</h4>
                <p class="text-muted mb-0">
                    Stages in the procurement lifecycle
                </p>
            </div>

            <a href="{{ route('procurement.settings.stages.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Stage
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
                <form id="stagesImportForm" action="{{ route('procurement.settings.stages.import') }}"
                    method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Import Stages (CSV/Excel)</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required>
                            @error('file')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-auto d-flex">
                            <button class="btn btn-outline-primary btn-sm me-2">Upload</button>
                            <a href="{{ route('procurement.settings.stages.template') }}"
                                class="btn btn-outline-secondary btn-sm">
                                <i class="feather-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12 text-muted small">
                            The spreadsheet must include the headers <code>stage_name</code>,
                            <code>description</code>, <code>is_active</code>, and <code>sort_order</code>.
                            Leave <code>is_active</code> blank for inactive rows or enter <strong>yes</strong>/<strong>true</strong>
                            (any case) for active ones. The sample file already uses this structure.
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="card shadow-sm">
            <div class="card-body">
                <x-data-table id="stagesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" width="60">#</th>
                            <th>Stage Name</th>
                            <th>Description</th>
                            <th class="text-center" width="100">Order</th>
                            <th class="text-center" width="100">Status</th>
                            <th class="text-center" width="120">Step Stages</th>
                            <th width="150">Created By</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($stages as $index => $stage)
                            <tr>
                                <td class="ps-4">{{ $index + 1 }}</td>
                                <td class="fw-semibold">{{ $stage->stage_name }}</td>
                                <td>
                                    <span class="text-muted">{{ Str::limit($stage->description, 50) ?? '—' }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark px-3 py-1">{{ $stage->sort_order }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $stage->is_active ? 'success' : 'secondary' }} px-3 py-1">
                                        {{ $stage->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info px-3 py-1">{{ $stage->step_stages_count ?? $stage->stepStages->count() }}</span>
                                </td>
                                <td>{{ $stage->creator->name ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('procurement.settings.stages.edit', $stage) }}"
                                            class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('procurement.settings.stages.destroy', $stage) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this stage?')">
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('stagesImportForm');
            const modalEl = document.getElementById('importLoadingModal');

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

@push('modals')
    <div class="modal fade" id="importLoadingModal" tabindex="-1" aria-labelledby="importLoadingModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3 border-0 shadow-sm">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0 fw-semibold">Uploading stages… please wait.</p>
                </div>
            </div>
        </div>
    </div>
@endpush
