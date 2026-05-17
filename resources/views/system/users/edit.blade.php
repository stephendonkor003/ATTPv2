@extends('layouts.app')

@section('title', 'Edit User')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            {{-- ================= PAGE HEADER ================= --}}
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">
                        <i class="bi bi-pencil-square me-1"></i>
                        Edit User
                    </h4>
                    <p class="text-muted mb-0">
                        Update user information and role assignment.
                    </p>
                </div>

                <a href="{{ route('system.users.index') }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Users
                </a>
            </div>

            {{-- ================= FLASH / ERRORS ================= --}}
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

            {{-- ================= EDIT FORM ================= --}}
            <form method="POST" action="{{ route('system.users.update', $user->id) }}">
                @csrf
                @method('PUT')

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        @php($isMemberStateType = old('user_type', $user->user_type) === 'member_state')

                        <div class="row">

                            {{-- NAME --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" class="form-control"
                                    value="{{ old('name', $user->name) }}" required
                                    {{ $user->role && $user->role->name === 'Super Admin' ? 'readonly' : '' }}>
                            </div>

                            {{-- EMAIL --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" name="email" class="form-control"
                                    value="{{ old('email', $user->email) }}" required
                                    {{ $user->role && $user->role->name === 'Super Admin' ? 'readonly' : '' }}>
                            </div>

                            {{-- ROLE --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Role <span class="text-danger">*</span>
                                </label>

                                @if ($user->role && $user->role->name === 'Super Admin')
                                    <input type="text" class="form-control" value="Super Admin" disabled>
                                    <small class="text-muted">
                                        Super Admin role is protected and cannot be changed.
                                    </small>
                                @else
                                    <select name="role_id" class="form-select" required>
                                        <option value="">-- Select Role --</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                {{ old('role_id', $user->role_id) == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">
                                        Changing role updates the user’s permissions.
                                    </small>
                                @endif
                            </div>

                            {{-- USER TYPE --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    User Type <span class="text-danger">*</span>
                                </label>
                                <select name="user_type" id="user_type" class="form-select"
                                    {{ $user->role && $user->role->name === 'Super Admin' ? 'disabled' : '' }} required>
                                    @foreach ([
                                        'staff' => 'Staff',
                                        'member_state' => 'Member State',
                                        'vendor' => 'Vendor',
                                        'funding_partner' => 'Funding Partner',
                                        'think_tank' => 'Think Tank',
                                        'evaluator' => 'Evaluator',
                                        'admin' => 'Admin',
                                    ] as $typeValue => $typeLabel)
                                        <option value="{{ $typeValue }}"
                                            {{ old('user_type', $user->user_type) === $typeValue ? 'selected' : '' }}>
                                            {{ $typeLabel }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Member-state users can update treaty signing and ratification status from their portal.
                                </small>
                            </div>

                            {{-- MEMBER STATE --}}
                            <div class="col-md-6 mb-3" id="member-state-group"
                                style="{{ $isMemberStateType ? '' : 'display: none;' }}">
                                <label class="form-label fw-semibold">
                                    Member State <span class="text-danger">*</span>
                                </label>
                                <select name="member_state_id" id="member_state_id" class="form-select"
                                    {{ $user->role && $user->role->name === 'Super Admin' ? 'disabled' : '' }}>
                                    <option value="">-- Select Member State --</option>
                                    @foreach ($memberStates as $memberState)
                                        @php($flagUrl = $memberState->flag_url ?? '')
                                        <option value="{{ $memberState->id }}"
                                            data-name="{{ $memberState->name }}"
                                            data-flag-url="{{ $flagUrl }}"
                                            {{ old('member_state_id', $user->member_state_id) == $memberState->id ? 'selected' : '' }}>
                                            {{ $memberState->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div id="member-state-preview" class="mt-2 p-2 border rounded"
                                    style="display: none; background: #f8fafc;">
                                    <div class="d-flex align-items-center gap-2">
                                        <img id="member-state-preview-image" src="" alt="Member state flag"
                                            style="width: 44px; height: 30px; object-fit: cover; border:1px solid #d1d5db; border-radius:4px;">
                                        <span id="member-state-preview-name" class="small fw-semibold text-dark"></span>
                                    </div>
                                </div>
                                <small class="text-muted">
                                    Links this user account directly to one AU member state.
                                </small>
                            </div>

                            {{-- PASSWORD INFO --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Password
                                </label>
                                <input type="text" class="form-control" value="Not editable here" disabled>
                                <small class="text-muted">
                                    Use password reset to change user password.
                                </small>
                            </div>

                            {{-- GOVERNANCE NODE --}}
                            <div class="col-md-6 mb-3 order-last" id="governance-node-group"
                                style="{{ $isMemberStateType ? 'display: none;' : '' }}">
                                <label class="form-label fw-semibold" id="governance-node-label">
                                    Governance Node
                                </label>
                                <select name="governance_node_id" id="governance_node_id" class="form-select"
                                    {{ $user->role && $user->role->name === 'Super Admin' ? 'disabled' : '' }}>
                                    <option value="">-- Select Node --</option>
                                    @foreach ($nodes as $node)
                                        <option value="{{ $node->id }}"
                                            {{ old('governance_node_id', $user->governance_node_id) == $node->id ? 'selected' : '' }}>
                                            {{ $node->name }} ({{ $node->level->name ?? 'Level' }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Optional. Use this only when the user should be scoped to a governance node.
                                </small>
                            </div>

                        </div>

                    </div>

                    {{-- ================= ACTION BUTTONS ================= --}}
                    <div class="card-footer bg-light d-flex justify-content-end gap-2">
                        <a href="{{ route('system.users.index') }}" class="btn btn-light">
                            Cancel
                        </a>

                        @if (!$user->role || $user->role->name !== 'Super Admin')
                            <form action="{{ route('system.users.reset-password', $user->id) }}"
                                method="POST" onsubmit="return confirm('Reset password and email user?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning">
                                    <i class="bi bi-key me-1"></i>
                                    Reset Password
                                </button>
                            </form>
                        @endif

                        @if (!$user->role || $user->role->name !== 'Super Admin')
                            <button type="submit" class="btn btn-primary px-4">
                                <i class="bi bi-save me-1"></i>
                                Update User
                            </button>
                        @endif
                    </div>
                </div>
            </form>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeSelect = document.getElementById('user_type');
            const governanceGroup = document.getElementById('governance-node-group');
            const governanceSelect = document.getElementById('governance_node_id');
            const memberStateGroup = document.getElementById('member-state-group');
            const memberStateSelect = document.getElementById('member_state_id');
            const memberStatePreview = document.getElementById('member-state-preview');
            const memberStatePreviewImage = document.getElementById('member-state-preview-image');
            const memberStatePreviewName = document.getElementById('member-state-preview-name');

            if (!userTypeSelect) {
                return;
            }

            function updateMemberStatePreview() {
                if (!memberStateSelect || !memberStatePreview || !memberStatePreviewImage || !memberStatePreviewName) {
                    return;
                }

                const selectedOption = memberStateSelect.options[memberStateSelect.selectedIndex];
                const selectedValue = memberStateSelect.value;
                const flagUrl = selectedOption ? (selectedOption.getAttribute('data-flag-url') || '') : '';
                const stateName = selectedOption ? (selectedOption.getAttribute('data-name') || '') : '';

                if (!selectedValue) {
                    memberStatePreview.style.display = 'none';
                    memberStatePreviewImage.setAttribute('src', '');
                    memberStatePreviewName.textContent = '';
                    return;
                }

                if (flagUrl) {
                    memberStatePreviewImage.setAttribute('src', flagUrl);
                } else {
                    memberStatePreviewImage.setAttribute('src', '');
                }
                memberStatePreviewName.textContent = stateName;
                memberStatePreview.style.display = '';
            }

            function toggleUserTypeFields() {
                const isMemberState = userTypeSelect.value === 'member_state';

                governanceGroup.style.display = isMemberState ? 'none' : '';
                governanceSelect.required = false;
                if (isMemberState) {
                    governanceSelect.value = '';
                }

                memberStateGroup.style.display = isMemberState ? '' : 'none';
                memberStateSelect.required = isMemberState;
                if (!isMemberState) {
                    memberStateSelect.value = '';
                }
                updateMemberStatePreview();
            }

            userTypeSelect.addEventListener('change', toggleUserTypeFields);
            memberStateSelect.addEventListener('change', updateMemberStatePreview);
            toggleUserTypeFields();
        });
    </script>
@endsection
