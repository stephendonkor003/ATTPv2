<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Vendor Portal')</title>
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --vendor-blue: #1d4ed8;
            --vendor-blue-dark: #0b1e4f;
            --vendor-blue-light: #e6f0ff;
            --vendor-blue-soft: #f3f7ff;
            --vendor-border: #c6dbff;
            --vendor-text: #0f172a;
            --vendor-muted: #64748b;
            --vendor-gold: #f2b94b;
        }

        body {
            background: linear-gradient(135deg, #e6f0ff 0%, #f8fbff 55%, #e9f2ff 100%);
            color: var(--vendor-text);
            font-family: "Manrope", sans-serif;
        }

        .vendor-wrapper {
            min-height: 100vh;
            display: flex;
            gap: 0;
        }

        .vendor-sidebar {
            width: 270px;
            background: linear-gradient(180deg, #0b1e4f 0%, #102a6a 50%, #0f255a 100%);
            color: #ffffff;
            padding: 28px 22px;
            position: relative;
            overflow: hidden;
        }

        .vendor-sidebar::after {
            content: "";
            position: absolute;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
            top: -60px;
            right: -80px;
        }

        .vendor-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 32px;
            position: relative;
            z-index: 1;
        }

        .vendor-brand-badge {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, #f2b94b 0%, #f8d37a 100%);
            display: grid;
            place-items: center;
            color: #1f2937;
            font-weight: 800;
            font-family: "Fraunces", serif;
        }

        .vendor-brand .brand-title {
            font-family: "Fraunces", serif;
            font-size: 1.1rem;
            font-weight: 700;
            color: #ffffff;
        }

        .vendor-brand .brand-subtitle {
            font-size: 0.8rem;
            color: rgba(255, 255, 255, 0.7);
        }

        .vendor-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 14px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            margin-bottom: 12px;
            background: rgba(255, 255, 255, 0.08);
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .vendor-nav a.active,
        .vendor-nav a:hover {
            background: #ffffff;
            color: #0b1e4f;
            border-color: rgba(255, 255, 255, 0.7);
            font-weight: 600;
        }

        .vendor-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .vendor-header {
            background: #ffffff;
            border-bottom: 1px solid var(--vendor-border);
            padding: 18px 28px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.05);
        }

        .vendor-header .header-meta {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .vendor-main {
            padding: 28px;
            flex: 1;
        }

        .vendor-footer {
            background: #ffffff;
            border-top: 1px solid var(--vendor-border);
            padding: 16px 28px;
            text-align: center;
            color: var(--vendor-muted);
            font-size: 0.85rem;
        }

        .vendor-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 16px 32px rgba(15, 23, 42, 0.12);
            background: #ffffff;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
            background: #eaf2ff;
            color: var(--vendor-blue);
        }

        .badge-soft {
            background: #e6f0ff;
            color: var(--vendor-blue);
            border-radius: 8px;
            padding: 4px 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .btn-vendor {
            background: linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%);
            border: none;
            color: #ffffff;
            border-radius: 12px;
            padding: 8px 16px;
            box-shadow: 0 12px 20px rgba(37, 99, 235, 0.2);
        }

        .btn-vendor-outline {
            border-radius: 12px;
            border: 1px solid var(--vendor-blue);
            color: var(--vendor-blue);
            background: #ffffff;
        }

        @media (max-width: 992px) {
            .vendor-wrapper {
                flex-direction: column;
            }

            .vendor-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid var(--vendor-border);
            }
        }
    </style>
    @stack('styles')
</head>

<body>
    <div class="vendor-wrapper">
        <aside class="vendor-sidebar">
            <div class="vendor-brand">
                <div class="vendor-brand-badge">VP</div>
                <div>
                    <div class="brand-title">Vendor Portal</div>
                    <div class="brand-subtitle">Procurement Workspace</div>
                </div>
            </div>

            <nav class="vendor-nav">
                <a href="{{ route('vendor.dashboard') }}" class="{{ request()->routeIs('vendor.dashboard') ? 'active' : '' }}">
                    My Dashboard
                </a>
                <a href="{{ route('vendor.procurements.index') }}" class="{{ request()->routeIs('vendor.procurements.*') ? 'active' : '' }}">
                    Open Procurements
                </a>
                <a href="{{ route('vendor.clarifications') }}" class="{{ request()->routeIs('vendor.clarifications') ? 'active' : '' }}">
                    Request Clarification
                </a>
                <a href="{{ route('vendor.submissions') }}" class="{{ request()->routeIs('vendor.submissions') ? 'active' : '' }}">
                    My Procurement Submissions
                </a>
                <a href="{{ route('vendor.payment-details') }}" class="{{ request()->routeIs('vendor.payment-details*') ? 'active' : '' }}">
                    Payment Details
                </a>
                <a href="{{ route('vendor.payments.index') }}" class="{{ request()->routeIs('vendor.payments.*') ? 'active' : '' }}">
                    Payment Records
                </a>
                <a href="{{ route('vendor.invoices.index') }}" class="{{ request()->routeIs('vendor.invoices.*') ? 'active' : '' }}">
                    My Invoices
                </a>
                <a href="{{ route('vendor.deliverables.index') }}" class="{{ request()->routeIs('vendor.deliverables.*') ? 'active' : '' }}">
                    Vendor Deliverables
                </a>
            </nav>
        </aside>

        <div class="vendor-content">
            <header class="vendor-header">
                <div class="header-meta">
                    <strong>{{ auth()->user()->name ?? 'Vendor' }}</strong>
                    <span class="text-muted small">
                        Category: {{ auth()->user()->vendor_category ?? 'Unassigned' }}
                    </span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <div class="text-end">
                        <div class="small text-muted">Current Date & Time</div>
                        <div id="vendorClock" class="fw-semibold">--</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-vendor-outline btn-sm">Logout</button>
                    </form>
                </div>
            </header>

            <main class="vendor-main">
                @yield('content')
            </main>

            <footer class="vendor-footer">
                African Union Procurement Portal · Vendor Access
            </footer>
        </div>
    </div>

    <script>
        (function() {
            const clockEl = document.getElementById('vendorClock');
            if (!clockEl) return;

            const updateClock = () => {
                const now = new Date();
                const options = {
                    weekday: 'short',
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                };
                clockEl.textContent = now.toLocaleString(undefined, options);
            };

            updateClock();
            setInterval(updateClock, 1000);
        })();
    </script>
    <script src="{{ asset('assets/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
    @stack('scripts')
</body>

</html>
