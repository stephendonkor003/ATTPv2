@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-activity text-primary me-2"></i>
                    System Audit Logs
                </h4>
                <p class="text-muted mb-0">Complete activity trail across the platform.</p>
            </div>
        </div>

        {{-- ================= AUDIT TABLE ================= --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="auditLogsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Date</th>
                            <th>User</th>
                            <th>Module</th>
                            <th>Action</th>
                            <th>Method</th>
                            <th>URL</th>
                            <th>IP</th>
                            <th>Country</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            @php
                                $actionColors = [
                                    'login' => 'success',
                                    'logout' => 'info',
                                    'login_failed' => 'danger',
                                    'request' => 'primary',
                                    'user_impersonation_started' => 'warning',
                                    'user_impersonation_stopped' => 'info',
                                    'user_impersonation_terminated' => 'danger',
                                ];
                                $methodColors = [
                                    'GET' => 'secondary',
                                    'POST' => 'success',
                                    'PUT' => 'warning',
                                    'PATCH' => 'warning',
                                    'DELETE' => 'danger',
                                ];
                                $impersonationContext = data_get($log->payload, '_impersonation');
                                $impersonator = $impersonationContext
                                    ? $impersonators->get((string) data_get($impersonationContext, 'administrator_id'))
                                    : null;
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div>{{ $log->created_at?->format('d M Y') }}</div>
                                    <small class="text-muted">{{ $log->created_at?->format('H:i:s') }}</small>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $log->user?->name ?? 'Guest' }}</div>
                                    <small class="text-muted">{{ $log->user?->email ?? '—' }}</small>
                                    @if ($impersonationContext)
                                        <div class="mt-1">
                                            <span class="badge bg-warning-subtle text-warning">ACTING AS USER</span>
                                        </div>
                                        <small class="d-block text-dark mt-1">
                                            Administrator: {{ $impersonator?->name ?? data_get($impersonationContext, 'administrator_id') }}
                                        </small>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-info-subtle text-info px-2 py-1">
                                        {{ strtoupper($log->module ?? 'system') }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-{{ $actionColors[$log->action] ?? 'secondary' }} px-2 py-1">
                                        {{ strtoupper($log->action ?? 'N/A') }}
                                    </span>
                                    @if ($log->action_message)
                                        <div class="small text-muted mt-1">{{ $log->action_message }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge bg-{{ $methodColors[$log->method] ?? 'secondary' }} px-2 py-1">
                                        {{ $log->method ?? '—' }}
                                    </span>
                                </td>
                                <td style="max-width: 280px;">
                                    <div class="text-truncate" title="{{ $log->url ?? '—' }}">
                                        {{ Str::limit($log->url ?? '—', 40) }}
                                    </div>
                                </td>
                                <td>
                                    <code class="small">{{ $log->ip_address ?? '—' }}</code>
                                </td>
                                <td>{{ $log->country ?? '—' }}</td>
                                <td class="text-center">
                                    @if($log->status_code)
                                        @php
                                            $statusClass = match(true) {
                                                $log->status_code >= 200 && $log->status_code < 300 => 'success',
                                                $log->status_code >= 300 && $log->status_code < 400 => 'info',
                                                $log->status_code >= 400 && $log->status_code < 500 => 'warning',
                                                $log->status_code >= 500 => 'danger',
                                                default => 'secondary',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $statusClass }} px-2 py-1">
                                            {{ $log->status_code }}
                                        </span>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>
    </div>
@endsection
