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
                <h4 class="fw-bold mb-1">Data Warehouse</h4>
                <p class="text-muted mb-0">View historical data records and their uploaded files.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('data-warehouse.categories') }}" class="btn btn-outline-secondary">
                    <i class="feather-folder me-1"></i> Data Category
                </a>
                <a href="{{ route('data-warehouse.create') }}" class="btn btn-primary">
                    <i class="feather-upload me-1"></i> Add Historical Data
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><div class="text-muted small">Records</div><div class="h4 fw-bold mb-0">{{ number_format($stats['records']) }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><div class="text-muted small">Files</div><div class="h4 fw-bold mb-0">{{ number_format($stats['files']) }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><div class="text-muted small">Categories</div><div class="h4 fw-bold mb-0">{{ number_format($stats['categories']) }}</div></div></div>
            </div>
            <div class="col-md-3">
                <div class="card h-100"><div class="card-body"><div class="text-muted small">Stored Size</div><div class="h4 fw-bold mb-0">{{ $formatBytes($stats['size']) }}</div></div></div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <x-data-table id="dataWarehouseTable">
                    <thead class="table-light">
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Period</th>
                            <th>Source</th>
                            <th>Files</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($records as $record)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $record->title }}</div>
                                    <small class="text-muted">{{ \Illuminate\Support\Str::limit($record->description, 80) }}</small>
                                </td>
                                <td>{{ $record->category?->name ?? 'Uncategorized' }}</td>
                                <td>{{ $record->reference_period ?? 'N/A' }}</td>
                                <td>{{ $record->source_name ?? 'N/A' }}</td>
                                <td>{{ $record->files->count() }}</td>
                                <td><span class="badge bg-secondary text-capitalize">{{ $record->status }}</span></td>
                                <td>{{ $record->created_at?->format('d M Y') ?? 'N/A' }}</td>
                                <td class="text-end">
                                    <a href="{{ route('data-warehouse.show', $record) }}" class="btn btn-sm btn-outline-primary">
                                        View Info
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>

                <div class="mt-3">
                    {{ $records->links() }}
                </div>
            </div>
        </div>
    </div>
@endsection
