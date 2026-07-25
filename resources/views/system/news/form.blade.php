@extends('layouts.app')

@section('title', $post->exists ? 'Edit News' : 'Create News')

@php
    $canEditNews = auth()->user()?->canAny(['news.manage', 'communications.respond']) ?? false;
@endphp

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

        .news-editor-fallback {
            min-height: 360px;
            overflow-y: auto;
        }

        .news-editor-wrap.is-invalid .ql-toolbar,
        .news-editor-wrap.is-invalid .ql-container,
        .news-editor-wrap.is-invalid .news-editor-fallback {
            border-color: #dc3545;
        }

        .cover-preview {
            border: 1px solid #dbe2ea;
            border-radius: 8px;
            background: #f8fafc;
            padding: 10px;
        }

        .cover-preview img {
            width: 100%;
            max-height: 220px;
            border-radius: 6px;
            object-fit: cover;
            display: block;
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
        @if (session('error'))
            <div class="alert alert-danger border-0 shadow-sm">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">
                <div class="fw-semibold mb-1">The news post was not saved:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4">
            <div class="col-lg-8">
                <form id="newsPostForm" class="card border-0 shadow-sm" method="POST" enctype="multipart/form-data"
                    data-can-edit="{{ $canEditNews ? '1' : '0' }}"
                    data-cover-max-bytes="{{ $newsUploadLimits['cover_bytes'] }}"
                    data-attachment-max-bytes="{{ $newsUploadLimits['attachment_bytes'] }}"
                    data-combined-max-bytes="{{ $newsUploadLimits['combined_bytes'] }}"
                    action="{{ $post->exists ? route('system.news.update', $post) : route('system.news.store') }}">
                    @csrf
                    @if ($post->exists)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="action" id="newsActionInput" value="{{ old('action', 'draft') }}">

                    <div class="card-header bg-white">
                        <h5 class="mb-0 fw-bold"><i class="feather-file-text me-1"></i> News Details</h5>
                    </div>
                    <fieldset class="card-body border-0 m-0" @disabled(! $canEditNews)>
                        <div id="newsClientError" class="alert alert-danger d-none" role="alert"></div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Title <span class="text-danger">*</span></label>
                            <input name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title', $post->title) }}" maxlength="255" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Slug</label>
                                <input name="slug" class="form-control @error('slug') is-invalid @enderror"
                                    value="{{ old('slug', $post->slug) }}" maxlength="255"
                                    placeholder="Auto-generated if blank">
                                <div class="form-text">Leave blank to generate a unique address from the title.</div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select @error('category') is-invalid @enderror" required>
                                    @foreach (['policy' => 'Policy', 'research' => 'Research', 'events' => 'Events', 'announcement' => 'Announcement', 'press' => 'Press'] as $value => $label)
                                        <option value="{{ $value }}" @selected(old('category', $post->category ?: 'announcement') === $value)>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('category')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Excerpt</label>
                            <textarea name="excerpt" class="form-control @error('excerpt') is-invalid @enderror"
                                rows="3" maxlength="500">{{ old('excerpt', $post->excerpt) }}</textarea>
                            @error('excerpt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Body <span class="text-danger">*</span></label>
                            <input type="hidden" name="body" id="newsBodyInput"
                                value="{{ old('body', $post->body) }}">
                            <div class="news-editor-wrap @error('body') is-invalid @enderror">
                                <div id="newsBodyEditor">{!! old('body', $post->body) !!}</div>
                            </div>
                            <div class="form-text">Use the editor for headings, lists, links, and article text. Add the main image under Cover Image.</div>
                            @error('body')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mt-3">
                            <label class="form-label fw-semibold">Tags</label>
                            <input name="tags" class="form-control @error('tags') is-invalid @enderror"
                                value="{{ old('tags', implode(', ', $post->tags ?? [])) }}"
                                maxlength="1000"
                                placeholder="Comma separated tags">
                            @error('tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cover Image</label>
                                <input type="file" name="cover_image" id="coverImageInput"
                                    class="form-control @error('cover_image') is-invalid @enderror"
                                    accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,image/jpeg,image/png,image/gif,image/bmp,image/webp">
                                <div class="form-text">JPG, PNG, GIF, BMP, or WebP; maximum {{ $newsUploadLimits['cover_label'] }}.</div>
                                @error('cover_image')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div id="coverImagePreviewWrap" class="cover-preview mt-3" @if(! $post->cover_image_url) style="display: none;" @endif>
                                    <div id="coverImagePreviewLabel" class="small text-muted mb-2">
                                        {{ $post->cover_image_url ? 'Current cover image' : 'Selected cover image' }}
                                    </div>
                                    <img id="coverImagePreview"
                                        src="{{ $post->cover_image_url ?: '' }}"
                                        alt="{{ $post->title ? $post->title . ' cover image' : 'News cover image preview' }}"
                                        data-fallback-src="{{ $newsCoverFallbackUrl }}"
                                        onerror="this.onerror=null;this.src=this.dataset.fallbackSrc;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Downloadable Attachments</label>
                                <input type="file" name="attachments[]" id="newsAttachmentsInput"
                                    class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png"
                                    multiple>
                                <div class="form-text">
                                    Up to 10 files; maximum {{ $newsUploadLimits['attachment_label'] }} each and
                                    {{ $newsUploadLimits['combined_label'] }} combined with the cover.
                                </div>
                                @error('attachments')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                @error('attachments.*')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </fieldset>
                    <div class="card-footer bg-white d-flex flex-wrap justify-content-end gap-2">
                        @if ($canEditNews)
                            <button class="btn btn-light border news-submit-button" type="submit" data-news-action="draft">
                                <i class="feather-save me-1"></i> Save Draft
                            </button>
                            <button class="btn btn-primary news-submit-button" type="submit" data-news-action="submit">
                                <i class="feather-send me-1"></i> Submit for Approval
                            </button>
                        @else
                            <span class="text-muted small">
                                Review-only mode. Use the approval panel to record your decision.
                            </span>
                        @endif
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
                    @canany(['news.approve', 'communications.respond'])
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
                    @endcanany

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white">
                            <h5 class="mb-0 fw-bold"><i class="feather-paperclip me-1"></i> Attachments</h5>
                        </div>
                        <div class="card-body">
                            @forelse ($post->attachments as $attachment)
                                <div class="border rounded-3 p-3 mb-2">
                                    <div class="fw-semibold">{{ $attachment->title }}</div>
                                    <small class="text-muted">
                                        {{ $attachment->file_name }} &middot; {{ number_format(($attachment->file_size_bytes ?? 0) / 1024, 1) }} KB
                                    </small>
                                    @canany(['news.manage', 'communications.respond'])
                                        <form method="POST"
                                            action="{{ route('system.news.attachments.destroy', [$post, $attachment]) }}"
                                            class="mt-2">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" type="submit">
                                                <i class="feather-trash-2 me-1"></i> Remove
                                            </button>
                                        </form>
                                    @endcanany
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
            const actionInput = document.getElementById('newsActionInput');
            const coverInput = document.getElementById('coverImageInput');
            const attachmentsInput = document.getElementById('newsAttachmentsInput');
            const coverPreviewWrap = document.getElementById('coverImagePreviewWrap');
            const coverPreview = document.getElementById('coverImagePreview');
            const coverPreviewLabel = document.getElementById('coverImagePreviewLabel');
            const clientError = document.getElementById('newsClientError');
            const submitButtons = Array.from(document.querySelectorAll('.news-submit-button'));
            const canEdit = form?.dataset.canEdit === '1';
            const coverMaxBytes = Number(form?.dataset.coverMaxBytes || 0);
            const attachmentMaxBytes = Number(form?.dataset.attachmentMaxBytes || 0);
            const combinedMaxBytes = Number(form?.dataset.combinedMaxBytes || 0);
            let coverPreviewObjectUrl = null;
            let submitting = false;

            coverInput?.addEventListener('change', function () {
                const file = this.files?.[0];

                if (!file || !coverPreview || !coverPreviewWrap) {
                    return;
                }

                if (coverPreviewObjectUrl) {
                    URL.revokeObjectURL(coverPreviewObjectUrl);
                }

                coverPreviewObjectUrl = URL.createObjectURL(file);
                coverPreview.src = coverPreviewObjectUrl;
                coverPreview.alt = file.name;
                coverPreviewWrap.style.display = '';

                if (coverPreviewLabel) {
                    coverPreviewLabel.textContent = 'Selected cover image';
                }
            });

            if (!editorElement || !bodyInput || !form) {
                return;
            }

            let editorRoot = editorElement;

            if (typeof Quill !== 'undefined') {
                const toolbarOptions = [
                    [{ header: [1, 2, 3, 4, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    [{ align: [] }],
                    ['blockquote', 'code-block'],
                    ['link'],
                    ['clean']
                ];

                const quill = new Quill(editorElement, {
                    theme: 'snow',
                    readOnly: !canEdit,
                    modules: {
                        toolbar: canEdit ? toolbarOptions : false
                    },
                    placeholder: 'Write the full news story here...'
                });

                editorRoot = quill.root;
                quill.on('text-change', function () {
                    bodyInput.value = editorRoot.innerHTML.trim();
                });
            } else {
                editorElement.setAttribute('contenteditable', canEdit ? 'true' : 'false');
                editorElement.classList.add('form-control', 'news-editor-fallback');
                editorElement.setAttribute('aria-label', 'News body');
                editorElement.addEventListener('input', function () {
                    bodyInput.value = editorElement.innerHTML.trim();
                });
            }

            const showClientErrors = function (messages) {
                if (!clientError) {
                    window.alert(messages.join('\n'));
                    return;
                }

                clientError.replaceChildren();
                const heading = document.createElement('div');
                heading.className = 'fw-semibold mb-1';
                heading.textContent = 'The news post was not saved:';
                const list = document.createElement('ul');
                list.className = 'mb-0 ps-3';

                messages.forEach(function (message) {
                    const item = document.createElement('li');
                    item.textContent = message;
                    list.appendChild(item);
                });

                clientError.append(heading, list);
                clientError.classList.remove('d-none');
                clientError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            };

            const selectedFiles = function () {
                return [
                    ...(coverInput?.files ? Array.from(coverInput.files) : []),
                    ...(attachmentsInput?.files ? Array.from(attachmentsInput.files) : [])
                ];
            };

            const formatMegabytes = function (bytes) {
                return `${Number((bytes / (1024 * 1024)).toFixed(1))} MB`;
            };

            submitButtons.forEach(function (button) {
                button.addEventListener('click', function () {
                    if (actionInput) {
                        actionInput.value = button.dataset.newsAction || 'draft';
                    }
                });
            });

            form.addEventListener('submit', function (event) {
                if (submitting) {
                    event.preventDefault();
                    return;
                }

                if (event.submitter?.dataset.newsAction && actionInput) {
                    actionInput.value = event.submitter.dataset.newsAction;
                }

                bodyInput.value = editorRoot.innerHTML.trim();
                const errors = [];
                const textContainer = document.createElement('div');
                textContainer.innerHTML = bodyInput.value;

                if (!textContainer.textContent.replace(/\u00a0/g, ' ').trim()) {
                    errors.push('The news body must contain some text.');
                }

                if (/<img\b[^>]*\bsrc\s*=\s*["']\s*data:image\//i.test(bodyInput.value)) {
                    errors.push('Images cannot be pasted into the article. Upload the main image under Cover Image.');
                }

                const attachmentFiles = attachmentsInput?.files ? Array.from(attachmentsInput.files) : [];

                if (coverMaxBytes && coverInput?.files?.[0]?.size > coverMaxBytes) {
                    errors.push(`The cover image must not be larger than ${formatMegabytes(coverMaxBytes)}.`);
                }

                if (attachmentFiles.length > 10) {
                    errors.push('You may upload no more than 10 attachments at a time.');
                }

                if (attachmentMaxBytes && attachmentFiles.some(file => file.size > attachmentMaxBytes)) {
                    errors.push(`Each attachment must not be larger than ${formatMegabytes(attachmentMaxBytes)}.`);
                }

                const totalBytes = selectedFiles().reduce((total, file) => total + file.size, 0);

                if (combinedMaxBytes && totalBytes > combinedMaxBytes) {
                    errors.push(`The combined upload size must not be larger than ${formatMegabytes(combinedMaxBytes)}.`);
                }

                if (errors.length) {
                    event.preventDefault();
                    showClientErrors(errors);
                    return;
                }

                submitting = true;
                clientError?.classList.add('d-none');
                submitButtons.forEach(function (button) {
                    button.disabled = true;

                    if (button === event.submitter) {
                        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Saving...';
                    }
                });
            });
        });
    </script>
@endpush
