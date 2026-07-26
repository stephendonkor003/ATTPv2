@php
    $rowPosition = is_numeric($position ?? null) ? (int) $position : 0;
    $fieldSuffix = is_numeric($position ?? null) ? (string) $rowPosition : '__INDEX__';
    $selectedMember = (string) ($selectedMember ?? '');
    $specialism = (string) ($specialism ?? '');
@endphp

<div class="basv-team-row" data-team-row>
    <span class="basv-team-number" data-team-number>{{ $rowPosition + 1 }}</span>
    <div>
        <label class="form-label mb-1 team-member-label" for="team_member_{{ $fieldSuffix }}">
            {{ $rowPosition === 0 ? 'Monitoring officer' : 'Monitoring specialist' }}
        </label>
        <select class="form-select team-member-select" id="team_member_{{ $fieldSuffix }}"
            name="team_members[]" required>
            <option value="">Select team member</option>
            @foreach ($users as $user)
                @php
                    $canLead = $user->can('biannual_site_visits.submit')
                        || $user->can('biannual_site_visits.approve');
                @endphp
                <option value="{{ $user->id }}" data-can-lead="{{ $canLead ? '1' : '0' }}"
                    @selected($selectedMember === (string) $user->id)>
                    {{ $user->name }} — {{ $user->email }} · {{ $canLead ? 'Can lead' : 'Member only' }}
                </option>
            @endforeach
        </select>
    </div>
    <div class="team-role">
        <label class="form-label mb-1 team-specialism-label"
            for="team_role_{{ $fieldSuffix }}">Specialism</label>
        <input class="form-control team-specialism-input" id="team_role_{{ $fieldSuffix }}"
            name="team_specialisms[]" maxlength="120" value="{{ $specialism }}"
            placeholder="e.g. Procurement, finance, MEAL">
    </div>
    <button type="button" class="basv-team-remove" data-remove-team-member
        title="Remove this team member" aria-label="Remove team member {{ $rowPosition + 1 }}">
        <i class="feather-trash-2" aria-hidden="true"></i>
        <span class="visually-hidden">Remove</span>
    </button>
</div>
