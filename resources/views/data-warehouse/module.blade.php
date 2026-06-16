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
        .km-module-shell {
            color: #111827;
        }

        .km-module-hero {
            background: linear-gradient(120deg, #0f172a 0%, {{ $selectedModule['accent'] }} 58%, #2563eb 100%);
            border-radius: 8px;
            color: #fff;
            overflow: hidden;
            position: relative;
        }

        .km-module-hero::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(90deg, rgba(255,255,255,.15), transparent 50%);
            pointer-events: none;
        }

        .km-module-hero-body {
            position: relative;
            z-index: 1;
            padding: 28px;
        }

        .km-folder-large {
            width: 88px;
            height: 88px;
            object-fit: contain;
            filter: drop-shadow(0 16px 24px rgba(15, 23, 42, .26));
        }

        .km-module-stat,
        .km-module-panel {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .km-module-stat {
            padding: 18px;
            height: 100%;
        }

        .km-module-panel-header {
            padding: 20px 22px;
            background: #f8fafc;
            border-bottom: 1px solid #e5e7eb;
        }

        .km-file-card {
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 16px;
            background: #fff;
            height: 100%;
        }

        .km-file-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 46px;
            height: 30px;
            border-radius: 6px;
            background: #fffbeb;
            color: #92400e;
            font-size: .72rem;
            font-weight: 800;
        }

        .km-path {
            max-width: 330px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        @media (max-width: 767.98px) {
            .km-module-hero-body {
                padding: 22px;
            }

            .km-folder-large {
                width: 68px;
                height: 68px;
            }

            .km-path {
                max-width: 210px;
            }
        }
    </style>

    <div class="nxl-container km-module-shell">
        <div class="km-module-hero mb-4">
            <div class="km-module-hero-body d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
                <div class="d-flex align-items-center gap-3">
                    <img src="{{ $folderImage }}" alt="" class="km-folder-large">
                    <div>
                        <div class="text-uppercase small fw-semibold text-white-50 mb-2">Knowledge Folder</div>
                        <h3 class="fw-bold mb-2 text-white">{{ $selectedModule['label'] }}</h3>
                        <p class="mb-0 text-white-50">{{ $selectedModule['description'] }}</p>
                    </div>
                </div>
                <a href="{{ route('data-warehouse.index') }}" class="btn btn-light">
                    <i class="feather-arrow-left me-1"></i> Back to Folders
                </a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="km-module-stat">
                    <div class="text-muted small">Files In Folder</div>
                    <div class="h4 fw-bold mb-0">{{ number_format($files->count()) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="km-module-stat">
                    <div class="text-muted small">Stored Size</div>
                    <div class="h4 fw-bold mb-0">{{ $formatBytes($files->sum('size')) }}</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="km-module-stat">
                    <div class="text-muted small">Latest Upload</div>
                    <div class="h6 fw-bold mb-0">{{ $formatDate($files->max('uploaded_at')) }}</div>
                </div>
            </div>
        </div>

        <div class="km-module-panel">
            <div class="km-module-panel-header d-flex flex-column flex-lg-row justify-content-between gap-2">
                <div>
                    <h5 class="fw-bold mb-1">Files</h5>
                    <div class="text-muted small">View, download, and audit uploaded files in this module folder.</div>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('data-warehouse.create') }}" class="btn btn-sm btn-primary">
                        <i class="feather-upload-cloud me-1"></i> Add Knowledge Record
                    </a>
                </div>
            </div>

            <div class="p-3 p-lg-4">
                @if ($files->isEmpty())
                    <div class="alert alert-light border mb-0">
                        <i class="feather-folder me-1"></i> No uploaded files were found in this folder yet.
                    </div>
                @else
                    <div class="row g-3 mb-4">
                        @foreach ($files->take(3) as $file)
                            <div class="col-lg-4">
                                <div class="km-file-card">
                                    <div class="d-flex align-items-start gap-2 mb-3">
                                        <span class="km-file-badge">{{ $file['extension'] }}</span>
                                        <div>
                                            <div class="fw-bold">{{ \Illuminate\Support\Str::limit($file['title'], 42) }}</div>
                                            <div class="text-muted small">{{ $formatDate($file['uploaded_at']) }}</div>
                                        </div>
                                    </div>
                                    <div class="text-muted small mb-3">{{ \Illuminate\Support\Str::limit($file['description'] ?: $file['source'], 95) }}</div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="small fw-semibold">{{ $formatBytes($file['size']) }}</span>
                                        <a href="{{ $file['view_url'] }}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                            <i class="feather-eye me-1"></i> View
                                        </a>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <x-data-table id="knowledgeModuleFilesTable">
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
                @endif
            </div>
        </div>
    </div>
@endsection
