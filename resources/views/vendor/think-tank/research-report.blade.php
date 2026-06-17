@extends('layouts.vendor')

@section('title', 'Research Report')

@section('content')
    @php
        use Illuminate\Support\Str;
    @endphp

    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Think Tank Module</div>
            <h3 class="vendor-page-title">Research Report</h3>
            <p class="text-muted mb-0">
                Submit and track research outputs for {{ $member->name }}.
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('vendor.work-plan.index') }}" class="btn btn-vendor-outline">
                <i class="feather-calendar me-1"></i> Work Plan
            </a>
            <button class="btn btn-vendor" data-bs-toggle="modal" data-bs-target="#researchReportModal">
                <i class="feather-upload-cloud me-1"></i> Submit Research
            </button>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row g-3 mb-4">
        @foreach ([
            ['label' => 'Research Outputs', 'value' => $stats['total'], 'icon' => 'feather-book-open'],
            ['label' => 'Submitted', 'value' => $stats['submitted'], 'icon' => 'feather-send'],
            ['label' => 'Approved', 'value' => $stats['approved'], 'icon' => 'feather-check-circle'],
            ['label' => 'With Files', 'value' => $stats['with_files'], 'icon' => 'feather-paperclip'],
        ] as $metric)
            <div class="col-xl-3 col-md-6">
                <div class="card vendor-card h-100">
                    <div class="card-body vendor-metric">
                        <div>
                            <div class="vendor-metric-label">{{ $metric['label'] }}</div>
                            <div class="vendor-metric-value">{{ number_format($metric['value']) }}</div>
                        </div>
                        <span class="vendor-metric-icon"><i class="{{ $metric['icon'] }}"></i></span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('vendor.research-report.index') }}" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Search</label>
                    <input name="q" value="{{ $search }}" class="form-control" placeholder="Search title, abstract, or link">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Output Type</label>
                    <select name="output_type" class="form-select">
                        <option value="">All types</option>
                        @foreach ($typeOptions as $option)
                            <option value="{{ $option }}" @selected($type === $option)>{{ Str::headline($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All statuses</option>
                        @foreach ($statusOptions as $option)
                            <option value="{{ $option }}" @selected($status === $option)>{{ Str::headline($option) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button class="btn btn-vendor flex-fill">Filter</button>
                    <a href="{{ route('vendor.research-report.index') }}" class="btn btn-vendor-outline">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card vendor-card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <div>
                    <div class="vendor-eyebrow">Research Library</div>
                    <h5 class="mb-0">Submitted Research Reports</h5>
                </div>
                <span class="badge-soft">{{ number_format($outputs->count()) }} visible</span>
            </div>

            @if ($outputs->isEmpty())
                <div class="vendor-empty">
                    <span class="vendor-empty-icon mb-3"><i class="feather-book-open"></i></span>
                    <h5>No research reports yet</h5>
                    <p class="text-muted mb-0">Submit your first research output for ATTP review.</p>
                </div>
            @else
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Research</th>
                                <th>Type</th>
                                <th>Published</th>
                                <th>Status</th>
                                <th>Submitted</th>
                                <th class="text-end">Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($outputs as $output)
                                <tr>
                                    <td>
                                        <div class="fw-semibold">{{ $output->title }}</div>
                                        <div class="text-muted small text-truncate" style="max-width: 420px;">
                                            {{ Str::limit($output->abstract ?: 'No abstract provided.', 140) }}
                                        </div>
                                    </td>
                                    <td>{{ Str::headline($output->output_type ?? 'research') }}</td>
                                    <td>{{ $output->published_on?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td><span class="status-pill">{{ Str::headline($output->status ?? 'submitted') }}</span></td>
                                    <td>{{ $output->submitted_at?->format('M d, Y') ?? $output->created_at?->format('M d, Y') }}</td>
                                    <td class="text-end">
                                        <div class="d-flex justify-content-end gap-2 flex-wrap">
                                            @if ($output->file_path)
                                                <a href="{{ route('vendor.research-report.download', $output) }}" class="btn btn-vendor-outline btn-sm">
                                                    <i class="feather-download me-1"></i> File
                                                </a>
                                            @endif
                                            @if ($output->external_url)
                                                <a href="{{ $output->external_url }}" target="_blank" rel="noopener" class="btn btn-vendor-outline btn-sm">
                                                    <i class="feather-external-link me-1"></i> Link
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>

    <div class="modal fade" id="researchReportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('vendor.research-report.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Submit Research Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Title</label>
                            <input name="title" value="{{ old('title') }}" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Output Type</label>
                            <select name="output_type" class="form-select" required>
                                @foreach ([
                                    'research' => 'Research',
                                    'policy_brief' => 'Policy Brief',
                                    'working_paper' => 'Working Paper',
                                    'article' => 'Article',
                                    'dataset' => 'Dataset',
                                    'report' => 'Report',
                                    'other' => 'Other',
                                ] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('output_type', 'research') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Published On</label>
                            <input type="date" name="published_on" value="{{ old('published_on') }}" class="form-control">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">External URL</label>
                            <input type="url" name="external_url" value="{{ old('external_url') }}" class="form-control" placeholder="https://">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Abstract / Summary</label>
                            <textarea name="abstract" rows="5" class="form-control" required>{{ old('abstract') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Upload File</label>
                            <input type="file" name="document" class="form-control">
                            <small class="text-muted">Accepted: PDF, Word, Excel, PowerPoint, images, and ZIP up to 20MB.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-vendor">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
@endsection
