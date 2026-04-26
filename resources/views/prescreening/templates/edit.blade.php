@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Edit Prescreening Template</h4>
                <p class="text-muted mb-0">
                    Update sections and yes/no prescreening items.
                </p>
            </div>

            <a href="{{ route('prescreening.templates.show', $template) }}" class="btn btn-outline-secondary btn-sm">
                Back
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">
                <strong>Unable to update template.</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('prescreening.templates.update', $template) }}" id="prescreeningTemplateForm">
            @csrf
            @method('PUT')
            <input type="hidden" name="sections_payload" id="sectionsPayload">

            <div class="card shadow-sm mb-4">
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Template Name <span class="text-danger">*</span>
                        </label>
                        <input type="text" name="name" class="form-control"
                            value="{{ old('name', $template->name) }}" required>
                    </div>

                    <div class="col-md-6 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                @checked(old('is_active', $template->is_active))>
                            <label class="form-check-label">Active Template</label>
                        </div>
                    </div>

                    <div class="col-md-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="3" class="form-control">{{ old('description', $template->description) }}</textarea>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <h6 class="fw-semibold mb-1">Prescreening Sections</h6>
                        <small class="text-muted">Each item in this template is evaluated as <strong>Yes</strong> or <strong>No</strong>.</small>
                    </div>

                    <button type="button" class="btn btn-sm btn-primary" id="addSectionBtn">
                        <i class="feather-plus me-1"></i> Add Section
                    </button>
                </div>

                <div class="card-body">
                    <div id="sectionsBuilder" class="d-flex flex-column gap-3"></div>
                </div>
            </div>

            <div class="d-flex justify-content-end mt-4">
                <button class="btn btn-success">
                    <i class="feather-save me-1"></i> Update Template
                </button>
            </div>
        </form>
    </div>

    <script>
        const sectionsBuilder = document.getElementById('sectionsBuilder');
        const sectionsPayload = document.getElementById('sectionsPayload');
        const form = document.getElementById('prescreeningTemplateForm');

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function normalizeBoolean(value, fallback = false) {
            if (value === undefined || value === null || value === '') {
                return fallback;
            }

            if (typeof value === 'boolean') {
                return value;
            }

            return !['0', 'false', 'off'].includes(String(value).toLowerCase());
        }

        function buildItemRow(seed = {}) {
            const row = document.createElement('tr');
            row.className = 'prescreening-item-row';
            row.innerHTML = `
                <td>
                    <input type="text" class="form-control form-control-sm item-name"
                        placeholder="Item title"
                        value="${escapeHtml(seed.name ?? '')}">
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm item-description"
                        placeholder="Optional description"
                        value="${escapeHtml(seed.description ?? '')}">
                </td>
                <td class="text-center">
                    <span class="badge bg-light text-dark border">Yes / No</span>
                </td>
                <td class="text-center">
                    <input type="checkbox" class="form-check-input item-mandatory"
                        ${normalizeBoolean(seed.is_mandatory, false) ? 'checked' : ''}>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-item">
                        <i class="feather-trash"></i>
                    </button>
                </td>
            `;

            return row;
        }

        function addItem(sectionCard, seed = {}) {
            const tbody = sectionCard.querySelector('.section-items-body');
            tbody?.appendChild(buildItemRow(seed));
        }

        function buildSectionCard(seed = {}) {
            const card = document.createElement('div');
            card.className = 'card border prescreening-section-card';
            card.innerHTML = `
                <div class="card-header bg-white d-flex justify-content-between align-items-start gap-3">
                    <div class="flex-grow-1">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label class="form-label fw-semibold mb-1">Section Name</label>
                                <input type="text" class="form-control section-name"
                                    placeholder="e.g. Administrative Compliance"
                                    value="${escapeHtml(seed.name ?? '')}">
                            </div>
                            <div class="col-md-7">
                                <label class="form-label fw-semibold mb-1">Section Description</label>
                                <input type="text" class="form-control section-description"
                                    placeholder="Optional section guidance"
                                    value="${escapeHtml(seed.description ?? '')}">
                            </div>
                        </div>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-section">
                        Remove Section
                    </button>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-3">
                            <thead class="table-light">
                                <tr>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th width="120" class="text-center">Decision</th>
                                    <th width="110" class="text-center">Mandatory</th>
                                    <th width="70" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="section-items-body"></tbody>
                        </table>
                    </div>

                    <button type="button" class="btn btn-sm btn-outline-primary btn-add-item">
                        <i class="feather-plus me-1"></i> Add Item
                    </button>
                </div>
            `;

            sectionsBuilder.appendChild(card);

            const items = Array.isArray(seed.items) ? seed.items : [];
            if (items.length) {
                items.forEach(item => addItem(card, item));
            } else {
                addItem(card);
            }
        }

        function collectSections() {
            return Array.from(document.querySelectorAll('.prescreening-section-card'))
                .map(card => {
                    const items = Array.from(card.querySelectorAll('.prescreening-item-row'))
                        .map(row => ({
                            name: row.querySelector('.item-name')?.value?.trim() ?? '',
                            description: row.querySelector('.item-description')?.value?.trim() ?? '',
                            is_mandatory: !!row.querySelector('.item-mandatory')?.checked,
                        }))
                        .filter(item => item.name !== '' || item.description !== '');

                    return {
                        name: card.querySelector('.section-name')?.value?.trim() ?? '',
                        description: card.querySelector('.section-description')?.value?.trim() ?? '',
                        items,
                    };
                })
                .filter(section => section.name !== '' || section.description !== '' || section.items.length > 0);
        }

        document.addEventListener('click', event => {
            const addSectionBtn = event.target.closest('#addSectionBtn');
            if (addSectionBtn) {
                buildSectionCard();
                return;
            }

            const addItemBtn = event.target.closest('.btn-add-item');
            if (addItemBtn) {
                addItem(addItemBtn.closest('.prescreening-section-card'));
                return;
            }

            const removeItemBtn = event.target.closest('.btn-remove-item');
            if (removeItemBtn) {
                const sectionCard = removeItemBtn.closest('.prescreening-section-card');
                const rows = sectionCard.querySelectorAll('.prescreening-item-row');
                const row = removeItemBtn.closest('.prescreening-item-row');

                if (rows.length === 1) {
                    row.querySelectorAll('input').forEach(input => {
                        if (input.type === 'checkbox') {
                            input.checked = false;
                        } else {
                            input.value = '';
                        }
                    });
                } else {
                    row.remove();
                }
                return;
            }

            const removeSectionBtn = event.target.closest('.btn-remove-section');
            if (removeSectionBtn) {
                const cards = document.querySelectorAll('.prescreening-section-card');
                const card = removeSectionBtn.closest('.prescreening-section-card');

                if (cards.length === 1) {
                    card.remove();
                    buildSectionCard();
                } else {
                    card.remove();
                }
            }
        });

        form?.addEventListener('submit', function(event) {
            const sections = collectSections();
            if (!sections.length) {
                event.preventDefault();
                alert('Add at least one section before updating.');
                return;
            }

            if (sections.some(section => section.items.length === 0)) {
                event.preventDefault();
                alert('Each section must contain at least one item.');
                return;
            }

            sectionsPayload.value = JSON.stringify(sections);
        });

        (() => {
            const oldPayload = @json(old('sections_payload'));

            if (oldPayload && typeof oldPayload === 'string') {
                try {
                    const decoded = JSON.parse(oldPayload);
                    if (Array.isArray(decoded) && decoded.length) {
                        decoded.forEach(section => buildSectionCard(section));
                        return;
                    }
                } catch (error) {
                    // Ignore invalid old payload and fall back to DB data.
                }
            }

            const seededSections = @json(
                $template->sections->map(function ($section) {
                    return [
                        'name' => $section->name,
                        'description' => $section->description,
                        'items' => $section->criteria->map(function ($criterion) {
                            return [
                                'name' => $criterion->name,
                                'description' => $criterion->description,
                                'is_mandatory' => $criterion->is_mandatory,
                            ];
                        })->values(),
                    ];
                })->values()
            );

            if (Array.isArray(seededSections) && seededSections.length) {
                seededSections.forEach(section => buildSectionCard(section));
                return;
            }

            buildSectionCard();
        })();
    </script>
@endsection
