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
            'total' => $indicators->count(),
            'complete' => 0,
            'needs_attention' => $indicators->count(),
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

@can('me.configuration.manage')
    @push('modals')
        @include('me.indicators.partials.disaggregation-modal')
    @endpush
@endcan

@push('scripts')
    <script src="{{ asset('admin/assets/vendors/js/dataTables.min.js') }}"></script>
    <script src="{{ asset('admin/assets/vendors/js/dataTables.bs5.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const table = document.querySelector('[data-indicator-register-table]');
            const toolbar = document.querySelector('[data-indicator-table-toolbar]');
            if (!(table instanceof HTMLTableElement) || !(toolbar instanceof HTMLElement)) {
                return;
            }

            const search = toolbar.querySelector('[data-indicator-table-search]');
            const filters = Array.from(toolbar.querySelectorAll('[data-indicator-table-filter]'));
            const length = toolbar.querySelector('[data-indicator-table-length]');
            const clear = toolbar.querySelector('[data-indicator-table-clear]');
            const count = toolbar.querySelector('[data-indicator-table-count]');
            const mobileCards = Array.from(document.querySelectorAll('[data-indicator-mobile-card]'));
            const normalize = (value) => String(value || '').trim().toLowerCase();
            const selectedFilters = () => Object.fromEntries(
                filters.map((control) => [control.dataset.indicatorTableFilter, String(control.value || '')])
            );
            const rowMatchesFilters = (row, selected) => Object.entries(selected)
                .every(([key, value]) => value === '' || String(row?.dataset?.[key] || '') === value);

            const filterMobileCards = () => {
                const query = normalize(search?.value);
                const selected = selectedFilters();
                let visible = 0;

                mobileCards.forEach((card) => {
                    const matches = rowMatchesFilters(card, selected)
                        && (query === '' || normalize(card.dataset.search).includes(query));
                    card.hidden = !matches;
                    if (matches) visible += 1;
                });

                if (!window.jQuery?.fn?.DataTable && count) {
                    count.textContent = `${visible} ${visible === 1 ? 'indicator' : 'indicators'}`;
                }
            };

            const dataTables = window.jQuery?.fn?.DataTable;
            if (!dataTables) {
                const fallbackRows = Array.from(table.tBodies[0]?.rows || []);
                const applyFallback = () => {
                    const query = normalize(search?.value);
                    const selected = selectedFilters();
                    let visible = 0;
                    fallbackRows.forEach((row) => {
                        const matches = rowMatchesFilters(row, selected)
                            && (query === '' || normalize(row.textContent).includes(query));
                        row.hidden = !matches;
                        if (matches) visible += 1;
                    });
                    if (count) count.textContent = `${visible} ${visible === 1 ? 'indicator' : 'indicators'}`;
                    filterMobileCards();
                };
                search?.addEventListener('input', applyFallback);
                filters.forEach((control) => control.addEventListener('change', applyFallback));
                clear?.addEventListener('click', () => {
                    if (search) search.value = '';
                    filters.forEach((control) => { control.value = ''; });
                    applyFallback();
                });
                applyFallback();
                return;
            }

            const $ = window.jQuery;
            const customFilter = (settings, _data, dataIndex) => {
                if (settings.nTable !== table) return true;
                const row = settings.aoData[dataIndex]?.nTr;
                return rowMatchesFilters(row, selectedFilters());
            };
            $.fn.dataTable.ext.search.push(customFilter);

            const dataTable = $(table).DataTable({
                autoWidth: false,
                pageLength: 10,
                lengthChange: false,
                order: [[0, 'asc']],
                dom: 't<"me-dt-footer"ip>',
                columnDefs: [
                    { targets: 4, orderable: false, searchable: false },
                ],
                language: {
                    emptyTable: 'No indicators are available.',
                    info: 'Showing _START_ to _END_ of _TOTAL_ indicators',
                    infoEmpty: 'Showing 0 indicators',
                    infoFiltered: '(filtered from _MAX_)',
                    paginate: { previous: 'Previous', next: 'Next' },
                    zeroRecords: 'No indicators match the selected filters.',
                },
            });

            const updateCount = () => {
                const visible = dataTable.page.info().recordsDisplay;
                if (count) {
                    count.textContent = `${visible} ${visible === 1 ? 'indicator' : 'indicators'} matched`;
                }
                filterMobileCards();
            };
            const applyFilters = () => {
                dataTable.search(String(search?.value || '')).draw();
            };

            let searchTimer = null;
            search?.addEventListener('input', () => {
                window.clearTimeout(searchTimer);
                searchTimer = window.setTimeout(applyFilters, 120);
            });
            filters.forEach((control) => control.addEventListener('change', applyFilters));
            length?.addEventListener('change', () => {
                dataTable.page.len(Number(length.value)).draw();
            });
            clear?.addEventListener('click', () => {
                if (search) search.value = '';
                filters.forEach((control) => { control.value = ''; });
                if (length) length.value = '10';
                dataTable.page.len(10).search('').draw();
                search?.focus();
            });
            dataTable.on('draw', updateCount);
            dataTable.on('destroy', () => {
                const index = $.fn.dataTable.ext.search.indexOf(customFilter);
                if (index >= 0) $.fn.dataTable.ext.search.splice(index, 1);
            });
            window.addEventListener('resize', () => dataTable.columns.adjust());

            applyFilters();
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const indicatorForm = document.querySelector('[data-indicator-form]');
            const portfolioSelect = indicatorForm?.querySelector('[data-indicator-portfolio]');

            if (indicatorForm && portfolioSelect instanceof HTMLSelectElement) {
                const ownerSelect = indicatorForm.querySelector('[data-indicator-owner]');
                const componentSelect = indicatorForm.querySelector('[data-indicator-component]');
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

                const synchronizeComponentWithOwner = () => {
                    if (!(ownerSelect instanceof HTMLSelectElement)
                        || !(componentSelect instanceof HTMLSelectElement)) {
                        return;
                    }

                    const componentId = ownerSelect.selectedOptions[0]?.dataset.componentId || '';
                    const isLocked = componentId !== '';

                    if (isLocked) {
                        componentSelect.value = componentId;
                    }

                    componentSelect.classList.toggle('bg-light', isLocked);
                    componentSelect.style.pointerEvents = isLocked ? 'none' : '';
                    componentSelect.tabIndex = isLocked ? -1 : 0;
                    componentSelect.setAttribute('aria-disabled', isLocked ? 'true' : 'false');
                };

                const filterIndicatorPortfolioFields = (clearInvalid = false) => {
                    const portfolioId = portfolioSelect.value;
                    const ownerCount = filterOptions(ownerSelect, portfolioId, clearInvalid);
                    const dependentCounts = dependentSelects.reduce((counts, select) => {
                        const kind = select.dataset.dependentKind || 'items';
                        counts[kind] = filterOptions(select, portfolioId, clearInvalid);
                        return counts;
                    }, {});

                    synchronizeComponentWithOwner();

                    if (ownerSelect instanceof HTMLSelectElement) {
                        ownerSelect.options[0].textContent = portfolioId === ''
                            ? 'Select a portfolio first'
                            : 'Use the selected portfolio';
                    }

                    dependentSelects.forEach((select) => {
                        if (select.options[0]) {
                            const itemNames = {
                                components: 'project component',
                                units: 'unit',
                                frequencies: 'reporting frequency',
                                evidence: 'repository evidence',
                            };
                            const itemName = itemNames[select.dataset.dependentKind] || 'item';
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
                            ? 'Select a portfolio to load its hierarchy, components, evidence, units and reporting frequencies.'
                            : `${ownerCount} hierarchy items, ${dependentCounts.components || 0} components, ${dependentCounts.evidence || 0} evidence items, ${dependentCounts.units || 0} units and ${dependentCounts.frequencies || 0} reporting frequencies are available.`;
                    }
                };

                portfolioSelect.addEventListener('change', () => filterIndicatorPortfolioFields(true));
                ownerSelect?.addEventListener('change', synchronizeComponentWithOwner);

                indicatorForm.querySelectorAll('[data-indicator-dimension-use]').forEach((useControl) => {
                    const requiredControl = useControl.closest('.border')
                        ?.querySelector('[data-indicator-dimension-required]');
                    if (!(useControl instanceof HTMLInputElement)
                        || !(requiredControl instanceof HTMLInputElement)) {
                        return;
                    }

                    useControl.addEventListener('change', () => {
                        requiredControl.disabled = !useControl.checked;
                        requiredControl.checked = useControl.checked;
                    });
                });

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

            document.addEventListener('submit', (event) => {
                if (event.target instanceof HTMLFormElement
                    && event.target.matches('[data-delete-indicator-form]')) {
                    if (!window.confirm('Delete this indicator? Existing targets and reported results linked to it will also be removed.')) {
                        event.preventDefault();
                    }
                }
            });

            const disaggregationModalElement = document.getElementById('indicatorDisaggregationModal');
            const disaggregationForm = disaggregationModalElement?.querySelector('[data-disaggregation-form]');
            if (disaggregationModalElement
                && disaggregationForm instanceof HTMLFormElement
                && window.bootstrap?.Modal) {
                const modal = window.bootstrap.Modal.getOrCreateInstance(disaggregationModalElement);
                const indicatorName = disaggregationModalElement.querySelector('[data-disaggregation-indicator-name]');

                disaggregationModalElement.addEventListener('show.bs.modal', () => {
                    document.body.classList.add('me-disaggregation-modal-open');
                });

                disaggregationModalElement.addEventListener('hidden.bs.modal', () => {
                    document.body.classList.remove('me-disaggregation-modal-open');
                });

                const values = (raw) => new Set((raw || '').split(',').map((value) => value.trim()).filter(Boolean));
                const synchronizeDimensionRow = (dimensionId) => {
                    const use = disaggregationForm.querySelector(`[data-dimension-use="${dimensionId}"]`);
                    const required = disaggregationForm.querySelector(`[data-dimension-required="${dimensionId}"]`);
                    const numeric = disaggregationForm.querySelector(`[data-dimension-numeric="${dimensionId}"]`);
                    if (!(use instanceof HTMLInputElement)) return;
                    [required, numeric].forEach((control) => {
                        if (control instanceof HTMLInputElement) {
                            control.disabled = !use.checked;
                            if (!use.checked) control.checked = false;
                        }
                    });
                };

                disaggregationForm.querySelectorAll('[data-dimension-use]').forEach((control) => {
                    control.addEventListener('change', () => synchronizeDimensionRow(control.dataset.dimensionUse));
                });

                document.addEventListener('click', (event) => {
                    const button = event.target instanceof Element
                        ? event.target.closest('[data-disaggregation-open]')
                        : null;
                    if (!(button instanceof HTMLElement)) return;

                    disaggregationForm.action = button.dataset.action || '#';
                    if (indicatorName) {
                        indicatorName.textContent = button.dataset.indicatorName || '';
                    }
                    const selected = values(button.dataset.dimensions);
                    const required = values(button.dataset.requiredDimensions);
                    const numeric = values(button.dataset.numericDimensions);
                    disaggregationForm.querySelectorAll('[data-dimension-use]').forEach((control) => {
                        const id = control.dataset.dimensionUse;
                        control.checked = selected.has(id);
                        const requiredControl = disaggregationForm.querySelector(`[data-dimension-required="${id}"]`);
                        const numericControl = disaggregationForm.querySelector(`[data-dimension-numeric="${id}"]`);
                        if (requiredControl instanceof HTMLInputElement) requiredControl.checked = required.has(id);
                        if (numericControl instanceof HTMLInputElement) numericControl.checked = numeric.has(id);
                        synchronizeDimensionRow(id);
                    });
                    modal.show();
                });
            }

            const firstInvalid = document.querySelector('.me-results-framework .is-invalid');
            if (firstInvalid) {
                firstInvalid.focus({ preventScroll: true });
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>

    @include('me.indicators.partials.inline-config-script')
@endpush
