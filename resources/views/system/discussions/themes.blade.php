@extends('layouts.app')

@section('title', 'Discussion Thematic Areas')

@push('styles')
    @include('system.discussions.partials.styles')
@endpush

@section('content')
    <div class="discussion-admin nxl-container">
        <section class="card forum-hero mb-4">
            <div class="card-body d-flex flex-column flex-lg-row align-items-lg-center justify-content-between gap-3">
                <div>
                    <div class="forum-eyebrow"><i class="feather-layers"></i> Discussion Controls</div>
                    <h1>Thematic Areas</h1>
                    <p>Organize public discussions into clear policy and programme areas that participants can explore.</p>
                </div>
                <span class="btn btn-light border text-nowrap disabled">
                    <i class="feather-folder me-1"></i> {{ $themes->count() }} Areas
                </span>
            </div>
        </section>

        @include('system.discussions.partials.navigation')
        @include('system.discussions.partials.alerts')

        <div class="row g-4">
            <div class="col-xl-4">
                <section class="card forum-panel sticky-actions">
                    <div class="card-header">
                        <h2><i class="feather-plus-circle me-2 text-success"></i>Add Thematic Area</h2>
                        <small class="forum-muted">Create a category for related conversations.</small>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('system.discussions.themes.store') }}">
                            @csrf
                            <div class="mb-3">
                                <label for="new-theme-name" class="form-label">Name <span class="text-danger">*</span></label>
                                <input id="new-theme-name" type="text" name="name" required maxlength="255"
                                    class="form-control @error('name') is-invalid @enderror"
                                    value="{{ old('name') }}" placeholder="Example: Digital Trade">
                            </div>
                            <div class="mb-3">
                                <label for="new-theme-slug" class="form-label">URL slug <span class="forum-muted fw-normal">(optional)</span></label>
                                <input id="new-theme-slug" type="text" name="slug" maxlength="255"
                                    class="form-control @error('slug') is-invalid @enderror"
                                    value="{{ old('slug') }}" placeholder="Generated from the name">
                            </div>
                            <div class="mb-3">
                                <label for="new-theme-description" class="form-label">Description</label>
                                <textarea id="new-theme-description" name="description" rows="4" maxlength="4000"
                                    class="form-control @error('description') is-invalid @enderror"
                                    placeholder="What discussions belong in this area?">{{ old('description') }}</textarea>
                            </div>
                            <div class="row g-3 mb-3">
                                <div class="col-7">
                                    <label for="new-theme-icon" class="form-label">Feather icon</label>
                                    <input id="new-theme-icon" type="text" name="icon" maxlength="40"
                                        class="form-control @error('icon') is-invalid @enderror"
                                        value="{{ old('icon', 'message-circle') }}" placeholder="message-circle">
                                </div>
                                <div class="col-5">
                                    <label for="new-theme-color" class="form-label">Colour</label>
                                    <input id="new-theme-color" type="color" name="color"
                                        class="form-control form-control-color w-100 @error('color') is-invalid @enderror"
                                        value="{{ old('color', '#006B3F') }}">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="new-theme-order" class="form-label">Display order</label>
                                <input id="new-theme-order" type="number" name="display_order" min="0" max="9999"
                                    class="form-control @error('display_order') is-invalid @enderror"
                                    value="{{ old('display_order', 0) }}">
                            </div>
                            <input type="hidden" name="is_active" value="0">
                            <div class="form-check form-switch mb-4">
                                <input id="new-theme-active" class="form-check-input" type="checkbox" name="is_active"
                                    value="1" @checked((bool) old('is_active', true))>
                                <label class="form-check-label fw-semibold" for="new-theme-active">Visible in the public forum</label>
                            </div>
                            <button class="btn btn-success w-100">
                                <i class="feather-plus me-1"></i> Add Thematic Area
                            </button>
                        </form>
                    </div>
                </section>
            </div>

            <div class="col-xl-8">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h2 class="forum-section-title mb-1">Configured Areas</h2>
                        <div class="small forum-muted">Change names, visual identity, ordering, and public availability.</div>
                    </div>
                </div>

                @forelse ($themes as $theme)
                    <article class="card forum-panel mb-3">
                        <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <span class="stat-icon" style="color: {{ $theme->color }}; background: {{ $theme->color }}18;">
                                    <i class="feather-{{ $theme->icon }}"></i>
                                </span>
                                <div>
                                    <h2>{{ $theme->name }}</h2>
                                    <div class="small forum-muted">
                                        {{ $theme->topics_count }} discussion(s) &middot;
                                        {{ $theme->open_topics_count }} open
                                    </div>
                                </div>
                            </div>
                            <span class="forum-status {{ $theme->is_active ? 'status-active' : 'status-closed' }}">
                                {{ $theme->is_active ? 'active' : 'hidden' }}
                            </span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="{{ route('system.discussions.themes.update', $theme) }}">
                                @csrf
                                @method('PUT')
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label for="theme-name-{{ $theme->id }}" class="form-label">Name</label>
                                        <input id="theme-name-{{ $theme->id }}" type="text" name="name" required maxlength="255"
                                            class="form-control" value="{{ $theme->name }}">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="theme-slug-{{ $theme->id }}" class="form-label">URL slug</label>
                                        <input id="theme-slug-{{ $theme->id }}" type="text" name="slug" maxlength="255"
                                            class="form-control" value="{{ $theme->slug }}">
                                    </div>
                                    <div class="col-12">
                                        <label for="theme-description-{{ $theme->id }}" class="form-label">Description</label>
                                        <textarea id="theme-description-{{ $theme->id }}" name="description" rows="2" maxlength="4000"
                                            class="form-control">{{ $theme->description }}</textarea>
                                    </div>
                                    <div class="col-sm-5">
                                        <label for="theme-icon-{{ $theme->id }}" class="form-label">Feather icon</label>
                                        <input id="theme-icon-{{ $theme->id }}" type="text" name="icon" maxlength="40"
                                            class="form-control" value="{{ $theme->icon }}">
                                    </div>
                                    <div class="col-sm-3">
                                        <label for="theme-color-{{ $theme->id }}" class="form-label">Colour</label>
                                        <input id="theme-color-{{ $theme->id }}" type="color" name="color"
                                            class="form-control form-control-color w-100" value="{{ $theme->color }}">
                                    </div>
                                    <div class="col-sm-4">
                                        <label for="theme-order-{{ $theme->id }}" class="form-label">Display order</label>
                                        <input id="theme-order-{{ $theme->id }}" type="number" name="display_order" min="0"
                                            max="9999" class="form-control" value="{{ $theme->display_order }}">
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-3 pt-3 border-top">
                                    <div>
                                        <input type="hidden" name="is_active" value="0">
                                        <div class="form-check form-switch">
                                            <input id="theme-active-{{ $theme->id }}" class="form-check-input" type="checkbox"
                                                name="is_active" value="1" @checked($theme->is_active)>
                                            <label class="form-check-label fw-semibold" for="theme-active-{{ $theme->id }}">
                                                Publicly visible
                                            </label>
                                        </div>
                                    </div>
                                    <button class="btn btn-success btn-sm">
                                        <i class="feather-save me-1"></i> Save Changes
                                    </button>
                                </div>
                            </form>

                            @if ($theme->topics_count === 0)
                                <form method="POST" action="{{ route('system.discussions.themes.destroy', $theme) }}"
                                    class="mt-3 text-end"
                                    onsubmit="return confirm('Delete this empty thematic area? This cannot be undone.');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-link text-danger text-decoration-none p-0">
                                        <i class="feather-trash-2 me-1"></i> Delete empty area
                                    </button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <section class="card forum-panel">
                        <div class="forum-empty">
                            <span class="empty-icon"><i class="feather-layers"></i></span>
                            <div class="fw-bold text-dark">No thematic areas configured</div>
                            <div class="small forum-muted">Use the form to create the first public category.</div>
                        </div>
                    </section>
                @endforelse
            </div>
        </div>
    </div>
@endsection
