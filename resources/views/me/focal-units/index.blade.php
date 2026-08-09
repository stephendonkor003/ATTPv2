@extends('layouts.app')

@section('title', 'M&E Focal Unit Control Centre')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.focal-units.partials.styles')
@endpush

@section('content')
@php
    $activeFilterCount = collect($filters)
        ->except(['contact_id', 'sort', 'per_page'])
        ->reject(fn ($value, $key) => $key === 'activity' && $value === 'active')
        ->filter(fn ($value) => filled($value))
        ->count();
    $preservedFilters = collect($filters)
        ->except('contact_id')
        ->reject(fn ($value) => $value === null || $value === '')
        ->reject(fn ($value, $key) => ($key === 'activity' && $value === 'active') || ($key === 'sort' && $value === 'organization') || ($key === 'per_page' && (int) $value === 25))
        ->all();
    $contactUrl = fn ($contact) => route('budget.me.focal-units.index', array_merge($preservedFilters, ['contact_id' => $contact->id])).'#focal-detail';
    $initials = function ($name) {
        return str(collect(preg_split('/\s+/', trim((string) $name)))->filter()->take(2)->map(fn ($part) => mb_substr($part, 0, 1))->join(''))->upper();
    };
    $selectedAccount = $selectedContact?->resolvedAccount;
    $oldContext = old('form_context');
@endphp

