@extends('layouts.app')

@section('title', 'Bi-Annual Site Visits')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
@endpush

@section('content')
    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-activity"></i> Monitoring &amp; Evaluation</span>
                    <h1>Bi-Annual Site Visits</h1>
                    <p>Plan H1 and H2 monitoring visits, coordinate flexible assessment teams, and complete the
                        configurable Think Tank questionnaire in one auditable workflow.</p>
                </div>
                <div class="basv-hero-actions">
                    @canany(['biannual_site_visits.view', 'biannual_site_visits.approve', 'biannual_site_visits.export'])
                        <a href="{{ route('biannual-site-visits.reports.submitted') }}" class="basv-btn basv-btn-light">
                            <i class="feather-file-text"></i> Submitted Reports
                        </a>
                    @endcanany
                    @can('biannual_site_visits.templates.manage')
                        <a href="{{ route('biannual-site-visits.templates.index') }}" class="basv-btn basv-btn-light">
                            <i class="feather-sliders"></i> Questionnaire Builder
                        </a>
                    @endcan
                    @can('biannual_site_visits.create')
                        <a href="{{ route('biannual-site-visits.create') }}" class="basv-btn basv-btn-light">
                            <i class="feather-plus"></i> Schedule Visit
                        </a>
                    @endcan
                </div>
            </div>

            @if (session('success'))
                <div class="basv-alert success"><i class="feather-check-circle me-1"></i>{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="basv-alert danger">
                    <strong>Please check the following:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="basv-stats">
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-calendar"></i></span>
                    <div><strong>{{ number_format($stats['total'] ?? 0) }}</strong><span>Total visits</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-edit-3"></i></span>
                    <div><strong>{{ number_format($stats['active'] ?? 0) }}</strong><span>In progress</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-clock"></i></span>
                    <div><strong>{{ number_format($stats['submitted'] ?? 0) }}</strong><span>Awaiting review</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-check-circle"></i></span>
                    <div><strong>{{ number_format($stats['approved'] ?? 0) }}</strong><span>Approved</span></div>
                </div>
            </div>

            <div class="basv-card">
                <div class="basv-card-head">
                    <h2><i class="feather-map-pin me-2"></i>Monitoring visit register</h2>
                    <form method="GET" class="d-flex gap-2">
                        <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All statuses</option>
                            @foreach (['draft' => 'Draft', 'returned' => 'Returned', 'submitted' => 'Submitted', 'approved' => 'Approved'] as $value => $label)
                                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <select name="cycle_year" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">All years</option>
                            @foreach ($years ?? [] as $year)
                                <option value="{{ $year }}" @selected((string) request('cycle_year') === (string) $year)>{{ $year }}</option>
                            @endforeach
                        </select>
                    </form>
                </div>

                <div class="table-responsive">
                    @if ($visits->isEmpty())
                        <div class="basv-empty">
                            <i class="feather-map"></i>
                            <strong>No bi-annual visits found</strong>
                            <div class="mt-1">Schedule the first H1 or H2 monitoring visit to begin.</div>
                        </div>
                    @else
                        <table class="basv-table">
                            <thead>
                                <tr>
                                    <th>Visit</th>
                                    <th>Think Tank</th>
                                    <th>Cycle</th>
                                    <th>Team</th>
                                    <th>Progress</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($visits as $visit)
                                    @php
                                        $status = $visit->siteVisit?->status ?: 'draft';
                                        $progress = (float) ($visit->completion_percentage ?? 0);
                                        $teamSpecialisms = (array) data_get($visit->settings, 'team_specialisms', []);
                                        $teamRoster = $visit->siteVisit?->group?->members
                                            ?->map(fn ($member) => [
                                                'id' => (string) $member->user_id,
                                                'name' => $member->user?->name ?: 'Monitoring team member',
                                                'email' => $member->user?->email ?: 'No email recorded',
                                                'specialism' => $teamSpecialisms[(string) $member->user_id] ?? '',
                                                'is_leader' => (string) $member->user_id
                                                    === (string) $visit->siteVisit?->group?->leader_id,
                                            ])
                                            ->values() ?? collect();
                                    @endphp
                                    <tr>
                                        <td>
                                            <a class="basv-record-title"
                                                href="{{ route('biannual-site-visits.show', $visit) }}">
                                                {{ $visit->title ?: 'Monitoring Site Visit' }}
                                            </a>
                                            <span class="basv-record-meta">{{ $visit->reference_number }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->thinkTank?->name ?? '—' }}</strong>
                                            <span class="basv-record-meta">{{ $visit->thinkTank?->country }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->cycleLabel() }}</strong>
                                            <span class="basv-record-meta">
                                                {{ optional($visit->starts_on)->format('d M Y') }}
                                                @if ($visit->ends_on)
                                                    – {{ $visit->ends_on->format('d M Y') }}
                                                @endif
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ $visit->siteVisit?->group?->members?->count() ?? 0 }} members</strong>
                                            <span class="basv-record-meta">
                                                Lead: {{ $visit->siteVisit?->group?->leader?->name ?? 'Not set' }}
                                            </span>
                                        </td>
                                        <td style="min-width: 130px">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small>{{ round($progress) }}%</small>
                                            </div>
                                            <div class="basv-progress"><span style="width: {{ min(100, $progress) }}%"></span></div>
                                        </td>
                                        <td><span class="basv-badge {{ $status }}">{{ str_replace('_', ' ', $status) }}</span></td>
                                        <td class="text-end">
                                            <div class="basv-register-actions">
                                                @if ($canManageTeams)
                                                    <button type="button" class="basv-btn basv-btn-primary"
                                                        data-add-team-members
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#add-team-members-modal"
                                                        data-action="{{ route('biannual-site-visits.team-members.store', $visit) }}"
                                                        data-visit-id="{{ $visit->id }}"
                                                        data-visit-title="{{ $visit->title ?: 'Monitoring Site Visit' }}"
                                                        data-visit-reference="{{ $visit->reference_number }}"
                                                        data-existing-members="{{ $visit->siteVisit?->group?->members?->pluck('user_id')->values()->toJson() ?: '[]' }}">
                                                        <i class="feather-user-plus"></i> Add members
                                                    </button>
                                                    <button type="button" class="basv-btn basv-btn-ghost"
                                                        data-manage-team
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#manage-team-modal"
                                                        data-action="{{ route('biannual-site-visits.team.update', $visit) }}"
                                                        data-visit-id="{{ $visit->id }}"
                                                        data-visit-title="{{ $visit->title ?: 'Monitoring Site Visit' }}"
                                                        data-visit-reference="{{ $visit->reference_number }}"
                                                        data-team-roster="{{ $teamRoster->toJson() }}">
                                                        <i class="feather-settings"></i> Manage team
                                                    </button>
                                                @endif
                                                <a href="{{ route('biannual-site-visits.show', $visit) }}"
                                                    class="basv-btn basv-btn-ghost">
                                                    Open <i class="feather-arrow-right"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if (method_exists($visits, 'links'))
                            <div class="p-3">{{ $visits->withQueryString()->links() }}</div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </main>

    @if ($canManageTeams)
        <div class="modal fade basv-team-modal" id="add-team-members-modal" tabindex="-1"
            aria-labelledby="add-team-members-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="#" class="modal-content basv-page" id="add-team-members-form">
                    @csrf
                    <input type="hidden" name="_team_visit_id" id="team-assignment-visit-id"
                        value="{{ old('_team_visit_id') }}">

                    <div class="modal-header">
                        <div class="basv-modal-heading-icon">
                            <i class="feather-users" aria-hidden="true"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="basv-modal-kicker">Monitoring team assignment</span>
                            <h2 class="modal-title" id="add-team-members-title">Add team members</h2>
                            <div class="basv-modal-meta" id="team-assignment-reference">
                                Select one or more active staff accounts.
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close team member assignment"></button>
                    </div>

                    <div class="modal-body">
                        <div class="basv-team-modal-toolbar">
                            <div class="basv-member-search">
                                <i class="feather-search" aria-hidden="true"></i>
                                <label class="visually-hidden" for="team-member-search">Search staff</label>
                                <input type="search" class="form-control" id="team-member-search"
                                    placeholder="Search by name, email, or system role">
                            </div>
                            <span class="basv-selection-count" id="team-selection-count">0 selected</span>
                        </div>

                        <div class="basv-assignment-note">
                            <i class="feather-mail" aria-hidden="true"></i>
                            <span>Each newly assigned member will receive a queued email with the visit details and a link to open the assignment.</span>
                        </div>

                        <div class="basv-member-options" id="team-member-options">
                            @forelse ($teamAssignableUsers as $staff)
                                @php
                                    $staffSearch = \Illuminate\Support\Str::lower(implode(' ', [
                                        $staff->name,
                                        $staff->email,
                                        $staff->role?->name,
                                    ]));
                                @endphp
                                <div class="basv-member-option" data-member-option
                                    data-search="{{ $staffSearch }}" data-user-id="{{ $staff->id }}">
                                    <label class="basv-member-identity" for="team-member-{{ $staff->id }}">
                                        <input class="form-check-input" type="checkbox"
                                            name="team_members[]" value="{{ $staff->id }}"
                                            id="team-member-{{ $staff->id }}" data-team-member-checkbox>
                                        <span class="basv-member-avatar">
                                            {{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($staff->name ?: 'S', 0, 1)) }}
                                        </span>
                                        <span>
                                            <strong>{{ $staff->name }}</strong>
                                            <small>{{ $staff->email }}</small>
                                            <small>{{ $staff->role?->name ?: 'Staff account' }}</small>
                                        </span>
                                    </label>
                                    <div>
                                        <label class="visually-hidden"
                                            for="team-specialism-{{ $staff->id }}">Specialist role for {{ $staff->name }}</label>
                                        <input class="form-control" name="team_specialisms[{{ $staff->id }}]"
                                            id="team-specialism-{{ $staff->id }}"
                                            list="biannual-specialist-role-options" maxlength="255"
                                            autocomplete="off" placeholder="Choose or enter a role"
                                            data-team-specialism disabled>
                                    </div>
                                </div>
                            @empty
                                <div class="basv-member-empty">
                                    <i class="feather-user-x"></i>
                                    <strong>No active staff accounts are available</strong>
                                    <span>Activate a staff account with a valid email before assigning the team.</span>
                                </div>
                            @endforelse
                        </div>

                        <div class="basv-member-empty" id="team-member-no-results" hidden>
                            <i class="feather-search"></i>
                            <strong>No available staff match this search</strong>
                            <span>Try a different name, email, or role.</span>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="basv-btn basv-btn-ghost"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="basv-btn basv-btn-primary"
                            id="save-team-members" disabled>
                            <i class="feather-user-plus"></i>
                            Add selected members
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <datalist id="biannual-specialist-role-options">
            @foreach ($specialistRoles as $specialistRole)
                <option value="{{ $specialistRole }}"></option>
            @endforeach
        </datalist>

        <div class="modal fade basv-team-modal" id="manage-team-modal" tabindex="-1"
            aria-labelledby="manage-team-title" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                <form method="POST" action="#" class="modal-content basv-page" id="manage-team-form">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_team_manage_visit_id" id="team-management-visit-id"
                        value="{{ old('_team_manage_visit_id') }}">

                    <div class="modal-header">
                        <div class="basv-modal-heading-icon">
                            <i class="feather-user-check" aria-hidden="true"></i>
                        </div>
                        <div class="flex-grow-1">
                            <span class="basv-modal-kicker">Leadership &amp; membership</span>
                            <h2 class="modal-title" id="manage-team-title">Manage monitoring team</h2>
                            <div class="basv-modal-meta" id="team-management-reference">
                                Change the team leader or remove assigned members.
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"
                            aria-label="Close team management"></button>
                    </div>

                    <div class="modal-body">
                        <div class="basv-assignment-note">
                            <i class="feather-info" aria-hidden="true"></i>
                            <span>Edit each specialist role and select exactly one team leader. Members marked for removal cannot be selected as leader, and at least one member must remain assigned.</span>
                        </div>

                        <div class="d-flex align-items-center justify-content-between gap-3 mb-2">
                            <strong class="basv-management-label">Current monitoring team</strong>
                            <span class="basv-selection-count" id="team-remaining-count">0 remaining</span>
                        </div>

                        <div class="basv-manage-team-list" id="manage-team-members"></div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="basv-btn basv-btn-ghost"
                            data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="basv-btn basv-btn-primary"
                            id="save-team-management">
                            <i class="feather-save"></i>
                            Save team changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
