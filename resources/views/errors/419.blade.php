@php
    $previousUrl = url()->previous();
    $fallbackUrl = auth()->check() && Route::has('dashboard') ? route('dashboard') : url('/');
    $backUrl = $previousUrl && $previousUrl !== request()->fullUrl() ? $previousUrl : $fallbackUrl;
@endphp

<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Session Expired | {{ config('app.name', 'ATTP') }}</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            background: #f3f4f6;
            color: #0f172a;
            font-family: Arial, sans-serif;
        }

        .error-card {
            width: min(92vw, 680px);
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .14);
            overflow: hidden;
        }

        .error-hero {
            padding: 30px;
            color: #fff;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 58%, #0ea5e9 100%);
        }

        .code {
            display: inline-flex;
            padding: 6px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, .16);
            border: 1px solid rgba(255, 255, 255, .28);
            font-weight: 700;
            letter-spacing: .08em;
        }

        .body {
            padding: 30px;
        }

        h1 {
            margin: 14px 0 8px;
            font-size: clamp(28px, 5vw, 42px);
        }

        p {
            margin: 0 0 18px;
            color: #64748b;
            line-height: 1.65;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 11px 16px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid #cbd5e1;
            color: #0f172a;
            background: #fff;
        }

        .btn.primary {
            color: #fff;
            border-color: #1d4ed8;
            background: #1d4ed8;
        }
    </style>
</head>
<body>
    @include('layouts.partials.impersonation-banner')
    <main class="error-card">
        <section class="error-hero">
            <span class="code">Session expired</span>
            <h1>Your secure session needs a refresh</h1>
        </section>
        <section class="body">
            <p>
                This usually happens when a page has been open for a while or the form token has expired.
                Please go back, refresh the form, and submit again.
            </p>
            <div class="actions">
                <a class="btn primary" href="{{ $backUrl }}">Go back and try again</a>
                <a class="btn" href="{{ $fallbackUrl }}">Open dashboard</a>
            </div>
        </section>
    </main>
</body>
</html>
