<style>
    .attp-header-strip {
        min-width: 0;
    }

    .attp-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        min-height: 34px;
        padding: 7px 10px;
        border: 1px solid rgba(167, 243, 208, 0.45);
        border-radius: 8px;
        background: linear-gradient(135deg, #047857 0%, #065f46 100%);
        color: #ffffff;
        font-size: 12px;
        font-weight: 600;
        line-height: 1.2;
        white-space: nowrap;
        box-shadow: 0 8px 18px rgba(4, 120, 87, 0.18);
    }

    .attp-chip.muted {
        color: #ffffff;
        font-weight: 500;
    }

    .attp-chip .label {
        color: rgba(255, 255, 255, 0.82);
        font-weight: 600;
    }

    .attp-chip.brand {
        background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
        border-color: rgba(167, 243, 208, 0.5);
        color: #fff;
    }

    .attp-header-strip .attp-chip i {
        color: #ffffff;
    }

    .nxl-header .nxl-navigation-toggle a,
    .nxl-header .nxl-lavel-mega-menu-toggle a,
    .nxl-header .nxl-head-mobile-toggler {
        color: #047857 !important;
    }

    .nxl-header .nxl-navigation-toggle a:hover,
    .nxl-header .nxl-lavel-mega-menu-toggle a:hover {
        color: #065f46 !important;
    }

    @media (max-width: 1399.98px) {
        .attp-chip.optional {
            display: none;
        }
    }
</style>

<header class="nxl-header"
    style="backdrop-filter: blur(6px); background: rgba(255,255,255,0.96); border-bottom: 1px solid #e5e7eb;">
    <div class="header-wrapper" style="padding: 8px 16px;">
        <!--! [Start] Header Left !-->
        <div class="header-left d-flex align-items-center gap-4">
            <!--! [Start] nxl-head-mobile-toggler !-->
            <a href="javascript:void(0);" class="nxl-head-mobile-toggler" id="mobile-collapse">
                <div class="hamburger hamburger--arrowturn">
                    <div class="hamburger-box">
                        <div class="hamburger-inner"></div>
                    </div>
                </div>
            </a>
            <!--! [Start] nxl-head-mobile-toggler !-->
            <!--! [Start] nxl-navigation-toggle !-->
            <div class="nxl-navigation-toggle">
                <a href="javascript:void(0);" id="menu-mini-button">
                    <i class="feather-align-left"></i>
                </a>
                <a href="javascript:void(0);" id="menu-expend-button" style="display: none">
                    <i class="feather-arrow-right"></i>
                </a>
            </div>
            <!--! [End] nxl-navigation-toggle !-->
            <!--! [Start] nxl-lavel-mega-menu-toggle !-->
            <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                <a href="javascript:void(0);" id="nxl-lavel-mega-menu-open">
                    <i class="feather-align-left"></i>
                </a>
            </div>
            <!--! [End] nxl-lavel-mega-menu-toggle !-->
            <!--! [Start] nxl-lavel-mega-menu !-->
            <div class="nxl-drp-link nxl-lavel-mega-menu">
                <div class="nxl-lavel-mega-menu-toggle d-flex d-lg-none">
                    <a href="javascript:void(0)" id="nxl-lavel-mega-menu-hide">
                        <i class="feather-arrow-left me-2"></i>
                        <span>{{ __('common.back') }}</span>
                    </a>
                </div>
                <!--! [Start] nxl-lavel-mega-menu-wrapper !-->

                <!--! [End] nxl-lavel-mega-menu-wrapper !-->
            </div>
            <!--! [End] nxl-lavel-mega-menu !-->
        </div>
        <!--! [End] Header Left !-->
        <!--! [Start] Header Right !-->
        <div class="header-right ms-auto w-100">
            <div class="d-flex align-items-center justify-content-end gap-3 flex-wrap">

                <div class="attp-header-strip d-none d-lg-flex align-items-center gap-2 me-auto flex-nowrap overflow-hidden">
                    <span class="attp-chip brand">
                        <i class="feather-cpu"></i>
                        ATTP Control Center
                    </span>
                    <span class="attp-chip muted">
                        <i class="feather-clock"></i>
                        <span class="label">Time:</span> <span id="attp-time">--:--:--</span>
                    </span>
                    <span class="attp-chip muted optional">
                        <i class="feather-map-pin"></i>
                        <span class="label">City:</span> <span id="attp-city">Resolving...</span>
                    </span>
                    <span class="attp-chip muted optional">
                        <i class="feather-thermometer"></i>
                        <span class="label">Weather:</span> <span id="attp-weather">--</span>
                    </span>
                    <span class="attp-chip muted optional">
                        <i class="feather-wifi"></i>
                        <span class="label">IP Tracker:</span> <span id="attp-ip">...</span>
                    </span>
                </div>

                <!--! [Start] Language Selector !-->
                <x-language-selector style="admin" />
                <!--! [End] Language Selector !-->

                <div class="dropdown nxl-h-item">
                    <a href="javascript:void(0);" data-bs-toggle="dropdown" role="button" data-bs-auto-close="outside">
                        <img src="{{ asset('assets/images/au.jpeg') }}" alt="user-image"
                            class="img-fluid user-avtar me-0">
                    </a>
                    <div class="dropdown-menu dropdown-menu-end nxl-h-dropdown nxl-user-dropdown">
                        <div class="dropdown-header">
                            @php
                                use Illuminate\Support\Facades\Auth;
                                $roleName = optional(Auth::user()->role)->name
                                    ?? (is_string(Auth::user()->role) ? Auth::user()->role : 'User');
                            @endphp

                            <div class="d-flex align-items-center">
                                <img src="{{ asset('assets/images/au.jpeg') }}" alt="user-image"
                                    class="img-fluid user-avtar">
                                <div class="ms-2">
                                    <h6 class="text-dark mb-0">
                                        {{ Auth::user()->name }}
                                        <span class="badge bg-soft-success text-success ms-1">{{ $roleName }}</span>
                                    </h6>
                                    <span class="fs-12 fw-medium text-muted">{{ Auth::user()->email }}</span>
                                </div>
                            </div>

                        </div>

                        <div class="px-3 py-2">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="text-muted small">Theme</span>
                                <div class="dark-light-theme">
                                    <a href="javascript:void(0);" class="nxl-head-link me-0 p-0 dark-button">
                                        <i class="feather-moon"></i>
                                    </a>
                                    <a href="javascript:void(0);" class="nxl-head-link me-0 p-0 light-button"
                                        style="display: none">
                                        <i class="feather-sun"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div class="px-3 py-2 d-flex align-items-center justify-content-between">
                            <span class="text-muted small">Full Screen</span>
                            <a href="javascript:void(0);" class="nxl-head-link me-0 p-0"
                                onclick="$('body').fullScreenHelper('toggle');">
                                <i class="feather-maximize maximize"></i>
                                <i class="feather-minimize minimize"></i>
                            </a>
                        </div>


                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">
                                <i class="feather-log-out"></i>
                                <span>{{ __('common.logout') }}</span>
                            </button>
                        </form>

                    </div>
                </div>
            </div>
        </div>
        <!--! [End] Header Right !-->
    </div>
