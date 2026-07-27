@extends('layouts.app')

@section('title', 'Schedule Bi-Annual Site Visit')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
@endpush

@section('content')
    @php
        $defaultSpecialisms = $specialistRoles;
        $pendingTeamMembers = is_array(old('new_team_members'))
            ? old('new_team_members')
            : [];
        $oldTeamMembers = old('team_members');
        $hasOldTeam = is_array($oldTeamMembers);
        $initialTeamMembers = $hasOldTeam
            ? array_map(
                static fn ($member) => is_scalar($member) ? (string) $member : '',
                array_values($oldTeamMembers)
            )
            : [''];
        if ($initialTeamMembers === []) {
            $initialTeamMembers = [''];
        }
        $oldTeamSpecialisms = old('team_specialisms');
        $initialTeamSpecialisms = is_array($oldTeamSpecialisms)
            ? array_map(
                static fn ($specialism) => is_scalar($specialism) ? (string) $specialism : '',
                array_values($oldTeamSpecialisms)
            )
            : [];
        $initialSelectedMembers = array_values(array_filter(
            $initialTeamMembers,
            static fn ($member) => filled($member)
        ));
        $initialSelectedCount = count(array_unique($initialSelectedMembers));
        $initialHasDuplicates = $initialSelectedCount !== count($initialSelectedMembers);
        $initialTeamComplete = count($initialTeamMembers) >= 1
            && $initialSelectedCount === count($initialTeamMembers)
            && ! $initialHasDuplicates;
        $eligibleTeamMemberCount = $users->count();
    @endphp

    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-calendar"></i> New monitoring cycle</span>
                    <h1>Schedule a Bi-Annual Site Visit</h1>
                    <p>Select the Think Tank and published questionnaire, then build the monitoring team from all
                        active staff accounts. One selected member must be the team lead.</p>
                </div>
                <div class="basv-hero-actions">
                    <a href="{{ route('biannual-site-visits.index') }}" class="basv-btn basv-btn-light">
                        <i class="feather-arrow-left"></i> Back to visits
                    </a>
                </div>
            </div>

            @if ($errors->any())
                <div class="basv-alert danger">
                    <strong>The visit could not be scheduled.</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('biannual-site-visits.store') }}" id="basv-create-form">
                @csrf

                <div class="basv-card">
                    <div class="basv-card-head">
                        <h2><i class="feather-clipboard me-2"></i>Visit details</h2>
                        <span class="basv-badge">Step 1 of 2</span>
                    </div>
                    <div class="basv-card-body">
                        <div class="basv-form-grid">
                            <div>
                                <label class="form-label" for="think_tank_member_id">Think Tank</label>
                                <select class="form-select" id="think_tank_member_id" name="think_tank_member_id" required>
                                    <option value="">Select a Think Tank</option>
                                    @foreach ($thinkTanks as $thinkTank)
                                        <option value="{{ $thinkTank->id }}" @selected(old('think_tank_member_id') === $thinkTank->id)>
                                            {{ $thinkTank->name }}{{ $thinkTank->country ? ' — '.$thinkTank->country : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="form-label" for="template_id">Published questionnaire</label>
                                <select class="form-select" id="template_id" name="template_id" required>
                                    <option value="">Select a published template</option>
                                    @foreach ($templates as $template)
                                        <option value="{{ $template->id }}" @selected(old('template_id') === $template->id)>
                                            {{ $template->name }} · v{{ $template->version }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="d-flex flex-wrap gap-2 mt-2">
                                    <a href="#" class="basv-btn basv-btn-ghost"
                                        id="preview-questionnaire"
                                        data-url-template="{{ route('biannual-site-visits.templates.preview', '__TEMPLATE__') }}"
                                        aria-disabled="true" target="_blank" rel="noopener">
                                        <i class="feather-eye" aria-hidden="true"></i> Preview questionnaire
                                    </a>
                                    <a href="#" class="basv-btn basv-btn-primary"
                                        id="download-questionnaire-pdf"
                                        data-url-template="{{ route('biannual-site-visits.templates.preview.pdf', '__TEMPLATE__') }}"
                                        aria-disabled="true" target="_blank" rel="noopener">
                                        <i class="feather-download" aria-hidden="true"></i> Download PDF
                                    </a>
                                </div>
                                <div class="basv-help" id="questionnaire-preview-help">
                                    Select both a Think Tank and questionnaire to apply the correct portfolio watermark.
                                </div>
                                @if ($templates->isEmpty())
                                    <div class="basv-help text-danger">Publish a questionnaire template before scheduling a visit.</div>
                                @endif
                            </div>
                            <div>
                                <label class="form-label" for="cycle_year">Cycle year</label>
                                <input class="form-control" id="cycle_year" name="cycle_year" type="number"
                                    min="2020" max="2100" value="{{ old('cycle_year', now()->year) }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="cycle_half">Monitoring period</label>
                                <select class="form-select" id="cycle_half" name="cycle_half" required>
                                    <option value="H1" @selected(old('cycle_half', now()->month <= 6 ? 'H1' : 'H2') === 'H1')>H1 · January–June</option>
                                    <option value="H2" @selected(old('cycle_half', now()->month <= 6 ? 'H1' : 'H2') === 'H2')>H2 · July–December</option>
                                </select>
                            </div>
                            <div class="basv-field-full">
                                <label class="form-label" for="title">Visit title</label>
                                <input class="form-control" id="title" name="title" maxlength="255"
                                    value="{{ old('title', 'Bi-Annual Monitoring Site Visit') }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="starts_on">Start date</label>
                                <input class="form-control" id="starts_on" name="starts_on" type="date"
                                    value="{{ old('starts_on') }}" required>
                            </div>
                            <div>
                                <label class="form-label" for="ends_on">End date</label>
                                <input class="form-control" id="ends_on" name="ends_on" type="date"
                                    value="{{ old('ends_on') }}" required>
                            </div>
                            <div class="basv-field-full">
                                <label class="form-label" for="location">Visit location</label>
                                <input class="form-control" id="location" name="location" maxlength="255"
                                    value="{{ old('location') }}" placeholder="City, office, or site address">
                            </div>
                            <div class="basv-field-full">
                                <label class="form-label" for="objectives">Objectives and preparation notes</label>
                                <textarea class="form-control" id="objectives" name="objectives"
                                    placeholder="Describe the purpose, scope, and documents the team should prepare.">{{ old('objectives') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="basv-card">
                    <div class="basv-card-head">
                        <div>
                            <h2><i class="feather-users me-2"></i>Monitoring team</h2>
                            <div class="basv-help">Select from all active staff accounts, assign one of the approved specialist roles, and choose a team lead.</div>
                        </div>
                        <span class="basv-badge">Step 2 of 2</span>
                    </div>
                    <div class="basv-card-body">
                        <div class="mb-3">
                            <label class="form-label" for="group_name">Team name</label>
                            <input class="form-control" id="group_name" name="group_name" maxlength="255"
                                value="{{ old('group_name', 'Bi-Annual Monitoring Team') }}" required>
                        </div>

                        @if ($eligibleTeamMemberCount < 1)
                            <div class="basv-alert">
                                No active staff accounts are currently available. Use <strong>Add new staff account</strong>
                                below to create and assign the first team member.
                            </div>
                        @endif

                        <div class="basv-team-builder">
                            <div class="basv-team-toolbar">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <strong id="team-member-count" aria-live="polite">
                                            {{ $initialSelectedCount.' '.\Illuminate\Support\Str::plural('member', $initialSelectedCount).' selected' }}
                                        </strong>
                                        <span class="basv-badge" id="team-completion-badge">
                                            {{ $initialTeamComplete ? 'Team ready' : 'Needs attention' }}
                                        </span>
                                    </div>
                                    <div class="basv-help mt-1" id="team-builder-status" aria-live="polite">
                                        @if ($initialTeamComplete)
                                            The team selection is valid. Add more staff if needed, or choose the lead.
                                        @elseif (count($initialSelectedMembers) < count($initialTeamMembers))
                                            Select a team member before adding the next row.
                                        @elseif ($initialHasDuplicates)
                                            Replace duplicate team members before continuing.
                                        @else
                                            Select at least one active staff member.
                                        @endif
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="basv-btn basv-btn-ghost" id="add-team-member"
                                        @disabled(
                                            count($initialSelectedMembers) < count($initialTeamMembers)
                                            || $initialHasDuplicates
                                        )>
                                        <i class="feather-plus" aria-hidden="true"></i> Add team row
                                    </button>
                                    <button type="button" class="basv-btn basv-btn-primary" id="show-new-staff-form"
                                        aria-expanded="false" aria-controls="new-staff-panel">
                                        <i class="feather-user-plus" aria-hidden="true"></i> Add new staff account
                                    </button>
                                </div>
                            </div>
                            <div class="basv-progress basv-team-progress" aria-hidden="true">
                                <span id="team-progress-bar"
                                    style="width: {{ count($initialTeamMembers) > 0 ? ($initialSelectedCount / count($initialTeamMembers)) * 100 : 0 }}%"></span>
                            </div>

                            <div class="basv-team-grid" id="team-members">
                                @foreach ($initialTeamMembers as $position => $selectedMember)
                                    @include('biannual-site-visits.partials.team-member-row', [
                                        'position' => $position,
                                        'selectedMember' => $selectedMember,
                                        'specialism' => $hasOldTeam
                                            ? ($initialTeamSpecialisms[$position] ?? '')
                                            : ($defaultSpecialisms[$position] ?? ''),
                                        'pendingTeamMembers' => $pendingTeamMembers,
                                    ])
                                @endforeach
                            </div>
                        </div>

                        <div class="basv-new-staff mt-3" id="new-staff-panel" hidden>
                            <div>
                                <strong>Add a staff member without leaving this visit</strong>
                                <div class="basv-help">
                                    An active ATTP staff account will be created when the visit is scheduled.
                                    The staff member will receive their temporary login details and the team assignment by email.
                                </div>
                            </div>
                            <div class="basv-form-grid mt-3">
                                <div>
                                    <label class="form-label" for="new_staff_name">Full name</label>
                                    <input class="form-control" id="new_staff_name" maxlength="255"
                                        autocomplete="name" placeholder="Staff member's full name">
                                </div>
                                <div>
                                    <label class="form-label" for="new_staff_email">Email address</label>
                                    <input class="form-control" id="new_staff_email" type="email" maxlength="255"
                                        autocomplete="email" placeholder="name@example.org">
                                </div>
                            </div>
                            <div class="basv-help text-danger" id="new-staff-error" role="alert" hidden></div>
                            <div class="d-flex flex-wrap justify-content-end gap-2 mt-3">
                                <button type="button" class="basv-btn basv-btn-ghost" id="cancel-new-staff">Cancel</button>
                                <button type="button" class="basv-btn basv-btn-primary" id="add-new-staff-to-team">
                                    <i class="feather-user-check" aria-hidden="true"></i> Add staff member to team
                                </button>
                            </div>
                        </div>
                        <div id="pending-staff-inputs">
                            @foreach ($pendingTeamMembers as $pendingKey => $pendingMember)
                                <input type="hidden" name="new_team_members[{{ $pendingKey }}][name]"
                                    value="{{ is_array($pendingMember) ? ($pendingMember['name'] ?? '') : '' }}">
                                <input type="hidden" name="new_team_members[{{ $pendingKey }}][email]"
                                    value="{{ is_array($pendingMember) ? ($pendingMember['email'] ?? '') : '' }}">
                            @endforeach
                        </div>

                        <div class="mt-3">
                            <label class="form-label" for="group_leader_id">Team lead</label>
                            <select class="form-select" id="group_leader_id" name="group_leader_id" required>
                                <option value="">Choose one of the selected members</option>
                            </select>
                            <div class="basv-help">Any selected active staff member can lead. The lead receives permission to submit the consolidated questionnaire.</div>
                        </div>

                        <template id="team-member-row-template">
                            @include('biannual-site-visits.partials.team-member-row', [
                                'position' => '__INDEX__',
                                'selectedMember' => '',
                                'specialism' => '',
                                'pendingTeamMembers' => $pendingTeamMembers,
                            ])
                        </template>
                        <noscript>
                            <div class="basv-alert danger mt-3 mb-0">
                                JavaScript is required to build the monitoring team and choose its lead.
                            </div>
                        </noscript>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mb-4">
                    <a href="{{ route('biannual-site-visits.index') }}" class="basv-btn basv-btn-ghost">Cancel</a>
                    <button class="basv-btn basv-btn-primary" id="schedule-visit-button" type="submit"
                        data-questionnaire-ready="{{ $templates->isNotEmpty() ? '1' : '0' }}"
                        @disabled($templates->isEmpty() || ! $initialTeamComplete)>
                        <i class="feather-check"></i> Schedule visit
                    </button>
                </div>
            </form>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const minimumMembers = 1;
            const defaultSpecialisms = @json($defaultSpecialisms);
            const initialPendingStaff = @json($pendingTeamMembers);
            const teamMembers = document.getElementById('team-members');
            const rowTemplate = document.getElementById('team-member-row-template');
            const addButton = document.getElementById('add-team-member');
            const leaderSelect = document.getElementById('group_leader_id');
            const countLabel = document.getElementById('team-member-count');
            const completionBadge = document.getElementById('team-completion-badge');
            const statusText = document.getElementById('team-builder-status');
            const progressBar = document.getElementById('team-progress-bar');
            const scheduleButton = document.getElementById('schedule-visit-button');
            const questionnaireReady = scheduleButton.dataset.questionnaireReady === '1';
            const thinkTankSelect = document.getElementById('think_tank_member_id');
            const templateSelect = document.getElementById('template_id');
            const previewLink = document.getElementById('preview-questionnaire');
            const pdfLink = document.getElementById('download-questionnaire-pdf');
            const previewHelp = document.getElementById('questionnaire-preview-help');
            const newStaffPanel = document.getElementById('new-staff-panel');
            const showNewStaffButton = document.getElementById('show-new-staff-form');
            const cancelNewStaffButton = document.getElementById('cancel-new-staff');
            const addNewStaffButton = document.getElementById('add-new-staff-to-team');
            const newStaffName = document.getElementById('new_staff_name');
            const newStaffEmail = document.getElementById('new_staff_email');
            const newStaffError = document.getElementById('new-staff-error');
            const pendingStaffInputs = document.getElementById('pending-staff-inputs');
            const pendingStaff = new Map(
                Object.entries(initialPendingStaff || {}).map(([key, member]) => [
                    key,
                    {
                        name: String(member?.name || '').trim(),
                        email: String(member?.email || '').trim().toLowerCase(),
                    },
                ])
            );
            let preferredLeader = @json(old('group_leader_id'));

            const rows = () => [...teamMembers.querySelectorAll('[data-team-row]')];
            const memberSelects = () => rows().map(row => row.querySelector('.team-member-select'));
            const teamState = () => {
                const currentRows = rows();
                const selectedMembers = memberSelects().map(select => select.value).filter(Boolean);
                const uniqueMembers = new Set(selectedMembers);
                const selectedSpecialisms = currentRows
                    .map(row => row.querySelector('.team-specialism-input').value)
                    .filter(Boolean);

                return {
                    rowCount: currentRows.length,
                    selectedCount: uniqueMembers.size,
                    hasBlank: selectedMembers.length < currentRows.length,
                    hasBlankSpecialism: selectedSpecialisms.length < currentRows.length,
                    hasDuplicates: uniqueMembers.size !== selectedMembers.length,
                    complete: currentRows.length >= minimumMembers
                        && uniqueMembers.size === currentRows.length
                        && selectedMembers.length === currentRows.length
                        && selectedSpecialisms.length === currentRows.length,
                };
            };

            function reindexRows() {
                const currentRows = rows();

                currentRows.forEach((row, index) => {
                    const memberSelect = row.querySelector('.team-member-select');
                    const memberLabel = row.querySelector('.team-member-label');
                    const specialismInput = row.querySelector('.team-specialism-input');
                    const specialismLabel = row.querySelector('.team-specialism-label');
                    const removeButton = row.querySelector('[data-remove-team-member]');

                    row.querySelector('[data-team-number]').textContent = index + 1;
                    memberSelect.id = `team_member_${index}`;
                    memberLabel.htmlFor = memberSelect.id;
                    memberLabel.textContent = index === 0 ? 'Monitoring officer' : 'Monitoring specialist';
                    specialismInput.id = `team_role_${index}`;
                    specialismLabel.htmlFor = specialismInput.id;
                    removeButton.disabled = currentRows.length <= minimumMembers;
                    removeButton.setAttribute('aria-label', `Remove team member ${index + 1}`);
                });
            }

            function addPendingOption(select, key, member) {
                const reference = `new:${key}`;
                if ([...select.options].some(option => option.value === reference)) return;

                const option = new Option(
                    `${member.name} — ${member.email} · New staff account`,
                    reference
                );
                option.dataset.canLead = '1';
                option.dataset.email = member.email;
                option.dataset.pendingStaff = '1';
                select.add(option);
            }

            function hydratePendingOptions(select) {
                pendingStaff.forEach((member, key) => addPendingOption(select, key, member));
            }

            function refreshTeamOptions() {
                const selects = memberSelects();
                const selected = selects.map(select => select.value).filter(Boolean);

                selects.forEach(current => {
                    hydratePendingOptions(current);
                    [...current.options].forEach(option => {
                        if (!option.value) return;
                        option.disabled = option.value !== current.value && selected.includes(option.value);
                    });
                });

                const currentLeader = leaderSelect.value || preferredLeader;
                leaderSelect.innerHTML = '<option value="">Choose one of the selected members</option>';
                const addedLeaders = new Set();

                selects.forEach(select => {
                    if (!select.value || addedLeaders.has(select.value)) return;
                    const option = select.options[select.selectedIndex];
                    if (option.dataset.canLead !== '1') return;
                    leaderSelect.add(new Option(option.text, option.value, false, option.value === currentLeader));
                    addedLeaders.add(select.value);
                });

                preferredLeader = leaderSelect.value;
            }

            function refreshBuilder() {
                reindexRows();
                refreshTeamOptions();

                const state = teamState();

                countLabel.textContent = `${state.selectedCount} ${state.selectedCount === 1 ? 'member' : 'members'} selected`;
                completionBadge.textContent = state.complete ? 'Team ready' : 'Needs attention';
                if (state.complete) {
                    statusText.textContent = 'The team selection is valid. Add more staff if needed, or choose the lead.';
                } else if (state.hasBlank) {
                    statusText.textContent = 'Select a team member before adding the next row.';
                } else if (state.hasBlankSpecialism) {
                    statusText.textContent = 'Select a specialist role for every team member.';
                } else if (state.hasDuplicates) {
                    statusText.textContent = 'Replace duplicate team members before continuing.';
                } else {
                    statusText.textContent = 'Select at least one active staff member.';
                }
                progressBar.style.width = `${(state.selectedCount / Math.max(state.rowCount, 1)) * 100}%`;
                addButton.disabled = state.hasBlank
                    || state.hasBlankSpecialism
                    || state.hasDuplicates;
                scheduleButton.disabled = !questionnaireReady
                    || !state.complete
                    || !leaderSelect.value;
            }

            function refreshQuestionnaireLinks() {
                const templateId = templateSelect.value;
                const thinkTankId = thinkTankSelect.value;
                const ready = Boolean(templateId && thinkTankId);

                [previewLink, pdfLink].forEach(link => {
                    link.setAttribute('aria-disabled', ready ? 'false' : 'true');
                    link.style.opacity = ready ? '1' : '.55';
                    link.style.pointerEvents = ready ? 'auto' : 'none';
                    link.tabIndex = ready ? 0 : -1;

                    if (!ready) {
                        link.href = '#';

                        return;
                    }

                    const baseUrl = link.dataset.urlTemplate.replace(
                        '__TEMPLATE__',
                        encodeURIComponent(templateId)
                    );
                    const url = new URL(baseUrl, window.location.origin);
                    url.searchParams.set('think_tank_member_id', thinkTankId);
                    link.href = url.toString();
                });

                previewHelp.textContent = ready
                    ? 'The preview and PDF will use the selected Think Tank’s portfolio name as the watermark.'
                    : 'Select both a Think Tank and questionnaire to apply the correct portfolio watermark.';
            }

            function addTeamMember() {
                const state = teamState();
                if (state.hasBlank || state.hasBlankSpecialism || state.hasDuplicates) {
                    const incompleteRow = rows().find(row =>
                        !row.querySelector('.team-member-select').value
                        || !row.querySelector('.team-specialism-input').value
                    );
                    incompleteRow
                        ?.querySelector(
                            incompleteRow.querySelector('.team-member-select').value
                                ? '.team-specialism-input'
                                : '.team-member-select'
                        )
                        ?.focus();
                    return;
                }

                teamMembers.append(rowTemplate.content.cloneNode(true));
                const currentRows = rows();
                const newRow = currentRows[currentRows.length - 1];
                hydratePendingOptions(newRow.querySelector('.team-member-select'));
                const assignedSpecialisms = new Set(
                    currentRows
                        .slice(0, -1)
                        .map(row => row.querySelector('.team-specialism-input').value.trim())
                        .filter(Boolean)
                );
                newRow.querySelector('.team-specialism-input').value = defaultSpecialisms.find(
                    specialism => !assignedSpecialisms.has(specialism)
                ) || '';
                refreshBuilder();
                newRow.querySelector('.team-member-select').focus();
            }

            function setNewStaffPanel(open) {
                newStaffPanel.hidden = !open;
                showNewStaffButton.setAttribute('aria-expanded', open ? 'true' : 'false');
                newStaffError.hidden = true;
                newStaffError.textContent = '';

                if (open) {
                    newStaffName.focus();
                } else {
                    newStaffName.value = '';
                    newStaffEmail.value = '';
                }
            }

            function addPendingStaffInputs(key, member) {
                const wrapper = document.createElement('span');
                wrapper.dataset.pendingStaffInputs = key;

                const nameInput = document.createElement('input');
                nameInput.type = 'hidden';
                nameInput.name = `new_team_members[${key}][name]`;
                nameInput.value = member.name;

                const emailInput = document.createElement('input');
                emailInput.type = 'hidden';
                emailInput.name = `new_team_members[${key}][email]`;
                emailInput.value = member.email;

                wrapper.append(nameInput, emailInput);
                pendingStaffInputs.append(wrapper);
            }

            function addNewStaffToTeam() {
                const member = {
                    name: newStaffName.value.trim(),
                    email: newStaffEmail.value.trim().toLowerCase(),
                };

                if (!member.name) {
                    newStaffError.textContent = 'Enter the staff member’s full name.';
                    newStaffError.hidden = false;
                    newStaffName.focus();
                    return;
                }

                if (!newStaffEmail.checkValidity() || !member.email) {
                    newStaffError.textContent = 'Enter a valid staff email address.';
                    newStaffError.hidden = false;
                    newStaffEmail.focus();
                    return;
                }

                const emailAlreadyExists = [...document.querySelectorAll('.team-member-select option[data-email]')]
                    .some(option => option.dataset.email.toLowerCase() === member.email);
                if (emailAlreadyExists) {
                    newStaffError.textContent = 'This email is already in the staff list. Select that account instead.';
                    newStaffError.hidden = false;
                    newStaffEmail.focus();
                    return;
                }

                const key = `staff_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
                const reference = `new:${key}`;
                pendingStaff.set(key, member);
                addPendingStaffInputs(key, member);

                let targetRow = rows().find(row => !row.querySelector('.team-member-select').value);
                if (!targetRow) {
                    teamMembers.append(rowTemplate.content.cloneNode(true));
                    targetRow = rows()[rows().length - 1];
                    const assignedSpecialisms = new Set(
                        rows()
                            .slice(0, -1)
                            .map(row => row.querySelector('.team-specialism-input').value)
                            .filter(Boolean)
                    );
                    targetRow.querySelector('.team-specialism-input').value = defaultSpecialisms.find(
                        specialism => !assignedSpecialisms.has(specialism)
                    ) || '';
                }

                memberSelects().forEach(select => addPendingOption(select, key, member));
                targetRow.querySelector('.team-member-select').value = reference;
                setNewStaffPanel(false);
                refreshBuilder();

                if (!targetRow.querySelector('.team-specialism-input').value) {
                    targetRow.querySelector('.team-specialism-input').focus();
                }
            }

            addButton.addEventListener('click', addTeamMember);
            showNewStaffButton.addEventListener('click', () => setNewStaffPanel(newStaffPanel.hidden));
            cancelNewStaffButton.addEventListener('click', () => setNewStaffPanel(false));
            addNewStaffButton.addEventListener('click', addNewStaffToTeam);
            newStaffEmail.addEventListener('keydown', event => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    addNewStaffToTeam();
                }
            });
            thinkTankSelect.addEventListener('change', refreshQuestionnaireLinks);
            templateSelect.addEventListener('change', refreshQuestionnaireLinks);
            leaderSelect.addEventListener('change', () => {
                preferredLeader = leaderSelect.value;
                refreshBuilder();
            });
            teamMembers.addEventListener('change', event => {
                if (event.target.matches('.team-member-select, .team-specialism-input')) refreshBuilder();
            });
            teamMembers.addEventListener('click', event => {
                const removeButton = event.target.closest('[data-remove-team-member]');
                if (!removeButton || rows().length <= minimumMembers) return;

                const row = removeButton.closest('[data-team-row]');
                const removedIndex = rows().indexOf(row);
                const removedMember = row.querySelector('.team-member-select').value;
                if (removedMember && removedMember === leaderSelect.value) preferredLeader = '';
                row.remove();
                refreshBuilder();

                const remainingRows = rows();
                remainingRows[Math.min(removedIndex, remainingRows.length - 1)]
                    ?.querySelector('.team-member-select')
                    ?.focus();
            });

            document.getElementById('basv-create-form').addEventListener('submit', event => {
                const state = teamState();
                if (state.complete && leaderSelect.value) return;
                event.preventDefault();
                statusText.textContent = state.complete
                    ? 'Choose a team lead before scheduling the visit.'
                    : 'Select a distinct active staff member and specialist role for every team row.';
                const incompleteRow = rows().find(row =>
                    !row.querySelector('.team-member-select').value
                    || !row.querySelector('.team-specialism-input').value
                );
                (
                    state.complete
                        ? leaderSelect
                        : incompleteRow?.querySelector(
                            incompleteRow.querySelector('.team-member-select').value
                                ? '.team-specialism-input'
                                : '.team-member-select'
                        ) || addButton
                ).focus();
            });

            refreshBuilder();
            refreshQuestionnaireLinks();
        })();
    </script>
@endpush
