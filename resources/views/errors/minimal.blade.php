<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | ATTP</title>
    <style>
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #eef3f1;
            color: #172033;
            font-family: Arial, Helvetica, sans-serif;
        }
        .error-card {
            width: min(620px, 100%);
            overflow: hidden;
            border: 1px solid #d9e2df;
            border-radius: 20px;
            background: #fff;
            box-shadow: 0 24px 60px rgba(15, 23, 42, .14);
        }
        .error-card__hero {
            padding: 34px;
            background: linear-gradient(135deg, #0f172a, #0f766e);
            color: #fff;
        }
        .error-card__code {
            display: inline-flex;
            padding: 6px 10px;
            border: 1px solid rgba(255,255,255,.35);
            border-radius: 999px;
            background: rgba(255,255,255,.12);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .08em;
        }
        h1 { margin: 14px 0 0; font-size: clamp(26px, 5vw, 38px); }
        .error-card__body { padding: 30px 34px 34px; }
        p { margin: 0; color: #52605d; line-height: 1.7; }
        .error-card__actions { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 24px; }
        .error-card__button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 10px 15px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            color: #172033;
            font-weight: 700;
            text-decoration: none;
        }
        .error-card__button--primary { border-color: #0f766e; background: #0f766e; color: #fff; }
    </style>
</head>
<body>
    @include('layouts.partials.impersonation-banner')

    <main class="error-card">
        <section class="error-card__hero">
            <span class="error-card__code">@yield('code')</span>
            <h1>@yield('heading')</h1>
        </section>
        <section class="error-card__body">
            <p>@yield('message')</p>
            <div class="error-card__actions">
                <a class="error-card__button error-card__button--primary" href="{{ url()->previous() }}">Go back</a>
                <a class="error-card__button" href="{{ url('/') }}">Open home page</a>
            </div>
        </section>
    </main>
</body>
</html>
