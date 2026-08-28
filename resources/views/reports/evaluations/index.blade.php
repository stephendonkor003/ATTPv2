@extends('layouts.app')

@section('title', 'Evaluation Reports')

@section('content')
    @php
        $reportGroups = collect($procurementReportGroups ?? []);
        $methodsByType = $reportGroups
            ->flatMap(fn (array $group) => collect($group['methods'] ?? []))
            ->groupBy('type');
        $methodCards = collect(\App\Models\Evaluation::configurationTypes())
            ->map(function (array $definition, string $type) use ($methodsByType): array {
                $rows = $methodsByType->get($type, collect());

                return [
                    'type' => $type,
                    'definition' => $definition,
                    'procurements' => $rows->count(),
                    'reports' => (int) $rows->sum('report_count'),
                    'applicants' => (int) $rows->sum('applicant_count'),
                    'ready' => $rows->where('status', 'ready')->count(),
                ];
            });
        $totalReports = $methodCards->sum('reports');
        $totalApplicants = collect($submissions ?? [])
            ->pluck('form_submission_id')
            ->filter()
            ->unique()
            ->count();
        $totalProcurements = $reportGroups
            ->filter(fn (array $group): bool => collect($group['methods'] ?? [])->isNotEmpty())
            ->count();
        $methodIcons = [
            'services' => 'feather-bar-chart-2',
            'goods' => 'feather-package',
            'eoi' => 'feather-user-check',
        ];
        $methodDescriptions = [
            'services' => 'Review technical and financial scores, panel averages, criteria performance, and ranked applicants.',
            'goods' => 'Review Yes or No compliance decisions, exceptions, supporting comments, and evaluator activity.',
            'eoi' => 'Review qualification outcomes, shortlist readiness, panel completion, and the full decision trail.',
        ];
    @endphp

    <main class="nxl-container evr-shell" aria-labelledby="evaluationReportsTitle">
        <header class="evr-hero">
            <div class="evr-hero__copy">
                <span class="evr-eyebrow">Evaluation intelligence</span>
                <h1 id="evaluationReportsTitle">Evaluation Report Centre</h1>
                <p>Choose an evaluation method to see its procurements, then open a procurement for the complete panel report, applicant outcomes, scoring evidence, and export tools.</p>
                <div class="evr-hero__meta" aria-label="Report overview">
                    <span><i class="feather-briefcase" aria-hidden="true"></i>{{ number_format($totalProcurements) }} procurements</span>
                    <span><i class="feather-file-text" aria-hidden="true"></i>{{ number_format($totalReports) }} completed reports</span>
                    <span><i class="feather-users" aria-hidden="true"></i>{{ number_format($totalApplicants) }} evaluated applicants</span>
                </div>
            </div>
            <div class="evr-hero__actions evr-no-print" aria-label="Consolidated report actions">
                <a href="{{ route('reports.evaluations.consolidated') }}" class="evr-btn evr-btn--ghost">
                    <i class="feather-layers" aria-hidden="true"></i> Consolidated view
                </a>
                <a href="{{ route('reports.evaluations.consolidated.pdf') }}" class="evr-btn evr-btn--light">
                    <i class="feather-download" aria-hidden="true"></i> Consolidated PDF
                </a>
            </div>
        </header>

        <section class="evr-section" aria-labelledby="methodChoiceTitle">
            <div class="evr-section-head">
                <div>
                    <span class="evr-eyebrow">Start with a method</span>
                    <h2 id="methodChoiceTitle">What would you like to review?</h2>
                    <p>Each workspace keeps its own evaluation rules, metrics, and report presentation.</p>
                </div>
                <span class="text-muted small">Only procurements in your assigned portfolio are shown.</span>
            </div>

            <div class="evr-method-grid">
                @foreach ($methodCards as $card)
                    @php
                        $type = $card['type'];
                        $definition = $card['definition'];
                    @endphp
                    <a
                        href="{{ route('reports.evaluations.method', $type) }}"
                        class="evr-method-card evr-method-card--{{ $type }}"
                        aria-label="Open {{ $definition['label'] }} evaluation reports"
                    >
                        <div class="evr-method-card__body">
                            <div class="evr-method-card__top">
                                <span class="evr-method-icon"><i class="{{ $methodIcons[$type] }}" aria-hidden="true"></i></span>
                                <span class="evr-method-number">0{{ $loop->iteration }}</span>
                            </div>
                            <h2>{{ $definition['label'] }}</h2>
                            <span class="evr-method-card__mode">{{ $definition['mode'] }}</span>
                            <p>{{ $methodDescriptions[$type] }}</p>
                            <div class="evr-method-card__stats" aria-label="{{ $definition['label'] }} totals">
                                <div>
                                    <span>Procurements</span>
                                    <strong>{{ number_format($card['procurements']) }}</strong>
                                </div>
                                <div>
                                    <span>Reports filed</span>
                                    <strong>{{ number_format($card['reports']) }}</strong>
                                </div>
                            </div>
                        </div>
                        <div class="evr-method-card__foot">
                            <span>Browse {{ $definition['label'] }} reports</span>
                            <i class="feather-arrow-right" aria-hidden="true"></i>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="evr-section" aria-labelledby="reportJourneyTitle">
            <div class="evr-section-head">
                <div>
                    <span class="evr-eyebrow">A simpler report journey</span>
                    <h2 id="reportJourneyTitle">From portfolio to evidence in three steps</h2>
                </div>
            </div>
            <div class="evr-steps">
                <article class="evr-step">
                    <span class="evr-step__number">01</span>
                    <div><strong>Select an evaluation method</strong><p>Services, Goods, and EOI remain separate so their results are never mixed.</p></div>
                </article>
                <article class="evr-step">
                    <span class="evr-step__number">02</span>
                    <div><strong>Open a procurement</strong><p>Search the dedicated list and review progress before opening the full report.</p></div>
                </article>
                <article class="evr-step">
                    <span class="evr-step__number">03</span>
                    <div><strong>Review or export</strong><p>Inspect panel evidence and download the report as Excel, CSV, PDF, or print.</p></div>
                </article>
            </div>
        </section>
    </main>
@endsection

@push('styles')
    @include('reports.evaluations.partials.report-suite-styles')
@endpush
