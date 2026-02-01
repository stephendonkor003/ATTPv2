@extends('layouts.app')

@php
    use Illuminate\Support\Str;
@endphp

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="page-title mb-1">Procurement Methods Planned</h4>
                <p class="text-muted mb-0">Capture procurement methods and the ordered milestones that make up each workflow.
                </p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('procurement.settings.method-planned.create') }}" class="btn btn-primary btn-sm">
                    <i class="feather-plus me-1"></i> New Method
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-2">{{ session('success') }}</div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger mt-2">{{ session('error') }}</div>
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
                <form id="methodImportForm" action="{{ route('procurement.settings.method-planned.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Import methods from CSV/Excel</label>
                            <input type="file" name="file" accept=".xlsx,.xls,.csv" class="form-control" required>
                            @error('file')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-auto d-flex gap-2">
                            <button class="btn btn-primary btn-sm">Upload</button>
                            <a href="{{ route('procurement.settings.method-planned.template') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="feather-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-sm-12 text-muted small">
                            <p class="mb-1 fw-semibold">Required columns</p>
                            <div class="d-flex flex-wrap gap-2">
                                <code>method_name</code>
                                <code>method_description</code>
                                <code>method_is_active</code>
                                <code>milestone_title</code>
                                <code>milestone_description</code>
                                <code>milestone_target_days</code>
                                <code>milestone_sort_order</code>
                                <code>milestone_is_active</code>
                            </div>
                            <p class="mb-0 mt-2">Use the same method_name on consecutive rows to attach multiple milestones. Leave <code>is_active</code> blank for inactive entries or set it to yes/true/1.</p>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-body px-0">
                <x-data-table id="methodsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" width="60">#</th>
                            <th>Method</th>
                            <th>Milestones</th>
                            <th class="text-center" width="120">Total Days</th>
                            <th class="text-center" width="100">Status</th>
                            <th width="150">Created By</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($methods as $index => $method)
                            <tr>
                                <td class="ps-4">{{ $index + 1 }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $method->method_name }}</div>
                                    @if ($method->description)
                                        <small class="text-muted">{{ Str::limit($method->description, 80) }}</small>
                                    @else
                                        <small class="text-muted">No description provided</small>
                                    @endif
                                </td>
                                <td>
                                    @if ($method->milestones->isEmpty())
                                        <span class="text-muted small">No milestones</span>
                                    @else
                                        <div class="d-flex flex-column gap-2">
                                            @foreach ($method->milestones as $milestone)
                                                <div class="d-flex justify-content-between gap-3"
                                                    style="font-size: .85rem;">
                                                    <div>
                                                        <strong>{{ $milestone->title }}</strong>
                                                        @if ($milestone->description)
                                                            <div class="text-muted">
                                                                {{ Str::limit($milestone->description, 60) }}</div>
                                                        @endif
                                                    </div>
                                                    <div class="text-end">
                                                        <span class="badge bg-info">{{ $milestone->target_days }}d</span>
                                                        <div class="mt-1">
                                                            <span
                                                                class="badge bg-{{ $milestone->is_active ? 'success' : 'warning' }} text-dark small">
                                                                {{ $milestone->is_active ? 'Active' : 'Inactive' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-info px-3 py-1">{{ $method->method_target_days }} days</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $method->is_active ? 'success' : 'secondary' }} px-3 py-1">
                                        {{ $method->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $method->creator->name ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('procurement.settings.method-planned.edit', $method) }}"
                                            class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('procurement.settings.method-planned.destroy', $method) }}"
                                            method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"
                                                onclick="return confirm('Are you sure you want to delete this method?')">
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
    <div class="modal fade" id="methodImportModal" tabindex="-1" aria-labelledby="methodImportModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content rounded-3 border-0 shadow-sm">
                <div class="modal-body text-center py-5">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0 fw-semibold">Uploading methods… please wait.</p>
                </div>
            </div>
        </div>
    </div>
@endpush

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('methodImportForm');
            const modalEl = document.getElementById('methodImportModal');

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
