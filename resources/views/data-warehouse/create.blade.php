@extends('layouts.app')

@push('styles')
    <style>
        .dw-page .dw-panel {
            background: #fff;
            border: 1px solid #dbe3ef;
            border-radius: 8px;
        }

        .dw-page .dw-panel-header {
            border-bottom: 1px solid #dbe3ef;
            padding: 16px 18px;
        }

        .dw-page .dw-panel-body {
            padding: 18px;
        }

        .dw-page .file-row {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #f8fafc;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container dw-page">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1">Add Knowledge Record</h4>
                <p class="text-muted mb-0">Create one main knowledge record and attach multiple titled files.</p>
            </div>
            <a href="{{ route('data-warehouse.index') }}" class="btn btn-outline-secondary">
                <i class="feather-arrow-left me-1"></i> File Library
            </a>
        </div>

        @if ($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ route('data-warehouse.store') }}" enctype="multipart/form-data">
            @csrf

            <div class="dw-panel">
                <div class="dw-panel-header">
                    <h5 class="fw-bold mb-0">Main Information</h5>
                </div>
                <div class="dw-panel-body">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" value="{{ old('title') }}" required>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">Status</label>
                            <select name="status" class="form-select">
                                <option value="published" @selected(old('status', 'published') === 'published')>Published</option>
                                <option value="draft" @selected(old('status') === 'draft')>Draft</option>
                                <option value="archived" @selected(old('status') === 'archived')>Archived</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Knowledge Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">Select category</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected(old('category_id') === $category->id)>
                                        {{ $category->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Or Create New Category</label>
                            <input type="text" name="new_category_name" class="form-control" value="{{ old('new_category_name') }}">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Source</label>
                            <input type="text" name="source_name" class="form-control" value="{{ old('source_name') }}">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reference Period</label>
                            <input type="text" name="reference_period" class="form-control" value="{{ old('reference_period') }}" placeholder="Example: 2018-2024">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Data Owner</label>
                            <input type="text" name="data_owner" class="form-control" value="{{ old('data_owner') }}">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Tags</label>
                            <input type="text" name="tags" class="form-control" value="{{ old('tags') }}" placeholder="Separate tags with commas">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" class="form-control" rows="4">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="dw-panel mt-4">
                <div class="dw-panel-header d-flex justify-content-between align-items-center gap-3">
                    <h5 class="fw-bold mb-0">Files</h5>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="addFileRow">
                        <i class="feather-plus me-1"></i> Add File
                    </button>
                </div>
                <div class="dw-panel-body">
                    <div id="fileRows" class="d-grid gap-3">
                        <div class="file-row">
                            <div class="row g-3">
                                <div class="col-lg-4">
                                    <label class="form-label fw-semibold">File Title</label>
                                    <input type="text" name="file_titles[]" class="form-control">
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label fw-semibold">Upload File <span class="text-danger">*</span></label>
                                    <input type="file" name="files[]" class="form-control" required>
                                </div>
                                <div class="col-lg-4">
                                    <label class="form-label fw-semibold">File Description</label>
                                    <input type="text" name="file_descriptions[]" class="form-control">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-text mt-2">You can attach spreadsheets, PDFs, Word documents, images, ZIP files, and other knowledge files up to 100 MB each.</div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="{{ route('data-warehouse.index') }}" class="btn btn-light">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="feather-upload me-1"></i> Save Knowledge Record
                </button>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const rows = document.getElementById('fileRows');
            const addButton = document.getElementById('addFileRow');

            addButton?.addEventListener('click', function () {
                const row = document.createElement('div');
                row.className = 'file-row';
                row.innerHTML = `
                    <div class="d-flex justify-content-end mb-2">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-file-row">
                            <i class="feather-x me-1"></i> Remove
                        </button>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">File Title</label>
                            <input type="text" name="file_titles[]" class="form-control">
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">Upload File <span class="text-danger">*</span></label>
                            <input type="file" name="files[]" class="form-control" required>
                        </div>
                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">File Description</label>
                            <input type="text" name="file_descriptions[]" class="form-control">
                        </div>
                    </div>
                `;
                rows.appendChild(row);
            });

            rows?.addEventListener('click', function (event) {
                const button = event.target.closest('.remove-file-row');
                if (button) {
                    button.closest('.file-row')?.remove();
                }
            });
        });
    </script>
@endpush