@endsection

@if ($canManageTeams)
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById('add-team-members-modal');
                const form = document.getElementById('add-team-members-form');
                const title = document.getElementById('add-team-members-title');
                const reference = document.getElementById('team-assignment-reference');
                const visitIdInput = document.getElementById('team-assignment-visit-id');
                const searchInput = document.getElementById('team-member-search');
                const countLabel = document.getElementById('team-selection-count');
                const saveButton = document.getElementById('save-team-members');
                const noResults = document.getElementById('team-member-no-results');
                const options = [...document.querySelectorAll('[data-member-option]')];
                const triggers = [...document.querySelectorAll('[data-add-team-members]')];

                if (!modalElement || !form) return;

                const updateSelection = () => {
                    const selected = options.filter(option => {
                        const checkbox = option.querySelector('[data-team-member-checkbox]');
                        return checkbox && checkbox.checked && !checkbox.disabled;
                    });

                    countLabel.textContent = `${selected.length} ${selected.length === 1 ? 'member' : 'members'} selected`;
                    const allHaveRoles = selected.every(option =>
                        option.querySelector('[data-team-specialism]')?.value.trim()
                    );
                    saveButton.disabled = selected.length === 0 || !allHaveRoles;
                };

                const filterOptions = () => {
                    const query = (searchInput.value || '').trim().toLowerCase();
                    let visible = 0;

                    options.forEach(option => {
                        const available = option.dataset.unavailable !== '1';
                        const matches = !query || option.dataset.search.includes(query);
                        option.hidden = !available || !matches;
                        if (available && matches) visible += 1;
                    });

                    noResults.hidden = visible > 0;
                };

                const configureModal = trigger => {
                    let existingMembers = [];
                    try {
                        existingMembers = JSON.parse(trigger.dataset.existingMembers || '[]').map(String);
                    } catch (error) {
                        existingMembers = [];
                    }

                    const assigned = new Set(existingMembers);
                    form.action = trigger.dataset.action;
                    visitIdInput.value = trigger.dataset.visitId;
                    title.textContent = `Add members · ${trigger.dataset.visitTitle}`;
                    reference.textContent = trigger.dataset.visitReference;
                    searchInput.value = '';

                    options.forEach(option => {
                        const checkbox = option.querySelector('[data-team-member-checkbox]');
                        const specialism = option.querySelector('[data-team-specialism]');
                        const isAssigned = assigned.has(String(option.dataset.userId));

                        option.dataset.unavailable = isAssigned ? '1' : '0';
                        option.classList.toggle('is-assigned', isAssigned);
                        checkbox.checked = false;
                        checkbox.disabled = isAssigned;
                        specialism.value = '';
                        specialism.disabled = true;
                        specialism.required = false;
                    });

                    filterOptions();
                    updateSelection();
                };

                options.forEach(option => {
                    const checkbox = option.querySelector('[data-team-member-checkbox]');
                    const specialism = option.querySelector('[data-team-specialism]');

                    checkbox?.addEventListener('change', () => {
                        specialism.disabled = !checkbox.checked;
                        specialism.required = checkbox.checked;
                        if (!checkbox.checked) specialism.value = '';
                        updateSelection();
                    });
                    specialism?.addEventListener('input', updateSelection);
                });

                triggers.forEach(trigger => {
                    trigger.addEventListener('click', () => configureModal(trigger));
                });
                searchInput.addEventListener('input', filterOptions);

                modalElement.addEventListener('shown.bs.modal', () => searchInput.focus());

                const failedVisitId = @json((string) old('_team_visit_id'));
                const oldSelected = @json(array_values((array) old('team_members', [])));
                const oldSpecialisms = @json((array) old('team_specialisms', []));

                if (failedVisitId) {
                    const trigger = triggers.find(item => item.dataset.visitId === failedVisitId);
                    if (trigger) {
                        configureModal(trigger);

                        oldSelected.map(String).forEach(userId => {
                            const option = options.find(item => String(item.dataset.userId) === userId);
                            const checkbox = option?.querySelector('[data-team-member-checkbox]');
                            const specialism = option?.querySelector('[data-team-specialism]');

                            if (!checkbox || checkbox.disabled || !specialism) return;
                            checkbox.checked = true;
                            specialism.disabled = false;
                            specialism.required = true;
                            specialism.value = oldSpecialisms[userId] || '';
                        });

                        updateSelection();
                        window.bootstrap?.Modal.getOrCreateInstance(modalElement).show();
                    }
                }

                const manageModalElement = document.getElementById('manage-team-modal');
                const manageForm = document.getElementById('manage-team-form');
                const manageTitle = document.getElementById('manage-team-title');
                const manageReference = document.getElementById('team-management-reference');
                const manageVisitIdInput = document.getElementById('team-management-visit-id');
                const manageList = document.getElementById('manage-team-members');
                const remainingCount = document.getElementById('team-remaining-count');
                const manageSaveButton = document.getElementById('save-team-management');
                const manageTriggers = [...document.querySelectorAll('[data-manage-team]')];

                const updateManagedTeam = () => {
                    const rows = [...manageList.querySelectorAll('[data-managed-member]')];
                    const activeRows = rows.filter(row => {
                        const remove = row.querySelector('[data-remove-managed-member]');
                        return !remove.checked;
                    });
                    let selectedLeader = activeRows.find(row =>
                        row.querySelector('[data-managed-leader]').checked
                    );

                    if (!selectedLeader && activeRows.length) {
                        activeRows[0].querySelector('[data-managed-leader]').checked = true;
                        selectedLeader = activeRows[0];
                    }

                    rows.forEach(row => {
                        const remove = row.querySelector('[data-remove-managed-member]');
                        const leader = row.querySelector('[data-managed-leader]');
                        const specialism = row.querySelector('[data-managed-specialism]');
                        const removed = remove.checked;

                        row.classList.toggle('is-removing', removed);
                        leader.disabled = removed;
                        specialism.disabled = removed;
                        specialism.required = !removed;
                        remove.disabled = !removed && activeRows.length <= 1;
                    });

                    const hasBlankSpecialism = activeRows.some(row =>
                        !row.querySelector('[data-managed-specialism]').value.trim()
                    );
                    remainingCount.textContent = `${activeRows.length} ${activeRows.length === 1 ? 'member' : 'members'} remaining`;
                    manageSaveButton.disabled = activeRows.length === 0
                        || !selectedLeader
                        || hasBlankSpecialism;
                };

                const buildManagedMember = member => {
                    const row = document.createElement('div');
                    row.className = 'basv-manage-member';
                    row.dataset.managedMember = '1';
                    row.dataset.userId = String(member.id);

                    const leaderChoice = document.createElement('label');
                    leaderChoice.className = 'basv-leader-choice';
                    const leaderRadio = document.createElement('input');
                    leaderRadio.type = 'radio';
                    leaderRadio.name = 'group_leader_id';
                    leaderRadio.value = String(member.id);
                    leaderRadio.required = true;
                    leaderRadio.checked = Boolean(member.is_leader);
                    leaderRadio.dataset.managedLeader = '1';
                    const leaderText = document.createElement('span');
                    leaderText.textContent = 'Leader';
                    leaderChoice.append(leaderRadio, leaderText);

                    const identity = document.createElement('div');
                    identity.className = 'basv-managed-identity';
                    const avatar = document.createElement('span');
                    avatar.className = 'basv-member-avatar';
                    avatar.textContent = String(member.name || 'M').trim().charAt(0).toUpperCase();
                    const details = document.createElement('span');
                    const name = document.createElement('strong');
                    name.textContent = member.name || 'Monitoring team member';
                    const email = document.createElement('small');
                    email.textContent = member.email || 'No email recorded';
                    details.append(name, email);
                    identity.append(avatar, details);

                    const roleField = document.createElement('div');
                    roleField.className = 'basv-managed-role';
                    const roleLabel = document.createElement('label');
                    roleLabel.className = 'form-label mb-1';
                    roleLabel.htmlFor = `managed-specialism-${member.id}`;
                    roleLabel.textContent = 'Specialist role';
                    const roleInput = document.createElement('input');
                    roleInput.type = 'text';
                    roleInput.className = 'form-control';
                    roleInput.id = `managed-specialism-${member.id}`;
                    roleInput.name = `team_specialisms[${member.id}]`;
                    roleInput.setAttribute('list', 'biannual-specialist-role-options');
                    roleInput.maxLength = 255;
                    roleInput.autocomplete = 'off';
                    roleInput.placeholder = 'Choose or enter a role';
                    roleInput.required = true;
                    roleInput.value = member.specialism || '';
                    roleInput.dataset.managedSpecialism = '1';
                    roleField.append(roleLabel, roleInput);

                    const removeChoice = document.createElement('label');
                    removeChoice.className = 'basv-remove-choice';
                    const removeCheckbox = document.createElement('input');
                    removeCheckbox.type = 'checkbox';
                    removeCheckbox.name = 'remove_members[]';
                    removeCheckbox.value = String(member.id);
                    removeCheckbox.dataset.removeManagedMember = '1';
                    const removeIcon = document.createElement('i');
                    removeIcon.className = 'feather-user-minus';
                    const removeText = document.createElement('span');
                    removeText.textContent = 'Remove';
                    removeChoice.append(removeCheckbox, removeIcon, removeText);

                    leaderRadio.addEventListener('change', updateManagedTeam);
                    removeCheckbox.addEventListener('change', updateManagedTeam);
                    roleInput.addEventListener('input', updateManagedTeam);
                    row.append(leaderChoice, identity, roleField, removeChoice);

                    return row;
                };

                const configureManageModal = trigger => {
                    let roster = [];
                    try {
                        roster = JSON.parse(trigger.dataset.teamRoster || '[]');
                    } catch (error) {
                        roster = [];
                    }

                    manageForm.action = trigger.dataset.action;
                    manageVisitIdInput.value = trigger.dataset.visitId;
                    manageTitle.textContent = `Manage team · ${trigger.dataset.visitTitle}`;
                    manageReference.textContent = trigger.dataset.visitReference;
                    manageList.replaceChildren(...roster.map(buildManagedMember));
                    updateManagedTeam();
                };

                manageTriggers.forEach(trigger => {
                    trigger.addEventListener('click', () => configureManageModal(trigger));
                });

                const failedManageVisitId = @json((string) old('_team_manage_visit_id'));
                const oldManagedLeader = @json((string) old('group_leader_id'));
                const oldRemovedMembers = @json(array_values((array) old('remove_members', [])));
                const oldManagedSpecialisms = @json((array) old('team_specialisms', []));

                if (failedManageVisitId && manageModalElement && manageForm) {
                    const trigger = manageTriggers.find(
                        item => item.dataset.visitId === failedManageVisitId
                    );

                    if (trigger) {
                        configureManageModal(trigger);
                        const removed = new Set(oldRemovedMembers.map(String));

                        manageList.querySelectorAll('[data-managed-member]').forEach(row => {
                            const userId = String(row.dataset.userId);
                            const leader = row.querySelector('[data-managed-leader]');
                            const remove = row.querySelector('[data-remove-managed-member]');
                            const specialism = row.querySelector('[data-managed-specialism]');
                            leader.checked = userId === oldManagedLeader;
                            remove.checked = removed.has(userId);
                            if (Object.prototype.hasOwnProperty.call(oldManagedSpecialisms, userId)) {
                                specialism.value = oldManagedSpecialisms[userId] || '';
                            }
                        });

                        updateManagedTeam();
                        window.bootstrap?.Modal.getOrCreateInstance(manageModalElement).show();
                    }
                }
            });
        </script>
    @endpush
@endif
