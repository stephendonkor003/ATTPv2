@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        <div class="page-header mb-4">
            <h4 class="fw-bold mb-1">
                <i class="feather-user-plus text-primary me-2"></i>
                Evaluator Assignment
            </h4>
            <p class="text-muted mb-0">
                Procurement: <strong>{{ $procurement->title }}</strong>
            </p>
        </div>

        {{-- ASSIGN FORM --}}
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <form method="POST" action="{{ route('eval.assign.store') }}">
                    @csrf

                    <input type="hidden" name="procurement_id" value="{{ $procurement->id }}">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Evaluation</label>
                            <select name="evaluation_id" class="form-select" required>
                                <option value="">Select evaluation</option>
                                @foreach ($evaluations as $eval)
                                    <option value="{{ $eval->id }}">
                                        {{ $eval->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if ($evaluations->isEmpty())
                                <small class="text-danger">
                                    No portfolio-owned evaluation template is active for this procurement.
                                </small>
                            @endif
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Evaluator</label>
                            <select name="user_id" class="form-select" required>
                                <option value="">Select evaluator</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}">
                                        {{ $user->name }} ({{ $user->email }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-4 align-self-end">
                            <button class="btn btn-primary w-100">
                                <i class="feather-user-plus me-1"></i> Assign Evaluator
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ASSIGNED LIST --}}
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <x-data-table id="assignmentsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Evaluator</th>
                            <th>Evaluation</th>
                            <th class="text-center">Status</th>
                            <th>Assigned At</th>
                            <th width="260" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assignments as $a)
                            @php
                                $statusColors = [
                                    'assigned' => 'secondary',
                                    'submitted' => 'success',
                                    'rework' => 'warning',
                                ];
                            @endphp
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-medium">{{ $a->evaluator->name }}</div>
                                    <small class="text-muted">{{ $a->evaluator->email }}</small>
                                </td>
                                <td>{{ $a->evaluation->name }}</td>
                                <td class="text-center">
                                    <span class="badge bg-{{ $statusColors[$a->status] ?? 'secondary' }} px-3 py-1">
                                        {{ ucfirst($a->status) }}
                                    </span>
                                </td>
                                <td>{{ $a->assigned_at?->format('d M Y') }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('eval.assign.applicants', $a->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="feather-users me-1"></i> Applicants
                                        </a>

                                        @if ($a->status === 'submitted')
                                            <a href="{{ route('my.eval.compare', $a->id) }}"
                                                class="btn btn-sm btn-outline-success">
                                                <i class="feather-bar-chart-2 me-1"></i> Compare
                                            </a>
                                        @endif

                                        @if ($a->status !== 'submitted')
                                            <form method="POST" action="{{ route('eval.assign.destroy', $a) }}"
                                                class="d-inline"
                                                onsubmit="return confirm('Remove evaluator?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">
                                                    <i class="feather-trash-2"></i>
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
