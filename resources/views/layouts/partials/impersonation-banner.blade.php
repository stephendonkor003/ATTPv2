@php
    $impersonationState = \App\Support\UserImpersonation::isActive(request())
        ? \App\Support\UserImpersonation::state(request())
        : null;
@endphp

@if ($impersonationState)
    <style>
        body {
            padding-bottom: 96px !important;
        }

        .attp-impersonation-banner {
            position: fixed;
            z-index: 2147483000;
            right: 18px;
            bottom: 18px;
            left: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            pointer-events: none;
            font-family: Arial, Helvetica, sans-serif;
        }

        .attp-impersonation-banner__panel {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            width: min(760px, 100%);
            padding: 13px 16px;
            border: 2px solid #fbbf24;
            border-radius: 12px;
            background: #111827;
            color: #f9fafb;
            box-shadow: 0 18px 45px rgba(15, 23, 42, .35);
            pointer-events: auto;
        }

        .attp-impersonation-banner__copy {
            min-width: 0;
            font-size: 13px;
            line-height: 1.35;
        }

        .attp-impersonation-banner__copy strong {
            display: block;
            color: #fbbf24;
            font-size: 14px;
        }

        .attp-impersonation-banner__button {
            appearance: none;
            flex: 0 0 auto;
            padding: 9px 13px;
            border: 0;
            border-radius: 8px;
            background: #fbbf24;
            color: #111827;
            cursor: pointer;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            white-space: nowrap;
        }

        .attp-impersonation-banner__button:hover,
        .attp-impersonation-banner__button:focus-visible {
            background: #fde68a;
            outline: 3px solid rgba(251, 191, 36, .35);
            outline-offset: 2px;
        }

        @media (max-width: 640px) {
            body {
                padding-bottom: 152px !important;
            }

            .attp-impersonation-banner__panel {
                align-items: stretch;
                flex-direction: column;
                gap: 10px;
            }

            .attp-impersonation-banner__button {
                width: 100%;
            }
        }

        @media (max-width: 991.98px) {
            body.aa-portal-body {
                padding-bottom: 160px !important;
            }

            .attp-impersonation-banner ~ .aa-mobile-menu {
                bottom: 96px !important;
            }
        }

        @media (max-width: 640px) {
            body.aa-portal-body {
                padding-bottom: 220px !important;
            }

            .attp-impersonation-banner ~ .aa-mobile-menu {
                bottom: 152px !important;
            }
        }
    </style>

    <aside class="attp-impersonation-banner" aria-label="User impersonation status">
        <div class="attp-impersonation-banner__panel">
            <div class="attp-impersonation-banner__copy" role="status" aria-live="polite">
                <strong>You are acting as {{ auth()->user()->name }}</strong>
                Actions use this user's access. Your administrator account remains available for return.
            </div>

            <form method="POST" action="{{ route('impersonation.stop') }}">
                @csrf
                <button type="submit" class="attp-impersonation-banner__button"
                    aria-label="Return to administrator account">
                    Return to admin
                </button>
            </form>
        </div>
    </aside>
@endif
