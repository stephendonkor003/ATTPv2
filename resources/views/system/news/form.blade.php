@extends('layouts.app')

@section('title', $post->exists ? 'Edit News' : 'Create News')
@section('lean_admin_scripts', '1')

@php
    $canEditNews = auth()->user()?->canAny(['news.manage', 'communications.respond']) ?? false;
    $canPublishNews = auth()->user()?->canAny(['news.approve', 'communications.respond']) ?? false;
    $isPublicNews = $post->exists && $post->isPublished();
    $isScheduledNews = $post->status === 'published' && $post->published_at?->isFuture();
    $defaultNewsAction = $post->exists ? 'save' : 'draft';
    $statusTone = match ($post->status) {
        'published' => $isPublicNews ? 'live' : 'scheduled',
        'approved' => 'approved',
        'submitted' => 'review',
        'rejected' => 'rejected',
        default => 'draft',
    };
    $newsTitleReady = filled(old('title', $post->title));
    $newsBodyReady = trim(strip_tags((string) old('body', $post->body))) !== '';
    $newsSummaryReady = filled(old('excerpt', $post->excerpt));
    $newsCoverReady = filled($post->cover_image_path);
@endphp

@push('styles')
    <style>
        .news-authoring {
            --news-green-950: #07382b;
            --news-green-800: #0b5c45;
            --news-green-700: #117a59;
            --news-green-100: #e8f5ef;
            --news-gold: #d3a229;
            --news-ink: #17251f;
            --news-muted: #62716a;
            --news-border: #dce6e1;
            --news-surface: #f6f9f7;
            color: var(--news-ink);
        }

        .news-authoring-hero {
            position: relative;
            overflow: hidden;
            margin-bottom: 1rem;
            padding: clamp(1.35rem, 3vw, 2rem);
            border: 1px solid #cfe3d8;
            border-radius: 1rem;
            background:
                radial-gradient(circle at 90% 10%, rgba(211, 162, 41, .22), transparent 28%),
                linear-gradient(130deg, #f7fcf9 0%, #e8f5ef 100%);
            box-shadow: 0 14px 34px rgba(7, 56, 43, .08);
        }

        .news-authoring-hero::after {
            content: "";
            position: absolute;
            right: -4rem;
            bottom: -5rem;
            width: 13rem;
            height: 13rem;
            border: 1.75rem solid rgba(11, 92, 69, .055);
            border-radius: 999px;
            pointer-events: none;
        }

        .news-authoring-eyebrow {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            margin-bottom: .5rem;
            color: var(--news-green-700);
            font-size: .7rem;
            font-weight: 850;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .news-authoring-hero h1 {
            margin: 0 0 .45rem;
            color: var(--news-green-950);
            font-size: clamp(1.45rem, 3vw, 2rem);
            font-weight: 850;
            letter-spacing: -.025em;
        }

        .news-authoring-hero p {
            max-width: 720px;
            margin: 0;
            color: var(--news-muted);
            font-size: .85rem;
            line-height: 1.6;
        }

        .news-authoring-hero-actions {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: .55rem;
        }

        .news-authoring .news-compose-card,
        .news-authoring .news-side-card {
            overflow: hidden;
            border: 1px solid var(--news-border);
            border-radius: .9rem;
            background: #fff;
            box-shadow: 0 10px 28px rgba(20, 42, 33, .055);
        }

        .news-authoring .news-section-heading,
        .news-authoring .news-side-heading {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            padding: 1rem 1.15rem;
            border-bottom: 1px solid var(--news-border);
            background: #fbfdfc;
        }

        .news-authoring .news-section-icon,
        .news-authoring .news-side-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 2.25rem;
            height: 2.25rem;
            border-radius: .65rem;
            background: var(--news-green-100);
            color: var(--news-green-700);
            font-size: .9rem;
        }

        .news-authoring .news-section-heading h2,
        .news-authoring .news-side-heading h2 {
            margin: 0;
            color: var(--news-green-950);
            font-size: .92rem;
            font-weight: 850;
        }

        .news-authoring .news-section-heading p,
        .news-authoring .news-side-heading p {
            margin: .2rem 0 0;
            color: var(--news-muted);
            font-size: .7rem;
            line-height: 1.45;
        }

        .news-authoring .news-compose-body {
            padding: 1.2rem;
        }

        .news-authoring .news-form-group + .news-form-group {
            margin-top: 1rem;
        }

        .news-authoring .form-label {
            margin-bottom: .42rem;
            color: #294239;
            font-size: .76rem;
            font-weight: 800;
        }

        .news-authoring .form-control,
        .news-authoring .form-select {
            min-height: 43px;
            border-color: #cfdcd6;
            border-radius: .57rem;
            color: var(--news-ink);
            font-size: .82rem;
        }

        .news-authoring textarea.form-control {
            min-height: 92px;
            resize: vertical;
        }

        .news-authoring .form-control:focus,
        .news-authoring .form-select:focus {
            border-color: var(--news-green-700);
            box-shadow: 0 0 0 .18rem rgba(17, 122, 89, .12);
        }

        .news-authoring .form-text {
            color: var(--news-muted);
            font-size: .67rem;
            line-height: 1.45;
        }

        .news-authoring .news-field-meta {
            display: flex;
            justify-content: space-between;
            gap: .75rem;
            margin-top: .3rem;
            color: var(--news-muted);
            font-size: .65rem;
        }

        .news-authoring .news-content-divider {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin: 1.25rem 0 1rem;
            color: var(--news-green-800);
            font-size: .7rem;
            font-weight: 850;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .news-authoring .news-content-divider::after {
            content: "";
            flex: 1;
            height: 1px;
            background: var(--news-border);
        }

        .news-editor-wrap .ql-toolbar {
            border-top-left-radius: .62rem;
            border-top-right-radius: .62rem;
            border-color: #cfdcd6;
            background: #f7faf8;
        }

        .news-editor-wrap .ql-container {
            min-height: 390px;
            border-color: #cfdcd6;
            border-bottom-right-radius: .62rem;
            border-bottom-left-radius: .62rem;
            font-size: .9rem;
        }

        .news-editor-wrap .ql-editor {
            min-height: 390px;
            color: var(--news-ink);
            line-height: 1.75;
        }

        .news-editor-fallback {
            min-height: 390px;
            overflow-y: auto;
        }

        .news-editor-wrap.is-invalid .ql-toolbar,
        .news-editor-wrap.is-invalid .ql-container,
        .news-editor-wrap.is-invalid .news-editor-fallback {
            border-color: #dc3545;
        }

        .news-authoring .news-upload-panel {
            height: 100%;
            padding: .9rem;
            border: 1px dashed #b9d4c7;
            border-radius: .7rem;
            background: #f8fbf9;
        }

        .news-authoring .news-upload-panel .form-control {
            background: #fff;
        }

        .cover-preview {
            overflow: hidden;
            padding: .55rem;
            border: 1px solid var(--news-border);
            border-radius: .65rem;
            background: #fff;
        }

        .cover-preview img {
            width: 100%;
            aspect-ratio: 16 / 9;
            border-radius: .5rem;
            object-fit: cover;
            display: block;
        }

        .news-authoring .news-selected-files {
            display: grid;
            gap: .4rem;
            margin-top: .7rem;
        }

        .news-authoring .news-selected-file {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .6rem;
            padding: .5rem .6rem;
            border: 1px solid var(--news-border);
            border-radius: .5rem;
            background: #fff;
            color: #3a5147;
            font-size: .68rem;
        }

        .news-authoring .news-compose-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .75rem;
            padding: .9rem 1.15rem;
            border-top: 1px solid var(--news-border);
            background: #fbfdfc;
        }

        .news-authoring .news-compose-actions p {
            max-width: 390px;
            margin: 0;
            color: var(--news-muted);
            font-size: .68rem;
            line-height: 1.45;
        }

        .news-authoring .news-compose-actions .btn {
            min-height: 40px;
            border-radius: .55rem;
            font-size: .75rem;
            font-weight: 800;
        }

        .news-authoring .news-publish-button {
            border-color: var(--news-green-800);
            background: var(--news-green-800);
            color: #fff;
            box-shadow: 0 8px 18px rgba(11, 92, 69, .18);
        }

        .news-authoring .news-publish-button:hover,
        .news-authoring .news-publish-button:focus {
            border-color: var(--news-green-950);
            background: var(--news-green-950);
            color: #fff;
        }

        .news-authoring .news-sidebar {
            position: sticky;
            top: calc(var(--attp-backoffice-header-offset, 104px) + 1rem);
        }

        .news-authoring .news-side-card + .news-side-card {
            margin-top: 1rem;
        }

        .news-authoring .news-side-body {
            padding: 1rem 1.1rem;
        }

        .news-authoring .news-visibility {
            padding: .8rem;
            border: 1px solid var(--news-border);
            border-radius: .68rem;
            background: var(--news-surface);
        }

        .news-authoring .news-visibility-title {
            display: flex;
            align-items: center;
            gap: .5rem;
            margin-bottom: .3rem;
            color: var(--news-green-950);
            font-size: .8rem;
            font-weight: 850;
        }

        .news-authoring .news-visibility-dot {
            width: .62rem;
            height: .62rem;
            border-radius: 999px;
            background: #87958e;
            box-shadow: 0 0 0 .22rem rgba(135, 149, 142, .12);
        }

        .news-authoring .news-visibility.is-live {
            border-color: #b9ddcc;
            background: #eff9f4;
        }

        .news-authoring .news-visibility.is-live .news-visibility-dot {
            background: #0f9d68;
            box-shadow: 0 0 0 .22rem rgba(15, 157, 104, .13);
        }

        .news-authoring .news-visibility.is-scheduled {
            border-color: #eadba9;
            background: #fffaf0;
        }

        .news-authoring .news-visibility.is-scheduled .news-visibility-dot {
            background: var(--news-gold);
            box-shadow: 0 0 0 .22rem rgba(211, 162, 41, .14);
        }

        .news-authoring .news-visibility p {
            margin: 0;
            color: var(--news-muted);
            font-size: .68rem;
            line-height: 1.5;
        }

        .news-authoring .news-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .3rem .55rem;
            border-radius: 999px;
            background: #edf1ef;
            color: #52635b;
            font-size: .65rem;
            font-weight: 850;
            text-transform: capitalize;
        }

        .news-authoring .news-status-pill.is-live {
            background: #daf2e6;
            color: #096744;
        }

        .news-authoring .news-status-pill.is-scheduled,
        .news-authoring .news-status-pill.is-approved {
            background: #fff2c9;
            color: #755817;
        }

        .news-authoring .news-status-pill.is-review {
            background: #e6effc;
            color: #245b9f;
        }

        .news-authoring .news-status-pill.is-rejected {
            background: #fde7e8;
            color: #a52d35;
        }

        .news-authoring .news-checklist {
            display: grid;
            gap: .55rem;
        }

        .news-authoring .news-check {
            display: flex;
            align-items: flex-start;
            gap: .55rem;
            color: #46594f;
            font-size: .7rem;
            line-height: 1.4;
        }

        .news-authoring .news-check i {
            margin-top: .12rem;
            color: #8b9791;
        }

        .news-authoring .news-check.is-ready i {
            color: var(--news-green-700);
        }

        .news-authoring .news-review-note {
            margin-bottom: .9rem;
            padding: .65rem .7rem;
            border: 1px solid #d7e7df;
            border-radius: .58rem;
            background: #f5faf7;
            color: #496257;
            font-size: .67rem;
            line-height: 1.5;
        }

        .news-authoring .alert {
            border-radius: .7rem;
            font-size: .78rem;
        }

        @media (max-width: 1199.98px) {
            .news-authoring .news-sidebar {
                position: static;
            }
        }

        @media (max-width: 767.98px) {
            .news-authoring-hero-actions,
            .news-authoring .news-compose-actions,
            .news-authoring .news-compose-actions > div {
                align-items: stretch;
                flex-direction: column;
                width: 100%;
            }

            .news-authoring-hero-actions .btn,
            .news-authoring .news-compose-actions .btn {
                width: 100%;
            }

            .news-authoring .news-compose-body {
                padding: 1rem;
            }

            .news-editor-wrap .ql-container,
            .news-editor-wrap .ql-editor,
            .news-editor-fallback {
                min-height: 310px;
            }
        }
    </style>
