@extends('layouts.app')

@section('title', 'ATTP AI Guide')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-bot text-primary me-2"></i>
                    ATTP AI Guide
                </h4>
                <p class="text-muted mb-0">
                    Configure and manage the AI-powered intelligent chat assistant
                </p>
            </div>
        </div>

        {{-- ================= FLASH MESSAGES ================= --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="feather-check-circle me-2"></i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="feather-alert-circle me-2"></i>
                <strong>Please fix the errors below:</strong>
                <ul class="mt-2 mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- ================= SETTINGS FORM ================= --}}
        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="feather-settings me-2"></i>
                            Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('system.attp-ai-guide.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            {{-- Enable/Disable --}}
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enabled" id="enabledToggle"
                                        value="1" {{ $settings->enabled ? 'checked' : '' }}
                                        onchange="toggleFieldStates()">
                                    <label class="form-check-label fw-bold" for="enabledToggle">
                                        Enable ATTP AI Guide
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    Enable or disable the Tawk.to chat widget across your application
                                </small>
                            </div>

                            <hr>

                            {{-- Widget Name --}}
                            <div class="mb-4">
                                <label for="name" class="form-label fw-bold">Widget Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name"
                                    value="{{ old('name', $settings->name ?? 'ATTP AI Guide') }}"
                                    placeholder="Enter widget name" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">Internal name for this widget configuration</small>
                            </div>

                            {{-- Description --}}
                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3" placeholder="Optional description">{{ old('description', $settings->description ?? '') }}</textarea>
                                @error('description')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr>

                            {{-- Service Credentials --}}
                            <div class="mb-4">
                                <label for="tawk_property_id" class="form-label fw-bold">Service ID (Part 1)</label>
                                <input type="text" class="form-control @error('tawk_property_id') is-invalid @enderror"
                                    id="tawk_property_id" name="tawk_property_id"
                                    value="{{ old('tawk_property_id', $settings->tawk_property_id ?? '') }}"
                                    placeholder="Enter service credentials" required>
                                @error('tawk_property_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">
                                    AI service configuration key (first part)
                                </small>
                            </div>

                            {{-- Service Widget ID --}}
                            <div class="mb-4">
                                <label for="tawk_widget_id" class="form-label fw-bold">Service ID (Part 2)</label>
                                <input type="text" class="form-control @error('tawk_widget_id') is-invalid @enderror"
                                    id="tawk_widget_id" name="tawk_widget_id"
                                    value="{{ old('tawk_widget_id', $settings->tawk_widget_id ?? '') }}"
                                    placeholder="Enter service credentials" required>
                                @error('tawk_widget_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">
                                    AI service configuration key (second part)
                                </small>
                            </div>

                            <hr>

                            {{-- Visibility Options --}}
                            <div class="mb-4">
                                <h6 class="fw-bold mb-3">Visibility Settings</h6>

                                <div class="form-check mb-3">
                                    <input class="form-check-input" type="checkbox" name="show_to_authenticated_only"
                                        id="showAuthOnly" value="1"
                                        {{ $settings->show_to_authenticated_only ? 'checked' : '' }}>
                                    <label class="form-check-label" for="showAuthOnly">
                                        Show to Authenticated Users Only
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="show_to_guests" id="showGuests"
                                        value="1" {{ $settings->show_to_guests ? 'checked' : '' }}>
                                    <label class="form-check-label" for="showGuests">
                                        Show to Guest Users
                                    </label>
                                </div>

                                <small class="text-muted d-block mt-2">
                                    Choose who can see the Tawk.to chat widget on your site
                                </small>
                            </div>

                            {{-- Targeted Roles (Optional) --}}
                            <div class="mb-4">
                                <label for="targeted_user_roles" class="form-label fw-bold">
                                    Limit to Specific Roles (Optional)
                                </label>
                                <select id="targeted_user_roles" name="targeted_user_roles[]" multiple
                                    class="form-control @error('targeted_user_roles') is-invalid @enderror">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}"
                                            {{ in_array($role->id, old('targeted_user_roles', $settings->targeted_user_roles ?? [])) ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('targeted_user_roles')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                <small class="text-muted d-block mt-2">
                                    Leave empty to show to all users. Select specific roles to limit visibility.
                                </small>
                            </div>

                            {{-- Welcome Message --}}
                            <div class="mb-4">
                                <label for="welcome_message" class="form-label fw-bold">Welcome Message (Optional)</label>
                                <textarea class="form-control @error('welcome_message') is-invalid @enderror" id="welcome_message"
                                    name="welcome_message" rows="3" placeholder="Custom welcome message for the chat widget">{{ old('welcome_message', $settings->welcome_message ?? '') }}</textarea>
                                @error('welcome_message')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <small class="text-muted">This message can be displayed in the Tawk widget</small>
                            </div>

                            {{-- Form Actions --}}
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-save me-1"></i>
                                    Save Settings
                                </button>
                                <a href="{{ route('system.users.index') }}" class="btn btn-secondary ms-auto">
                                    <i class="feather-arrow-left me-1"></i>
                                    Back to Users
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- ================= INFO CARD ================= --}}
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="feather-info me-2"></i>
                            Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">About ATTP AI Guide</h6>
                        <p class="small mb-3">
                            The ATTP AI Guide is an intelligent assistant that helps users navigate the system, answer
                            questions, and provide real-time support.
                        </p>

                        <hr>

                        <div class="alert alert-info mb-0" role="alert">
                            <i class="feather-lightbulb me-2"></i>
                            <small>
                                <strong>Tip:</strong> The AI Guide will only appear for users matching the visibility
                                settings you configure here.
                            </small>
                        </div>
                    </div>
                </div>

                {{-- Status Card --}}
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-light border-bottom">
                        <h5 class="mb-0">
                            <i class="feather-activity me-2"></i>
                            Status
                        </h5>
                    </div>
                    <div class="card-body">
                        @if ($settings->enabled)
                            <div class="alert alert-success mb-3">
                                <i class="feather-check-circle me-2"></i>
                                <strong>Active</strong>
                                <small class="d-block mt-1">Widget is enabled and will appear for selected users</small>
                            </div>
                        @else
                            <div class="alert alert-warning mb-3">
                                <i class="feather-alert-circle me-2"></i>
                                <strong>Inactive</strong>
                                <small class="d-block mt-1">Widget is disabled. Enable it to show it to users</small>
                            </div>
                        @endif

                        @if ($settings->tawk_property_id && $settings->tawk_widget_id)
                            <div class="badge bg-success mb-2">
                                <i class="feather-check me-1"></i>
                                Credentials Configured
                            </div>
                        @else
                            <div class="badge bg-warning text-dark mb-2">
                                <i class="feather-alert-triangle me-1"></i>
                                Credentials Missing
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Select2 for multi-select roles --}}
    @push('scripts')
        <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Initialize Select2 for roles
                $('#targeted_user_roles').select2({
                    placeholder: 'Select roles (optional)',
                    allowClear: true,
                    width: '100%'
                });

                // Toggle field states based on enabled checkbox
                toggleFieldStates();
            });

            function toggleFieldStates() {
                const isEnabled = document.getElementById('enabledToggle').checked;
                const fields = [
                    'name', 'description', 'tawk_property_id', 'tawk_widget_id',
                    'showAuthOnly', 'showGuests', 'targeted_user_roles', 'welcome_message'
                ];

                fields.forEach(fieldId => {
                    const field = document.getElementById(fieldId);
                    if (field) {
                        field.disabled = !isEnabled;
                    }
                });
            }
        </script>
    @endpush

    @push('styles')
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css">
    @endpush
@endsection
