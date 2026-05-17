@extends('layouts.app')

@section('title', 'ATTP AI Guide Settings')

@section('content')
    <div class="nxl-container">
        <div class="page-header mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-bot me-2"></i>ATTP AI Guide Settings</h4>
                <p class="mb-0">Configure the intelligent assistant that helps users navigate and get support.</p>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Please fix the errors below:</strong>
                <ul class="mt-2 mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="feather-settings me-2"></i>Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('system.attp-ai-guide.update') }}" method="POST">
                            @csrf
                            @method('PUT')

                            <div class="mb-4 p-3 bg-light rounded">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enabled" id="enabledToggle"
                                        value="1" {{ old('enabled', $settings->enabled ?? false) ? 'checked' : '' }}
                                        onchange="toggleFieldStates()">
                                    <label class="form-check-label fw-bold" for="enabledToggle">Enable ATTP AI Guide</label>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label for="name" class="form-label fw-bold">Assistant Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    id="name" name="name" value="{{ old('name', $settings->name ?? 'ATTP AI Guide') }}" required>
                                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="mb-4">
                                <label for="description" class="form-label fw-bold">Description</label>
                                <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description"
                                    rows="3">{{ old('description', $settings->description ?? '') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-4">
                                    <label for="tawk_property_id" class="form-label fw-bold">Property ID</label>
                                    <input type="text" class="form-control @error('tawk_property_id') is-invalid @enderror"
                                        id="tawk_property_id" name="tawk_property_id"
                                        value="{{ old('tawk_property_id', $settings->tawk_property_id ?? '') }}">
                                    @error('tawk_property_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                                <div class="col-md-6 mb-4">
                                    <label for="tawk_widget_id" class="form-label fw-bold">Widget ID</label>
                                    <input type="text" class="form-control @error('tawk_widget_id') is-invalid @enderror"
                                        id="tawk_widget_id" name="tawk_widget_id"
                                        value="{{ old('tawk_widget_id', $settings->tawk_widget_id ?? '') }}">
                                    @error('tawk_widget_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                </div>
                            </div>

                            <h6 class="fw-bold text-uppercase text-primary mb-3">Visibility & Access</h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="show_to_authenticated_only"
                                    id="showAuthOnly" value="1"
                                    {{ old('show_to_authenticated_only', $settings->show_to_authenticated_only ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="showAuthOnly">Show to authenticated users only</label>
                            </div>
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="show_to_guests" id="showGuests"
                                    value="1" {{ old('show_to_guests', $settings->show_to_guests ?? false) ? 'checked' : '' }}>
                                <label class="form-check-label" for="showGuests">Show to guest users</label>
                            </div>

                            <div class="mb-4">
                                <label for="targeted_user_roles" class="form-label fw-bold">Limit to Specific Roles</label>
                                <select id="targeted_user_roles" name="targeted_user_roles[]" multiple class="form-control">
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->id }}"
                                            @selected(in_array($role->id, old('targeted_user_roles', $settings->targeted_user_roles ?? [])))>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="welcome_message" class="form-label fw-bold">Welcome Message</label>
                                <textarea class="form-control @error('welcome_message') is-invalid @enderror" id="welcome_message"
                                    name="welcome_message" rows="3">{{ old('welcome_message', $settings->welcome_message ?? '') }}</textarea>
                                @error('welcome_message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-save me-1"></i>Save Settings
                                </button>
                                <a href="{{ route('system.users.index') }}" class="btn btn-secondary ms-auto">
                                    <i class="feather-arrow-left me-1"></i>Back to Users
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="feather-activity me-2"></i>Status</h5>
                    </div>
                    <div class="card-body">
                        @if ($settings->enabled ?? false)
                            <div class="alert alert-success mb-3"><strong>Active</strong><br><small>The assistant is available to selected users.</small></div>
                        @else
                            <div class="alert alert-warning mb-3"><strong>Inactive</strong><br><small>Enable it to make it available.</small></div>
                        @endif

                        @if (($settings->tawk_property_id ?? null) && ($settings->tawk_widget_id ?? null))
                            <span class="badge bg-success">Credentials Configured</span>
                        @else
                            <span class="badge bg-warning text-dark">Credentials Missing</span>
                        @endif
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-0"><i class="feather-info me-2"></i>How It Works</h5>
                    </div>
                    <div class="card-body">
                        <ol class="small mb-0">
                            <li class="mb-2">Enable the guide.</li>
                            <li class="mb-2">Add service credentials.</li>
                            <li class="mb-2">Choose who can see it.</li>
                            <li>Add a welcome message if needed.</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', toggleFieldStates);

        function toggleFieldStates() {
            const enabledToggle = document.getElementById('enabledToggle');
            if (!enabledToggle) return;

            const enabled = enabledToggle.checked;
            ['name', 'description', 'tawk_property_id', 'tawk_widget_id', 'showAuthOnly', 'showGuests', 'targeted_user_roles', 'welcome_message']
                .forEach((id) => {
                    const field = document.getElementById(id);
                    if (field) field.disabled = !enabled;
                });
        }
    </script>
@endpush
