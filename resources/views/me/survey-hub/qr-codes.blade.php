@extends('layouts.app')
@section('title', 'Survey QR Codes')

@section('content')
    <div class="nxl-container">
        @include('me.survey-hub._header', [
            'active' => 'qr',
            'title' => 'Generate QR Code',
            'subtitle' => 'Preview every live survey link as a downloadable QR code so workshops, field teams, and public respondents can open questionnaires quickly.',
            'heroActions' => [
                ['href' => route('budget.me.surveys.questionnaires'), 'label' => 'Questionnaire Library', 'icon' => 'feather-book-open', 'class' => 'btn btn-light btn-sm'],
                ['href' => route('budget.me.surveys.responses'), 'label' => 'View Responses', 'icon' => 'feather-inbox', 'class' => 'btn btn-outline-light btn-sm'],
            ],
        ])

        @include('me.survey-hub._alerts')

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Active QR Links</div>
                    <div class="survey-stat__value mt-2">{{ $stats['active_links'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Survey links currently available for sharing.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Published Forms</div>
                    <div class="survey-stat__value mt-2">{{ $stats['questionnaires'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Questionnaires ready to power public access.</div>
                </div>
            </div>
            <div class="col-sm-6 col-xl-3">
                <div class="survey-stat p-3">
                    <div class="survey-stat__label">Responses</div>
                    <div class="survey-stat__value mt-2">{{ $stats['responses'] ?? 0 }}</div>
                    <div class="survey-stat__meta mt-2">Total submissions captured through these links.</div>
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
            <form method="GET" action="{{ route('budget.me.surveys.qr') }}" class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label fw-semibold">Search QR-ready surveys</label>
                    <input type="text" name="q" value="{{ $search }}" class="form-control"
                        placeholder="Search by token, questionnaire, or indicator name">
                </div>
                <div class="col-lg-4 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-search me-1"></i> Search
                    </button>
                    <a href="{{ route('budget.me.surveys.qr') }}" class="btn btn-light border">Reset</a>
                </div>
            </form>
        </div>

        <div class="row g-4">
            @forelse ($surveyLinks as $surveyLink)
                <div class="col-md-6 col-xl-4">
                    <div class="survey-qr-card">
                        <div class="survey-qr-card__image">
                            <img src="{{ $surveyLink->qr_url }}" alt="QR code for {{ $surveyLink->indicator->name ?? 'survey' }}">
                        </div>
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <div class="fw-semibold text-dark">{{ $surveyLink->indicator->name ?? 'Untitled indicator' }}</div>
                                    <div class="survey-muted small">{{ $surveyLink->methodology->name ?? 'No methodology' }}</div>
                                </div>
                                <span class="survey-chip {{ $surveyLink->is_active ? 'success' : 'secondary' }}">
                                    {{ $surveyLink->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </div>

                            <div class="survey-muted small mb-3">
                                Token: <span class="fw-semibold text-primary">{{ $surveyLink->public_token }}</span>
                            </div>

                            <div class="d-flex justify-content-between small mb-3">
                                <div>
                                    <div class="survey-muted">Responses</div>
                                    <div class="fw-semibold text-dark">{{ $surveyLink->responses_count ?? 0 }}</div>
                                </div>
                                <div class="text-end">
                                    <div class="survey-muted">Latest</div>
                                    <div class="fw-semibold text-dark">
                                        {{ !empty($surveyLink->latest_response_at) ? \Illuminate\Support\Carbon::parse($surveyLink->latest_response_at)->format('d M Y') : 'No data' }}
                                    </div>
                                </div>
                            </div>

                            <div class="d-grid gap-2">
                                <div class="d-flex gap-2">
                                    <a href="{{ $surveyLink->public_url }}" target="_blank" class="btn btn-outline-primary btn-sm w-100">
                                        <i class="feather-external-link me-1"></i> Open
                                    </a>
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100 js-open-survey-qr"
                                        data-link="{{ $surveyLink->public_url }}"
                                        data-qr="{{ $surveyLink->qr_url }}"
                                        data-title="{{ $surveyLink->indicator->name ?? 'Survey QR Code' }}">
                                        <i class="feather-maximize-2 me-1"></i> Preview
                                    </button>
                                </div>
                                <div class="d-flex gap-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" data-copy-text="{{ $surveyLink->public_url }}">
                                        <i class="feather-clipboard me-1"></i> Copy Link
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100"
                                        data-download-qr="{{ $surveyLink->qr_url }}"
                                        data-download-title="{{ $surveyLink->indicator->name ?? 'survey-qr' }}">
                                        <i class="feather-download me-1"></i> PNG
                                    </button>
                                </div>
                                <div class="d-flex gap-2">
                                    <a href="{{ route('budget.me.data-sources.surveys.show', $surveyLink) }}" class="btn btn-outline-secondary btn-sm w-100">
                                        <i class="feather-inbox me-1"></i> Responses
                                    </a>
                                    <a class="btn btn-success btn-sm w-100"
                                        href="https://wa.me/?text={{ urlencode($surveyLink->public_url) }}"
                                        target="_blank" rel="noopener">
                                        <i class="feather-share-2 me-1"></i> Share
                                    </a>
                                </div>
                                @can('me.configuration.manage')
                                    <form action="{{ route('budget.me.surveys.links.destroy', $surveyLink) }}" method="POST"
                                        onsubmit="return confirm('Delete this survey and all of its responses?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger btn-sm w-100">
                                            <i class="feather-trash-2 me-1"></i> Delete Survey
                                        </button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="survey-panel">
                        <div class="survey-empty">No survey links matched the current search.</div>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($surveyLinks->hasPages())
            <div class="mt-4">
                {{ $surveyLinks->links() }}
            </div>
        @endif

        @include('me.survey-hub._qr_modal')
    </div>
@endsection
