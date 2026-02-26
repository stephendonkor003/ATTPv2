<div class="row g-4">
    <div class="col-md-8">
        <label class="form-label fw-semibold">Treaty Title <span class="text-danger">*</span></label>
        <input type="text" name="title" class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title', optional($treaty)->title) }}" required>
        @error('title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Short Title</label>
        <input type="text" name="short_title" class="form-control @error('short_title') is-invalid @enderror"
            value="{{ old('short_title', optional($treaty)->short_title) }}">
        @error('short_title')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Reference Code</label>
        <input type="text" name="reference_code" class="form-control @error('reference_code') is-invalid @enderror"
            value="{{ old('reference_code', optional($treaty)->reference_code) }}" placeholder="e.g. AU-TR-2026-01">
        @error('reference_code')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Adoption Date</label>
        <input type="date" name="adoption_date" class="form-control @error('adoption_date') is-invalid @enderror"
            value="{{ old('adoption_date', optional(optional($treaty)->adoption_date)->format('Y-m-d')) }}">
        @error('adoption_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Entry Into Force</label>
        <input type="date" name="entry_into_force_date"
            class="form-control @error('entry_into_force_date') is-invalid @enderror"
            value="{{ old('entry_into_force_date', optional(optional($treaty)->entry_into_force_date)->format('Y-m-d')) }}">
        @error('entry_into_force_date')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">Record Status <span class="text-danger">*</span></label>
        <select name="status" class="form-select @error('status') is-invalid @enderror" required>
            @php($selectedStatus = old('status', optional($treaty)->status ?? 'active'))
            <option value="draft" {{ $selectedStatus === 'draft' ? 'selected' : '' }}>Draft</option>
            <option value="active" {{ $selectedStatus === 'active' ? 'selected' : '' }}>Active</option>
            <option value="archived" {{ $selectedStatus === 'archived' ? 'selected' : '' }}>Archived</option>
        </select>
        @error('status')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <label class="form-label fw-semibold">Description</label>
        <textarea name="description" rows="6" class="form-control @error('description') is-invalid @enderror"
            placeholder="Treaty summary, objectives, and implementation notes...">{{ old('description', optional($treaty)->description) }}</textarea>
        @error('description')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="col-12">
        <div class="border rounded-3 p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <label class="form-label fw-semibold mb-0">Supporting Documents</label>
                <button type="button" class="btn btn-sm btn-outline-primary" id="add-supporting-document-row">
                    <i class="feather-plus"></i> Add Document
                </button>
            </div>
            <p class="text-muted small mb-3">
                Add multiple supporting documents (legal notes, annexes, background papers, implementation guidelines).
            </p>

            @if (optional($treaty)->exists && $treaty->supportingDocuments->count())
                <div class="mb-3">
                    <div class="small fw-semibold text-muted mb-2">Existing Supporting Documents</div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th>File</th>
                                    <th width="130">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($treaty->supportingDocuments as $existingDocument)
                                    <tr>
                                        <td>{{ $existingDocument->title ?: '—' }}</td>
                                        <td>{{ $existingDocument->document_type ?: '—' }}</td>
                                        <td>
                                            <a href="{{ route('treaties.supporting-documents.download', $existingDocument->id) }}?download=1">
                                                {{ $existingDocument->file_name }}
                                            </a>
                                        </td>
                                        <td>
                                            <label class="form-check mb-0">
                                                <input type="checkbox" class="form-check-input"
                                                    name="remove_supporting_document_ids[]"
                                                    value="{{ $existingDocument->id }}">
                                                <span class="form-check-label">Remove</span>
                                            </label>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <div id="supporting-documents-wrapper">
                @foreach ((array) old('supporting_document_titles', ['']) as $index => $oldTitle)
                    <div class="row g-2 supporting-document-row mb-2">
                        <div class="col-md-4">
                            <input type="text" name="supporting_document_titles[]" class="form-control"
                                placeholder="Document title (optional)" value="{{ $oldTitle }}">
                        </div>
                        <div class="col-md-3">
                            <select name="supporting_document_types[]" class="form-select">
                                @php($selectedType = old('supporting_document_types.' . $index, ''))
                                <option value="" {{ $selectedType === '' ? 'selected' : '' }}>Type (optional)</option>
                                <option value="Legal Opinion" {{ $selectedType === 'Legal Opinion' ? 'selected' : '' }}>
                                    Legal Opinion
                                </option>
                                <option value="Annex" {{ $selectedType === 'Annex' ? 'selected' : '' }}>Annex</option>
                                <option value="Policy Brief" {{ $selectedType === 'Policy Brief' ? 'selected' : '' }}>
                                    Policy Brief
                                </option>
                                <option value="Implementation Guide"
                                    {{ $selectedType === 'Implementation Guide' ? 'selected' : '' }}>
                                    Implementation Guide
                                </option>
                                <option value="Other" {{ $selectedType === 'Other' ? 'selected' : '' }}>Other</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <input type="file" name="supporting_documents[]" class="form-control">
                        </div>
                        <div class="col-md-1 d-flex align-items-center">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-supporting-document-row"
                                title="Remove row">
                                <i class="feather-x"></i>
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>

            @error('supporting_documents.*')
                <div class="text-danger small mt-1">{{ $message }}</div>
            @enderror
        </div>
    </div>
</div>
