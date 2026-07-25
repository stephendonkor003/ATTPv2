@extends('layouts.app')

@section('title', 'Create Performance Report')
@section('lean_admin_scripts', '1')

@push('styles')
    <style>
        .me-report-create {
            --report-green: #0b5c45;
            --report-ink: #173c31;
            --report-muted: #64756f;
            --report-border: #dce8e3;
            max-width: 1120px;
            margin: 0 auto;
        }

        .me-report-create .report-hero {
            position: relative;
            overflow: hidden;
            padding: 1.5rem;
            border-radius: 1rem;
            color: #fff;
            background: linear-gradient(120deg, #073f30, #0b6d50);
            box-shadow: 0 16px 35px rgba(7, 63, 48, .16);
        }

        .me-report-create .report-hero::after {
            position: absolute;
            top: -75px;
            right: -45px;
            width: 210px;
            height: 210px;
            border: 32px solid rgba(255, 255, 255, .08);
            border-radius: 50%;
            content: "";
        }

        .me-report-create .report-hero p {
            max-width: 720px;
            margin: .45rem 0 0;
            color: rgba(255, 255, 255, .78);
        }

        .me-report-create .report-card {
            border: 1px solid var(--report-border);
            border-radius: 1rem;
            background: #fff;
            box-shadow: 0 10px 25px rgba(28, 65, 53, .06);
        }

        .me-report-create .report-step {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 2rem;
            height: 2rem;
            margin-right: .45rem;
            border-radius: 50%;
            color: #fff;
            background: var(--report-green);
            font-size: .78rem;
            font-weight: 800;
        }

        .me-report-create .form-label {
            color: var(--report-ink);
            font-weight: 750;
        }

        .me-report-create .form-select,
        .me-report-create .form-control {
            min-height: 46px;
            border-color: #cfddd7;
            border-radius: .7rem;
        }

        .me-report-create .form-select:focus,
        .me-report-create .form-control:focus {
            border-color: #2f8b6d;
            box-shadow: 0 0 0 .2rem rgba(47, 139, 109, .13);
        }

        .me-report-create .template-preview {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: .75rem;
            padding: 1rem;
            border: 1px solid #dbe9e3;
            border-radius: .85rem;
            background: #f6fbf8;
        }

        .me-report-create .preview-item small {
            display: block;
            margin-bottom: .18rem;
            color: var(--report-muted);
            font-size: .68rem;
            font-weight: 800;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .me-report-create .preview-item strong {
            display: block;
            color: var(--report-ink);
            line-height: 1.4;
        }

        .me-report-create .frequency-list {
            margin-top: .75rem;
            padding-top: .75rem;
            border-top: 1px solid var(--report-border);
            color: #496258;
            font-size: .8rem;
        }

        .me-report-create .btn-create-report {
            min-height: 46px;
            padding-inline: 1.2rem;
            border: 0;
            border-radius: .72rem;
            background: var(--report-green);
            font-weight: 750;
        }

        @media (max-width: 767.98px) {
            .me-report-create .template-preview {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="me-report-create">
            <header class="report-hero mb-4">
                <div class="position-relative" style="z-index: 1">
                    <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}" class="text-white-50 text-decoration-none small">
                        <i class="feather-arrow-left me-1" aria-hidden="true"></i>Performance reports
                    </a>
                    <h3 class="fw-bold mt-3 mb-0">Create a Performance Report</h3>
                    <p>Choose an approved reporting form and quarter. The report will include only indicators due under their approved reporting frequency.</p>
                </div>
            </header>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <div class="fw-bold mb-1">The report could not be created.</div>
                    <ul class="mb-0 ps-3">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="report-card p-3 p-lg-4">
                @if ($forms->isEmpty())
                    <div class="text-center py-5">
                        <i class="feather-file-text text-muted" style="font-size: 2.5rem" aria-hidden="true"></i>
                        <h5 class="mt-3">No eligible reporting form is available</h5>
                        <p class="text-muted">Publish a form that has a Project Component and linked performance indicators first.</p>
                        <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'forms', 'action' => 'create']) }}#data-entry-workspace" class="btn btn-primary">
                            Configure a reporting form
                        </a>
                    </div>
                @else
                    <form method="POST" action="{{ route('budget.me.performance-reports.store') }}" class="row g-4" id="performance-report-create-form">
                        @csrf

                        <div class="col-12">
                            <label for="report-form" class="form-label"><span class="report-step">1</span>Reporting form</label>
                            <select name="form_id" id="report-form" class="form-select @error('form_id') is-invalid @enderror" required>
                                <option value="">Select a published reporting form</option>
                                @foreach ($forms as $form)
                                    @php
                                        $frequencySummary = $form->indicators
                                            ->map(fn ($indicator) => $indicator->indicator_code.' — '.($indicator->frequency?->indicatorCadenceLabel() ?: 'Not configured'))
                                            ->values();
                                    @endphp
                                    <option
                                        value="{{ $form->id }}"
                                        data-portfolio="{{ $form->portfolio?->name }}"
                                        data-component="{{ $form->projectComponent?->name }}"
                                        data-directorate="{{ $form->projectComponent?->governanceNode?->name }}"
                                        data-indicator-count="{{ $form->indicators->count() }}"
                                        data-frequencies='@json($frequencySummary)'
                                        @selected(old('form_id', request('form_id')) === (string) $form->id)
                                    >{{ $form->code }} · {{ $form->title }}</option>
                                @endforeach
                            </select>
                            @error('form_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 d-none" id="template-preview" aria-live="polite">
                            <div class="template-preview">
                                <div class="preview-item"><small>Portfolio</small><strong data-preview="portfolio">—</strong></div>
                                <div class="preview-item"><small>Project Component</small><strong data-preview="component">—</strong></div>
                                <div class="preview-item"><small>Responsible Directorate</small><strong data-preview="directorate">—</strong></div>
                                <div class="preview-item">
                                    <small>Linked indicators</small>
                                    <strong><span data-preview="indicator-count">0</span> approved indicator(s)</strong>
                                </div>
                                <div class="preview-item frequency-list grid-column-full" style="grid-column: 1 / -1">
                                    <small>Approved reporting frequencies</small>
                                    <div data-preview="frequencies">—</div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="report-quarter" class="form-label"><span class="report-step">2</span>Reporting period</label>
                            <select name="reporting_quarter" id="report-quarter" class="form-select @error('reporting_quarter') is-invalid @enderror" required>
                                <option value="">Choose Q1, Q2, Q3 or Q4</option>
                                @foreach ($quarters as $value => $label)
                                    <option value="{{ $value }}" @selected(old('reporting_quarter') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('reporting_quarter')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-md-6">
                            <label for="report-year" class="form-label"><span class="report-step">3</span>Reporting year</label>
                            <input type="number" name="reporting_year" id="report-year" min="2000" max="2100" class="form-control @error('reporting_year') is-invalid @enderror" value="{{ old('reporting_year', $defaultYear) }}" required>
                            @error('reporting_year')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>

                        <div class="col-12 d-flex flex-wrap justify-content-end gap-2 pt-2">
                            <a href="{{ route('budget.me.rebuild.data-entry', ['tab' => 'reports']) }}" class="btn btn-light border">Cancel</a>
                            <button type="submit" class="btn btn-primary btn-create-report">
                                <i class="feather-plus-circle me-1" aria-hidden="true"></i>Create Report
                            </button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const formSelect = document.getElementById('report-form');
            const preview = document.getElementById('template-preview');
            if (!formSelect || !preview) {
                return;
            }

            const updatePreview = () => {
                const option = formSelect.options[formSelect.selectedIndex];
                const selected = Boolean(option?.value);
                preview.classList.toggle('d-none', !selected);
                if (!selected) {
                    return;
                }

                ['portfolio', 'component', 'directorate', 'indicator-count'].forEach((key) => {
                    const output = preview.querySelector(`[data-preview="${key}"]`);
                    if (output) {
                        output.textContent = option.dataset[key] || 'Not assigned';
                    }
                });

                const frequencyOutput = preview.querySelector('[data-preview="frequencies"]');
                if (frequencyOutput) {
                    try {
                        const frequencies = JSON.parse(option.dataset.frequencies || '[]');
                        frequencyOutput.textContent = frequencies.length ? frequencies.join(' • ') : 'No approved frequency';
                    } catch (error) {
                        frequencyOutput.textContent = 'Frequency details unavailable';
                    }
                }
            };

            formSelect.addEventListener('change', updatePreview);
            updatePreview();
        });
    </script>
@endpush
