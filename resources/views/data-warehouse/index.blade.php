@extends('layouts.app')

@section('content')
    @php
        $formatBytes = function ($bytes) {
            $bytes = (float) $bytes;
            foreach (['B', 'KB', 'MB', 'GB', 'TB'] as $unit) {
                if ($bytes < 1024 || $unit === 'TB') {
                    return number_format($bytes, $unit === 'B' ? 0 : 2) . ' ' . $unit;
                }
                $bytes /= 1024;
            }
        };
        $formatDate = fn ($date) => $date ? \Carbon\Carbon::parse($date)->format('d M Y, H:i') : 'N/A';
        $folderImage = asset('assets/images/knowledge-folder.svg');
    @endphp

    <style>
        .km-shell {
            color: #111827;
        }

        .km-hero {
            background: linear-gradient(120deg, #0f172a 0%, #0f766e 48%, #2563eb 100%);
            border-radius: 8px;
            color: #f8fafc;
            overflow: hidden;
            position: relative;
        }

        .km-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255,255,255,0.14), transparent 45%);
            pointer-events: none;
        }

        .km-hero-body {
            position: relative;
            z-index: 1;
            padding: 28px;
        }

        .km-stat {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            padding: 18px;
            height: 100%;
            transition: transform .16s ease, box-shadow .16s ease;
        }

        .km-stat:hover,
        .km-folder:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 30px rgba(15, 23, 42, .12);
        }

        .km-folder {
            display: block;
            height: 100%;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
            color: inherit;
            text-decoration: none;
            padding: 18px;
            cursor: pointer;
            overflow: hidden;
            position: relative;
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease, background .18s ease;
        }

        .km-folder::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, color-mix(in srgb, var(--km-accent) 13%, transparent), transparent 52%);
            opacity: 0;
            transition: opacity .18s ease;
        }

        .km-folder > * {
            position: relative;
            z-index: 1;
        }

        .km-folder:hover,
        .km-folder:focus {
            border-color: var(--km-accent);
            background: #ffffff;
            box-shadow: 0 18px 38px rgba(15, 23, 42, .16);
            color: inherit;
            outline: none;
            transform: translateY(-6px);
        }

        .km-folder:hover::before,
        .km-folder:focus::before {
            opacity: 1;
        }

        .km-folder-icon {
            width: 52px;
            height: 52px;
            object-fit: contain;
            filter: drop-shadow(0 8px 12px rgba(15, 23, 42, .18));
            transition: transform .18s ease, filter .18s ease;
        }

        .km-folder:hover .km-folder-icon,
        .km-folder:focus .km-folder-icon {
            filter: drop-shadow(0 12px 18px rgba(15, 23, 42, .24));
            transform: scale(1.08) rotate(-2deg);
        }

        .km-folder-accent {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: var(--km-accent);
            display: inline-block;
        }

        .km-folder-open {
            align-items: center;
            border-top: 1px solid #eef2f7;
            color: var(--km-accent);
            display: flex;
            font-weight: 700;
            gap: 6px;
            justify-content: space-between;
            margin-top: 16px;
            padding-top: 12px;
        }

        .km-folder-open i {
            transition: transform .18s ease;
        }

        .km-folder:hover .km-folder-open i,
        .km-folder:focus .km-folder-open i {
            transform: translateX(4px);
        }

        @media (max-width: 767.98px) {
            .km-hero-body {
                padding: 22px;
            }
        }
    </style>

    <div class="nxl-container km-shell">
        <div class="km-hero mb-4">
            <div class="km-hero-body d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Knowledge Management</div>
                    <h3 class="fw-bold mb-2 text-white">System File Library</h3>
                    <p class="mb-0 text-white-50">
                        Documents, images, evidence, contracts, PDFs, videos, and uploaded files grouped by module.
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 align-items-start">
                    <a href="{{ route('data-warehouse.create') }}" class="btn btn-light">
                        <i class="feather-upload-cloud me-1"></i> Add Knowledge Record
                    </a>
                    <a href="{{ route('data-warehouse.categories') }}" class="btn btn-outline-light">
                        <i class="feather-tag me-1"></i> Categories
                    </a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="km-stat">
                    <div class="text-muted small">Module Folders</div>
                    <div class="h4 fw-bold mb-0">{{ number_format($stats['modules']) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="km-stat">
                    <div class="text-muted small">Indexed Files</div>
                    <div class="h4 fw-bold mb-0">{{ number_format($stats['files']) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="km-stat">
                    <div class="text-muted small">Stored Size</div>
                    <div class="h4 fw-bold mb-0">{{ $formatBytes($stats['size']) }}</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="km-stat">
                    <div class="text-muted small">Latest Upload</div>
                    <div class="h6 fw-bold mb-0">{{ $formatDate($stats['latest_upload']) }}</div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ($modules as $module)
                <div class="col-sm-6 col-xl-3">
                    <a class="km-folder"
                        style="--km-accent: {{ $module['accent'] }};"
                        href="{{ route('data-warehouse.modules.show', $module['slug']) }}">
                        <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                            <img src="{{ $folderImage }}" alt="" class="km-folder-icon">
                            <span class="km-folder-accent"></span>
                        </div>
                        <div class="fw-bold mb-1">{{ $module['label'] }}</div>
                        <div class="text-muted small mb-3">{{ $module['description'] }}</div>
                        <div class="d-flex justify-content-between small">
                            <span>{{ number_format($module['files_count']) }} files</span>
                            <span>{{ $formatBytes($module['stored_size']) }}</span>
                        </div>
                        <div class="km-folder-open small">
                            <span>Open files</span>
                            <i class="feather-arrow-right"></i>
                        </div>
                    </a>
                </div>
            @endforeach
        </div>
    </div>
@endsection
