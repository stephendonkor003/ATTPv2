@extends('layouts.app')

@section('title', 'ATTP M&E Focal Units')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-start gap-3 mb-4"><div><div class="text-muted small fw-semibold text-uppercase">Monitoring &amp; Evaluation</div><h3 class="mb-1">M&amp;E Focal Unit Register</h3><p class="text-muted mb-0">The controlled reporting-responsibility register supplied with the unified tracker: 13 think tanks across three consortia and their focal contacts.</p></div><a href="{{ route('budget.me.consolidated-reports.index') }}" class="btn btn-outline-primary">Submission register</a></div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0 ps-3">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    @php
        $disabledFocalAccounts = $contacts->filter(function ($contact) use ($matchingUsers) {
            $account = $contact->user ?: $matchingUsers->get(strtolower($contact->email));
            return $account && (bool) $account->is_disabled;
        });
        $readyFocalOrganizations = $contacts->filter(function ($contact) use ($matchingUsers) {
            $account = $contact->user ?: $matchingUsers->get(strtolower($contact->email));
            return $account
                && !(bool) $account->is_disabled
                && !(bool) $account->is_blacklisted
                && (string) $account->think_tank_member_id === (string) $contact->think_tank_member_id
                && in_array($account->think_tank_access_level, [
                    \App\Models\User::THINK_TANK_ACCESS_ADMIN,
                    \App\Models\User::THINK_TANK_ACCESS_ME,
                ], true);
        })->pluck('think_tank_member_id')->filter()->unique()->count();
    @endphp

    @if($disabledFocalAccounts->isNotEmpty())
        <div class="alert alert-warning d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-2">
            <div>
                <div class="fw-bold">{{ $disabledFocalAccounts->count() }} focal account(s) cannot sign in</div>
                <div class="small">Their login is disabled. An authorized user administrator must review and enable only the approved accounts.</div>
            </div>
            @can('users.manage')
                <a href="{{ route('system.users.index') }}" class="btn btn-sm btn-dark flex-shrink-0">Open user access management</a>
            @endcan
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Consortia</div><div class="fs-3 fw-bold">{{ $contacts->pluck('consortium_name')->unique()->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Think tanks mapped</div><div class="fs-3 fw-bold">{{ $contacts->pluck('think_tank_member_id')->filter()->unique()->count() }} / 13</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Focal contacts</div><div class="fs-3 fw-bold">{{ $contacts->count() }}</div></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><div class="text-muted small">Organizations ready to sign in</div><div class="fs-3 fw-bold {{ $readyFocalOrganizations>=13?'text-success':'text-warning' }}">{{ $readyFocalOrganizations }} / 13</div></div></div></div>
    </div>

    @can('me.configuration.manage')
    <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h5 class="mb-0">Add focal contact</h5></div><div class="card-body"><form method="POST" action="{{ route('budget.me.focal-units.store') }}" class="row g-3">@csrf
        <div class="col-md-2"><label class="form-label">Consortium</label><input name="consortium_name" class="form-control" required placeholder="BRIDGE"></div>
        <div class="col-md-3"><label class="form-label">Think tank</label><select name="think_tank_member_id" class="form-select"><option value="">Map later</option>@foreach($thinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}">{{ $thinkTank->name }}</option>@endforeach</select></div>
        <div class="col-md-2"><label class="form-label">Short label</label><input name="think_tank_label" class="form-control" required placeholder="ACET"></div>
        <div class="col-md-2"><label class="form-label">Focal person</label><input name="focal_person_name" class="form-control" required></div>
        <div class="col-md-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
        <div class="col-12 d-flex justify-content-between"><label class="form-check"><input type="checkbox" name="is_primary" value="1" class="form-check-input"> Primary contact</label><button class="btn btn-primary">Add contact</button></div>
    </form></div></div>
    @endcan

    @foreach($contacts->groupBy('consortium_name') as $consortium => $consortiumContacts)
        <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white d-flex justify-content-between"><h5 class="mb-0">{{ $consortium }}</h5><span class="badge bg-primary-subtle text-primary">{{ $consortiumContacts->pluck('think_tank_label')->unique()->count() }} organizations · {{ $consortiumContacts->count() }} contacts</span></div><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>Think tank</th><th>Focal person</th><th>Platform account</th><th>Responsibility readiness</th><th class="text-end">Manage</th></tr></thead><tbody>
        @foreach($consortiumContacts as $contact)
            @php($match = $contact->user ?: $matchingUsers->get(strtolower($contact->email)))
            <tr><td><div class="fw-semibold">{{ $contact->think_tank_label }}</div><div class="text-muted small">{{ $contact->thinkTank?->name ?: 'Not mapped to platform organization' }}</div></td><td><div class="fw-semibold">{{ $contact->focal_person_name }} @if($contact->is_primary)<span class="badge bg-success">Primary</span>@endif</div><a href="mailto:{{ $contact->email }}" class="small">{{ $contact->email }}</a>@if($contact->notes)<div class="small text-muted">{{ $contact->notes }}</div>@endif</td><td>@if($match)<div>{{ $match->name }}</div><div class="small text-muted">{{ $match->email }}</div>@if($match->is_disabled)<span class="badge bg-danger mt-1">Login disabled</span>@endif @else<span class="badge bg-light text-danger border">No matching account</span><div class="small text-muted mt-1">Create an account with this exact email, then return here.</div>@endif</td><td>@if($contact->user && !$contact->user->is_disabled && !$contact->user->is_blacklisted && $contact->user->think_tank_member_id===$contact->think_tank_member_id && in_array($contact->user->think_tank_access_level, [\App\Models\User::THINK_TANK_ACCESS_ADMIN, \App\Models\User::THINK_TANK_ACCESS_ME], true))<span class="badge bg-success">Ready to report</span>@elseif($match && $match->is_disabled)<span class="badge bg-danger">Enable login</span>@elseif($match && $contact->think_tank_member_id)<span class="badge bg-warning text-dark">Account found; link required</span>@else<span class="badge bg-light text-danger border">Setup incomplete</span>@endif</td><td class="text-end"><div class="d-flex justify-content-end gap-2">
                @can('me.configuration.manage')
                    @if($match && $contact->think_tank_member_id && (!$contact->user_id || $contact->user?->think_tank_member_id!==$contact->think_tank_member_id || $contact->user?->think_tank_access_level!==\App\Models\User::THINK_TANK_ACCESS_ME))<form method="POST" action="{{ route('budget.me.focal-units.link-account',$contact) }}" onsubmit="return confirm('Assign this account to {{ addslashes($contact->think_tank_label) }} as an M&E Officer?');">@csrf<input type="hidden" name="user_id" value="{{ $match->id }}"><button class="btn btn-sm btn-success">Link M&amp;E account</button></form>@endif
                    @can('users.manage')
                        @if($match && $match->is_disabled)<form method="POST" action="{{ route('system.users.unblock-login', $match) }}" onsubmit="return confirm('Enable login for {{ addslashes($match->name) }} after confirming this is the approved focal account?');">@csrf<button class="btn btn-sm btn-outline-success">Enable login</button></form>@endif
                    @endcan
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#edit-focal-{{ $contact->id }}">Edit</button>
                    <form method="POST" action="{{ route('budget.me.focal-units.destroy',$contact) }}" onsubmit="return confirm('Remove this focal contact?');">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" @disabled($contact->user_id)>Delete</button></form>
                @endcan
            </div></td></tr>
        @endforeach
        </tbody></table></div></div>
    @endforeach

    @can('me.configuration.manage')
        @foreach($contacts as $contact)<div class="modal fade" id="edit-focal-{{ $contact->id }}" tabindex="-1"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit focal contact</h5><button class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="{{ route('budget.me.focal-units.update',$contact) }}"><div class="modal-body row g-3">@csrf @method('PUT')
            <div class="col-6"><label class="form-label">Consortium</label><input name="consortium_name" class="form-control" value="{{ $contact->consortium_name }}" required></div><div class="col-6"><label class="form-label">Short label</label><input name="think_tank_label" class="form-control" value="{{ $contact->think_tank_label }}" required></div>
            <div class="col-12"><label class="form-label">Mapped organization</label><select name="think_tank_member_id" class="form-select"><option value="">Not mapped</option>@foreach($thinkTanks as $thinkTank)<option value="{{ $thinkTank->id }}" @selected($contact->think_tank_member_id===$thinkTank->id)>{{ $thinkTank->name }}</option>@endforeach</select></div>
            <div class="col-6"><label class="form-label">Focal person</label><input name="focal_person_name" class="form-control" value="{{ $contact->focal_person_name }}" required></div><div class="col-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ $contact->email }}" required></div>
            <div class="col-12"><label class="form-label">Notes</label><textarea name="notes" class="form-control">{{ $contact->notes }}</textarea></div><div class="col-12"><label class="form-check"><input type="checkbox" name="is_primary" value="1" class="form-check-input" @checked($contact->is_primary)> Primary contact</label></div>
        </div><div class="modal-footer"><button class="btn btn-primary">Save changes</button></div></form></div></div></div>@endforeach
    @endcan
</div>
@endsection
