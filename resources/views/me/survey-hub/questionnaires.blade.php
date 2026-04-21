@extends('layouts.app')
@section('title', 'Questionnaire Library')

@section('content')
    @php
        $heroActions = [
            ['href' => route('budget.me.surveys.qr'), 'label' => 'QR Codes', 'icon' => 'feather-grid', 'class' => 'btn btn-outline-light btn-sm'],
        ];

        if (auth()->user()?->can('me.configuration.manage')) {
            array_unshift($heroActions, [
                'href' => route('budget.me.surveys.questionnaires.create'),
                'label' => 'Add Questionnaire',
                'icon' => 'feather-plus-square',
                'class' => 'btn btn-light btn-sm',
            ]);
        }
    @endphp

    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'questionnaires',
            'title' => 'Questionnaire Library',
            'subtitle' => 'Maintain survey forms in one dedicated library, with visibility into sections, questions, linked indicators, and publication status.',
            'heroActions' => $heroActions,
        ])

        @include('me.survey-hub._alerts')

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Questionnaires</div>
                    <div class="survey-stat__value mt-2">{{ $stats['questionnaires'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Survey forms currently stored in the workspace.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Published</div>
                    <div class="survey-stat__value mt-2">{{ $stats['published'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Forms that can power a public survey link.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Questions</div>
                    <div class="survey-stat__value mt-2">{{ $stats['questions'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Combined question volume across all questionnaires.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Linked Indicators</div>
                    <div class="survey-stat__value mt-2">{{ $stats['linked_indicators'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Indicators currently mapped to these survey methodologies.</div>
                </div>
            </div>
        </div>

        <div class="survey-search p-3 mb-4">
            <form method="GET" action="{{ route('budget.me.surveys.questionnaires') }}" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold">Search questionnaire library</label>
                    <input type="text" name="q" value="{{ $search }}" class="form-control"
                        placeholder="Search by questionnaire name, title, or description">
                </div>
                <div class="col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-search me-1"></i> Search
                    </button>
                    <a href="{{ route('budget.me.surveys.questionnaires') }}" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>

        <div class="survey-panel">
            <div class="survey-panel__header">
                <div>
                    <div class="survey-panel__title">Survey Questionnaires</div>
                    <p class="survey-panel__subtitle">Structured forms used to generate public survey flows and response links.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table survey-table align-middle">
                    <thead>
                        <tr>
                            <th class="ps-3">Questionnaire</th>
                            <th>Structure</th>
                            <th>Coverage</th>
                            <th>Status</th>
                            <th>Updated</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($questionnaires as $questionnaire)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">{{ $questionnaire->name }}</div>
                                    <div class="survey-muted small">{{ data_get($questionnaire, 'survey_summary.title', 'Untitled public survey') }}</div>
                                    @if (!empty(data_get($questionnaire, 'survey_summary.intro')))
                                        <div class="survey-muted small mt-1">{{ \Illuminate\Support\Str::limit(data_get($questionnaire, 'survey_summary.intro'), 110) }}</div>
                                    @endif
                                </td>
                                <td>
                                    <div>{{ data_get($questionnaire, 'survey_summary.section_count', 0) }} sections</div>
                                    <div class="survey-muted small">{{ data_get($questionnaire, 'survey_summary.question_count', 0) }} questions</div>
                                </td>
                                <td>
                                    <div>{{ $questionnaire->linked_indicators_count ?? 0 }} indicators</div>
                                    <div class="survey-muted small">{{ $questionnaire->active_survey_links_count ?? 0 }} active QR links</div>
                                </td>
                                <td>
                                    <span class="survey-chip {{ data_get($questionnaire, 'survey_summary.state_class', 'secondary') }}">
                                        {{ data_get($questionnaire, 'survey_summary.state', 'Draft') }}
                                    </span>
                                    <div class="survey-muted small mt-1">
                                        {{ data_get($questionnaire, 'survey_summary.estimated_minutes') ? data_get($questionnaire, 'survey_summary.estimated_minutes') . ' min' : 'No duration set' }}
                                    </div>
                                </td>
                                <td>{{ optional($questionnaire->updated_at)->format('d M Y') ?? '—' }}</td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <a href="{{ route('budget.me.surveys.qr', ['q' => $questionnaire->name]) }}" class="btn btn-outline-secondary btn-sm">
                                            <i class="feather-grid me-1"></i> QR
                                        </a>
                                        @can('me.configuration.manage')
                                            <a href="{{ route('budget.me.surveys.questionnaires.edit', $questionnaire) }}" class="btn btn-outline-primary btn-sm">
                                                <i class="feather-edit-2 me-1"></i> Edit
                                            </a>
                                        @endcan
                                        @can('me.configuration.manage')
                                            <form action="{{ route('budget.me-configuration.methodologies.destroy', $questionnaire) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Delete this questionnaire and its linked survey data?');">
                                                @csrf
                                                @method('DELETE')
                                                <input type="hidden" name="from_survey_module" value="1">
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="survey-empty">No questionnaires matched the current search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($questionnaires->hasPages())
                <div class="p-3 border-top">
                    {{ $questionnaires->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
