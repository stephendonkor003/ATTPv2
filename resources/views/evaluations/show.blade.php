@extends('layouts.app')

@section('content')
    <div class="nxl-container evaluation-builder">

        {{-- ================= HEADER ================= --}}
        <div class="page-header mb-4 d-flex justify-content-between align-items-start">
            <div>
                <h4 class="page-title mb-1">{{ $evaluation->name }}</h4>
                <small class="text-muted">
                    @if ($evaluation->type === 'services')
                        Define scoring structure. Section totals and final score are calculated automatically.
                    @else
                        Define compliance criteria (Yes / No with evaluator comments).
                    @endif
                </small>
            </div>

            <a href="{{ route('evals.cfg.index') }}" class="btn btn-outline-secondary btn-sm">
                ← Back to Evaluations
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success mb-3">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger mb-3">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger mb-3">
                <strong>Please fix the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- ================= TYPE BADGE ================= --}}
        <div class="alert alert-info mb-4">
            <strong>Evaluation Type:</strong>
            <span class="badge bg-{{ $evaluation->type === 'goods' ? 'warning' : 'primary' }}">
                {{ ucfirst($evaluation->type) }}
            </span>
        </div>
        @if ($evaluation->status !== 'draft')
            <div class="alert alert-warning mb-4">
                This evaluation is currently <strong>{{ ucfirst($evaluation->status) }}</strong> and cannot be modified.
            </div>
        @endif

        {{-- ================= TOTAL (SERVICES ONLY) ================= --}}
        @if ($evaluation->type === 'services')
            <div class="alert alert-primary d-flex justify-content-between align-items-center">
                <strong>Total Evaluation Score</strong>
                <span class="badge bg-dark fs-6" id="overall-total">0</span>
            </div>
        @endif

        {{-- ================= ADD SECTION ================= --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header fw-bold">Add Evaluation Section</div>
            <div class="card-body">
                <form method="POST" action="{{ route('evals.cfg.sec.add', $evaluation) }}">
                    @csrf
                    <div class="row g-2 align-items-end">
                        <div class="col-md-4">
                            <input name="name" class="form-control" placeholder="Section name" required>
                        </div>
                        <div class="col-md-6">
                            <input name="description" class="form-control" placeholder="Description">
                        </div>
                        <div class="col-md-2">
                            <button class="btn btn-primary w-100">Add Section</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @php
            $colors = ['primary', 'success', 'warning', 'info', 'danger', 'purple'];
        @endphp

        {{-- ================= SECTIONS ================= --}}
        @foreach ($evaluation->sections as $i => $sec)
            @php $color = $colors[$i % count($colors)]; @endphp

            <div class="card shadow-sm mb-4 section-card">

                {{-- HEADER --}}
                <div class="card-header bg-{{ $color }} text-white d-flex justify-content-between">
                    <div>
                        <strong>{{ $sec->name }}</strong><br>
                        <small>{{ $sec->description }}</small>
                    </div>

                    @if ($evaluation->type === 'services')
                        <span class="badge bg-light text-dark">
                            Section Total:
                            <span class="section-total">0</span>
                        </span>
                    @endif
                </div>

                {{-- BODY --}}
                <div class="card-body bg-soft-{{ $color }}">

                    {{-- ADD CRITERIA --}}
                    <form method="POST" action="{{ route('evals.cfg.crt.add', $sec) }}" class="row g-2 mb-3">
                        @csrf
                        <div class="col-md-5">
                            <input name="name" class="form-control" placeholder="Criteria name" required>
                        </div>
                        <div class="col-md-5">
                            <input name="description" class="form-control" placeholder="Description">
                        </div>

                        @if ($evaluation->type === 'services')
                            <div class="col-md-1">
                                <input name="max_score" type="number" min="1" class="form-control max-score-input"
                                    placeholder="Max" required>
                            </div>
                        @endif

                        <div class="col-md-{{ $evaluation->type === 'services' ? 1 : 2 }}">
                            <button class="btn btn-outline-{{ $color }} w-100">Add</button>
                        </div>
                    </form>

                    {{-- CRITERIA TABLE --}}
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Criteria</th>
                                <th>Description</th>
                                @if ($evaluation->type === 'services')
                                    <th class="text-end">Max</th>
                                @endif
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sec->criteria as $crt)
                                <tr data-update-url="{{ route('evals.cfg.crt.upd', $crt) }}">
                                    {{-- NAME --}}
                                    <td>
                                        <span class="view">{{ $crt->name }}</span>
                                        <input class="edit form-control form-control-sm d-none"
                                            value="{{ $crt->name }}">
                                    </td>

                                    {{-- DESCRIPTION --}}
                                    <td>
                                        <span class="view">{{ $crt->description }}</span>
                                        <input class="edit form-control form-control-sm d-none"
                                            value="{{ $crt->description }}">
                                    </td>

                                    {{-- MAX SCORE (SERVICES ONLY) --}}
                                    @if ($evaluation->type === 'services')
                                        <td class="text-end">
                                            <span class="view score">{{ $crt->max_score }}</span>
                                            <input type="number" min="1"
                                                class="edit form-control form-control-sm d-none"
                                                value="{{ $crt->max_score }}">
                                        </td>
                                    @endif

                                    {{-- ACTION --}}
                                    <td class="text-center">
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-edit">
                                            Edit
                                        </button>

                                        <button type="button" class="btn btn-sm btn-success btn-save d-none">
                                            Save
                                        </button>

                                        <form method="POST" action="{{ route('evals.cfg.crt.del', $crt) }}"
                                            class="d-inline" onsubmit="return confirm('Delete this criteria?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                </div>
            </div>
        @endforeach

    </div>

    {{-- ================= STYLES ================= --}}
    <style>
        .bg-soft-primary {
            background: #f0f6ff;
        }

        .bg-soft-success {
            background: #f1fbf5;
        }

        .bg-soft-warning {
            background: #fff8e6;
        }

        .bg-soft-danger {
            background: #fff0f0;
        }

        .bg-soft-info {
            background: #eef9fb;
        }

        .bg-soft-purple {
            background: #f6f1ff;
        }

        .bg-purple {
            background: #6f42c1 !important;
        }
    </style>

    {{-- ================= INLINE EDIT JS ================= --}}
    <script>
        document.querySelectorAll('.btn-edit').forEach(btn => {
            btn.addEventListener('click', () => {
                const row = btn.closest('tr');

                row.querySelectorAll('.view').forEach(el => el.classList.add('d-none'));
                row.querySelectorAll('.edit').forEach(el => el.classList.remove('d-none'));

                btn.classList.add('d-none');
                row.querySelector('.btn-save').classList.remove('d-none');
            });
        });

        document.querySelectorAll('.btn-save').forEach(btn => {
            btn.addEventListener('click', async () => {
                const row = btn.closest('tr');
                const inputs = row.querySelectorAll('.edit');

                const formData = new FormData();
                formData.append('name', inputs[0].value);
                formData.append('description', inputs[1].value);

                if (inputs[2]) {
                    formData.append('max_score', inputs[2].value);
                }

                formData.append('_method', 'PUT');
                formData.append('_token', '{{ csrf_token() }}');

                const response = await fetch(row.dataset.updateUrl, {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    alert('Update failed');
                    return;
                }

                // Update UI
                row.querySelectorAll('.view')[0].textContent = inputs[0].value;
                row.querySelectorAll('.view')[1].textContent = inputs[1].value;

                if (inputs[2]) {
                    row.querySelector('.score').textContent = inputs[2].value;
                }

                row.querySelectorAll('.view').forEach(el => el.classList.remove('d-none'));
                row.querySelectorAll('.edit').forEach(el => el.classList.add('d-none'));

                btn.classList.add('d-none');
                row.querySelector('.btn-edit').classList.remove('d-none');
            });
        });
    </script>

    @if ($evaluation->type === 'services')
        <script>
            function calculateTotals() {
                let overall = 0;

                document.querySelectorAll('.section-card').forEach(section => {
                    let sectionTotal = 0;

                    section.querySelectorAll('.score').forEach(span => {
                        sectionTotal += parseFloat(span.textContent) || 0;
                    });

                    section.querySelector('.section-total').textContent = sectionTotal.toFixed(2);
                    overall += sectionTotal;
                });

                document.getElementById('overall-total').textContent = overall.toFixed(2);
            }

            calculateTotals();
        </script>
    @endif
@endsection
