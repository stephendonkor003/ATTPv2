@extends('layouts.app')

@section('title', 'Questionnaire Preview — '.$template->name)
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-preview-shell {
            display: grid;
            grid-template-columns: minmax(205px, 260px) minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        .basv-preview-summary {
            position: sticky;
            top: 110px;
        }

        .basv-preview-summary dl {
            display: grid;
            gap: .75rem;
            margin: 0;
        }

        .basv-preview-summary dt {
            margin-bottom: .15rem;
            color: var(--basv-muted);
            font-size: .63rem;
            font-weight: 850;
            letter-spacing: .065em;
            text-transform: uppercase;
        }

        .basv-preview-summary dd {
            margin: 0;
            color: var(--basv-ink);
            font-size: .78rem;
            font-weight: 750;
        }

        .basv-preview-nav {
            display: grid;
            gap: .3rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--basv-border);
        }

        .basv-preview-nav a {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
            padding: .55rem .65rem;
            border-radius: .55rem;
            color: #4f635e;
            font-size: .68rem;
            font-weight: 750;
            text-decoration: none;
        }

        .basv-preview-nav a:hover {
            background: var(--basv-green-soft);
            color: var(--basv-green-dark);
        }

        .basv-preview-document {
            position: relative;
            overflow: hidden;
        }

        .basv-preview-document::before {
            position: fixed;
            z-index: 0;
            top: 49%;
            left: 45%;
            width: 65%;
            color: rgba(7, 84, 70, .035);
            content: attr(data-watermark);
            font-size: clamp(2.2rem, 5vw, 5.6rem);
            font-weight: 900;
            letter-spacing: .04em;
            line-height: 1;
            pointer-events: none;
            text-align: center;
            text-transform: uppercase;
            transform: translate(-42%, -50%) rotate(-28deg);
        }

        .basv-preview-document > * {
            position: relative;
            z-index: 1;
        }

        .basv-preview-intro {
            margin-bottom: 1rem;
            padding: 1rem 1.15rem;
            border: 1px solid #d6e7e1;
            border-left: 4px solid var(--basv-gold);
            border-radius: .8rem;
            background: rgba(250, 253, 252, .94);
            color: #455d57;
            font-size: .76rem;
            line-height: 1.65;
        }

        .basv-preview-section {
            overflow: hidden;
            margin-bottom: 1rem;
            border: 1px solid var(--basv-border);
            border-radius: .9rem;
            background: rgba(255, 255, 255, .96);
            box-shadow: 0 8px 24px rgba(15, 42, 39, .055);
            scroll-margin-top: 110px;
        }

        .basv-preview-section-head {
            padding: 1rem 1.15rem;
            background: linear-gradient(120deg, #075446, #08765f);
            color: #fff;
        }

        .basv-preview-section-head small {
            display: block;
            margin-bottom: .3rem;
            color: #cce9df;
            font-size: .62rem;
            font-weight: 850;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .basv-preview-section-head h2 {
            margin: 0;
            color: #fff;
            font-size: 1rem;
            font-weight: 850;
        }

        .basv-preview-section-head p {
            margin: .35rem 0 0;
            color: rgba(255, 255, 255, .78);
            font-size: .7rem;
            line-height: 1.5;
        }

        .basv-preview-topic {
            padding: 1rem 1.15rem;
        }

        .basv-preview-topic + .basv-preview-topic {
            border-top: 1px solid var(--basv-border);
        }

        .basv-preview-topic-head {
            margin-bottom: .75rem;
            padding: .65rem .75rem;
            border-left: 4px solid var(--basv-gold);
            border-radius: .55rem;
            background: #f7faf9;
        }

        .basv-preview-topic-head h3 {
            margin: 0;
            color: var(--basv-ink);
            font-size: .86rem;
            font-weight: 850;
        }

        .basv-preview-topic-head p {
            margin: .25rem 0 0;
            color: var(--basv-muted);
            font-size: .68rem;
            line-height: 1.5;
        }

        .basv-preview-question {
            margin-top: .65rem;
            padding: .85rem .9rem;
            border: 1px solid #e1e9e6;
            border-radius: .7rem;
            background: #fff;
        }

        .basv-preview-question-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .8rem;
        }

        .basv-preview-question-title {
            color: #20332f;
            font-size: .76rem;
            font-weight: 750;
            line-height: 1.55;
        }

        .basv-preview-question-meta {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            margin-top: .55rem;
        }

        .basv-preview-help {
            margin-top: .55rem;
            padding: .55rem .65rem;
            border-left: 2px solid #9dcfc0;
            background: #f5faf8;
            color: #5b6f69;
            font-size: .67rem;
            line-height: 1.5;
        }

        .basv-preview-options {
            width: 100%;
            margin-top: .65rem;
            border-collapse: collapse;
        }

        .basv-preview-options td {
            padding: .45rem .55rem;
            border: 1px solid #e1e9e6;
            color: #4e625d;
            font-size: .66rem;
            vertical-align: top;
        }

        .basv-preview-options strong {
            color: var(--basv-green-dark);
        }

        .basv-preview-empty {
            padding: 3rem 1rem;
            border: 1px dashed var(--basv-border);
            border-radius: .9rem;
            color: var(--basv-muted);
            text-align: center;
        }

        @media (max-width: 991.98px) {
            .basv-preview-shell {
                grid-template-columns: 1fr;
            }

            .basv-preview-summary {
                position: static;
            }

            .basv-preview-nav {
                display: none;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $sections = collect($definition['sections'] ?? []);
        $topicCount = $sections->sum(static fn (array $section): int => count($section['topics'] ?? []));
        $questions = $sections->flatMap(
            static fn (array $section) => collect($section['topics'] ?? [])->flatMap(
                static fn (array $topic) => $topic['questions'] ?? []
            )
        );
        $questionCount = $questions->count();
        $requiredCount = $questions->where('required', true)->count();
        $scoredCount = $questions->where('response_type', 'scored_assessment')->count();
    @endphp

    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow">
                        <i class="feather-eye"></i> Questionnaire preview
                        <span>·</span> Version {{ $template->version }}
                    </span>
                    <h1>{{ $template->name }}</h1>
                    <p>
                        A read-only preview for {{ $thinkTank->name }} under the
                        <strong>{{ $portfolioName }}</strong> portfolio.
                    </p>
                </div>
                <div class="basv-hero-actions">
                    @can('biannual_site_visits.create')
                        <a href="{{ route('biannual-site-visits.create') }}" class="basv-btn basv-btn-light">
                            <i class="feather-arrow-left"></i> Schedule Visit
                        </a>
                    @else
                        <a href="{{ route('biannual-site-visits.index') }}" class="basv-btn basv-btn-light">
                            <i class="feather-arrow-left"></i> Visit Register
                        </a>
                    @endcan
                    <a href="{{ $pdfUrl }}" class="basv-btn basv-btn-light">
                        <i class="feather-download"></i> Download PDF
                    </a>
                </div>
            </div>

            <div class="basv-stats">
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-layers"></i></span>
                    <div><strong>{{ $sections->count() }}</strong><span>Questionnaire sections</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-list"></i></span>
                    <div><strong>{{ $topicCount }}</strong><span>Monitoring topics</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-help-circle"></i></span>
                    <div><strong>{{ $questionCount }}</strong><span>Total questions</span></div>
                </div>
                <div class="basv-stat">
                    <span class="basv-stat-icon"><i class="feather-check-square"></i></span>
                    <div><strong>{{ $requiredCount }}</strong><span>Required responses</span></div>
                </div>
            </div>

            <div class="basv-preview-shell">
                <aside class="basv-card basv-preview-summary">
                    <div class="basv-card-head">
                        <h2><i class="feather-file-text me-2"></i>Document profile</h2>
                    </div>
                    <div class="basv-card-body">
                        <dl>
                            <div>
                                <dt>Portfolio</dt>
                                <dd>{{ $portfolioName }}</dd>
                            </div>
                            <div>
                                <dt>Think Tank</dt>
                                <dd>{{ $thinkTank->name }}</dd>
                            </div>
                            <div>
                                <dt>Template</dt>
                                <dd>{{ $template->code }} · v{{ $template->version }}</dd>
                            </div>
                            <div>
                                <dt>Scored questions</dt>
                                <dd>{{ $scoredCount }} of {{ $questionCount }}</dd>
                            </div>
                            <div>
                                <dt>Status</dt>
                                <dd><span class="basv-badge {{ $template->status }}">{{ str_replace('_', ' ', $template->status) }}</span></dd>
                            </div>
                        </dl>

                        @if ($sections->isNotEmpty())
                            <nav class="basv-preview-nav" aria-label="Questionnaire sections">
                                @foreach ($sections as $sectionIndex => $section)
                                    <a href="#preview-section-{{ $sectionIndex + 1 }}">
                                        <span>{{ $sectionIndex + 1 }}. {{ $section['title'] ?? 'Section' }}</span>
                                        <i class="feather-chevron-right"></i>
                                    </a>
                                @endforeach
                            </nav>
                        @endif
                    </div>
                </aside>

                <section class="basv-preview-document" data-watermark="{{ $portfolioName }}">
                    @if (filled($definition['description'] ?? null) || filled($definition['instructions'] ?? null))
                        <div class="basv-preview-intro">
                            @if (filled($definition['description'] ?? null))
                                <strong class="d-block mb-1">About this questionnaire</strong>
                                <div>{{ $definition['description'] }}</div>
                            @endif
                            @if (filled($definition['instructions'] ?? null))
                                <strong class="d-block mb-1 mt-3">Completion instructions</strong>
                                <div style="white-space: pre-line">{{ $definition['instructions'] }}</div>
                            @endif
                        </div>
                    @endif

                    @forelse ($sections as $sectionIndex => $section)
                        <article class="basv-preview-section" id="preview-section-{{ $sectionIndex + 1 }}">
                            <header class="basv-preview-section-head">
                                <small>Part {{ $sectionIndex + 1 }} · Weight {{ number_format((float) ($section['weight'] ?? 1), 2) }}</small>
                                <h2>{{ $section['title'] ?? 'Section' }}</h2>
                                @if (filled($section['description'] ?? null))
                                    <p>{{ $section['description'] }}</p>
                                @endif
                            </header>

                            @forelse ($section['topics'] ?? [] as $topicIndex => $topic)
                                <div class="basv-preview-topic">
                                    <div class="basv-preview-topic-head">
                                        <h3>{{ $sectionIndex + 1 }}.{{ $topicIndex + 1 }} {{ $topic['title'] ?? 'Topic' }}</h3>
                                        @if (filled($topic['description'] ?? null))
                                            <p>{{ $topic['description'] }}</p>
                                        @endif
                                    </div>

                                    @foreach ($topic['questions'] ?? [] as $questionIndex => $question)
                                        @php
                                            $type = (string) ($question['response_type'] ?? $question['type'] ?? 'long_text');
                                            $options = collect($question['options'] ?? [])->filter();
                                            $key = $question['key'] ?? 'Q-'.($sectionIndex + 1).'.'.($topicIndex + 1).'.'.($questionIndex + 1);
                                        @endphp
                                        <div class="basv-preview-question">
                                            <div class="basv-preview-question-head">
                                                <div class="basv-preview-question-title">
                                                    <span class="basv-question-key">{{ $key }}</span>
                                                    <div class="mt-2">{{ $question['prompt'] ?? $question['label'] ?? 'Question' }}</div>
                                                </div>
                                                <span class="basv-badge">{{ str_replace('_', ' ', $type) }}</span>
                                            </div>

                                            <div class="basv-preview-question-meta">
                                                @if ($question['required'] ?? false)
                                                    <span class="basv-badge submitted">Required</span>
                                                @endif
                                                @if ($type === 'scored_assessment')
                                                    <span class="basv-badge">Weight {{ number_format((float) ($question['weight'] ?? 1), 2) }}</span>
                                                    <span class="basv-badge">
                                                        {{ ($question['scoring_direction'] ?? 'positive') === 'negative' ? 'Risk direction' : 'Positive direction' }}
                                                    </span>
                                                    @if ($question['allows_na'] ?? false)
                                                        <span class="basv-badge">N/A permitted</span>
                                                    @endif
                                                @endif
                                            </div>

                                            @if (filled($question['help_text'] ?? null))
                                                <div class="basv-preview-help">
                                                    <strong>Assessor guidance:</strong> {{ $question['help_text'] }}
                                                </div>
                                            @endif

                                            @if ($options->isNotEmpty())
                                                <table class="basv-preview-options">
                                                    <tbody>
                                                        @foreach ($options as $option)
                                                            @php
                                                                $optionValue = is_array($option) ? ($option['score'] ?? $option['value'] ?? null) : null;
                                                                $optionLabel = is_array($option) ? ($option['label'] ?? $option['value'] ?? 'Option') : $option;
                                                                $optionDescription = is_array($option)
                                                                    ? ($option['description'] ?? $option['help_text'] ?? null)
                                                                    : null;
                                                            @endphp
                                                            <tr>
                                                                <td style="width: 28%">
                                                                    @if ($optionValue !== null && $optionValue !== '')
                                                                        <strong>{{ $optionValue }}</strong> ·
                                                                    @endif
                                                                    {{ $optionLabel }}
                                                                </td>
                                                                <td>{{ $optionDescription ?: 'Selectable response option' }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @empty
                                <div class="basv-preview-empty">No topics have been configured in this section.</div>
                            @endforelse
                        </article>
                    @empty
                        <div class="basv-preview-empty">
                            <i class="feather-file-text d-block mb-2"></i>
                            This template does not contain any questionnaire sections yet.
                        </div>
                    @endforelse
                </section>
            </div>
        </div>
    </main>
@endsection
