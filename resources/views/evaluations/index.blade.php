@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-clipboard text-primary me-2"></i>
                    Evaluation Configuration
                </h4>
                <p class="text-muted mb-0">
                    Manage evaluation structures used for procurement assessments.
                </p>
            </div>

            <a href="{{ route('evals.cfg.new') }}" class="btn btn-primary btn-sm">
                <i class="feather-plus me-1"></i> New Evaluation
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif

        {{-- ================= INFO ================= --}}
        <div class="alert alert-info mb-4">
            <strong>Lifecycle:</strong>
            <span class="ms-2">Draft → Active → Close</span>
            <div class="small mt-1 text-muted">
                Create and configure evaluations while in Draft. Activate before assigning to procurements.
            </div>
        </div>

        {{-- ================= TABLE ================= --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="evaluationTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Evaluation</th>
                            <th class="text-center">Type</th>
                            <th class="text-center">Sections</th>
                            <th class="text-center">Status</th>
                            <th width="200" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($evaluations as $eval)
                            @php
                                $statusColors = [
                                    'draft' => 'secondary',
                                    'active' => 'success',
                                    'close' => 'danger',
                                ];
                                $statusColor = $statusColors[$eval->status] ?? 'secondary';

                                $typeColor = $eval->type === 'goods' ? 'warning' : 'primary';
                                $typeLabel = $eval->type === 'goods' ? 'Goods' : 'Services';
                            @endphp

                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $eval->name }}</div>
                                    <small class="text-muted">
                                        {{ \Illuminate\Support\Str::limit($eval->description ?? '', 60) }}
                                    </small>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-{{ $typeColor }} px-3 py-1">
                                        {{ $typeLabel }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-dark px-3 py-1">
                                        {{ $eval->sections_count }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-{{ $statusColor }} px-3 py-1">
                                        {{ ucfirst($eval->status) }}
                                    </span>
                                </td>

                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('evals.cfg.show', $eval) }}" class="btn btn-sm btn-outline-primary">
                                            <i class="feather-settings me-1"></i> Configure
                                        </a>

                                        @if ($eval->status === 'draft')
                                            <form method="POST" action="{{ route('evals.cfg.update', $eval) }}" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="active">
                                                <button class="btn btn-sm btn-outline-success"
                                                    onclick="return confirm('Activate this evaluation?')">
                                                    Activate
                                                </button>
                                            </form>
                                        @elseif ($eval->status === 'active')
                                            <form method="POST" action="{{ route('evals.cfg.update', $eval) }}" class="d-inline">
                                                @csrf
                                                @method('PUT')
                                                <input type="hidden" name="status" value="close">
                                                <button class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Closing an evaluation is final. Continue?')">
                                                    Close
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>

    </div>
@endsection
