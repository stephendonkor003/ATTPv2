@extends('layouts.app')
@section('title', 'Survey Responses')

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1">
                <i class="feather-inbox text-primary me-2"></i> Survey Responses
            </h4>
            <p class="text-muted mb-0">
                Indicator: <strong>{{ $indicator->name }}</strong>
            </p>
        </div>
        <a href="{{ route('budget.me.indicators.index', ['tab' => 'settings']) }}" class="btn btn-light border btn-sm">
            <i class="feather-arrow-left me-1"></i> Back to Indicators
        </a>
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
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($responses as $response)
                            @php
                                $answerSummary = collect($response->answers ?? [])
                                    ->map(function ($item) {
                                        $question = trim((string) data_get($item, 'question', 'Question'));
                                        $answer = data_get($item, 'answer', '');
                                        if (is_array($answer)) {
                                            $answer = implode(', ', $answer);
                                        }
                                        $answer = trim((string) $answer);
                                        if ($answer === '') {
                                            $answer = 'No response';
                                        }
                                        return $question . ': ' . $answer;
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
                                <td>{{ optional($response->submitted_at)->format('Y-m-d H:i') ?? '—' }}</td>
                                <td>{{ $response->respondent_name ?: 'Anonymous' }}</td>
                                <td>
                                    <div>{{ $response->respondent_email ?: '—' }}</div>
                                    <small class="text-muted">{{ $response->respondent_phone ?: '' }}</small>
                                </td>
                                <td>{{ $response->respondent_organization ?: '—' }}</td>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
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
@endsection