</header>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const timeEl = document.getElementById('attp-time');
        const timeSideEl = document.getElementById('attp-time-side');
        const ipEl = document.getElementById('attp-ip');
        const cityEl = document.getElementById('attp-city');
        const weatherEl = document.getElementById('attp-weather');
        const weatherSideEl = document.getElementById('attp-weather-side');

        const updateTime = () => {
            const now = new Date();
            const pretty = now.toLocaleString(undefined, {
                weekday: 'short',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            });
            timeEl && (timeEl.textContent = pretty);
            timeSideEl && (timeSideEl.textContent = pretty);
        };
        updateTime();
        setInterval(updateTime, 1000);

        const setText = (el, value) => {
            if (el) el.textContent = value;
        };

        // Fetch IP and location
        fetch('https://ipapi.co/json/')
            .then(res => res.ok ? res.json() : null)
            .then(data => {
                if (!data) return;
                setText(ipEl, data.ip || '--');
                setText(cityEl, data.city || data.country_name || 'N/A');
                const {
                    latitude,
                    longitude
                } = data;
                if (latitude && longitude) {
                    return fetch(
                        `https://api.open-meteo.com/v1/forecast?latitude=${latitude}&longitude=${longitude}&current=temperature_2m,weather_code`
                        );
                }
            })
            .then(res => res && res.ok ? res.json() : null)
            .then(wx => {
                if (!wx || !wx.current) return;
                const temp = wx.current.temperature_2m;
                const code = wx.current.weather_code;
                const label = temp !== undefined ? `${Math.round(temp)}°C` : '--';
                setText(weatherEl, label);
                setText(weatherSideEl, `Weather: ${label}`);
            })
            .catch(() => {
                setText(ipEl, 'N/A');
                setText(cityEl, 'N/A');
                setText(weatherEl, 'N/A');
                setText(weatherSideEl, 'Weather: N/A');
            });
    });
</script>
