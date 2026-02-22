@extends('layouts.app')
@section('title','Create Methodology')

@push('styles')
<style>
    .survey-builder-panel {
        border: 1px solid #bfdbfe;
        border-radius: 14px;
        background: linear-gradient(180deg, #f8fbff 0%, #eff6ff 100%);
        padding: 1rem;
    }

    .survey-question-card {
        border: 1px solid #dbeafe;
        border-radius: 12px;
        padding: 0.9rem;
        background: #ffffff;
        box-shadow: 0 8px 18px rgba(15, 23, 42, 0.06);
    }

    .survey-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.35rem;
        border-radius: 999px;
        background: #dbeafe;
        color: #1e3a8a;
        border: 1px solid #93c5fd;
        padding: 0.28rem 0.62rem;
        font-size: 0.76rem;
        font-weight: 700;
    }

    .survey-help {
        font-size: 0.82rem;
        color: #475569;
    }
</style>
@endpush

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-book-open text-primary me-2"></i>Create Methodology</h4>
            <p class="text-muted mb-0">Define a reusable measurement approach.</p>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('budget.me-configuration.methodologies.store') }}" class="row g-3">
                @csrf
                <div class="col-md-6">
                    <label class="form-label">Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="methodologyNameInput" class="form-control"
                        value="{{ old('name') }}" placeholder="e.g., Survey, Field Observation, Desk Review" required>
                    <small class="text-muted">If the name includes <strong>survey</strong>, survey builder options
                        appear automatically.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Active</label>
                    <div class="form-check form-switch mt-2">
                        <input class="form-check-input" type="checkbox" name="is_active" value="1" {{ old('is_active', true) ? 'checked' : '' }}>
                        <label class="form-check-label">Enabled</label>
                    </div>
                </div>
                <div class="col-12">
                    <label class="form-label">Description</label>
                    <textarea name="description" rows="2" class="form-control">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label">Steps / Guidance</label>
                    <textarea name="steps" rows="5" class="form-control" placeholder="E.g., data sources, calculation notes">{{ old('steps') }}</textarea>
                </div>

                <div class="col-12 d-none" id="surveyBuilderPanel">
                    <div class="survey-builder-panel">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                            <div>
                                <span class="survey-badge"><i class="feather-zap"></i> Survey Engine Enabled</span>
                                <h6 class="fw-semibold mt-2 mb-1">Public Survey Builder</h6>
                                <p class="survey-help mb-0">
                                    Configure the public survey that will be used when an indicator selects this methodology.
                                    The system will generate shareable links per indicator and store responses with responsible
                                    party snapshots.
                                </p>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Public Access</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" name="survey_public_enabled"
                                        id="surveyPublicEnabled" value="1"
                                        {{ old('survey_public_enabled', true) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="surveyPublicEnabled">Enable public form access</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Public Survey Title</label>
                                <input type="text" name="survey_title" id="surveyTitleInput" class="form-control"
                                    value="{{ old('survey_title') }}" placeholder="Public survey title">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Intro / Instructions</label>
                                <textarea name="survey_intro" id="surveyIntroInput" rows="2" class="form-control"
                                    placeholder="Write simple instructions for external respondents.">{{ old('survey_intro') }}</textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-semibold mb-0">Survey Questions</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addSurveyQuestionBtn">
                                <i class="feather-plus me-1"></i> Add Question
                            </button>
                        </div>

                        <input type="hidden" name="survey_questions_json" id="surveyQuestionsJson"
                            value="{{ old('survey_questions_json', '[]') }}">
                        <div id="surveyQuestionsContainer" class="d-grid gap-2"></div>
                        <small class="text-muted d-block mt-2">Supported field types: text, textarea, number, email, date,
                            select, radio, checkbox.</small>
                    </div>
                </div>

                <div class="col-12 d-flex justify-content-end gap-2">
                    <a href="{{ route('budget.me-configuration.methodologies.index') }}" class="btn btn-light border">Cancel</a>
                    <button class="btn btn-primary" type="submit"><i class="feather-save me-1"></i> Save</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('form[action*="methodologies"]');
    if (!form) {
        return;
    }

    const nameInput = document.getElementById('methodologyNameInput');
    const surveyPanel = document.getElementById('surveyBuilderPanel');
    const questionsContainer = document.getElementById('surveyQuestionsContainer');
    const questionsJsonInput = document.getElementById('surveyQuestionsJson');
    const addQuestionBtn = document.getElementById('addSurveyQuestionBtn');
    const surveyTitleInput = document.getElementById('surveyTitleInput');
    const surveyIntroInput = document.getElementById('surveyIntroInput');

    if (!nameInput || !surveyPanel || !questionsContainer || !questionsJsonInput || !addQuestionBtn) {
        return;
    }

    const questionTypes = [
        {value: 'text', label: 'Text'},
        {value: 'textarea', label: 'Long Text'},
        {value: 'number', label: 'Number'},
        {value: 'email', label: 'Email'},
        {value: 'date', label: 'Date'},
        {value: 'select', label: 'Dropdown'},
        {value: 'radio', label: 'Single Choice'},
        {value: 'checkbox', label: 'Multi Choice'},
    ];

    let questions = [];
    try {
        const parsed = JSON.parse(questionsJsonInput.value || '[]');
        if (Array.isArray(parsed)) {
            questions = parsed;
        }
    } catch (error) {
        questions = [];
    }

    const isSurveyName = () => (nameInput.value || '').toLowerCase().includes('survey');

    function defaultQuestion() {
        return {
            label: '',
            type: 'text',
            required: true,
            hint: '',
            options: [],
        };
    }

    function normalizeOptions(raw) {
        return (raw || [])
            .map((item) => (item || '').toString().trim())
            .filter((item, index, array) => item !== '' && array.indexOf(item) === index);
    }

    function renderQuestions() {
        questionsContainer.innerHTML = '';

        if (!questions.length) {
            const empty = document.createElement('div');
            empty.className = 'text-muted small border rounded p-3 bg-white';
            empty.textContent = 'No questions yet. Add at least one question for survey methodology.';
            questionsContainer.appendChild(empty);
            return;
        }

        questions.forEach((question, index) => {
            const card = document.createElement('div');
            card.className = 'survey-question-card';

            const optionBlockVisible = ['select', 'radio', 'checkbox'].includes(question.type);
            const optionsText = (question.options || []).join('\n');

            card.innerHTML = `
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Question ${index + 1}</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove="${index}">
                        <i class="feather-trash-2"></i>
                    </button>
                </div>
                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label">Question Label</label>
                        <input type="text" class="form-control form-control-sm" data-label="${index}" value="${escapeHtml(question.label || '')}" placeholder="Type the question shown to respondents">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Input Type</label>
                        <select class="form-select form-select-sm" data-type="${index}">
                            ${questionTypes.map((type) => `<option value="${type.value}" ${type.value === question.type ? 'selected' : ''}>${type.label}</option>`).join('')}
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label d-block">Required</label>
                        <div class="form-check form-switch mt-1">
                            <input class="form-check-input" type="checkbox" data-required="${index}" ${question.required ? 'checked' : ''}>
                            <label class="form-check-label">Mandatory</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Help Text (Optional)</label>
                        <input type="text" class="form-control form-control-sm" data-hint="${index}" value="${escapeHtml(question.hint || '')}" placeholder="Optional helper text under the question">
                    </div>
                    <div class="col-12 ${optionBlockVisible ? '' : 'd-none'}" data-options-wrap="${index}">
                        <label class="form-label">Options (one per line)</label>
                        <textarea class="form-control form-control-sm" rows="3" data-options="${index}" placeholder="Option 1&#10;Option 2&#10;Option 3">${escapeHtml(optionsText)}</textarea>
                    </div>
                </div>
            `;

            questionsContainer.appendChild(card);
        });
    }

    function syncQuestionsFromDom() {
        questions = questions.map((question, index) => {
            const label = form.querySelector(`[data-label="${index}"]`)?.value || '';
            const type = form.querySelector(`[data-type="${index}"]`)?.value || 'text';
            const required = !!form.querySelector(`[data-required="${index}"]`)?.checked;
            const hint = form.querySelector(`[data-hint="${index}"]`)?.value || '';
            const optionsRaw = (form.querySelector(`[data-options="${index}"]`)?.value || '').split('\n');

            return {
                label: label.trim(),
                type: type.trim(),
                required: required,
                hint: hint.trim(),
                options: ['select', 'radio', 'checkbox'].includes(type) ? normalizeOptions(optionsRaw) : [],
            };
        });

        questionsJsonInput.value = JSON.stringify(questions);
    }

    function applySurveyMode() {
        const surveyMode = isSurveyName();
        surveyPanel.classList.toggle('d-none', !surveyMode);

        if (surveyMode && !surveyTitleInput.value.trim()) {
            surveyTitleInput.value = `${nameInput.value.trim()} Public Survey`.trim();
        }

        if (!surveyMode) {
            questionsJsonInput.value = '[]';
        } else {
            syncQuestionsFromDom();
        }
    }

    function escapeHtml(value) {
        return (value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    addQuestionBtn.addEventListener('click', () => {
        questions.push(defaultQuestion());
        renderQuestions();
        syncQuestionsFromDom();
    });

    questionsContainer.addEventListener('input', (event) => {
        const target = event.target;
        if (!target) {
            return;
        }

        if (target.matches('[data-type]')) {
            const index = Number(target.getAttribute('data-type'));
            const optionsWrap = questionsContainer.querySelector(`[data-options-wrap="${index}"]`);
            if (optionsWrap) {
                optionsWrap.classList.toggle('d-none', !['select', 'radio', 'checkbox'].includes(target.value));
            }
        }

        syncQuestionsFromDom();
    });

    questionsContainer.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-remove]');
        if (!trigger) {
            return;
        }
        const index = Number(trigger.getAttribute('data-remove'));
        questions.splice(index, 1);
        renderQuestions();
        syncQuestionsFromDom();
    });

    nameInput.addEventListener('input', applySurveyMode);
    form.addEventListener('submit', () => {
        syncQuestionsFromDom();
    });

    if (questions.length === 0 && isSurveyName()) {
        questions.push(defaultQuestion());
    }

    renderQuestions();
    applySurveyMode();
});
</script>
@endpush
