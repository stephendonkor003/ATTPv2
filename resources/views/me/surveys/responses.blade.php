@extends('layouts.app')
@section('title', 'Survey Responses')

@section('content')
    <div class="nxl-container">
        @php
            $publicUrl = $indicator->surveyLink
                ? route('public.me.indicators.surveys.show', ['token' => $indicator->surveyLink->public_token])
                : null;
            $qrUrl = $publicUrl ? \App\Support\MeSurvey::qrCodeUrl($publicUrl) : null;
        @endphp

        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-inbox text-primary me-2"></i> Survey Responses
                </h4>
                <p class="text-muted mb-0">
                    Indicator: <strong>{{ $indicator->name }}</strong>
                </p>
                @if ($publicUrl)
                    <small class="text-muted">{{ $publicUrl }}</small>
                @endif
            </div>
            <div class="d-flex gap-2">
                @if ($publicUrl)
                    <a href="{{ $publicUrl }}" target="_blank" class="btn btn-outline-primary btn-sm">
                        <i class="feather-external-link me-1"></i> Open Survey
                    </a>
                    <button type="button" class="btn btn-outline-dark btn-sm js-open-survey-qr"
                        data-link="{{ $publicUrl }}"
                        data-qr="{{ $qrUrl }}"
                        data-title="{{ $indicator->name }}">
                        <i class="feather-grid me-1"></i> QR Code
                    </button>
                @endif
                @can('me.configuration.manage')
                    @if ($indicator->surveyLink)
                        <form action="{{ route('budget.me.surveys.links.destroy', $indicator->surveyLink) }}" method="POST"
                            onsubmit="return confirm('Delete this survey and all of its responses?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="feather-trash-2 me-1"></i> Delete Survey
                            </button>
                        </form>
                    @endif
                @endcan
                <a href="{{ route('budget.me.indicators.index', ['tab' => 'settings']) }}" class="btn btn-light border btn-sm">
                    <i class="feather-arrow-left me-1"></i> Back to Indicators
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-0 pb-0 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Captured Responses</h6>
                <span class="badge bg-light text-dark">{{ $responses->total() }} total</span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Submitted</th>
                                <th>Respondent</th>
                                <th>Contact</th>
                                <th>Organization</th>
                                <th>Assigned Responsible Person/Agency</th>
                                <th>Answers</th>
                                @can('me.configuration.manage')
                                    <th class="text-end">Actions</th>
                                @endcan
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($responses as $response)
                                @php
                                    $answerSummary = collect($response->answers ?? [])
                                        ->map(function ($item) {
                                            $section = trim((string) data_get($item, 'section', ''));
                                            $question = trim((string) data_get($item, 'question', 'Question'));
                                            $answer = data_get($item, 'answer', '');

                                            if (is_array($answer)) {
                                                $isAssoc = array_keys($answer) !== range(0, count($answer) - 1);
                                                if ($isAssoc) {
                                                    $answer = collect($answer)
                                                        ->map(fn ($value, $key) => $key . ': ' . $value)
                                                        ->implode(' | ');
                                                } else {
                                                    $answer = implode(', ', $answer);
                                                }
                                            }

                                            $answer = trim((string) $answer);
                                            if ($answer === '') {
                                                $answer = 'No response';
                                            }

                                            $prefix = $section !== '' ? ($section . ' - ') : '';
                                            return $prefix . $question . ': ' . $answer;
                                        })
                                        ->all();

                                    $responsibleRows = collect($response->responsible_snapshot ?? [])
                                        ->map(function ($item) {
                                            $name = trim((string) data_get($item, 'name', ''));
                                            $agency = trim((string) data_get($item, 'agency', ''));
                                            if ($name === '' && $agency === '') {
                                                return null;
                                            }
                                            return $agency !== '' ? ($name . ' (' . $agency . ')') : $name;
                                        })
                                        ->filter()
                                        ->values()
                                        ->all();
                                @endphp
                                <tr>
                                    <td>{{ optional($response->submitted_at)->format('Y-m-d H:i') ?? '-' }}</td>
                                    <td>{{ $response->respondent_name ?: 'Anonymous' }}</td>
                                    <td>
                                        <div>{{ $response->respondent_email ?: '-' }}</div>
                                        <small class="text-muted">{{ $response->respondent_phone ?: '' }}</small>
                                    </td>
                                    <td>{{ $response->respondent_organization ?: '-' }}</td>
                                    <td>
                                        @if (!empty($responsibleRows))
                                            <ul class="mb-0 ps-3">
                                                @foreach ($responsibleRows as $line)
                                                    <li>{{ $line }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-muted">No responsible party attached</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($answerSummary))
                                            <ul class="mb-0 ps-3">
                                                @foreach ($answerSummary as $line)
                                                    <li>{{ $line }}</li>
                                                @endforeach
                                            </ul>
                                        @else
                                            <span class="text-muted">No answers</span>
                                        @endif
                                    </td>
                                    @can('me.configuration.manage')
                                        <td class="text-end">
                                            <form action="{{ route('budget.me.surveys.responses.destroy', $response) }}" method="POST"
                                                onsubmit="return confirm('Delete this response?');" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        </td>
                                    @endcan
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ auth()->user()?->can('me.configuration.manage') ? 7 : 6 }}" class="text-center text-muted py-4">
                                        No responses submitted yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($responses->hasPages())
                    <div class="mt-3">
                        {{ $responses->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    @if ($publicUrl)
        <div class="modal fade" id="surveyQrModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title" id="surveyQrModalTitle">{{ $indicator->name }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body text-center">
                        <img src="{{ $qrUrl }}" alt="Survey QR code" id="surveyQrModalImage" class="img-fluid rounded border p-2 bg-white">
                        <div class="small text-muted mt-3" id="surveyQrModalLink">{{ $publicUrl }}</div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary js-copy-survey-qr-link">Copy Link</button>
                        <button type="button" class="btn btn-primary js-download-survey-qr">Download QR</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection

@push('scripts')
    @if ($publicUrl)
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const modalElement = document.getElementById('surveyQrModal');
                const modal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;
                const state = {
                    link: @json($publicUrl),
                    qr: @json($qrUrl),
                    title: @json($indicator->name),
                };

                document.querySelectorAll('.js-open-survey-qr').forEach((button) => {
                    button.addEventListener('click', () => modal?.show());
                });

                document.querySelector('.js-copy-survey-qr-link')?.addEventListener('click', async () => {
                    try {
                        await navigator.clipboard.writeText(state.link);
                    } catch (error) {
                        window.prompt('Copy survey link:', state.link);
                    }
                });

                document.querySelector('.js-download-survey-qr')?.addEventListener('click', async () => {
                    try {
                        const response = await fetch(state.qr);
                        const blob = await response.blob();
                        const objectUrl = URL.createObjectURL(blob);
                        const anchor = document.createElement('a');
                        anchor.href = objectUrl;
                        anchor.download = `${(state.title || 'survey-qr').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`;
                        document.body.appendChild(anchor);
                        anchor.click();
                        anchor.remove();
                        URL.revokeObjectURL(objectUrl);
                    } catch (error) {
                        window.open(state.qr, '_blank', 'noopener');
                    }
                });
            });
        </script>
    @endif
@endpush
