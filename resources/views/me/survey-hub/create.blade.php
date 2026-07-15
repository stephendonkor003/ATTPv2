@extends('layouts.app')
@section('title', 'Add Questionnaire')

@section('content')
    @php
        $forceSurveyBuilder = true;
        $surveyEnabled = old('survey_public_enabled', true);
        $surveyTitle = old('survey_title', '');
        $surveyIntro = old('survey_intro', '');
        $surveyEstimatedMinutes = old('survey_estimated_minutes', '');
        $initialSurveySections = old('survey_sections_json', '[]');
    @endphp

    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'create',
            'title' => 'Add Questionnaire',
            'subtitle' => 'Create a dedicated survey form with an intro message, section-by-section navigation, and the question logic needed for public respondents.',
            'heroActions' => [
                ['href' => route('budget.me.surveys.questionnaires'), 'label' => 'Questionnaire Library', 'icon' => 'feather-book-open', 'class' => 'btn btn-light btn-sm'],
            ],
        ])

        @include('me.survey-hub._alerts')

        <div class="survey-panel">
            <div class="survey-panel__header">
                <div>
                    <div class="survey-panel__title">Questionnaire Builder</div>
                    <p class="survey-panel__subtitle">This page is dedicated to survey design, so the public survey builder is already active. Name the questionnaire however you want.</p>
                </div>
            </div>
            <div class="p-3 p-lg-4">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('budget.me-configuration.methodologies.store') }}" class="row g-3"
                    data-force-survey-builder="1">
                    @csrf
                    <input type="hidden" name="is_survey_methodology" value="1">
                    <input type="hidden" name="from_survey_module" value="1">

                    @include('me.partials.portfolio-scope-field')

                    <div class="col-md-6">
                        <label class="form-label">Questionnaire Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="methodologyNameInput" class="form-control"
                            value="{{ old('name') }}" placeholder="e.g., ATTP Post Workshop Feedback" required>
                        <small class="text-muted">Use a clear operational name. It does not need to include the word survey.</small>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Active</label>
                        <div class="form-check form-switch mt-2">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1"
                                {{ old('is_active', true) ? 'checked' : '' }}>
                            <label class="form-check-label">Enabled</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Description</label>
                        <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Steps / Guidance</label>
                        <textarea name="steps" rows="5" class="form-control"
                            placeholder="Capture facilitation notes, sampling guidance, or response handling instructions">{{ old('steps') }}</textarea>
                    </div>

                    @include('me.methodologies._survey_builder')

                    <div class="col-12 d-flex justify-content-end gap-2">
                        <a href="{{ route('budget.me.surveys.questionnaires') }}" class="btn btn-light border">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="feather-save me-1"></i> Save Questionnaire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
