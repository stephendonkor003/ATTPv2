@php
    $forceSurveyBuilder = (bool) ($forceSurveyBuilder ?? false);
@endphp

@once
    @push('styles')
        <style>
            .survey-builder-panel {
                border: 1px solid #bfdbfe;
                border-radius: 18px;
                background:
                    radial-gradient(circle at top right, rgba(59, 130, 246, 0.14), transparent 30%),
                    linear-gradient(180deg, #f8fbff 0%, #eef6ff 100%);
                padding: 1.15rem;
            }

            .survey-builder-hero {
                display: grid;
                gap: 0.35rem;
                margin-bottom: 1rem;
            }

            .survey-badge {
                display: inline-flex;
                align-items: center;
                gap: 0.35rem;
                border-radius: 999px;
                background: rgba(37, 99, 235, 0.12);
                color: #1d4ed8;
                border: 1px solid rgba(37, 99, 235, 0.18);
                padding: 0.35rem 0.7rem;
                font-size: 0.76rem;
                font-weight: 700;
                width: fit-content;
            }

            .survey-help {
                font-size: 0.84rem;
                color: #475569;
                margin: 0;
                max-width: 70ch;
            }

            .survey-section-card {
                border: 1px solid #cbd5e1;
                border-radius: 18px;
                padding: 1rem;
                background: rgba(255, 255, 255, 0.92);
                box-shadow: 0 12px 28px rgba(15, 23, 42, 0.06);
            }

            .survey-question-card {
                border: 1px solid #dbeafe;
                border-radius: 14px;
                padding: 0.9rem;
                background: #ffffff;
                box-shadow: 0 8px 18px rgba(15, 23, 42, 0.05);
            }

            .survey-ghost {
                border: 1px dashed #93c5fd;
                border-radius: 14px;
                padding: 1rem;
                background: rgba(255, 255, 255, 0.78);
                color: #64748b;
                text-align: center;
            }

            .survey-mini-label {
                font-size: 0.72rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: #64748b;
                font-weight: 700;
                margin-bottom: 0.35rem;
            }

            .survey-condition-box {
                border: 1px dashed #bfdbfe;
                border-radius: 14px;
                padding: 0.85rem;
                background: #f8fbff;
            }

            .survey-type-note {
                color: #64748b;
                font-size: 0.78rem;
            }
        </style>
    @endpush
@endonce

<div class="col-12 {{ $forceSurveyBuilder ? '' : 'd-none' }}" id="surveyBuilderPanel">
    <div class="survey-builder-panel">
        <div class="survey-builder-hero">
            <span class="survey-badge"><i class="feather-zap"></i> Survey Engine Enabled</span>
            <div>
                <h6 class="fw-semibold mb-1">Public Survey Builder</h6>
                <p class="survey-help">
                    Build a guided public survey with an intro message, sections, follow-up logic, grid questions,
                    and respondent navigation between previous and next sections.
                </p>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Public Access</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" name="survey_public_enabled"
                        id="surveyPublicEnabled" value="1" {{ $surveyEnabled ? 'checked' : '' }}>
                    <label class="form-check-label" for="surveyPublicEnabled">Enable public survey link</label>
                </div>
            </div>
            <div class="col-md-6">
                <label class="form-label">Survey Title</label>
                <input type="text" name="survey_title" id="surveyTitleInput" class="form-control"
                    value="{{ $surveyTitle }}" placeholder="Post Workshop Survey">
            </div>
            <div class="col-md-3">
                <label class="form-label">Estimated Minutes</label>
                <input type="number" min="1" max="240" name="survey_estimated_minutes" id="surveyEstimatedMinutesInput"
                    class="form-control" value="{{ $surveyEstimatedMinutes }}" placeholder="10">
            </div>
            <div class="col-12">
                <label class="form-label">Intro / Welcome Message</label>
                <textarea name="survey_intro" id="surveyIntroInput" rows="3" class="form-control"
                    placeholder="Thank you for participating. This survey will take approximately 10 minutes...">{{ $surveyIntro }}</textarea>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
            <div>
                <h6 class="fw-semibold mb-1">Survey Sections</h6>
                <div class="survey-type-note">
                    Supported types: text field, long text, number, email, date, date and time, link, dropdown, multi select, single choice, checkbox, slider, scale, file upload, matrix/grid.
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-primary" id="addSurveySectionBtn">
                <i class="feather-plus me-1"></i> Add Section
            </button>
        </div>

        <input type="hidden" name="survey_sections_json" id="surveySectionsJson" value="{{ $initialSurveySections }}">
        <div id="surveySectionsContainer" class="d-grid gap-3"></div>
    </div>
</div>

@once
    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const form = document.querySelector('form[action*="methodologies"]');
                if (!form) {
                    return;
                }

                const nameInput = document.getElementById('methodologyNameInput');
                const surveyPanel = document.getElementById('surveyBuilderPanel');
                const sectionsContainer = document.getElementById('surveySectionsContainer');
                const sectionsJsonInput = document.getElementById('surveySectionsJson');
                const addSectionBtn = document.getElementById('addSurveySectionBtn');
                const surveyTitleInput = document.getElementById('surveyTitleInput');
                const forceSurveyMode = ['1', 'true'].includes((form.dataset.forceSurveyBuilder || '').toLowerCase());

                if (!nameInput || !surveyPanel || !sectionsContainer || !sectionsJsonInput || !addSectionBtn || !surveyTitleInput) {
                    return;
                }

                const questionTypes = [
                    { value: 'text', label: 'Text Field' },
                    { value: 'textarea', label: 'Long Text' },
                    { value: 'number', label: 'Number' },
                    { value: 'email', label: 'Email' },
                    { value: 'date', label: 'Date' },
                    { value: 'datetime', label: 'Date & Time' },
                    { value: 'url', label: 'Link / URL' },
                    { value: 'select', label: 'Dropdown' },
                    { value: 'multiselect', label: 'Multi Select' },
                    { value: 'radio', label: 'Single Choice' },
                    { value: 'checkbox', label: 'Checkboxes' },
                    { value: 'slider', label: 'Slider / Swiper' },
                    { value: 'scale', label: 'Scale (e.g. 1-5)' },
                    { value: 'file', label: 'File Upload' },
                    { value: 'matrix', label: 'Matrix / Grid' },
                ];

                let sections = [];
                try {
                    const parsed = JSON.parse(sectionsJsonInput.value || '[]');
                    if (Array.isArray(parsed)) {
                        sections = normalizeSections(parsed);
                    }
                } catch (error) {
                    sections = [];
                }

                function createKey(prefix) {
                    return `${prefix}_${Date.now().toString(36)}_${Math.random().toString(36).slice(2, 8)}`;
                }

                function escapeHtml(value) {
                    return (value || '')
                        .toString()
                        .replaceAll('&', '&amp;')
                        .replaceAll('<', '&lt;')
                        .replaceAll('>', '&gt;')
                        .replaceAll('"', '&quot;')
                        .replaceAll("'", '&#039;');
                }

                function parseStringList(value, separatorPattern = /[\r\n,]+/) {
                    return (value || '')
                        .toString()
                        .split(separatorPattern)
                        .map((item) => item.trim())
                        .filter((item, index, array) => item !== '' && array.indexOf(item) === index);
                }

                function ensureLabelEntries(list, fallbackPrefix) {
                    return (Array.isArray(list) ? list : [])
                        .map((item, index) => {
                            const label = typeof item === 'object' && item !== null
                                ? (item.label || '').toString().trim()
                                : (item || '').toString().trim();
                            if (!label) {
                                return null;
                            }

                            const rawKey = typeof item === 'object' && item !== null
                                ? (item.key || '').toString().trim()
                                : '';

                            return {
                                key: rawKey || `${fallbackPrefix}_${index + 1}`,
                                label,
                            };
                        })
                        .filter(Boolean);
                }

                function normalizeVisibility(visibility) {
                    const questionKey = (visibility?.question_key || '').toString().trim();
                    const values = Array.isArray(visibility?.values)
                        ? visibility.values.map((item) => (item || '').toString().trim()).filter(Boolean)
                        : [];

                    if (!questionKey || values.length === 0) {
                        return { question_key: '', values: [] };
                    }

                    return {
                        question_key: questionKey,
                        values: values.filter((item, index, array) => array.indexOf(item) === index),
                    };
                }

                function defaultQuestion() {
                    return {
                        key: createKey('question'),
                        label: '',
                        type: 'text',
                        required: true,
                        hint: '',
                        options: [],
                        rows: [],
                        columns: [],
                        scale: {
                            min: 1,
                            max: 5,
                            step: 1,
                            min_label: '',
                            max_label: '',
                        },
                        min_selections: null,
                        max_selections: null,
                        visibility: {
                            question_key: '',
                            values: [],
                        },
                    };
                }

                function defaultSection() {
                    return {
                        key: createKey('section'),
                        title: '',
                        description: '',
                        visibility: {
                            question_key: '',
                            values: [],
                        },
                        questions: [defaultQuestion()],
                    };
                }

                function normalizeQuestion(question) {
                    return {
                        key: (question?.key || '').toString().trim() || createKey('question'),
                        label: (question?.label || '').toString(),
                        type: questionTypes.some((item) => item.value === question?.type) ? question.type : 'text',
                        required: Boolean(question?.required ?? true),
                        hint: (question?.hint || '').toString(),
                        options: Array.isArray(question?.options) ? question.options.map((item) => (item || '').toString().trim()).filter(Boolean) : [],
                        rows: ensureLabelEntries(question?.rows, 'row'),
                        columns: ensureLabelEntries(question?.columns, 'column'),
                        scale: {
                            min: Number.parseInt(question?.scale?.min ?? question?.scale_min ?? 1, 10) || 1,
                            max: Number.parseInt(question?.scale?.max ?? question?.scale_max ?? 5, 10) || 5,
                            step: Number.parseInt(question?.scale?.step ?? question?.scale_step ?? 1, 10) || 1,
                            min_label: (question?.scale?.min_label || question?.scale_min_label || '').toString(),
                            max_label: (question?.scale?.max_label || question?.scale_max_label || '').toString(),
                        },
                        min_selections: question?.min_selections ? Number.parseInt(question.min_selections, 10) : null,
                        max_selections: question?.max_selections ? Number.parseInt(question.max_selections, 10) : null,
                        visibility: normalizeVisibility(question?.visibility || {
                            question_key: question?.depends_on || '',
                            values: question?.show_if || [],
                        }),
                    };
                }

                function normalizeSections(rawSections) {
                    return rawSections
                        .filter((section) => section && typeof section === 'object')
                        .map((section) => ({
                            key: (section.key || '').toString().trim() || createKey('section'),
                            title: (section.title || '').toString(),
                            description: (section.description || section.intro || '').toString(),
                            visibility: normalizeVisibility(section.visibility || {
                                question_key: section.depends_on || '',
                                values: section.show_if || [],
                            }),
                            questions: Array.isArray(section.questions) && section.questions.length
                                ? section.questions.map(normalizeQuestion)
                                : [defaultQuestion()],
                        }));
                }

                function isSurveyMode() {
                    return forceSurveyMode || (nameInput.value || '').toLowerCase().includes('survey');
                }

                function dependencyChoices(targetSectionKey, targetQuestionKey = null) {
                    const choices = [];

                    for (const section of sections) {
                        if (targetQuestionKey === null && section.key === targetSectionKey) {
                            break;
                        }

                        for (const question of section.questions || []) {
                            if (targetQuestionKey !== null && question.key === targetQuestionKey) {
                                return choices;
                            }

                            choices.push({
                                key: question.key,
                                label: question.label || `${section.title || 'Section'} question`,
                                type: question.type,
                                options: answerOptionsForQuestion(question),
                            });
                        }
                    }

                    return choices;
                }

                function answerOptionsForQuestion(question) {
                    if (['select', 'multiselect', 'radio', 'checkbox'].includes(question.type)) {
                        return Array.isArray(question.options) ? question.options : [];
                    }

                    if (question.type === 'scale') {
                        const min = Number.parseInt(question.scale?.min ?? 1, 10) || 1;
                        const max = Number.parseInt(question.scale?.max ?? 5, 10) || 5;
                        const values = [];
                        for (let value = min; value <= max; value += 1) {
                            values.push(String(value));
                        }
                        return values;
                    }

                    if (question.type === 'slider') {
                        const min = Number.parseInt(question.scale?.min ?? 1, 10) || 1;
                        const max = Number.parseInt(question.scale?.max ?? 5, 10) || 5;
                        const step = Number.parseInt(question.scale?.step ?? 1, 10) || 1;
                        const values = [];
                        for (let value = min; value <= max; value += step) {
                            values.push(String(value));
                        }
                        return values;
                    }

                    if (question.type === 'matrix') {
                        return Array.isArray(question.columns)
                            ? question.columns.map((column) => column.label).filter(Boolean)
                            : [];
                    }

                    return [];
                }

                function visibilityMarkup(scope, sectionIndex, questionIndex, visibility, choices) {
                    const selectedChoice = choices.find((item) => item.key === visibility.question_key);
                    const valuesText = Array.isArray(visibility.values) ? visibility.values.join(', ') : '';
                    const availableValues = selectedChoice?.options?.length
                        ? `Available answers: ${selectedChoice.options.join(', ')}`
                        : 'Use exact answer values separated by commas when this depends on free-text or numeric input.';

                    return `
                        <div class="survey-condition-box mt-3">
                            <div class="survey-mini-label">Conditional Display</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Show when this answer is given in</label>
                                    <select class="form-select form-select-sm"
                                        data-condition-scope="${scope}"
                                        data-condition-section="${sectionIndex}"
                                        data-condition-question="${questionIndex ?? ''}"
                                        data-condition-key="question_key">
                                        <option value="">Always show</option>
                                        ${choices.map((choice) => `
                                            <option value="${escapeHtml(choice.key)}" ${choice.key === visibility.question_key ? 'selected' : ''}>
                                                ${escapeHtml(choice.label)}
                                            </option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Matching Answer Value(s)</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-condition-scope="${scope}"
                                        data-condition-section="${sectionIndex}"
                                        data-condition-question="${questionIndex ?? ''}"
                                        data-condition-key="values"
                                        value="${escapeHtml(valuesText)}"
                                        placeholder="Virtual, In-person">
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">${escapeHtml(availableValues)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                }

                function questionMarkup(section, question, sectionIndex, questionIndex) {
                    const choices = dependencyChoices(section.key, question.key);
                    const optionsText = (question.options || []).join('\n');
                    const rowsText = (question.rows || []).map((row) => row.label).join('\n');
                    const columnsText = (question.columns || []).map((column) => column.label).join('\n');
                    const showOptions = ['select', 'multiselect', 'radio', 'checkbox'].includes(question.type);
                    const showScale = ['scale', 'slider'].includes(question.type);
                    const showSliderStep = question.type === 'slider';
                    const showMatrix = question.type === 'matrix';
                    const showCheckboxRules = ['checkbox', 'multiselect'].includes(question.type);

                    return `
                        <div class="survey-question-card">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <strong>Question ${questionIndex + 1}</strong>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-question="up" data-section-index="${sectionIndex}" data-question-index="${questionIndex}" ${questionIndex === 0 ? 'disabled' : ''}>
                                        <i class="feather-arrow-up"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-question="down" data-section-index="${sectionIndex}" data-question-index="${questionIndex}" ${questionIndex === (section.questions.length - 1) ? 'disabled' : ''}>
                                        <i class="feather-arrow-down"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-question="${questionIndex}" data-section-index="${sectionIndex}">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">Question Label</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-question-field="label"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${escapeHtml(question.label || '')}"
                                        placeholder="Enter the question respondents will see">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Type</label>
                                    <select class="form-select form-select-sm"
                                        data-question-field="type"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}">
                                        ${questionTypes.map((item) => `
                                            <option value="${item.value}" ${item.value === question.type ? 'selected' : ''}>${item.label}</option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label d-block">Required</label>
                                    <div class="form-check form-switch mt-1">
                                        <input class="form-check-input"
                                            type="checkbox"
                                            data-question-field="required"
                                            data-section-index="${sectionIndex}"
                                            data-question-index="${questionIndex}"
                                            ${question.required ? 'checked' : ''}>
                                        <label class="form-check-label">Mandatory</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Help Text</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-question-field="hint"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${escapeHtml(question.hint || '')}"
                                        placeholder="Optional explanation shown below the question">
                                </div>

                                <div class="col-12 ${showOptions ? '' : 'd-none'}" data-question-options-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Options (one per line)</label>
                                    <textarea class="form-control form-control-sm" rows="3"
                                        data-question-field="options"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        placeholder="Option 1&#10;Option 2&#10;Option 3">${escapeHtml(optionsText)}</textarea>
                                </div>

                                <div class="col-md-6 ${showCheckboxRules ? '' : 'd-none'}" data-question-checkbox-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Minimum Selections</label>
                                    <input type="number" min="1" class="form-control form-control-sm"
                                        data-question-field="min_selections"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${question.min_selections ?? ''}"
                                        placeholder="Optional">
                                </div>
                                <div class="col-md-6 ${showCheckboxRules ? '' : 'd-none'}" data-question-checkbox-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Maximum Selections</label>
                                    <input type="number" min="1" class="form-control form-control-sm"
                                        data-question-field="max_selections"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${question.max_selections ?? ''}"
                                        placeholder="Optional">
                                </div>

                                <div class="col-md-3 ${showScale ? '' : 'd-none'}" data-question-scale-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Scale Min</label>
                                    <input type="number" min="1" class="form-control form-control-sm"
                                        data-question-field="scale_min"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${question.scale?.min ?? 1}">
                                </div>
                                <div class="col-md-3 ${showScale ? '' : 'd-none'}" data-question-scale-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Scale Max</label>
                                    <input type="number" min="2" class="form-control form-control-sm"
                                        data-question-field="scale_max"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${question.scale?.max ?? 5}">
                                </div>
                                <div class="col-md-2 ${showSliderStep ? '' : 'd-none'}" data-question-scale-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Step</label>
                                    <input type="number" min="1" class="form-control form-control-sm"
                                        data-question-field="scale_step"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${question.scale?.step ?? 1}">
                                </div>
                                <div class="col-md-2 ${showScale ? '' : 'd-none'}" data-question-scale-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Min Label</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-question-field="scale_min_label"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${escapeHtml(question.scale?.min_label || '')}"
                                        placeholder="Poor">
                                </div>
                                <div class="col-md-2 ${showScale ? '' : 'd-none'}" data-question-scale-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Max Label</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-question-field="scale_max_label"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${escapeHtml(question.scale?.max_label || '')}"
                                        placeholder="Excellent">
                                </div>

                                <div class="col-md-6 ${showMatrix ? '' : 'd-none'}" data-question-matrix-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Rows (one per line)</label>
                                    <textarea class="form-control form-control-sm" rows="4"
                                        data-question-field="rows"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        placeholder="Venue comfort&#10;Audio quality">${escapeHtml(rowsText)}</textarea>
                                </div>
                                <div class="col-md-6 ${showMatrix ? '' : 'd-none'}" data-question-matrix-wrap="${sectionIndex}_${questionIndex}">
                                    <label class="form-label">Columns (one per line)</label>
                                    <textarea class="form-control form-control-sm" rows="4"
                                        data-question-field="columns"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        placeholder="Poor&#10;Fair&#10;Good&#10;Excellent">${escapeHtml(columnsText)}</textarea>
                                </div>
                            </div>

                            ${visibilityMarkup('question', sectionIndex, questionIndex, question.visibility || { question_key: '', values: [] }, choices)}
                        </div>
                    `;
                }

                function renderSections() {
                    sectionsContainer.innerHTML = '';

                    if (!sections.length) {
                        sectionsContainer.innerHTML = `
                            <div class="survey-ghost">
                                No sections yet. Add a section to start designing the survey flow.
                            </div>
                        `;
                        return;
                    }

                    sections.forEach((section, sectionIndex) => {
                        const card = document.createElement('div');
                        card.className = 'survey-section-card';
                        const sectionChoices = dependencyChoices(section.key);

                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <div class="survey-mini-label">Section ${sectionIndex + 1}</div>
                                    <h6 class="fw-semibold mb-0">${escapeHtml(section.title || `Section ${sectionIndex + 1}`)}</h6>
                                </div>
                                <div class="d-flex gap-1">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-section="up" data-section-index="${sectionIndex}" ${sectionIndex === 0 ? 'disabled' : ''}>
                                        <i class="feather-arrow-up"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-move-section="down" data-section-index="${sectionIndex}" ${sectionIndex === (sections.length - 1) ? 'disabled' : ''}>
                                        <i class="feather-arrow-down"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-section="${sectionIndex}">
                                        <i class="feather-trash-2"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Section Title</label>
                                    <input type="text" class="form-control"
                                        data-section-field="title"
                                        data-section-index="${sectionIndex}"
                                        value="${escapeHtml(section.title || '')}"
                                        placeholder="Section 1: Participant Information">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Section Description</label>
                                    <textarea class="form-control" rows="2"
                                        data-section-field="description"
                                        data-section-index="${sectionIndex}"
                                        placeholder="Short guidance shown at the top of this section">${escapeHtml(section.description || '')}</textarea>
                                </div>
                            </div>

                            ${visibilityMarkup('section', sectionIndex, null, section.visibility || { question_key: '', values: [] }, sectionChoices)}

                            <div class="d-flex justify-content-between align-items-center mt-3 mb-2">
                                <div>
                                    <h6 class="fw-semibold mb-1">Questions</h6>
                                    <div class="survey-type-note">Each public screen shows one section, with back and next navigation.</div>
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-primary" data-add-question="${sectionIndex}">
                                    <i class="feather-plus me-1"></i> Add Question
                                </button>
                            </div>

                            <div class="d-grid gap-2" data-section-questions="${sectionIndex}">
                                ${(section.questions || []).map((question, questionIndex) => questionMarkup(section, question, sectionIndex, questionIndex)).join('')}
                            </div>
                        `;

                        sectionsContainer.appendChild(card);
                    });
                }

                function syncSectionsFromDom() {
                    sections = sections.map((section, sectionIndex) => {
                        const title = form.querySelector(`[data-section-field="title"][data-section-index="${sectionIndex}"]`)?.value || '';
                        const description = form.querySelector(`[data-section-field="description"][data-section-index="${sectionIndex}"]`)?.value || '';
                        const sectionConditionQuestion = form.querySelector(`[data-condition-scope="section"][data-condition-section="${sectionIndex}"][data-condition-key="question_key"]`)?.value || '';
                        const sectionConditionValues = form.querySelector(`[data-condition-scope="section"][data-condition-section="${sectionIndex}"][data-condition-key="values"]`)?.value || '';

                        const questions = (section.questions || []).map((question, questionIndex) => {
                            const type = form.querySelector(`[data-question-field="type"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || 'text';
                            const scaleMin = Number.parseInt(form.querySelector(`[data-question-field="scale_min"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '1', 10) || 1;
                            const scaleMax = Number.parseInt(form.querySelector(`[data-question-field="scale_max"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '5', 10) || 5;
                            const scaleStep = Number.parseInt(form.querySelector(`[data-question-field="scale_step"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '1', 10) || 1;

                            return {
                                key: question.key || createKey('question'),
                                label: form.querySelector(`[data-question-field="label"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '',
                                type,
                                required: Boolean(form.querySelector(`[data-question-field="required"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.checked),
                                hint: form.querySelector(`[data-question-field="hint"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '',
                                options: ['select', 'multiselect', 'radio', 'checkbox'].includes(type)
                                    ? parseStringList(form.querySelector(`[data-question-field="options"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '', /[\r\n]+/)
                                    : [],
                                rows: type === 'matrix'
                                    ? parseStringList(form.querySelector(`[data-question-field="rows"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '', /[\r\n]+/).map((label, index) => ({
                                        key: question.rows?.[index]?.key || createKey('row'),
                                        label,
                                    }))
                                    : [],
                                columns: type === 'matrix'
                                    ? parseStringList(form.querySelector(`[data-question-field="columns"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '', /[\r\n]+/).map((label, index) => ({
                                        key: question.columns?.[index]?.key || createKey('column'),
                                        label,
                                    }))
                                    : [],
                                scale: ['scale', 'slider'].includes(type)
                                    ? {
                                        min: Math.max(1, Math.min(scaleMin, scaleMax)),
                                        max: Math.max(scaleMin, scaleMax),
                                        step: Math.max(1, scaleStep),
                                        min_label: form.querySelector(`[data-question-field="scale_min_label"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '',
                                        max_label: form.querySelector(`[data-question-field="scale_max_label"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '',
                                    }
                                    : {
                                        min: 1,
                                        max: 5,
                                        step: 1,
                                        min_label: '',
                                        max_label: '',
                                    },
                                min_selections: ['checkbox', 'multiselect'].includes(type)
                                    ? (form.querySelector(`[data-question-field="min_selections"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '').trim() || null
                                    : null,
                                max_selections: ['checkbox', 'multiselect'].includes(type)
                                    ? (form.querySelector(`[data-question-field="max_selections"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '').trim() || null
                                    : null,
                                visibility: normalizeVisibility({
                                    question_key: form.querySelector(`[data-condition-scope="question"][data-condition-section="${sectionIndex}"][data-condition-question="${questionIndex}"][data-condition-key="question_key"]`)?.value || '',
                                    values: parseStringList(form.querySelector(`[data-condition-scope="question"][data-condition-section="${sectionIndex}"][data-condition-question="${questionIndex}"][data-condition-key="values"]`)?.value || ''),
                                }),
                            };
                        });

                        return {
                            key: section.key || createKey('section'),
                            title: title.trim(),
                            description: description.trim(),
                            visibility: normalizeVisibility({
                                question_key: sectionConditionQuestion,
                                values: parseStringList(sectionConditionValues),
                            }),
                            questions,
                        };
                    });

                    sectionsJsonInput.value = JSON.stringify(sections);
                }

                function moveItem(list, fromIndex, direction) {
                    const toIndex = direction === 'up' ? fromIndex - 1 : fromIndex + 1;
                    if (toIndex < 0 || toIndex >= list.length) {
                        return list;
                    }

                    const clone = [...list];
                    [clone[fromIndex], clone[toIndex]] = [clone[toIndex], clone[fromIndex]];
                    return clone;
                }

                function applySurveyMode() {
                    const surveyMode = isSurveyMode();
                    surveyPanel.classList.toggle('d-none', !surveyMode);

                    if (surveyMode && !surveyTitleInput.value.trim()) {
                        surveyTitleInput.value = `${nameInput.value.trim()} Public Survey`.trim();
                    }

                    if (!surveyMode) {
                        sectionsJsonInput.value = '[]';
                        return;
                    }

                    if (sections.length === 0) {
                        sections = [defaultSection()];
                    }

                    renderSections();
                    syncSectionsFromDom();
                }

                addSectionBtn.addEventListener('click', () => {
                    sections.push(defaultSection());
                    renderSections();
                    syncSectionsFromDom();
                });

                sectionsContainer.addEventListener('click', (event) => {
                    const removeSectionTrigger = event.target.closest('[data-remove-section]');
                    if (removeSectionTrigger) {
                        sections.splice(Number(removeSectionTrigger.getAttribute('data-remove-section')), 1);
                        renderSections();
                        syncSectionsFromDom();
                        return;
                    }

                    const moveSectionTrigger = event.target.closest('[data-move-section]');
                    if (moveSectionTrigger) {
                        const index = Number(moveSectionTrigger.getAttribute('data-section-index'));
                        sections = moveItem(sections, index, moveSectionTrigger.getAttribute('data-move-section'));
                        renderSections();
                        syncSectionsFromDom();
                        return;
                    }

                    const addQuestionTrigger = event.target.closest('[data-add-question]');
                    if (addQuestionTrigger) {
                        const sectionIndex = Number(addQuestionTrigger.getAttribute('data-add-question'));
                        sections[sectionIndex].questions.push(defaultQuestion());
                        renderSections();
                        syncSectionsFromDom();
                        return;
                    }

                    const removeQuestionTrigger = event.target.closest('[data-remove-question]');
                    if (removeQuestionTrigger) {
                        const sectionIndex = Number(removeQuestionTrigger.getAttribute('data-section-index'));
                        const questionIndex = Number(removeQuestionTrigger.getAttribute('data-remove-question'));
                        sections[sectionIndex].questions.splice(questionIndex, 1);
                        if (sections[sectionIndex].questions.length === 0) {
                            sections[sectionIndex].questions.push(defaultQuestion());
                        }
                        renderSections();
                        syncSectionsFromDom();
                        return;
                    }

                    const moveQuestionTrigger = event.target.closest('[data-move-question]');
                    if (moveQuestionTrigger) {
                        const sectionIndex = Number(moveQuestionTrigger.getAttribute('data-section-index'));
                        const questionIndex = Number(moveQuestionTrigger.getAttribute('data-question-index'));
                        sections[sectionIndex].questions = moveItem(
                            sections[sectionIndex].questions,
                            questionIndex,
                            moveQuestionTrigger.getAttribute('data-move-question')
                        );
                        renderSections();
                        syncSectionsFromDom();
                    }
                });

                sectionsContainer.addEventListener('input', (event) => {
                    if (!event.target) {
                        return;
                    }

                    syncSectionsFromDom();
                });

                sectionsContainer.addEventListener('change', (event) => {
                    const target = event.target;
                    if (!target) {
                        return;
                    }

                    syncSectionsFromDom();

                    if (
                        target.matches('[data-question-field="type"]')
                        || target.matches('[data-condition-key="question_key"]')
                    ) {
                        renderSections();
                        syncSectionsFromDom();
                    }
                });

                nameInput.addEventListener('input', applySurveyMode);
                form.addEventListener('submit', () => {
                    syncSectionsFromDom();
                });

                if (sections.length === 0 && isSurveyMode()) {
                    sections = [defaultSection()];
                }

                renderSections();
                applySurveyMode();
            });
        </script>
    @endpush
@endonce