@endpush

@section('content')
    <div class="news-authoring nxl-container">
        <header class="news-authoring-hero">
            <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-4">
                <div>
                    <div class="news-authoring-eyebrow">
                        <i class="feather-radio" aria-hidden="true"></i> Communications workspace
                    </div>
                    <h1>{{ $post->exists ? 'Edit News' : 'Create News' }}</h1>
                    <p>
                        Build a polished public update, add supporting files, and choose whether to save,
                        request approval, or publish immediately when you have publishing permission.
                    </p>
                </div>
                <div class="news-authoring-hero-actions">
                    @if ($isPublicNews)
                        <a href="{{ route('news.show', $post) }}" class="btn btn-success" target="_blank" rel="noopener">
                            <i class="feather-external-link me-1" aria-hidden="true"></i> View public article
                        </a>
                    @endif
                    <a href="{{ route('system.news.index') }}" class="btn btn-light border">
                        <i class="feather-arrow-left me-1" aria-hidden="true"></i> News register
                    </a>
                </div>
            </div>
        </header>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm" role="status">
                <i class="feather-check-circle me-2" aria-hidden="true"></i>{{ session('success') }}
            </div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger border-0 shadow-sm" role="alert">
                <i class="feather-alert-triangle me-2" aria-hidden="true"></i>{{ session('error') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm" role="alert" tabindex="-1" id="news-validation-summary">
                <div class="fw-semibold mb-1">The news post was not saved:</div>
                <ul class="mb-0 ps-3">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="row g-4 align-items-start">
            <div class="col-xl-8">
                <form id="newsPostForm" class="news-compose-card" method="POST" enctype="multipart/form-data"
                    data-can-edit="{{ $canEditNews ? '1' : '0' }}"
                    data-cover-max-bytes="{{ $newsUploadLimits['cover_bytes'] }}"
                    data-attachment-max-bytes="{{ $newsUploadLimits['attachment_bytes'] }}"
                    data-combined-max-bytes="{{ $newsUploadLimits['combined_bytes'] }}"
                    action="{{ $post->exists ? route('system.news.update', $post) : route('system.news.store') }}">
                    @csrf
                    @if ($post->exists)
                        @method('PUT')
                    @endif
                    <input type="hidden" name="action" id="newsActionInput" value="{{ old('action', $defaultNewsAction) }}">

                    <div class="news-section-heading">
                        <span class="news-section-icon" aria-hidden="true"><i class="feather-edit-3"></i></span>
                        <div>
                            <h2>Article content</h2>
                            <p>Write the information exactly as readers should see it on the public news page.</p>
                        </div>
                    </div>
                    <fieldset class="news-compose-body border-0 m-0" @disabled(! $canEditNews)>
                        <div id="newsClientError" class="alert alert-danger d-none" role="alert"></div>

                        <div class="news-form-group">
                            <label class="form-label" for="newsTitleInput">Headline <span class="text-danger">*</span></label>
                            <input id="newsTitleInput" name="title" class="form-control @error('title') is-invalid @enderror"
                                value="{{ old('title', $post->title) }}" maxlength="255"
                                placeholder="Write a clear, specific news headline" required>
                            <div class="news-field-meta">
                                <span>This becomes the main title on the public page.</span>
                                <span><span data-character-count="newsTitleInput">0</span>/255</span>
                            </div>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="row g-3 news-form-group">
                            <div class="col-md-7">
                                <label class="form-label" for="newsSlugInput">Public URL</label>
                                <div class="input-group">
                                    <span class="input-group-text">/news/</span>
                                    <input id="newsSlugInput" name="slug" class="form-control @error('slug') is-invalid @enderror"
                                    value="{{ old('slug', $post->slug) }}" maxlength="255"
                                    placeholder="Generated automatically">
                                </div>
                                <div class="form-text">Optional. Leave blank to create a unique address from the headline.</div>
                                @error('slug')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="newsCategoryInput">Category <span class="text-danger">*</span></label>
                                <select id="newsCategoryInput" name="category" class="form-select @error('category') is-invalid @enderror" required>
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

                        <div class="news-form-group">
                            <label class="form-label" for="newsExcerptInput">Summary</label>
                            <textarea id="newsExcerptInput" name="excerpt" class="form-control @error('excerpt') is-invalid @enderror"
                                rows="3" maxlength="500"
                                placeholder="A concise introduction shown on news cards and search results">{{ old('excerpt', $post->excerpt) }}</textarea>
                            <div class="news-field-meta">
                                <span>Recommended: one or two sentences.</span>
                                <span><span data-character-count="newsExcerptInput">0</span>/500</span>
                            </div>
                            @error('excerpt')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="news-content-divider"><i class="feather-align-left" aria-hidden="true"></i> Full story</div>
                        <div class="news-form-group">
                            <label class="form-label" for="newsBodyEditor">Article body <span class="text-danger">*</span></label>
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

                        <div class="news-form-group">
                            <label class="form-label" for="newsTagsInput">Search tags</label>
                            <input id="newsTagsInput" name="tags" class="form-control @error('tags') is-invalid @enderror"
                                value="{{ old('tags', implode(', ', $post->tags ?? [])) }}"
                                maxlength="1000"
                                placeholder="e.g. policy, research, African Union">
                            <div class="form-text">Separate tags with commas. Tags help readers discover related updates.</div>
                            @error('tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="news-content-divider"><i class="feather-image" aria-hidden="true"></i> Media and downloads</div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="news-upload-panel">
                                    <label class="form-label" for="coverImageInput">Cover image</label>
                                    <input type="file" name="cover_image" id="coverImageInput"
                                        class="form-control @error('cover_image') is-invalid @enderror"
                                        accept=".jpg,.jpeg,.png,.gif,.bmp,.webp,image/jpeg,image/png,image/gif,image/bmp,image/webp">
                                    <div class="form-text">Use a wide 16:9 image; maximum {{ $newsUploadLimits['cover_label'] }}.</div>
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
                            </div>
                            <div class="col-md-6">
                                <div class="news-upload-panel">
                                    <label class="form-label" for="newsAttachmentsInput">Downloadable attachments</label>
                                    <input type="file" name="attachments[]" id="newsAttachmentsInput"
                                        class="form-control @error('attachments') is-invalid @enderror @error('attachments.*') is-invalid @enderror"
                                        accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.zip,.jpg,.jpeg,.png"
                                        multiple>
                                    <div class="form-text">
                                        Up to 10 files; maximum {{ $newsUploadLimits['attachment_label'] }} each and
                                        {{ $newsUploadLimits['combined_label'] }} combined.
                                    </div>
                                    <div class="news-selected-files" data-selected-attachments aria-live="polite"></div>
                                    @error('attachments')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                    @error('attachments.*')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </fieldset>
                    <div class="news-compose-actions">
                        @if ($canEditNews)
                            <p>
                                @if ($canPublishNews)
                                    Publishing makes the article available on the public News &amp; Updates page immediately.
                                @else
                                    Submit the completed article for review. Only approved and published articles appear publicly.
                                @endif
                            </p>
                            <div class="d-flex flex-wrap justify-content-end gap-2">
                                <button class="btn btn-light border news-submit-button" type="submit"
                                    data-news-action="{{ $post->exists ? 'save' : 'draft' }}">
                                    <i class="feather-save me-1" aria-hidden="true"></i>
                                    {{ $post->exists ? ($isPublicNews ? 'Update live article' : 'Save changes') : 'Save draft' }}
                                </button>
                                @unless ($isPublicNews)
                                    <button class="btn btn-outline-primary news-submit-button" type="submit" data-news-action="submit">
                                        <i class="feather-send me-1" aria-hidden="true"></i> Submit for approval
                                    </button>
                                    @if ($canPublishNews)
                                        <button class="btn news-publish-button news-submit-button" type="submit" data-news-action="publish">
                                            <i class="feather-radio me-1" aria-hidden="true"></i> Publish now
                                        </button>
                                    @endif
                                @endunless
                            </div>
                        @else
                            <span class="text-muted small">
                                Review-only mode. Use the approval panel to record your decision.
                            </span>
                        @endif
                    </div>
                </form>
            </div>

            <div class="col-xl-4">
                <aside class="news-sidebar">
                    <div class="news-side-card">
                        <div class="news-side-heading">
                            <span class="news-side-icon" aria-hidden="true"><i class="feather-eye"></i></span>
                            <div>
                                <h2>Public visibility</h2>
                                <p>Confirm whether readers can currently find this article.</p>
                            </div>
                        </div>
                        <div class="news-side-body">
                            <div class="news-visibility {{ $isPublicNews ? 'is-live' : ($isScheduledNews ? 'is-scheduled' : '') }}">
                                <div class="news-visibility-title">
                                    <span class="news-visibility-dot" aria-hidden="true"></span>
                                    @if ($isPublicNews)
                                        Public now
                                    @elseif ($isScheduledNews)
                                        Scheduled
                                    @else
                                        Not public
                                    @endif
                                </div>
                                <p>
                                    @if ($isPublicNews)
                                        This article is visible on the public News &amp; Updates page.
                                    @elseif ($isScheduledNews)
                                        It will appear automatically on {{ $post->published_at->format('d M Y \a\t H:i') }}.
                                    @elseif ($post->status === 'submitted')
                                        It is awaiting an approval decision. Select “Approve and publish” to make it public.
                                    @elseif ($post->status === 'approved')
                                        It is approved but not published. Approval alone does not make an article public.
                                    @elseif ($post->status === 'rejected')
                                        It was returned for changes and must be submitted or published again.
                                    @else
                                        Drafts are private. Use “Submit for approval” or “Publish now” when ready.
                                    @endif
                                </p>
                            </div>

                            <div class="d-flex align-items-center justify-content-between gap-2 mt-3">
                                <span class="small text-muted">Workflow status</span>
                                <span class="news-status-pill is-{{ $statusTone }}">
                                    <i class="feather-circle" aria-hidden="true"></i>{{ $post->status ?: 'draft' }}
                                </span>
                            </div>

                            @if ($isPublicNews)
                                <a href="{{ route('news.show', $post) }}" class="btn btn-outline-success btn-sm w-100 mt-3"
                                    target="_blank" rel="noopener">
                                    <i class="feather-external-link me-1" aria-hidden="true"></i> Open public article
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="news-side-card">
                        <div class="news-side-heading">
                            <span class="news-side-icon" aria-hidden="true"><i class="feather-check-square"></i></span>
                            <div>
                                <h2>Publishing checklist</h2>
                                <p>Complete the essentials before sending the article live.</p>
                            </div>
                        </div>
                        <div class="news-side-body">
                            <div class="news-checklist">
                                <div class="news-check {{ $newsTitleReady ? 'is-ready' : '' }}">
                                    <i class="feather-{{ $newsTitleReady ? 'check-circle' : 'circle' }}" aria-hidden="true"></i>
                                    <span><strong>Headline</strong><br>A clear public-facing title.</span>
                                </div>
                                <div class="news-check is-ready">
                                    <i class="feather-check-circle" aria-hidden="true"></i>
                                    <span><strong>Category</strong><br>Controls grouping and filtering.</span>
                                </div>
                                <div class="news-check {{ $newsSummaryReady ? 'is-ready' : '' }}">
                                    <i class="feather-{{ $newsSummaryReady ? 'check-circle' : 'circle' }}" aria-hidden="true"></i>
                                    <span><strong>Summary</strong><br>Recommended for stronger news cards.</span>
                                </div>
                                <div class="news-check {{ $newsBodyReady ? 'is-ready' : '' }}">
                                    <i class="feather-{{ $newsBodyReady ? 'check-circle' : 'circle' }}" aria-hidden="true"></i>
                                    <span><strong>Full story</strong><br>Required article content.</span>
                                </div>
                                <div class="news-check {{ $newsCoverReady ? 'is-ready' : '' }}">
                                    <i class="feather-{{ $newsCoverReady ? 'check-circle' : 'circle' }}" aria-hidden="true"></i>
                                    <span><strong>Cover image</strong><br>Recommended; a gallery fallback is used otherwise.</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    @if ($post->exists)
                        @canany(['news.approve', 'communications.respond'])
                            <div class="news-side-card">
                                <div class="news-side-heading">
                                    <span class="news-side-icon" aria-hidden="true"><i class="feather-shield"></i></span>
                                    <div>
                                        <h2>Review and publication</h2>
                                        <p>Approve, schedule, publish, or return the article for changes.</p>
                                    </div>
                                </div>
                                <div class="news-side-body">
                                    <div class="news-review-note">
                                        “Approve only” keeps the article private. Choose “Approve and publish” for public visibility.
                                        Leave the date blank to publish immediately.
                                    </div>
                                    <form method="POST" action="{{ route('system.news.approve', $post) }}">
                                        @csrf
                                        <label class="form-label" for="newsApprovalDecision">Decision</label>
                                        <select id="newsApprovalDecision" name="status" class="form-select mb-3" required>
                                            <option value="approved" @selected($post->status === 'approved')>Approve only — keep private</option>
                                            <option value="published" @selected($post->status === 'published')>Approve and publish</option>
                                            <option value="rejected" @selected($post->status === 'rejected')>Return for changes</option>
                                        </select>

                                        <label class="form-label" for="newsPublishDate">Publish date and time</label>
                                        <input id="newsPublishDate" type="datetime-local" name="published_at" class="form-control mb-1"
                                            value="{{ $post->published_at?->format('Y-m-d\TH:i') }}">
                                        <div class="form-text mb-3">A future time schedules visibility; blank means publish now.</div>

                                        <label class="form-label" for="newsReviewNotes">Review notes</label>
                                        <textarea id="newsReviewNotes" name="review_notes" class="form-control mb-3"
                                            rows="4" placeholder="Optional feedback for the author">{{ $post->review_notes }}</textarea>

                                        <button class="btn news-publish-button w-100" type="submit">
                                            <i class="feather-check me-1" aria-hidden="true"></i> Save decision
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endcanany

                        <div class="news-side-card">
                            <div class="news-side-heading">
                                <span class="news-side-icon" aria-hidden="true"><i class="feather-paperclip"></i></span>
                                <div>
                                    <h2>Saved attachments</h2>
                                    <p>Files currently available with this article.</p>
                                </div>
                            </div>
                            <div class="news-side-body">
                                @forelse ($post->attachments as $attachment)
                                    <div class="border rounded-3 p-3 mb-2">
                                        <div class="fw-semibold">{{ $attachment->title }}</div>
                                        <small class="text-muted">
                                            {{ $attachment->file_name }} &middot;
                                            {{ number_format(($attachment->file_size_bytes ?? 0) / 1024, 1) }} KB
                                        </small>
                                        @canany(['news.manage', 'communications.respond'])
                                            <form method="POST"
                                                action="{{ route('system.news.attachments.destroy', [$post, $attachment]) }}"
                                                class="mt-2">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger" type="submit">
                                                    <i class="feather-trash-2 me-1" aria-hidden="true"></i> Remove
                                                </button>
                                            </form>
                                        @endcanany
                                    </div>
                                @empty
                                    <p class="text-muted mb-0 small">No attachments have been saved.</p>
                                @endforelse
                            </div>
                        </div>
                    @endif
                </aside>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('admin/assets/vendors/js/quill.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const editorElement = document.getElementById('newsBodyEditor');
            const bodyInput = document.getElementById('newsBodyInput');
            const form = bodyInput?.closest('form');
            const actionInput = document.getElementById('newsActionInput');
            const titleInput = document.getElementById('newsTitleInput');
            const slugInput = document.getElementById('newsSlugInput');
            const coverInput = document.getElementById('coverImageInput');
            const attachmentsInput = document.getElementById('newsAttachmentsInput');
            const selectedAttachments = document.querySelector('[data-selected-attachments]');
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

            document.querySelectorAll('[data-character-count]').forEach(function (counter) {
                const input = document.getElementById(counter.dataset.characterCount);

                if (!input) {
                    return;
                }

                const updateCounter = function () {
                    counter.textContent = String(input.value.length);
                };

                input.addEventListener('input', updateCounter);
                updateCounter();
            });

            const slugify = function (value) {
                return value
                    .normalize('NFKD')
                    .replace(/[\u0300-\u036f]/g, '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/^-+|-+$/g, '')
                    .slice(0, 80);
            };

            const updateSlugHint = function () {
                if (!slugInput || slugInput.value.trim()) {
                    return;
                }

                slugInput.placeholder = slugify(titleInput?.value || '') || 'Generated automatically';
            };

            titleInput?.addEventListener('input', updateSlugHint);
            updateSlugHint();

            const formatFileSize = function (bytes) {
                if (bytes < 1024 * 1024) {
                    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
                }

                return `${Number((bytes / (1024 * 1024)).toFixed(1))} MB`;
            };

            const renderSelectedAttachments = function () {
                if (!selectedAttachments) {
                    return;
                }

                selectedAttachments.replaceChildren();
                const files = attachmentsInput?.files ? Array.from(attachmentsInput.files) : [];

                files.forEach(function (file) {
                    const row = document.createElement('div');
                    row.className = 'news-selected-file';

                    const name = document.createElement('span');
                    name.textContent = file.name;
                    name.className = 'text-truncate';

                    const size = document.createElement('span');
                    size.textContent = formatFileSize(file.size);
                    size.className = 'flex-shrink-0 text-muted';

                    row.append(name, size);
                    selectedAttachments.appendChild(row);
                });
            };

            attachmentsInput?.addEventListener('change', renderSelectedAttachments);

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
