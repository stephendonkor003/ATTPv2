<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Change Password | {{ config('app.name', 'ATTP') }}</title>
    <link rel="icon" href="{{ asset('assets/images/au.png') }}" type="image/png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --green: #006B3F;
            --green-dark: #004d2e;
            --blue: #1d4ed8;
            --slate: #0f172a;
            --muted: #64748b;
            --border: #e5e7eb;
            --danger: #dc2626;
            --success: #15803d;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family: Inter, Arial, sans-serif;
            color: var(--slate);
            background:
                radial-gradient(circle at top left, rgba(14, 165, 233, .16), transparent 34%),
                linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
            display: grid;
            place-items: center;
            padding: 24px;
        }

        .shell {
            width: min(100%, 980px);
            display: grid;
            grid-template-columns: 0.9fr 1.1fr;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 22px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, .16);
        }

        .brand {
            padding: 38px;
            color: #fff;
            background: linear-gradient(145deg, var(--green-dark) 0%, var(--green) 58%, #0ea5e9 100%);
            position: relative;
            overflow: hidden;
        }

        .brand::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: -90px;
            bottom: -90px;
            border-radius: 999px;
            border: 44px solid rgba(255, 255, 255, .08);
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .logo {
            width: 78px;
            height: 78px;
            object-fit: contain;
            margin-bottom: 20px;
        }

        .brand h1 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 40px);
            line-height: 1.05;
            color: #fff;
        }

        .brand p {
            margin: 0;
            color: rgba(255, 255, 255, .84);
            line-height: 1.7;
        }

        .panel {
            padding: 38px;
        }

        .eyebrow {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            color: var(--blue);
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .08em;
        }

        h2 {
            margin: 18px 0 8px;
            font-size: 28px;
            color: var(--slate);
        }

        .muted {
            margin: 0 0 22px;
            color: var(--muted);
            line-height: 1.65;
        }

        .notice {
            padding: 12px 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            border: 1px solid var(--border);
        }

        .notice.error {
            color: var(--danger);
            background: #fef2f2;
            border-color: #fecaca;
        }

        label {
            display: block;
            margin: 0 0 8px;
            font-weight: 700;
            color: #334155;
        }

        .field {
            margin-bottom: 15px;
        }

        input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            padding: 13px 14px;
            font-size: 15px;
            color: var(--slate);
            outline: none;
        }

        input:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 4px rgba(29, 78, 216, .12);
        }

        .hint {
            margin-top: 6px;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 18px;
        }

        button {
            border-radius: 12px;
            padding: 13px 16px;
            font-weight: 800;
            cursor: pointer;
        }

        button.primary {
            color: #fff;
            background: var(--blue);
            border: 1px solid var(--blue);
        }

        button.danger {
            color: #b91c1c;
            background: #fef2f2;
            border: 1px solid #fecaca;
        }

        .signout-row {
            margin-top: 18px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
        }

        @media (max-width: 820px) {
            .shell {
                grid-template-columns: 1fr;
            }

            .brand,
            .panel {
                padding: 28px;
            }
        }
    </style>
</head>
<body>
    @include('layouts.partials.impersonation-banner')
    <main class="shell">
        <section class="brand">
            <div class="brand-content">
                <img class="logo" src="{{ asset('assets/images/au.png') }}" alt="ATTP">
                <h1>Update Your Password</h1>
                <p>{{ $message ?? 'Please create a new secure password to continue using the ATTP portal.' }}</p>
            </div>
        </section>

        <section class="panel">
            <span class="eyebrow">{{ ($reason ?? 'security') === 'first_login' ? 'First Login' : 'Security Check' }}</span>
            <h2>Create a secure password</h2>
            <p class="muted">
                Use at least eight characters with uppercase, lowercase, numbers, and a special character.
            </p>

            @if (isset($errors) && $errors->any())
                <div class="notice error">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('security.password.submit') }}">
                @csrf

                <div class="field">
                    <label for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required autofocus>
                </div>

                <div class="field">
                    <label for="password">New password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required>
                    <div class="hint">Example format: StrongPass123!</div>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>

                <div class="actions">
                    <button class="primary" type="submit">Update password and continue</button>
                </div>
            </form>

            <form method="POST" action="{{ route('logout') }}" class="signout-row">
                @csrf
                <button class="danger" type="submit">Sign out and return to login</button>
            </form>
        </section>
    </main>
</body>
</html>
