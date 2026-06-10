@extends('layouts.app')

@push('styles')
<style>
    .edit-card {
        border: 1.5px solid #dbe3ef;
        border-radius: 10px;
        background: #fff;
        overflow: hidden;
    }
    .edit-card-header {
        padding: 12px 18px;
        background: #f6f8fb;
        border-bottom: 1px solid #dbe3ef;
        font-weight: 600;
        font-size: .9rem;
        color: #172033;
    }
    .edit-card-body { padding: 18px; }

    .freq-opt {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 14px;
        border: 1.5px solid #dbe3ef;
        border-radius: 8px;
        cursor: pointer;
        transition: border-color .15s, background .15s;
        flex: 1;
    }
    .freq-opt input[type="radio"] { flex-shrink: 0; }
    .freq-opt:has(input:checked) {
        border-color: #2563eb;
        background: #eff6ff;
    }
    .freq-label { font-size: .82rem; font-weight: 600; }
    .freq-hint  { font-size: .73rem; color: #64748b; }
</style>
@endpush

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="fw-bold mb-1">Edit Deliverable</h4>
            <p class="text-muted mb-0">
                Editing <strong>{{ $deliverable->title }}</strong>
                &mdash; {{ $deliverable->procurement?->reference_no ?? '' }}
                {{ $deliverable->procurement?->title ?? '' }}
            </p>
        </div>
        <a href="{{ route('procurement.deliverables.index', ['procurement_id' => $deliverable->procurement_id]) }}"
           class="btn btn-outline-secondary">
            <i class="feather-arrow-left me-1"></i> Back to Deliverables
        </a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('procurement.deliverables.update', $deliverable) }}">
        @csrf
        @method('PUT')

        <div class="row g-4">
            {{-- Left: main fields --}}
            <div class="col-lg-8">

                {{-- Context (read-only) --}}
                <div class="edit-card mb-4">
                    <div class="edit-card-header"><i class="feather-link me-2"></i>Procurement Context</div>
                    <div class="edit-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-muted small">Procurement</label>
                                <div class="form-control bg-light text-muted">
                                    {{ $deliverable->procurement?->reference_no ?? '—' }}
                                    {{ $deliverable->procurement?->title ? '— ' . $deliverable->procurement->title : '' }}
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-muted small">Vendor</label>
                                <div class="form-control bg-light text-muted">
                                    {{ $deliverable->vendor?->name ?? 'Not assigned' }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Core fields --}}
                <div class="edit-card mb-4">
                    <div class="edit-card-header"><i class="feather-file-text me-2"></i>Deliverable Details</div>
                    <div class="edit-card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
                                       value="{{ old('title', $deliverable->title) }}" required maxlength="255"
                                       placeholder="e.g. Final report submission">
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Type <span class="text-danger">*</span></label>
                                <select name="type" class="form-control @error('type') is-invalid @enderror" required>
                                    <option value="deliverable" @selected(old('type', $deliverable->type) === 'deliverable')>Deliverable</option>
                                    <option value="milestone"   @selected(old('type', $deliverable->type) === 'milestone')>Milestone</option>
                                </select>
                                @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Sequence</label>
                                <input type="number" name="sequence" class="form-control @error('sequence') is-invalid @enderror"
                                       value="{{ old('sequence', $deliverable->sequence) }}" min="1">
                                @error('sequence')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description</label>
                                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                                          rows="3" maxlength="3000"
                                          placeholder="Describe what this deliverable entails…">{{ old('description', $deliverable->description) }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Schedule frequency --}}
                <div class="edit-card mb-4">
                    <div class="edit-card-header"><i class="feather-repeat me-2"></i>Schedule Frequency <span class="text-danger">*</span></div>
                    <div class="edit-card-body">
                        @error('frequency')<div class="alert alert-danger py-2 mb-3">{{ $message }}</div>@enderror
                        <div class="row g-2">
                            @php
                                $freqOptions = [
                                    'one_time'  => ['label' => 'One-time',   'hint' => 'Happens once'],
                                    'daily'     => ['label' => 'Daily',      'hint' => 'Every day'],
                                    'weekly'    => ['label' => 'Weekly',     'hint' => 'Every week'],
                                    'monthly'   => ['label' => 'Monthly',    'hint' => 'Month by month'],
                                    'quarterly' => ['label' => 'Quarterly',  'hint' => 'Quarter by quarter'],
                                    'yearly'    => ['label' => 'Yearly',     'hint' => 'Year over year'],
                                ];
                                $selectedFreq = old('frequency', $deliverable->frequency ?? 'one_time');
                            @endphp
                            @foreach ($freqOptions as $val => $opt)
                                <div class="col-md-4">
                                    <label class="freq-opt w-100">
                                        <input type="radio" name="frequency" value="{{ $val }}"
                                               {{ $selectedFreq === $val ? 'checked' : '' }} required>
                                        <div>
                                            <div class="freq-label">{{ $opt['label'] }}</div>
                                            <div class="freq-hint">{{ $opt['hint'] }}</div>
                                        </div>
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Timeline --}}
                <div class="edit-card mb-4">
                    <div class="edit-card-header"><i class="feather-calendar me-2"></i>Timeline</div>
                    <div class="edit-card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Start Date</label>
                                <input type="date" name="timeline_start"
                                       class="form-control @error('timeline_start') is-invalid @enderror"
                                       value="{{ old('timeline_start', $deliverable->timeline_start?->format('Y-m-d')) }}"
                                       id="editStart">
                                @error('timeline_start')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">End Date</label>
                                <input type="date" name="timeline_end"
                                       class="form-control @error('timeline_end') is-invalid @enderror"
                                       value="{{ old('timeline_end', $deliverable->timeline_end?->format('Y-m-d')) }}"
                                       id="editEnd">
                                @error('timeline_end')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div id="durationHint" class="form-text"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            {{-- Right: financial + notes --}}
            <div class="col-lg-4">
                <div class="edit-card mb-4">
                    <div class="edit-card-header"><i class="feather-dollar-sign me-2"></i>Financial</div>
                    <div class="edit-card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Currency</label>
                            <input type="text" name="currency" class="form-control @error('currency') is-invalid @enderror"
                                   value="{{ old('currency', $deliverable->currency) }}"
                                   maxlength="10" placeholder="e.g. USD">
                            @error('currency')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label fw-semibold">Amount</label>
                            <input type="number" name="amount" class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount', $deliverable->amount) }}"
                                   min="0" step="0.01" placeholder="0.00">
                            @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>

                <div class="edit-card mb-4">
                    <div class="edit-card-header"><i class="feather-message-square me-2"></i>Notes</div>
                    <div class="edit-card-body">
                        <textarea name="notes" class="form-control @error('notes') is-invalid @enderror"
                                  rows="5" maxlength="2000"
                                  placeholder="Any internal notes…">{{ old('notes', $deliverable->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="feather-save me-2"></i> Save Changes
                    </button>
                    <a href="{{ route('procurement.deliverables.index', ['procurement_id' => $deliverable->procurement_id]) }}"
                       class="btn btn-outline-secondary">
                        Cancel
                    </a>
                </div>
            </div>
        </div>
    </form>
</div>

@push('scripts')
<script>
(function () {
    const startInput = document.getElementById('editStart');
    const endInput   = document.getElementById('editEnd');
    const hint       = document.getElementById('durationHint');
    const freqRadios = document.querySelectorAll('input[name="frequency"]');

    function selectedFreq() {
        return document.querySelector('input[name="frequency"]:checked')?.value || 'one_time';
    }

    function updateHint() {
        const s = startInput.value;
        const e = endInput.value;
        if (!s || !e) { hint.textContent = ''; return; }
        const ms   = new Date(e) - new Date(s);
        if (ms <= 0) { hint.textContent = ''; return; }
        const days = Math.round(ms / 86400000);
        const freq = selectedFreq();
        const map  = {
            daily:     `≈ ${days} day${days !== 1 ? 's' : ''}`,
            weekly:    `≈ ${Math.round(days / 7)} week${Math.round(days / 7) !== 1 ? 's' : ''}`,
            monthly:   `≈ ${Math.round(days / 30)} month${Math.round(days / 30) !== 1 ? 's' : ''}`,
            quarterly: `≈ ${Math.round(days / 91)} quarter${Math.round(days / 91) !== 1 ? 's' : ''}`,
            yearly:    `≈ ${Math.round(days / 365)} year${Math.round(days / 365) !== 1 ? 's' : ''}`,
            one_time:  `${days} day${days !== 1 ? 's' : ''} span`,
        };
        hint.textContent = map[freq] || '';
    }

    startInput.addEventListener('change', updateHint);
    endInput.addEventListener('change', updateHint);
    freqRadios.forEach(r => r.addEventListener('change', updateHint));
    updateHint();
})();
</script>
@endpush
@endsection
