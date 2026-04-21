@extends('layouts.app')
@section('title', 'Data Source Controller')

@php
    $columnMapFields = [
        'actual_value' => 'Actual Value',
        'period_label' => 'Period Label',
        'period_type' => 'Period Type',
        'period_start' => 'Period Start',
        'period_end' => 'Period End',
        'method' => 'Method',
        'notes' => 'Notes',
    ];
@endphp

@push('styles')
    <style>
        .ds-hero {
            border: 0;
            background: linear-gradient(130deg, #0f172a 0%, #0f766e 55%, #0ea5e9 100%);
            color: #f8fafc;
            border-radius: 16px;
            box-shadow: 0 14px 28px rgba(15, 23, 42, 0.24);
        }

        .ds-hero .title {
            font-size: 1.15rem;
            font-weight: 700;
        }

        .ds-hero .subtitle {
            color: rgba(248, 250, 252, 0.9);
            font-size: 0.88rem;
        }

        .ds-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.3rem 0.72rem;
            border-radius: 999px;
            border: 1px solid rgba(248, 250, 252, 0.4);
            background: rgba(248, 250, 252, 0.16);
            font-size: 0.74rem;
            font-weight: 600;
            margin: 0.22rem 0.35rem 0 0;
        }

        .ds-stat-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: #ffffff;
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            height: 100%;
        }

        .ds-stat-card .label {
            color: #64748b;
            font-size: 0.76rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .ds-stat-card .value {
            color: #0f172a;
            font-size: 1.24rem;
            font-weight: 700;
            line-height: 1.1;
        }

        .ds-search-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 10px 20px rgba(15, 23, 42, 0.04);
        }

        .ds-table-card {
            border: 1px solid #dbe4ef;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.05);
        }

        .ds-table thead th {
            background: #e2e8f0;
            color: #0f172a;
            font-size: 0.74rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
            position: sticky;
            top: 0;
            z-index: 2;
        }

        .ds-table tbody tr:nth-child(odd) {
            background: #fcfdff;
        }

        .ds-table td {
            vertical-align: top;
            font-size: 0.82rem;
        }

        .ds-source-box {
            border: 1px solid #d6e4ff;
            border-radius: 10px;
            background: #f8fbff;
            padding: 0.55rem 0.65rem;
        }

        .ds-file-list {
            border: 1px solid #dbe4ef;
            border-radius: 10px;
            background: #ffffff;
            max-height: 150px;
            overflow-y: auto;
        }

        .ds-sync-panel {
            border: 1px solid #cfe0f7;
            border-radius: 12px;
            background: linear-gradient(180deg, #f8fbff 0%, #eef5ff 100%);
            padding: 0.6rem;
        }

        .ds-sync-panel .form-label {
            color: #1e293b;
            font-size: 0.72rem;
            margin-bottom: 0.15rem;
            font-weight: 600;
        }

        .ds-sync-panel .form-text {
            font-size: 0.68rem;
            color: #64748b;
            margin-top: 0.15rem;
        }

        .ds-map-grid .form-select {
            font-size: 0.74rem;
        }

        .ds-map-grid .form-label {
            font-size: 0.67rem;
            margin-bottom: 0.1rem;
        }

        .ds-map-box {
            border: 1px solid #dbe4ef;
            border-radius: 10px;
            background: #ffffff;
            padding: 0.5rem;
        }

        .ds-pill {
            border-radius: 999px;
            padding: 0.2rem 0.56rem;
            font-size: 0.68rem;
            font-weight: 600;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .ds-pill.success {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .ds-pill.warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .ds-pill.danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .ds-pill.secondary {
            background: #e2e8f0;
            color: #334155;
            border-color: #cbd5e1;
        }

        @media (max-width: 992px) {
            .ds-table-card {
                border-radius: 10px;
            }

            .ds-table thead th {
                position: static;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="card ds-hero mb-3">
            <div class="card-body p-3 p-lg-4 d-flex flex-wrap justify-content-between gap-3 align-items-start">
                <div>
                    <div class="title">
                        <i class="feather-database me-2"></i>Data Source Controller
                    </div>
                    <p class="subtitle mb-2">
                        Link indicator data feeds, map dynamic columns, and sync from CSV/Excel sources with reusable rules.
                    </p>
                    <div>
                        <span class="ds-chip"><i class="feather-grid"></i> Editable Column Mapping</span>
                        <span class="ds-chip"><i class="feather-trending-up"></i> Growing Rows Support</span>
                        <span class="ds-chip"><i class="feather-calculator"></i> Calculated Excel Values</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('budget.me.data-sources.sync-all', ['q' => request('q')]) }}">
                    @csrf
                    <button type="submit" class="btn btn-light btn-sm fw-semibold px-3"
                        onclick="return confirm('Run manual sync for all configured data sources?');">
                        <i class="feather-refresh-cw me-1"></i> Manual Sync All
                    </button>
                </form>
            </div>
        </div>

        {{-- Surveys & Responses --}}
        <div class="card shadow-sm mb-3" id="survey-management">
            <div class="card-header bg-light d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 fw-semibold">Surveys & Responses</h6>
                    <small class="text-muted d-block">Survey links generated from indicators and their collected responses.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary js-copy-all-survey-links">
                        <i class="feather-clipboard me-1"></i> Copy All Links
                    </button>
                    <a class="btn btn-sm btn-outline-primary" href="{{ route('budget.me.data-sources.surveys.export', ['format' => 'csv']) }}">
                        <i class="feather-download me-1"></i> Export CSV
                    </a>
                    <a class="btn btn-sm btn-outline-secondary disabled" tabindex="-1" aria-disabled="true">
                        <i class="feather-file-text me-1"></i> Export PDF (coming soon)
                    </a>
                </div>
            </div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="ds-stat-card p-3">
                            <div class="label">Surveys</div>
                            <div class="value">{{ $surveyStats['surveys'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-6 col-md-4 col-xl-3">
                        <div class="ds-stat-card p-3">
                            <div class="label">Responses</div>
                            <div class="value">{{ $surveyStats['responses'] ?? 0 }}</div>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 col-xl-3">
                        <div class="ds-stat-card p-3">
                            <div class="label">Last Submission</div>
                            <div class="value">
                                {{ optional($surveyStats['last_response'])->format('d M Y H:i') ?? '—' }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive ds-table-card">
                    <table class="table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Survey Token</th>
                                <th>Indicator</th>
                                <th>Methodology</th>
                                <th>Responses</th>
                                <th>Created</th>
                                <th>Status</th>
                                <th>Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($surveyLinks ?? [] as $survey)
                                <tr>
                                    <td><span class="fw-semibold text-primary">{{ $survey->public_token }}</span></td>
                                    <td>{{ $survey->indicator->name ?? '—' }}</td>
                                    <td>{{ $survey->methodology->name ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success">{{ $survey->responses_count }}</span>
                                    </td>
                                    <td>{{ optional($survey->created_at)->format('d M Y') }}</td>
                                    <td>
                                        @if ($survey->is_active)
                                            <span class="ds-pill success"><i class="feather-check"></i> Active</span>
                                        @else
                                            <span class="ds-pill secondary"><i class="feather-pause"></i> Inactive</span>
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $publicUrl = route('public.me.indicators.surveys.show', ['token' => $survey->public_token]);
                                            $qrUrl = \App\Support\MeSurvey::qrCodeUrl($publicUrl);
                                        @endphp
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="{{ $publicUrl }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="feather-external-link me-1"></i> Open
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-secondary js-copy-survey-link"
                                                data-link="{{ $publicUrl }}">
                                                <i class="feather-clipboard me-1"></i> Copy Link
                                            </button>
                                            <a class="btn btn-sm btn-outline-success"
                                                href="https://wa.me/?text={{ urlencode($publicUrl) }}" target="_blank" rel="noopener">
                                                <i class="feather-share-2 me-1"></i> Share
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-dark js-open-survey-qr"
                                                data-link="{{ $publicUrl }}"
                                                data-qr="{{ $qrUrl }}"
                                                data-title="{{ $survey->indicator->name ?? 'Survey QR Code' }}">
                                                <i class="feather-grid me-1"></i> QR Code
                                            </button>
                                        </div>
                                        <small class="text-muted">Share on WhatsApp / social / email, or open a QR code for download.</small>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-3">No surveys configured yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat-card p-3">
                    <div class="label">Indicators</div>
                    <div class="value">{{ $summary['total'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat-card p-3">
                    <div class="label">File Sources</div>
                    <div class="value">{{ $summary['file_sources'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat-card p-3">
                    <div class="label">Link/Connector</div>
                    <div class="value">{{ $summary['link_sources'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat-card p-3">
                    <div class="label">Dir Accessible</div>
                    <div class="value">{{ $summary['accessible_directories'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat-card p-3">
                    <div class="label">Last Success</div>
                    <div class="value">{{ $summary['last_success'] ?? 0 }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat-card p-3">
                    <div class="label">Need Attention</div>
                    <div class="value text-danger">{{ $summary['needs_attention'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="card ds-search-card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('budget.me.data-sources.index') }}" class="row g-2 align-items-end">
                    <div class="col-lg-10">
                        <label for="dataSourceSearch" class="form-label mb-1 fw-semibold">Search Data Sources</label>
                        <input type="text" id="dataSourceSearch" name="q" value="{{ $search }}"
                            class="form-control form-control-sm"
                            placeholder="Search by indicator, source path/URL, or methodology">
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="feather-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ds-table-card">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0 ds-table">
                    <thead>
                        <tr>
                            <th class="ps-4">Indicator</th>
                            <th>Owner</th>
                            <th>Source Type</th>
                            <th>Source / Directory</th>
                            <th>Last Sync</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th class="text-end pe-4" style="min-width: 430px;">Sync Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php
                                $status = strtolower((string) $row['last_status']);
                                $statusClass = match ($status) {
                                    'success' => 'success',
                                    'partial' => 'warning',
                                    'failed' => 'danger',
                                    default => 'secondary',
                                };
                                $indicator = $row['indicator'];
                                $supportedFiles = $row['supported_files'] ?? [];
                                $columnsByFile = collect($supportedFiles)->mapWithKeys(function ($file) {
                                    return [$file['name'] => $file['columns'] ?? []];
                                });
                                $selectedSourceFile = $row['saved_selected_file'] ?: ($row['default_source_file'] ?? '');
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $indicator->name }}</div>
                                    <small class="text-muted">{{ $row['last_message'] }}</small>
                                </td>
                                <td>{{ $row['owner'] }}</td>
                                <td>{{ $row['source_type'] }}</td>
                                <td style="max-width: 350px;">
                                    <div class="small text-break">{{ $row['source_value'] }}</div>
                                    @if ($row['source_url'])
                                        <div class="mt-1">
                                            <a href="{{ $row['source_url'] }}" target="_blank" class="btn btn-sm btn-outline-info">
                                                <i class="feather-external-link me-1"></i> Open Source
                                            </a>
                                        </div>
                                    @endif

                                    @if ($row['source_type_key'] === 'file_location')
                                        <div class="ds-source-box mt-2">
                                            <div class="small fw-semibold mb-1">Directory Access</div>
                                            <div class="small text-muted text-break">{{ $row['directory_path'] ?: 'Not accessible' }}</div>
                                            <div class="mt-1 small">
                                                <span class="ds-pill {{ $row['directory_accessible'] ? 'success' : 'danger' }}">
                                                    {{ $row['directory_accessible'] ? 'Accessible' : 'Not Accessible' }}
                                                </span>
                                                <span class="text-muted ms-1">{{ count($row['directory_files']) }} file(s)</span>
                                            </div>
                                            @if (!empty($row['directory_files']))
                                                <details class="mt-2">
                                                    <summary class="small fw-semibold" style="cursor: pointer;">Show directory files</summary>
                                                    <div class="ds-file-list mt-2 p-2">
                                                        @foreach ($row['directory_files'] as $file)
                                                            <div
                                                                class="d-flex justify-content-between align-items-center gap-2 small py-1 border-bottom">
                                                                <span class="text-break">{{ $file['name'] }}</span>
                                                                <span class="text-nowrap">
                                                                    <span
                                                                        class="badge bg-{{ $file['is_supported'] ? 'primary' : 'secondary' }}">
                                                                        {{ strtoupper($file['extension']) }}
                                                                    </span>
                                                                    <span class="text-muted ms-1">{{ $file['size_human'] }}</span>
                                                                </span>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                                <td>{{ $row['last_sync_at'] ? $row['last_sync_at']->format('Y-m-d H:i:s') : 'Never' }}</td>
                                <td>
                                    <span class="ds-pill {{ $statusClass }}">
                                        {{ $status === 'never' ? 'Never Synced' : ucfirst($status) }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route('budget.me.data-sources.raw-data', ['indicator' => $indicator, 'q' => request('q')]) }}"
                                        class="fw-semibold text-decoration-none">
                                        {{ $row['synced_rows'] }}
                                    </a>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-grid gap-1">
                                        <a href="{{ route('budget.me.data-sources.raw-data', ['indicator' => $indicator, 'q' => request('q')]) }}"
                                            class="btn btn-sm btn-outline-dark fw-semibold">
                                            <i class="feather-eye me-1"></i> View Raw Data
                                        </a>
                                        <a href="{{ route('budget.me.data-sources.template.download', ['indicator' => $indicator, 'source_type' => $row['source_type_key'], 'source_value' => $row['source_value']]) }}"
                                            class="btn btn-sm btn-outline-primary fw-semibold">
                                            <i class="feather-download me-1"></i> Data Source Bridge Template
                                        </a>

                                        <form method="POST" action="{{ route('budget.me.data-sources.sync', $indicator) }}"
                                            class="data-source-sync-form ds-sync-panel text-start"
                                            data-column-map='@json($columnsByFile)'
                                            data-saved-map='@json($row['saved_column_map'] ?? [])'
                                            data-saved-row-mode="{{ $row['saved_row_mode'] ?? 'all_rows' }}"
                                            data-preview-url="{{ route('budget.me.data-sources.columns-preview', $indicator) }}"
                                            enctype="multipart/form-data">
                                            @csrf
                                            <input type="hidden" name="q" value="{{ request('q') }}">

                                            @if ($row['source_type_key'] === 'file_location')
                                                <div class="mb-2">
                                                    <label class="form-label">Select File (CSV/XLSX/XLS)</label>
                                                    <select name="source_file"
                                                        class="form-select form-select-sm source-file-select">
                                                        <option value="">Auto-select latest supported file</option>
                                                        @foreach ($supportedFiles as $file)
                                                            <option value="{{ $file['name'] }}" @selected($selectedSourceFile === $file['name'])>
                                                                {{ $file['name'] }} ({{ strtoupper($file['extension']) }})
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label">Or Upload File (One-time Sync)</label>
                                                    <input type="file" name="upload_file"
                                                        class="form-control form-control-sm upload-file-input"
                                                        accept=".csv,.xlsx,.xls">
                                                    <div class="form-text upload-columns-status">
                                                        Upload is applied for this sync only.
                                                    </div>
                                                </div>

                                                <div class="mb-2">
                                                    <label class="form-label">Row Sync Mode</label>
                                                    <select name="row_mode" class="form-select form-select-sm row-mode-select">
                                                        <option value="all_rows">All Rows (for growing datasets)</option>
                                                        <option value="latest_row_only">Latest Row Only (for live/calculated value)</option>
                                                    </select>
                                                    <div class="form-text">
                                                        Choose <strong>Latest Row Only</strong> when the newest row always holds the current
                                                        indicator value.
                                                    </div>
                                                </div>

                                                <div class="ds-map-box mb-2">
                                                    <div class="small fw-semibold mb-1">Select Columns To Store (Editable & Reusable)</div>
                                                    <div class="row g-1 ds-map-grid">
                                                        @foreach ($columnMapFields as $fieldKey => $fieldLabel)
                                                            <div class="col-6">
                                                                <label class="form-label">{{ $fieldLabel }}</label>
                                                                <select name="column_map[{{ $fieldKey }}]"
                                                                    class="form-select form-select-sm column-map-select"
                                                                    data-field="{{ $fieldKey }}">
                                                                    <option value="">Auto/None</option>
                                                                </select>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                    <div class="form-text">
                                                        Mapping is saved and reused for next manual sync and sync-all.
                                                        Actual Value supports number, Yes/No, True/False, %, and values like 6kg.
                                                    </div>
                                                </div>
                                            @endif

                                            <button type="submit" class="btn btn-sm btn-success w-100 fw-semibold">
                                                <i class="feather-refresh-cw me-1"></i> Sync Indicator Data
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No indicators with primary data source configured.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($indicators->hasPages())
                <div class="card-footer border-0 bg-white">
                    {{ $indicators->links() }}
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="surveyQrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <h5 class="modal-title mb-1" id="surveyQrModalTitle">Survey QR Code</h5>
                        <small class="text-muted">Scan to open the public survey.</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Survey QR code" id="surveyQrModalImage" class="img-fluid rounded border p-2 bg-white">
                    <div class="small text-muted mt-3" id="surveyQrModalLink"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary js-copy-survey-qr-link">Copy Link</button>
                    <button type="button" class="btn btn-outline-primary js-share-survey-qr-link">Share</button>
                    <button type="button" class="btn btn-primary js-download-survey-qr">Download QR</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const copyAllBtn = document.querySelector('.js-copy-all-survey-links');
            if (copyAllBtn) {
                copyAllBtn.addEventListener('click', async () => {
                    const links = Array.from(document.querySelectorAll('.js-copy-survey-link'))
                        .map((btn) => btn.dataset.link)
                        .filter((link) => !!link);
                    const bundle = links.join('\n');
                    if (!bundle) return;
                    const original = copyAllBtn.innerHTML;
                    try {
                        await navigator.clipboard.writeText(bundle);
                        copyAllBtn.classList.remove('btn-outline-secondary');
                        copyAllBtn.classList.add('btn-success');
                        copyAllBtn.innerHTML = '<i class="feather-check me-1"></i> Copied';
                        setTimeout(() => {
                            copyAllBtn.classList.add('btn-outline-secondary');
                            copyAllBtn.classList.remove('btn-success');
                            copyAllBtn.innerHTML = original;
                        }, 1800);
                    } catch (error) {
                        window.prompt('Copy survey links:', bundle);
                    }
                });
            }

            const copyButtons = document.querySelectorAll('.js-copy-survey-link');
            copyButtons.forEach((btn) => {
                btn.addEventListener('click', async () => {
                    const link = btn.dataset.link || '';
                    if (!link) {
                        return;
                    }
                    const original = btn.innerHTML;
                    try {
                        await navigator.clipboard.writeText(link);
                        btn.classList.remove('btn-outline-secondary');
                        btn.classList.add('btn-success');
                        btn.innerHTML = '<i class="feather-check me-1"></i> Copied';
                        setTimeout(() => {
                            btn.classList.add('btn-outline-secondary');
                            btn.classList.remove('btn-success');
                            btn.innerHTML = original;
                        }, 1800);
                    } catch (error) {
                        window.prompt('Copy survey link:', link);
                    }
                });
            });

            const qrModalElement = document.getElementById('surveyQrModal');
            const qrModal = qrModalElement && window.bootstrap ? new bootstrap.Modal(qrModalElement) : null;
            const qrImage = document.getElementById('surveyQrModalImage');
            const qrTitle = document.getElementById('surveyQrModalTitle');
            const qrLink = document.getElementById('surveyQrModalLink');
            const qrState = { link: '', qr: '', title: 'Survey QR Code' };

            document.querySelectorAll('.js-open-survey-qr').forEach((btn) => {
                btn.addEventListener('click', () => {
                    qrState.link = btn.dataset.link || '';
                    qrState.qr = btn.dataset.qr || '';
                    qrState.title = btn.dataset.title || 'Survey QR Code';

                    if (qrTitle) qrTitle.textContent = qrState.title;
                    if (qrImage) qrImage.src = qrState.qr;
                    if (qrLink) qrLink.textContent = qrState.link;

                    qrModal?.show();
                });
            });

            document.querySelector('.js-copy-survey-qr-link')?.addEventListener('click', async () => {
                if (!qrState.link) return;
                try {
                    await navigator.clipboard.writeText(qrState.link);
                } catch (error) {
                    window.prompt('Copy survey link:', qrState.link);
                }
            });

            document.querySelector('.js-share-survey-qr-link')?.addEventListener('click', async () => {
                if (!qrState.link) return;

                if (navigator.share) {
                    try {
                        await navigator.share({
                            title: qrState.title,
                            text: qrState.title,
                            url: qrState.link
                        });
                        return;
                    } catch (error) {
                        if (error?.name === 'AbortError') {
                            return;
                        }
                    }
                }

                try {
                    await navigator.clipboard.writeText(qrState.link);
                } catch (error) {
                    window.prompt('Copy survey link:', qrState.link);
                }
            });

            document.querySelector('.js-download-survey-qr')?.addEventListener('click', async () => {
                if (!qrState.qr) return;

                try {
                    const response = await fetch(qrState.qr);
                    const blob = await response.blob();
                    const objectUrl = URL.createObjectURL(blob);
                    const anchor = document.createElement('a');
                    anchor.href = objectUrl;
                    anchor.download = `${(qrState.title || 'survey-qr').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
                    document.body.appendChild(anchor);
                    anchor.click();
                    anchor.remove();
                    URL.revokeObjectURL(objectUrl);
                } catch (error) {
                    window.open(qrState.qr, '_blank', 'noopener');
                }
            });

            const defaultCandidates = {
                actual_value: ['actual_value', 'actual', 'value', 'result', 'score', 'calculated_value'],
                period_label: ['period_label', 'period', 'reporting_period', 'month', 'quarter', 'year'],
                period_type: ['period_type', 'type'],
                period_start: ['period_start', 'start_date', 'date'],
                period_end: ['period_end', 'end_date'],
                method: ['method', 'methodology'],
                notes: ['notes', 'comment', 'remarks']
            };

            const forms = document.querySelectorAll('.data-source-sync-form');
            forms.forEach((form) => {
                const sourceFileSelect = form.querySelector('.source-file-select');
                const uploadInput = form.querySelector('.upload-file-input');
                const uploadStatus = form.querySelector('.upload-columns-status');
                const rowModeSelect = form.querySelector('.row-mode-select');
                const mappingSelects = Array.from(form.querySelectorAll('.column-map-select'));
                if (mappingSelects.length === 0) {
                    return;
                }

                let columnsByFile = {};
                try {
                    columnsByFile = JSON.parse(form.dataset.columnMap || '{}');
                } catch (error) {
                    columnsByFile = {};
                }

                let savedMap = {};
                try {
                    savedMap = JSON.parse(form.dataset.savedMap || '{}');
                } catch (error) {
                    savedMap = {};
                }

                const savedRowMode = (form.dataset.savedRowMode || 'all_rows').trim();
                if (rowModeSelect && ['all_rows', 'latest_row_only'].includes(savedRowMode)) {
                    rowModeSelect.value = savedRowMode;
                }

                let uploadColumns = [];
                let firstHydration = true;

                const flattenColumns = () => {
                    const bucket = {};
                    Object.values(columnsByFile).forEach((columns) => {
                        if (!Array.isArray(columns)) {
                            return;
                        }
                        columns.forEach((column) => {
                            if (!column || !column.value) {
                                return;
                            }
                            bucket[column.value] = column;
                        });
                    });

                    return Object.values(bucket);
                };

                const resolveCurrentColumns = () => {
                    if (uploadColumns.length > 0) {
                        return uploadColumns;
                    }

                    if (sourceFileSelect && sourceFileSelect.value && Array.isArray(columnsByFile[sourceFileSelect.value])) {
                        return columnsByFile[sourceFileSelect.value];
                    }

                    return flattenColumns();
                };

                const autoSelectColumn = (field, columns) => {
                    const preferred = defaultCandidates[field] || [field];
                    const available = columns.map((column) => column.value);

                    for (const candidate of preferred) {
                        if (available.includes(candidate)) {
                            return candidate;
                        }
                    }

                    return '';
                };

                const refreshMappingOptions = () => {
                    const columns = resolveCurrentColumns();

                    mappingSelects.forEach((select) => {
                        const field = select.dataset.field || '';
                        const previous = select.value;
                        const savedValue = (savedMap[field] || '').trim();

                        while (select.options.length > 1) {
                            select.remove(1);
                        }

                        columns.forEach((column) => {
                            const option = document.createElement('option');
                            option.value = column.value;
                            option.textContent = column.label || column.value;
                            select.appendChild(option);
                        });

                        if (previous && columns.some((column) => column.value === previous)) {
                            select.value = previous;
                            return;
                        }

                        if (firstHydration && savedValue && columns.some((column) => column.value === savedValue)) {
                            select.value = savedValue;
                            return;
                        }

                        const auto = autoSelectColumn(field, columns);
                        if (auto !== '') {
                            select.value = auto;
                        }
                    });

                    firstHydration = false;
                };

                const previewUploadColumns = async (file) => {
                    if (!file) {
                        uploadColumns = [];
                        if (uploadStatus) {
                            uploadStatus.textContent = 'Upload is applied for this sync only.';
                        }
                        refreshMappingOptions();
                        return;
                    }

                    const extension = (file.name.split('.').pop() || '').toLowerCase();
                    if (!['csv', 'xlsx', 'xls'].includes(extension)) {
                        uploadColumns = [];
                        if (uploadStatus) {
                            uploadStatus.textContent = 'Unsupported file. Use CSV, XLSX, or XLS.';
                        }
                        refreshMappingOptions();
                        return;
                    }

                    const previewUrl = form.dataset.previewUrl || '';
                    if (previewUrl === '') {
                        return;
                    }

                    if (uploadStatus) {
                        uploadStatus.textContent = 'Reading upload columns...';
                    }

                    const formData = new FormData();
                    formData.append('upload_file', file);

                    const csrfToken = form.querySelector('input[name="_token"]')?.value || '';

                    try {
                        const response = await fetch(previewUrl, {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const payload = await response.json();
                        if (!response.ok) {
                            throw new Error(payload.message || 'Could not load uploaded file columns.');
                        }

                        uploadColumns = Array.isArray(payload.columns) ? payload.columns : [];
                        if (uploadStatus) {
                            uploadStatus.textContent = uploadColumns.length > 0 ?
                                `Loaded ${uploadColumns.length} column(s) from uploaded file.` :
                                'No columns found in uploaded file.';
                        }
                    } catch (error) {
                        uploadColumns = [];
                        if (uploadStatus) {
                            uploadStatus.textContent = error.message || 'Could not read uploaded file columns.';
                        }
                    }

                    refreshMappingOptions();
                };

                if (sourceFileSelect) {
                    sourceFileSelect.addEventListener('change', () => {
                        if (uploadInput && uploadInput.files && uploadInput.files.length > 0) {
                            return;
                        }
                        refreshMappingOptions();
                    });
                }

                if (uploadInput) {
                    uploadInput.addEventListener('change', () => {
                        const file = uploadInput.files && uploadInput.files[0] ? uploadInput.files[0] : null;
                        previewUploadColumns(file);
                    });
                }

                refreshMappingOptions();
            });
        });
    </script>
@endpush
