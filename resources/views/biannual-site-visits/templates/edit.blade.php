@extends('layouts.app')

@section('title', 'Build '.$template->name)
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-builder-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 270px;
            gap: 1rem;
            align-items: start;
        }

        .builder-section {
            margin-bottom: 1rem;
            border: 1px solid var(--basv-border);
            border-radius: .9rem;
            background: #fff;
            box-shadow: 0 8px 22px rgba(15, 42, 39, .045);
        }

        .builder-section-head,
        .builder-topic-head,
        .builder-question-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .65rem;
        }

        .builder-section-head {
            padding: 1rem;
            border-bottom: 1px solid var(--basv-border);
            background: #f4faf7;
        }

        .builder-topic {
            margin: .8rem;
            border: 1px solid #e1eae7;
            border-radius: .75rem;
            background: #fbfdfc;
        }

        .builder-topic > summary {
            cursor: pointer;
            list-style: none;
        }

        .builder-topic > summary::-webkit-details-marker {
            display: none;
        }

        .builder-topic-head {
            padding: .8rem;
        }

        .builder-topic-body {
            padding: 0 .8rem .8rem;
            border-top: 1px solid #e6eeeb;
        }

        .builder-question {
            margin-top: .7rem;
            padding: .8rem;
            border: 1px solid #dfe9e6;
            border-left: 3px solid var(--basv-gold);
            border-radius: .65rem;
            background: #fff;
        }

        .builder-mini-actions {
            display: inline-flex;
            gap: .25rem;
        }

        .builder-icon-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 30px;
            height: 30px;
            border: 1px solid var(--basv-border);
            border-radius: .45rem;
            background: #fff;
            color: #536862;
        }

        .builder-icon-btn:hover {
            border-color: #9fcbbf;
            color: var(--basv-green);
        }

        .builder-icon-btn.danger:hover {
            border-color: #e6baba;
            color: #a13d3d;
        }

        .builder-side {
            position: sticky;
            top: 118px;
        }

        .builder-counts {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: .4rem;
        }

        .builder-counts > div {
            padding: .6rem .35rem;
            border-radius: .55rem;
            background: #f3f8f6;
            text-align: center;
        }

        .builder-counts strong,
        .builder-counts span {
            display: block;
        }

        .builder-counts strong {
            color: var(--basv-green-dark);
            font-size: 1rem;
        }

        .builder-counts span {
            color: var(--basv-muted);
            font-size: .59rem;
            font-weight: 750;
        }

        @media (max-width: 1100px) {
            .basv-builder-layout {
                grid-template-columns: 1fr;
            }

            .builder-side {
                position: static;
                order: -1;
            }
        }
    </style>
@endpush

