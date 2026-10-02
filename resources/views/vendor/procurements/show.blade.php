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
                {!! app(\App\Services\ProcurementRichTextService::class)->render($procurement->description) !!}
            </div>
        </div>
    </div>

    @if ($procurement->documents->isNotEmpty())
        <div class="card vendor-card mb-4">
            <div class="card-body">
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
                    <div>
                        <h5 class="mb-1">Procurement Documents</h5>
                        <p class="text-muted small mb-0">Download the official bidder files before completing your application.</p>
                    </div>
                    <span class="badge bg-light text-dark border">
                        {{ $procurement->documents->count() }}
                        {{ \Illuminate\Support\Str::plural('file', $procurement->documents->count()) }}
                    </span>
                </div>

                <div class="row g-3">
                    @foreach ($procurement->documents as $document)
                        <div class="col-md-6">
                            <a href="{{ route('vendor.procurements.documents.download', [$procurement, $document]) }}"
                                class="d-flex align-items-center gap-3 border rounded-3 p-3 text-decoration-none bg-light h-100">
                                <span class="btn btn-primary btn-icon rounded-circle flex-shrink-0">
                                    <i class="feather-download"></i>
                                </span>
                                <span class="min-w-0">
                                    <strong class="d-block text-dark">{{ $document->document_name }}</strong>
                                    <small class="text-muted d-block text-truncate">
                                        {{ $document->original_name }} · {{ $document->formatted_size }}
                                    </small>
                                </span>
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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

                    @if($form->fields->whereIn('field_type', \App\Support\DynamicProcurementFormCatalog::UPLOAD_TYPES)->isNotEmpty())
                        <div class="alert alert-light border small" role="note">
                            You may upload up to {{ \App\Support\DynamicProcurementFormCatalog::MAX_SUBMISSION_FILES }} files,
                            with a combined maximum of {{ \App\Support\DynamicProcurementFormCatalog::MAX_SUBMISSION_UPLOAD_MB }} MB.
                        </div>
                    @endif
                    @error('files')<div class="alert alert-danger" role="alert">{{ $message }}</div>@enderror

                    <div class="row g-3">
                        @foreach ($form->fields as $field)
                            @php
                                $oldValue = old($field->field_key);

                                if (in_array($field->field_type, ['checkbox', 'multiselect']) && is_string($oldValue)) {
                                    $oldValue = array_filter(array_map('trim', explode(',', $oldValue)));
                                }

                                $options = $field->optionValues();
                                $isRequired = (bool) $field->is_required;
                                $configuration = (array) $field->validation_rules;
                                $isIdentityField = in_array($field->field_key, ['official_name', 'official_email'], true);

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

                                $wideTypes = ['textarea', 'radio', 'checkbox', 'boolean', 'file', 'image'];
                                $safeExtensions = \App\Support\DynamicProcurementFormCatalog::extensionsFor($field->field_type);
                                $configuredExtensions = array_values(array_intersect(
                                    array_map(fn($extension) => strtolower(ltrim(trim((string) $extension), '.')), (array) ($configuration['allowed_extensions'] ?? $safeExtensions)),
                                    $safeExtensions,
                                ));
                                $acceptedExtensions = collect($configuredExtensions ?: $safeExtensions)
                                    ->map(fn($extension) => '.'.ltrim($extension, '.'))
                                    ->implode(',');
                                $acceptedFiles = $acceptedExtensions;
                                $isGroupedChoice = in_array($field->field_type, ['radio', 'checkbox', 'boolean'], true);
                                $fieldHelpId = 'field-'.$field->id.'-help';
                                $fieldErrorId = 'field-'.$field->id.'-error';
                                $fieldHasError = $errors->has($field->field_key) || $errors->has($field->field_key.'.*');
                                $fieldHasHelp = in_array($field->field_type, ['select', 'radio', 'multiselect', 'checkbox', 'file', 'image'], true)
                                    || filled($field->help_text);
                                $fieldDescribedBy = collect([$fieldHasHelp ? $fieldHelpId : null, $fieldHasError ? $fieldErrorId : null])->filter()->implode(' ');
                            @endphp

                            <div class="{{ in_array($field->field_type, $wideTypes, true) ? 'col-12' : 'col-md-6' }}">
                                @if($isGroupedChoice)
                                <fieldset class="border-0 p-0 m-0" @if($isRequired) aria-required="true" @endif @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif>
                                    <legend class="form-label fw-semibold">
                                        {{ $field->label }}
                                        @if ($isRequired)<span class="text-danger">*</span>@else<small class="text-muted">(Optional)</small>@endif
                                    </legend>
                                @else
                                <label class="form-label fw-semibold" for="field-{{ $field->id }}">
                                    {{ $field->label }}
                                    @if ($isRequired)
                                        <span class="text-danger">*</span>
                                    @else
                                        <small class="text-muted">(Optional)</small>
                                    @endif
                                </label>
                                @endif

                                @if (in_array($field->field_type, ['text', 'email', 'tel', 'url', 'date', 'time'], true))
                                    <input id="field-{{ $field->id }}" type="{{ $field->field_type }}" name="{{ $field->field_key }}"
                                        value="{{ $oldValue }}" class="form-control" placeholder="{{ $field->placeholder }}"
                                        @if($configuration['max_length'] ?? null) maxlength="{{ $configuration['max_length'] }}" @endif
                                        @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @readonly($isIdentityField) @required($isRequired)>
                                @elseif ($field->field_type === 'number')
                                    <input id="field-{{ $field->id }}" type="number" step="any" name="{{ $field->field_key }}"
                                        value="{{ $oldValue }}" class="form-control" placeholder="{{ $field->placeholder }}"
                                        @if(array_key_exists('min', $configuration)) min="{{ $configuration['min'] }}" @endif
                                        @if(array_key_exists('max', $configuration)) max="{{ $configuration['max'] }}" @endif
                                        @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @required($isRequired)>
                                @elseif ($field->field_type === 'datetime-local')
                                    <input id="field-{{ $field->id }}" type="datetime-local" name="{{ $field->field_key }}"
                                        value="{{ $dateTimeValue }}" class="form-control" @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @required($isRequired)>
                                @elseif ($field->field_type === 'textarea')
                                    <textarea id="field-{{ $field->id }}" name="{{ $field->field_key }}" rows="5"
                                        class="form-control" placeholder="{{ $field->placeholder }}"
                                        @if($configuration['max_length'] ?? null) maxlength="{{ $configuration['max_length'] }}" @endif
                                        @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @required($isRequired)>{{ $oldValue }}</textarea>
                                @elseif ($field->field_type === 'select')
                                    <select id="field-{{ $field->id }}" name="{{ $field->field_key }}" class="form-select"
                                        @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @required($isRequired)>
                                        <option value="">Select an option</option>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field->field_type === 'multiselect')
                                    <select id="field-{{ $field->id }}" name="{{ $field->field_key }}[]" class="form-select" multiple
                                        @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @required($isRequired)>
                                        @foreach ($options as $option)
                                            <option value="{{ $option }}" @selected(is_array($oldValue) && in_array($option, $oldValue, true))>{{ $option }}</option>
                                        @endforeach
                                    </select>
                                @elseif ($field->field_type === 'radio')
                                    <div id="field-{{ $field->id }}" class="d-grid gap-2 border rounded-3 p-3">
                                        @foreach ($options as $option)
                                            <label class="d-flex align-items-center gap-2 mb-0">
                                                <input type="radio" name="{{ $field->field_key }}" value="{{ $option }}"
                                                    @checked((string) $oldValue === (string) $option) @required($isRequired)>
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($field->field_type === 'checkbox')
                                    <div id="field-{{ $field->id }}" class="d-grid gap-2 border rounded-3 p-3" @if($isRequired) data-required-checkbox-group @endif>
                                        @foreach ($options as $option)
                                            <label class="d-flex align-items-center gap-2 mb-0">
                                                <input type="checkbox" name="{{ $field->field_key }}[]" value="{{ $option }}"
                                                    @checked(is_array($oldValue) && in_array($option, $oldValue, true))>
                                                <span>{{ $option }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                @elseif ($field->field_type === 'boolean')
                                    <label id="field-{{ $field->id }}" class="d-flex align-items-start gap-2 border rounded-3 p-3 mb-0">
                                        <input type="checkbox" name="{{ $field->field_key }}" value="1"
                                            @checked(old($field->field_key)) @required($isRequired)>
                                        <span>{{ $field->placeholder ?: 'Yes, I confirm.' }}</span>
                                    </label>
                                @elseif (in_array($field->field_type, ['file', 'image'], true))
                                    <input id="field-{{ $field->id }}" type="file" name="{{ $field->field_key }}" class="form-control"
                                        @if($acceptedFiles) accept="{{ $acceptedFiles }}" @endif @if($fieldDescribedBy) aria-describedby="{{ $fieldDescribedBy }}" @endif @if($fieldHasError) aria-invalid="true" @endif @required($isRequired)>
                                @endif

                                @if($fieldHasHelp)<div id="{{ $fieldHelpId }}">
                                @if(in_array($field->field_type, ['select', 'radio'], true))
                                    <div class="form-text">Choose one of the answers specified above.</div>
                                @elseif(in_array($field->field_type, ['multiselect', 'checkbox'], true))
                                    <div class="form-text">Choose one or more of the answers specified above.</div>
                                @endif
                                @if($field->help_text)<div class="form-text">{{ $field->help_text }}</div>@endif
                                @if(in_array($field->field_type, ['file', 'image'], true) && ($configuration['max_file_size_mb'] ?? null))
                                    <div class="form-text">Maximum file size: {{ $configuration['max_file_size_mb'] }} MB.</div>
                                @endif
                                @if(in_array($field->field_type, ['file', 'image'], true) && !empty($configuredExtensions))
                                    <div class="form-text">Accepted types: {{ collect($configuredExtensions)->map(fn($extension) => strtoupper($extension))->implode(', ') }}.</div>
                                @endif
                                </div>@endif
                                @if($fieldHasError)<div id="{{ $fieldErrorId }}" class="text-danger small mt-1" role="alert">
                                    @error($field->field_key)<div>{{ $message }}</div>@enderror
                                    @error($field->field_key.'.*')<div>{{ $message }}</div>@enderror
                                </div>@endif
                                @if($isGroupedChoice)</fieldset>@endif
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

@push('scripts')
    @include('procurement.partials.required-checkbox-groups')
@endpush
