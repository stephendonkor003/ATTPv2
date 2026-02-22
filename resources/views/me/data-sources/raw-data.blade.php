@extends('layouts.app')
@section('title', 'Data Source Raw Data')

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

        .ds-card {
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
            background: #fff;
        }

        .ds-stat {
            border: 1px solid #dbe4ef;
            border-radius: 10px;
            background: #f8fafc;
            padding: 0.7rem 0.8rem;
            height: 100%;
        }

        .ds-stat .label {
            font-size: 0.73rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            font-weight: 600;
        }

        .ds-stat .value {
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
            margin-top: 0.1rem;
            word-break: break-word;
        }

        .ds-table th {
            background: #e2e8f0;
            color: #0f172a;
            font-size: 0.73rem;
            text-transform: uppercase;
            letter-spacing: 0.03em;
            white-space: nowrap;
        }

        .ds-table td {
            font-size: 0.82rem;
            vertical-align: top;
        }

        .ds-status-pill {
            border-radius: 999px;
            font-size: 0.68rem;
            font-weight: 600;
            padding: 0.2rem 0.55rem;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.2rem;
        }

        .ds-status-pill.success {
            background: #dcfce7;
            color: #166534;
            border-color: #86efac;
        }

        .ds-status-pill.warning {
            background: #fef3c7;
            color: #92400e;
            border-color: #fcd34d;
        }

        .ds-status-pill.danger {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fca5a5;
        }

        .ds-status-pill.secondary {
            background: #e2e8f0;
            color: #334155;
            border-color: #cbd5e1;
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
                        Raw synchronized/collected records for indicator: <strong>{{ $indicator->name }}</strong>
                    </p>
                    <div>
                        <span class="ds-chip"><i class="feather-layers"></i> Raw Data Viewer</span>
                        <span class="ds-chip"><i class="feather-clock"></i> Sync History</span>
                        <span class="ds-chip"><i class="feather-filter"></i> Searchable Records</span>
                    </div>
                </div>
                <a href="{{ route('budget.me.data-sources.index', ['q' => request('q')]) }}" class="btn btn-light btn-sm fw-semibold px-3">
                    <i class="feather-arrow-left me-1"></i> Back To Controller
                </a>
            </div>
        </div>

        <div class="row g-2 mb-3">
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat">
                    <div class="label">Owner</div>
                    <div class="value">{{ $summary['owner'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat">
                    <div class="label">Source Type</div>
                    <div class="value">{{ $summary['source_type'] }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat">
                    <div class="label">Total Rows</div>
                    <div class="value">{{ number_format((int) ($summary['total_rows'] ?? 0)) }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat">
                    <div class="label">Latest Value</div>
                    <div class="value">{{ $summary['latest_value'] !== null ? $summary['latest_value'] : '—' }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat">
                    <div class="label">Latest Collected</div>
                    <div class="value">{{ $summary['latest_collected_at'] ? $summary['latest_collected_at']->format('Y-m-d H:i:s') : '—' }}</div>
                </div>
            </div>
            <div class="col-6 col-md-4 col-xl-2">
                <div class="ds-stat">
                    <div class="label">Collected By</div>
                    <div class="value">{{ $summary['latest_collected_by'] ?: '—' }}</div>
                </div>
            </div>
        </div>

        <div class="card ds-card mb-3">
            <div class="card-body">
                <form method="GET" action="{{ route('budget.me.data-sources.raw-data', $indicator) }}" class="row g-2 align-items-end">
                    <div class="col-lg-10">
                        <label for="rawDataSearch" class="form-label mb-1 fw-semibold">Search Raw Data</label>
                        <input type="text" id="rawDataSearch" name="q" value="{{ $search }}"
                            class="form-control form-control-sm"
                            placeholder="Search by period, value, method, notes, or data source">
                    </div>
                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="feather-search me-1"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card ds-card mb-3">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="mb-0 fw-semibold">Raw Records</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 ds-table">
                    <thead>
                        <tr>
                            <th>Period Label</th>
                            <th>Period Type</th>
                            <th>Period Start</th>
                            <th>Period End</th>
                            <th>Actual Value</th>
                            <th>Unit</th>
                            <th>Method</th>
                            <th>Notes</th>
                            <th>Data Source</th>
                            <th>Collected At</th>
                            <th>Collected By</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($results as $result)
                            <tr>
                                <td>{{ $result->period_label ?: '—' }}</td>
                                <td>{{ $result->period_type ?: '—' }}</td>
                                <td>{{ $result->period_start ? $result->period_start->format('Y-m-d') : '—' }}</td>
                                <td>{{ $result->period_end ? $result->period_end->format('Y-m-d') : '—' }}</td>
                                <td>{{ $result->actual_value ?? '—' }}</td>
                                <td>
                                    @if ($result->unit)
                                        {{ $result->unit->name }}{{ $result->unit->symbol ? ' (' . $result->unit->symbol . ')' : '' }}
                                    @else
                                        —
                                    @endif
                                </td>
                                <td>{{ $result->method ?: '—' }}</td>
                                <td class="text-break" style="max-width: 320px;">{{ $result->notes ?: '—' }}</td>
                                <td class="text-break" style="max-width: 280px;">{{ $result->data_source ?: '—' }}</td>
                                <td>{{ $result->collected_at ? $result->collected_at->format('Y-m-d H:i:s') : '—' }}</td>
                                <td>{{ $result->collectedByUser?->name ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="text-center text-muted py-4">No raw records found for this indicator.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($results->hasPages())
                <div class="card-footer border-0 bg-white">
                    {{ $results->links() }}
                </div>
            @endif
        </div>

        <div class="card ds-card">
            <div class="card-header bg-white border-0 pb-0">
                <h6 class="mb-0 fw-semibold">Recent Sync History (Last 20)</h6>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-bordered align-middle mb-0 ds-table">
                    <thead>
                        <tr>
                            <th>Synced At</th>
                            <th>Status</th>
                            <th>Rows</th>
                            <th>Message</th>
                            <th>Configuration</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($syncLogs as $log)
                            @php
                                $status = strtolower((string) $log->status);
                                $statusClass = match ($status) {
                                    'success' => 'success',
                                    'partial' => 'warning',
                                    'failed' => 'danger',
                                    default => 'secondary',
                                };
                                $meta = is_array($log->meta) ? $log->meta : [];
                                $rowMode = $meta['row_mode'] ?? null;
                                $selectedFile = $meta['selected_source_file'] ?? null;
                            @endphp
                            <tr>
                                <td>{{ $log->synced_at ? $log->synced_at->format('Y-m-d H:i:s') : '—' }}</td>
                                <td>
                                    <span class="ds-status-pill {{ $statusClass }}">{{ ucfirst($status ?: 'unknown') }}</span>
                                </td>
                                <td>{{ (int) $log->synced_rows }}</td>
                                <td class="text-break" style="max-width: 420px;">{{ $log->message ?: '—' }}</td>
                                <td>
                                    <div class="small">
                                        <div><strong>Source:</strong> {{ $log->source_type ?: '—' }}</div>
                                        @if ($rowMode)
                                            <div><strong>Row Mode:</strong> {{ str_replace('_', ' ', $rowMode) }}</div>
                                        @endif
                                        @if ($selectedFile)
                                            <div class="text-break"><strong>File:</strong> {{ $selectedFile }}</div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No sync history found for this indicator.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

