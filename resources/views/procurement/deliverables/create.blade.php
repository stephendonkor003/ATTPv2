@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-3">
            <div>
                <h4 class="fw-bold mb-1">Create Deliverable</h4>
                <p class="text-muted mb-0">Define a deliverable or milestone for an awarded procurement.</p>
            </div>
            <a href="{{ route('procurement.deliverables.index') }}" class="btn btn-light btn-sm">
                <i class="feather-arrow-left me-1"></i> Back
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

        <form method="POST" action="{{ route('procurement.deliverables.store') }}">
            @csrf

            {{-- Procurement & Vendor --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="fw-bold mb-0">Procurement &amp; Vendor</h6>
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
                                @foreach ($procurements as $procurement)
                                    <option value="{{ $procurement->id }}"
                                        {{ (old('procurement_id', $selectedProcurementId) == $procurement->id) ? 'selected' : '' }}>
                                        {{ $procurement->reference_no ?? 'N/A' }} — {{ $procurement->title }}
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
                                <option value="">— Optional: assign to vendor —</option>
                                @foreach ($vendors as $vendor)
                                    <option value="{{ $vendor->id }}"
                                        {{ old('vendor_id') == $vendor->id ? 'selected' : '' }}>
                                        {{ $vendor->name }} — {{ $vendor->email }}
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

            {{-- Deliverable Details --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="fw-bold mb-0">Deliverable Details</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">
                                Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" name="title"
                                   class="form-control @error('title') is-invalid @enderror"
                                   value="{{ old('title') }}" maxlength="255" required
                                   placeholder="e.g. Inception Report, Kick-off Workshop, Final Evaluation">
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">
                                Type <span class="text-danger">*</span>
                            </label>
                            <select name="type"
                                    class="form-control @error('type') is-invalid @enderror"
                                    required>
                                <option value="deliverable" @selected(old('type', 'deliverable') === 'deliverable')>Deliverable</option>
                                <option value="milestone" @selected(old('type') === 'milestone')>Milestone</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Sequence #</label>
                            <input type="number" name="sequence" min="1"
                                   class="form-control @error('sequence') is-invalid @enderror"
                                   value="{{ old('sequence', 1) }}">
                            @error('sequence')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" rows="3"
                                      class="form-control @error('description') is-invalid @enderror"
                                      maxlength="3000"
                                      placeholder="Describe what must be delivered, acceptance criteria, expected output…">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Timeline & Financials --}}
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h6 class="fw-bold mb-0">Timeline &amp; Financials</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Start Date</label>
                            <input type="date" name="timeline_start"
                                   class="form-control @error('timeline_start') is-invalid @enderror"
                                   value="{{ old('timeline_start') }}">
                            @error('timeline_start')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">End Date</label>
                            <input type="date" name="timeline_end"
                                   class="form-control @error('timeline_end') is-invalid @enderror"
                                   value="{{ old('timeline_end') }}">
                            @error('timeline_end')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Amount</label>
                            <input type="number" step="0.01" min="0" name="amount"
                                   class="form-control @error('amount') is-invalid @enderror"
                                   value="{{ old('amount') }}"
                                   placeholder="0.00">
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Currency</label>
                            <input type="text" name="currency"
                                   class="form-control @error('currency') is-invalid @enderror"
                                   value="{{ old('currency', 'USD') }}" maxlength="10">
                            @error('currency')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Notes</label>
                            <textarea name="notes" rows="2"
                                      class="form-control @error('notes') is-invalid @enderror"
                                      maxlength="2000">{{ old('notes') }}</textarea>
                            @error('notes')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-end">
                <a href="{{ route('procurement.deliverables.index') }}" class="btn btn-light me-2">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather-plus-circle me-1"></i> Create Deliverable
                </button>
            </div>
        </form>
    </div>
@endsection
