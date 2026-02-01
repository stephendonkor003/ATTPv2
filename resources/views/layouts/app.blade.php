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
