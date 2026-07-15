@extends('layouts.app')

@section('title', 'Results Framework and Indicator Management')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.indicators.partials.styles')
@endpush

@section('content')
    @php
        $showIndicatorForm = ($showForm ?? false)
            || request()->boolean('create')
            || (bool) $editingIndicator
            || $errors->any();
        $summary = $summary ?? [
            'total' => $indicators->total(),
            'complete' => 0,
            'needs_attention' => $indicators->total(),
        ];
        $completeIndicators = (int) ($summary['complete'] ?? $summary['with_target'] ?? 0);
        $indicatorsNeedingAttention = (int) ($summary['needs_attention'] ?? $summary['without_target'] ?? 0);
    @endphp

    <main class="me-results-framework nxl-container">
        <header class="me-hero">
            <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-4">
                <div>
                    <div class="me-eyebrow"><i class="feather-target" aria-hidden="true"></i> Monitoring &amp; Evaluation</div>
                    <h1>Results Framework and Indicator Management</h1>
                    <p>
                        Define each indicator once, including its measurement, baseline, target, reporting source and accountable person.
                        This register is the foundation for later data entry, quality review and reporting.
                    </p>
                </div>

                @can('me.configuration.manage')
                    @unless ($showIndicatorForm)
                        <div class="me-hero-actions">
                            <a href="{{ route('budget.me.indicators.index', ['create' => 1]) }}#indicator-form" class="me-primary-action">
                                <i class="feather-plus" aria-hidden="true"></i> Add indicator
                            </a>
                        </div>
                    @endunless
                @endcan
            </div>
        </header>

        <section class="me-summary-grid" aria-label="Indicator register summary">
            <article class="me-summary-card">
                <span class="me-summary-icon"><i class="feather-list" aria-hidden="true"></i></span>
                <div>
                    <div class="me-summary-value">{{ number_format((int) ($summary['total'] ?? 0)) }}</div>
                    <div class="me-summary-label">Indicators in the framework</div>
                </div>
            </article>
            <article class="me-summary-card">
                <span class="me-summary-icon"><i class="feather-check-circle" aria-hidden="true"></i></span>
                <div>
                    <div class="me-summary-value">{{ number_format($completeIndicators) }}</div>
                    <div class="me-summary-label">Complete measurement profiles</div>
                </div>
            </article>
            <article class="me-summary-card">
                <span class="me-summary-icon"><i class="feather-alert-circle" aria-hidden="true"></i></span>
                <div>
                    <div class="me-summary-value">{{ number_format($indicatorsNeedingAttention) }}</div>
                    <div class="me-summary-label">Profiles needing attention</div>
                </div>
            </article>
        </section>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm" role="status">
                <i class="feather-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger border-0 shadow-sm" role="alert">
                <i class="feather-alert-triangle me-2" aria-hidden="true"></i>{{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm" role="alert" tabindex="-1" id="indicator-validation-summary">
                <div class="fw-bold mb-2"><i class="feather-alert-triangle me-1"></i> Please correct the indicator information below.</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('me.configuration.manage')
            @if ($showIndicatorForm)
                @include('me.indicators.partials.form')
            @endif
        @endcan

        @include('me.indicators.partials.register')
    </main>

    @can('me.configuration.manage')
        @if ($showIndicatorForm)
            @include('me.indicators.partials.inline-config-modals')
        @endif
    @endcan
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const indicatorForm = document.querySelector('[data-indicator-form]');
            const portfolioSelect = indicatorForm?.querySelector('[data-indicator-portfolio]');

            if (indicatorForm && portfolioSelect instanceof HTMLSelectElement) {
                const ownerSelect = indicatorForm.querySelector('[data-indicator-owner]');
                const dependentSelects = Array.from(indicatorForm.querySelectorAll('[data-indicator-portfolio-dependent]'));
                const scopeStatus = indicatorForm.querySelector('[data-indicator-scope-status]');
                const inlineCreateLinks = Array.from(indicatorForm.querySelectorAll('[data-inline-config-open]'));

                const filterOptions = (select, portfolioId, clearInvalid) => {
                    if (!(select instanceof HTMLSelectElement)) return 0;

                    const selectedOption = select.selectedOptions[0] || null;
                    let available = 0;

                    Array.from(select.options).forEach((option, index) => {
                        if (index === 0 || option.value === '') {
                            option.hidden = false;
                            option.disabled = false;
                            return;
                        }

                        const matches = portfolioId !== '' && option.dataset.portfolioId === portfolioId;
                        option.hidden = !matches;
                        option.disabled = !matches;
                        if (matches) available += 1;
                    });

                    Array.from(select.querySelectorAll('optgroup')).forEach((group) => {
                        const hasAvailableOption = Array.from(group.querySelectorAll('option'))
                            .some((option) => !option.disabled && !option.hidden);
                        group.hidden = !hasAvailableOption;
                        group.disabled = !hasAvailableOption;
                    });

                    if (clearInvalid && selectedOption?.value && selectedOption.dataset.portfolioId !== portfolioId) {
                        select.value = '';
                    }

                    select.disabled = portfolioId === '';
                    return available;
                };

                const filterIndicatorPortfolioFields = (clearInvalid = false) => {
                    const portfolioId = portfolioSelect.value;
                    const ownerCount = filterOptions(ownerSelect, portfolioId, clearInvalid);
                    const dependentCounts = dependentSelects.map((select) => filterOptions(select, portfolioId, clearInvalid));

                    if (ownerSelect instanceof HTMLSelectElement) {
                        ownerSelect.options[0].textContent = portfolioId === ''
                            ? 'Select a portfolio first'
                            : 'Use the selected portfolio';
                    }

                    dependentSelects.forEach((select) => {
                        if (select.options[0]) {
                            const itemName = select.id === 'indicator-unit' ? 'unit' : 'frequency';
                            select.options[0].textContent = portfolioId === ''
                                ? 'Select a portfolio first'
                                : `Select ${itemName} for this portfolio`;
                        }
                    });

                    inlineCreateLinks.forEach((link) => {
                        link.setAttribute('aria-disabled', portfolioId === '' ? 'true' : 'false');
                    });

                    if (scopeStatus) {
                        scopeStatus.textContent = portfolioId === ''
                            ? 'Select a portfolio to load its hierarchy, units and reporting frequencies.'
                            : `${ownerCount} hierarchy ${ownerCount === 1 ? 'item' : 'items'}, ${dependentCounts[0] || 0} units and ${dependentCounts[1] || 0} reporting frequencies are available.`;
                    }
                };

                portfolioSelect.addEventListener('change', () => filterIndicatorPortfolioFields(true));

                inlineCreateLinks.forEach((link) => {
                    link.addEventListener('click', (event) => {
                        if (!portfolioSelect.value) {
                            event.preventDefault();
                            event.stopImmediatePropagation();
                            portfolioSelect.focus();
                            return;
                        }

                        const kind = link.dataset.inlineConfigOpen;
                        const modal = kind
                            ? document.querySelector(`[data-inline-config-modal="${kind}"]`)
                            : null;
                        const modalPortfolio = modal?.querySelector('[name="portfolio_id"]');

                        if (modalPortfolio instanceof HTMLSelectElement) {
                            Array.from(modalPortfolio.options).forEach((option) => {
                                const selected = option.value === portfolioSelect.value;
                                option.disabled = option.value !== '' && !selected;
                                option.hidden = option.value !== '' && !selected;
                            });
                            modalPortfolio.value = portfolioSelect.value;
                            modalPortfolio.dataset.portfolioLockedByIndicator = 'true';
                            modalPortfolio.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                });

                filterIndicatorPortfolioFields(false);
            }

            document.querySelectorAll('[data-delete-indicator-form]').forEach((form) => {
                form.addEventListener('submit', (event) => {
                    if (!window.confirm('Delete this indicator? Existing targets and reported results linked to it will also be removed.')) {
                        event.preventDefault();
                    }
                });
            });

            const firstInvalid = document.querySelector('.me-results-framework .is-invalid');
            if (firstInvalid) {
                firstInvalid.focus({ preventScroll: true });
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>

    @include('me.indicators.partials.inline-config-script')
@endpush
