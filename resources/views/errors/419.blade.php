@php
    $homeUrl = url('/');
    $shouldLogout = auth()->check();

    $maxAttempts = 4;
    $windowSeconds = 300; // 5 minutes

    $attempts = 1;
    $logoutAfterAttempts = false;

    $previousUrl = url()->previous();
    $homeHost = parse_url($homeUrl, PHP_URL_HOST);
    $prevHost = parse_url($previousUrl, PHP_URL_HOST);
    $prevScheme = parse_url($previousUrl, PHP_URL_SCHEME);

    // Avoid redirecting users off-site via a crafted Referer.
    if ($prevHost && $homeHost && $prevHost !== $homeHost) {
        $previousUrl = $homeUrl;
    }
    if ($prevScheme && !in_array($prevScheme, ['http', 'https'], true)) {
        $previousUrl = $homeUrl;
    }

    try {
        $now = now()->timestamp;
        $lastAt = (int) session()->get('page_expired_last_at', 0);
        $attempts = (int) session()->get('page_expired_attempts', 0);

        if ($lastAt <= 0 || ($now - $lastAt) > $windowSeconds) {
            $attempts = 0;
        }

        $attempts++;

        session()->put('page_expired_attempts', $attempts);
        session()->put('page_expired_last_at', $now);

        $logoutAfterAttempts = $attempts >= $maxAttempts;
    } catch (\Throwable $e) {
        // If the session store is unavailable, fall back to a simple redirect.
        $attempts = 1;
        $logoutAfterAttempts = false;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1" />
        <meta name="robots" content="noindex,nofollow" />
        <title>{{ config('app.name', 'Application') }} - Session Expired</title>
        <style>
            :root {
                --bg: #0f172a;
                --card: #111827;
                --text: #e5e7eb;
                --muted: #9ca3af;
                --accent: #60a5fa;
                --border: rgba(255, 255, 255, 0.08);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, Helvetica, Arial, "Apple Color Emoji",
                    "Segoe UI Emoji";
                background: radial-gradient(1200px 600px at 10% 10%, rgba(96, 165, 250, 0.15), transparent 60%),
                    radial-gradient(800px 500px at 90% 20%, rgba(167, 139, 250, 0.12), transparent 60%), var(--bg);
                color: var(--text);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }

            .card {
                width: 100%;
                max-width: 520px;
                background: rgba(17, 24, 39, 0.85);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 28px;
                box-shadow: 0 24px 60px rgba(0, 0, 0, 0.35);
            }

            .title {
                font-size: 20px;
                font-weight: 700;
                margin: 0 0 8px 0;
                letter-spacing: 0.2px;
            }

            .subtitle {
                margin: 0 0 18px 0;
                color: var(--muted);
                line-height: 1.5;
            }

            .row {
                display: flex;
                gap: 14px;
                align-items: center;
                padding: 14px 0 4px 0;
            }

            .spinner {
                width: 28px;
                height: 28px;
                border-radius: 999px;
                border: 3px solid rgba(255, 255, 255, 0.18);
                border-top-color: var(--accent);
                animation: spin 0.9s linear infinite;
                flex: 0 0 auto;
            }

            @keyframes spin {
                to {
                    transform: rotate(360deg);
                }
            }

            .countdown {
                color: var(--muted);
                font-size: 14px;
                line-height: 1.4;
            }

            .actions {
                margin-top: 18px;
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
            }

            .btn {
                display: inline-block;
                padding: 10px 14px;
                border-radius: 10px;
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                border: 1px solid var(--border);
                color: var(--text);
                background: rgba(255, 255, 255, 0.04);
            }

            .btn-primary {
                background: rgba(96, 165, 250, 0.16);
                border-color: rgba(96, 165, 250, 0.35);
            }

            .btn:hover {
                filter: brightness(1.1);
            }

            .small {
                margin-top: 14px;
                color: var(--muted);
                font-size: 12px;
                line-height: 1.5;
            }
        </style>
    </head>
    <body>
        <main class="card" role="main" aria-live="polite">
            <h1 class="title">Your session has expired</h1>
            <p class="subtitle">
                @if ($logoutAfterAttempts && $shouldLogout)
                    This keeps happening. For your security, we will sign you out and take you to the homepage.
                @else
                    No worries. Please refresh the page and try again. We will take you back to where you were.
                @endif
            </p>

            <div class="row">
                <div class="spinner" aria-hidden="true"></div>
                <div class="countdown">
                    @if ($logoutAfterAttempts && $shouldLogout)
                        Signing you out in <strong><span id="countdown">3</span></strong> seconds...
                    @else
                        Taking you back in <strong><span id="countdown">3</span></strong> seconds...
                    @endif
                </div>
            </div>

            <div class="actions">
                @if ($logoutAfterAttempts && $shouldLogout)
                    <a class="btn btn-primary" href="#" onclick="event.preventDefault(); var f=document.getElementById('logout-form'); if (f) f.submit();">Log out now</a>
                    <a class="btn" href="{{ $homeUrl }}">Go to homepage</a>
                @else
                    <a class="btn btn-primary" href="{{ $previousUrl }}">Go back now</a>
                    <a class="btn" href="{{ $homeUrl }}">Go to homepage</a>
                @endif
            </div>

            <div class="small">
                Tip: If you were submitting a form, please refresh the page and try again.
            </div>

            @if ($shouldLogout && $logoutAfterAttempts)
                <form id="logout-form" method="POST" action="{{ route('logout') }}" style="display:none;">
                    @csrf
                </form>
            @endif
        </main>

        <script>
            (function () {
                var shouldLogout = {{ $shouldLogout ? 'true' : 'false' }};
                var logoutAfterAttempts = {{ $logoutAfterAttempts ? 'true' : 'false' }};
                var previousUrl = @json($previousUrl);
                var homeUrl = @json($homeUrl);
                var seconds = 3;
                var el = document.getElementById('countdown');

                function render() {
                    if (el) el.textContent = String(Math.max(seconds, 0));
                }

                render();

                var timer = setInterval(function () {
                    seconds -= 1;
                    render();

                    if (seconds <= 0) {
                        clearInterval(timer);

                        if (logoutAfterAttempts && shouldLogout) {
                            var form = document.getElementById('logout-form');
                            if (form) {
                                form.submit();
                                return;
                            }
                        }

                        window.location.href = previousUrl || homeUrl;
                    }
                }, 1000);
            })();
        </script>
    </body>
</html>
