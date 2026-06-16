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
            transition: transform .16s ease, box-shadow .16s ease, border-color .16s ease;
        }

        .km-folder.active {
            border-color: var(--km-accent);
            box-shadow: 0 14px 30px rgba(15, 23, 42, .12);
        }

        .km-folder-icon {
            width: 52px;
            height: 52px;
            object-fit: contain;
            filter: drop-shadow(0 8px 12px rgba(15, 23, 42, .18));
        }

        .km-folder-accent {
            width: 12px;
            height: 12px;
            border-radius: 999px;
            background: var(--km-accent);
            display: inline-block;
        }

        .km-panel {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #ffffff;
        }

        .km-panel-header {
            padding: 20px 22px;
            border-bottom: 1px solid #e5e7eb;
            background: #f8fafc;
        }

        .km-file-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            height: 28px;
            border-radius: 6px;
            background: #eef2ff;
            color: #3730a3;
            font-size: .72rem;
            font-weight: 700;
        }

        .km-path {
            max-width: 360px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .km-hero-body {
                padding: 22px;
            }

            .km-path {
                max-width: 220px;
            }
        }
    </style>

    <div class="nxl-container km-shell">
        <div class="km-hero mb-4">
            <div class="km-hero-body d-flex flex-column flex-lg-row justify-content-between gap-3">
                <div>
                    <div class="text-uppercase small fw-semibold text-white-50 mb-2">Knowledge Management</div>
                    <h3 class="fw-bold mb-2">System File Library</h3>
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
                    <a class="km-folder {{ $selectedModule['slug'] === $module['slug'] ? 'active' : '' }}"
                        style="--km-accent: {{ $module['accent'] }};"
                        href="{{ route('data-warehouse.index', ['module' => $module['slug']]) }}">
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
                    </a>
                </div>
            @endforeach
        </div>

        <div class="km-panel">
            <div class="km-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="km-folder-accent" style="--km-accent: {{ $selectedModule['accent'] }};"></span>
                        <h5 class="fw-bold mb-0">{{ $selectedModule['label'] }}</h5>
                    </div>
                    <div class="text-muted">{{ $selectedModule['description'] }}</div>
                </div>
                <div class="text-lg-end">
                    <div class="fw-bold">{{ number_format($files->count()) }} files</div>
                    <div class="text-muted small">{{ $formatBytes($files->sum('size')) }}</div>
                </div>
            </div>

            <div class="p-3 p-lg-4">
                @if ($files->isEmpty())
                    <div class="alert alert-light border mb-0">
                        <i class="feather-folder me-1"></i> No uploaded files were found for this module yet.
                    </div>
                @else
                    <div class="table-responsive">
                        <x-data-table id="knowledgeFilesTable">
                            <thead class="table-light">
                                <tr>
                                    <th>File</th>
                                    <th>Description</th>
                                    <th>Source</th>
                                    <th>Date</th>
                                    <th>Size</th>
                                    <th>Uploaded By</th>
                                    <th>Storage</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($files as $file)
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-start gap-2">
                                                <span class="km-file-badge">{{ $file['extension'] }}</span>
                                                <div>
                                                    <div class="fw-semibold">{{ $file['title'] }}</div>
                                                    <div class="small text-muted">{{ $file['original_name'] }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>{{ \Illuminate\Support\Str::limit($file['description'] ?: 'N/A', 90) }}</td>
                                        <td>{{ $file['source'] }}</td>
                                        <td>{{ $formatDate($file['uploaded_at']) }}</td>
                                        <td>{{ $formatBytes($file['size']) }}</td>
                                        <td>{{ $file['uploaded_by'] ?: 'N/A' }}</td>
                                        <td>
                                            <div class="small text-muted text-uppercase">{{ $file['disk'] }}</div>
                                            <div class="small km-path" title="{{ $file['path'] }}">{{ $file['path'] }}</div>
                                        </td>
                                        <td class="text-end">
                                            <div class="btn-group btn-group-sm">
                                                <a href="{{ $file['view_url'] }}" target="_blank" class="btn btn-outline-secondary">
                                                    <i class="feather-eye me-1"></i> View
                                                </a>
                                                <a href="{{ $file['download_url'] }}" class="btn btn-outline-primary">
                                                    <i class="feather-download me-1"></i> Download
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </x-data-table>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
