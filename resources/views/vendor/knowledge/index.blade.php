@extends('layouts.vendor')

@section('title', 'Knowledge Management')

@section('content')
    <div class="vendor-page-head">
        <div>
            <div class="vendor-eyebrow">Knowledge Management</div>
            <h3 class="mb-1">Document Library</h3>
            <p class="text-muted mb-0">All vendor files submitted through requests, reports, and procurement forms in one place.</p>
        </div>
        <button class="btn btn-vendor" data-bs-toggle="modal" data-bs-target="#uploadKnowledgeModal">
            <i class="feather-upload-cloud me-1"></i> Upload Document
        </button>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="card vendor-card mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('vendor.knowledge.index') }}" class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label fw-semibold">Search</label>
                    <input name="q" value="{{ $search }}" class="form-control" placeholder="Search title, file name, or source">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Document Type</label>
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        @foreach ($types as $option)
                            <option value="{{ $option }}" @selected($type === $option)>{{ $option }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-vendor flex-fill">Filter</button>
                    <a href="{{ route('vendor.knowledge.index') }}" class="btn btn-vendor-outline">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @forelse ($library as $file)
            <div class="col-xl-4 col-md-6">
                <div class="vendor-doc-card">
                    <div class="vendor-doc-icon">
                        <i class="feather-file-text"></i>
                    </div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $file['title'] }}</div>
                        <div class="small text-muted text-truncate">{{ $file['file_name'] }}</div>
                        <div class="d-flex flex-wrap gap-2 mt-2">
                            <span class="badge-soft">{{ $file['document_type'] }}</span>
                            <span class="status-pill">{{ $file['source'] }}</span>
                        </div>
                        <div class="small text-muted mt-2">
                            {{ $file['uploaded_at']?->format('M d, Y') ?? 'N/A' }}
                            @if (!empty($file['size']))
                                &middot; {{ number_format($file['size'] / 1024, 1) }} KB
                            @endif
                        </div>
                    </div>
                    <a href="{{ $file['download_url'] }}" class="btn btn-vendor-outline btn-sm">
                        <i class="feather-download"></i>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="vendor-empty">
                    <div class="vendor-empty-icon"><i class="feather-folder"></i></div>
                    <h5>No documents found</h5>
                    <p class="text-muted mb-0">Submitted files and manual uploads will appear here.</p>
                </div>
            </div>
        @endforelse
    </div>

    <div class="modal fade" id="uploadKnowledgeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('vendor.knowledge.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Upload Knowledge Document</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <input name="title" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Document Type</label>
                        <input name="document_type" class="form-control" placeholder="Policy, Invoice Evidence, Certificate" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tags</label>
                        <input name="tags" class="form-control" placeholder="Comma separated">
                    </div>
                    <div>
                        <label class="form-label fw-semibold">File</label>
                        <input type="file" name="document" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-vendor">Upload</button>
                </div>
            </form>
        </div>
    </div>
@endsection
