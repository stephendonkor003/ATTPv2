@extends('layouts.app')
@section('title', 'Edit Methodology')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-book-open text-primary me-2"></i>Edit Methodology</h4>
                <p class="text-muted mb-0">Update methodology details and refine the public survey journey.</p>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-body">
                @php
                    use App\Support\MeSurvey;

                    $normalizedSurvey = MeSurvey::surveyConfigFromMetadata(
                        (array) ($methodology->metadata ?? []),
                        ($methodology->name ?: 'Public Survey') . ' Public Survey'
                    );

                    $surveyEnabled = old('survey_public_enabled', data_get($normalizedSurvey, 'enabled', true));
                    $surveyTitle = old('survey_title', data_get($normalizedSurvey, 'title', $methodology->name . ' Public Survey'));
                    $surveyIntro = old('survey_intro', data_get($normalizedSurvey, 'intro', ''));
                    $surveyEstimatedMinutes = old('survey_estimated_minutes', data_get($normalizedSurvey, 'estimated_minutes', ''));
                    $initialSurveySections = old(
                        'survey_sections_json',
                        json_encode((array) data_get($normalizedSurvey, 'sections', []))
                    );
                @endphp

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('budget.me-configuration.methodologies.update', $methodology) }}" class="row g-3">
                    @csrf
                    @method('PUT')

                    <div class="col-md-6">
                        <label class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="methodologyNameInput" class="form-control"
                            value="{{ old('name', $methodology->name) }}"
                            placeholder="e.g., Workshop Survey, Field Observation, Desk Review" required>
                        <small class="text-muted">If the name includes <strong>survey</strong>, the survey builder appears automatically.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Active</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                {{ old('is_active', $methodology->is_active) ? 'checked' : '' }}>
                            <label class="form-check-label">Enabled</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description', $methodology->description) }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Steps / Guidance</label>
                        <textarea name="steps" rows="5" class="form-control">{{ old('steps', $methodology->steps) }}</textarea>
                    </div>

                    @include('me.methodologies._survey_builder')

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('budget.me-configuration.methodologies.index') }}" class="btn btn-light border">Cancel</a>
                        <button class="btn btn-primary" type="submit"><i class="feather-save me-1"></i> Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
