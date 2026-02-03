@extends('layouts.app')

@section('title', 'Assign Permissions')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-lock text-primary me-2"></i>
                    Assign Permissions
                </h4>
                <p class="text-muted mb-0">
                    Configure access rights for role:
                    <span class="fw-semibold text-dark">{{ $role->name }}</span>
                </p>
            </div>

            <a href="{{ route('system.roles.index') }}" class="btn btn-light">
                <i class="feather-arrow-left me-1"></i>
                Back to Roles
            </a>
        </div>

        {{-- ================= SECURITY NOTICE ================= --}}
        <div class="alert alert-info d-flex align-items-start mb-4">
            <i class="feather-shield me-2 mt-1"></i>
            <div>
                <strong>Security Notice</strong>
                <p class="mb-0 small">
                    You are assigning system permissions to this role.
                    Only enable permissions that are absolutely necessary.
                    Permission identifiers are hidden to prevent misuse.
                </p>
            </div>
        </div>

        {{-- ================= FLASH & ERRORS ================= --}}
        @if (session('success'))
            <div class="alert alert-success shadow-sm">
                <i class="feather-check-circle me-1"></i>
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger shadow-sm">
                <i class="feather-alert-triangle me-1"></i>
                {{ $errors->first() }}
            </div>
        @endif

	        {{-- ================= FORM ================= --}}
	        <form method="POST" action="{{ route('system.permissions.assign.store', $role->id) }}">
	            @csrf

            @php
                $groupedPermissions = $permissions->groupBy(fn($p) => $p->module ?? 'general');

                $moduleDescriptions = [
                    'users' => 'Manage user accounts, profiles, and access.',
                    'roles' => 'Create and manage user roles.',
                    'permissions' => 'Assign and control system permissions.',
                    'dashboard' => 'Access dashboards and system overviews.',
                    'finance' => 'Manage budgets, funding, and execution.',
                    'budget' => 'Plan and structure budget components.',
                    'reports' => 'View and export analytical reports.',
                    'hr' => 'Manage recruitment, applicants, and HR analytics.',
                    'settings' => 'Configure system-wide settings.',
                    'general' => 'General system permissions.',
                ];
            @endphp

	            {{-- ================= MODULE PERMISSION CARDS ================= --}}
	            @foreach ($groupedPermissions as $module => $modulePermissions)
	                @php
	                    $moduleKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', (string) $module);
	                @endphp
	                <div class="card shadow-sm border-0 mb-4">

	                    {{-- Module Header --}}
	                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
	                        <div>
                            <h6 class="fw-bold text-primary mb-0">
                                <i class="feather-layers me-1"></i>
                                {{ strtoupper($module) }} MODULE
                            </h6>
                            <small class="text-muted">
                                {{ $moduleDescriptions[$module] ?? 'Controls access to features in this module.' }}
	                            </small>
	                        </div>

	                        <div class="d-flex align-items-center gap-2">
	                            <button type="button" class="btn btn-sm btn-outline-primary js-module-toggle"
	                                data-module="{{ $moduleKey }}">
	                                Select all
	                            </button>

	                            <span class="badge bg-secondary">
	                                {{ $modulePermissions->count() }} permissions
	                            </span>
	                        </div>
	                    </div>

	                    {{-- Module Body --}}
	                    <div class="card-body">
	                        <div class="row">

                            @foreach ($modulePermissions as $permission)
                                <div class="col-xl-4 col-lg-6 col-md-6 mb-3">
                                    <label class="form-check form-switch d-flex align-items-start gap-2">

	                                        <input class="form-check-input mt-1" type="checkbox" name="permissions[]"
	                                            data-module="{{ $moduleKey }}"
	                                            value="{{ $permission->id }}"
	                                            {{ $role->permissions->contains('id', $permission->id) ? 'checked' : '' }}>

                                        <span class="form-check-label" title="{{ $permission->name }}">
                                            {{ $permission->description ?? 'No description provided' }}
                                        </span>

                                    </label>
                                </div>
                            @endforeach

                        </div>
                    </div>

                </div>
            @endforeach

	            {{-- ================= ACTION BUTTONS ================= --}}
	            <div class="d-flex justify-content-end gap-2 mt-4">
	                <a href="{{ route('system.roles.index') }}" class="btn btn-light">
	                    Cancel
	                </a>

                <button type="submit" class="btn btn-primary px-4">
                    <i class="feather-save me-1"></i>
                    Save Permissions
                </button>
	            </div>

	        </form>

	        <script>
	            (function () {
	                function getModuleCheckboxes(moduleKey) {
	                    return Array.prototype.slice.call(
	                        document.querySelectorAll('input[type="checkbox"][data-module="' + moduleKey + '"]')
	                    );
	                }

	                function updateToggleLabel(btn) {
	                    var moduleKey = btn.getAttribute('data-module');
	                    if (!moduleKey) return;

	                    var checkboxes = getModuleCheckboxes(moduleKey);
	                    if (!checkboxes.length) return;

	                    var allChecked = checkboxes.every(function (c) { return c.checked; });
	                    btn.textContent = allChecked ? 'Unselect all' : 'Select all';
	                }

	                var toggles = Array.prototype.slice.call(document.querySelectorAll('.js-module-toggle'));

	                toggles.forEach(function (btn) {
	                    updateToggleLabel(btn);

	                    btn.addEventListener('click', function () {
	                        var moduleKey = btn.getAttribute('data-module');
	                        if (!moduleKey) return;

	                        var checkboxes = getModuleCheckboxes(moduleKey);
	                        if (!checkboxes.length) return;

	                        var allChecked = checkboxes.every(function (c) { return c.checked; });
	                        checkboxes.forEach(function (c) {
	                            c.checked = !allChecked;
	                            c.dispatchEvent(new Event('change', { bubbles: true }));
	                        });

	                        updateToggleLabel(btn);
	                    });
	                });

	                document.addEventListener('change', function (e) {
	                    var target = e && e.target;
	                    if (!target || target.tagName !== 'INPUT') return;
	                    if (target.type !== 'checkbox') return;

	                    var moduleKey = target.getAttribute('data-module');
	                    if (!moduleKey) return;

	                    var btn = document.querySelector('.js-module-toggle[data-module="' + moduleKey + '"]');
	                    if (btn) updateToggleLabel(btn);
	                });
	            })();
	        </script>

	    </div>
	@endsection
