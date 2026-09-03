@extends('layouts.app')

@section('title', 'Edit User')

@php
    $userTypeConversionPrompt = session('user_type_conversion_prompt');
@endphp

@push('styles')
    <style>
        .user-type-conversion-modal {
            z-index: 2050 !important;
        }

        .user-type-conversion-modal .modal-dialog {
            max-width: min(700px, calc(100vw - 32px));
        }

        .user-type-conversion-modal .modal-content {
            border: 0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.32);
            overflow: hidden;
        }

        .user-type-conversion-modal .modal-header {
            align-items: flex-start;
            gap: 12px;
            border-bottom: 1px solid #e2e8f0;
            background: #fff7ed;
            padding: 18px 20px;
        }

        .user-type-conversion-modal .modal-icon {
            width: 42px;
            height: 42px;
            flex: 0 0 42px;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #92400e;
            background: #fed7aa;
            font-size: 1.2rem;
        }

        .user-type-conversion-modal .modal-title {
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .user-type-conversion-modal .modal-subtitle {
            color: #475569;
            font-size: 0.86rem;
            margin-top: 4px;
        }

        .user-type-conversion-modal .modal-body {
            padding: 20px;
            color: #334155;
            font-size: 0.94rem;
            line-height: 1.55;
        }

        .user-type-conversion-modal .account-summary {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            padding: 14px 16px;
        }

        .user-type-conversion-modal .account-summary-label {
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .user-type-conversion-modal .account-summary-value {
            color: #0f172a;
            font-weight: 700;
            margin-top: 3px;
        }

        .user-type-conversion-modal .conversion-impact {
            margin: 14px 0 0;
            padding-left: 18px;
        }

        .user-type-conversion-modal .conversion-impact li + li {
            margin-top: 6px;
        }

        .user-type-conversion-modal .modal-footer {
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 14px 20px;
        }

        .user-type-conversion-modal + .modal-backdrop,
        .modal-backdrop.user-type-conversion-backdrop {
            z-index: 2040 !important;
            background-color: #0f172a;
            opacity: 0.52 !important;
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
        }
    </style>
@endpush

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

            @if ($user->user_type === 'vendor')
                <div class="alert alert-info">
                    This user currently has vendor portal access. To revert them to a back-office user, change
                    <strong>User Type</strong> from Vendor, assign a system role, and save.
                </div>
            @endif

            {{-- ================= EDIT FORM ================= --}}
            <form method="POST" action="{{ route('system.users.update', $user->id) }}" id="userEditForm">
                @csrf
                @method('PUT')
                <input type="hidden" name="confirm_user_type_conversion" id="confirm_user_type_conversion"
                    value="{{ old('confirm_user_type_conversion', '0') }}">

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        @php
                            $selectedUserType = old('user_type', $user->user_type);
                            $isMemberStateType = $selectedUserType === 'member_state';
                            $isVendorType = $selectedUserType === 'vendor';
                            $vendorCategories = $vendorCategories ?? collect();
                        @endphp

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
                            <div class="col-md-6 mb-3" id="role-group"
                                style="{{ $isVendorType ? 'display: none;' : '' }}">
                                <label class="form-label fw-semibold">
                                    Role <span class="text-danger">*</span>
                                </label>

                                @if ($user->role && $user->role->name === 'Super Admin')
                                    <input type="text" class="form-control" value="Super Admin" disabled>
                                    <small class="text-muted">
                                        Super Admin role is protected and cannot be changed.
                                    </small>
                                @else
                                    <select name="role_id" id="role_id" class="form-select"
                                        {{ $isVendorType ? 'disabled' : 'required' }}>
                                        <option value="">-- Select Role --</option>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->id }}"
                                                data-role-name="{{ $role->name }}"
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
                                        'evaluator' => 'Evaluator',
                                        'ttl' => 'Task Team Leader',
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

                            {{-- VENDOR CATEGORY --}}
                            <div class="col-md-6 mb-3" id="vendor-category-group"
                                style="{{ $isVendorType ? '' : 'display: none;' }}">
                                <label class="form-label fw-semibold">
                                    Vendor Category
                                </label>
                                <select name="vendor_category" id="vendor_category" class="form-select"
                                    {{ $user->role && $user->role->name === 'Super Admin' ? 'disabled' : '' }}
                                    {{ $isVendorType ? '' : 'disabled' }}>
                                    <option value="">-- Select Vendor Category --</option>
                                    @foreach ($vendorCategories as $category)
                                        <option value="{{ $category }}"
                                            {{ old('vendor_category', $user->vendor_category) === $category ? 'selected' : '' }}>
                                            {{ $category }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Optional. Used to target vendor-group procurements.
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
                                style="{{ $isMemberStateType || $isVendorType ? 'display: none;' : '' }}">
                                <label class="form-label fw-semibold" id="governance-node-label">
                                    Governance Node
                                    <span class="text-danger d-none" id="governance-node-required">*</span>
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
                                    Required for Monitoring and Evaluation Manager users. Other back-office users may be scoped here when needed.
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

            <div class="modal fade user-type-conversion-modal" id="userTypeConversionModal" tabindex="-1"
                aria-labelledby="userTypeConversionModalLabel" aria-hidden="true" data-bs-backdrop="static"
                data-bs-keyboard="false">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <span class="modal-icon">
                                <i class="feather-alert-triangle"></i>
                            </span>
                            <div class="flex-grow-1">
                                <h5 class="modal-title" id="userTypeConversionModalLabel">
                                    Confirm Account Type Change
                                </h5>
                                <div class="modal-subtitle" id="userTypeConversionSubtitle">
                                    Review this access change before saving.
                                </div>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3" id="userTypeConversionMessage"></p>
                            <div class="account-summary mb-3">
                                <div class="account-summary-label">Current account</div>
                                <div class="account-summary-value" id="currentAccountSummary"></div>
                            </div>
                            <div class="account-summary mb-3">
                                <div class="account-summary-label">New account</div>
                                <div class="account-summary-value" id="targetAccountSummary"></div>
                            </div>
                            <p class="mb-2" id="userTypeConversionQuestion"></p>
                            <ul class="conversion-impact" id="userTypeConversionImpact"></ul>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                No, Review Changes
                            </button>
                            <button type="button" class="btn btn-warning" id="confirmUserTypeConversionBtn">
                                Yes, Continue
                            </button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const userTypeSelect = document.getElementById('user_type');
            const roleGroup = document.getElementById('role-group');
            const roleSelect = document.getElementById('role_id');
            const governanceGroup = document.getElementById('governance-node-group');
            const governanceSelect = document.getElementById('governance_node_id');
            const vendorCategoryGroup = document.getElementById('vendor-category-group');
            const vendorCategorySelect = document.getElementById('vendor_category');
            const memberStateGroup = document.getElementById('member-state-group');
            const memberStateSelect = document.getElementById('member_state_id');
            const memberStatePreview = document.getElementById('member-state-preview');
            const memberStatePreviewImage = document.getElementById('member-state-preview-image');
            const memberStatePreviewName = document.getElementById('member-state-preview-name');
            const userEditForm = document.getElementById('userEditForm');
            const conversionConfirmInput = document.getElementById('confirm_user_type_conversion');
            const userTypeConversionModalEl = document.getElementById('userTypeConversionModal');
            const userTypeConversionModalLabel = document.getElementById('userTypeConversionModalLabel');
            const userTypeConversionSubtitle = document.getElementById('userTypeConversionSubtitle');
            const userTypeConversionMessage = document.getElementById('userTypeConversionMessage');
            const currentAccountSummary = document.getElementById('currentAccountSummary');
            const targetAccountSummary = document.getElementById('targetAccountSummary');
            const userTypeConversionQuestion = document.getElementById('userTypeConversionQuestion');
            const userTypeConversionImpact = document.getElementById('userTypeConversionImpact');
            const confirmUserTypeConversionBtn = document.getElementById('confirmUserTypeConversionBtn');
            const governanceRequiredMarker = document.getElementById('governance-node-required');
            const originalUserType = @json($user->user_type);
            const originalUserTypeLabel = @json(ucfirst(str_replace('_', ' ', (string) $user->user_type)));
            const originalRoleName = @json($user->role?->name);
            const originalVendorCategory = @json($user->vendor_category);
            const editedUserName = @json($user->name);
            const editedUserEmail = @json($user->email);
            const shouldShowConversionPrompt = @json((bool) $userTypeConversionPrompt);
            let conversionSubmitConfirmed = false;

            if (!userTypeSelect) {
                return;
            }

            const userTypeLabels = {
                staff: 'Staff',
                member_state: 'Member State',
                vendor: 'Vendor',
                funding_partner: 'Funding Partner',
                evaluator: 'Evaluator',
                admin: 'Admin',
            };

            function selectedOptionText(select) {
                if (!select || !select.value) {
                    return '';
                }

                return (select.options[select.selectedIndex]?.textContent || '').trim();
            }

            function selectedRoleName() {
                if (!roleSelect || !roleSelect.value) {
                    return '';
                }

                return roleSelect.options[roleSelect.selectedIndex]?.dataset.roleName || '';
            }

            function currentAccountLabel() {
                const parts = [originalUserTypeLabel];
                if (originalRoleName) {
                    parts.push(originalRoleName);
                }
                if (originalUserType === 'vendor' && originalVendorCategory) {
                    parts.push(originalVendorCategory);
                }

                return parts.join(' - ');
            }

            function targetAccountLabel() {
                const targetType = userTypeSelect.value;
                const parts = [userTypeLabels[targetType] || targetType || 'User'];

                if (targetType === 'vendor') {
                    const vendorCategory = vendorCategorySelect?.value || '';
                    if (vendorCategory) {
                        parts.push(vendorCategory);
                    }
                } else {
                    const roleName = selectedOptionText(roleSelect);
                    if (roleName) {
                        parts.push(roleName);
                    }
                }

                return parts.join(' - ');
            }

            function isVendorBoundaryChange() {
                return (originalUserType === 'vendor') !== (userTypeSelect.value === 'vendor');
            }

            function resetConversionConfirmation() {
                if (conversionConfirmInput) {
                    conversionConfirmInput.value = '0';
                }
                conversionSubmitConfirmed = false;
            }

            function renderImpactItems(items) {
                if (!userTypeConversionImpact) {
                    return;
                }

                userTypeConversionImpact.innerHTML = '';
                items.forEach((item) => {
                    const li = document.createElement('li');
                    li.textContent = item;
                    userTypeConversionImpact.appendChild(li);
                });
            }

            function showUserTypeConversionPrompt() {
                const convertingToVendor = userTypeSelect.value === 'vendor';
                const title = convertingToVendor
                    ? 'Convert Back-Office User to Vendor?'
                    : 'Revert Vendor to Back Office?';
                const subtitle = convertingToVendor
                    ? 'This account will move from back-office access to vendor portal access.'
                    : 'This account will move from vendor portal access back to back-office access.';
                const question = convertingToVendor
                    ? 'Do you want to convert this back-office user into a vendor account?'
                    : 'Do you want to convert this vendor account back to a back-office user?';
                const impacts = convertingToVendor
                    ? [
                        'System role, governance scope, and member-state link will be removed.',
                        'Vendor portal access will be granted.',
                        'The user will keep their existing password.',
                        'This can be reverted later by editing the user and choosing a non-vendor type.',
                    ]
                    : [
                        'Vendor portal-only access will be removed.',
                        'The selected role and back-office user type will apply.',
                        'Vendor category will be cleared.',
                        'The user will keep their existing password.',
                    ];

                if (userTypeConversionModalLabel) userTypeConversionModalLabel.textContent = title;
                if (userTypeConversionSubtitle) userTypeConversionSubtitle.textContent = subtitle;
                if (userTypeConversionMessage) {
                    userTypeConversionMessage.textContent = `${editedUserName} (${editedUserEmail}) is being changed from ${currentAccountLabel()} to ${targetAccountLabel()}.`;
                }
                if (currentAccountSummary) currentAccountSummary.textContent = currentAccountLabel();
                if (targetAccountSummary) targetAccountSummary.textContent = targetAccountLabel();
                if (userTypeConversionQuestion) userTypeConversionQuestion.textContent = question;
                if (confirmUserTypeConversionBtn) {
                    confirmUserTypeConversionBtn.textContent = convertingToVendor
                        ? 'Yes, Convert to Vendor'
                        : 'Yes, Convert to Back Office';
                }
                renderImpactItems(impacts);

                if (!userTypeConversionModalEl) {
                    if (confirm(question)) {
                        submitConfirmedConversion();
                    }
                    return;
                }

                if (userTypeConversionModalEl.parentElement !== document.body) {
                    document.body.appendChild(userTypeConversionModalEl);
                }

                if (window.bootstrap?.Modal) {
                    const modal = bootstrap.Modal.getOrCreateInstance(userTypeConversionModalEl, {
                        backdrop: 'static',
                        keyboard: false,
                    });
                    modal.show();

                    window.requestAnimationFrame(() => {
                        document.querySelector('.modal-backdrop:last-child')
                            ?.classList.add('user-type-conversion-backdrop');
                    });
                    return;
                }

                if (confirm(question)) {
                    submitConfirmedConversion();
                }
            }

            function submitConfirmedConversion() {
                if (conversionConfirmInput) {
                    conversionConfirmInput.value = '1';
                }

                conversionSubmitConfirmed = true;

                if (userEditForm?.requestSubmit) {
                    userEditForm.requestSubmit();
                } else {
                    userEditForm?.submit();
                }
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
                const isVendor = userTypeSelect.value === 'vendor';

                if (roleGroup && roleSelect) {
                    roleGroup.style.display = isVendor ? 'none' : '';
                    roleSelect.required = !isVendor;
                    roleSelect.disabled = isVendor;
                    if (isVendor) {
                        roleSelect.value = '';
                    }
                }

                const requiresGovernance = selectedRoleName() === 'Monitoring and Evaluation Manager';

                governanceGroup.style.display = (isMemberState || isVendor) ? 'none' : '';
                governanceSelect.required = !(isMemberState || isVendor) && requiresGovernance;
                governanceRequiredMarker?.classList.toggle('d-none', !governanceSelect.required);
                if (isMemberState || isVendor) {
                    governanceSelect.value = '';
                }

                if (vendorCategoryGroup && vendorCategorySelect) {
                    vendorCategoryGroup.style.display = isVendor ? '' : 'none';
                    vendorCategorySelect.disabled = !isVendor;
                    if (!isVendor) {
                        vendorCategorySelect.value = '';
                    }
                }

                memberStateGroup.style.display = isMemberState ? '' : 'none';
                memberStateSelect.required = isMemberState;
                if (!isMemberState) {
                    memberStateSelect.value = '';
                }
                updateMemberStatePreview();
            }

            userTypeSelect.addEventListener('change', toggleUserTypeFields);
            roleSelect?.addEventListener('change', toggleUserTypeFields);
            [userTypeSelect, roleSelect, vendorCategorySelect, governanceSelect, memberStateSelect].forEach((field) => {
                field?.addEventListener('change', resetConversionConfirmation);
            });
            memberStateSelect.addEventListener('change', updateMemberStatePreview);
            confirmUserTypeConversionBtn?.addEventListener('click', submitConfirmedConversion);
            userEditForm?.addEventListener('submit', function(event) {
                if (conversionSubmitConfirmed || conversionConfirmInput?.value === '1') {
                    return;
                }

                if (isVendorBoundaryChange()) {
                    event.preventDefault();
                    showUserTypeConversionPrompt();
                }
            });
            toggleUserTypeFields();

            if (shouldShowConversionPrompt && isVendorBoundaryChange()) {
                showUserTypeConversionPrompt();
            }
        });
    </script>
@endsection
