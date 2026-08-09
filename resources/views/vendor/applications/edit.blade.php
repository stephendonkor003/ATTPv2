@extends('layouts.vendor')

@section('title', 'Review Application')

@section('content')
    @php
        $isRecallResponse = $submission->status === \App\Models\FormSubmission::STATUS_REVISION_REQUESTED;
        $procurement = $submission->procurement;
    @endphp

    <div class="mb-4 d-flex flex-wrap justify-content-between align-items-start gap-3">
        <div>
            <h3 class="mb-1">{{ $isRecallResponse ? 'Respond and resubmit application' : 'Review application' }}</h3>
            <p class="text-muted mb-0">
                {{ $procurement?->title ?? 'Procurement opportunity' }} &middot;
                {{ $procurement?->reference_no ?? 'No reference' }} &middot;
                Publication version {{ $procurement?->publication_version ?? 1 }}
            </p>
        </div>
        <a href="{{ route('vendor.submissions') }}" class="btn btn-vendor-outline">Back to applications</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger"><strong>Please correct the highlighted information before resubmitting.</strong></div>
    @endif

    @if($isRecallResponse)
        <div class="alert border-0 mb-4" style="background:#fff6df;color:#5f430e;">
            <div class="fw-bold mb-1"><i class="feather-alert-circle me-1"></i> This opportunity was recalled and has now been republished</div>
            <div>{{ $procurement?->recall_reason ?: 'Review the current opportunity details and confirm your application remains accurate.' }}</div>
            <div class="small mt-2">Your previous answers and documents are preserved. Update only what changed, add your response below, then resubmit.</div>
        </div>
    @endif

    <div class="card vendor-card">
        <div class="card-body p-4">
            <form method="POST" action="{{ route('vendor.applications.update', $submission) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if($isRecallResponse)
                    <div class="mb-4 p-3 rounded-3" style="background:#eef7fa;border:1px solid #cce1e8;">
                        <label class="form-label fw-semibold" for="vendor-response">Response to the recall note <span class="text-danger">*</span></label>
                        <textarea id="vendor-response" name="vendor_response" rows="4" class="form-control @error('vendor_response') is-invalid @enderror" maxlength="2000" required placeholder="Explain any changes made, or confirm that your previous application remains valid.">{{ old('vendor_response', $submission->vendor_response) }}</textarea>
                        <div class="form-text">This response will accompany your resubmitted application.</div>
                        @error('vendor_response')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif

                <div class="row g-3">
                    @foreach ($form->fields as $field)
                        @php
                            $storedValue = $values->get($field->field_key)?->value;
                            $oldValue = old($field->field_key, $storedValue);
                            if (in_array($field->field_type, ['checkbox', 'multiselect'], true) && is_string($oldValue)) {
                                $decoded = json_decode($oldValue, true);
                                $oldValue = is_array($decoded) ? $decoded : array_filter(array_map('trim', preg_split('/[\r\n,]+/', $oldValue)));
                            }
                            $options = $field->optionValues();
                            $configuration = (array) $field->validation_rules;
                            $wide = in_array($field->field_type, ['textarea', 'radio', 'checkbox', 'boolean', 'file', 'image'], true);
                            $acceptedFiles = $field->field_type === 'image'
                                ? 'image/jpeg,image/png,image/webp'
                                : collect((array) ($configuration['allowed_extensions'] ?? []))->map(fn($extension) => '.'.ltrim($extension, '.'))->implode(',');
                        @endphp

                        <div class="{{ $wide ? 'col-12' : 'col-lg-6' }}">
                            <label class="form-label fw-semibold" for="vendor-field-{{ $field->id }}">
                                {{ $field->label }}
                                @if ($field->is_required)<span class="text-danger">*</span>@else<span class="text-muted small">(Optional)</span>@endif
                            </label>

                            @if ($field->field_type === 'textarea')
                                <textarea id="vendor-field-{{ $field->id }}" name="{{ $field->field_key }}" rows="5" class="form-control" placeholder="{{ $field->placeholder }}" @required($field->is_required)>{{ $oldValue }}</textarea>
                            @elseif (in_array($field->field_type, ['file', 'image'], true))
                                <input id="vendor-field-{{ $field->id }}" type="file" name="{{ $field->field_key }}" class="form-control" @if($acceptedFiles) accept="{{ $acceptedFiles }}" @endif @required($field->is_required && !$storedValue)>
                                @if ($storedValue)<div class="form-text text-success"><i class="feather-check-circle"></i> A file is stored. Select another file only to replace it.</div>@endif
                            @elseif ($field->field_type === 'select')
                                <select id="vendor-field-{{ $field->id }}" name="{{ $field->field_key }}" class="form-select" @required($field->is_required)>
                                    <option value="">Choose one answer</option>
                                    @foreach ($options as $option)<option value="{{ $option }}" @selected((string) $oldValue === (string) $option)>{{ $option }}</option>@endforeach
                                </select>
                            @elseif ($field->field_type === 'multiselect')
                                <select id="vendor-field-{{ $field->id }}" name="{{ $field->field_key }}[]" class="form-select" multiple @required($field->is_required)>
                                    @foreach ($options as $option)<option value="{{ $option }}" @selected(is_array($oldValue) && in_array($option, $oldValue, true))>{{ $option }}</option>@endforeach
                                </select>
                                <div class="form-text">Choose one or more answers.</div>
                            @elseif ($field->field_type === 'radio')
                                <div class="d-grid gap-2 rounded-3 border p-3" id="vendor-field-{{ $field->id }}">
                                    @foreach($options as $option)<label class="d-flex gap-2 align-items-center"><input type="radio" name="{{ $field->field_key }}" value="{{ $option }}" @checked((string) $oldValue === (string) $option) @required($field->is_required)><span>{{ $option }}</span></label>@endforeach
                                </div>
                            @elseif ($field->field_type === 'checkbox')
                                <div class="d-grid gap-2 rounded-3 border p-3" id="vendor-field-{{ $field->id }}">
                                    @foreach($options as $option)<label class="d-flex gap-2 align-items-center"><input type="checkbox" name="{{ $field->field_key }}[]" value="{{ $option }}" @checked(is_array($oldValue) && in_array($option, $oldValue, true))><span>{{ $option }}</span></label>@endforeach
                                </div>
                                <div class="form-text">Choose one or more answers.</div>
                            @elseif ($field->field_type === 'boolean')
                                <label class="d-flex gap-2 align-items-start rounded-3 border p-3" id="vendor-field-{{ $field->id }}"><input class="mt-1" type="checkbox" name="{{ $field->field_key }}" value="1" @checked($oldValue) @required($field->is_required)><span>{{ $field->placeholder ?: 'Yes, I confirm.' }}</span></label>
                            @elseif ($field->field_type === 'number')
                                <input id="vendor-field-{{ $field->id }}" type="number" step="any" name="{{ $field->field_key }}" value="{{ $oldValue }}" class="form-control" placeholder="{{ $field->placeholder }}" @if(array_key_exists('min', $configuration)) min="{{ $configuration['min'] }}" @endif @if(array_key_exists('max', $configuration)) max="{{ $configuration['max'] }}" @endif @required($field->is_required)>
                            @else
                                <input id="vendor-field-{{ $field->id }}" type="{{ $field->field_type }}" name="{{ $field->field_key }}" value="{{ $oldValue }}" class="form-control" placeholder="{{ $field->placeholder }}" @required($field->is_required)>
                            @endif

                            @if($field->help_text)<div class="form-text">{{ $field->help_text }}</div>@endif
                            @error($field->field_key)<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @error($field->field_key.'.*')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    @endforeach
                </div>

                <div class="d-flex justify-content-end mt-4 gap-2">
                    <a href="{{ route('vendor.submissions') }}" class="btn btn-vendor-outline">Cancel</a>
                    <button class="btn btn-vendor" type="submit"><i class="feather-send me-1"></i>{{ $isRecallResponse ? 'Submit response & resubmit' : 'Save and resubmit' }}</button>
                </div>
            </form>
        </div>
    </div>

    <details class="card vendor-card mt-4">
        <summary class="card-body fw-semibold" style="cursor:pointer;color:#9f2f2f;">Withdraw this application</summary>
        <div class="card-body border-top">
            <p class="text-muted">Withdrawal keeps an audit record but removes this application from active consideration. If this opportunity is still open, you can submit a new application afterward.</p>
            <form method="POST" action="{{ route('vendor.applications.withdraw', $submission) }}" onsubmit="return confirm('Withdraw this application from consideration?')">
                @csrf
                <label class="form-label fw-semibold" for="withdrawal-reason">Reason for withdrawal <span class="text-danger">*</span></label>
                <textarea id="withdrawal-reason" name="withdrawal_reason" rows="3" class="form-control" minlength="5" maxlength="1000" required></textarea>
                <button type="submit" class="btn btn-outline-danger mt-3"><i class="feather-x-circle me-1"></i> Withdraw application</button>
            </form>
        </div>
    </details>
@endsection
