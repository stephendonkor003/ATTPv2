@extends('layouts.app')
@section('title', 'Indicator Units')

@section('content')
    <div class="nxl-container">

        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-ruler text-primary me-2"></i>
                    Indicator Units
                </h4>
                <p class="text-muted mb-0">Manage measurement units for indicators.</p>
            </div>
            <a href="{{ route('budget.me-configuration.units.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> Add Unit
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Name</th>
                            <th>Symbol</th>
                            <th>Description</th>
                            <th>Sort</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($units as $unit)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $unit->name }}</td>
                                <td>{{ $unit->symbol ?? '?' }}</td>
                                <td>{{ $unit->description ?? '?' }}</td>
                                <td>{{ $unit->sort_order }}</td>
                                <td>
                                    <span class="badge {{ $unit->is_active ? 'bg-success' : 'bg-secondary' }}">
                                        {{ $unit->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('budget.me-configuration.units.edit', $unit) }}"
                                            class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="feather-edit-2"></i>
                                        </a>
                                        <form method="POST"
                                            action="{{ route('budget.me-configuration.units.destroy', $unit) }}"
                                            onsubmit="return confirm('Delete this unit?');" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">No indicator units found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($units->hasPages())
                <div class="card-footer border-0">
                    {{ $units->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
