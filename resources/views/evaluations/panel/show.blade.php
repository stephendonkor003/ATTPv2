@extends('layouts.app')

@section('title', 'Evaluation Journey - '.($procurement->reference_no ?: $procurement->title))

@section('content')
    @php
        $title = $procurement->title ?: 'Untitled procurement';
        $currentStep = $journeySteps->firstWhere('state', 'current');
    @endphp

    <main class="nxl-container pev-shell" aria-labelledby="panelJourneyTitle">
        <nav class="pev-breadcrumb" aria-label="Breadcrumb">
            <a href="{{ route('eval.panel.index') }}"><i class="feather-arrow-left" aria-hidden="true"></i> Panel evaluations</a>
            <span aria-hidden="true">/</span>
            <span>{{ $procurement->reference_no ?: 'Procurement journey' }}</span>
        </nav>

        <header class="pev-hero pev-hero--journey">
            <div class="pev-hero__copy">
                <div class="pev-reference-line">
                    <span class="pev-reference pev-reference--light"><i class="feather-hash" aria-hidden="true"></i>{{ $procurement->reference_no ?: 'No reference' }}</span>
                    <span class="pev-status pev-status--{{ $card['status'] }}">{{ Str::headline(str_replace('_', ' ', $card['status'])) }}</span>
                </div>
                <h1 id="panelJourneyTitle">{{ $title }}</h1>
                <p>Follow the procurement from publication through panel evaluation, applicant communication, proposal intake, and the final decision.</p>
                <div class="pev-hero__meta">
                    <span><i class="feather-activity" aria-hidden="true"></i>{{ Str::headline((string) ($procurement->status ?: 'Status not set')) }}</span>
                    <span><i class="feather-layers" aria-hidden="true"></i>{{ $card['methods']->count() }} evaluation type(s)</span>
                    @if ($card['latest_at'])
                        <span><i class="feather-clock" aria-hidden="true"></i>Updated {{ $card['latest_at']->format('d M Y, H:i') }}</span>
                    @endif
                </div>
            </div>
            <div class="pev-now-card">
                <span class="pev-now-card__orb"><i class="{{ $currentStep['icon'] ?? 'feather-check-circle' }}" aria-hidden="true"></i></span>
                <small>{{ $currentStep ? 'Happening now' : 'Journey status' }}</small>
                <strong>{{ $currentStep['label'] ?? 'Evaluation journey complete' }}</strong>
                <span>{{ $currentStep['meta'] ?? 'All recorded stages are complete' }}</span>
            </div>
        </header>

        <section class="pev-kpi-grid" aria-label="Procurement panel overview">
            @foreach ([
                ['feather-inbox', 'Applications', $card['application_count'], 'received'],
                ['feather-users', 'Active panel', $card['evaluator_count'], $card['assignment_count'].' assignments'],
                ['feather-file-text', 'Active reports', $card['report_count'], $card['evaluated_applicant_count'].' applicants evaluated'],
                ['feather-trending-up', 'Panel progress', $card['completion_percent'].'%', 'current assignments only'],
            ] as [$icon, $label, $value, $detail])
                <article class="pev-kpi">
                    <span class="pev-kpi__icon"><i class="{{ $icon }}" aria-hidden="true"></i></span>
                    <div><span>{{ $label }}</span><strong>{{ is_numeric($value) ? number_format($value) : $value }}</strong><small>{{ $detail }}</small></div>
                </article>
            @endforeach
        </section>

        <section class="pev-panel pev-journey-panel" aria-labelledby="journeyFlowTitle">
            <header class="pev-panel__head">
                <div>
                    <span class="pev-eyebrow">Live procurement journey</span>
                    <h2 id="journeyFlowTitle">What is happening?</h2>
                    <p>The animated circle identifies the next active step. Completed and upcoming stages remain visible for context.</p>
                </div>
                <span class="pev-trust-note"><i class="feather-shield" aria-hidden="true"></i> Current assignments only</span>
            </header>
            <div class="pev-panel__body">
                @include('evaluations.panel.partials.journey', ['steps' => $journeySteps])
            </div>
        </section>

        <section class="pev-section" aria-labelledby="evaluationMethodsTitle">
            <header class="pev-section__head">
                <div>
                    <span class="pev-eyebrow">Evaluation workspaces</span>
                    <h2 id="evaluationMethodsTitle">Open the right evaluation type</h2>
                    <p>Each method keeps its own rules, panel progress, evidence, and report presentation.</p>
                </div>
            </header>

            <div class="pev-method-grid">
                @forelse ($card['methods'] as $methodCard)
                    @include('evaluations.panel.partials.method-card', compact('methodCard', 'procurement'))
                @empty
                    <div class="pev-empty">
                        <span class="pev-empty__icon"><i class="feather-settings" aria-hidden="true"></i></span>
                        <h3>No evaluation method configured</h3>
                        <p>This procurement is visible in the workspace, but a Services, Goods, or EOI evaluation must be configured before panel activity can begin.</p>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($eoiStats)
            <section class="pev-panel" aria-labelledby="eoiHandoffTitle">
                <header class="pev-panel__head">
                    <div>
                        <span class="pev-eyebrow">EOI hand-off</span>
                        <h2 id="eoiHandoffTitle">Qualification to technical proposal</h2>
                        <p>The shortlist and proposal intake below follow the final, active EOI panel decision.</p>
                    </div>
                    @if (auth()->user()?->can('evaluations.view_all'))
                        <a href="{{ route('reports.evaluations.eoi.procurement', $procurement) }}" class="pev-btn pev-btn--outline">
                            Open qualification report <i class="feather-arrow-up-right" aria-hidden="true"></i>
                        </a>
                    @endif
                </header>
                <div class="pev-handoff-grid">
                    <article><span class="pev-handoff-icon pev-handoff-icon--success"><i class="feather-user-check"></i></span><div><small>Qualified to advance</small><strong>{{ number_format($eoiStats['advance']) }}</strong><span>Fully or average qualified after panel completion</span></div></article>
                    <article><span class="pev-handoff-icon pev-handoff-icon--warning"><i class="feather-clock"></i></span><div><small>Awaiting panel</small><strong>{{ number_format($eoiStats['panel_incomplete']) }}</strong><span>Final routing remains held</span></div></article>
                    <article><span class="pev-handoff-icon pev-handoff-icon--violet"><i class="feather-mail"></i></span><div><small>Invitation delivery</small><strong>{{ number_format($communicationSummary['notified_qualified']) }}</strong><span>{{ number_format($communicationSummary['offline_candidates']) }} offline applicant(s) enrolled for admin capture</span></div></article>
                    <article><span class="pev-handoff-icon pev-handoff-icon--blue"><i class="feather-upload-cloud"></i></span><div><small>Proposal responses</small><strong>{{ number_format($communicationSummary['proposal_respondents']) }}</strong><span>{{ number_format($communicationSummary['proposal_documents']) }} submitted documents</span></div></article>
                </div>
            </section>
        @endif
    </main>
@endsection

@push('styles')
    @include('evaluations.panel.partials.styles')
@endpush
