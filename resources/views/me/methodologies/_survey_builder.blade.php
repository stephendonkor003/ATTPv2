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
                --section-color: #2563eb;
                border: 1px solid #cbd5e1;
                border-top: 4px solid var(--section-color);
                border-radius: 18px;
                padding: 1rem;
                background:
                    linear-gradient(180deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.92));
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

            .survey-section-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                border-radius: 999px;
                padding: 0.35rem 0.7rem;
                font-size: 0.76rem;
                font-weight: 700;
                background: rgba(15, 23, 42, 0.04);
                color: #334155;
            }

            .survey-section-chip__swatch {
                width: 0.8rem;
                height: 0.8rem;
                border-radius: 999px;
                background: var(--section-color);
                box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.55);
            }

            .survey-logic-summary {
                border-radius: 12px;
                background: rgba(15, 23, 42, 0.04);
                color: #475569;
                padding: 0.7rem 0.8rem;
                font-size: 0.78rem;
            }

            .survey-type-note {
                color: #64748b;
                font-size: 0.78rem;
            }

            .survey-builder-nav {
                display: flex;
                gap: 0.65rem;
                overflow-x: auto;
                padding-bottom: 0.2rem;
                margin-bottom: 1rem;
                scrollbar-width: thin;
            }

            .survey-builder-nav:empty {
                display: none;
            }

            .survey-nav-chip {
                display: inline-flex;
                align-items: center;
                gap: 0.55rem;
                border-radius: 999px;
                border: 1px solid rgba(59, 130, 246, 0.18);
                background: rgba(255, 255, 255, 0.86);
                color: #1e3a5f;
                padding: 0.5rem 0.8rem;
                text-decoration: none;
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.05);
                white-space: nowrap;
            }

            .survey-nav-chip:hover {
                color: #1d4ed8;
                border-color: rgba(37, 99, 235, 0.3);
                transform: translateY(-1px);
            }

            .survey-nav-chip__index {
                width: 1.6rem;
                height: 1.6rem;
                border-radius: 999px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                background: rgba(37, 99, 235, 0.12);
                font-size: 0.76rem;
                font-weight: 700;
                color: #1d4ed8;
            }

            .survey-nav-chip__body {
                display: grid;
                gap: 0.1rem;
            }

            .survey-nav-chip__body strong {
                font-size: 0.82rem;
                font-weight: 700;
            }

            .survey-nav-chip__body span {
                font-size: 0.72rem;
                color: #64748b;
            }

            .survey-section-card {
                position: relative;
                scroll-margin-top: 7rem;
            }

            .survey-section-footer {
                position: sticky;
                bottom: 1rem;
                z-index: 4;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 0.8rem;
                margin-top: 1rem;
                padding: 0.8rem 0.9rem;
                border: 1px solid rgba(15, 23, 42, 0.08);
                border-radius: 16px;
                background: rgba(255, 255, 255, 0.94);
                backdrop-filter: blur(14px);
                box-shadow: 0 18px 34px rgba(15, 23, 42, 0.1);
            }

            .survey-builder-dock {
                position: sticky;
                bottom: 1rem;
                z-index: 5;
                display: flex;
                justify-content: flex-end;
                margin-top: 1rem;
                pointer-events: none;
            }

            .survey-builder-dock__inner {
                pointer-events: auto;
                display: inline-flex;
                align-items: center;
                gap: 0.8rem;
                border-radius: 999px;
                border: 1px solid rgba(15, 23, 42, 0.08);
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(14px);
                padding: 0.55rem 0.7rem 0.55rem 1rem;
                box-shadow: 0 18px 36px rgba(15, 23, 42, 0.12);
            }

            .survey-builder-dock__inner .survey-type-note {
                margin: 0;
            }

            @media (max-width: 767.98px) {
                .survey-section-footer {
                    flex-direction: column;
                    align-items: stretch;
                }

                .survey-builder-dock {
                    justify-content: stretch;
                }

                .survey-builder-dock__inner {
                    width: 100%;
                    border-radius: 18px;
                    justify-content: space-between;
                    flex-wrap: wrap;
                }
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
        <div class="survey-builder-nav" id="surveyBuilderNav"></div>
        <div id="surveySectionsContainer" class="d-grid gap-3"></div>
        <div class="survey-builder-dock">
            <div class="survey-builder-dock__inner">
                <p class="survey-type-note">Long questionnaire? Add a new section from anywhere.</p>
                <button type="button" class="btn btn-sm btn-primary" id="addSurveySectionDockBtn">
                    <i class="feather-plus me-1"></i> Add Section
                </button>
            </div>
        </div>
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
                const builderNav = document.getElementById('surveyBuilderNav');
                const addSectionBtn = document.getElementById('addSurveySectionBtn');
                const addSectionDockBtn = document.getElementById('addSurveySectionDockBtn');
                const surveyTitleInput = document.getElementById('surveyTitleInput');
                const forceSurveyMode = ['1', 'true'].includes((form.dataset.forceSurveyBuilder || '').toLowerCase());

                if (!nameInput || !surveyPanel || !sectionsContainer || !sectionsJsonInput || !builderNav || !addSectionBtn || !addSectionDockBtn || !surveyTitleInput) {
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

                const sectionColorPalette = [
                    '#143E5A',
                    '#8C4B2F',
                    '#1E6B57',
                    '#6B4FA1',
                    '#A23E52',
                    '#0F766E',
                    '#9A6700',
                    '#3B5B92',
                ];

                let sections = [];
                let routeTargets = {
                    sectionKeys: new Set(),
                    questionKeys: new Set(),
                };
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

                function normalizeHexColor(value, fallback) {
                    const candidate = (value || '').toString().trim();
                    const match = candidate.match(/^#?([a-fA-F0-9]{6})$/);
                    if (!match) {
                        return fallback;
                    }

                    return `#${match[1].toUpperCase()}`;
                }

                function defaultSectionColor(index) {
                    return sectionColorPalette[index % sectionColorPalette.length];
                }

                function hexToRgba(hex, alpha) {
                    const normalized = normalizeHexColor(hex, '#2563EB').replace('#', '');
                    const red = Number.parseInt(normalized.slice(0, 2), 16);
                    const green = Number.parseInt(normalized.slice(2, 4), 16);
                    const blue = Number.parseInt(normalized.slice(4, 6), 16);
                    return `rgba(${red}, ${green}, ${blue}, ${alpha})`;
                }

                function logicSummary(scope, visibility, choices) {
                    const scopeLabel = scope === 'section' ? 'section' : 'follow-up question';
                    const selectedChoice = choices.find((item) => item.key === visibility.question_key);
                    const targetLabel = selectedChoice?.label || 'the selected indicator question';
                    const values = Array.isArray(visibility.values) ? visibility.values.filter(Boolean) : [];

                    if (!visibility.question_key || values.length === 0) {
                        return `This ${scopeLabel} is always shown. No skip rule is active.`;
                    }

                    return `Show this ${scopeLabel} only when "${targetLabel}" has one of these answers: ${values.join(', ')}. Otherwise it is skipped automatically.`;
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
                        flow_type: 'normal',
                        required: true,
                        hint: '',
                        options: [],
                        rows: [],
                        columns: [],
                        scale: {
                            min: 1,
                            max: 5,
                            step: 1,
                            labels: {},
                            min_label: '',
                            max_label: '',
                        },
                        min_selections: null,
                        max_selections: null,
                        visibility: {
                            question_key: '',
                            values: [],
                        },
                        route: {
                            target_type: '',
                            target_key: '',
                            values: [],
                        },
                    };
                }

                function defaultSection(sectionIndex = sections.length) {
                    return {
                        key: createKey('section'),
                        title: '',
                        description: '',
                        color: defaultSectionColor(sectionIndex),
                        flow_type: 'normal',
                        visibility: {
                            question_key: '',
                            values: [],
                        },
                        questions: [defaultQuestion()],
                    };
                }

                function normalizeFlowType(value) {
                    return (value || '').toString().trim().toLowerCase() === 'special' ? 'special' : 'normal';
                }

                function normalizeRoute(route) {
                    const targetType = (route?.target_type || route?.type || '').toString().trim();
                    const targetKey = (route?.target_key || route?.key || '').toString().trim();
                    const values = Array.isArray(route?.values)
                        ? route.values.map((item) => (item || '').toString().trim()).filter(Boolean)
                        : [];
                    const normalizedType = ['section', 'question'].includes(targetType) ? targetType : '';

                    return {
                        target_type: normalizedType,
                        target_key: normalizedType ? targetKey : '',
                        values: values.filter((item, index, array) => array.indexOf(item) === index),
                    };
                }

                function normalizeQuestion(question) {
                    const scaleMin = Number.parseInt(question?.scale?.min ?? question?.scale_min ?? 1, 10) || 1;
                    const scaleMax = Number.parseInt(question?.scale?.max ?? question?.scale_max ?? 5, 10) || 5;
                    const scaleStep = Number.parseInt(question?.scale?.step ?? question?.scale_step ?? 1, 10) || 1;
                    const normalizedMin = Math.min(scaleMin, scaleMax);
                    const normalizedMax = Math.max(scaleMin, scaleMax);
                    const legacyMinLabel = (question?.scale?.min_label || question?.scale_min_label || '').toString();
                    const legacyMaxLabel = (question?.scale?.max_label || question?.scale_max_label || '').toString();

                    return {
                        key: (question?.key || '').toString().trim() || createKey('question'),
                        label: (question?.label || '').toString(),
                        type: questionTypes.some((item) => item.value === question?.type) ? question.type : 'text',
                        flow_type: normalizeFlowType(question?.flow_type || question?.flow),
                        required: Boolean(question?.required ?? true),
                        hint: (question?.hint || '').toString(),
                        options: Array.isArray(question?.options) ? question.options.map((item) => (item || '').toString().trim()).filter(Boolean) : [],
                        rows: ensureLabelEntries(question?.rows, 'row'),
                        columns: ensureLabelEntries(question?.columns, 'column'),
                        scale: {
                            min: normalizedMin,
                            max: normalizedMax,
                            step: scaleStep,
                            labels: normalizeScaleLabels(
                                question?.scale?.labels ?? question?.scale_labels ?? {},
                                normalizedMin,
                                normalizedMax,
                                question?.type === 'slider' ? scaleStep : 1,
                                legacyMinLabel,
                                legacyMaxLabel
                            ),
                            min_label: legacyMinLabel,
                            max_label: legacyMaxLabel,
                        },
                        min_selections: question?.min_selections ? Number.parseInt(question.min_selections, 10) : null,
                        max_selections: question?.max_selections ? Number.parseInt(question.max_selections, 10) : null,
                        visibility: normalizeVisibility(question?.visibility || {
                            question_key: question?.depends_on || '',
                            values: question?.show_if || [],
                        }),
                        route: normalizeRoute(question?.route || question?.jump || {
                            target_type: question?.route_target_type || '',
                            target_key: question?.route_target_key || '',
                            values: question?.route_values || [],
                        }),
                    };
                }

                function normalizeSections(rawSections) {
                    return rawSections
                        .filter((section) => section && typeof section === 'object')
                        .map((section, sectionIndex) => ({
                            key: (section.key || '').toString().trim() || createKey('section'),
                            title: (section.title || '').toString(),
                            description: (section.description || section.intro || '').toString(),
                            color: normalizeHexColor(section.color || section.section_color || '', defaultSectionColor(sectionIndex)),
                            flow_type: normalizeFlowType(section.flow_type || section.flow),
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

                function specialTargetChoices(currentQuestionKey = '', currentSectionKey = '') {
                    const choices = [];

                    sections.forEach((section) => {
                        if (section.key !== currentSectionKey) {
                            choices.push({
                                type: 'section',
                                key: section.key,
                                label: `Section: ${section.title || 'Untitled section'}`,
                            });
                        }

                        (section.questions || []).forEach((question) => {
                            if (question.key === currentQuestionKey) {
                                return;
                            }

                            choices.push({
                                type: 'question',
                                key: question.key,
                                label: `Question: ${question.label || 'Untitled question'} (${section.title || 'Section'})`,
                            });
                        });
                    });

                    return choices;
                }

                function buildRouteTargetState() {
                    const sectionKeys = new Set();
                    const questionKeys = new Set();

                    sections.forEach((section) => {
                        (section.questions || []).forEach((question) => {
                            const route = normalizeRoute(question.route || {});
                            if (route.target_type === 'section' && route.target_key) {
                                sectionKeys.add(route.target_key);
                            }

                            if (route.target_type === 'question' && route.target_key) {
                                questionKeys.add(route.target_key);
                            }
                        });
                    });

                    return { sectionKeys, questionKeys };
                }

                function refreshRouteTargets() {
                    routeTargets = buildRouteTargetState();
                }

                function renderSectionNavigator() {
                    if (!sections.length) {
                        builderNav.innerHTML = '';
                        return;
                    }

                    builderNav.innerHTML = sections.map((section, sectionIndex) => {
                        const sectionId = `survey-builder-section-${section.key}`;
                        const title = section.title || `Section ${sectionIndex + 1}`;
                        const questionCount = Array.isArray(section.questions) ? section.questions.length : 0;

                        return `
                            <a href="#${sectionId}" class="survey-nav-chip">
                                <span class="survey-nav-chip__index">${sectionIndex + 1}</span>
                                <span class="survey-nav-chip__body">
                                    <strong>${escapeHtml(title)}</strong>
                                    <span>${questionCount} question${questionCount === 1 ? '' : 's'}</span>
                                </span>
                            </a>
                        `;
                    }).join('');
                }

                function scrollToBuilderTarget(selector) {
                    requestAnimationFrame(() => {
                        const target = document.querySelector(selector);
                        if (!target) {
                            return;
                        }

                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center',
                        });
                    });
                }

                function answerOptionsForQuestion(question) {
                    if (['select', 'multiselect', 'radio', 'checkbox'].includes(question.type)) {
                        return Array.isArray(question.options) ? question.options : [];
                    }

                    if (question.type === 'scale') {
                        return scaleValues(question, 'scale').map((value) => String(value));
                    }

                    if (question.type === 'slider') {
                        return scaleValues(question, 'slider').map((value) => String(value));
                    }

                    if (question.type === 'matrix') {
                        return Array.isArray(question.columns)
                            ? question.columns.map((column) => column.label).filter(Boolean)
                            : [];
                    }

                    return [];
                }

                function scaleValues(question, mode = null) {
                    const type = mode || question.type || 'scale';
                    const min = Number.parseInt(question.scale?.min ?? 1, 10) || 1;
                    const max = Number.parseInt(question.scale?.max ?? 5, 10) || 5;
                    const step = type === 'slider'
                        ? (Number.parseInt(question.scale?.step ?? 1, 10) || 1)
                        : 1;
                    const start = Math.min(min, max);
                    const end = Math.max(min, max);
                    const values = [];

                    for (let value = start; value <= end; value += Math.max(step, 1)) {
                        values.push(value);
                    }

                    return values;
                }

                function normalizeScaleLabels(labels, min, max, step, legacyMinLabel = '', legacyMaxLabel = '') {
                    const values = [];
                    for (let value = Math.min(min, max); value <= Math.max(min, max); value += Math.max(step, 1)) {
                        values.push(String(value));
                    }

                    const source = labels && typeof labels === 'object' ? labels : {};
                    const normalized = {};

                    values.forEach((value) => {
                        const label = (source[value] || '').toString().trim();
                        if (label) {
                            normalized[value] = label;
                        }
                    });

                    if (legacyMinLabel && values.includes(String(min))) {
                        normalized[String(min)] = legacyMinLabel.toString().trim();
                    }

                    if (legacyMaxLabel && values.includes(String(max))) {
                        normalized[String(max)] = legacyMaxLabel.toString().trim();
                    }

                    return normalized;
                }

                function scaleLabelsMarkup(question, sectionIndex, questionIndex) {
                    const values = scaleValues(question, question.type);
                    const currentLabels = normalizeScaleLabels(
                        question.scale?.labels || {},
                        Number.parseInt(question.scale?.min ?? 1, 10) || 1,
                        Number.parseInt(question.scale?.max ?? 5, 10) || 5,
                        question.type === 'slider'
                            ? (Number.parseInt(question.scale?.step ?? 1, 10) || 1)
                            : 1,
                        question.scale?.min_label || '',
                        question.scale?.max_label || ''
                    );

                    return `
                        <div class="col-12" data-question-scale-labels-wrap="${sectionIndex}_${questionIndex}">
                            <label class="form-label">Scale Point Labels</label>
                            <div class="row g-2">
                                ${values.map((value) => `
                                    <div class="col-md-4">
                                        <label class="form-label form-label-sm">${escapeHtml(String(value))} Label</label>
                                        <input type="text" class="form-control form-control-sm"
                                            data-question-field="scale_label"
                                            data-scale-value="${value}"
                                            data-section-index="${sectionIndex}"
                                            data-question-index="${questionIndex}"
                                            value="${escapeHtml(currentLabels[String(value)] || '')}"
                                            placeholder="${value} = Enter label">
                                    </div>
                                `).join('')}
                            </div>
                            <small class="text-muted">
                                Add the wording for each point, for example 1 = Poor, 2 = Fair, 3 = Good.
                            </small>
                        </div>
                    `;
                }

                function visibilityMarkup(scope, sectionIndex, questionIndex, visibility, choices) {
                    const selectedChoice = choices.find((item) => item.key === visibility.question_key);
                    const valuesText = Array.isArray(visibility.values) ? visibility.values.join(', ') : '';
                    const ruleTitle = scope === 'section' ? 'Section Indicator / Skip Logic' : 'Follow-up Indicator / Skip Logic';
                    const selectorLabel = scope === 'section' ? 'Indicator question' : 'Ask this follow-up when answer is given in';
                    const valuePlaceholder = scope === 'section'
                        ? 'Virtual, In-person'
                        : 'Not achieved, Partially achieved';
                    const availableValues = selectedChoice?.options?.length
                        ? `Available answers: ${selectedChoice.options.join(', ')}`
                        : 'Use exact answer values separated by commas when this depends on free-text or numeric input.';
                    const summaryText = logicSummary(scope, visibility, choices);

                    return `
                        <div class="survey-condition-box mt-3">
                            <div class="survey-mini-label">${ruleTitle}</div>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label">${selectorLabel}</label>
                                    <select class="form-select form-select-sm"
                                        data-condition-scope="${scope}"
                                        data-condition-section="${sectionIndex}"
                                        data-condition-question="${questionIndex ?? ''}"
                                        data-condition-key="question_key">
                                        <option value="">No indicator, always show</option>
                                        ${choices.map((choice) => `
                                            <option value="${escapeHtml(choice.key)}" ${choice.key === visibility.question_key ? 'selected' : ''}>
                                                ${escapeHtml(choice.label)}
                                            </option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Trigger answer value(s)</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-condition-scope="${scope}"
                                        data-condition-section="${sectionIndex}"
                                        data-condition-question="${questionIndex ?? ''}"
                                        data-condition-key="values"
                                        value="${escapeHtml(valuesText)}"
                                        placeholder="${valuePlaceholder}">
                                </div>
                                <div class="col-12">
                                    <div class="survey-logic-summary">${escapeHtml(summaryText)}</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">${escapeHtml(availableValues)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                }

                function routeMarkup(section, question, sectionIndex, questionIndex) {
                    const route = normalizeRoute(question.route || {});
                    const selectedTargetType = route.target_type || '';
                    const selectedTargetKey = route.target_key || '';
                    const valuesText = Array.isArray(route.values) ? route.values.join(', ') : '';
                    const targetChoices = specialTargetChoices(question.key, section.key);
                    const filteredTargets = targetChoices.filter((choice) => choice.type === selectedTargetType);
                    const selectedTarget = filteredTargets.find((choice) => choice.key === selectedTargetKey);
                    const summaryText = selectedTargetType && selectedTargetKey && route.values.length
                        ? `When this answer matches ${route.values.join(', ')}, the respondent is sent directly to ${selectedTarget?.label || 'the selected follow-up page'} outside the normal survey flow.`
                        : 'No direct follow-up routing is active for this question.';

                    return `
                        <div class="survey-condition-box mt-3">
                            <div class="survey-mini-label">Direct Follow-up Routing</div>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <label class="form-label">Route to</label>
                                    <select class="form-select form-select-sm"
                                        data-route-field="target_type"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}">
                                        <option value="">No direct routing</option>
                                        <option value="section" ${selectedTargetType === 'section' ? 'selected' : ''}>Special section</option>
                                        <option value="question" ${selectedTargetType === 'question' ? 'selected' : ''}>Special question page</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Follow-up target</label>
                                    <select class="form-select form-select-sm"
                                        data-route-field="target_key"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}">
                                        <option value="">Select target</option>
                                        ${filteredTargets.map((choice) => `
                                            <option value="${escapeHtml(choice.key)}" ${choice.key === selectedTargetKey ? 'selected' : ''}>
                                                ${escapeHtml(choice.label)}
                                            </option>
                                        `).join('')}
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Trigger value(s)</label>
                                    <input type="text" class="form-control form-control-sm"
                                        data-route-field="values"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}"
                                        value="${escapeHtml(valuesText)}"
                                        placeholder="Not achieved, Partially achieved">
                                </div>
                                <div class="col-12">
                                    <div class="survey-logic-summary">${escapeHtml(summaryText)}</div>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">
                                        Any section or question can be targeted here. The selected target is automatically treated as a follow-up page outside the normal flow.
                                    </small>
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
                    const questionFlowType = normalizeFlowType(question.flow_type);
                    const questionAutoSpecial = routeTargets.questionKeys.has(question.key);
                    const effectiveQuestionFlow = questionFlowType === 'special' || questionAutoSpecial ? 'special' : 'normal';
                    const questionFlowNote = effectiveQuestionFlow === 'special'
                        ? 'This question is excluded from the normal section page. It opens only when another question routes respondents here.'
                        : 'This question is part of the normal section flow.';

                    return `
                        <div class="survey-question-card" data-question-card-key="${question.key}">
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
                                <div class="col-md-3">
                                    <label class="form-label">Flow Placement</label>
                                    <select class="form-select form-select-sm"
                                        data-question-field="flow_type"
                                        data-section-index="${sectionIndex}"
                                        data-question-index="${questionIndex}">
                                        <option value="normal" ${questionFlowType === 'normal' ? 'selected' : ''}>Normal section flow</option>
                                        <option value="special" ${questionFlowType === 'special' ? 'selected' : ''}>Special follow-up only</option>
                                    </select>
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
                                <div class="col-12">
                                    <small class="text-muted">${escapeHtml(questionFlowNote)}</small>
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
                                ${showScale ? scaleLabelsMarkup(question, sectionIndex, questionIndex) : ''}

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
                            ${routeMarkup(section, question, sectionIndex, questionIndex)}
                        </div>
                    `;
                }

                function renderSections() {
                    sectionsContainer.innerHTML = '';
                    refreshRouteTargets();
                    renderSectionNavigator();

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
                        card.id = `survey-builder-section-${section.key}`;
                        const sectionColor = normalizeHexColor(section.color, defaultSectionColor(sectionIndex));
                        const sectionFlowType = normalizeFlowType(section.flow_type);
                        const sectionAutoSpecial = routeTargets.sectionKeys.has(section.key);
                        const effectiveSectionFlow = sectionFlowType === 'special' || sectionAutoSpecial ? 'special' : 'normal';
                        const sectionFlowNote = effectiveSectionFlow === 'special'
                            ? 'This section is removed from the normal survey sequence and only opens when a routing rule sends respondents here.'
                            : 'This section remains part of the normal survey sequence.';
                        card.style.setProperty('--section-color', sectionColor);
                        card.style.borderColor = hexToRgba(sectionColor, 0.22);
                        card.style.background = `linear-gradient(180deg, ${hexToRgba(sectionColor, 0.08)}, rgba(255, 255, 255, 0.96) 28%, rgba(248, 250, 252, 0.92))`;
                        const sectionChoices = dependencyChoices(section.key);

                        card.innerHTML = `
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                                <div>
                                    <div class="survey-mini-label">Section ${sectionIndex + 1}</div>
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <h6 class="fw-semibold mb-0">${escapeHtml(section.title || `Section ${sectionIndex + 1}`)}</h6>
                                        <span class="survey-section-chip" style="--section-color: ${sectionColor};">
                                            <span class="survey-section-chip__swatch"></span>
                                            <span class="survey-section-chip__label">${escapeHtml(sectionColor)}</span>
                                        </span>
                                    </div>
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
                                <div class="col-md-4">
                                    <label class="form-label">Section Title</label>
                                    <input type="text" class="form-control"
                                        data-section-field="title"
                                        data-section-index="${sectionIndex}"
                                        value="${escapeHtml(section.title || '')}"
                                        placeholder="Section 1: Participant Information">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Section Color</label>
                                    <input type="color" class="form-control form-control-color w-100"
                                        data-section-field="color"
                                        data-section-index="${sectionIndex}"
                                        value="${sectionColor}"
                                        title="Choose a section color">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Flow Placement</label>
                                    <select class="form-select"
                                        data-section-field="flow_type"
                                        data-section-index="${sectionIndex}">
                                        <option value="normal" ${sectionFlowType === 'normal' ? 'selected' : ''}>Normal survey flow</option>
                                        <option value="special" ${sectionFlowType === 'special' ? 'selected' : ''}>Special follow-up only</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Section Description</label>
                                    <textarea class="form-control" rows="2"
                                        data-section-field="description"
                                        data-section-index="${sectionIndex}"
                                        placeholder="Short guidance shown at the top of this section">${escapeHtml(section.description || '')}</textarea>
                                </div>
                                <div class="col-12">
                                    <small class="text-muted">${escapeHtml(sectionFlowNote)}</small>
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

                            <div class="survey-section-footer">
                                <div class="survey-type-note">
                                    Keep building from the bottom of this section without scrolling back to the top.
                                </div>
                                <div class="d-flex gap-2 flex-wrap justify-content-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" data-add-question="${sectionIndex}">
                                        <i class="feather-plus me-1"></i> Add Question
                                    </button>
                                    <button type="button" class="btn btn-sm btn-primary" data-add-section-after="${sectionIndex}">
                                        <i class="feather-layers me-1"></i> Add Section Below
                                    </button>
                                </div>
                            </div>
                        `;

                        sectionsContainer.appendChild(card);
                    });
                }

                function syncSectionsFromDom() {
                    sections = sections.map((section, sectionIndex) => {
                        const title = form.querySelector(`[data-section-field="title"][data-section-index="${sectionIndex}"]`)?.value || '';
                        const description = form.querySelector(`[data-section-field="description"][data-section-index="${sectionIndex}"]`)?.value || '';
                        const color = normalizeHexColor(
                            form.querySelector(`[data-section-field="color"][data-section-index="${sectionIndex}"]`)?.value || '',
                            defaultSectionColor(sectionIndex)
                        );
                        const flowType = form.querySelector(`[data-section-field="flow_type"][data-section-index="${sectionIndex}"]`)?.value || 'normal';
                        const sectionConditionQuestion = form.querySelector(`[data-condition-scope="section"][data-condition-section="${sectionIndex}"][data-condition-key="question_key"]`)?.value || '';
                        const sectionConditionValues = form.querySelector(`[data-condition-scope="section"][data-condition-section="${sectionIndex}"][data-condition-key="values"]`)?.value || '';

                        const questions = (section.questions || []).map((question, questionIndex) => {
                            const type = form.querySelector(`[data-question-field="type"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || 'text';
                            const scaleMin = Number.parseInt(form.querySelector(`[data-question-field="scale_min"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '1', 10) || 1;
                            const scaleMax = Number.parseInt(form.querySelector(`[data-question-field="scale_max"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '5', 10) || 5;
                            const scaleStep = Number.parseInt(form.querySelector(`[data-question-field="scale_step"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '1', 10) || 1;
                            const normalizedScaleMin = Math.max(1, Math.min(scaleMin, scaleMax));
                            const normalizedScaleMax = Math.max(scaleMin, scaleMax);
                            const normalizedScaleStep = Math.max(1, scaleStep);
                            const scaleValuesForQuestion = ['scale', 'slider'].includes(type)
                                ? (() => {
                                    const values = [];
                                    const step = type === 'slider' ? normalizedScaleStep : 1;
                                    for (let value = normalizedScaleMin; value <= normalizedScaleMax; value += step) {
                                        values.push(String(value));
                                    }
                                    return values;
                                })()
                                : [];
                            const scaleLabels = scaleValuesForQuestion.reduce((labels, value) => {
                                const field = form.querySelector(`[data-question-field="scale_label"][data-scale-value="${value}"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`);
                                const label = (field?.value || '').trim();
                                if (label) {
                                    labels[value] = label;
                                }
                                return labels;
                            }, {});
                            const selectedRouteType = form.querySelector(`[data-route-field="target_type"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '';
                            const selectedRouteKey = form.querySelector(`[data-route-field="target_key"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '';
                            const validRouteTargetKeys = specialTargetChoices(question.key || '', section.key || '')
                                .filter((choice) => choice.type === selectedRouteType)
                                .map((choice) => choice.key);

                            return {
                                key: question.key || createKey('question'),
                                label: form.querySelector(`[data-question-field="label"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || '',
                                type,
                                flow_type: normalizeFlowType(form.querySelector(`[data-question-field="flow_type"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || 'normal'),
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
                                        min: normalizedScaleMin,
                                        max: normalizedScaleMax,
                                        step: normalizedScaleStep,
                                        labels: scaleLabels,
                                        min_label: scaleLabels[String(normalizedScaleMin)] || '',
                                        max_label: scaleLabels[String(normalizedScaleMax)] || '',
                                    }
                                    : {
                                        min: 1,
                                        max: 5,
                                        step: 1,
                                        labels: {},
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
                                route: normalizeRoute({
                                    target_type: selectedRouteType,
                                    target_key: validRouteTargetKeys.includes(selectedRouteKey) ? selectedRouteKey : '',
                                    values: parseStringList(form.querySelector(`[data-route-field="values"][data-section-index="${sectionIndex}"][data-question-index="${questionIndex}"]`)?.value || ''),
                                }),
                            };
                        });

                        return {
                            key: section.key || createKey('section'),
                            title: title.trim(),
                            description: description.trim(),
                            color: normalizeHexColor(color, defaultSectionColor(sectionIndex)),
                            flow_type: normalizeFlowType(flowType),
                            visibility: normalizeVisibility({
                                question_key: sectionConditionQuestion,
                                values: parseStringList(sectionConditionValues),
                            }),
                            questions,
                        };
                    });

                    sectionsJsonInput.value = JSON.stringify(sections);
                }

                function addSection(afterIndex = null) {
                    const newSection = defaultSection(Number.isInteger(afterIndex) ? afterIndex + 1 : sections.length);
                    if (Number.isInteger(afterIndex) && afterIndex >= -1 && afterIndex < sections.length) {
                        sections.splice(afterIndex + 1, 0, newSection);
                    } else {
                        sections.push(newSection);
                    }

                    renderSections();
                    syncSectionsFromDom();
                    scrollToBuilderTarget(`#survey-builder-section-${newSection.key}`);
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

                addSectionBtn.addEventListener('click', () => addSection());
                addSectionDockBtn.addEventListener('click', () => addSection());

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

                    const addSectionAfterTrigger = event.target.closest('[data-add-section-after]');
                    if (addSectionAfterTrigger) {
                        addSection(Number(addSectionAfterTrigger.getAttribute('data-add-section-after')));
                        return;
                    }

                    const addQuestionTrigger = event.target.closest('[data-add-question]');
                    if (addQuestionTrigger) {
                        const sectionIndex = Number(addQuestionTrigger.getAttribute('data-add-question'));
                        const newQuestion = defaultQuestion();
                        sections[sectionIndex].questions.push(newQuestion);
                        renderSections();
                        syncSectionsFromDom();
                        scrollToBuilderTarget(`[data-question-card-key="${newQuestion.key}"]`);
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

                    const target = event.target;
                    const sectionIndex = target.getAttribute('data-section-index');

                    if (sectionIndex !== null && target.matches('[data-section-field="color"]')) {
                        const sectionCard = target.closest('.survey-section-card');
                        const color = normalizeHexColor(target.value, defaultSectionColor(Number(sectionIndex)));
                        target.value = color;

                            if (sectionCard) {
                                sectionCard.style.setProperty('--section-color', color);
                                sectionCard.style.borderColor = hexToRgba(color, 0.22);
                                sectionCard.style.background = `linear-gradient(180deg, ${hexToRgba(color, 0.08)}, rgba(255, 255, 255, 0.96) 28%, rgba(248, 250, 252, 0.92))`;
                                const chip = sectionCard.querySelector('.survey-section-chip');
                                if (chip) {
                                    chip.style.setProperty('--section-color', color);
                                    const chipLabel = chip.querySelector('.survey-section-chip__label');
                                    if (chipLabel) {
                                        chipLabel.textContent = color;
                                    }
                                }
                            }
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
                        || target.matches('[data-question-field="flow_type"]')
                        || target.matches('[data-section-field="flow_type"]')
                        || target.matches('[data-question-field="scale_min"]')
                        || target.matches('[data-question-field="scale_max"]')
                        || target.matches('[data-question-field="scale_step"]')
                        || target.matches('[data-route-field="target_type"]')
                        || target.matches('[data-route-field="target_key"]')
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