@section('content')
    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-edit-3"></i> Visual questionnaire builder</span>
                    <h1>{{ $template->name }}</h1>
                    <p>{{ $template->code }} · Draft version {{ $template->version }}. Organize the assessment into
                        sections, topics, and configurable question types.</p>
                </div>
                <div class="basv-hero-actions">
                    <a href="{{ route('biannual-site-visits.templates.index') }}" class="basv-btn basv-btn-light">
                        <i class="feather-arrow-left"></i> Template library
                    </a>
                </div>
            </div>

            @if ($errors->any())
                <div class="basv-alert danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('biannual-site-visits.templates.update', $template) }}" id="builder-form">
                @csrf
                @method('PUT')
                <input type="hidden" name="structure" id="structure-json">

                <div class="basv-builder-layout">
                    <div>
                        <div class="basv-card">
                            <div class="basv-card-head"><h2><i class="feather-info me-2"></i>Template information</h2></div>
                            <div class="basv-card-body">
                                <div class="basv-form-grid">
                                    <div>
                                        <label class="form-label">Template name</label>
                                        <input class="form-control" name="name" value="{{ old('name', $template->name) }}" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Version</label>
                                        <input class="form-control" value="Version {{ $template->version }}" disabled>
                                    </div>
                                    <div class="basv-field-full">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="description">{{ old('description', $template->description) }}</textarea>
                                    </div>
                                    <div class="basv-field-full">
                                        <label class="form-label">Assessor instructions</label>
                                        <textarea class="form-control" name="instructions">{{ old('instructions', $template->instructions) }}</textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="builder-sections"></div>
                        <button type="button" class="basv-btn basv-btn-ghost w-100" data-action="add-section">
                            <i class="feather-plus-circle"></i> Add section
                        </button>
                    </div>

                    <aside class="builder-side">
                        <div class="basv-card">
                            <div class="basv-card-head"><h3>Template structure</h3></div>
                            <div class="basv-card-body">
                                <div class="builder-counts mb-3">
                                    <div><strong id="section-count">0</strong><span>Sections</span></div>
                                    <div><strong id="topic-count">0</strong><span>Topics</span></div>
                                    <div><strong id="question-count">0</strong><span>Questions</span></div>
                                </div>
                                <div class="basv-alert mb-3">
                                    <strong>Published versions lock.</strong>
                                    <div class="mt-1">Duplicate a published template to customize the next monitoring cycle.</div>
                                </div>
                                <button class="basv-btn basv-btn-primary w-100 mb-2" type="submit">
                                    <i class="feather-save"></i> Save draft
                                </button>
                                <a class="basv-btn basv-btn-ghost w-100"
                                    href="{{ route('biannual-site-visits.templates.index') }}">Cancel</a>
                            </div>
                        </div>
                    </aside>
                </div>
            </form>
        </div>
    </main>
@endsection

