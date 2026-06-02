@props(['member', 'title' => 'Think Tank Portal', 'showPortalTabs' => true])

@extends('layouts.app')

@section('title', $title . ' | ATTP')

@push('styles')
    <style>
        .think-tank-workspace .page-header {
            margin-bottom: 1rem !important;
        }

        .think-tank-workspace .think-guide-hero {
            background: linear-gradient(130deg, #0f172a 0%, #1d4ed8 55%, #38bdf8 100%);
            border-radius: 16px;
            padding: 1.25rem;
            color: #f8fafc;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.2);
        }

        .think-tank-workspace .think-guide-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.45rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.35);
            border-radius: 999px;
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
            margin: 0.2rem 0.4rem 0 0;
        }

        .think-tank-workspace .tt-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .think-tank-workspace .top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 20px;
        }

        .think-tank-workspace .sub {
            color: #64748b;
            margin: 5px 0 0;
        }

        .think-tank-workspace .grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
        }

        .think-tank-workspace .grid.two {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .think-tank-workspace .section {
            margin-top: 18px;
        }

        .think-tank-workspace .card {
            border: 0;
            border-radius: 12px;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
        }

        .think-tank-workspace .metric {
            font-size: 24px;
            font-weight: 800;
            margin-top: 7px;
        }

        .think-tank-workspace .label {
            color: #64748b;
            font-size: 13px;
        }

        .think-tank-workspace table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .think-tank-workspace th,
        .think-tank-workspace td {
            padding: 11px 9px;
            border-bottom: 1px solid #e5e7eb;
            text-align: left;
            vertical-align: top;
        }

        .think-tank-workspace th {
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: .03em;
            background: #e2e8f0;
            border-bottom: 2px solid #94a3b8;
        }

        .think-tank-workspace .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #075985;
            font-size: 12px;
            font-weight: 700;
        }

        .think-tank-workspace .badge.good {
            background: #dcfce7;
            color: #166534;
        }

        .think-tank-workspace form.stack {
            display: grid;
            gap: 10px;
        }

        .think-tank-workspace input,
        .think-tank-workspace select,
        .think-tank-workspace textarea {
            width: 100%;
            border: 1px solid #d8dee8;
            border-radius: 6px;
            padding: 10px;
            font: inherit;
            background: #fff;
        }

        .think-tank-workspace textarea {
            min-height: 92px;
            resize: vertical;
        }

        .think-tank-workspace .row {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        .think-tank-workspace .btn.light {
            background: #e2e8f0;
            color: #0f172a;
        }

        @media (max-width: 900px) {
            .think-tank-workspace .grid,
            .think-tank-workspace .grid.two,
            .think-tank-workspace .row {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container think-tank-workspace">
        @php
            $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
                ? ['think_tank_member_id' => $member->id]
                : [];
        @endphp

        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-users text-primary me-2"></i>
                    {{ $title }}
                </h4>
                <p class="text-muted mb-0">{{ $member->name }} | {{ $member->consortium->name ?? 'Consortium' }}</p>
            </div>
            <a href="{{ route('think-tank.procurement', $portalRouteParams) }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> Procurement
            </a>
        </div>

        @if($showPortalTabs)
        <ul class="nav nav-tabs attp-management-tabs mb-4">
            <li class="nav-item">
                @can('think_tank.portal.access')
                    <a class="nav-link {{ request()->routeIs('think-tank.dashboard') ? 'active' : '' }}" @if(request()->routeIs('think-tank.dashboard')) aria-current="page" @endif href="{{ route('think-tank.dashboard', $portalRouteParams) }}">Dashboard</a>
                @else
                    <span class="nav-link disabled">Dashboard</span>
                @endcan
            </li>
            <li class="nav-item">
                @canany(['think_tank.reports.view', 'think_tank.reports.submit'])
                    <a class="nav-link {{ request()->routeIs('think-tank.reports*') ? 'active' : '' }}" @if(request()->routeIs('think-tank.reports*')) aria-current="page" @endif href="{{ route('think-tank.reports', $portalRouteParams) }}">Reports</a>
                @else
                    <span class="nav-link disabled">Reports</span>
                @endcanany
            </li>
            <li class="nav-item">
                @can('think_tank.reports.submit')
                    <a class="nav-link {{ request()->routeIs('think-tank.upload-report-finding') ? 'active' : '' }}" @if(request()->routeIs('think-tank.upload-report-finding')) aria-current="page" @endif href="{{ route('think-tank.upload-report-finding', $portalRouteParams) }}">Upload Report and Finding</a>
                @else
                    <span class="nav-link disabled">Upload Report and Finding</span>
                @endcan
            </li>
            <li class="nav-item">
                @canany(['think_tank.research.view', 'think_tank.research.submit'])
                    <a class="nav-link {{ request()->routeIs('think-tank.research*') ? 'active' : '' }}" @if(request()->routeIs('think-tank.research*')) aria-current="page" @endif href="{{ route('think-tank.research', $portalRouteParams) }}">Research</a>
                @else
                    <span class="nav-link disabled">Research</span>
                @endcanany
            </li>
            <li class="nav-item">
                @canany(['think_tank.procurement.view', 'think_tank.procurement.manage', 'think_tank.procurement.evaluate', 'think_tank.procurement.select'])
                    <a class="nav-link {{ request()->routeIs('think-tank.procurement*') ? 'active' : '' }}" @if(request()->routeIs('think-tank.procurement*')) aria-current="page" @endif href="{{ route('think-tank.procurement', $portalRouteParams) }}">Procurement</a>
                @else
                    <span class="nav-link disabled">Procurement</span>
                @endcanany
            </li>
            <li class="nav-item">
                @can('think_tank.procurement.manage')
                    <a class="nav-link {{ request()->routeIs('think-tank.purchase-orders*') ? 'active' : '' }}" @if(request()->routeIs('think-tank.purchase-orders*')) aria-current="page" @endif href="{{ route('think-tank.purchase-orders', $portalRouteParams) }}">Purchase Orders</a>
                @else
                    <span class="nav-link disabled">Purchase Orders</span>
                @endcan
            </li>
        </ul>
        @endif

        <div class="card shadow-sm border-0 overflow-hidden mb-4">
            <div class="card-body p-4">
                <div class="think-guide-hero">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                            <span class="badge bg-light text-primary fw-semibold mb-2">Think Tank Portal</span>
                            <h4 class="fw-bold mb-2 text-white">{{ $member->name }}</h4>
                            <p class="mb-0">
                                Manage reports, research submissions, procurement plans, and opportunities from your dedicated think tank workspace.
                            </p>
                            <div class="mt-3">
                                <span class="think-guide-chip"><i class="feather-file-text"></i> Secretariat reports</span>
                                <span class="think-guide-chip"><i class="feather-book-open"></i> Research outputs</span>
                                <span class="think-guide-chip"><i class="feather-briefcase"></i> Procurement</span>
                            </div>
                        </div>
                        <div class="col-lg-4">
                            <div class="p-3 rounded-3" style="background: rgba(15, 23, 42, 0.18); border: 1px solid rgba(255,255,255,.3);">
                                <p class="fw-semibold mb-1">Consortium</p>
                                <p class="mb-0 small">{{ $member->consortium->name ?? 'Consortium' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Legacy compact header retained for page actions that expect .top spacing --}}
        <div class="d-none">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <span class="badge bg-primary-subtle text-primary mb-2">Think Tank Management</span>
                    <h4 class="mb-1">{{ $member->name }}</h4>
                    <p class="text-muted mb-0">{{ $member->consortium->name ?? 'Consortium' }}</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a class="btn btn-light border {{ request()->routeIs('think-tank.dashboard') ? 'active' : '' }}" href="{{ route('think-tank.dashboard', $portalRouteParams) }}">Dashboard</a>
                    <a class="btn btn-light border {{ request()->routeIs('think-tank.reports*') ? 'active' : '' }}" href="{{ route('think-tank.reports', $portalRouteParams) }}">Reports</a>
                    <a class="btn btn-light border {{ request()->routeIs('think-tank.research*') ? 'active' : '' }}" href="{{ route('think-tank.research', $portalRouteParams) }}">Research</a>
                    <a class="btn btn-light border {{ request()->routeIs('think-tank.procurement*') ? 'active' : '' }}" href="{{ route('think-tank.procurement', $portalRouteParams) }}">Procurement</a>
                </div>
            </div>
        </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if (isset($errors) && $errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        {{ $slot }}
    </div>
@endsection
