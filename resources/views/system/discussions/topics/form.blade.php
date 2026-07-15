@extends('layouts.app')

@php($isEditing = $topic->exists)

@section('title', $isEditing ? 'Edit Discussion' : 'Create Discussion')

@push('styles')
    @include('system.discussions.partials.styles')
@endpush

@section('content')
    <div class="discussion-admin nxl-container">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-edit-3"></i> Discussion Controls</div>
                    <h1>{{ $isEditing ? 'Edit Discussion' : 'Create Discussion' }}</h1>
                    <p>
                        {{ $isEditing
                            ? 'Refine the topic, participation settings, schedule, and public visibility.'
                            : 'Set the purpose and participation rules for a new public conversation.' }}
                    </p>
                </div>
                <a href="{{ route('system.discussions.topics.index') }}" class="btn btn-light border text-nowrap">
                    <i class="feather-arrow-left me-1"></i> Back to Discussions
                </a>
            </div>
        </section>

        @include('system.discussions.partials.navigation')
        @include('system.discussions.partials.alerts')

        <form method="POST" enctype="multipart/form-data"
            action="{{ $isEditing ? route('system.discussions.topics.update', $topic) : route('system.discussions.topics.store') }}">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            <div class="row g-4">
                <div class="col-xl-8">
                    <section class="card forum-panel mb-4">
                        <div class="card-header">
                            <h2><i class="feather-message-square me-2 text-success"></i>Discussion Content</h2>
                            <small class="forum-muted">Give participants a clear question and enough context to respond well.</small>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label for="title" class="form-label">Discussion title <span class="text-danger">*</span></label>
                                <input id="title" type="text" name="title" maxlength="255" required
                                    class="form-control @error('title') is-invalid @enderror"
                                    value="{{ old('title', $topic->title) }}"
                                    placeholder="Example: How can digital trade benefit small businesses?">
                                @error('title')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-7">
                                    <label for="theme_id" class="form-label">Thematic area</label>
                                    <select id="theme_id" name="theme_id"
                                        class="form-select @error('theme_id') is-invalid @enderror">
                                        <option value="">General discussion</option>
                                        @foreach ($themes as $theme)
                                            <option value="{{ $theme->id }}" @selected(old('theme_id', $topic->theme_id) === $theme->id)>
                                                {{ $theme->name }}{{ $theme->is_active ? '' : ' (inactive)' }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('theme_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-5">
                                    <label for="slug" class="form-label">URL slug <span class="forum-muted fw-normal">(optional)</span></label>
                                    <input id="slug" type="text" name="slug" maxlength="255"
                                        class="form-control @error('slug') is-invalid @enderror"
                                        value="{{ old('slug', $topic->slug) }}" placeholder="Generated from the title">
                                    @error('slug')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="summary" class="form-label">Short summary <span class="text-danger">*</span></label>
                                <textarea id="summary" name="summary" rows="3" maxlength="2000" required
                                    class="form-control @error('summary') is-invalid @enderror"
                                    placeholder="A concise introduction displayed in discussion lists.">{{ old('summary', $topic->summary) }}</textarea>
                                <div class="form-text">Use one or two sentences to explain what feedback is being requested.</div>
                                @error('summary')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="body" class="form-label">Discussion brief</label>
                                <textarea id="body" name="body" rows="12" maxlength="50000"
                                    class="form-control @error('body') is-invalid @enderror"
                                    placeholder="Add background, guiding questions, or participation expectations.">{{ old('body', $topic->body) }}</textarea>
                                <div class="form-text">Plain text is displayed safely in the public forum.</div>
                                @error('body')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </section>

                    <section class="card forum-panel mb-4">
                        <div class="card-header">
                            <h2><i class="feather-paperclip me-2 text-success"></i>Resources &amp; Documents</h2>
                            <small class="forum-muted">Add trusted sources that help participants understand and contribute to the topic.</small>
                        </div>
                        <div class="card-body">
                            @include('system.discussions.topics.partials.resource-list', [
                                'field' => 'related_links',
                                'resourceTitle' => 'Related Links',
                                'resourceIcon' => 'link-2',
                                'resourceHelp' => 'Official pages and useful websites related to this discussion.',
                                'fixedType' => 'link',
                                'typeOptions' => [],
                            ])

                            @include('system.discussions.topics.partials.resource-list', [
                                'field' => 'materials',
                                'resourceTitle' => 'Supporting Materials',
                                'resourceIcon' => 'book-open',
                                'resourceHelp' => 'Research, guidance, datasets, articles, videos, and toolkits.',
                                'typeOptions' => [
                                    'website' => 'Website',
                                    'article' => 'Article',
                                    'brief' => 'Brief',
                                    'dataset' => 'Dataset',
                                    'report' => 'Report',
                                    'toolkit' => 'Toolkit',
                                    'video' => 'Video',
                                    'guidance' => 'Guidance',
                                    'other' => 'Other',
                                ],
                            ])

                            @include('system.discussions.topics.partials.resource-list', [
                                'field' => 'documents',
                                'resourceTitle' => 'External Document Links',
                                'resourceIcon' => 'download',
                                'resourceHelp' => 'Keep using this list for approved documents already hosted on a trusted website.',
                                'typeOptions' => [
                                    'pdf' => 'PDF',
                                    'word' => 'Word',
                                    'spreadsheet' => 'Spreadsheet',
                                    'presentation' => 'Slides',
                                    'text' => 'Text',
                                    'guide' => 'Guide',
                                    'archive' => 'Archive',
                                    'other' => 'Other',
                                ],
                            ])

                            @include('system.discussions.topics.partials.uploaded-documents')
                        </div>
                    </section>

                    <section class="card forum-panel">
                        <div class="card-header">
                            <h2><i class="feather-calendar me-2 text-success"></i>Participation Window</h2>
                            <small class="forum-muted">Leave dates empty to open immediately and continue without a deadline.</small>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="starts_at" class="form-label">Starts at</label>
                                    <input id="starts_at" type="datetime-local" name="starts_at"
                                        class="form-control @error('starts_at') is-invalid @enderror"
                                        value="{{ old('starts_at', $topic->starts_at?->format('Y-m-d\TH:i')) }}">
                                    @error('starts_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6">
                                    <label for="closes_at" class="form-label">Closes at</label>
                                    <input id="closes_at" type="datetime-local" name="closes_at"
                                        class="form-control @error('closes_at') is-invalid @enderror"
                                        value="{{ old('closes_at', $topic->closes_at?->format('Y-m-d\TH:i')) }}">
                                    @error('closes_at')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </section>
                </div>

                <div class="col-xl-4">
                    <div class="sticky-actions">
                        <section class="card forum-panel mb-4">
                            <div class="card-header">
                                <h2><i class="feather-sliders me-2 text-success"></i>Publishing</h2>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Discussion status</label>
                                    <select id="status" name="status" required
                                        class="form-select @error('status') is-invalid @enderror">
                                        @foreach ($topicStatuses as $topicStatus)
                                            <option value="{{ $topicStatus }}" @selected(old('status', $topic->status) === $topicStatus)>
                                                {{ ucfirst($topicStatus) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Only open and closed discussions appear publicly.</div>
                                    @error('status')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="rounded border p-3 mb-3 bg-light">
                                    <input type="hidden" name="is_featured" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" id="is_featured" type="checkbox" name="is_featured"
                                            value="1" @checked((bool) old('is_featured', $topic->is_featured))>
                                        <label class="form-check-label fw-bold text-dark" for="is_featured">Feature discussion</label>
                                    </div>
                                    <div class="small forum-muted mt-1">Give this topic priority on the public forum.</div>
                                </div>

                                <div class="rounded border p-3 mb-3 bg-light">
                                    <div class="d-flex align-items-center gap-2 fw-bold text-dark">
                                        <i class="feather-radio text-success"></i> Open contribution publishing
                                    </div>
                                    <div class="small forum-muted mt-1">
                                        Contributions appear immediately. Moderators can remove content that violates ATTP community rules.
                                    </div>
                                </div>

                                <div class="rounded border p-3">
                                    <input type="hidden" name="allow_replies" value="0">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" id="allow_replies" type="checkbox" name="allow_replies"
                                            value="1" @checked((bool) old('allow_replies', $topic->allow_replies))>
                                        <label class="form-check-label fw-bold text-dark" for="allow_replies">Allow participation</label>
                                    </div>
                                    <div class="small forum-muted mt-1">Turn this off to keep the discussion readable but stop new posts.</div>
                                </div>
                            </div>
                        </section>

                        @if ($isEditing)
                            <section class="card forum-panel mb-4">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="forum-muted">All contributions</span>
                                        <strong>{{ number_format($topic->posts_count) }}</strong>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="forum-muted">Removed for rule violations</span>
                                        <strong class="{{ $topic->removed_posts_count ? 'text-danger' : 'text-success' }}">
                                            {{ number_format($topic->removed_posts_count) }}
                                        </strong>
                                    </div>
                                    @if ($topic->removed_posts_count)
                                        <a href="{{ route('system.discussions.moderation.index', ['status' => 'removed', 'topic_id' => $topic->id]) }}"
                                            class="btn btn-sm btn-light border w-100 mt-3">
                                            View Moderation History
                                        </a>
                                    @endif
                                </div>
                            </section>
                        @endif

                        <div class="d-grid gap-2">
                            <button class="btn btn-success btn-lg">
                                <i class="feather-save me-1"></i>
                                {{ $isEditing ? 'Save Changes' : 'Create Discussion' }}
                            </button>
                            <a href="{{ route('system.discussions.topics.index') }}" class="btn btn-light border">Cancel</a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-resource-list]').forEach(function (list) {
                var rows = list.querySelector('[data-resource-rows]');
                var template = list.querySelector('[data-resource-template]');
                var addButton = list.querySelector('[data-add-resource]');

                function renumberRows() {
                    rows.querySelectorAll('[data-resource-row]').forEach(function (row, index) {
                        var number = row.querySelector('[data-resource-number]');
                        if (number) number.textContent = 'Item ' + (index + 1);
                    });
                }

                addButton.addEventListener('click', function () {
                    var index = Number(list.dataset.nextIndex || 0);
                    var fragment = template.content.cloneNode(true);

                    fragment.querySelectorAll('[name], [id], label[for]').forEach(function (element) {
                        ['name', 'id', 'for'].forEach(function (attribute) {
                            if (element.hasAttribute(attribute)) {
                                element.setAttribute(
                                    attribute,
                                    element.getAttribute(attribute).replaceAll('__INDEX__', String(index))
                                );
                            }
                        });
                    });

                    rows.appendChild(fragment);
                    list.dataset.nextIndex = String(index + 1);
                    renumberRows();
                    rows.lastElementChild.querySelector('input[type="text"]')?.focus();
                });

                rows.addEventListener('click', function (event) {
                    var removeButton = event.target.closest('[data-remove-resource]');
                    if (!removeButton) return;

                    removeButton.closest('[data-resource-row]')?.remove();
                    renumberRows();
                });

                renumberRows();
            });
        });
    </script>
@endpush
