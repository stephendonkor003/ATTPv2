@extends('layouts.app')

@section('title', 'Edit ' . $evaluation->name)

@section('content')
    @php
        $canEdit = $evaluation->status === 'draft';
        $portfolioFieldLocked = $portfolioFieldLocked || ! $canEdit;
        $sectionCount = $evaluation->sections->count();
        $questionCount = $evaluation->sections->sum(fn ($section) => $section->criteria->count());
        $maximumPoints = $evaluation->usesNumericScoring()
            ? (float) $evaluation->sections->sum(fn ($section) => $section->criteria->sum('max_score'))
            : null;
        $currentPortfolio = $portfolioOptions->firstWhere('id', old('portfolio_id', $selectedPortfolioId));
    @endphp

    <div class="nxl-container evaluation-details-page" data-evaluation-details>
        <section class="details-hero">
            <div>
                <nav class="details-breadcrumb" aria-label="Breadcrumb">
                    <a href="{{ route('evals.cfg.index') }}">Evaluations</a>
                    <i class="feather-chevron-right" aria-hidden="true"></i>
                    <a href="{{ route('evals.cfg.show', $evaluation) }}">Form builder</a>
                    <i class="feather-chevron-right" aria-hidden="true"></i>
                    <span>Edit details</span>
                </nav>
                <div class="details-title-row">
                    <span class="details-title-icon" aria-hidden="true"><i class="feather-edit-3"></i></span>
                    <div>
                        <div class="details-badges">
                            <span class="details-type-badge">{{ $evaluation->typeLabel() }}</span>
                            <span class="details-status details-status-{{ $evaluation->status }}">
                                <i></i>{{ ucfirst($evaluation->status) }}
                            </span>
                        </div>
                        <h1>Edit evaluation details</h1>
                        <p>Update the template identity and ownership. Its questions and four-tier structure stay in the form builder.</p>
                    </div>
                </div>
            </div>
            <a href="{{ route('evals.cfg.show', $evaluation) }}" class="btn btn-light">
                <i class="feather-arrow-left me-1" aria-hidden="true"></i>Back to builder
            </a>
        </section>

        @if ($errors->any())
            <div class="alert alert-danger details-alert" role="alert">
                <i class="feather-alert-triangle" aria-hidden="true"></i>
                <div>
                    <strong>Review the highlighted details.</strong>
                    <ul class="mb-0 mt-1 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @unless ($canEdit)
            <div class="alert alert-warning details-alert" role="status">
                <i class="feather-lock" aria-hidden="true"></i>
                <span>This evaluation is {{ $evaluation->status }}. Only draft evaluation details can be changed.</span>
            </div>
        @endunless

        <div class="details-layout">
            <main class="details-card">
                <header class="details-card-header">
                    <span class="section-icon" aria-hidden="true"><i class="feather-file-text"></i></span>
                    <div>
                        <span class="details-kicker">Template information</span>
                        <h2>Name, description and portfolio</h2>
                    </div>
                </header>

                <form method="POST" action="{{ route('evals.cfg.update', $evaluation) }}" data-details-form>
                    @csrf
                    @method('PUT')

                    <div class="details-card-body">
                        <div class="field-group">
                            <label class="form-label" for="evaluation-name">Evaluation name <span class="text-danger">*</span></label>
                            <input id="evaluation-name" type="text" name="name" maxlength="255" required
                                class="form-control form-control-lg @error('name') is-invalid @enderror"
                                value="{{ old('name', $evaluation->name) }}" placeholder="Give the form a clear, recognizable name"
                                {{ $canEdit ? '' : 'disabled' }} data-details-name>
                            @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <span class="field-help">Use a name evaluators will recognize in assignments and reports.</span>
                        </div>

                        <div class="field-group portfolio-field-wrap">
                            @include('evaluations.partials.portfolio-field')
                        </div>

                        <div class="field-group mb-0">
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <label class="form-label" for="evaluation-description">Description</label>
                                <span class="character-count"><strong data-description-count>0</strong>/1000</span>
                            </div>
                            <textarea id="evaluation-description" name="description" rows="6" maxlength="1000"
                                class="form-control @error('description') is-invalid @enderror"
                                placeholder="Explain the purpose, scope, and any guidance evaluators should know."
                                {{ $canEdit ? '' : 'disabled' }} data-details-description>{{ old('description', $evaluation->description) }}</textarea>
                            @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            <span class="field-help">Section-specific instructions belong beside the relevant section in the builder.</span>
                        </div>
                    </div>

                    <footer class="details-form-footer">
                        <div class="change-state" data-change-state>
                            <i class="feather-check-circle" aria-hidden="true"></i>
                            <span>No unsaved changes</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('evals.cfg.show', $evaluation) }}" class="btn btn-light">Cancel</a>
                            @if ($canEdit)
                                <button type="submit" class="btn btn-primary" data-save-details>
                                    <i class="feather-save me-1" aria-hidden="true"></i>Save and return to builder
                                </button>
                            @endif
                        </div>
                    </footer>
                </form>
            </main>

            <aside class="details-sidebar" aria-label="Evaluation summary">
                <section class="summary-card">
                    <header>
                        <span class="section-icon" aria-hidden="true"><i class="feather-layers"></i></span>
                        <div><span class="details-kicker">Live summary</span><h2>Your form at a glance</h2></div>
                    </header>
                    <div class="summary-name" data-summary-name>{{ old('name', $evaluation->name) }}</div>
                    <div class="summary-stats">
                        <div><strong>{{ $sectionCount }}</strong><span>Sections</span></div>
                        <div><strong>{{ $questionCount }}</strong><span>Questions</span></div>
                        <div>
                            <strong>{{ $maximumPoints !== null ? number_format($maximumPoints, 2) : 'Category' }}</strong>
                            <span>{{ $maximumPoints !== null ? 'Maximum points' : 'Response model' }}</span>
                        </div>
                    </div>
                    <dl class="summary-list">
                        <div><dt>Method</dt><dd>{{ $evaluation->typeLabel() }}</dd></div>
                        <div><dt>Portfolio</dt><dd data-summary-portfolio>{{ $currentPortfolio?->name ?? 'Not selected' }}</dd></div>
                        <div><dt>Status</dt><dd>{{ ucfirst($evaluation->status) }}</dd></div>
                    </dl>
                </section>

                <section class="locked-method-card">
                    <span class="locked-icon" aria-hidden="true"><i class="feather-shield"></i></span>
                    <div>
                        <strong>{{ $evaluation->typeLabel() }} response model</strong>
                        <p>
                            @if ($evaluation->usesNumericScoring())
                                Evaluators enter a numeric score and supporting evidence response for every question.
                            @elseif ($evaluation->isEoi())
                                Evaluators choose Qualified, Average Qualified, or Not Qualified and add an evidence comment for every question.
                            @else
                                Evaluators answer each compliance question with Yes or No and add an evidence comment.
                            @endif
                        </p>
                        <span><i class="feather-lock me-1" aria-hidden="true"></i>The evaluation type is fixed after creation.</span>
                    </div>
                </section>

                <a href="{{ route('evals.cfg.show', $evaluation) }}" class="builder-link-card">
                    <span><i class="feather-git-branch" aria-hidden="true"></i></span>
                    <div><strong>Continue designing the form</strong><small>Add sections, nested levels, subtotals, and questions.</small></div>
                    <i class="feather-arrow-right" aria-hidden="true"></i>
                </a>
            </aside>
        </div>
    </div>

    @include('evaluations.partials.hierarchy-theme')

    <style>
        .evaluation-details-page { --details-ink: #172033; --details-muted: #667085; --details-border: #e2e7ef; color: var(--details-ink); padding-bottom: 3rem; }
        .details-hero { display: flex; align-items: flex-start; justify-content: space-between; gap: 2rem; margin-bottom: 1.1rem; padding: 1.55rem 1.7rem; color: #fff; border-radius: 18px; background: radial-gradient(circle at 86% 14%, rgba(255,255,255,.18), transparent 27%), linear-gradient(126deg, #17296b 0%, #3157d5 58%, #4b72ea 100%); box-shadow: 0 18px 42px rgba(35,68,178,.18); }
        .details-breadcrumb { display: flex; align-items: center; gap: .35rem; margin-bottom: 1.05rem; color: rgba(255,255,255,.68); font-size: .72rem; }
        .details-breadcrumb a { color: #fff; text-decoration: none; }
        .details-title-row { display: flex; align-items: flex-start; gap: .85rem; }
        .details-title-icon, .section-icon { display: grid; flex: 0 0 auto; place-items: center; }
        .details-title-icon { width: 46px; height: 46px; border: 1px solid rgba(255,255,255,.2); border-radius: 13px; background: rgba(255,255,255,.12); font-size: 1.15rem; }
        .details-badges { display: flex; align-items: center; gap: .45rem; margin-bottom: .35rem; }
        .details-type-badge, .details-status { display: inline-flex; align-items: center; gap: .35rem; padding: .26rem .52rem; border-radius: 999px; font-size: .65rem; font-weight: 750; letter-spacing: .04em; text-transform: uppercase; }
        .details-type-badge { color: #203b98; background: #fff; }
        .details-status { background: rgba(255,255,255,.13); }
        .details-status i { width: 6px; height: 6px; border-radius: 50%; background: #ffd369; }
        .details-status-active i { background: #77e1a7; }
        .details-hero h1 { margin: 0; color: #fff; font-size: clamp(1.35rem, 2vw, 1.9rem); font-weight: 760; letter-spacing: -.02em; }
        .details-hero p { max-width: 720px; margin: .35rem 0 0; color: rgba(255,255,255,.76); font-size: .82rem; }
        .details-hero > .btn { flex: 0 0 auto; margin-top: 1.8rem; border-radius: 9px; font-weight: 650; }
        .details-alert { display: flex; align-items: flex-start; gap: .7rem; border: 0; border-radius: 12px; }
        .details-layout { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 1rem; align-items: start; }
        .details-card, .summary-card, .locked-method-card, .builder-link-card { border: 1px solid var(--details-border); border-radius: 15px; background: #fff; box-shadow: 0 7px 24px rgba(20,34,66,.055); }
        .details-card { overflow: hidden; }
        .details-card-header, .summary-card header { display: flex; align-items: center; gap: .7rem; padding: 1rem 1.15rem; border-bottom: 1px solid var(--details-border); }
        .section-icon { width: 37px; height: 37px; color: #3157d5; border-radius: 10px; background: #edf1ff; }
        .details-kicker { display: block; color: #3157d5; font-size: .61rem; font-weight: 780; letter-spacing: .08em; text-transform: uppercase; }
        .details-card h2, .summary-card h2 { margin: .1rem 0 0; font-size: .92rem; font-weight: 750; }
        .details-card-body { padding: 1.2rem; }
        .field-group { margin-bottom: 1.2rem; }
        .field-group .form-label { margin-bottom: .35rem; color: #344054; font-size: .76rem; font-weight: 700; }
        .field-group .form-control, .field-group .form-select { border-color: #d7dde7; border-radius: 9px; font-size: .82rem; }
        .field-group .form-control:focus, .field-group .form-select:focus { border-color: #829aeb; box-shadow: 0 0 0 3px rgba(49,87,213,.09); }
        .field-help, .portfolio-field-wrap small { display: block; margin-top: .38rem; color: var(--details-muted); font-size: .68rem; }
        .portfolio-field-wrap > .mb-3 { margin-bottom: 0 !important; }
        .character-count { color: var(--details-muted); font-size: .67rem; }
        .details-form-footer { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .9rem 1.15rem; border-top: 1px solid var(--details-border); background: #fafbfc; }
        .change-state { display: flex; align-items: center; gap: .4rem; color: #7a8494; font-size: .69rem; }
        .change-state.is-dirty { color: #a55c0a; }
        .details-form-footer .btn { border-radius: 9px; font-weight: 650; }
        .details-sidebar { display: grid; gap: .85rem; }
        .summary-card { overflow: hidden; }
        .summary-name { padding: 1rem 1rem .2rem; overflow-wrap: anywhere; font-size: .88rem; font-weight: 750; }
        .summary-stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: .4rem; padding: .7rem 1rem; }
        .summary-stats div { min-width: 0; padding: .65rem .35rem; border-radius: 9px; background: #f5f7fb; text-align: center; }
        .summary-stats strong { display: block; overflow: hidden; color: #233e99; font-size: .87rem; text-overflow: ellipsis; }
        .summary-stats span { display: block; margin-top: .13rem; color: var(--details-muted); font-size: .58rem; }
        .summary-list { margin: .15rem 1rem 1rem; }
        .summary-list > div { display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; padding: .62rem 0; border-bottom: 1px dashed #e4e8ef; }
        .summary-list > div:last-child { border: 0; }
        .summary-list dt { color: var(--details-muted); font-size: .67rem; font-weight: 600; }
        .summary-list dd { max-width: 62%; margin: 0; font-size: .7rem; font-weight: 700; text-align: right; }
        .locked-method-card { display: flex; align-items: flex-start; gap: .7rem; padding: .9rem; background: #fbfcff; }
        .locked-icon { display: grid; flex: 0 0 34px; width: 34px; height: 34px; place-items: center; color: #3157d5; border-radius: 9px; background: #eaf0ff; }
        .locked-method-card strong { display: block; font-size: .76rem; }
        .locked-method-card p { margin: .25rem 0 .45rem; color: var(--details-muted); font-size: .67rem; line-height: 1.45; }
        .locked-method-card span:last-child { color: #7a8494; font-size: .62rem; }
        .builder-link-card { display: grid; grid-template-columns: 38px minmax(0, 1fr) auto; align-items: center; gap: .65rem; padding: .85rem; color: var(--details-ink); text-decoration: none; transition: border-color .16s ease, transform .16s ease, box-shadow .16s ease; }
        .builder-link-card:hover { color: var(--details-ink); border-color: #9eb0ea; transform: translateY(-1px); box-shadow: 0 10px 26px rgba(49,87,213,.1); }
        .builder-link-card > span { display: grid; width: 38px; height: 38px; place-items: center; color: #3157d5; border-radius: 10px; background: #edf1ff; }
        .builder-link-card strong, .builder-link-card small { display: block; }
        .builder-link-card strong { font-size: .74rem; }
        .builder-link-card small { margin-top: .15rem; color: var(--details-muted); font-size: .62rem; }
        @media (max-width: 1050px) { .details-layout { grid-template-columns: 1fr; } .details-sidebar { grid-template-columns: repeat(2, minmax(0,1fr)); } .summary-card { grid-row: span 2; } }
        @media (max-width: 720px) { .details-hero { flex-direction: column; padding: 1.2rem; } .details-hero > .btn { margin-top: 0; } .details-title-icon { display: none; } .details-sidebar { grid-template-columns: 1fr; } .summary-card { grid-row: auto; } .details-form-footer { align-items: stretch; flex-direction: column; } .details-form-footer > div:last-child { justify-content: flex-end; } }
        @media (max-width: 480px) { .summary-stats { grid-template-columns: 1fr; } .details-form-footer > div:last-child { flex-direction: column; } .details-form-footer .btn { width: 100%; } }
    </style>

    <script>
        (() => {
            const page = document.querySelector('[data-evaluation-details]');
            const form = page?.querySelector('[data-details-form]');
            if (!page || !form) return;

            const nameInput = form.querySelector('[data-details-name]');
            const description = form.querySelector('[data-details-description]');
            const portfolio = form.querySelector('[name="portfolio_id"]');
            const counter = page.querySelector('[data-description-count]');
            const state = page.querySelector('[data-change-state]');
            const initialSignature = JSON.stringify(Array.from(new FormData(form).entries()));

            const updatePreview = () => {
                page.querySelector('[data-summary-name]').textContent = nameInput?.value.trim() || 'Untitled evaluation';
                if (counter) counter.textContent = description?.value.length ?? 0;
                if (portfolio?.tagName === 'SELECT') {
                    page.querySelector('[data-summary-portfolio]').textContent = portfolio.selectedOptions[0]?.text || 'Not selected';
                }
                const changed = JSON.stringify(Array.from(new FormData(form).entries())) !== initialSignature;
                if (state) {
                    state.classList.toggle('is-dirty', changed);
                    state.querySelector('span').textContent = changed ? 'Unsaved changes' : 'No unsaved changes';
                }
            };

            form.addEventListener('input', updatePreview);
            form.addEventListener('change', updatePreview);
            form.addEventListener('submit', () => {
                const button = form.querySelector('[data-save-details]');
                if (!button) return;
                button.disabled = true;
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span>Saving...';
            });
            updatePreview();
        })();
    </script>
@endsection
