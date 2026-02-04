@extends('layouts.vendor')

@section('title', 'Procurement Application')

@section('content')
    @php
        use Illuminate\Support\Str;
    @endphp

    <div class="mb-4">
        <h3 class="mb-1">{{ $procurement->title }}</h3>
        <p class="text-muted mb-0">Reference: {{ $procurement->reference_no ?? 'N/A' }}</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>Please correct the errors below.</strong>
        </div>
    @endif

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <h5 class="mb-3">Procurement Details</h5>
            <div style="line-height:1.7;">
                {!! nl2br(e($procurement->description ?? '')) !!}
            </div>
        </div>
    </div>

    @if ($existingSubmission)
        <div class="alert alert-info">
            You already submitted this procurement. You can update your application from the submissions list.
            <a href="{{ route('vendor.applications.edit', $existingSubmission) }}" class="ms-2">Edit Application</a>
        </div>
    @endif

    <div class="card vendor-card">
        <div class="card-body">
            <h5 class="mb-3">Application Form</h5>

            @if ($form?->fields?->isNotEmpty())
                <form method="POST" action="{{ route('vendor.procurements.submit', $procurement) }}"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        @foreach ($form->fields as $field)
                            @php
                                $oldValue = old($field->field_key);

                                if (in_array($field->field_type, ['checkbox', 'multiselect']) && is_string($oldValue)) {
                                    $oldValue = array_filter(array_map('trim', explode(',', $oldValue)));
                                }

                                $options = collect(explode(',', (string) $field->options))
                                    ->map(fn($opt) => trim($opt))
                                    ->filter()
                                    ->values()
                                    ->toArray();

                                $isRequired = $field->is_required;

                                $dateTimeValue = $oldValue;
                                if ($field->field_type === 'datetime-local' && $oldValue) {
                                    try {
                                        $dateTimeValue = \Carbon\Carbon::parse($oldValue)->format('Y-m-d\TH:i');
                                    } catch (\Exception $e) {
                                        $dateTimeValue = $oldValue;
                                    }
                                }

                                if ($field->field_key === 'official_name' && !$oldValue) {
                                    $oldValue = auth()->user()->name ?? auth()->user()->email;
                                }
                                if ($field->field_key === 'official_email' && !$oldValue) {
                                    $oldValue = auth()->user()->email;
                                }
                            @endphp

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">
                                    {{ $field->label }}
                                    @if ($isRequired)
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>

                                @if ($field->field_type === 'text')
                                    <input type="text" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'email')
                                    <input type="email" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'number')
                                    <input type="number" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'date')
                                    <input type="date" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'time')
                                    <input type="time" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'datetime-local')
                                    <input type="datetime-local" name="{{ $field->field_key }}" value="{{ $dateTimeValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'url')
                                    <input type="url" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'tel')
                                    <input type="tel" name="{{ $field->field_key }}" value="{{ $oldValue }}"
                                        class="form-control" {{ $isRequired ? 'required' : '' }}>
                                @elseif ($field->field_type === 'textarea')
                                    <textarea name="{{ $field->field_key }}" rows="4" class="form-control"
                                        {{ $isRequired ? 'required' : '' }}>{{ $oldValue }}</textarea>
                                @elseif ($field->field_type === 'select')
                                    <select name="{{ $field->field_key }}" class="form-select"
                                        {{ $isRequired ? 'required' : '' }}>
                                        <option value="">Select an option</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}"
                                                {{ (string) $oldValue === (string) $option ? 'selected' : '' }}>
                                                {{ $option }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif (in_array($field->field_type, ['checkbox', 'multiselect']))
                                    <select name="{{ $field->field_key }}[]" class="form-select" multiple
                                        {{ $isRequired ? 'required' : '' }}>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}"
                                                {{ is_array($oldValue) && in_array($option, $oldValue) ? 'selected' : '' }}>
                                                {{ $option }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif ($field->field_type === 'file')
                                    <input type="file" name="{{ $field->field_key }}" class="form-control"
                                        {{ $isRequired ? 'required' : '' }}>
                                @endif

                                @error($field->field_key)
                                    <div class="text-danger small mt-1">{{ $message }}</div>
                                @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-vendor" {{ $existingSubmission ? 'disabled' : '' }}>
                            Submit Application
                        </button>
                    </div>
                </form>
            @else
                <p class="text-muted mb-0">No application form has been attached to this procurement yet.</p>
            @endif
        </div>
    </div>
@endsection
