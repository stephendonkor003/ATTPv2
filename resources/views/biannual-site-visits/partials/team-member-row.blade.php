@php
    $rowPosition = is_numeric($position ?? null) ? (int) $position : 0;
    $fieldSuffix = is_numeric($position ?? null) ? (string) $rowPosition : '__INDEX__';
    $selectedMember = (string) ($selectedMember ?? '');
    $specialism = (string) ($specialism ?? '');
    $pendingTeamMembers = (array) ($pendingTeamMembers ?? []);
@endphp

<div class="basv-team-row" data-team-row>
    <span class="basv-team-number" data-team-number>{{ $rowPosition + 1 }}</span>
    <div>
        <label class="form-label mb-1 team-member-label" for="team_member_{{ $fieldSuffix }}">
            {{ $rowPosition === 0 ? 'Monitoring officer' : 'Monitoring specialist' }}
        </label>
        <select class="form-select team-member-select" id="team_member_{{ $fieldSuffix }}"
            name="team_members[]" required>
            <option value="">Select active staff member</option>
            @foreach ($users as $user)
                <option value="{{ $user->id }}" data-can-lead="1"
                    data-email="{{ \Illuminate\Support\Str::lower($user->email) }}"
                    @selected($selectedMember === (string) $user->id)>
                    {{ $user->name }} — {{ $user->email }}{{ $user->role?->name ? ' · '.$user->role->name : '' }}
                </option>
            @endforeach
            @foreach ($pendingTeamMembers as $pendingKey => $pendingMember)
                @php
                    $pendingReference = 'new:'.$pendingKey;
                    $pendingName = is_array($pendingMember) ? ($pendingMember['name'] ?? '') : '';
                    $pendingEmail = is_array($pendingMember) ? ($pendingMember['email'] ?? '') : '';
                @endphp
                <option value="{{ $pendingReference }}" data-can-lead="1"
                    data-email="{{ \Illuminate\Support\Str::lower($pendingEmail) }}" data-pending-staff="1"
                    @selected($selectedMember === $pendingReference)>
                    {{ $pendingName }} — {{ $pendingEmail }} · New staff account
                </option>
            @endforeach
        </select>
    </div>
    <div class="team-role">
        <label class="form-label mb-1 team-specialism-label"
            for="team_role_{{ $fieldSuffix }}">Specialist role</label>
        <select class="form-select team-specialism-input" id="team_role_{{ $fieldSuffix }}"
            name="team_specialisms[]" required>
            <option value="">Select specialist role</option>
            @if ($specialism !== '' && ! in_array($specialism, $specialistRoles, true))
                <option value="{{ $specialism }}" selected>{{ $specialism }}</option>
            @endif
            @foreach ($specialistRoles as $specialistRole)
                <option value="{{ $specialistRole }}" @selected($specialism === $specialistRole)>
                    {{ $specialistRole }}
                </option>
            @endforeach
        </select>
    </div>
    <button type="button" class="basv-team-remove" data-remove-team-member
        title="Remove this team member" aria-label="Remove team member {{ $rowPosition + 1 }}">
        <i class="feather-trash-2" aria-hidden="true"></i>
        <span class="visually-hidden">Remove</span>
    </button>
</div>
