@php
    $partner = $funder ?? null;
    $portalAccess = (bool) old('has_portal_access', $partner?->has_portal_access);
    $lastContactAt = old('last_contact_at', optional($partner?->last_contact_at)->format('Y-m-d\TH:i'));
@endphp

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Partner Profile</h5>

                <div class="row g-3">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Partner Name *</label>
                        <input name="name" class="form-control" value="{{ old('name', $partner?->name) }}"
                            placeholder="e.g. World Bank, AU, Government of Ghana" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-semibold">Partner Type *</label>
                        <select name="type" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            @foreach (\App\Models\Funder::TYPES as $type)
                                <option value="{{ $type }}" @selected(old('type', $partner?->type) === $type)>
                                    {{ \Illuminate\Support\Str::headline($type) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Default Currency *</label>
                        <input name="currency" class="form-control" value="{{ old('currency', $partner?->currency) }}"
                            placeholder="USD, GHS, EUR" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label fw-semibold">Organization Logo</label>
                        @if($partner?->hasLogo())
                            <div class="d-flex align-items-center gap-3 mb-2">
                                <img src="{{ $partner->getLogoUrl() }}" alt="{{ $partner->name }}"
                                    style="max-height: 76px; max-width: 160px;" class="border rounded p-2 bg-white">
                                <div class="small text-muted">
                                    Current logo
                                </div>
                            </div>
                        @endif
                        <input type="file" name="logo" class="form-control" accept="image/*">
                        <small class="text-muted">PNG, JPG, or SVG up to 2MB.</small>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold mb-3">Primary Contact</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Person</label>
                        <input name="contact_person" class="form-control" value="{{ old('contact_person', $partner?->contact_person) }}"
                            placeholder="e.g. John Doe" data-portal-required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Email</label>
                        <input type="email" name="contact_email" class="form-control"
                            value="{{ old('contact_email', $partner?->contact_email) }}"
                            placeholder="partner@example.com" data-portal-required>
                        <small class="text-muted">Used for direct contact and partner portal login if access is enabled.</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Contact Phone</label>
                        <input name="contact_phone" class="form-control" value="{{ old('contact_phone', $partner?->contact_phone) }}"
                            placeholder="+233 XXX XXX XXX">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Correspondent / Partner Manager</label>
                        <select name="relationship_manager_id" class="form-select">
                            <option value="">-- Select Responsible User --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}"
                                    @selected(old('relationship_manager_id', $partner?->relationship_manager_id) === $user->id)>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">This is the staff member responsible for managing the partner.</small>
                    </div>
                </div>

                <hr class="my-4">

                <h6 class="fw-bold mb-3">Lifecycle & Communication</h6>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Partnership Status</label>
                        <select name="partnership_status" class="form-select">
                            <option value="">-- Select Status --</option>
                            @foreach (\App\Models\Funder::PARTNERSHIP_STATUSES as $status)
                                <option value="{{ $status }}"
                                    @selected(old('partnership_status', $partner?->partnership_status) === $status)>
                                    {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Partnership Started</label>
                        <input type="date" name="partnership_started_at" class="form-control"
                            value="{{ old('partnership_started_at', optional($partner?->partnership_started_at)->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Next Follow-up</label>
                        <input type="date" name="next_follow_up_at" class="form-control"
                            value="{{ old('next_follow_up_at', optional($partner?->next_follow_up_at)->format('Y-m-d')) }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Communication Date</label>
                        <input type="datetime-local" name="last_contact_at" class="form-control" value="{{ $lastContactAt }}">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Last Communication Subject</label>
                        <input name="last_contact_subject" class="form-control"
                            value="{{ old('last_contact_subject', $partner?->last_contact_subject) }}"
                            placeholder="e.g. Budget clarification meeting">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Communication Status</label>
                        <select name="last_contact_status" class="form-select">
                            <option value="">-- Select Status --</option>
                            @foreach (\App\Models\Funder::COMMUNICATION_STATUSES as $status)
                                <option value="{{ $status }}"
                                    @selected(old('last_contact_status', $partner?->last_contact_status) === $status)>
                                    {{ \Illuminate\Support\Str::headline(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Handled / Responded By</label>
                        <select name="last_contact_user_id" class="form-select">
                            <option value="">-- Select User --</option>
                            @foreach($users as $user)
                                <option value="{{ $user->id }}"
                                    @selected(old('last_contact_user_id', $partner?->last_contact_user_id) === $user->id)>
                                    {{ $user->name }} ({{ $user->email }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-12">
                        <label class="form-label fw-semibold">Communication Notes</label>
                        <textarea name="last_contact_notes" class="form-control" rows="4"
                            placeholder="Capture the latest discussion, question raised, commitments made, and follow-up actions.">{{ old('last_contact_notes', $partner?->last_contact_notes) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Portal Access</h5>

                <div class="mb-3 form-check">
                    <input type="checkbox" class="form-check-input" id="has_portal_access"
                           name="has_portal_access" value="1" {{ $portalAccess ? 'checked' : '' }}>
                    <label class="form-check-label" for="has_portal_access">
                        <strong>Grant Partner Portal Access</strong>
                    </label>
                </div>

                <div class="alert alert-info mb-0">
                    <i class="feather-info me-2"></i>
                    Enable this when the partner should log in and monitor the programs it supports.
                </div>

                @if($partner?->portalUser)
                    <div class="mt-3 p-3 rounded-3 bg-light border">
                        <div class="small text-muted text-uppercase mb-1">Current Portal User</div>
                        <div class="fw-semibold">{{ $partner->portalUser->name }}</div>
                        <div class="text-muted">{{ $partner->portalUser->email }}</div>
                    </div>
                @endif
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Internal Notes</h5>
                <textarea name="notes" class="form-control" rows="9"
                    placeholder="Store a CRM-style snapshot of the partner relationship, risks, opportunities, or agreements.">{{ old('notes', $partner?->notes) }}</textarea>
            </div>
        </div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const portalCheckbox = document.getElementById('has_portal_access');
                const contactFields = document.querySelectorAll('[data-portal-required]');

                if (!portalCheckbox) {
                    return;
                }

                const syncPortalRequirements = () => {
                    contactFields.forEach((field) => {
                        field.required = portalCheckbox.checked;
                    });
                };

                portalCheckbox.addEventListener('change', syncPortalRequirements);
                syncPortalRequirements();
            });
        </script>
    @endpush
@endonce
