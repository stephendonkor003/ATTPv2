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
@endcomponent

Use your existing ATTP account password to sign in. If you do not know it, use the Forgot password link on the portal to request a secure, single-use reset link. Passwords are never sent by email.

@component('mail::button', ['url' => $loginUrl])
Open Think Tank Portal
@endcomponent

Thanks,<br>
{{ config('app.name', 'ATTP') }}
@endcomponent
