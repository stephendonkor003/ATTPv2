@extends('layouts.app')
@section('title', 'Survey Responses')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            @php
                $publicUrl = route('public.me.indicators.surveys.show', ['token' => $surveyLink->public_token]);
                $qrUrl = \App\Support\MeSurvey::qrCodeUrl($publicUrl);
            @endphp

            <div class="page-header d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Survey Responses</h5>
                    <p class="mb-0 text-white-50">
                        Indicator: {{ $surveyLink->indicator->name ?? '-' }} | Methodology: {{ $surveyLink->methodology->name ?? '-' }}
                    </p>
                    <small class="text-white-50">{{ $publicUrl }}</small>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-light btn-sm" href="{{ $publicUrl }}" target="_blank">
                        <i class="feather-external-link me-1"></i> Open Survey
                    </a>
                    <button type="button" class="btn btn-outline-light btn-sm js-open-survey-qr"
                        data-link="{{ $publicUrl }}"
                        data-qr="{{ $qrUrl }}"
                        data-title="{{ $surveyLink->indicator->name ?? 'Survey QR Code' }}">
                        <i class="feather-grid me-1"></i> QR Code
                    </button>
                    <a class="btn btn-light btn-sm"
                        href="{{ route('budget.me.data-sources.surveys.single-export', [$surveyLink, 'format' => 'csv']) }}">
                        <i class="feather-download me-1"></i> Export CSV
                    </a>
                    <a class="btn btn-light btn-sm"
                        href="{{ route('budget.me.data-sources.surveys.single-export', [$surveyLink, 'format' => 'pdf']) }}">
                        <i class="feather-file-text me-1"></i> Export PDF
                    </a>
                    @can('me.configuration.manage')
                        <form action="{{ route('budget.me.surveys.links.destroy', $surveyLink) }}" method="POST"
                            onsubmit="return confirm('Delete this survey and all of its responses?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">
                                <i class="feather-trash-2 me-1"></i> Delete Survey
                            </button>
                        </form>
                    @endcan
                    <a class="btn btn-outline-secondary btn-sm" href="{{ route('budget.me.data-sources.index') }}">
                        <i class="feather-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Respondent</th>
                                    <th>Email</th>
                                    <th>Organization</th>
                                    <th>Submitted At</th>
                                    <th>Answers (JSON)</th>
                                    <th>IP</th>
                                    @can('me.configuration.manage')
                                        <th class="text-end">Actions</th>
                                    @endcan
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($responses as $resp)
                                    <tr>
                                        <td>{{ $resp->respondent_name ?? '-' }}</td>
                                        <td>{{ $resp->respondent_email ?? '-' }}</td>
                                        <td>{{ $resp->respondent_organization ?? '-' }}</td>
                                        <td>{{ optional($resp->submitted_at)->format('d M Y H:i') ?? '-' }}</td>
                                        <td><code class="small">{{ json_encode($resp->answers) }}</code></td>
                                        <td>{{ $resp->ip_address ?? '-' }}</td>
                                        @can('me.configuration.manage')
                                            <td class="text-end">
                                                <form action="{{ route('budget.me.surveys.responses.destroy', $resp) }}" method="POST"
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
                                        <td colspan="{{ auth()->user()?->can('me.configuration.manage') ? 7 : 6 }}" class="text-center text-muted py-3">No responses yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="p-3">
                        {{ $responses->links() }}
                    </div>
                </div>
            </div>
        </div>
    </main>

    <div class="modal fade" id="surveyQrModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="surveyQrModalTitle">Survey QR Code</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" alt="Survey QR code" id="surveyQrModalImage" class="img-fluid rounded border p-2 bg-white">
                    <div class="small text-muted mt-3" id="surveyQrModalLink"></div>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-outline-secondary js-copy-survey-qr-link">Copy Link</button>
                    <button type="button" class="btn btn-primary js-download-survey-qr">Download QR</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modalElement = document.getElementById('surveyQrModal');
            const modal = modalElement && window.bootstrap ? new bootstrap.Modal(modalElement) : null;
            const image = document.getElementById('surveyQrModalImage');
            const title = document.getElementById('surveyQrModalTitle');
            const link = document.getElementById('surveyQrModalLink');
            const state = { link: '', qr: '', title: 'Survey QR Code' };

            document.querySelectorAll('.js-open-survey-qr').forEach((button) => {
                button.addEventListener('click', () => {
                    state.link = button.dataset.link || '';
                    state.qr = button.dataset.qr || '';
                    state.title = button.dataset.title || 'Survey QR Code';

                    if (title) title.textContent = state.title;
                    if (image) image.src = state.qr;
                    if (link) link.textContent = state.link;

                    modal?.show();
                });
            });

            document.querySelector('.js-copy-survey-qr-link')?.addEventListener('click', async () => {
                if (!state.link) return;
                try {
                    await navigator.clipboard.writeText(state.link);
                } catch (error) {
                    window.prompt('Copy survey link:', state.link);
                }
            });

            document.querySelector('.js-download-survey-qr')?.addEventListener('click', async () => {
                if (!state.qr) return;
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
@endpush
