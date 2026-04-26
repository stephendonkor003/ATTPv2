@extends('layouts.app')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">Prescreening Template</h4>
                <p class="text-muted mb-0">
                    Review the section structure and yes/no items configured for this template.
                </p>
            </div>

            <div class="d-flex gap-2">
                <a href="{{ route('prescreening.templates.edit', $template) }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-edit me-1"></i> Edit
                </a>

                <a href="{{ route('prescreening.templates.index') }}" class="btn btn-outline-secondary btn-sm">
                    Back
                </a>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-body row g-3">
                <div class="col-md-4">
                    <div class="text-muted small">Template Name</div>
                    <div class="fw-semibold fs-6">{{ $template->name }}</div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Status</div>
                    <span class="badge {{ $template->is_active ? 'bg-success' : 'bg-secondary' }}">
                        {{ $template->is_active ? 'Active' : 'Inactive' }}
                    </span>
                </div>

                <div class="col-md-2">
                    <div class="text-muted small">Sections</div>
                    <div class="fw-semibold">{{ $template->sections->count() }}</div>
                </div>

                <div class="col-md-3">
                    <div class="text-muted small">Items</div>
                    <div class="fw-semibold">{{ $template->sections->sum(fn ($section) => $section->criteria->count()) }}</div>
                </div>

                <div class="col-md-12">
                    <div class="text-muted small">Description</div>
                    <div class="border rounded p-3 bg-light">
                        {{ $template->description ?? 'No description provided.' }}
                    </div>
                </div>
            </div>
        </div>

        @forelse ($template->sections as $sectionIndex => $section)
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-semibold mb-1">
                                {{ $sectionIndex + 1 }}. {{ $section->name }}
                            </h6>
                            @if ($section->description)
                                <small class="text-muted">{{ $section->description }}</small>
                            @endif
                        </div>

                        <span class="badge bg-primary-subtle text-primary">
                            {{ $section->criteria->count() }} items
                        </span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="60">#</th>
                                    <th>Item</th>
                                    <th>Description</th>
                                    <th width="140" class="text-center">Decision</th>
                                    <th width="130" class="text-center">Mandatory</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($section->criteria as $criterionIndex => $criterion)
                                    <tr>
                                        <td>{{ $criterionIndex + 1 }}</td>
                                        <td class="fw-semibold">{{ $criterion->name }}</td>
                                        <td>{{ $criterion->description ?? '—' }}</td>
                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">Yes / No</span>
                                        </td>
                                        <td class="text-center">
                                            <span class="badge {{ $criterion->is_mandatory ? 'bg-success' : 'bg-secondary' }}">
                                                {{ $criterion->is_mandatory ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-4">
                                            No items defined in this section.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @empty
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    No sections defined for this template.
                </div>
            </div>
        @endforelse
    </div>
@endsection
