@extends('layouts.app')

@php
    $defaultUserType = $defaultUserType ?? request('user_type', 'staff');
    $selectedUserType = old('user_type', $defaultUserType);
    $isMemberStateType = $selectedUserType === 'member_state';
    $isVendorType = $selectedUserType === 'vendor';
    $vendorCreateOnly = $vendorCreateOnly ?? false;
    $vendorCategories = $vendorCategories ?? collect();
    $formAction = $formAction ?? route('system.users.store');
    $cancelRoute = $cancelRoute ?? route('system.users.index');
    $pageTitle = $pageTitle ?? 'Create User';
    $pageSubtitle = $pageSubtitle ?? 'Create a new system user and assign an access role.';
    $backButtonText = $backButtonText ?? 'Back to Users';
    $submitButtonText = $submitButtonText ?? ($isVendorType ? 'Create Vendor' : 'Create User');
    $vendorConversionPrompt = session('vendor_conversion_prompt');
@endphp

@section('title', $pageTitle)

@push('styles')
    <style>
        .vendor-conversion-modal {
            z-index: 2050 !important;
        }

        .vendor-conversion-modal .modal-dialog {
            max-width: min(680px, calc(100vw - 32px));
        }

        .vendor-conversion-modal .modal-content {
            border: 0;
            border-radius: 10px;
            background: #ffffff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.32);
            overflow: hidden;
        }

        .vendor-conversion-modal .modal-header {
            align-items: flex-start;
            gap: 12px;
            border-bottom: 1px solid #e2e8f0;
            background: #fff7ed;
            padding: 18px 20px;
        }

        .vendor-conversion-modal .modal-icon {
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

        .vendor-conversion-modal .modal-title {
            color: #0f172a;
            font-size: 1.08rem;
            font-weight: 800;
            line-height: 1.25;
        }

        .vendor-conversion-modal .modal-subtitle {
            color: #475569;
            font-size: 0.86rem;
            margin-top: 4px;
        }

        .vendor-conversion-modal .modal-body {
            padding: 20px;
            color: #334155;
            font-size: 0.94rem;
            line-height: 1.55;
        }

        .vendor-conversion-modal .account-summary {
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            padding: 14px 16px;
        }

        .vendor-conversion-modal .account-summary-label {
            color: #64748b;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.05em;
            text-transform: uppercase;
        }

        .vendor-conversion-modal .account-summary-value {
            color: #0f172a;
            font-weight: 700;
            margin-top: 3px;
        }

        .vendor-conversion-modal .conversion-impact {
            margin: 14px 0 0;
            padding-left: 18px;
        }

        .vendor-conversion-modal .conversion-impact li + li {
            margin-top: 6px;
        }

        .vendor-conversion-modal .modal-footer {
            border-top: 1px solid #e2e8f0;
            background: #f8fafc;
            padding: 14px 20px;
        }

        .vendor-conversion-modal + .modal-backdrop,
        .modal-backdrop.vendor-conversion-backdrop {
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
                        <i class="bi bi-person-plus me-1"></i>
                        {{ $pageTitle }}
                    </h4>
                    <p class="text-muted mb-0">
                        {{ $pageSubtitle }}
                    </p>
                </div>

                <a href="{{ $cancelRoute }}" class="btn btn-light">
                    <i class="bi bi-arrow-left me-1"></i>
                    {{ $backButtonText }}
                </a>
            </div>

            {{-- ================= VALIDATION ERRORS ================= --}}
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- ================= CREATE USER FORM ================= --}}
            <form method="POST" action="{{ $formAction }}" id="userCreateForm">
                @csrf
                <input type="hidden" name="convert_existing_vendor" id="convert_existing_vendor"
                    value="{{ old('convert_existing_vendor', '0') }}">

                <div class="card shadow-sm border-0">
                    <div class="card-body">
                        <div class="row">

                            {{-- NAME --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" name="name" class="form-control" value="{{ old('name') }}"
                                    placeholder="Enter full name" required>
                            </div>

                            {{-- EMAIL --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" name="email" id="email" class="form-control" value="{{ old('email') }}"
                                    placeholder="Enter email address" required>
                            </div>

                            {{-- ROLE --}}
                            <div class="col-md-6 mb-3" id="role-group"
                                style="{{ $isVendorType ? 'display: none;' : '' }}">
                                <label class="form-label fw-semibold">
                                    Role <span class="text-danger">*</span>
                                </label>
                                <select name="role_id" id="role_id" class="form-select"
                                    {{ $isVendorType ? 'disabled' : 'required' }}>
                                    <option value="">-- Select Role --</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}"
                                            data-role-name="{{ $role->name }}"
                                            {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Determines what the user can access in the system. Vendor portal accounts do not use roles.
                                </small>
                            </div>

                            {{-- USER TYPE --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    User Type <span class="text-danger">*</span>
                                </label>
                                @if ($vendorCreateOnly)
                                    <input type="hidden" name="user_type" id="user_type" value="vendor">
                                    <input type="text" class="form-control" value="Vendor" disabled>
                                @else
                                    <select name="user_type" id="user_type" class="form-select" required>
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
                                                {{ $selectedUserType === $typeValue ? 'selected' : '' }}>
                                                {{ $typeLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                <small class="text-muted">
                                    Vendor users access the vendor portal and do not need a system role.
                                </small>
                            </div>

                            {{-- VENDOR CATEGORY --}}
                            <div class="col-md-6 mb-3" id="vendor-category-group"
                                style="{{ $isVendorType ? '' : 'display: none;' }}">
                                <label class="form-label fw-semibold">
                                    Vendor Category
                                </label>
                                <select name="vendor_category" id="vendor_category" class="form-select"
                                    {{ $isVendorType ? '' : 'disabled' }}>
                                    <option value="">-- Select Vendor Category --</option>
                                    @foreach ($vendorCategories as $category)
                                        <option value="{{ $category }}" {{ old('vendor_category') === $category ? 'selected' : '' }}>
                                            {{ $category }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">
                                    Optional. Used to target vendor-group procurements.
                                </small>
                            </div>

                            @if ($vendorCreateOnly)
                                <div class="col-12">
                                    @include('vendor.admin.partials.funding-assignments', [
                                        'vendorFundingPrograms' => $vendorFundingPrograms ?? collect(),
                                        'vendorFundingAssignments' => $vendorFundingAssignments ?? collect(),
                                    ])
                                </div>
                            @endif

                            {{-- MEMBER STATE --}}
                            <div class="col-md-6 mb-3" id="member-state-group"
                                style="{{ $isMemberStateType ? '' : 'display: none;' }}">
                                <label class="form-label fw-semibold">
                                    Member State <span class="text-danger">*</span>
                                </label>
                                <select name="member_state_id" id="member_state_id" class="form-select">
                                    <option value="">-- Select Member State --</option>
                                    @foreach ($memberStates as $memberState)
                                        @php($flagUrl = $memberState->flag_url ?? '')
                                        <option value="{{ $memberState->id }}"
                                            data-name="{{ $memberState->name }}"
                                            data-flag-url="{{ $flagUrl }}"
                                            {{ old('member_state_id') == $memberState->id ? 'selected' : '' }}>
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
                                    Links this login to the official member state account for treaty actions.
                                </small>
                            </div>

                            {{-- PASSWORD INFO --}}
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-semibold">
                                    Password
                                </label>
                                <input type="text" class="form-control" value="Auto-generated" disabled>
                                <small class="text-muted">
                                    A secure password will be generated automatically and emailed to the user.
                                </small>
                            </div>

                            {{-- GOVERNANCE NODE --}}
                            <div class="col-md-6 mb-3 order-last" id="governance-node-group"
                                style="{{ $isMemberStateType || $isVendorType ? 'display: none;' : '' }}">
                                <label class="form-label fw-semibold" id="governance-node-label">
                                    Governance Node
                                    <span class="text-danger d-none" id="governance-node-required">*</span>
                                </label>
                                <select name="governance_node_id" id="governance_node_id" class="form-select">
                                    <option value="">-- Select Node --</option>
                                    @foreach ($nodes as $node)
                                        <option value="{{ $node->id }}"
                                            {{ old('governance_node_id') == $node->id ? 'selected' : '' }}>
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
                        <a href="{{ $cancelRoute }}" class="btn btn-light">
                            Cancel
                        </a>

                        <button type="submit" class="btn btn-success px-4">
                            <i class="bi bi-check-circle me-1"></i>
                            {{ $submitButtonText }}
                        </button>
                    </div>
                </div>
            </form>

            @if ($vendorConversionPrompt)
                <div class="modal fade vendor-conversion-modal" id="vendorConversionModal" tabindex="-1"
                    aria-labelledby="vendorConversionModalLabel" aria-hidden="true" data-bs-backdrop="static"
                    data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <span class="modal-icon">
                                    <i class="feather-alert-triangle"></i>
                                </span>
                                <div class="flex-grow-1">
                                    <h5 class="modal-title" id="vendorConversionModalLabel">
                                        Existing Back-Office Account Found
                                    </h5>
                                    <div class="modal-subtitle">
                                        Confirm before changing this user's access type.
                                    </div>
                                </div>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <p class="mb-3">
                                    The email <strong>{{ $vendorConversionPrompt['email'] }}</strong> already belongs to
                                    <strong>{{ $vendorConversionPrompt['name'] }}</strong>.
                                </p>
                                <div class="account-summary mb-3">
                                    <div class="account-summary-label">Current account</div>
                                    <div class="account-summary-value">
                                        {{ $vendorConversionPrompt['user_type'] }}
                                        @if (!empty($vendorConversionPrompt['role']))
                                            - {{ $vendorConversionPrompt['role'] }}
                                        @endif
                                    </div>
                                </div>
                                <p class="mb-2">
                                    Do you want to convert this back-office user into a vendor account?
                                </p>
                                <ul class="conversion-impact">
                                    <li>System role, governance scope, and member-state link will be removed.</li>
                                    <li>Vendor portal access will be granted.</li>
                                    <li>The user will keep their existing password.</li>
                                    <li>You can revert this later from Edit User by choosing a non-vendor user type and assigning a role.</li>
                                </ul>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">
                                    No, Keep Back Office
                                </button>
                                <button type="button" class="btn btn-warning" id="confirmVendorConversionBtn">
                                    Yes, Convert to Vendor
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

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
            const userCreateForm = document.getElementById('userCreateForm');
            const emailInput = document.getElementById('email');
            const convertExistingVendorInput = document.getElementById('convert_existing_vendor');
            const vendorConversionModalEl = document.getElementById('vendorConversionModal');
            const confirmVendorConversionBtn = document.getElementById('confirmVendorConversionBtn');
            const governanceRequiredMarker = document.getElementById('governance-node-required');

            function selectedRoleName() {
                if (!roleSelect || !roleSelect.value) {
                    return '';
                }

                return roleSelect.options[roleSelect.selectedIndex]?.dataset.roleName || '';
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
            userTypeSelect.addEventListener('change', () => {
                if (convertExistingVendorInput) {
                    convertExistingVendorInput.value = '0';
                }
            });
            emailInput?.addEventListener('input', () => {
                if (convertExistingVendorInput) {
                    convertExistingVendorInput.value = '0';
                }
            });
            memberStateSelect.addEventListener('change', updateMemberStatePreview);
            toggleUserTypeFields();

            if (vendorConversionModalEl) {
                const showConversionPrompt = () => {
                    if (vendorConversionModalEl.parentElement !== document.body) {
                        document.body.appendChild(vendorConversionModalEl);
                    }

                    if (window.bootstrap?.Modal) {
                        const modal = new bootstrap.Modal(vendorConversionModalEl, {
                            backdrop: 'static',
                            keyboard: false,
                        });
                        modal.show();

                        window.requestAnimationFrame(() => {
                            document.querySelector('.modal-backdrop:last-child')
                                ?.classList.add('vendor-conversion-backdrop');
                        });
                        return;
                    }

                    if (confirm('This email already belongs to a back-office account. Convert it to a vendor account?')) {
                        if (convertExistingVendorInput) {
                            convertExistingVendorInput.value = '1';
                        }
                        userCreateForm?.submit();
                    }
                };

                confirmVendorConversionBtn?.addEventListener('click', () => {
                    if (convertExistingVendorInput) {
                        convertExistingVendorInput.value = '1';
                    }
                    userCreateForm?.submit();
                });

                showConversionPrompt();
            }
        });
    </script>
@endsection
