@extends('layouts.vendor')

@section('title', 'Edit Application')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Edit Application</h3>
        <p class="text-muted mb-0">
            Procurement: {{ $submission->procurement?->title ?? 'N/A' }} ·
            Reference: {{ $submission->procurement?->reference_no ?? 'N/A' }}
        </p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>There were errors with your update.</strong>
        </div>
    @endif

    <div class="card vendor-card">
        <div class="card-body">
            <form method="POST" action="{{ route('vendor.applications.update', $submission) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                <div class="row g-3">
                    @foreach ($form->fields as $field)
                        @php
                            $storedValue = $values->get($field->field_key)?->value;
                            $oldValue = old($field->field_key, $storedValue);

                            if (in_array($field->field_type, ['checkbox', 'multiselect'])) {
                                if (is_string($oldValue)) {
                                    $decoded = json_decode($oldValue, true);
                                    $oldValue = is_array($decoded) ? $decoded : array_filter(array_map('trim', explode(',', $oldValue)));
                                }
                            }
                        @endphp

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                {{ $field->label }}
                                @if ($field->is_required)
                                    <span class="text-danger">*</span>
                                @endif
                            </label>

                            @if ($field->field_type === 'textarea')
                                <textarea name="{{ $field->field_key }}" rows="4" class="form-control"
                                    {{ $field->is_required ? 'required' : '' }}>{{ $oldValue }}</textarea>

                            @elseif ($field->field_type === 'file')
                                <input type="file" name="{{ $field->field_key }}" class="form-control"
                                    {{ $field->is_required && !$storedValue ? 'required' : '' }}>
                                @if ($storedValue)
                                    <div class="text-muted small mt-1">A file is already uploaded. Upload a new file to replace it.</div>
                                @endif

                            @elseif ($field->field_type === 'select')
                                @php
                                    $options = collect(explode(',', (string) $field->options))
                                        ->map(fn($opt) => trim($opt))
                                        ->filter()
                                        ->values();
                                @endphp
                                <select name="{{ $field->field_key }}" class="form-select" {{ $field->is_required ? 'required' : '' }}>
                                    <option value="">Select an option</option>
                                    @foreach ($options as $option)
                                        <option value="{{ $option }}" {{ (string) $oldValue === (string) $option ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                    @endforeach
                                </select>

                            @elseif (in_array($field->field_type, ['checkbox', 'multiselect']))
                                @php
                                    $options = collect(explode(',', (string) $field->options))
                                        ->map(fn($opt) => trim($opt))
                                        ->filter()
                                        ->values();
                                @endphp
                                <select name="{{ $field->field_key }}[]" class="form-select" multiple
                                    {{ $field->is_required ? 'required' : '' }}>
                                    @foreach ($options as $option)
                                        <option value="{{ $option }}" {{ is_array($oldValue) && in_array($option, $oldValue) ? 'selected' : '' }}>
                                            {{ $option }}
                                        </option>
                                    @endforeach
                                </select>
                            @else
                                <input type="{{ $field->field_type }}" name="{{ $field->field_key }}"
                                    value="{{ $oldValue }}" class="form-control"
                                    {{ $field->is_required ? 'required' : '' }}>
                            @endif

                            @error($field->field_key)
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-end mt-4 gap-2">
                    <a href="{{ route('vendor.dashboard') }}" class="btn btn-vendor-outline">Cancel</a>
                    <button class="btn btn-vendor" type="submit">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
@endsection
