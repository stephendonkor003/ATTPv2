@extends('layouts.app')
@section('title', 'Survey Responses')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="mb-1">Survey Responses</h5>
                    <p class="mb-0 text-white-50">
                        Indicator: {{ $surveyLink->indicator->name ?? '—' }} | Methodology: {{ $surveyLink->methodology->name ?? '—' }}
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-light btn-sm"
                        href="{{ route('budget.me.data-sources.surveys.single-export', [$surveyLink, 'format' => 'csv']) }}">
                        <i class="feather-download me-1"></i> Export CSV
                    </a>
                    <a class="btn btn-light btn-sm"
                        href="{{ route('budget.me.data-sources.surveys.single-export', [$surveyLink, 'format' => 'pdf']) }}">
                        <i class="feather-file-text me-1"></i> Export PDF
                    </a>
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
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($responses as $resp)
                                    <tr>
                                        <td>{{ $resp->respondent_name ?? '—' }}</td>
                                        <td>{{ $resp->respondent_email ?? '—' }}</td>
                                        <td>{{ $resp->respondent_organization ?? '—' }}</td>
                                        <td>{{ optional($resp->submitted_at)->format('d M Y H:i') ?? '—' }}</td>
                                        <td><code class="small">{{ json_encode($resp->answers) }}</code></td>
                                        <td>{{ $resp->ip_address ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-3">No responses yet.</td>
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
@endsection
