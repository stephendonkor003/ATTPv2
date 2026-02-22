@extends('layouts.app')
@section('title','Methodologies')

@section('content')
<div class="nxl-container">
    <div class="page-header d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="feather-book-open text-primary me-2"></i> Indicator Methodologies</h4>
            <p class="text-muted mb-0">Reusable methods for data collection and calculation.</p>
        </div>
        <a href="{{ route('budget.me-configuration.methodologies.create') }}" class="btn btn-primary btn-sm"><i class="feather-plus me-1"></i> Add Methodology</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Description</th>
                        <th>Survey Engine</th>
                        <th>Status</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($methodologies as $item)
                        @php
                            $surveyMeta = (array) data_get($item->metadata, 'survey', []);
                            $surveyQuestionsCount = collect($surveyMeta['questions'] ?? [])
                                ->filter(fn($question) => is_array($question) && trim((string) ($question['label'] ?? '')) !== '')
                                ->count();
                            $surveyEnabled = (bool) data_get($surveyMeta, 'enabled', false) && $surveyQuestionsCount > 0;
                        @endphp
                        <tr>
                            <td class="ps-4 fw-semibold">{{ $item->name }}</td>
                            <td>{{ $item->description ?? '—' }}</td>
                            <td>
                                @if($surveyEnabled)
                                    <span class="badge bg-primary">Enabled</span>
                                    <small class="text-muted d-block">{{ $surveyQuestionsCount }} question{{ $surveyQuestionsCount === 1 ? '' : 's' }}</small>
                                @else
                                    <span class="text-muted">Not Configured</span>
                                @endif
                            </td>
                            <td><span class="badge {{ $item->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $item->is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="text-end pe-4">
                                <div class="d-inline-flex gap-1">
                                    <a href="{{ route('budget.me-configuration.methodologies.edit', $item) }}" class="btn btn-sm btn-outline-primary"><i class="feather-edit-2"></i></a>
                                    <form action="{{ route('budget.me-configuration.methodologies.destroy', $item) }}" method="POST" onsubmit="return confirm('Delete this methodology?');" class="d-inline">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger"><i class="feather-trash-2"></i></button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-muted py-4">No methodologies found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($methodologies->hasPages())
            <div class="card-footer border-0">{{ $methodologies->links() }}</div>
        @endif
    </div>
</div>
@endsection
