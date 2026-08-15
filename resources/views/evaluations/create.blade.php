@extends('layouts.app')

@section('title', 'Create Evaluation Template')

@push('styles')
    <style>
        .evaluation-create-page {
            --eval-border: #e2e8f0;
            --eval-muted: #64748b;
            --eval-ink: #0f172a;
            --eval-surface: #f8fafc;
        }

        .evaluation-create-page .page-title-icon,
        .evaluation-create-page .section-icon {
            align-items: center;
            background: #eef2ff;
            border-radius: 12px;
            color: #4f46e5;
            display: inline-flex;
            flex: 0 0 auto;
            justify-content: center;
        }

        .evaluation-create-page .page-title-icon {
            height: 44px;
            width: 44px;
        }

        .evaluation-create-page .section-icon {
            height: 36px;
            width: 36px;
        }

        .evaluation-create-page .form-card,
        .evaluation-create-page .review-card {
            border: 1px solid var(--eval-border);
            border-radius: 14px;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }

        .evaluation-create-page .form-card .card-header,
        .evaluation-create-page .review-card .card-header {
            background: #fff;
            border-bottom: 1px solid var(--eval-border);
            padding: 1rem 1.25rem;
        }

        .evaluation-create-page .form-card .card-body,
        .evaluation-create-page .review-card .card-body {
            padding: 1.25rem;
        }

        .evaluation-create-page .field-help {
            color: var(--eval-muted);
            display: block;
            font-size: 0.78rem;
            margin-top: 0.4rem;
        }

        .evaluation-create-page .evaluation-method-input {
            height: 1px;
            opacity: 0;
            pointer-events: none;
            position: absolute;
            width: 1px;
        }

        .evaluation-create-page .evaluation-method-card {
            --method-accent: #4f46e5;
            background: #fff;
            border: 2px solid var(--eval-border);
            border-radius: 14px;
            color: var(--eval-ink);
            cursor: pointer;
            display: flex;
            flex-direction: column;
            min-height: 100%;
            padding: 1rem;
            position: relative;
            transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
        }

        .evaluation-create-page .evaluation-method-card:hover {
            border-color: color-mix(in srgb, var(--method-accent) 55%, #fff);
            transform: translateY(-2px);
        }

        .evaluation-create-page .evaluation-method-input:checked + .evaluation-method-card {
            background: color-mix(in srgb, var(--method-accent) 5%, #fff);
            border-color: var(--method-accent);
            box-shadow: 0 8px 20px color-mix(in srgb, var(--method-accent) 16%, transparent);
        }

        .evaluation-create-page .evaluation-method-input:focus-visible + .evaluation-method-card {
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--method-accent) 20%, transparent);
            outline: 2px solid var(--method-accent);
            outline-offset: 2px;
        }

        .evaluation-create-page .method-icon {
            align-items: center;
            background: color-mix(in srgb, var(--method-accent) 12%, #fff);
            border-radius: 10px;
            color: var(--method-accent);
            display: inline-flex;
            font-size: 1.05rem;
            height: 38px;
            justify-content: center;
            width: 38px;
        }

        .evaluation-create-page .method-check {
            align-items: center;
            background: var(--method-accent);
            border-radius: 50%;
            color: #fff;
            display: inline-flex;
            height: 24px;
            justify-content: center;
            opacity: 0;
            transform: scale(0.75);
            transition: opacity 160ms ease, transform 160ms ease;
            width: 24px;
        }

        .evaluation-create-page .evaluation-method-input:checked + .evaluation-method-card .method-check {
            opacity: 1;
            transform: scale(1);
        }

        .evaluation-create-page .method-copy {
            color: var(--eval-muted);
            font-size: 0.82rem;
            line-height: 1.45;
        }

        .evaluation-create-page .eoi-scale-compact {
            display: grid;
            gap: 0.35rem;
            margin-top: auto;
            padding-top: 0.85rem;
        }

        .evaluation-create-page .eoi-scale-compact span {
            align-items: center;
            color: #475569;
            display: flex;
            font-size: 0.72rem;
            gap: 0.4rem;
        }

        .evaluation-create-page .eoi-scale-compact strong {
            align-items: center;
            background: #dcfce7;
            border-radius: 999px;
            color: #166534;
            display: inline-flex;
            height: 20px;
            justify-content: center;
            width: 20px;
        }

        .evaluation-create-page .method-guidance {
            background: var(--eval-surface);
            border: 1px solid var(--eval-border);
            border-left: 4px solid #4f46e5;
            border-radius: 10px;
            color: #475569;
            font-size: 0.85rem;
            line-height: 1.5;
            padding: 0.85rem 1rem;
        }

        .evaluation-create-page .review-card {
            background: #fff;
        }

        .evaluation-create-page .review-name {
            color: var(--eval-ink);
            font-size: 1rem;
            font-weight: 700;
            overflow-wrap: anywhere;
        }

        .evaluation-create-page .review-row {
            align-items: flex-start;
            border-bottom: 1px dashed var(--eval-border);
            display: flex;
            gap: 0.75rem;
            justify-content: space-between;
            padding: 0.8rem 0;
        }

        .evaluation-create-page .review-row:last-child {
            border-bottom: 0;
        }

        .evaluation-create-page .review-label {
            color: var(--eval-muted);
            font-size: 0.76rem;
            font-weight: 700;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .evaluation-create-page .review-value {
            color: #334155;
            font-size: 0.82rem;
            font-weight: 600;
            max-width: 62%;
            text-align: right;
        }

        .evaluation-create-page .eoi-outcomes {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 12px;
            padding: 0.9rem;
        }

        .evaluation-create-page .outcome-row {
            align-items: center;
            display: flex;
            gap: 0.65rem;
            padding: 0.35rem 0;
        }

        .evaluation-create-page .outcome-score {
            align-items: center;
            background: #15803d;
            border-radius: 8px;
            color: #fff;
            display: inline-flex;
            flex: 0 0 28px;
            font-size: 0.78rem;
            font-weight: 800;
            height: 28px;
            justify-content: center;
        }

        .evaluation-create-page .next-step {
            align-items: flex-start;
            display: flex;
            gap: 0.7rem;
        }

        .evaluation-create-page .next-step-number {
            align-items: center;
            background: #e0e7ff;
            border-radius: 50%;
            color: #4338ca;
            display: inline-flex;
            flex: 0 0 26px;
            font-size: 0.75rem;
            font-weight: 800;
            height: 26px;
            justify-content: center;
        }

        .evaluation-create-page .form-actions {
            background: var(--eval-surface);
            border-top: 1px solid var(--eval-border);
            padding: 1rem 1.25rem;
        }

        .evaluation-create-page .method-selection.is-invalid-group .evaluation-method-card {
            border-color: #dc3545;
        }

        @media (min-width: 1200px) {
            .evaluation-create-page .review-column {
                position: sticky;
                top: 90px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .evaluation-create-page .evaluation-method-card,
            .evaluation-create-page .method-check {
                transition: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $selectedType = old('type', 'services');
    @endphp

    <div class="nxl-container evaluation-create-page">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div class="d-flex align-items-start gap-3">
                <span class="page-title-icon" aria-hidden="true">
                    <i class="feather-clipboard"></i>
                </span>
                <div>
                    <h4 class="fw-bold mb-1">Create Evaluation Template</h4>
                    <p class="text-muted mb-0">
                        Define the template details and choose how evaluators will assess each submission.
                    </p>
                </div>
            </div>

            <a href="{{ route('evals.cfg.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1" aria-hidden="true"></i>
                Back to Evaluations
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger d-flex align-items-start gap-2 mb-4" role="alert">
                <i class="feather-alert-circle mt-1" aria-hidden="true"></i>
                <div>
                    <strong>We could not create this evaluation.</strong>
                    <div class="small mt-1">Review the highlighted fields and try again.</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('evals.cfg.store') }}" id="evaluationCreateForm">
            @csrf

            <div class="row g-4 align-items-start">
                <div class="col-xl-8">
                    <div class="card form-card mb-4">
                        <div class="card-header">
                            <div class="d-flex align-items-center gap-3">
                                <span class="section-icon" aria-hidden="true">
                                    <i class="feather-file-text"></i>
                                </span>
                                <div>
                                    <h5 class="mb-1 fw-bold">Template details</h5>
                                    <p class="text-muted small mb-0">Give this evaluation a clear identity and portfolio owner.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <div class="mb-3">
                                        <label for="evaluationName" class="form-label fw-semibold">
                                            Evaluation Name <span class="text-danger">*</span>
                                        </label>
                                        <input type="text" id="evaluationName" name="name"
                                            class="form-control @error('name') is-invalid @enderror"
                                            value="{{ old('name') }}"
                                            placeholder="e.g. Expression of Interest Assessment"
                                            autocomplete="off" maxlength="255" required autofocus>
                                        <small class="field-help">Use a name that will be easy to recognise when assigning evaluators.</small>
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>

                                <div class="col-lg-6" id="portfolioFieldContainer">
                                    @include('evaluations.partials.portfolio-field')
                                </div>

                                <div class="col-12">
                                    <div class="mb-0">
                                        <div class="d-flex justify-content-between align-items-center gap-2">
                                            <label for="evaluationDescription" class="form-label fw-semibold">
                                                Description
                                            </label>
                                            <span class="small text-muted" id="descriptionCounter" aria-live="polite">0 / 1,000</span>
                                        </div>
                                        <textarea id="evaluationDescription" name="description"
                                            class="form-control @error('description') is-invalid @enderror"
                                            rows="4" maxlength="1000"
                                            aria-describedby="evaluationDescriptionHelp descriptionCounter"
                                            placeholder="Describe the purpose, scope, and intended use of this evaluation.">{{ old('description') }}</textarea>
                                        <small class="field-help" id="evaluationDescriptionHelp">Optional guidance shown with the evaluation template.</small>
                                        @error('description')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card form-card">
                        <div class="card-header">
                            <div class="d-flex align-items-center gap-3">
                                <span class="section-icon" aria-hidden="true">
                                    <i class="feather-sliders"></i>
                                </span>
                                <div>
                                    <h5 class="mb-1 fw-bold">Evaluation method</h5>
                                    <p class="text-muted small mb-0">This choice controls how criteria and evaluator decisions are recorded.</p>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <fieldset class="border-0 m-0 p-0">
                                <legend class="form-label fw-semibold mb-3">
                                    Select a method <span class="text-danger">*</span>
                                </legend>

                                <div class="row g-3 method-selection @error('type') is-invalid-group @enderror">
                                    <div class="col-md-6 col-xl-4 position-relative">
                                        <input class="evaluation-method-input" type="radio" name="type"
                                            id="evaluationTypeServices" value="services"
                                            data-method-label="Services"
                                            @checked($selectedType === 'services') required>
                                        <label class="evaluation-method-card" for="evaluationTypeServices"
                                            style="--method-accent: #4f46e5;">
                                            <span class="d-flex justify-content-between align-items-start mb-3">
                                                <span class="method-icon" aria-hidden="true">
                                                    <i class="feather-bar-chart-2"></i>
                                                </span>
                                                <span class="method-check" aria-hidden="true">
                                                    <i class="feather-check"></i>
                                                </span>
                                            </span>
                                            <strong class="mb-1">Services</strong>
                                            <span class="method-copy mb-3">Score each criterion numerically against a defined maximum.</span>
                                            <span class="badge bg-primary-subtle text-primary align-self-start mt-auto">Numeric scoring</span>
                                        </label>
                                    </div>

                                    <div class="col-md-6 col-xl-4 position-relative">
                                        <input class="evaluation-method-input" type="radio" name="type"
                                            id="evaluationTypeGoods" value="goods"
                                            data-method-label="Goods"
                                            @checked($selectedType === 'goods') required>
                                        <label class="evaluation-method-card" for="evaluationTypeGoods"
                                            style="--method-accent: #d97706;">
                                            <span class="d-flex justify-content-between align-items-start mb-3">
                                                <span class="method-icon" aria-hidden="true">
                                                    <i class="feather-check-square"></i>
                                                </span>
                                                <span class="method-check" aria-hidden="true">
                                                    <i class="feather-check"></i>
                                                </span>
                                            </span>
                                            <strong class="mb-1">Goods</strong>
                                            <span class="method-copy mb-3">Record Yes or No compliance decisions with evaluator comments.</span>
                                            <span class="badge bg-warning-subtle text-warning align-self-start mt-auto">Compliance check</span>
                                        </label>
                                    </div>

                                    <div class="col-md-6 col-xl-4 position-relative">
                                        <input class="evaluation-method-input" type="radio" name="type"
                                            id="evaluationTypeEoi" value="eoi"
                                            data-method-label="Expression of Interest"
                                            @checked($selectedType === 'eoi') required>
                                        <label class="evaluation-method-card" for="evaluationTypeEoi"
                                            style="--method-accent: #059669;">
                                            <span class="d-flex justify-content-between align-items-start mb-3">
                                                <span class="method-icon" aria-hidden="true">
                                                    <i class="feather-users"></i>
                                                </span>
                                                <span class="method-check" aria-hidden="true">
                                                    <i class="feather-check"></i>
                                                </span>
                                            </span>
                                            <strong class="mb-1">Expression of Interest</strong>
                                            <span class="method-copy mb-2">Classify each EOI criterion using the fixed qualification scale.</span>
                                            <span class="eoi-scale-compact"
                                                aria-label="EOI scale: Qualified, Average Qualified, Not Qualified">
                                                <span><strong>1</strong> Qualified</span>
                                                <span><strong>2</strong> Average Qualified</span>
                                                <span><strong>3</strong> Not Qualified</span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                @error('type')
                                    <div class="text-danger small mt-2" role="alert">{{ $message }}</div>
                                @enderror
                            </fieldset>

                            <div class="method-guidance mt-3" id="methodGuidance" aria-live="polite">
                                <strong id="methodGuidanceTitle">Services evaluation</strong>
                                <div id="methodGuidanceText">Evaluators award points for each criterion and totals are calculated automatically.</div>
                            </div>
                        </div>

                        <div class="form-actions d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <span class="small text-muted">
                                <i class="feather-info me-1" aria-hidden="true"></i>
                                The template starts as a draft and can be configured before activation.
                            </span>
                            <div class="d-flex flex-wrap gap-2">
                                <a href="{{ route('evals.cfg.index') }}" class="btn btn-light border">Cancel</a>
                                <button type="submit" class="btn btn-primary px-4" id="createEvaluationButton">
                                    <i class="feather-arrow-right-circle me-1" aria-hidden="true"></i>
                                    <span>Create &amp; Configure</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <aside class="review-column" aria-label="Evaluation template review">
                        <div class="card review-card mb-4">
                            <div class="card-header">
                                <div class="d-flex align-items-center gap-3">
                                    <span class="section-icon" aria-hidden="true">
                                        <i class="feather-eye"></i>
                                    </span>
                                    <div>
                                        <h5 class="mb-1 fw-bold">Live review</h5>
                                        <p class="text-muted small mb-0">Check the setup before creating the template.</p>
                                    </div>
                                </div>
                            </div>

                            <div class="card-body" aria-live="polite">
                                <div class="review-name mb-2" id="reviewTemplateName">Untitled evaluation</div>
                                <span class="badge bg-secondary-subtle text-secondary border">Draft</span>

                                <div class="mt-3">
                                    <div class="review-row">
                                        <span class="review-label">Portfolio</span>
                                        <span class="review-value" id="reviewPortfolio">Not selected</span>
                                    </div>
                                    <div class="review-row">
                                        <span class="review-label">Method</span>
                                        <span class="review-value" id="reviewMethod">Services</span>
                                    </div>
                                    <div class="review-row">
                                        <span class="review-label">Result format</span>
                                        <span class="review-value" id="reviewResultFormat">Numeric score</span>
                                    </div>
                                </div>

                                <div class="eoi-outcomes mt-3 d-none" id="eoiOutcomeReview">
                                    <div class="small fw-bold text-success mb-1">Fixed EOI categories</div>
                                    <div class="outcome-row">
                                        <span class="outcome-score">1</span>
                                        <span class="small fw-semibold">Qualified</span>
                                    </div>
                                    <div class="outcome-row">
                                        <span class="outcome-score">2</span>
                                        <span class="small fw-semibold">Average Qualified</span>
                                    </div>
                                    <div class="outcome-row">
                                        <span class="outcome-score">3</span>
                                        <span class="small fw-semibold">Not Qualified</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card review-card">
                            <div class="card-body">
                                <h6 class="fw-bold mb-3">
                                    <i class="feather-navigation text-primary me-1" aria-hidden="true"></i>
                                    What happens next?
                                </h6>
                                <div class="d-grid gap-3">
                                    <div class="next-step">
                                        <span class="next-step-number">1</span>
                                        <div>
                                            <div class="small fw-semibold">Create the draft</div>
                                            <div class="small text-muted">Save these template settings.</div>
                                        </div>
                                    </div>
                                    <div class="next-step">
                                        <span class="next-step-number">2</span>
                                        <div>
                                            <div class="small fw-semibold">Build the structure</div>
                                            <div class="small text-muted">Add sections and evaluation criteria.</div>
                                        </div>
                                    </div>
                                    <div class="next-step">
                                        <span class="next-step-number">3</span>
                                        <div>
                                            <div class="small fw-semibold">Review and activate</div>
                                            <div class="small text-muted">Confirm the template before evaluator use.</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('evaluationCreateForm');
            if (!form) {
                return;
            }

            const nameInput = document.getElementById('evaluationName');
            const descriptionInput = document.getElementById('evaluationDescription');
            const descriptionCounter = document.getElementById('descriptionCounter');
            const methodInputs = Array.from(form.querySelectorAll('input[name="type"]'));
            const portfolioSelect = form.querySelector('select[name="portfolio_id"]');
            const portfolioHidden = form.querySelector('input[type="hidden"][name="portfolio_id"]');
            const reviewTemplateName = document.getElementById('reviewTemplateName');
            const reviewPortfolio = document.getElementById('reviewPortfolio');
            const reviewMethod = document.getElementById('reviewMethod');
            const reviewResultFormat = document.getElementById('reviewResultFormat');
            const eoiOutcomeReview = document.getElementById('eoiOutcomeReview');
            const methodGuidanceTitle = document.getElementById('methodGuidanceTitle');
            const methodGuidanceText = document.getElementById('methodGuidanceText');
            const createButton = document.getElementById('createEvaluationButton');

            const methodDetails = {
                services: {
                    label: 'Services',
                    result: 'Numeric score',
                    title: 'Services evaluation',
                    guidance: 'Evaluators award points for each criterion and totals are calculated automatically.',
                },
                goods: {
                    label: 'Goods',
                    result: 'Yes / No compliance',
                    title: 'Goods evaluation',
                    guidance: 'Evaluators record a Yes or No compliance decision and add a supporting comment for each criterion.',
                },
                eoi: {
                    label: 'Expression of Interest',
                    result: 'Three-level qualification',
                    title: 'Expression of Interest evaluation',
                    guidance: 'Each EOI criterion is classified as Qualified, Average Qualified, or Not Qualified.',
                },
            };

            const syncName = () => {
                const value = nameInput?.value.trim();
                reviewTemplateName.textContent = value || 'Untitled evaluation';
            };

            const syncDescriptionCounter = () => {
                const length = descriptionInput?.value.length || 0;
                descriptionCounter.textContent = `${length.toLocaleString()} / 1,000`;
            };

            const syncPortfolio = () => {
                if (portfolioSelect) {
                    const option = portfolioSelect.options[portfolioSelect.selectedIndex];
                    reviewPortfolio.textContent = option?.value ? option.text.trim() : 'Not selected';
                    return;
                }

                if (portfolioHidden) {
                    const lockedField = portfolioHidden.parentElement?.querySelector('.form-control');
                    reviewPortfolio.textContent = lockedField?.textContent.trim() || 'Assigned portfolio';
                }
            };

            const syncMethod = () => {
                const selected = methodInputs.find(input => input.checked);
                const details = methodDetails[selected?.value] || methodDetails.services;

                reviewMethod.textContent = details.label;
                reviewResultFormat.textContent = details.result;
                methodGuidanceTitle.textContent = details.title;
                methodGuidanceText.textContent = details.guidance;
                eoiOutcomeReview.classList.toggle('d-none', selected?.value !== 'eoi');
            };

            nameInput?.addEventListener('input', syncName);
            descriptionInput?.addEventListener('input', syncDescriptionCounter);
            portfolioSelect?.addEventListener('change', syncPortfolio);
            methodInputs.forEach(input => input.addEventListener('change', syncMethod));

            form.addEventListener('submit', () => {
                if (!form.checkValidity() || !createButton) {
                    return;
                }

                createButton.disabled = true;
                createButton.setAttribute('aria-disabled', 'true');
                createButton.innerHTML = `
                    <span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span>
                    <span>Creating...</span>
                `;
            });

            syncName();
            syncDescriptionCounter();
            syncPortfolio();
            syncMethod();
        });
    </script>
@endpush
