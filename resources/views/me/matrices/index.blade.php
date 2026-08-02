@extends('layouts.app')

@section('title', 'M&E Matrix Manager')

@section('content')
<div class="container-fluid">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
        <div>
            <div class="text-muted small fw-semibold text-uppercase mb-1">Monitoring &amp; Evaluation</div>
            <h3 class="mb-1">M&amp;E Matrix Manager</h3>
            <p class="text-muted mb-0">Upload, inspect, activate and retain controlled versions of the ATTP M&amp;E Matrix.</p>
        </div>
        <a href="{{ route('budget.me.rebuild.knowledge-repository') }}" class="btn btn-outline-primary"><i class="feather-book-open me-1"></i> Knowledge Repository</a>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><div class="fw-semibold">The matrix was not saved.</div><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @can('me.configuration.manage')
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3"><h5 class="mb-0">Upload a controlled matrix version</h5></div>
            <div class="card-body">
                <div class="alert alert-info small">Uploading does not overwrite indicators or results. The workbook is inspected, stored in the repository, and remains a draft until an authorized M&amp;E officer activates it.</div>
                <form method="POST" action="{{ route('budget.me.matrices.store') }}" enctype="multipart/form-data" class="row g-3">
                    @csrf
                    <div class="col-lg-4">
                        <label class="form-label">Portfolio <span class="text-danger">*</span></label>
                        <select name="portfolio_id" class="form-select" required><option value="">Select portfolio</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected(old('portfolio_id') == $portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-lg-5"><label class="form-label">Document title <span class="text-danger">*</span></label><input name="title" value="{{ old('title', 'ATTP Unified Indicator Performance Tracking Matrix') }}" class="form-control" required maxlength="255"></div>
                    <div class="col-lg-3"><label class="form-label">Matrix code <span class="text-danger">*</span></label><input name="matrix_code" value="{{ old('matrix_code', 'ATTP-MEL-MATRIX') }}" class="form-control text-uppercase" required maxlength="80"></div>
                    <div class="col-lg-2"><label class="form-label">Version</label><input type="number" name="version_number" value="{{ old('version_number') }}" min="1" max="9999" class="form-control" placeholder="Auto"></div>
                    <div class="col-lg-3"><label class="form-label">Effective from</label><input type="date" name="effective_from" value="{{ old('effective_from') }}" class="form-control"></div>
                    <div class="col-lg-3"><label class="form-label">Effective to</label><input type="date" name="effective_to" value="{{ old('effective_to') }}" class="form-control"></div>
                    <div class="col-lg-4"><label class="form-label">Matrix file <span class="text-danger">*</span></label><input type="file" name="matrix_file" class="form-control" accept=".xlsx,.xls,.csv,.pdf" required><div class="form-text">XLSX, XLS, CSV or PDF; maximum 30 MB.</div></div>
                    <div class="col-12"><label class="form-label">Change summary <span class="text-danger">*</span></label><textarea name="change_summary" class="form-control" rows="2" required maxlength="5000" placeholder="State what was added, corrected or approved in this version.">{{ old('change_summary') }}</textarea></div>
                    <div class="col-12 text-end"><button class="btn btn-primary"><i class="feather-upload me-1"></i> Upload draft matrix</button></div>
                </form>
            </div>
        </div>
    @endcan

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Matrix version register</h5></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light"><tr><th>Matrix</th><th>Portfolio</th><th>Version / status</th><th>Workbook inspection</th><th>Effective dates</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                @forelse($matrices as $matrix)
                    <tr>
                        <td><div class="fw-semibold">{{ $matrix->title }}</div><div class="text-muted small">{{ $matrix->matrix_code }}</div><div class="small mt-1">{{ $matrix->change_summary }}</div></td>
                        <td>{{ $matrix->portfolio?->name ?: 'All ATTP' }}</td>
                        <td><div class="fw-semibold">Version {{ $matrix->version_number }}</div><span class="badge {{ $matrix->status === 'active' ? 'bg-success' : ($matrix->status === 'retired' ? 'bg-secondary' : 'bg-warning text-dark') }}">{{ $statuses[$matrix->status] ?? ucfirst($matrix->status) }}</span></td>
                        <td>
                            @php($summary = $matrix->import_summary ?: [])
                            <div>{{ $summary['format'] ?? 'File' }}@if(isset($summary['sheet_count'])) &middot; {{ $summary['sheet_count'] }} sheet(s)@endif</div>
                            @if(!empty($summary['sheets']))
                                <details class="small"><summary class="text-primary">View structure</summary><ul class="mb-0 ps-3">@foreach($summary['sheets'] as $sheet)<li>{{ $sheet['name'] }}: {{ $sheet['data_rows'] }} rows, {{ $sheet['data_columns'] }} columns, {{ $sheet['formula_cells'] }} formula cells, {{ $sheet['validated_cells'] }} validated cells</li>@endforeach</ul></details>
                            @elseif(!empty($summary['message']))<div class="text-muted small">{{ $summary['message'] }}</div>@endif
                        </td>
                        <td>{{ $matrix->effective_from?->format('Y-m-d') ?: 'Not set' }} &ndash; {{ $matrix->effective_to?->format('Y-m-d') ?: 'Open' }}</td>
                        <td><div class="d-flex justify-content-end gap-2 flex-wrap">
                            <a href="{{ route('budget.me.matrices.download', $matrix) }}" class="btn btn-sm btn-light border"><i class="feather-download me-1"></i> Download</a>
                            @can('me.configuration.manage')
                                @if($matrix->status === 'draft')
                                    <form method="POST" action="{{ route('budget.me.matrices.activate', $matrix) }}" onsubmit="return confirm('Activate this matrix and retire the currently active version with the same code?');">@csrf<button class="btn btn-sm btn-success"><i class="feather-check-circle me-1"></i> Activate</button></form>
                                    <form method="POST" action="{{ route('budget.me.matrices.destroy', $matrix) }}" onsubmit="return confirm('Delete this draft matrix version?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i class="feather-trash-2"></i></button></form>
                                @elseif($matrix->status === 'active')
                                    <form method="POST" action="{{ route('budget.me.matrices.retire', $matrix) }}" onsubmit="return confirm('Retire this active matrix version?');">@csrf<button class="btn btn-sm btn-outline-secondary">Retire</button></form>
                                @endif
                            @endcan
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">No M&amp;E Matrix has been uploaded.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($matrices->hasPages())<div class="card-footer bg-white">{{ $matrices->links() }}</div>@endif
    </div>
</div>
@endsection
