@extends('layouts.app')

@section('title', 'Edit Vendor')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-edit text-primary me-2"></i>
                    Edit Vendor
                </h4>
                <p class="text-muted mb-0">Update vendor profile details and category.</p>
            </div>
            <a href="{{ route('vendors.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i>
                Back to Vendors
            </a>
        </div>

        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('vendors.update', $vendor) }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Vendor Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" class="form-control"
                                value="{{ old('name', $vendor->name) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" name="email" class="form-control"
                                value="{{ old('email', $vendor->email) }}" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Vendor Category</label>
                            <select name="vendor_category" class="form-control" {{ $categories->isEmpty() ? 'disabled' : '' }}>
                                <option value="">-- Select Category --</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category }}"
                                        {{ old('vendor_category', $vendor->vendor_category) === $category ? 'selected' : '' }}>
                                        {{ $category }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Used to match vendors to group procurements.</small>
                            @if ($categories->isEmpty())
                                <div class="text-danger small mt-1">No vendor categories configured yet.</div>
                            @endif
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Current Status</label>
                            <div class="d-flex align-items-center gap-2">
                                @if ($vendor->is_blacklisted)
                                    <span class="badge bg-danger">Blacklisted</span>
                                @elseif ($vendor->is_disabled)
                                    <span class="badge bg-warning text-dark">Disabled</span>
                                @else
                                    <span class="badge bg-success">Active</span>
                                @endif
                            </div>
                            <small class="text-muted">Use disable/blacklist actions from the vendor list.</small>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-light d-flex justify-content-end gap-2">
                    <a href="{{ route('vendors.index') }}" class="btn btn-light">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-1"></i>
                        Update Vendor
                    </button>
                </div>
            </div>
        </form>
    </div>
@endsection