<div class="mel-focal">
    <header class="fu-header">
        <div class="fu-header-copy">
            <span class="fu-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>M&amp;E Focal Unit control centre</h1>
            <p>A controlled responsibility register for organizational M&amp;E contacts, formal portal-account assignments, reporting readiness and access-risk follow-up.</p>
        </div>
        <div class="fu-header-side">
            <span class="fu-generated">Register updated {{ $generatedAt->format('d M Y, H:i') }}</span>
            <div class="fu-actions">
                <a class="fu-btn fu-btn-header" href="{{ route('budget.me.consolidated-reports.index') }}">Submission register</a>
                @if($canManageUsers)<a class="fu-btn fu-btn-header" href="{{ route('system.think-tank-users.index') }}">Think Tank Users</a>@endif
                <a class="fu-btn fu-btn-header" href="{{ route('budget.me.focal-units.pdf', $exportQuery) }}">Download PDF</a>
                @if($canManage)<a class="fu-btn fu-btn-header fu-btn-solid" href="#focal-add" data-focal-open="focal-add">Add contact</a>@endif
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="fu-alert success" role="status"><span>OK</span><div><strong>Focal register updated</strong><p>{{ session('success') }}</p></div></div>
    @endif
    @if($errors->any())
        <div class="fu-alert danger" role="alert"><span>!</span><div><strong>The focal-unit change could not be completed</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <aside class="fu-governance" aria-label="Focal-unit governance guidance">
        <span class="fu-governance-mark">RESP</span>
        <div><strong>Contact records and login authority are controlled separately</strong><p>A matching email is not enough for reporting access. The contact must be mapped to an active organization, formally linked, assigned an M&amp;E-capable portal role and have an enabled, non-blacklisted account.</p></div>
    </aside>

    <section class="fu-metrics" aria-label="Focal-unit readiness summary">
        <a class="fu-metric" style="--metric:#075c7a" href="{{ route('budget.me.focal-units.index', $preservedFilters) }}"><span>Organizations mapped</span><strong>{{ number_format($metrics['mapped_organizations']) }} / {{ number_format($metrics['organization_target']) }}</strong><small>Active ATTP think tanks in the register</small></a>
        <a class="fu-metric" style="--metric:#187459" href="{{ route('budget.me.focal-units.index', array_merge($preservedFilters, ['readiness' => 'ready'])) }}"><span>Ready to report</span><strong>{{ number_format($metrics['ready_organizations']) }}</strong><small>{{ number_format($metrics['readiness_rate'], 1) }}% organization readiness</small></a>
        <a class="fu-metric" style="--metric:#3f8aa0" href="#focal-register"><span>Active contacts</span><strong>{{ number_format($metrics['active_contacts']) }}</strong><small>{{ number_format($metrics['primary_contacts']) }} designated primary</small></a>
        <a class="fu-metric" style="--metric:#6b63a8" href="#focal-analytics"><span>Account coverage</span><strong>{{ number_format($metrics['account_coverage'], 1) }}%</strong><small>{{ number_format($metrics['account_matches']) }} matching platform accounts</small></a>
        <a class="fu-metric" style="--metric:#b8791f" href="{{ route('budget.me.focal-units.index', array_merge($preservedFilters, ['readiness' => 'link_required'])) }}"><span>Links required</span><strong>{{ number_format($metrics['link_required']) }}</strong><small>Accounts found but not formally linked</small></a>
        <a class="fu-metric" style="--metric:#ae4d49" href="{{ route('budget.me.focal-units.index', array_merge($preservedFilters, ['readiness' => 'disabled'])) }}"><span>Access attention</span><strong>{{ number_format($metrics['disabled'] + $metrics['blacklisted']) }}</strong><small>{{ number_format($metrics['disabled']) }} disabled · {{ number_format($metrics['blacklisted']) }} blacklisted</small></a>
    </section>

    <details class="fu-panel fu-filter" @if($activeFilterCount > 0) open @endif>
        <summary class="fu-panel-head"><div><h2>Search and responsibility scope</h2><p>Metrics, graphs, the register and PDF export all use the same selected scope.</p></div><div class="fu-summary-right"><span class="fu-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span><span class="fu-chevron">⌄</span></div></summary>
        <div class="fu-panel-body">
            <form method="GET" action="{{ route('budget.me.focal-units.index') }}" class="fu-filter-grid">
                <div class="fu-field fu-field-wide"><label for="focal-search">Search focal register</label><input class="form-control" id="focal-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Organization, focal person, email, country, account or notes"><small>Search is case-insensitive and applies before pagination.</small></div>
                <div class="fu-field"><label for="focal-consortium">Consortium</label><select class="form-select" id="focal-consortium" name="consortium"><option value="">All consortia</option>@foreach($consortia as $consortium)<option value="{{ $consortium }}" @selected($filters['consortium'] === $consortium)>{{ $consortium }}</option>@endforeach</select></div>
                <div class="fu-field"><label for="focal-organization">Think tank</label><select class="form-select" id="focal-organization" name="think_tank_id"><option value="">All active think tanks</option>@foreach($thinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string) $filters['think_tank_id'] === (string) $thinkTank->id)>{{ $thinkTank->name }}</option>@endforeach</select></div>
                <div class="fu-field"><label for="focal-readiness">Reporting readiness</label><select class="form-select" id="focal-readiness" name="readiness"><option value="">All readiness states</option>@foreach($readinessOptions as $value => $option)<option value="{{ $value }}" @selected($filters['readiness'] === $value)>{{ $option['label'] }}</option>@endforeach</select></div>
                <div class="fu-field"><label for="focal-activity">Register state</label><select class="form-select" id="focal-activity" name="activity"><option value="active" @selected($filters['activity'] === 'active')>Active contacts</option><option value="archived" @selected($filters['activity'] === 'archived')>Archived contacts</option><option value="all" @selected($filters['activity'] === 'all')>Active and archived</option></select></div>
                <div class="fu-field"><label for="focal-primary">Contact priority</label><select class="form-select" id="focal-primary" name="primary"><option value="">Primary and secondary</option><option value="primary" @selected($filters['primary'] === 'primary')>Primary contacts only</option><option value="secondary" @selected($filters['primary'] === 'secondary')>Secondary contacts only</option></select></div>
                <div class="fu-field"><label for="focal-sort">Sort register</label><select class="form-select" id="focal-sort" name="sort"><option value="organization" @selected($filters['sort'] === 'organization')>Organization</option><option value="consortium" @selected($filters['sort'] === 'consortium')>Consortium</option><option value="contact" @selected($filters['sort'] === 'contact')>Focal person</option><option value="readiness" @selected($filters['sort'] === 'readiness')>Readiness priority</option><option value="newest" @selected($filters['sort'] === 'newest')>Recently added</option><option value="updated" @selected($filters['sort'] === 'updated')>Recently updated</option></select></div>
                <div class="fu-field"><label for="focal-page-size">Rows per page</label><select class="form-select" id="focal-page-size" name="per_page">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }} rows</option>@endforeach</select></div>
                <div class="fu-filter-actions"><p><strong>Operational tip:</strong> filter by readiness to produce an action list, then export the matching PDF for follow-up.</p><div class="fu-actions"><a class="fu-btn fu-btn-secondary" href="{{ route('budget.me.focal-units.index') }}">Clear filters</a><button class="fu-btn fu-btn-primary" type="submit">Apply scope</button></div></div>
            </form>
        </div>
    </details>

    <section class="fu-analytics" id="focal-analytics" aria-label="Focal-unit analytics">
        <article class="fu-panel fu-chart-panel"><div class="fu-panel-head"><div><h2>Readiness distribution</h2><p>Every contact has one mutually exclusive readiness state.</p></div><span class="fu-badge">{{ number_format($metrics['contacts']) }} records</span></div><div id="focal-readiness-chart" class="fu-chart" role="img" aria-label="Donut chart of focal contacts by readiness status"></div></article>
        <article class="fu-panel fu-chart-panel fu-chart-panel-wide"><div class="fu-panel-head"><div><h2>Consortium responsibility coverage</h2><p>Mapped and fully ready organizations compared with contact volume.</p></div><span class="fu-badge">{{ number_format($metrics['consortia']) }} consortia</span></div><div id="focal-consortium-chart" class="fu-chart" role="img" aria-label="Grouped bar chart of focal-unit coverage by consortium"></div></article>
        <article class="fu-panel fu-chart-panel"><div class="fu-panel-head"><div><h2>Geographic representation</h2><p>Registered organizations and focal contacts across countries.</p></div><span class="fu-badge">Top 10</span></div><div id="focal-country-chart" class="fu-chart fu-chart-tall" role="img" aria-label="Horizontal bar chart of focal-unit coverage by country"></div></article>
    </section>

    <section class="fu-panel fu-register" id="focal-register" aria-labelledby="focal-register-title">
        <div class="fu-panel-head"><div><h2 id="focal-register-title">Focal responsibility register</h2><p>{{ number_format($contacts->total()) }} matching {{ str('contact')->plural($contacts->total()) }} · use the readiness column as the operational action queue.</p></div><div class="fu-actions"><a class="fu-btn fu-btn-small fu-btn-secondary" href="{{ route('budget.me.focal-units.pdf', $exportQuery) }}">Export filtered PDF</a>@if($canManage)<a class="fu-btn fu-btn-small fu-btn-primary" href="#focal-add" data-focal-open="focal-add">Add contact</a>@endif</div></div>
        @if($contacts->isNotEmpty())
            <div class="fu-table-wrap"><table class="fu-table"><thead><tr><th>Consortium / organization</th><th>Focal contact</th><th>Platform account</th><th>Reporting readiness</th><th>Responsibility</th><th>Register source</th><th>Updated</th><th>Actions</th></tr></thead><tbody>
            @foreach($contacts as $contact)
                @php
                    $account = $contact->resolvedAccount;
                @endphp
                <tr class="{{ $selectedContact && (string) $selectedContact->id === (string) $contact->id ? 'selected' : '' }}" data-focal-row data-href="{{ $contactUrl($contact) }}">
                    <td><div class="fu-organization-cell"><span>{{ $initials($contact->think_tank_label) }}</span><div><a href="{{ $contactUrl($contact) }}">{{ $contact->think_tank_label }}</a><strong>{{ $contact->thinkTank?->name ?: 'Organization mapping required' }}</strong><small>{{ $contact->consortium_name }}@if($contact->thinkTank?->country) · {{ $contact->thinkTank->country }}@endif</small></div></div></td>
                    <td><strong class="fu-cell-strong">{{ $contact->focal_person_name }}</strong><a class="fu-email" href="mailto:{{ $contact->email }}">{{ $contact->email }}</a>@if($contact->is_primary)<span class="fu-primary">Primary</span>@endif</td>
                    <td>@if($account)<strong class="fu-cell-strong">{{ $account->name }}</strong><small class="fu-cell-note">{{ $account->email }}</small><span class="fu-account-type">{{ $account->thinkTankAccessLabel() }}</span>@else<span class="fu-source danger">No account match</span><small class="fu-cell-note">Create an account with the exact focal email.</small>@endif</td>
                    <td><span class="fu-status {{ $contact->readiness_tone }}">{{ $contact->readiness_label }}</span><small class="fu-cell-note">{{ $contact->is_active ? 'Active register record' : 'Archived register record' }}</small></td>
                    <td><strong class="fu-cell-strong">{{ $contact->is_primary ? 'Primary contact' : 'Supporting contact' }}</strong><small class="fu-cell-note">{{ $contact->user_id ? 'Formally linked' : 'Not formally linked' }}</small></td>
                    <td><span class="fu-source">{{ $contact->source ?: 'Platform maintained' }}</span></td>
                    <td><strong class="fu-cell-strong">{{ $contact->updated_at?->format('d M Y') }}</strong><small class="fu-cell-note">{{ $contact->updated_at?->format('H:i') }}</small></td>
                    <td><div class="fu-row-actions"><a href="{{ $contactUrl($contact) }}">Inspect</a><a href="mailto:{{ $contact->email }}">Email</a><a href="{{ route('budget.me.focal-units.pdf', ['contact_id' => $contact->id, 'activity' => $contact->is_active ? 'active' : 'all']) }}">PDF</a></div></td>
                </tr>
            @endforeach
            </tbody></table></div>
            <div class="fu-register-footer"><span>Showing {{ number_format($contacts->firstItem()) }}–{{ number_format($contacts->lastItem()) }} of {{ number_format($contacts->total()) }}</span><span>Horizontal scrolling is available on smaller screens.</span></div>
            @if($contacts->hasPages())<div class="fu-pagination">{{ $contacts->links() }}</div>@endif
        @else
            <div class="fu-empty"><span>FOC</span><strong>No focal contacts match this scope</strong><p>Clear filters, inspect archived records or add a controlled contact.</p>@if($canManage)<a class="fu-btn fu-btn-primary" href="#focal-add" data-focal-open="focal-add">Add focal contact</a>@endif</div>
        @endif
    </section>

    <section class="fu-panel fu-detail" id="focal-detail" aria-labelledby="focal-detail-title">
        @if($selectedContact)
            <div class="fu-detail-head"><div class="fu-detail-avatar">{{ $initials($selectedContact->focal_person_name) }}</div><div><span class="fu-eyebrow-dark">Selected responsibility record</span><h2 id="focal-detail-title">{{ $selectedContact->focal_person_name }}</h2><p>{{ $selectedContact->think_tank_label }} · {{ $selectedContact->consortium_name }}</p></div><span class="fu-status {{ $selectedContact->readiness_tone }}">{{ $selectedContact->readiness_label }}</span></div>
            <div class="fu-detail-actions"><a class="fu-btn fu-btn-primary" href="mailto:{{ $selectedContact->email }}">Email focal contact</a><a class="fu-btn fu-btn-secondary" href="{{ route('budget.me.focal-units.pdf', ['contact_id' => $selectedContact->id, 'activity' => $selectedContact->is_active ? 'active' : 'all']) }}">Control sheet PDF</a>@if($canManage)<a class="fu-btn fu-btn-secondary" href="#focal-edit" data-focal-open="focal-edit">Edit record</a>@endif @if($canManageUsers && $selectedAccount)<a class="fu-btn fu-btn-secondary" href="{{ route('system.think-tank-users.index', ['q' => $selectedAccount->email]) }}">Manage user</a>@endif</div>

            <div class="fu-detail-grid">
                <section><h3>Responsibility record</h3><dl class="fu-metadata"><div><dt>Consortium</dt><dd>{{ $selectedContact->consortium_name }}</dd></div><div><dt>Short label</dt><dd>{{ $selectedContact->think_tank_label }}</dd></div><div><dt>Mapped organization</dt><dd>{{ $selectedContact->thinkTank?->name ?: 'Not mapped' }}</dd></div><div><dt>Country</dt><dd>{{ $selectedContact->thinkTank?->country ?: 'Not recorded' }}</dd></div><div><dt>Contact priority</dt><dd>{{ $selectedContact->is_primary ? 'Primary contact' : 'Supporting contact' }}</dd></div><div><dt>Register state</dt><dd>{{ $selectedContact->is_active ? 'Active' : 'Archived' }}</dd></div><div><dt>Source</dt><dd>{{ $selectedContact->source ?: 'Platform maintained' }}</dd></div><div><dt>Last updated</dt><dd>{{ $selectedContact->updated_at?->format('d M Y, H:i') }}</dd></div></dl>@if($selectedContact->notes)<div class="fu-notes"><strong>Responsibility notes</strong><p>{{ $selectedContact->notes }}</p></div>@endif</section>
                <section><div class="fu-section-head"><div><h3>Platform account control</h3><p>Account identity, organization assignment and access health.</p></div><span class="fu-status {{ $selectedContact->readiness_tone }}">{{ $selectedContact->readiness_label }}</span></div>
                    @if($selectedAccount)
                        <div class="fu-account-card"><div class="fu-detail-avatar small">{{ $initials($selectedAccount->name) }}</div><div><strong>{{ $selectedAccount->name }}</strong><p>{{ $selectedAccount->email }}</p></div><span>{{ $selectedAccount->thinkTankAccessLabel() }}</span></div>
                        <div class="fu-control-checks"><span class="{{ $selectedAccount->user_type === 'think_tank' ? 'pass' : 'fail' }}"><i></i>Think tank account</span><span class="{{ (string) $selectedAccount->think_tank_member_id === (string) $selectedContact->think_tank_member_id ? 'pass' : 'fail' }}"><i></i>Organization matches</span><span class="{{ in_array($selectedAccount->think_tank_access_level, [\App\Models\User::THINK_TANK_ACCESS_ADMIN, \App\Models\User::THINK_TANK_ACCESS_ME], true) ? 'pass' : 'fail' }}"><i></i>M&amp;E role assigned</span><span class="{{ !$selectedAccount->is_disabled && !$selectedAccount->is_blacklisted ? 'pass' : 'fail' }}"><i></i>Login in good standing</span><span class="{{ (string) $selectedContact->user_id === (string) $selectedAccount->id ? 'pass' : 'fail' }}"><i></i>Formal register link</span></div>
                    @else
                        <div class="fu-empty fu-empty-compact"><span>USR</span><strong>No matching platform account</strong><p>Create a Think Tank User with the exact email {{ $selectedContact->email }}, then return to complete the formal link.</p>@if($canManageUsers)<a class="fu-btn fu-btn-small fu-btn-primary" href="{{ route('system.think-tank-users.index') }}">Open Think Tank Users</a>@endif</div>
                    @endif
                </section>
            </div>

            @if($canManage)
                <div class="fu-lifecycle-actions"><div><strong>Responsibility and account actions</strong><p>Formal linking assigns the account to this organization as an M&amp;E Officer. Archiving preserves register history and requires the account link to be removed first.</p></div><div class="fu-actions">
                    @if($selectedContact->can_link_account && $selectedAccount)<form method="POST" action="{{ route('budget.me.focal-units.link-account', $selectedContact) }}" data-confirm="Assign this account to {{ $selectedContact->think_tank_label }} as an M&E Officer and create the formal focal-register link?">@csrf<input type="hidden" name="user_id" value="{{ $selectedAccount->id }}"><button class="fu-btn fu-btn-success" type="submit">Link M&amp;E account</button></form>@endif
                    @if($selectedContact->user_id)<form method="POST" action="{{ route('budget.me.focal-units.unlink-account', $selectedContact) }}" data-confirm="Remove the formal focal-register link? This will not delete or disable the user account.">@csrf<button class="fu-btn fu-btn-secondary" type="submit">Unlink register</button></form>@endif
                    @if($canManageUsers && $selectedAccount?->is_disabled)<form method="POST" action="{{ route('system.users.unblock-login', $selectedAccount) }}" data-confirm="Enable login only after confirming this is the approved focal account?">@csrf<button class="fu-btn fu-btn-success" type="submit">Enable login</button></form>@endif
                    @if($selectedContact->is_active)<form method="POST" action="{{ route('budget.me.focal-units.destroy', $selectedContact) }}" data-confirm="Archive this focal contact while retaining the responsibility history?">@csrf @method('DELETE')<button class="fu-btn fu-btn-danger" type="submit" @disabled($selectedContact->user_id)>Archive contact</button></form>@else<form method="POST" action="{{ route('budget.me.focal-units.restore', $selectedContact) }}" data-confirm="Restore this contact to the active focal register?">@csrf<button class="fu-btn fu-btn-success" type="submit">Restore contact</button></form>@endif
                </div></div>
            @endif
        @else
            <div class="fu-empty"><span>SEL</span><strong>Select a focal contact</strong><p>Choose a register row to inspect organization mapping, contact authority and portal-account readiness.</p></div>
        @endif
    </section>

    @if($canManage)
        <section class="fu-modal {{ $oldContext === 'focal_create' ? 'is-open' : '' }}" id="focal-add" role="dialog" aria-modal="true" aria-labelledby="focal-add-title" data-focal-modal @if($oldContext === 'focal_create') data-auto-open @endif>
            <a class="fu-modal-backdrop" href="#" data-focal-close aria-label="Close add contact dialog"></a><div class="fu-modal-card"><header><div><span>Controlled responsibility intake</span><h2 id="focal-add-title">Add M&amp;E focal contact</h2><p>Create the responsibility record first. A matching platform account can then be formally linked after its identity and organization are verified.</p></div><a class="fu-modal-close" href="#" data-focal-close aria-label="Close">×</a></header>
                <form method="POST" action="{{ route('budget.me.focal-units.store') }}">@csrf<input type="hidden" name="form_context" value="focal_create"><div class="fu-modal-body"><div class="fu-form-grid">
                    <div class="fu-field"><label for="add-consortium">Consortium *</label><input class="form-control" id="add-consortium" name="consortium_name" value="{{ old('consortium_name') }}" maxlength="120" required placeholder="e.g. BRIDGE"></div>
                    <div class="fu-field fu-field-wide"><label for="add-organization">Mapped think tank</label><select class="form-select" id="add-organization" name="think_tank_member_id" data-focal-organization><option value="">Map later</option>@foreach($thinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" data-name="{{ $thinkTank->name }}" @selected((string) old('think_tank_member_id') === (string) $thinkTank->id)>{{ $thinkTank->name }}@if($thinkTank->country) · {{ $thinkTank->country }}@endif</option>@endforeach</select><small>Mapping is required before portal-account linking.</small></div>
                    <div class="fu-field"><label for="add-label">Short organization label *</label><input class="form-control" id="add-label" name="think_tank_label" value="{{ old('think_tank_label') }}" maxlength="160" required placeholder="e.g. ACET" data-focal-label></div>
                    <div class="fu-field"><label for="add-person">Focal person *</label><input class="form-control" id="add-person" name="focal_person_name" value="{{ old('focal_person_name') }}" maxlength="180" required autocomplete="name"></div>
                    <div class="fu-field fu-field-wide"><label for="add-email">Official email *</label><input class="form-control" id="add-email" type="email" name="email" value="{{ old('email') }}" maxlength="255" required autocomplete="email"><small>This must exactly match the platform account email used for formal linking.</small></div>
                    <div class="fu-field fu-field-full"><label for="add-notes">Responsibility notes</label><textarea class="form-control" id="add-notes" name="notes" rows="3" maxlength="2000" placeholder="Role, reporting responsibility, coverage or approved contact instructions.">{{ old('notes') }}</textarea></div>
                    <label class="fu-check fu-field-full"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))><span><strong>Primary organizational focal contact</strong><small>Selecting this automatically demotes any other active primary contact for the mapped organization.</small></span></label>
                </div></div><footer><a class="fu-btn fu-btn-secondary" href="#" data-focal-close>Cancel</a><button class="fu-btn fu-btn-primary" type="submit">Add focal contact</button></footer></form>
            </div>
        </section>

        @if($selectedContact)
            <section class="fu-modal {{ $oldContext === 'focal_update' ? 'is-open' : '' }}" id="focal-edit" role="dialog" aria-modal="true" aria-labelledby="focal-edit-title" data-focal-modal @if($oldContext === 'focal_update') data-auto-open @endif>
                <a class="fu-modal-backdrop" href="#" data-focal-close aria-label="Close edit contact dialog"></a><div class="fu-modal-card"><header><div><span>Controlled responsibility record</span><h2 id="focal-edit-title">Edit focal contact</h2><p>If an account is formally linked, unlink it before changing the official email or mapped organization.</p></div><a class="fu-modal-close" href="#" data-focal-close aria-label="Close">×</a></header>
                    <form method="POST" action="{{ route('budget.me.focal-units.update', $selectedContact) }}">@csrf @method('PUT')<input type="hidden" name="form_context" value="focal_update"><div class="fu-modal-body"><div class="fu-form-grid">
                        <div class="fu-field"><label for="edit-consortium">Consortium *</label><input class="form-control" id="edit-consortium" name="consortium_name" value="{{ old('consortium_name', $selectedContact->consortium_name) }}" maxlength="120" required></div>
                        <div class="fu-field fu-field-wide"><label for="edit-organization">Mapped think tank</label><select class="form-select" id="edit-organization" name="think_tank_member_id"><option value="">Not mapped</option>@foreach($thinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" @selected((string) old('think_tank_member_id', $selectedContact->think_tank_member_id) === (string) $thinkTank->id)>{{ $thinkTank->name }}@if($thinkTank->country) · {{ $thinkTank->country }}@endif</option>@endforeach</select></div>
                        <div class="fu-field"><label for="edit-label">Short organization label *</label><input class="form-control" id="edit-label" name="think_tank_label" value="{{ old('think_tank_label', $selectedContact->think_tank_label) }}" maxlength="160" required></div>
                        <div class="fu-field"><label for="edit-person">Focal person *</label><input class="form-control" id="edit-person" name="focal_person_name" value="{{ old('focal_person_name', $selectedContact->focal_person_name) }}" maxlength="180" required></div>
                        <div class="fu-field fu-field-wide"><label for="edit-email">Official email *</label><input class="form-control" id="edit-email" type="email" name="email" value="{{ old('email', $selectedContact->email) }}" maxlength="255" required></div>
                        <div class="fu-field fu-field-full"><label for="edit-notes">Responsibility notes</label><textarea class="form-control" id="edit-notes" name="notes" rows="3" maxlength="2000">{{ old('notes', $selectedContact->notes) }}</textarea></div>
                        <label class="fu-check fu-field-full"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary', $selectedContact->is_primary))><span><strong>Primary organizational focal contact</strong><small>Only one active primary is maintained per mapped organization.</small></span></label>
                    </div></div><footer><a class="fu-btn fu-btn-secondary" href="#" data-focal-close>Cancel</a><button class="fu-btn fu-btn-primary" type="submit">Save contact details</button></footer></form>
                </div>
            </section>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
(function () {
    const ready = function () {
        const body = document.body;
        const openModal = function (id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            document.querySelectorAll('[data-focal-modal].is-open').forEach(item => item.classList.remove('is-open'));
            modal.classList.add('is-open'); body.classList.add('fu-modal-open');
            window.setTimeout(() => modal.querySelector('input:not([type="hidden"]),select,textarea,button')?.focus(), 20);
        };
        const closeModal = function (modal) {
            modal?.classList.remove('is-open');
            if (!document.querySelector('[data-focal-modal].is-open')) body.classList.remove('fu-modal-open');
            if (window.location.hash?.startsWith('#focal-')) history.replaceState(null, '', window.location.pathname + window.location.search);
        };
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-focal-open]');
            if (opener) { event.preventDefault(); openModal(opener.dataset.focalOpen); return; }
            const closer = event.target.closest('[data-focal-close]');
            if (closer) { event.preventDefault(); closeModal(closer.closest('[data-focal-modal]')); return; }
            const row = event.target.closest('[data-focal-row]');
            if (row && !event.target.closest('a,button,input,select,textarea,form')) window.location.href = row.dataset.href;
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(document.querySelector('[data-focal-modal].is-open')); });
        document.querySelectorAll('[data-focal-modal][data-auto-open]').forEach(modal => openModal(modal.id));
        if (window.location.hash === '#focal-add') openModal('focal-add');
        if (window.location.hash === '#focal-edit') openModal('focal-edit');
        document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', function (event) { if (!window.confirm(this.dataset.confirm)) event.preventDefault(); }));

        const organization = document.querySelector('[data-focal-organization]');
        const label = document.querySelector('[data-focal-label]');
        organization?.addEventListener('change', function () {
            if (!label || label.value.trim()) return;
            const name = this.selectedOptions?.[0]?.dataset.name || '';
            label.value = name.split(/\s+/).filter(Boolean).map(word => word[0]).join('').slice(0, 12).toUpperCase();
        });

        const readiness = @json($charts['readiness']);
        const consortia = @json($charts['consortia']);
        const countries = @json($charts['countries']);
        const baseUrl = @json(route('budget.me.focal-units.index'));
        const baseFilters = @json($preservedFilters);
        const openFiltered = function (extra) { const parameters = new URLSearchParams({...baseFilters, ...extra}); window.location.href = baseUrl + '?' + parameters.toString(); };
        const render = function (selector, options) {
            const target = document.querySelector(selector); if (!target) return;
            if (typeof window.ApexCharts !== 'function') { target.innerHTML = '<div class="fu-chart-unavailable"><strong>Chart unavailable</strong><span>The register and PDF remain available.</span></div>'; return; }
            new window.ApexCharts(target, options).render();
        };
        const base = {chart:{fontFamily:'Inter, Arial, sans-serif',foreColor:'#657980',toolbar:{show:false},animations:{speed:380}},grid:{borderColor:'#e4ecee',strokeDashArray:3},tooltip:{theme:'light'},dataLabels:{style:{fontSize:'10px',fontWeight:700}},noData:{text:'No focal-unit data in this scope'}};
        render('#focal-readiness-chart', {...base,chart:{...base.chart,type:'donut',height:300,events:{dataPointSelection:(_event,_chart,selection)=>{const item=readiness[selection.dataPointIndex];if(item)openFiltered({readiness:item.key});}}},series:readiness.map(item=>item.count),labels:readiness.map(item=>item.label),colors:readiness.map(item=>item.color),stroke:{colors:['#fff'],width:3},dataLabels:{enabled:false},legend:{position:'bottom',fontSize:'10px'},plotOptions:{pie:{donut:{size:'66%',labels:{show:true,total:{show:true,label:'Contacts',formatter:()=>readiness.reduce((sum,item)=>sum+item.count,0)}}}}}});
        render('#focal-consortium-chart', {...base,chart:{...base.chart,type:'bar',height:300,events:{dataPointSelection:(_event,_chart,selection)=>{const item=consortia[selection.dataPointIndex];if(item)openFiltered({consortium:item.key});}}},series:[{name:'Contacts',data:consortia.map(item=>item.contacts)},{name:'Mapped organizations',data:consortia.map(item=>item.mapped)},{name:'Ready organizations',data:consortia.map(item=>item.ready)}],colors:['#8fb7c2','#3f8aa0','#187459'],plotOptions:{bar:{horizontal:false,borderRadius:3,columnWidth:'56%'}},xaxis:{categories:consortia.map(item=>item.label)},yaxis:{min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},dataLabels:{enabled:false},legend:{position:'bottom',fontSize:'10px'}});
        render('#focal-country-chart', {...base,chart:{...base.chart,type:'bar',height:Math.max(300,countries.length*42)},series:[{name:'Organizations',data:countries.map(item=>item.organizations)},{name:'Contacts',data:countries.map(item=>item.contacts)}],colors:['#075c7a','#7eb4c2'],plotOptions:{bar:{horizontal:true,borderRadius:3,barHeight:'62%'}},xaxis:{categories:countries.map(item=>item.label),min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}},yaxis:{labels:{maxWidth:150,style:{fontSize:'10px'}}},dataLabels:{enabled:false},legend:{position:'bottom',fontSize:'10px'}});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, {once:true}); else ready();
})();
</script>
@endpush
