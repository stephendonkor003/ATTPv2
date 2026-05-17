@component('mail::message')
# Your ATTP Account Has Been Created

Hello {{ $user->name ?? 'User' }},

An account has been created for you on the African Think Tank Platform Administration system.

@component('mail::panel')
Email: {{ $user->email }}

Temporary password: {{ $plainPassword }}
@endcomponent

Use these credentials to sign in. For security, you may be asked to change your password after your first login.

@component('mail::button', ['url' => route('login')])
Sign in to ATTP
@endcomponent

Thanks,<br>
{{ config('app.name', 'ATTP') }}
@endcomponent
