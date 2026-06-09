@extends('layouts.app')

@section('content')
    @php
        $formatBytes = function ($bytes) {
            $bytes = (float) $bytes;
            foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
                if ($bytes < 1024 || $unit === 'GB') {
                    return number_format($bytes, $unit === 'B' ? 0 : 2) . ' ' . $unit;
                }
                $bytes /= 1024;
            }
        };
    @endphp

    <div class="nxl-container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">{{ $record->title }}</h4>
                <p class="text-muted mb-0">{{ $record->category?->name ?? 'Uncategorized' }} | {{ $record->reference_period ?? 'No period set' }}</p>
            </div>
            <a href="{{ route('data-warehouse.index') }}" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i> View Info
            </a>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Record Information</h5>
                        <div class="mb-3">
                            <div class="text-muted small">Source</div>
                            <div class="fw-semibold">{{ $record->source_name ?? 'N/A' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Data Owner</div>
                            <div class="fw-semibold">{{ $record->data_owner ?? 'N/A' }}</div>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Status</div>
                            <span class="badge bg-secondary text-capitalize">{{ $record->status }}</span>
                        </div>
                        <div class="mb-3">
                            <div class="text-muted small">Tags</div>
                            @forelse ($record->tags ?? [] as $tag)
                                <span class="badge bg-light text-dark border">{{ $tag }}</span>
                            @empty
                                <span class="text-muted">N/A</span>
                            @endforelse
                        </div>
                        <div>
                            <div class="text-muted small">Description</div>
                            <div>{{ $record->description ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Uploaded Files</h5>
                        <div class="table-responsive">
                            <table class="table align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Title</th>
                                        <th>Original File</th>
                                        <th>Size</th>
                                        <th>Uploaded By</th>
                                        <th class="text-end">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($record->files as $file)
                                        <tr>
                                            <td>
                                                <div class="fw-semibold">{{ $file->title ?? $file->original_name }}</div>
                                                <small class="text-muted">{{ $file->description }}</small>
                                            </td>
                                            <td>{{ $file->original_name }}</td>
                                            <td>{{ $formatBytes($file->size) }}</td>
                                            <td>{{ $file->uploader?->name ?? 'N/A' }}</td>
                                            <td class="text-end">
                                                <a href="{{ route('data-warehouse.files.download', $file) }}" class="btn btn-sm btn-outline-primary">
                                                    <i class="feather-download me-1"></i> Download
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