@push('scripts')
    <script>
        (() => {
            const responseTypes = @json($responseTypes);
            const builderDefaults = @json($builderDefaults);
            let structure = @json(old('structure') ? json_decode(old('structure'), true) : $structure);

            if (!Array.isArray(structure)) structure = [];

            const root = document.getElementById('builder-sections');
            const hidden = document.getElementById('structure-json');

            const escapeHtml = value => String(value ?? '')
                .replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;')
                .replaceAll('"', '&quot;').replaceAll("'", '&#039;');

            const makeKey = () => `CUSTOM-${Date.now()}-${Math.random().toString(36).slice(2, 7).toUpperCase()}`;
            const clone = value => JSON.parse(JSON.stringify(value ?? []));
            const numberOr = (value, fallback) => {
                const parsed = Number(value ?? fallback);
                return Number.isFinite(parsed) ? Math.max(0, parsed) : fallback;
            };
            const optionValue = value => String(value ?? '').trim();
            const isNaOption = option => {
                if (!option || typeof option !== 'object') return false;
                const label = String(option.label ?? '').trim().toLowerCase();
                const value = String(option.value ?? '').trim().toLowerCase();
                return Boolean(option.is_not_applicable ?? option.is_na)
                    || ['na', 'n/a', 'not applicable', 'not_applicable'].includes(label)
                    || ['na', 'n/a', 'not applicable', 'not_applicable'].includes(value);
            };
            const escapeOptionField = value => String(value ?? '')
                .replaceAll('\\', '\\\\')
                .replaceAll('|', '\\|')
                .replaceAll('\r', '\\r')
                .replaceAll('\n', '\\n');
            const splitOptionLine = line => {
                const parts = [];
                let current = '';
                let escaped = false;

                for (const character of String(line)) {
                    if (escaped) {
                        if (character === 'n') current += '\n';
                        else if (character === 'r') current += '\r';
                        else if (character === '|' || character === '\\') current += character;
                        else current += `\\${character}`;
                        escaped = false;
                    } else if (character === '\\') {
                        escaped = true;
                    } else if (character === '|') {
                        parts.push(current.trim());
                        current = '';
                    } else {
                        current += character;
                    }
                }

                if (escaped) current += '\\';
                parts.push(current.trim());
                return parts;
            };
            const optionMarkerAliases = ['na', 'n/a', 'true', 'yes'];
            const formatOption = option => {
                if (!option || typeof option !== 'object') return escapeOptionField(option);
                const fields = [
                    option.value ?? '',
                    option.score ?? '',
                    option.label ?? option.value ?? '',
                    option.description ?? option.help_text ?? '',
                    isNaOption(option) ? 'N/A' : '',
                ].map(value => String(value ?? ''));
                const preserveEmptyMarker = !isNaOption(option)
                    && optionMarkerAliases.includes(fields[3].trim().toLowerCase());
                while (fields.length > (preserveEmptyMarker ? 5 : 0) && fields[fields.length - 1] === '') {
                    fields.pop();
                }
                return fields.map(escapeOptionField).join(' | ');
            };
            const parseOption = line => {
                const parts = splitOptionLine(line);
                if (parts.length < 2) return parts[0] ?? '';

                let [rawValue, rawScore, rawLabel, rawDescription = '', rawMarker = ''] = parts;
                if (parts.length === 4 && optionMarkerAliases.includes(rawDescription.toLowerCase())) {
                    rawMarker = rawDescription;
                    rawDescription = '';
                }
                const option = {
                    value: optionValue(rawValue || rawLabel),
                    label: rawLabel || rawValue,
                    description: rawDescription,
                    is_not_applicable: optionMarkerAliases.includes(rawMarker.toLowerCase()),
                };
                if (rawScore !== '' && Number.isFinite(Number(rawScore))) option.score = Number(rawScore);
                return option;
            };

            function normalize() {
                structure = structure.map((section, sectionIndex) => ({
                    key: section.key || section.section_key || '',
                    title: section.title || `Section ${sectionIndex + 1}`,
                    description: section.description || '',
                    guidance: section.guidance || '',
                    settings: section.settings && typeof section.settings === 'object' ? section.settings : {},
                    visibility_rules: section.visibility_rules || section.visibility || {},
                    weight: numberOr(section.weight, 1),
                    topics: Array.isArray(section.topics) ? section.topics.map((topic, topicIndex) => ({
                        key: topic.key || topic.topic_key || '',
                        title: topic.title || `Topic ${topicIndex + 1}`,
                        description: topic.description || '',
                        guidance: topic.guidance || '',
                        settings: topic.settings && typeof topic.settings === 'object' ? topic.settings : {},
                        visibility_rules: topic.visibility_rules || topic.visibility || {},
                        weight: numberOr(topic.weight, 1),
                        questions: Array.isArray(topic.questions) ? topic.questions.map(question => ({
                            key: question.key || question.stable_key || makeKey(),
                            original_key: question.original_key || question.key || question.stable_key || '',
                            prompt: question.prompt || question.question || '',
                            response_type: question.response_type || 'scored_assessment',
                            required: Boolean(question.required),
                            weight: numberOr(question.weight, 1),
                            scoring_direction: question.scoring_direction || 'positive',
                            minimum_score: numberOr(
                                question.minimum_score ?? question.min_score,
                                Number(builderDefaults.minimum_score ?? 0)
                            ),
                            maximum_score: numberOr(
                                question.maximum_score ?? question.max_score,
                                Number(builderDefaults.maximum_score ?? 3)
                            ),
                            allows_na: question.allows_na === undefined
                                ? (question.response_type || 'scored_assessment') === 'scored_assessment'
                                : Boolean(question.allows_na),
                            required_when: question.required_when || {},
                            rating_labels: question.rating_labels || {},
                            settings: question.settings && typeof question.settings === 'object' ? question.settings : {},
                            help_text: question.help_text || '',
                            options: Array.isArray(question.options) ? question.options : [],
                            validation_rules: question.validation_rules || {},
                            visibility_rules: question.visibility_rules || {},
                        })) : [],
                    })) : [],
                }));
            }

            function typeOptions(selected) {
                return Object.entries(responseTypes).map(([value, label]) =>
                    `<option value="${escapeHtml(value)}" ${selected === value ? 'selected' : ''}>${escapeHtml(label)}</option>`
                ).join('');
            }

            function questionHtml(question, si, ti, qi) {
                const options = (question.options || []).map(formatOption).join('\n');

                return `
                    <div class="builder-question" data-entity="question" data-section="${si}" data-topic="${ti}" data-question="${qi}">
                        <div class="builder-question-head mb-2">
                            <strong>Question ${qi + 1}</strong>
                            <span class="builder-mini-actions">
                                <button type="button" class="builder-icon-btn" data-action="move-question-up" title="Move up"><i class="feather-arrow-up"></i></button>
                                <button type="button" class="builder-icon-btn" data-action="move-question-down" title="Move down"><i class="feather-arrow-down"></i></button>
                                <button type="button" class="builder-icon-btn danger" data-action="delete-question" title="Remove"><i class="feather-trash-2"></i></button>
                            </span>
                        </div>
                        <div class="row g-2">
                            <div class="col-lg-8">
                                <label class="form-label">Question prompt</label>
                                <textarea class="form-control" data-field="prompt" required>${escapeHtml(question.prompt)}</textarea>
                            </div>
                            <div class="col-lg-4">
                                <label class="form-label">Response type</label>
                                <select class="form-select" data-field="response_type">${typeOptions(question.response_type)}</select>
                                <label class="d-flex align-items-center gap-2 mt-2 small fw-semibold">
                                    <input class="form-check-input m-0" type="checkbox" data-field="required" ${question.required ? 'checked' : ''}>
                                    Required at submission
                                </label>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Stable reporting key</label>
                                <input class="form-control" data-field="key" value="${escapeHtml(question.key)}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Weight</label>
                                <input class="form-control" type="number" min="0" step="0.01" data-field="weight" value="${escapeHtml(question.weight)}">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Minimum score</label>
                                <input class="form-control" type="number" step="0.01" data-field="minimum_score" value="${escapeHtml(question.minimum_score)}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Maximum score</label>
                                <input class="form-control" type="number" min="0.01" step="0.01" data-field="maximum_score" value="${escapeHtml(question.maximum_score)}" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Scoring direction</label>
                                <select class="form-select" data-field="scoring_direction">
                                    <option value="positive" ${question.scoring_direction === 'positive' ? 'selected' : ''}>Higher is better</option>
                                    <option value="negative" ${question.scoring_direction === 'negative' ? 'selected' : ''}>Higher indicates risk</option>
                                    <option value="none" ${question.scoring_direction === 'none' ? 'selected' : ''}>Not scored</option>
                                </select>
                                <label class="d-flex align-items-center gap-2 mt-2 small fw-semibold">
                                    <input class="form-check-input m-0" type="checkbox" data-field="allows_na" ${question.allows_na ? 'checked' : ''}>
                                    Allow not applicable
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Help text</label>
                                <textarea class="form-control" data-field="help_text">${escapeHtml(question.help_text)}</textarea>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Choice options <span class="text-muted">(value | score | label | rubric description | optional N/A; use \\| for a literal pipe)</span></label>
                                <textarea class="form-control" data-field="options_text"
                                    placeholder="critical | 5 | Critical risk | Major control failure">${escapeHtml(options)}</textarea>
                            </div>
                        </div>
                    </div>`;
            }

            function topicHtml(topic, si, ti) {
                return `
                    <details class="builder-topic" open data-entity="topic" data-section="${si}" data-topic="${ti}">
                        <summary class="builder-topic-head">
                            <div>
                                <strong>${ti + 1}. ${escapeHtml(topic.title)}</strong>
                                <span class="basv-record-meta">${topic.questions.length} questions</span>
                            </div>
                            <span class="builder-mini-actions">
                                <button type="button" class="builder-icon-btn" data-action="move-topic-up" title="Move up"><i class="feather-arrow-up"></i></button>
                                <button type="button" class="builder-icon-btn" data-action="move-topic-down" title="Move down"><i class="feather-arrow-down"></i></button>
                                <button type="button" class="builder-icon-btn danger" data-action="delete-topic" title="Remove"><i class="feather-trash-2"></i></button>
                            </span>
                        </summary>
                        <div class="builder-topic-body">
                            <div class="row g-2 mt-1">
                                <div class="col-md-7">
                                    <label class="form-label">Topic title</label>
                                    <input class="form-control" data-field="title" value="${escapeHtml(topic.title)}" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Weight</label>
                                    <input class="form-control" type="number" min="0" step="0.01" data-field="weight" value="${escapeHtml(topic.weight)}">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" data-field="description">${escapeHtml(topic.description)}</textarea>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Assessor guidance</label>
                                    <textarea class="form-control" data-field="guidance">${escapeHtml(topic.guidance)}</textarea>
                                </div>
                            </div>
                            <div>${topic.questions.map((q, qi) => questionHtml(q, si, ti, qi)).join('')}</div>
                            <button type="button" class="basv-btn basv-btn-ghost w-100 mt-2" data-action="add-question">
                                <i class="feather-plus"></i> Add question
                            </button>
                        </div>
                    </details>`;
            }

            function sectionHtml(section, si) {
                return `
                    <section class="builder-section" data-entity="section" data-section="${si}">
                        <div class="builder-section-head">
                            <div>
                                <span class="basv-eyebrow" style="color:var(--basv-green)">Section ${si + 1}</span>
                                <strong class="d-block">${escapeHtml(section.title)}</strong>
                            </div>
                            <span class="builder-mini-actions">
                                <button type="button" class="builder-icon-btn" data-action="move-section-up" title="Move up"><i class="feather-arrow-up"></i></button>
                                <button type="button" class="builder-icon-btn" data-action="move-section-down" title="Move down"><i class="feather-arrow-down"></i></button>
                                <button type="button" class="builder-icon-btn danger" data-action="delete-section" title="Remove"><i class="feather-trash-2"></i></button>
                            </span>
                        </div>
                        <div class="p-3">
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label">Section title</label>
                                    <input class="form-control" data-field="title" value="${escapeHtml(section.title)}" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label">Weight</label>
                                    <input class="form-control" type="number" min="0" step="0.01" data-field="weight" value="${escapeHtml(section.weight)}">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" data-field="description">${escapeHtml(section.description)}</textarea>
                                </div>
                            </div>
                            <div>${section.topics.map((topic, ti) => topicHtml(topic, si, ti)).join('')}</div>
                            <button type="button" class="basv-btn basv-btn-ghost w-100 mt-2" data-action="add-topic">
                                <i class="feather-plus"></i> Add topic
                            </button>
                        </div>
                    </section>`;
            }

            function render() {
                normalize();
                root.innerHTML = structure.map(sectionHtml).join('') || `
                    <div class="basv-empty basv-card">
                        <i class="feather-layers"></i>
                        <strong>This template is empty</strong>
                        <div class="mt-1">Add the first section to begin building.</div>
                    </div>`;
                sync();
            }

            function sync() {
                hidden.value = JSON.stringify(structure);
                document.getElementById('section-count').textContent = structure.length;
                document.getElementById('topic-count').textContent =
                    structure.reduce((sum, section) => sum + section.topics.length, 0);
                document.getElementById('question-count').textContent =
                    structure.reduce((sum, section) => sum + section.topics.reduce((n, topic) => n + topic.questions.length, 0), 0);
            }

            function context(element) {
                const entity = element.closest('[data-entity]');
                return {
                    entity,
                    si: Number(entity?.dataset.section ?? -1),
                    ti: Number(entity?.dataset.topic ?? -1),
                    qi: Number(entity?.dataset.question ?? -1),
                };
            }

            document.addEventListener('input', event => {
                const field = event.target.dataset.field;
                if (!field) return;
                const {entity, si, ti, qi} = context(event.target);
                if (!entity) return;

                let target = structure[si];
                if (entity.dataset.entity === 'topic') target = target.topics[ti];
                if (entity.dataset.entity === 'question') target = target.topics[ti].questions[qi];

                if (['required', 'allows_na'].includes(field)) {
                    target[field] = event.target.checked;
                    if (field === 'allows_na' && !target[field]) {
                        target.options = (target.options || []).filter(option => !isNaOption(option));
                    }
                }
                else if (['weight', 'minimum_score', 'maximum_score'].includes(field)) target[field] = Number(event.target.value || 0);
                else if (field === 'options_text') {
                    const existingOptions = Array.isArray(target.options) ? target.options : [];
                    const usedOptions = new Set();
                    const optionLines = event.target.value.split(/\r?\n/)
                        .map(value => value.trim())
                        .filter(Boolean);
                    const canMatchByPosition = optionLines.length === existingOptions.length;
                    target.options = optionLines.map((line, index) => {
                            const parsed = parseOption(line);
                            if (!parsed || typeof parsed !== 'object') return parsed;

                            let existingIndex = existingOptions.findIndex((option, optionIndex) =>
                                !usedOptions.has(optionIndex)
                                && option
                                && typeof option === 'object'
                                && String(option.value ?? '') === String(parsed.value ?? '')
                            );
                            if (existingIndex < 0 && canMatchByPosition && !usedOptions.has(index)) {
                                existingIndex = index;
                            }
                            const existing = existingOptions[existingIndex];
                            if (existingIndex >= 0) usedOptions.add(existingIndex);

                            return existing && typeof existing === 'object'
                                ? {...existing, ...parsed}
                                : parsed;
                        });
                } else target[field] = event.target.value;
                sync();
            });

            document.addEventListener('change', event => {
                if (event.target.dataset.field) event.target.dispatchEvent(new Event('input', {bubbles: true}));
            });

            document.addEventListener('click', event => {
                const button = event.target.closest('[data-action]');
                if (!button) return;
                const action = button.dataset.action;
                const {si, ti, qi} = context(button);
                const move = (items, index, delta) => {
                    const next = index + delta;
                    if (index < 0 || next < 0 || next >= items.length) return;
                    [items[index], items[next]] = [items[next], items[index]];
                };

                if (action === 'add-section') structure.push({title: `Section ${structure.length + 1}`, description: '', weight: 1, topics: []});
                if (action === 'delete-section' && confirm('Remove this section and all of its topics?')) structure.splice(si, 1);
                if (action === 'move-section-up') move(structure, si, -1);
                if (action === 'move-section-down') move(structure, si, 1);
                if (action === 'add-topic') structure[si].topics.push({title: `Topic ${structure[si].topics.length + 1}`, description: '', guidance: '', weight: 1, questions: []});
                if (action === 'delete-topic' && confirm('Remove this topic and all of its questions?')) structure[si].topics.splice(ti, 1);
                if (action === 'move-topic-up') move(structure[si].topics, ti, -1);
                if (action === 'move-topic-down') move(structure[si].topics, ti, 1);
                if (action === 'add-question') structure[si].topics[ti].questions.push({
                    key: makeKey(), prompt: '', response_type: 'scored_assessment', required: true,
                    weight: 1,
                    minimum_score: Number(builderDefaults.minimum_score ?? 0),
                    maximum_score: Number(builderDefaults.maximum_score ?? 3),
                    scoring_direction: 'positive',
                    allows_na: Boolean(builderDefaults.allows_na),
                    help_text: '',
                    options: clone(builderDefaults.options || []),
                    validation_rules: {}, visibility_rules: {},
                    required_when: {}, rating_labels: {}, settings: {},
                });
                if (action === 'delete-question' && confirm('Remove this question?')) structure[si].topics[ti].questions.splice(qi, 1);
                if (action === 'move-question-up') move(structure[si].topics[ti].questions, qi, -1);
                if (action === 'move-question-down') move(structure[si].topics[ti].questions, qi, 1);

                render();
            });

            document.getElementById('builder-form').addEventListener('submit', sync);
            render();
        })();
    </script>
@endpush
