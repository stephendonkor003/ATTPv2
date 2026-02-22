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
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('admin/assets/images/favicon.ico') }}">

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
    @if(app()->getLocale() === 'ar')
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

    </style>
</head>


<body>


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

    <!-- Page-specific scripts -->
    @stack('scripts')
    @stack('modals')


</body>

</html>
