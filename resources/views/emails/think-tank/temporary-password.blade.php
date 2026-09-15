@component('mail::message')
# Your Think Tank Portal Account

Hello {{ $user->name }},

Your administrator at **{{ $tenant->name }}** created your ATTP Think Tank Portal account.

@component('mail::panel')
Login email: {{ $user->email }}

Temporary password: **{{ $temporaryPassword }}**

Access role: {{ $user->thinkTankAccessLabel() }}
@endcomponent

You must create a new private password immediately after signing in. You will then receive a six-digit verification code to complete your first login. Do not forward this message or reuse the temporary password.

@component('mail::button', ['url' => $loginUrl])
Sign in to the Think Tank Portal
@endcomponent

Thanks,<br>
{{ config('app.name', 'ATTP') }}
@endcomponent
