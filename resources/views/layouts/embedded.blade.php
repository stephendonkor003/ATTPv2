<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'ATTP Preview')</title>
    <link rel="shortcut icon" type="image/jpeg" href="{{ asset('assets/images/attp-logo.jpeg') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/vendors/css/vendors.min.css') }}">
    <link rel="stylesheet" href="{{ asset('admin/assets/css/theme.min.css') }}">
    @stack('styles')
    <style>
        html,
        body {
            min-height: 100%;
            background: #f2f7f5;
        }

        body {
            margin: 0;
        }

        .nxl-container {
            position: static !important;
            top: auto !important;
            min-height: 100vh !important;
            margin: 0 !important;
        }

        .nxl-content {
            max-width: 1440px;
            margin: 0 auto;
            padding: 1rem !important;
        }
    </style>
</head>
<body>
    @yield('content')
    <script src="{{ asset('admin/assets/vendors/js/vendors.min.js') }}"></script>
    @stack('scripts')
</body>
</html>
