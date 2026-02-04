@extends('layouts.app')

@section('title', 'Vendor Categories')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-tag text-primary me-2"></i>
                    Vendor Categories
                </h4>
                <p class="text-muted mb-0">Create and manage vendor categories for procurement access.</p>
            </div>
            <a href="{{ route('vendors.categories.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Category
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="vendorCategoriesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Category</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-center" width="140">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($categories as $category)
                            <tr>
                                <td class="ps-4 fw-semibold">{{ $category->name }}</td>
                                <td>{{ $category->description ?? '—' }}</td>
                                <td>
                                    <span class="badge bg-{{ $category->is_active ? 'success' : 'secondary' }}">
                                        {{ $category->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td>{{ $category->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('vendors.categories.edit', $category) }}"
                                            class="btn btn-sm btn-outline-warning" title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('vendors.categories.destroy', $category) }}"
                                            method="POST" class="d-inline"
                                            onsubmit="return confirm('Delete this category?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" title="Delete">
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
