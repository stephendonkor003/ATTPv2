@extends('layouts.app')

@section('title', 'Knowledge and Evidence Repository')

@section('content')
    <div class="container-fluid">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3 mb-4">
            <div>
                <div class="text-muted small fw-semibold text-uppercase mb-1">Monitoring &amp; Evaluation</div>
                <h3 class="mb-1">Knowledge and Evidence Repository</h3>
                <p class="text-muted mb-0">Manage MEAL plans, theories of change, evidence and indicator Means of Verification.</p>
            </div>
            <a href="{{ route('budget.me.indicators.index') }}" class="btn btn-outline-primary">
                <i class="feather-target me-1"></i> Indicator register
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success" role="status">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger" role="alert">
                <div class="fw-semibold mb-1">Please correct the repository item.</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @can('me.configuration.manage')
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Add evidence</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="{{ route('budget.me.knowledge-evidence.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-lg-4">
                                <label for="evidence-portfolio" class="form-label">Portfolio <span class="text-danger">*</span></label>
                                <select id="evidence-portfolio" name="portfolio_id" class="form-select @error('portfolio_id') is-invalid @enderror" required>
                                    <option value="">Select portfolio</option>
                                    @foreach ($portfolios as $portfolio)
                                        <option value="{{ $portfolio->id }}" @selected((string) old('portfolio_id') === (string) $portfolio->id)>{{ $portfolio->name }}</option>
                                    @endforeach
                                </select>
                                @error('portfolio_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-5">
                                <label for="evidence-title" class="form-label">Title <span class="text-danger">*</span></label>
                                <input id="evidence-title" name="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" maxlength="255" required>
                                @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-3">
                                <label for="evidence-type" class="form-label">Document type <span class="text-danger">*</span></label>
                                <select id="evidence-type" name="document_type" class="form-select @error('document_type') is-invalid @enderror" required>
                                    @foreach ($documentTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('document_type', 'means_of_verification') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('document_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-6">
                                <label for="evidence-file" class="form-label">Upload document</label>
                                <input id="evidence-file" type="file" name="evidence_file" class="form-control @error('evidence_file') is-invalid @enderror" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png">
                                <div class="form-text">Maximum 20 MB. A file or external URL is required.</div>
                                @error('evidence_file')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-lg-6">
                                <label for="evidence-url" class="form-label">External URL</label>
                                <input id="evidence-url" type="url" name="external_url" class="form-control @error('external_url') is-invalid @enderror" value="{{ old('external_url') }}" maxlength="2000" placeholder="https://">
                                @error('external_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12">
                                <label for="evidence-description" class="form-label">Description</label>
                                <textarea id="evidence-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="2" maxlength="5000">{{ old('description') }}</textarea>
                                @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="feather-upload me-1"></i> Add to repository
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        @endcan

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3">
                <form method="GET" action="{{ route('budget.me.rebuild.knowledge-repository') }}" class="row g-2 align-items-end">
                    <div class="col-lg-6">
                        <label for="repository-search" class="form-label">Search repository</label>
                        <input id="repository-search" type="search" name="q" class="form-control" value="{{ $search }}" placeholder="Title, description, file or URL">
                    </div>
                    <div class="col-lg-4">
                        <label for="repository-portfolio" class="form-label">Portfolio</label>
                        <select id="repository-portfolio" name="portfolio_id" class="form-select">
                            <option value="">All portfolios</option>
                            @foreach ($portfolios as $portfolio)
                                <option value="{{ $portfolio->id }}" @selected((string) $selectedPortfolioId === (string) $portfolio->id)>{{ $portfolio->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-lg-2 d-flex gap-2">
                        <button class="btn btn-outline-primary flex-grow-1">Filter</button>
                        @if ($search !== '' || $selectedPortfolioId !== '')
                            <a href="{{ route('budget.me.rebuild.knowledge-repository') }}" class="btn btn-light border">Clear</a>
                        @endif
                    </div>
                </form>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Evidence</th>
                            <th>Portfolio</th>
                            <th>Type</th>
                            <th>Linked indicators</th>
                            <th>Validation</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $item->title }}</div>
                                    @if ($item->description)
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($item->description, 120) }}</div>
                                    @endif
                                    <div class="text-muted small mt-1">
                                        Added {{ $item->created_at?->format('Y-m-d') }}
                                        @if ($item->creator) by {{ $item->creator->name }} @endif
                                    </div>
                                </td>
                                <td>{{ $item->portfolio?->name ?: '—' }}</td>
                                <td><span class="badge bg-primary-subtle text-primary">{{ $item->typeLabel() }}</span></td>
                                <td>{{ number_format((int) $item->indicators_count) }}</td>
                                <td>
                                    <span class="badge {{ $item->validation_status === 'validated' ? 'bg-success' : ($item->validation_status === 'rejected' ? 'bg-danger' : 'bg-warning text-dark') }}">
                                        {{ \Illuminate\Support\Str::headline($item->validation_status ?: 'pending') }}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        @if ($item->file_path)
                                            <a href="{{ route('budget.me.knowledge-evidence.download', $item) }}" class="btn btn-sm btn-light border">
                                                <i class="feather-download me-1"></i> Download
                                            </a>
                                        @endif
                                        @if ($item->external_url)
                                            <a href="{{ $item->external_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-light border">
                                                <i class="feather-external-link me-1"></i> Open
                                            </a>
                                        @endif
                                        @can('me.configuration.manage')
                                            @if($item->document_type === 'means_of_verification' && $item->validation_status !== 'validated')
                                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.validate', $item) }}">
                                                    @csrf
                                                    <input type="hidden" name="validation_status" value="validated">
                                                    <button class="btn btn-sm btn-outline-success" title="Validate Means of Verification">
                                                        <i class="feather-check-circle"></i>
                                                    </button>
                                                </form>
                                            @endif
                                            <form method="POST" action="{{ route('budget.me.knowledge-evidence.destroy', $item) }}" onsubmit="return confirm('Remove this evidence item?');">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" @disabled((int) $item->indicators_count > 0) title="{{ (int) $item->indicators_count > 0 ? 'Linked evidence cannot be deleted' : 'Delete evidence' }}">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">No knowledge or evidence items match this view.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($items->hasPages())
                <div class="card-footer bg-white">{{ $items->links() }}</div>
            @endif
        </div>
    </div>
@endsection
