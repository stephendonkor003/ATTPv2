@extends('layouts.app')
@section('title', 'Edit Questionnaire')

@section('content')
    @php
        use App\Support\MeSurvey;

        $forceSurveyBuilder = true;
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

        $heroActions = [
            ['href' => route('budget.me.surveys.questionnaires'), 'label' => 'Questionnaire Library', 'icon' => 'feather-book-open', 'class' => 'btn btn-light btn-sm'],
        ];

        if ($methodology->surveyLinks()->where('is_active', true)->exists()) {
            $heroActions[] = ['href' => route('budget.me.surveys.qr', ['q' => $methodology->name]), 'label' => 'View QR Codes', 'icon' => 'feather-grid', 'class' => 'btn btn-outline-light btn-sm'];
        }
    @endphp

    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'questionnaires',
            'title' => 'Edit Questionnaire',
            'subtitle' => 'Refine section flow, update question logic, and keep the published survey experience aligned with your M&E needs.',
            'heroActions' => $heroActions,
        ])

        @include('me.survey-hub._alerts')

        <div class="survey-panel">
            <div class="survey-panel__header">
                <div>
                    <div class="survey-panel__title">{{ $methodology->name }}</div>
                    <p class="survey-panel__subtitle">Update the questionnaire structure, public intro, and conditional response flow from this dedicated survey editor.</p>
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

                <form method="POST" action="{{ route('budget.me-configuration.methodologies.update', $methodology) }}" class="row g-3"
                    data-force-survey-builder="1">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="is_survey_methodology" value="1">
                    <input type="hidden" name="from_survey_module" value="1">

                    <div class="col-md-6">
                        <label class="form-label">Questionnaire Name <span class="text-danger">*</span></label>
                        <input type="text" name="name" id="methodologyNameInput" class="form-control"
                            value="{{ old('name', $methodology->name) }}"
                            placeholder="e.g., ATTP Post Workshop Feedback" required>
                        <small class="text-muted">The survey builder stays active here even if the name does not contain the word survey.</small>
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
                        <a href="{{ route('budget.me.surveys.questionnaires') }}" class="btn btn-light border">Cancel</a>
                        <button class="btn btn-primary" type="submit">
                            <i class="feather-save me-1"></i> Update Questionnaire
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
