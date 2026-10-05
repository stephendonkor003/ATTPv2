@extends('layouts.app')

@section('title', 'Edit Role')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-4">
            <h4 class="fw-bold">
                <i class="feather-edit me-2"></i>
                Edit Role
            </h4>
            <p class="text-muted">
                Update role information
            </p>
        </div>

        <form method="POST" action="{{ route('system.roles.update', $role->id) }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm border-0">
                <div class="card-body">

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Role Name
                        </label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $role->name) }}"
                            {{ $role->isReadOnlyAuditor() ? 'readonly' : '' }} required>
                        @if ($role->isReadOnlyAuditor())
                            <div class="form-text text-info">
                                Auditor is the canonical system-managed read-only role and cannot be renamed.
                            </div>
                        @endif
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Description
                        </label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description', $role->description) }}</textarea>
                    </div>

                    <div class="d-flex justify-content-end">
                        <a href="{{ route('system.roles.index') }}" class="btn btn-light me-2">
                            Cancel
                        </a>
                        <button class="btn btn-primary">
                            <i class="feather-save me-1"></i>
                            Update Role
                        </button>
                    </div>

                </div>
            </div>
        </form>
    </div>
@endsection
