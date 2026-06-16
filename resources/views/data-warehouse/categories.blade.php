@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Knowledge Categories</h4>
                <p class="text-muted mb-0">Create and manage categories used for knowledge records.</p>
            </div>
            <a href="{{ route('data-warehouse.index') }}" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i> File Library
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="fw-bold mb-3">Add Category</h5>
                        <form method="POST" action="{{ route('data-warehouse.categories.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Name</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Code</label>
                                <input type="text" name="code" class="form-control" value="{{ old('code') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="feather-save me-1"></i> Save Category
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <x-data-table id="dataWarehouseCategoriesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Name</th>
                                    <th>Code</th>
                                    <th>Records</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($categories as $category)
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $category->name }}</div>
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($category->description, 90) }}</small>
                                        </td>
                                        <td>{{ $category->code ?? 'N/A' }}</td>
                                        <td>{{ number_format($category->records_count) }}</td>
                                        <td><span class="badge bg-secondary text-capitalize">{{ $category->status }}</span></td>
                                        <td>{{ $category->created_at?->format('d M Y') ?? 'N/A' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-data-table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
