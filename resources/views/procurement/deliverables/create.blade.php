@extends('layouts.app')

@push('styles')
<style>
    .dlv-builder {
        --dlv-border: #dbe3ef;
        --dlv-soft:   #f6f8fb;
        --dlv-ink:    #172033;
        --dlv-muted:  #64748b;
        --dlv-blue:   #2563eb;
        --dlv-accent: #0f766e;
    }

    .dlv-builder .row-card {
        border: 1.5px solid var(--dlv-border);
        border-radius: 10px;
        background: #fff;
        transition: border-color .2s;
        overflow: hidden;
    }
    .dlv-builder .row-card:focus-within {
        border-color: var(--dlv-blue);
    }

    .dlv-builder .row-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 16px;
        background: var(--dlv-soft);
        border-bottom: 1px solid var(--dlv-border);
        cursor: default;
    }

    .dlv-builder .row-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: var(--dlv-blue);
        color: #fff;
        font-size: .75rem;
        font-weight: 700;
        flex-shrink: 0;
    }

    .dlv-builder .row-title-preview {
        flex: 1;
        font-weight: 600;
        color: var(--dlv-ink);
        font-size: .9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .dlv-builder .freq-badge {
        font-size: .72rem;
        padding: 3px 8px;
        border-radius: 20px;
        font-weight: 600;
        white-space: nowrap;
    }

    .dlv-builder .row-body {
        padding: 18px 16px;
    }

    .dlv-builder .remove-btn {
        border: none;
        background: none;
        color: #94a3b8;
        padding: 4px 6px;
        border-radius: 6px;
        line-height: 1;
        transition: color .15s, background .15s;
        flex-shrink: 0;
    }
    .dlv-builder .remove-btn:hover:not(:disabled) {
        color: #dc2626;
        background: #fee2e2;
    }
    .dlv-builder .remove-btn:disabled {
        opacity: .3;
        cursor: not-allowed;
    }

    .dlv-builder .add-btn {
        border: 2px dashed var(--dlv-border);
        border-radius: 10px;
        background: transparent;
        color: var(--dlv-muted);
        padding: 14px;
        width: 100%;
        font-weight: 600;
        transition: border-color .15s, color .15s, background .15s;
    }
    .dlv-builder .add-btn:hover {
        border-color: var(--dlv-blue);
        color: var(--dlv-blue);
        background: #eff6ff;
    }

    .freq-one_time  { background: #f1f5f9; color: #475569; }
    .freq-daily     { background: #dbeafe; color: #1d4ed8; }
    .freq-weekly    { background: #ede9fe; color: #6d28d9; }
    .freq-monthly   { background: #d1fae5; color: #065f46; }
    .freq-quarterly { background: #fef3c7; color: #92400e; }
    .freq-yearly    { background: #fee2e2; color: #991b1b; }
</style>
@endpush

@section('content')
<div class="nxl-container dlv-builder">

    <div class="page-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Create Deliverables</h4>
            <p class="text-muted mb-0">Add one or more deliverables or milestones for an awarded procurement.</p>
        </div>
        <a href="{{ route('procurement.deliverables.index') }}" class="btn btn-light btn-sm">
            <i class="feather-arrow-left me-1"></i> Back
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger mb-4">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('procurement.deliverables.store') }}" id="dlvForm">
        @csrf

        {{-- Procurement & Vendor (shared for all rows) --}}
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h6 class="fw-bold mb-0">Procurement &amp; Vendor</h6>
                <p class="text-muted small mb-0 mt-1">Applies to all deliverables below.</p>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Procurement <span class="text-danger">*</span>
                        </label>
                        <select name="procurement_id"
                                class="form-control @error('procurement_id') is-invalid @enderror"
                                required>
                            <option value="">— Select a Procurement —</option>
                            @foreach ($procurements as $p)
                                <option value="{{ $p->id }}"
                                    {{ old('procurement_id', $selectedProcurementId) == $p->id ? 'selected' : '' }}>
                                    {{ $p->reference_no ?? 'N/A' }} — {{ $p->title }}
                                </option>
                            @endforeach
                        </select>
                        @error('procurement_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Vendor / Contractor</label>
                        <select name="vendor_id"
                                class="form-control @error('vendor_id') is-invalid @enderror">
                            <option value="">— Optional —</option>
                            @foreach ($vendors as $v)
                                <option value="{{ $v->id }}"
                                    {{ old('vendor_id') == $v->id ? 'selected' : '' }}>
                                    {{ $v->name }} — {{ $v->email }}
                                </option>
                            @endforeach
                        </select>
                        @error('vendor_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- Dynamic deliverable rows --}}
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h6 class="fw-bold mb-0">Deliverables &amp; Milestones</h6>
            <span id="rowCountBadge" class="badge bg-secondary">1 item</span>
        </div>

        <div id="deliverablesList" class="d-flex flex-column gap-3 mb-3"></div>

        <button type="button" id="addRowBtn" class="add-btn mb-4">
            <i class="feather-plus-circle me-2"></i> Add Another Deliverable
        </button>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('procurement.deliverables.index') }}" class="btn btn-light">Cancel</a>
            <button type="submit" class="btn btn-primary" id="submitBtn">
                <i class="feather-save me-1"></i> Save Deliverables
            </button>
        </div>
    </form>
</div>

{{-- Row template (hidden, cloned by JS) --}}
<template id="rowTemplate">
    <div class="row-card" data-row-index="__IDX__">
        <div class="row-header">
            <span class="row-badge row-num">1</span>
            <span class="row-title-preview text-muted fst-italic">Untitled deliverable</span>
            <span class="freq-badge freq-one_time">One-time</span>
            <button type="button" class="remove-btn ms-auto" title="Remove this row">
                <i class="feather-x" style="font-size:1rem"></i>
            </button>
        </div>
        <div class="row-body">
            <div class="row g-3">

                {{-- Title --}}
                <div class="col-md-5">
                    <label class="form-label fw-semibold">
                        Title <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           name="deliverables[__IDX__][title]"
                           class="form-control dlv-title"
                           placeholder="e.g. Inception Report, Q1 Milestone…"
                           maxlength="255" required>
                </div>

                {{-- Type --}}
                <div class="col-md-2">
                    <label class="form-label fw-semibold">
                        Type <span class="text-danger">*</span>
                    </label>
                    <select name="deliverables[__IDX__][type]" class="form-control" required>
                        <option value="deliverable">Deliverable</option>
                        <option value="milestone">Milestone</option>
                    </select>
                </div>

                {{-- Frequency --}}
                <div class="col-md-3">
                    <label class="form-label fw-semibold">
                        Schedule / Frequency <span class="text-danger">*</span>
                    </label>
                    <select name="deliverables[__IDX__][frequency]" class="form-control dlv-frequency" required>
                        <option value="one_time">One-time</option>
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="yearly">Yearly</option>
                    </select>
                </div>

                {{-- Sequence --}}
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Seq #</label>
                    <input type="number"
                           name="deliverables[__IDX__][sequence]"
                           class="form-control dlv-sequence"
                           min="1" value="1">
                </div>

                {{-- Description --}}
                <div class="col-12">
                    <label class="form-label fw-semibold">Description</label>
                    <textarea name="deliverables[__IDX__][description]"
                              class="form-control"
                              rows="2"
                              maxlength="3000"
                              placeholder="What must be delivered, acceptance criteria, expected output…"></textarea>
                </div>

                {{-- Timeline --}}
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Start Date</label>
                    <input type="date" name="deliverables[__IDX__][timeline_start]" class="form-control dlv-start">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">End Date</label>
                    <input type="date" name="deliverables[__IDX__][timeline_end]" class="form-control dlv-end">
                    <div class="form-text dlv-duration-hint"></div>
                </div>

                {{-- Financials --}}
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Amount</label>
                    <input type="number" step="0.01" min="0"
                           name="deliverables[__IDX__][amount]"
                           class="form-control" placeholder="0.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Currency</label>
                    <input type="text"
                           name="deliverables[__IDX__][currency]"
                           class="form-control dlv-currency"
                           value="USD" maxlength="10">
                </div>

                {{-- Notes --}}
                <div class="col-12">
                    <label class="form-label fw-semibold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="deliverables[__IDX__][notes]"
                              class="form-control"
                              rows="1"
                              maxlength="2000"></textarea>
                </div>

            </div>
        </div>
    </div>
</template>
@endsection

@push('scripts')
<script>
(function () {
    const list        = document.getElementById('deliverablesList');
    const addBtn      = document.getElementById('addRowBtn');
    const countBadge  = document.getElementById('rowCountBadge');
    const submitBtn   = document.getElementById('submitBtn');

    let rowCounter = 0;   // monotonically increasing — never reuse an index

    const FREQ_LABELS = {
        one_time:  'One-time',
        daily:     'Daily',
        weekly:    'Weekly',
        monthly:   'Monthly',
        quarterly: 'Quarterly',
        yearly:    'Yearly',
    };

    const FREQ_CLASS = {
        one_time:  'freq-one_time',
        daily:     'freq-daily',
        weekly:    'freq-weekly',
        monthly:   'freq-monthly',
        quarterly: 'freq-quarterly',
        yearly:    'freq-yearly',
    };

    /* ── helpers ──────────────────────────────────────────── */

    function daysBetween(start, end) {
        const ms = new Date(end) - new Date(start);
        return Math.round(ms / 86400000);
    }

    function durationHint(startVal, endVal, freq) {
        if (!startVal || !endVal) return '';
        const days = daysBetween(startVal, endVal);
        if (days < 0) return '⚠ End is before start';
        const hints = {
            daily:     `${days} day${days !== 1 ? 's' : ''}`,
            weekly:    `≈ ${Math.round(days / 7)} week${Math.round(days / 7) !== 1 ? 's' : ''}`,
            monthly:   `≈ ${Math.round(days / 30)} month${Math.round(days / 30) !== 1 ? 's' : ''}`,
            quarterly: `≈ ${Math.round(days / 91)} quarter${Math.round(days / 91) !== 1 ? 's' : ''}`,
            yearly:    `≈ ${Math.round(days / 365)} year${Math.round(days / 365) !== 1 ? 's' : ''}`,
            one_time:  `${days} day${days !== 1 ? 's' : ''}`,
        };
        return hints[freq] || `${days} days`;
    }

    /* ── build a row from the template ───────────────────── */

    function addRow(prefill) {
        const idx  = rowCounter++;
        const tmpl = document.getElementById('rowTemplate');
        const frag = tmpl.content.cloneNode(true);
        const card = frag.querySelector('.row-card');

        // Replace placeholder index in all name attributes
        card.querySelectorAll('[name]').forEach(el => {
            el.name = el.name.replace(/__IDX__/g, idx);
        });
        card.dataset.rowIndex = idx;

        // Pre-fill from old() values on validation failure
        if (prefill) {
            setValue(card, '.dlv-title',     prefill.title        || '');
            setValue(card, '.dlv-currency',  prefill.currency     || 'USD');
            setValue(card, '.dlv-sequence',  prefill.sequence     || '');
            setValue(card, '.dlv-start',     prefill.timeline_start || '');
            setValue(card, '.dlv-end',       prefill.timeline_end   || '');
            setSelect(card, '[name$="[type]"]',      prefill.type      || 'deliverable');
            setSelect(card, '[name$="[frequency]"]', prefill.frequency || 'one_time');
            setText(card,  '[name$="[description]"]', prefill.description || '');
            setText(card,  '[name$="[amount]"]',       prefill.amount      || '');
            setText(card,  '[name$="[notes]"]',        prefill.notes       || '');
        }

        wireRow(card);
        list.appendChild(card);
        updateAll();

        // Focus the title input of the new row
        card.querySelector('.dlv-title')?.focus();
    }

    function setValue(scope, sel, val)  { const el = scope.querySelector(sel); if (el) el.value = val; }
    function setSelect(scope, sel, val) { setValue(scope, sel, val); }
    function setText(scope, sel, val)   { const el = scope.querySelector(sel); if (el) el.value = val; }

    /* ── live-update row header as user types ─────────────── */

    function wireRow(card) {
        const titleInput = card.querySelector('.dlv-title');
        const freqSelect = card.querySelector('.dlv-frequency');
        const startInput = card.querySelector('.dlv-start');
        const endInput   = card.querySelector('.dlv-end');
        const removeBtn  = card.querySelector('.remove-btn');

        const preview  = card.querySelector('.row-title-preview');
        const freqBadge = card.querySelector('.freq-badge');
        const durationHintEl = card.querySelector('.dlv-duration-hint');

        const refreshHeader = () => {
            // Title preview
            const title = titleInput.value.trim();
            preview.textContent  = title || 'Untitled deliverable';
            preview.classList.toggle('text-muted', !title);
            preview.classList.toggle('fst-italic', !title);

            // Frequency badge
            const freq = freqSelect.value;
            freqBadge.textContent = FREQ_LABELS[freq] || freq;
            // Remove old freq classes, add new one
            Object.values(FREQ_CLASS).forEach(cls => freqBadge.classList.remove(cls));
            freqBadge.classList.add(FREQ_CLASS[freq] || 'freq-one_time');

            // Duration hint under end-date
            if (durationHintEl) {
                durationHintEl.textContent = durationHint(startInput.value, endInput.value, freq);
            }
        };

        titleInput?.addEventListener('input', refreshHeader);
        freqSelect?.addEventListener('change', refreshHeader);
        startInput?.addEventListener('change', refreshHeader);
        endInput?.addEventListener('change', refreshHeader);

        removeBtn?.addEventListener('click', () => {
            card.remove();
            updateAll();
        });

        refreshHeader();
    }

    /* ── update numbers, badge count, remove button state ─── */

    function updateAll() {
        const rows = list.querySelectorAll('.row-card');
        const n    = rows.length;

        rows.forEach((row, i) => {
            const badge = row.querySelector('.row-num');
            if (badge) badge.textContent = i + 1;

            const btn = row.querySelector('.remove-btn');
            if (btn) btn.disabled = n <= 1;
        });

        countBadge.textContent = `${n} item${n !== 1 ? 's' : ''}`;
        submitBtn.textContent  = n === 1
            ? 'Save Deliverable'
            : `Save ${n} Deliverables`;
        // Replace icon (simple approach)
        submitBtn.innerHTML = `<i class="feather-save me-1"></i> ${n === 1 ? 'Save Deliverable' : 'Save ' + n + ' Deliverables'}`;
    }

    /* ── initialise ───────────────────────────────────────── */

    addBtn.addEventListener('click', () => addRow());

    // Restore state after a validation failure
    const oldRows = @json(array_values(old('deliverables', [])));

    if (oldRows && oldRows.length > 0) {
        oldRows.forEach(row => addRow(row));
    } else {
        addRow();   // start with one blank row
    }
})();
</script>
@endpush
