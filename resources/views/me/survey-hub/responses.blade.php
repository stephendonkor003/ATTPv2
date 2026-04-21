@extends('layouts.app')
@section('title', 'Survey Responses')

@section('content')
    @php
        $heroActions = [
            ['href' => route('budget.me.data-sources.surveys.export', ['format' => 'csv']), 'label' => 'Export CSV', 'icon' => 'feather-download', 'class' => 'btn btn-light btn-sm'],
            ['href' => route('budget.me.surveys.qr'), 'label' => 'QR Codes', 'icon' => 'feather-grid', 'class' => 'btn btn-outline-light btn-sm'],
        ];

        if (auth()->user()?->can('me.configuration.manage')) {
            array_unshift($heroActions, [
                'href' => route('budget.me.surveys.questionnaires.create'),
                'label' => 'Add Questionnaire',
                'icon' => 'feather-plus-square',
                'class' => 'btn btn-outline-light btn-sm',
            ]);
        }
    @endphp

    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'responses',
            'title' => 'Survey Responses',
            'subtitle' => 'Review submissions by public survey link, open detailed response pages, and move quickly between data collection and export.',
            'heroActions' => $heroActions,
        ])

        @include('me.survey-hub._alerts')

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Responses</div>
                    <div class="survey-stat__value mt-2">{{ $stats['responses'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Total submissions stored across survey links.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Active Links</div>
                    <div class="survey-stat__value mt-2">{{ $stats['active_links'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Public survey URLs currently live.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Surveys With Data</div>
                    <div class="survey-stat__value mt-2">{{ $stats['surveys_with_responses'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Links that have at least one submission.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Last Submission</div>
                    <div class="survey-stat__value mt-2" style="font-size: 1.1rem;">
                        {{ !empty($stats['last_response']) ? \Illuminate\Support\Carbon::parse($stats['last_response'])->format('d M Y') : 'No data yet' }}
                    </div>
                    <div class="survey-stat__meta mt-2">
                        {{ !empty($stats['last_response']) ? \Illuminate\Support\Carbon::parse($stats['last_response'])->format('H:i') : 'Awaiting first submission' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="survey-search p-3 mb-4">
            <form method="GET" action="{{ route('budget.me.surveys.responses') }}" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold">Search surveys, indicators, or tokens</label>
                    <input type="text" name="q" value="{{ $search }}" class="form-control"
                        placeholder="Search by token, questionnaire, or indicator name">
                </div>
                <div class="col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-search me-1"></i> Search
                    </button>
                    <a href="{{ route('budget.me.surveys.responses') }}" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>

        <div class="survey-panel">
            <div class="survey-panel__header">
                <div>
                    <div class="survey-panel__title">Response Monitor</div>
                    <p class="survey-panel__subtitle">Each row represents one public survey link attached to an indicator.</p>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table survey-table align-middle">
                    <thead>
                        <tr>
                            <th class="ps-3">Survey</th>
                            <th>Responses</th>
                            <th>Latest Submission</th>
                            <th>Status</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($surveyLinks as $surveyLink)
                            <tr>
                                <td class="ps-3">
                                    <div class="fw-semibold text-dark">{{ $surveyLink->indicator->name ?? 'Untitled indicator' }}</div>
                                    <div class="survey-muted small">{{ $surveyLink->methodology->name ?? 'No methodology' }}</div>
                                    <div class="survey-muted small">Token: {{ $surveyLink->public_token }}</div>
                                </td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $surveyLink->responses_count ?? 0 }}</div>
                                    <div class="survey-muted small">
                                        {{ $surveyLink->is_active ? 'Live and receiving submissions' : 'Link currently inactive' }}
                                    </div>
                                </td>
                                <td>
                                    {{ !empty($surveyLink->latest_response_at) ? \Illuminate\Support\Carbon::parse($surveyLink->latest_response_at)->format('d M Y H:i') : 'No submissions yet' }}
                                </td>
                                <td>
                                    <span class="survey-chip {{ $surveyLink->is_active ? 'success' : 'secondary' }}">
                                        <i class="feather-{{ $surveyLink->is_active ? 'check' : 'pause' }}"></i>
                                        {{ $surveyLink->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="text-end pe-3">
                                    <div class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                        <a href="{{ route('budget.me.data-sources.surveys.show', $surveyLink) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="feather-eye me-1"></i> Responses
                                        </a>
                                        <a href="{{ $surveyLink->public_url }}" target="_blank" class="btn btn-outline-secondary btn-sm">
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
                                        @can('me.configuration.manage')
                                            <form action="{{ route('budget.me.surveys.links.destroy', $surveyLink) }}" method="POST" class="d-inline"
                                                onsubmit="return confirm('Delete this survey and all of its responses?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm" title="Delete survey">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="survey-empty">No survey links matched the current search.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($surveyLinks->hasPages())
                <div class="p-3 border-top">
                    {{ $surveyLinks->links() }}
                </div>
            @endif
        </div>

        @include('me.survey-hub._qr_modal')
    </div>
@endsection
