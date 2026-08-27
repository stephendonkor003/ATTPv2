<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta http-equiv="x-ua-compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="">
    <meta name="keyword" content="">
    <meta name="author" content="WRAPCODERS">

    <title>@yield('title', 'ATTP || Administration')</title>

    <!-- Favicon -->
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('assets/images/attp-logo.jpeg') }}">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">

    <!-- Vendors CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/dataTables.bs5.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/tagify.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/tagify-data.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/quill.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/select2-theme.min.css') }}">

    <!-- Custom Theme CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">

    <!-- DataTable Custom CSS -->
    <link rel="stylesheet" href="{{ asset('admin/assets/css/datatable-custom.css') }}">

    <!-- Page-specific styles -->
    @stack('styles')

    <!-- RTL CSS for Arabic -->
    @if (app()->getLocale() === 'ar')
        <link rel="stylesheet" href="{{ asset('assets/css/rtl.css') }}">
    @endif

    <!--[if lt IE 9]>
        <script src="https://oss.maxcdn.com/html5shiv/3.7.2/html5shiv.min.js"></script>
        <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->

    <style>
        .btn-custom {
            background-color: #532934 !important;
            color: #fff !important;
            border: none;
            padding: 6px 12px;
            margin: 2px;
            border-radius: 4px;
        }

        .btn-custom:hover {
            background-color: #3e1f28 !important;
            color: #fff !important;
        }

        /* Ensure Bootstrap modals appear above custom overlays */
        .modal {
            z-index: 2000;
        }

        .modal-backdrop {
            z-index: 1990;
        }

        /* Remove any blur effects applied by theme/backdrop */
        .modal-backdrop {
            backdrop-filter: none !important;
            -webkit-backdrop-filter: none !important;
            filter: none !important;
        }

        body.modal-open,
        body.modal-open .main-wrapper {
            filter: none !important;
        }

        :root {
            --attp-backoffice-header-offset: 104px;
        }

        .content-wrapper {
            min-height: 100vh;
            margin-left: 280px;
            transition: all .3s ease;
        }

        .minimenu .content-wrapper {
            margin-left: 100px;
        }

        .content-wrapper .content {
            min-height: calc(100vh - var(--attp-backoffice-header-offset));
            padding-top: calc(var(--attp-backoffice-header-offset) + 1.5rem) !important;
        }

        .content .nxl-container {
            position: static !important;
            top: auto !important;
            margin-left: 0 !important;
            min-height: auto !important;
        }

        .content > .nxl-container:first-child,
        .content > .page-header:first-child,
        .content > .dash-hero:first-child {
            margin-top: 0 !important;
        }

        @media (max-width: 1199.98px) {
            .content-wrapper {
                margin-left: 0;
            }
        }

        /* Header info chips */
        .attp-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: linear-gradient(135deg, #0ea5e9 0%, #10b981 100%);
            color: #0f172a;
            font-weight: 700;
            font-size: 0.78rem;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
            border: 1px solid rgba(15, 23, 42, 0.06);
        }

        .attp-chip.muted {
            background: #f8fafc;
            color: #0f172a;
        }

        .attp-chip .label {
            color: #0f172a;
            font-weight: 600;
        }

        .attp-chip i {
            color: #0f172a;
        }

        /* Unified Data Source-style header for all pages using .page-header */
        .content .page-header {
            position: relative !important;
            top: auto !important;
            right: auto !important;
            left: auto !important;
            min-height: 0 !important;
            width: 100% !important;
            border: 0 !important;
            border-radius: 16px !important;
            padding: 1rem 1.15rem !important;
            margin: 0 0 1rem 0 !important;
            background: linear-gradient(130deg, #0f172a 0%, #0f766e 55%, #0ea5e9 100%) !important;
            color: #f8fafc !important;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.2) !important;
            display: flex !important;
            align-items: center !important;
            justify-content: space-between !important;
            gap: 0.75rem !important;
            flex-wrap: wrap !important;
        }

        .content .page-header .page-header-left,
        .content .page-header .page-header-right,
        .content .page-header .page-header-title,
        .content .page-header .page-block {
            color: #f8fafc !important;
            width: auto !important;
            max-width: 100%;
        }

        .content .page-header h1,
        .content .page-header h2,
        .content .page-header h3,
        .content .page-header h4,
        .content .page-header h5,
        .content .page-header h6 {
            color: #f8fafc !important;
            margin-bottom: 0 !important;
            font-weight: 700 !important;
            letter-spacing: 0.01em;
            border-right: 0 !important;
            padding-right: 0 !important;
            margin-right: 0 !important;
        }

        .content .page-header p,
        .content .page-header small,
        .content .page-header .small,
        .content .page-header .text-muted,
        .content .page-header .subtitle,
        .content .page-header .page-header-subtitle {
            color: rgba(248, 250, 252, 0.9) !important;
        }

        .content .page-header .breadcrumb,
        .content .page-header .breadcrumb .breadcrumb-item,
        .content .page-header .breadcrumb .breadcrumb-item a {
            color: rgba(248, 250, 252, 0.9) !important;
        }

        .content .page-header a:not(.btn) {
            color: #e0f2fe !important;
        }

        .content .page-header .btn-outline-primary,
        .content .page-header .btn-outline-secondary,
        .content .page-header .btn-outline-success,
        .content .page-header .btn-outline-dark,
        .content .page-header .btn-light {
            border-color: rgba(248, 250, 252, 0.52) !important;
            color: #f8fafc !important;
            background: rgba(248, 250, 252, 0.12) !important;
        }

        .content .page-header .btn-outline-primary:hover,
        .content .page-header .btn-outline-secondary:hover,
        .content .page-header .btn-outline-success:hover,
        .content .page-header .btn-outline-dark:hover,
        .content .page-header .btn-light:hover {
            border-color: rgba(248, 250, 252, 0.8) !important;
            color: #0f172a !important;
            background: #f8fafc !important;
        }

        .content .page-header .badge.bg-light,
        .content .page-header .badge.text-dark {
            background: rgba(248, 250, 252, 0.2) !important;
            color: #f8fafc !important;
            border: 1px solid rgba(248, 250, 252, 0.35) !important;
        }

        @media (max-width: 768px) {
            .content .page-header {
                border-radius: 12px !important;
                padding: 0.9rem !important;
            }
        }

        .content .attp-management-tabs {
            gap: 0.45rem;
            border-bottom: 0 !important;
            padding: 0.35rem;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .content .attp-management-tabs .nav-item {
            margin-bottom: 0;
        }

        .content .attp-management-tabs .nav-link {
            border: 1px solid transparent !important;
            border-radius: 9px !important;
            color: #475569 !important;
            background: transparent !important;
            font-weight: 700;
            padding: 0.65rem 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            opacity: 1 !important;
            pointer-events: auto;
        }

        .content .attp-management-tabs .nav-link:hover,
        .content .attp-management-tabs .nav-link:focus {
            color: #0f172a !important;
            background: #f1f5f9 !important;
            border-color: #cbd5e1 !important;
        }

        .content .attp-management-tabs .nav-link.active,
        .content .attp-management-tabs .nav-link[aria-current="page"] {
            color: #ffffff !important;
            background: linear-gradient(135deg, #0f172a 0%, #1d4ed8 58%, #0ea5e9 100%) !important;
            border-color: #1d4ed8 !important;
            box-shadow: 0 10px 20px rgba(29, 78, 216, 0.22);
            position: relative;
        }

        .content .attp-management-tabs .nav-link.active::before,
        .content .attp-management-tabs .nav-link[aria-current="page"]::before {
            content: "";
            width: 0.45rem;
            height: 0.45rem;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.18);
        }

        .content .attp-management-tabs .nav-link.disabled {
            color: #94a3b8 !important;
            background: #f8fafc !important;
            border-color: #e2e8f0 !important;
            cursor: not-allowed;
            pointer-events: none;
        }

        @if (request()->is('budget/me*'))
            .content .nxl-container > .page-header,
            .content .nxl-container .page-header {
                background: linear-gradient(130deg, #052e2b 0%, #0f766e 56%, #16a34a 100%) !important;
                color: #ffffff !important;
            }

            .content .nxl-container > .page-header h1,
            .content .nxl-container > .page-header h2,
            .content .nxl-container > .page-header h3,
            .content .nxl-container > .page-header h4,
            .content .nxl-container > .page-header h5,
            .content .nxl-container > .page-header h6,
            .content .nxl-container > .page-header p,
            .content .nxl-container > .page-header small,
            .content .nxl-container > .page-header .small,
            .content .nxl-container > .page-header .text-muted,
            .content .nxl-container > .page-header .text-primary,
            .content .nxl-container > .page-header .text-secondary,
            .content .nxl-container > .page-header .breadcrumb,
            .content .nxl-container > .page-header .breadcrumb-item,
            .content .nxl-container > .page-header .breadcrumb-item a,
            .content .nxl-container .survey-hero,
            .content .nxl-container .survey-hero h1,
            .content .nxl-container .survey-hero h2,
            .content .nxl-container .survey-hero h3,
            .content .nxl-container .survey-hero h4,
            .content .nxl-container .survey-hero p,
            .content .nxl-container .survey-hero small,
            .content .nxl-container .survey-hero .survey-hero__eyebrow,
            .content .nxl-container .ds-hero,
            .content .nxl-container .ds-hero .title,
            .content .nxl-container .ds-hero .subtitle {
                color: #ffffff !important;
            }

            .content .nxl-container > .page-header i[class*="text-"],
            .content .nxl-container .survey-hero i[class*="text-"],
            .content .nxl-container .ds-hero i[class*="text-"] {
                color: #ffffff !important;
            }

            .content .nxl-container > .page-header a:not(.btn),
            .content .nxl-container .survey-hero a:not(.btn),
            .content .nxl-container .ds-hero a:not(.btn) {
                color: #ffffff !important;
            }
        @endif

        /* Tawk.to Widget Styles - Override Theme Effects */
        #tawkToRight,
        #tawkToLeft,
        .tawk-min-container,
        .tawk-chat-container,
        [class*="tawk"] {
            z-index: 9999 !important;
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
            filter: none !important;
            backdrop-filter: none !important;
            transform: none !important;
            pointer-events: auto !important;
        }

        #tawkToRight:hover,
        #tawkToLeft:hover,
        .tawk-min-container:hover {
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }

        iframe[src*="tawk.to"],
        iframe[src*="embed.tawk"] {
            z-index: 9999 !important;
            opacity: 1 !important;
            visibility: visible !important;
            display: block !important;
        }
    </style>
</head>


<body>
    @include('layouts.partials.impersonation-banner')


    <div class="main-wrapper">
        <!-- Sidebar -->
        @include('layouts.partials.sidebar')

        <div class="content-wrapper">
            <!-- Header -->
            @include('layouts.partials.header')

            <!-- Main Content -->
            <div class="content p-4">
                @yield('content')
            </div>

            <!-- Footer -->
            @include('layouts.partials.footer')
        </div>
    </div>

    <!-- Scripts -->
    @php
        $useLeanAdminScripts = request()->routeIs('system.discussions.moderation.live')
            || trim($__env->yieldContent('lean_admin_scripts')) === '1';
    @endphp

    @if ($useLeanAdminScripts)
        {{-- The live monitor intentionally avoids duplicate and page-specific bundles.
             Re-loading jQuery/vendors/common initializers on a polling screen caused
             competing handlers and substantial main-thread work. --}}
        <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
        <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
        <script src="{{ asset('admin/assets/js/theme-customizer-init.min.js') }}"></script>
    @else
    <!--! BEGIN: Vendors JS -->
    <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/daterangepicker.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/circle-progress.min.js') }}"></script>
    <!--! END: Vendors JS -->

    <!--! BEGIN: Apps Init -->
    <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/dashboard-init.min.js') }}"></script>
    <!--! END: Apps Init -->

    <!--! BEGIN: Theme Customizer -->
    <script src="{{ asset('admin/assets/js/theme-customizer-init.min.js') }}"></script>
    <!--! END: Theme Customizer -->

    <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/leads-init.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/tagify.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/tagify-data.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/quill.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/select2-active.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/common-init.min.js') }}"></script>
    <script src="{{ asset('admin/assets/js/proposal-init.min.js') }}"></script>

    <!-- jQuery (loaded early for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <!-- DataTables Core -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>

    <!-- DataTables Buttons Extension -->
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.2.7/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.colVis.min.js"></script>

    <!-- Custom DataTable Configuration -->
    <script src="{{ asset('admin/assets/js/datatable-config.js') }}"></script>
    @endif

    <!-- Page-specific scripts -->
    @stack('scripts')
    @stack('modals')

    {{-- ATTP AI Guide Integration (Admin Controlled) --}}
    @php
        $aiGuideSettings = $useLeanAdminScripts ? null : \App\Models\AttpAiGuideSetting::active();
        $showAIGuide = $aiGuideSettings && $aiGuideSettings->isAvailableForUser();
    @endphp

    @if ($showAIGuide && $aiGuideSettings->tawk_property_id && $aiGuideSettings->tawk_widget_id)
        <!--Start of Tawk.to Script-->
        <script type="text/javascript">
            var Tawk_API = Tawk_API || {},
                Tawk_LoadStart = new Date();
            (function() {
                var s1 = document.createElement("script"),
                    s0 = document.getElementsByTagName("script")[0];
                s1.async = true;
                s1.src = 'https://embed.tawk.to/69204852eba156195f5dae48/1jaj1trqo';
                s1.charset = 'UTF-8';
                s1.setAttribute('crossorigin', '*');
                s0.parentNode.insertBefore(s1, s0);
            })();
        </script>
        <!--End of Tawk.to Script-->
    @endif

</body>

</html>
