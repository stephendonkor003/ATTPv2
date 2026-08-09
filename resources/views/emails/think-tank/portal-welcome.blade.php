@component('mail::message')
# Your Think Tank Portal Is Ready

Hello {{ $user->name ?? $member->name }},

You have been added to the ATTP Think Tank Portal.

@component('mail::panel')
Think tank: {{ $member->name }}

Consortium: {{ $consortium->name }}

Portal access: {{ $user->thinkTankAccessLabel() }}

Country: {{ $member->country ?? 'Not specified' }}

Login email: {{ $user->email }}

@if($temporaryPassword)
Temporary password: {{ $temporaryPassword }}
@endif
@endcomponent

@if($temporaryPassword)
Use the temporary password above to sign in. For security, you may be asked to change it after your first login.
@else
Use your existing ATTP account password to sign in.
@endif

@component('mail::button', ['url' => $loginUrl])
Open Think Tank Portal
@endcomponent

Thanks,<br>
{{ config('app.name', 'ATTP') }}
@endcomponent
