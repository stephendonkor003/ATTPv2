@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ===================== PAGE HEADER ===================== --}}
        <div class="page-header d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Program Funding</h4>
                <p class="text-muted mb-0">
                    Funding allocations linked to approved programs
                </p>
            </div>
            @can('finance.program_funding.create')
                <a href="{{ route('finance.program-funding.create') }}" class="btn btn-primary">
                    <i class="feather-plus-circle me-1"></i>
                    New Program Funding
                </a>
            @endcan
        </div>

        {{-- ===================== FLASH MESSAGES ===================== --}}
        @if (session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif

        @if (isset($debug))
            <div class="alert alert-warning mt-3">
                <div class="fw-semibold mb-1">Debug: Governance Scope</div>
                <div class="small">
                    User: {{ $debug['user_name'] ?? '—' }} (ID: {{ $debug['user_id'] ?? '—' }}) |
                    Node ID: {{ $debug['user_node_id'] ?? '—' }} |
                    Admin: {{ $debug['is_admin'] ? 'Yes' : 'No' }} |
                    Visible Nodes: {{ is_array($debug['visible_node_ids']) ? implode(', ', $debug['visible_node_ids']) : 'ALL' }}
                </div>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        {{-- ===================== PROGRAM FUNDING TABLE ===================== --}}
        <div class="card shadow-sm mt-3">
            <div class="card-body">
                <table id="fundingTable" class="table table-striped table-hover {{ $fundings->count() > 0 ? 'data-table' : '' }}" style="width: 100%;">
                    <thead>
                        <tr>
                            <th style="width: 50px;" class="text-center">#</th>
                            <th>Program</th>
                            <th>Funder</th>
                            <th>Governance</th>
                            <th style="width: 150px;" class="text-end">Amount</th>
                            <th style="width: 100px;" class="text-center">Status</th>
                            <th style="width: 120px;">Created On</th>
                            <th style="width: 140px;" class="text-center no-sort no-export">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($fundings as $f)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td><strong>{{ $f->program_name ?? ($f->program->name ?? '—') }}</strong></td>
                                <td>{{ $f->funder->name ?? '—' }}</td>
                                <td>
                                    <div class="fw-semibold">{{ $f->governanceNode->name ?? '-' }}</div>
                                    <div class="text-muted small"><i class="feather-tag me-1"></i>{{ $f->governanceNode->level->name ?? '' }}</div>
                                </td>
                                <td class="text-end">
                                    <strong>{{ $f->program->currency ?? '' }} {{ number_format($f->approved_amount ?? 0, 2) }}</strong>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $f->status === 'approved' ? 'bg-success' : ($f->status === 'pending' ? 'bg-warning text-dark' : 'bg-secondary') }}">
                                        {{ ucfirst($f->status) }}
                                    </span>
                                </td>
                                <td>{{ optional($f->created_at)->format('d M Y') }}</td>
                                <td class="text-center no-export">
                                    <a href="{{ route('finance.program-funding.show', $f->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="feather-eye"></i>
                                    </a>
                                    @can('finance.program_funding.delete')
                                        <form method="POST" action="{{ route('finance.program-funding.destroy', $f) }}"
                                            class="d-inline"
                                            onsubmit="return confirm('Delete this program funding record? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">No program funding records found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
