@extends('layouts.app')
@section('title', 'Survey Workspace')

@section('content')
    @php
        $heroActions = [
            ['href' => route('budget.me.surveys.responses'), 'label' => 'View Responses', 'icon' => 'feather-inbox', 'class' => 'btn btn-outline-light btn-sm'],
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
            'active' => 'overview',
            'title' => 'Survey Workspace',
            'subtitle' => 'Run the full M&E survey workflow from one place: design questionnaires, monitor submissions, and publish QR-ready links for public access.',
            'heroActions' => $heroActions,
        ])

        @include('me.survey-hub._alerts')

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Questionnaires</div>
                    <div class="survey-stat__value mt-2">{{ $stats['questionnaires'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Survey-ready forms available in the library.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Published Forms</div>
                    <div class="survey-stat__value mt-2">{{ $stats['published_questionnaires'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Questionnaires with public survey mode turned on.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Active QR Links</div>
                    <div class="survey-stat__value mt-2">{{ $stats['active_links'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Indicator-linked survey entries ready to share.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Responses Captured</div>
                    <div class="survey-stat__value mt-2">{{ $stats['responses'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">
                        Last response:
                        {{ !empty($stats['last_response']) ? \Illuminate\Support\Carbon::parse($stats['last_response'])->format('d M Y H:i') : 'No submissions yet' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-7">
                <div class="survey-panel h-100">
                    <div class="survey-panel__header">
                        <div>
                            <div class="survey-panel__title">Recent Questionnaires</div>
                            <p class="survey-panel__subtitle">The latest survey forms being managed under M&E.</p>
                        </div>
                        <a href="{{ route('budget.me.surveys.questionnaires') }}" class="btn btn-outline-primary btn-sm">
                            <i class="feather-arrow-right me-1"></i> Open Library
                        </a>
                    </div>
                    <div class="table-responsive">
                        <table class="table survey-table align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-3">Questionnaire</th>
                                    <th>Structure</th>
                                    <th>Reach</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentQuestionnaires as $questionnaire)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark">{{ $questionnaire->name }}</div>
                                            <div class="survey-muted small">{{ data_get($questionnaire, 'survey_summary.title', 'Untitled public survey') }}</div>
                                        </td>
                                        <td>
                                            <div>{{ data_get($questionnaire, 'survey_summary.section_count', 0) }} sections</div>
                                            <div class="survey-muted small">{{ data_get($questionnaire, 'survey_summary.question_count', 0) }} questions</div>
                                        </td>
                                        <td>
                                            <div>{{ $questionnaire->linked_indicators_count ?? 0 }} indicators</div>
                                            <div class="survey-muted small">{{ $questionnaire->active_survey_links_count ?? 0 }} active QR links</div>
                                        </td>
                                        <td class="text-end pe-3">
                                            @can('me.configuration.manage')
                                                <a href="{{ route('budget.me.surveys.questionnaires.edit', $questionnaire) }}" class="btn btn-outline-primary btn-sm">
                                                    <i class="feather-edit-2 me-1"></i> Edit
                                                </a>
                                            @else
                                                <a href="{{ route('budget.me.surveys.questionnaires') }}" class="btn btn-outline-secondary btn-sm">
                                                    <i class="feather-arrow-right me-1"></i> Library
                                                </a>
                                            @endcan
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="survey-empty">No questionnaires created yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-5">
                <div class="row g-3">
                    @can('me.configuration.manage')
                        <div class="col-12">
                            <div class="survey-action-tile">
                                <div class="survey-action-tile__icon"><i class="feather-plus-square"></i></div>
                                <div class="survey-action-tile__title">Design a new questionnaire</div>
                                <div class="survey-action-tile__text">Create survey sections, add logic-driven questions, and shape the respondent flow screen by screen.</div>
                                <a href="{{ route('budget.me.surveys.questionnaires.create') }}" class="btn btn-primary btn-sm">
                                    Start building
                                </a>
                            </div>
                        </div>
                    @endcan
                    <div class="col-md-6 col-xl-12">
                        <div class="survey-action-tile">
                            <div class="survey-action-tile__icon"><i class="feather-inbox"></i></div>
                            <div class="survey-action-tile__title">Track responses</div>
                            <div class="survey-action-tile__text">Monitor submissions per link and open response detail pages for export and review.</div>
                            <a href="{{ route('budget.me.surveys.responses') }}" class="btn btn-outline-primary btn-sm">
                                Open responses
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-12">
                        <div class="survey-action-tile">
                            <div class="survey-action-tile__icon"><i class="feather-bar-chart-2"></i></div>
                            <div class="survey-action-tile__title">Build survey reports</div>
                            <div class="survey-action-tile__text">Filter cumulative responses, review question-field summaries, and export charts and PDF reporting packs.</div>
                            <a href="{{ route('budget.me.surveys.reports') }}" class="btn btn-outline-primary btn-sm">
                                Open reports
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6 col-xl-12">
                        <div class="survey-action-tile">
                            <div class="survey-action-tile__icon"><i class="feather-grid"></i></div>
                            <div class="survey-action-tile__title">Share through QR codes</div>
                            <div class="survey-action-tile__text">Preview generated QR codes, copy links, and download images for workshop or field distribution.</div>
                            <a href="{{ route('budget.me.surveys.qr') }}" class="btn btn-outline-primary btn-sm">
                                Manage QR codes
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-xl-6">
                <div class="survey-panel h-100">
                    <div class="survey-panel__header">
                        <div>
                            <div class="survey-panel__title">Live Survey Links</div>
                            <p class="survey-panel__subtitle">Recently generated public links ready for sharing.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table survey-table align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-3">Indicator</th>
                                    <th>Token</th>
                                    <th>Responses</th>
                                    <th class="text-end pe-3">Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentLinks as $surveyLink)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark">{{ $surveyLink->indicator->name ?? 'Untitled indicator' }}</div>
                                            <div class="survey-muted small">{{ $surveyLink->methodology->name ?? 'No methodology' }}</div>
                                        </td>
                                        <td>
                                            <div class="fw-semibold text-primary">{{ $surveyLink->public_token }}</div>
                                            <div class="survey-muted small">
                                                {{ !empty($surveyLink->latest_response_at) ? \Illuminate\Support\Carbon::parse($surveyLink->latest_response_at)->format('d M Y H:i') : 'No submissions yet' }}
                                            </div>
                                        </td>
                                        <td>{{ $surveyLink->responses_count ?? 0 }}</td>
                                        <td class="text-end pe-3">
                                            <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                                <a href="{{ $surveyLink->public_url }}" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="feather-external-link"></i>
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary btn-sm js-open-survey-qr"
                                                    data-link="{{ $surveyLink->public_url }}"
                                                    data-qr="{{ $surveyLink->qr_url }}"
                                                    data-title="{{ $surveyLink->indicator->name ?? 'Survey QR Code' }}">
                                                    <i class="feather-grid"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" data-copy-text="{{ $surveyLink->public_url }}">
                                                    <i class="feather-clipboard"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="survey-empty">No public survey links have been generated yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-xl-6">
                <div class="survey-panel h-100">
                    <div class="survey-panel__header">
                        <div>
                            <div class="survey-panel__title">Recent Responses</div>
                            <p class="survey-panel__subtitle">Latest survey submissions captured across all linked questionnaires.</p>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table survey-table align-middle">
                            <thead>
                                <tr>
                                    <th class="ps-3">Respondent</th>
                                    <th>Indicator</th>
                                    <th>Submitted</th>
                                    <th class="text-end pe-3">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($recentResponses as $response)
                                    <tr>
                                        <td class="ps-3">
                                            <div class="fw-semibold text-dark">{{ $response->respondent_name ?: 'Anonymous' }}</div>
                                            <div class="survey-muted small">{{ $response->respondent_organization ?: 'No organization provided' }}</div>
                                        </td>
                                        <td>
                                            <div>{{ $response->indicator->name ?? 'Untitled indicator' }}</div>
                                            <div class="survey-muted small">{{ $response->methodology->name ?? 'No methodology' }}</div>
                                        </td>
                                        <td>{{ optional($response->submitted_at)->format('d M Y H:i') ?? '—' }}</td>
                                        <td class="text-end pe-3">
                                            @if ($response->surveyLink)
                                                <a href="{{ route('budget.me.data-sources.surveys.show', $response->surveyLink) }}" class="btn btn-outline-primary btn-sm">
                                                    <i class="feather-eye me-1"></i> Open
                                                </a>
                                            @else
                                                <span class="survey-muted small">No detail page</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="survey-empty">No responses have been submitted yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @include('me.survey-hub._qr_modal')
    </div>
@endsection
