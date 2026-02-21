@extends('layouts.app')
@section('title','Indicator Definitions / Formulas')

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-file-text text-primary me-2"></i> Indicator Definitions / Formulas</h4>
            <p class="text-muted mb-0">Build and manage indicator formulas.</p>
        </div>
        <a href="{{ route('budget.me-configuration.definitions.create') }}" class="btn btn-primary btn-sm">
            <i class="feather-plus me-1"></i> New Definition
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Code</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($definitions as $def)
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $def->name }}</td>
                            <td>{{ $def->code ?? '—' }}</td>
                            <td><span class="badge {{ $def->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $def->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('budget.me-configuration.definitions.edit', $def) }}" class="btn btn-sm btn-outline-primary"><i class="feather-edit-2"></i></a>
                                    <form action="{{ route('budget.me-configuration.definitions.destroy', $def) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this definition?');">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="feather-trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No definitions found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($definitions->hasPages())
            <div class="card-footer border-0">{{ $definitions->links() }}</div>
        @endif
    </div>
</div>
@endsection
