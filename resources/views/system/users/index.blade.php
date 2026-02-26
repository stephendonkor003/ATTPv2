@extends('layouts.app')
@section('title', 'Users')

@section('content')
    <div class="nxl-container">

        {{-- PAGE HEADER --}}
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-users text-primary me-2"></i>
                    Users Management
                </h4>
                <p class="text-muted mb-0">
                    Manage users, roles, and access permissions.
                </p>
            </div>

            <a href="{{ route('system.users.create') }}" class="btn btn-primary btn-sm">
                <i class="feather-user-plus me-1"></i> Create User
            </a>
        </div>

        {{-- FLASH --}}
        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="usersTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">User</th>
                            <th>Email</th>
                            <th>Governance</th>
                            <th>Role</th>
                            <th class="text-center">Permissions</th>
                            <th>Login Access</th>
                            <th>Created</th>
                            <th class="text-center" width="280">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($users as $user)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $user->name }}</div>
                                    <small class="text-muted">{{ ucfirst(str_replace('_', ' ', (string) $user->user_type)) }}</small>
                                    @if ($user->memberState)
                                        <div class="mt-1 d-flex align-items-center gap-2">
                                            @if ($user->memberState->flag_url)
                                                <img src="{{ $user->memberState->flag_url }}"
                                                    alt="{{ $user->memberState->name }} flag"
                                                    style="width: 28px; height: 20px; object-fit: cover; border:1px solid #d1d5db; border-radius:3px;">
                                            @endif
                                            <small class="text-info">Member State: {{ $user->memberState->name }}</small>
                                        </div>
                                    @endif
                                </td>

                                <td>{{ $user->email }}</td>

                                <td>
                                    <div class="fw-medium">
                                        {{ $user->governanceNode->name ?? '—' }}
                                    </div>
                                    <small class="text-muted">
                                        {{ $user->governanceNode->level->name ?? '' }}
                                    </small>
                                </td>

                                {{-- ROLE --}}
                                <td>
                                    @if ($user->isAdmin() || $user->isSuperAdmin())
                                        <span class="badge bg-danger px-3 py-1">
                                            <i class="feather-shield me-1"></i>
                                            Super Admin
                                        </span>
                                    @else
                                        <form method="POST"
                                            action="{{ route('system.users.role.update', $user->id) }}">
                                            @csrf
                                            @method('PUT')

                                            <select name="role_id" class="form-select form-select-sm"
                                                onchange="this.form.submit()" style="min-width: 140px;">
                                                <option value="">Select role</option>
                                                @foreach ($roles as $role)
                                                    <option value="{{ $role->id }}"
                                                        {{ $user->role_id === $role->id ? 'selected' : '' }}>
                                                        {{ $role->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </form>
                                    @endif
                                </td>

                                {{-- PERMISSIONS --}}
                                <td class="text-center">
                                    @if ($user->role && $user->role->permissions->count())
                                        <span class="badge bg-info px-3 py-1">
                                            {{ $user->role->permissions->count() }}
                                        </span>
                                    @else
                                        <span class="badge bg-secondary px-3 py-1">0</span>
                                    @endif
                                </td>

                                <td>
                                    @php
                                        $hasActiveBlock = $user->is_disabled && (!$user->disabled_until || $user->disabled_until->isFuture());
                                        $hasExpiredTemporaryBlock = $user->is_disabled && $user->disabled_until && $user->disabled_until->isPast();
                                    @endphp

                                    @if ($hasActiveBlock)
                                        @if ($user->disabled_until)
                                            <span class="badge bg-warning text-dark">Temporarily Blocked</span>
                                            <div class="small text-muted mt-1">
                                                Until: {{ $user->disabled_until->format('d M Y H:i') }}
                                            </div>
                                        @else
                                            <span class="badge bg-danger">Permanently Blocked</span>
                                        @endif
                                        @if ($user->disabled_reason)
                                            <div class="small text-muted mt-1">{{ $user->disabled_reason }}</div>
                                        @endif
                                    @elseif ($hasExpiredTemporaryBlock)
                                        <span class="badge bg-secondary">Block Expired</span>
                                        <div class="small text-muted mt-1">Will auto-clear on next login.</div>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </td>

                                <td>{{ $user->created_at->format('d M Y') }}</td>

                                {{-- ACTIONS --}}
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('system.users.edit', $user->id) }}"
                                            class="btn btn-sm btn-outline-success" title="Edit User">
                                            <i class="feather-edit"></i>
                                        </a>

                                        @if (!($user->isAdmin() || $user->isSuperAdmin()))
                                            <a href="{{ route('system.users.permissions', $user->id) }}"
                                                class="btn btn-sm btn-outline-primary"
                                                title="Assign Direct Permissions">
                                                <i class="feather-lock"></i>
                                            </a>

                                            <button type="button"
                                                class="btn btn-sm btn-outline-dark open-block-modal"
                                                data-bs-toggle="modal"
                                                data-bs-target="#blockLoginModal"
                                                data-user-id="{{ $user->id }}"
                                                data-user-name="{{ $user->name }}"
                                                title="Block Login">
                                                <i class="feather-slash"></i>
                                            </button>

                                            @php
                                                $hasActiveBlock = $user->is_disabled && (!$user->disabled_until || $user->disabled_until->isFuture());
                                            @endphp
                                            @if ($hasActiveBlock)
                                                <form action="{{ route('system.users.unblock-login', $user->id) }}"
                                                    method="POST" class="d-inline"
                                                    onsubmit="return confirm('Unblock login for this user?');">
                                                    @csrf
                                                    <button class="btn btn-sm btn-outline-success" title="Unblock Login">
                                                        <i class="feather-unlock"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            <form action="{{ route('system.users.reset-password', $user->id) }}"
                                                method="POST" class="d-inline"
                                                onsubmit="return confirm('Reset password and email user?');">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-warning" title="Reset Password">
                                                    <i class="feather-key"></i>
                                                </button>
                                            </form>

                                            <form action="{{ route('system.users.destroy', $user->id) }}"
                                                method="POST" class="d-inline"
                                                onsubmit="return confirm('Delete this user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" title="Delete User">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

        <div class="modal fade" id="blockLoginModal" tabindex="-1" aria-labelledby="blockLoginModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form method="POST" id="blockLoginForm">
                        @csrf
                        <div class="modal-header">
                            <h5 class="modal-title" id="blockLoginModalLabel">Block User Login</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3 small text-muted">Configure login block for <strong id="block-user-name">selected user</strong>.</p>

                            <div class="mb-3">
                                <label class="form-label fw-semibold">Block Type <span class="text-danger">*</span></label>
                                <select class="form-select" name="block_type" id="block_type" required>
                                    <option value="permanent">Permanent</option>
                                    <option value="temporary">Temporary</option>
                                </select>
                            </div>

                            <div class="row g-2 mb-3" id="temporary-duration-group" style="display: none;">
                                <div class="col-5">
                                    <label class="form-label fw-semibold">Duration <span class="text-danger">*</span></label>
                                    <input type="number" min="1" max="3650" class="form-control" name="duration_value" id="duration_value">
                                </div>
                                <div class="col-7">
                                    <label class="form-label fw-semibold">&nbsp;</label>
                                    <select class="form-select" name="duration_unit" id="duration_unit">
                                        <option value="minutes">Minutes</option>
                                        <option value="hours">Hours</option>
                                        <option value="days" selected>Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="months">Months</option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Reason</label>
                                <input type="text" class="form-control" name="reason" maxlength="255"
                                    placeholder="Optional reason for the block">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-danger">
                                <i class="feather-slash me-1"></i> Block Login
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const blockLoginForm = document.getElementById('blockLoginForm');
            const blockUserName = document.getElementById('block-user-name');
            const blockTypeSelect = document.getElementById('block_type');
            const temporaryDurationGroup = document.getElementById('temporary-duration-group');
            const durationValueInput = document.getElementById('duration_value');
            const durationUnitSelect = document.getElementById('duration_unit');

            function toggleTemporaryDuration() {
                const isTemporary = blockTypeSelect.value === 'temporary';
                temporaryDurationGroup.style.display = isTemporary ? '' : 'none';
                durationValueInput.required = isTemporary;
                durationUnitSelect.required = isTemporary;
                if (!isTemporary) {
                    durationValueInput.value = '';
                }
            }

            document.querySelectorAll('.open-block-modal').forEach(function(button) {
                button.addEventListener('click', function() {
                    const userId = button.getAttribute('data-user-id');
                    const userName = button.getAttribute('data-user-name');

                    blockLoginForm.setAttribute('action', `{{ url('/system/users') }}/${userId}/block-login`);
                    blockUserName.textContent = userName || 'selected user';
                    blockTypeSelect.value = 'permanent';
                    toggleTemporaryDuration();
                });
            });

            blockTypeSelect.addEventListener('change', toggleTemporaryDuration);
            toggleTemporaryDuration();
        });
    </script>
@endsection
