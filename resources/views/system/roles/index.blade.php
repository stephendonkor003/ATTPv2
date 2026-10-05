@extends('layouts.app')

@section('title', 'Roles Management')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-shield text-primary me-2"></i>
                    Roles Management
                </h4>
                <p class="text-muted mb-0">
                    Manage system roles and assign permissions
                </p>
            </div>

            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                <i class="feather-plus me-1"></i>
                Add New Role
            </button>
        </div>

        {{-- ================= FLASH MESSAGES ================= --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{-- ================= ROLES TABLE ================= --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="rolesTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Role Name</th>
                            <th>Description</th>
                            <th class="text-center">Permissions</th>
                            <th class="text-center" width="160">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($roles as $role)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $role->name }}</div>
                                    @if ($role->isReadOnlyAuditor())
                                        <span class="badge bg-info-subtle text-info mt-1">
                                            <i class="feather-eye me-1"></i>System-managed read only
                                        </span>
                                    @endif
                                </td>

                                <td class="text-muted">
                                    {{ $role->description ?? '—' }}
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-info px-3 py-1">
                                        {{ $role->permissions->count() }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <a href="{{ route('system.permissions.assign', $role->id) }}"
                                        class="btn btn-sm btn-outline-primary"
                                        title="{{ $role->isReadOnlyAuditor() ? 'View system-managed permissions' : 'Assign Permissions' }}">
                                        <i class="feather-lock"></i>
                                    </a>

                                    <a href="{{ route('system.roles.edit', $role->id) }}"
                                        class="btn btn-sm btn-outline-warning" title="Edit Role">
                                        <i class="feather-edit"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>
    </div>

    {{-- ================= CREATE ROLE MODAL ================= --}}
    <div class="modal fade" id="createRoleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content border-0 shadow">

                <form method="POST" action="{{ route('system.roles.store') }}">
                    @csrf

                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="feather-plus-circle me-1"></i>
                            Create New Role
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Role Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Administrator"
                                required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Description
                            </label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional role description"></textarea>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                            Cancel
                        </button>

                        <button type="submit" class="btn btn-primary">
                            <i class="feather-save me-1"></i>
                            Save Role
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </div>
@endsection
