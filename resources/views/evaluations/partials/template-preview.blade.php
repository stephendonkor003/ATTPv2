@php
    $totalCriteria = $evaluation->sections->sum(fn ($section) => $section->criteria->count());
@endphp

<div class="mb-3">
    <div class="d-flex flex-wrap gap-2">
        <span class="badge bg-primary-subtle text-primary">Type: {{ ucfirst($evaluation->type) }}</span>
        <span class="badge bg-info-subtle text-info">Sections: {{ $evaluation->sections->count() }}</span>
        <span class="badge bg-secondary-subtle text-secondary">Criteria: {{ $totalCriteria }}</span>
        <span class="badge bg-dark-subtle text-dark">Status: {{ ucfirst($evaluation->status) }}</span>
    </div>
    @if ($evaluation->description)
        <p class="text-muted mt-2 mb-0">{{ $evaluation->description }}</p>
    @endif
</div>

@forelse ($evaluation->sections as $sectionIndex => $section)
    <div class="card border-0 shadow-sm mb-3">
        <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
            <span>{{ $sectionIndex + 1 }}. {{ $section->name }}</span>
            <span class="badge bg-dark">{{ $section->criteria->count() }} criteria</span>
        </div>
        <div class="card-body">
            @if ($section->description)
                <p class="text-muted mb-3">{{ $section->description }}</p>
            @endif

            @if ($section->criteria->isEmpty())
                <div class="text-muted">No criteria defined in this section.</div>
            @else
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th width="40">#</th>
                                <th>Criteria</th>
                                <th>Description</th>
                                @if ($evaluation->type === 'services')
                                    <th width="120" class="text-end">Max Score</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($section->criteria as $criterionIndex => $criterion)
                                <tr>
                                    <td>{{ $criterionIndex + 1 }}</td>
                                    <td class="fw-semibold">{{ $criterion->name }}</td>
                                    <td>{{ $criterion->description ?: '—' }}</td>
                                    @if ($evaluation->type === 'services')
                                        <td class="text-end">{{ number_format((float) $criterion->max_score, 2) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
@empty
    <div class="alert alert-warning mb-0">
        This evaluation has no sections yet. Configure sections and criteria first.
    </div>
@endforelse
