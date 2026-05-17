@extends('layouts.app')

@section('title', $post->exists ? 'Edit News' : 'Create News')

@push('styles')
    <style>
        .news-editor-wrap .ql-toolbar {
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            background: #f8fafc;
            border-color: #dbe2ea;
        }

        .news-editor-wrap .ql-container {
            min-height: 360px;
            border-bottom-left-radius: 8px;
            border-bottom-right-radius: 8px;
            border-color: #dbe2ea;
            font-size: 1rem;
        }

        .news-editor-wrap .ql-editor {
            min-height: 360px;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="card mb-4 border-0"
            style="background: linear-gradient(120deg, #0f172a, #0f766e 55%, #0ea5e9); color: #fff; border-radius: 14px;">
            <div class="card-body d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <div class="text-uppercase small text-white-50 fw-semibold">News Posting</div>
                    <h4 class="fw-bold mb-1 text-white">{{ $post->exists ? 'Edit News' : 'Create News' }}</h4>
                    <p class="mb-0 text-white-50">Prepare a news item, attach supporting files, and submit it for approval.</p>
                </div>
                <a href="{{ route('system.news.index') }}" class="btn btn-light">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">{{ $errors->first() }}</div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <form class="card border-0 shadow-sm" method="POST" enctype="multipart/form-data"
                    action="{{ $post->exists ? route('system.news.update', $post) : route('system.news.store') }}">
                    @csrf
                    @if ($post->exists)
                        @method('PUT')
                    @endif

                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold"><i class="feather-file-text me-1"></i> News Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input name="title" class="form-control" value="{{ old('title', $post->title) }}" required>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Slug</label>
                                <input name="slug" class="form-control" value="{{ old('slug', $post->slug) }}"
                                    placeholder="Auto-generated if blank">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    @foreach (['policy' => 'Policy', 'research' => 'Research', 'events' => 'Events', 'announcement' => 'Announcement', 'press' => 'Press'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('category', $post->category ?: 'announcement') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Excerpt</label>
                            <textarea name="excerpt" class="form-control" rows="3">{{ old('excerpt', $post->excerpt) }}</textarea>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                            <input type="hidden" name="body" id="newsBodyInput"
                                value="{{ old('body', $post->body) }}" required>
                            <div class="news-editor-wrap">
                                <div id="newsBodyEditor">{!! old('body', $post->body) !!}</div>
                            </div>
                            <div class="form-text">Use the editor for formatted headings, lists, links, images, tables, and article layout.</div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Tags</label>
                            <input name="tags" class="form-control"
                                value="{{ old('tags', implode(', ', $post->tags ?? [])) }}"
                                placeholder="Comma separated tags">
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cover Image</label>
                                <input type="file" name="cover_image" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Downloadable Attachments</label>
                                <input type="file" name="attachments[]" class="form-control" multiple>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2">
                        <button class="btn btn-light border" type="submit" name="action" value="draft">
                            <i class="feather-save me-1"></i> Save Draft
                        </button>
                        <button class="btn btn-primary" type="submit" name="action" value="submit">
                            <i class="feather-send me-1"></i> Submit for Approval
                        </button>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold"><i class="feather-activity me-1"></i> Status</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-2">
                            <span class="badge text-capitalize bg-secondary">{{ $post->status ?: 'draft' }}</span>
                        </p>
                        <div class="small text-muted">Approved</div>
                        <div class="fw-semibold mb-3">{{ optional($post->approved_at)->format('d M Y H:i') ?? 'No' }}</div>
                        <div class="small text-muted">Published</div>
                        <div class="fw-semibold mb-3">{{ optional($post->published_at)->format('d M Y H:i') ?? 'No' }}</div>
                        <div class="small text-muted">Subscribers Notified</div>
                        <div class="fw-semibold">{{ optional($post->notified_at)->format('d M Y H:i') ?? 'No' }}</div>
                    </div>
                </div>

                @if ($post->exists)
                    @can('news.approve')
                        <div class="card border-0 shadow-sm mb-4">
                            <div class="card-header bg-white">
                                <h5 class="mb-0 fw-bold"><i class="feather-check-circle me-1"></i> Approval</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="{{ route('system.news.approve', $post) }}">
                                    @csrf
                                    <label class="form-label fw-semibold">Decision</label>
                                    <select name="status" class="form-select mb-3" required>
                                        <option value="approved">Approve only</option>
                                        <option value="published">Approve and publish</option>
                                        <option value="rejected">Reject</option>
                                    </select>

                                    <label class="form-label fw-semibold">Publish Date</label>
                                    <input type="datetime-local" name="published_at" class="form-control mb-3">

                                    <label class="form-label fw-semibold">Review Notes</label>
                                    <textarea name="review_notes" class="form-control mb-3" rows="4">{{ $post->review_notes }}</textarea>

                                    <button class="btn btn-success w-100" type="submit">
                                        <i class="feather-check me-1"></i> Save Approval
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endcan

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 fw-bold"><i class="feather-paperclip me-1"></i> Attachments</h5>
                        </div>
                        <div class="card-body">
                            @forelse ($post->attachments as $attachment)
                                <div class="border rounded-3 p-3 mb-2">
                                    <div class="fw-semibold">{{ $attachment->title }}</div>
                                    <small class="text-muted">
                                        {{ $attachment->file_name }} · {{ number_format(($attachment->file_size_bytes ?? 0) / 1024, 1) }} KB
                                    </small>
                                    @can('news.manage')
                                        <form method="POST"
                                            action="{{ route('system.news.attachments.destroy', [$post, $attachment]) }}"
                                            class="mt-2">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                <i class="feather-trash-2 me-1"></i> Remove
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            @empty
                                <p class="text-muted mb-0">No attachments uploaded.</p>
                            @endforelse
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editorElement = document.getElementById('newsBodyEditor');
            const bodyInput = document.getElementById('newsBodyInput');
            const form = bodyInput?.closest('form');

            if (!editorElement || !bodyInput || typeof Quill === 'undefined') {
                return;
            }

            const toolbarOptions = [
                [{ header: [1, 2, 3, 4, false] }],
                ['bold', 'italic', 'underline', 'strike'],
                [{ color: [] }, { background: [] }],
                [{ list: 'ordered' }, { list: 'bullet' }],
                [{ align: [] }],
                ['blockquote', 'code-block'],
                ['link', 'image'],
                ['clean']
            ];

            const quill = new Quill(editorElement, {
                theme: 'snow',
                modules: {
                    toolbar: toolbarOptions
                },
                placeholder: 'Write the full news story here...'
            });

            const syncBody = function () {
                bodyInput.value = quill.root.innerHTML.trim();
            };

            quill.on('text-change', syncBody);
            form?.addEventListener('submit', syncBody);
        });
    </script>
@endpush
