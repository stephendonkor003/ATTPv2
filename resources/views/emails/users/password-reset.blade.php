@component('mail::message')
# Password Reset

Hello {{ $user->name ?? 'User' }},

Your ATTP account password has been reset by an administrator.

@component('mail::panel')
Email: {{ $user->email }}

Temporary password: {{ $plainPassword }}
@endcomponent

Please sign in and change this password immediately if the system asks you to do so.

@component('mail::button', ['url' => route('login')])
Sign in to ATTP
@endcomponent

If you did not request this reset, please contact the ATTP administrator.

Thanks,<br>
{{ config('app.name', 'ATTP') }}
@endcomponent
