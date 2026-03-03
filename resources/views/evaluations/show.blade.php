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
                    <form method="POST" action="{{ route('evals.cfg.crt.add', $sec) }}"
                        class="criteria-bulk-form mb-3"
                        data-section-id="{{ $sec->id }}"
                        data-services="{{ $evaluation->type === 'services' ? 1 : 0 }}"
                        data-color="{{ $color }}">
                        @csrf
                        <input type="hidden" name="criteria_payload" class="criteria-payload">

                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-2">
                                <thead class="table-light">
                                    <tr>
                                        <th>Sub-Criteria</th>
                                        <th>Description</th>
                                        @if ($evaluation->type === 'services')
                                            <th width="120">Max Score</th>
                                        @endif
                                        <th width="80" class="text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody class="criteria-draft-body"></tbody>
                            </table>
                        </div>

                        <div class="d-flex gap-2 align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-{{ $color }} btn-add-criteria-row">
                                Add Row
                            </button>
                            <button type="submit" class="btn btn-sm btn-{{ $color }}">
                                Save Added Criteria
                            </button>
                            <small class="text-muted">
                                Add as many rows as needed, then save once.
                            </small>
                        </div>

                        <div class="criteria-feedback small mt-2"></div>
                    </form>

                    {{-- CRITERIA TABLE --}}
                    <table class="table table-sm table-bordered align-middle criteria-list-table"
                        data-section-id="{{ $sec->id }}"
                        data-services="{{ $evaluation->type === 'services' ? 1 : 0 }}">
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
                        <tbody class="criteria-list-body">
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
        const csrfToken = '{{ csrf_token() }}';

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function extractError(payload) {
            if (!payload) {
                return null;
            }

            if (payload.message) {
                return payload.message;
            }

            if (payload.errors && typeof payload.errors === 'object') {
                const firstKey = Object.keys(payload.errors)[0];
                if (firstKey && Array.isArray(payload.errors[firstKey]) && payload.errors[firstKey].length) {
                    return payload.errors[firstKey][0];
                }
            }

            return null;
        }

        function setCriteriaFeedback(form, message, type = 'success') {
            const feedback = form.querySelector('.criteria-feedback');
            if (!feedback) {
                return;
            }

            feedback.textContent = message;
            feedback.className = `criteria-feedback small mt-2 text-${type === 'success' ? 'success' : 'danger'}`;
        }

        function buildDraftRow(form, seed = {}) {
            const isServices = form.dataset.services === '1';
            const tr = document.createElement('tr');
            tr.className = 'criteria-draft-row';

            tr.innerHTML = `
                <td>
                    <input type="text" class="form-control form-control-sm draft-name"
                        placeholder="e.g. Methodology quality"
                        value="${escapeHtml(seed.name ?? '')}">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm draft-description"
                        placeholder="Optional description"
                        value="${escapeHtml(seed.description ?? '')}">
                </td>
                ${isServices ? `
                <td>
                    <input type="number" min="1" step="0.01"
                        class="form-control form-control-sm draft-max-score"
                        placeholder="Max"
                        value="${escapeHtml(seed.max_score ?? '')}">
                </td>` : ''}
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-draft-row">Remove</button>
                </td>
            `;

            form.querySelector('.criteria-draft-body')?.appendChild(tr);
        }

        function initializeBulkCriteriaForms() {
            document.querySelectorAll('.criteria-bulk-form').forEach(form => {
                if (!form.querySelector('.criteria-draft-row')) {
                    buildDraftRow(form);
                }
            });
        }

        function collectDraftRows(form) {
            const isServices = form.dataset.services === '1';

            return Array.from(form.querySelectorAll('.criteria-draft-row'))
                .map(row => {
                    const name = row.querySelector('.draft-name')?.value?.trim() ?? '';
                    const description = row.querySelector('.draft-description')?.value?.trim() ?? '';
                    const rawMax = row.querySelector('.draft-max-score')?.value ?? '';
                    const maxScore = isServices && rawMax !== '' ? rawMax : null;

                    return {
                        name,
                        description,
                        max_score: maxScore,
                    };
                })
                .filter(item => item.name !== '' || item.description !== '' || item.max_score !== null);
        }

        function appendCriteriaRow(sectionId, criterion, isServices) {
            const body = document.querySelector(
                `.criteria-list-table[data-section-id="${sectionId}"] .criteria-list-body`
            );
            if (!body) {
                return;
            }

            const tr = document.createElement('tr');
            tr.setAttribute('data-update-url', criterion.update_url);

            tr.innerHTML = `
                <td>
                    <span class="view">${escapeHtml(criterion.name)}</span>
                    <input class="edit form-control form-control-sm d-none" value="${escapeHtml(criterion.name)}">
                </td>
                <td>
                    <span class="view">${escapeHtml(criterion.description ?? '')}</span>
                    <input class="edit form-control form-control-sm d-none" value="${escapeHtml(criterion.description ?? '')}">
                </td>
                ${isServices ? `
                    <td class="text-end">
                        <span class="view score">${escapeHtml(criterion.max_score ?? '')}</span>
                        <input type="number" min="1" class="edit form-control form-control-sm d-none"
                            value="${escapeHtml(criterion.max_score ?? '')}">
                    </td>` : ''}
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-secondary btn-edit">Edit</button>
                    <button type="button" class="btn btn-sm btn-success btn-save d-none">Save</button>
                    <form method="POST" action="${escapeHtml(criterion.delete_url)}"
                        class="d-inline" onsubmit="return confirm('Delete this criteria?')">
                        <input type="hidden" name="_token" value="${escapeHtml(csrfToken)}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </td>
            `;

            body.appendChild(tr);
        }

        document.addEventListener('click', async event => {
            const addRowBtn = event.target.closest('.btn-add-criteria-row');
            if (addRowBtn) {
                buildDraftRow(addRowBtn.closest('.criteria-bulk-form'));
                return;
            }

            const removeDraftBtn = event.target.closest('.btn-remove-draft-row');
            if (removeDraftBtn) {
                const form = removeDraftBtn.closest('.criteria-bulk-form');
                const row = removeDraftBtn.closest('.criteria-draft-row');
                const rows = form.querySelectorAll('.criteria-draft-row');

                if (rows.length === 1) {
                    row.querySelectorAll('input').forEach(input => input.value = '');
                } else {
                    row.remove();
                }
                return;
            }

            const editBtn = event.target.closest('.btn-edit');
            if (editBtn) {
                const row = editBtn.closest('tr');
                row.querySelectorAll('.view').forEach(el => el.classList.add('d-none'));
                row.querySelectorAll('.edit').forEach(el => el.classList.remove('d-none'));
                editBtn.classList.add('d-none');
                row.querySelector('.btn-save')?.classList.remove('d-none');
                return;
            }

            const saveBtn = event.target.closest('.btn-save');
            if (saveBtn) {
                const row = saveBtn.closest('tr');
                const inputs = row.querySelectorAll('.edit');
                const maxInput = inputs[2];

                const formData = new FormData();
                formData.append('name', inputs[0].value);
                formData.append('description', inputs[1].value);
                if (maxInput) {
                    formData.append('max_score', maxInput.value);
                }
                formData.append('_method', 'PUT');
                formData.append('_token', csrfToken);

                try {
                    const response = await fetch(row.dataset.updateUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: formData
                    });
                    const payload = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(extractError(payload) || 'Update failed.');
                    }

                    row.querySelectorAll('.view')[0].textContent = inputs[0].value;
                    row.querySelectorAll('.view')[1].textContent = inputs[1].value;
                    if (maxInput) {
                        row.querySelector('.score').textContent = maxInput.value;
                    }

                    row.querySelectorAll('.view').forEach(el => el.classList.remove('d-none'));
                    row.querySelectorAll('.edit').forEach(el => el.classList.add('d-none'));
                    saveBtn.classList.add('d-none');
                    row.querySelector('.btn-edit')?.classList.remove('d-none');

                    if (typeof calculateTotals === 'function') {
                        calculateTotals();
                    }
                } catch (error) {
                    alert(error.message || 'Update failed.');
                }
            }
        });

        document.addEventListener('submit', async event => {
            const form = event.target.closest('.criteria-bulk-form');
            if (!form) {
                return;
            }

            event.preventDefault();

            const sectionId = form.dataset.sectionId;
            const isServices = form.dataset.services === '1';
            const rows = collectDraftRows(form);

            if (!rows.length) {
                setCriteriaFeedback(form, 'Add at least one sub-criteria row before saving.', 'danger');
                return;
            }

            if (isServices && rows.some(item => item.max_score === null || Number(item.max_score) <= 0)) {
                setCriteriaFeedback(form, 'Each services sub-criteria row must include a valid max score.', 'danger');
                return;
            }

            const payloadInput = form.querySelector('.criteria-payload');
            payloadInput.value = JSON.stringify(rows);

            const requestData = new FormData();
            requestData.append('_token', form.querySelector('input[name="_token"]').value);
            requestData.append('criteria_payload', payloadInput.value);

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: requestData
                });
                const payload = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(extractError(payload) || 'Unable to save criteria.');
                }

                const created = Array.isArray(payload.criteria) ? payload.criteria : [];
                created.forEach(criterion => appendCriteriaRow(sectionId, criterion, isServices));

                form.querySelector('.criteria-draft-body').innerHTML = '';
                buildDraftRow(form);

                setCriteriaFeedback(form, payload.message || 'Criteria added successfully.', 'success');

                if (typeof calculateTotals === 'function') {
                    calculateTotals();
                }
            } catch (error) {
                setCriteriaFeedback(form, error.message || 'Unable to save criteria.', 'danger');
            }
        });

        initializeBulkCriteriaForms();
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
