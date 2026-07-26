@extends('layouts.app')

@section('title', 'Bi-Annual Questionnaire Templates')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
@endpush

@section('content')
    <main class="nxl-container">
        <div class="nxl-content basv-page">
            <div class="basv-hero">
                <div>
                    <span class="basv-eyebrow"><i class="feather-sliders"></i> Configurable assessment engine</span>
                    <h1>Questionnaire Templates</h1>
                    <p>Build questionnaires visually or import a monitoring workbook. Published versions are immutable;
                        visits retain a snapshot so future customization never changes historical assessments.</p>
                </div>
                <div class="basv-hero-actions">
                    <a href="{{ route('biannual-site-visits.index') }}" class="basv-btn basv-btn-light">
                        <i class="feather-arrow-left"></i> Bi-Annual Visits
                    </a>
                </div>
            </div>

            @if (session('success'))
                <div class="basv-alert success"><i class="feather-check-circle me-1"></i>{{ session('success') }}</div>
            @endif
            @if ($errors->any())
                <div class="basv-alert danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-3">
                <div class="col-xl-7">
                    <div class="basv-card h-100">
                        <div class="basv-card-head">
                            <h2><i class="feather-file-plus me-2"></i>Create a blank template</h2>
                            <span class="basv-badge draft">Draft first</span>
                        </div>
                        <div class="basv-card-body">
                            <form method="POST" action="{{ route('biannual-site-visits.templates.store') }}">
                                @csrf
                                <div class="basv-form-grid">
                                    <div>
                                        <label class="form-label">Template name</label>
                                        <input class="form-control" name="name" value="{{ old('name') }}"
                                            placeholder="e.g. Institutional Monitoring Tool" required>
                                    </div>
                                    <div>
                                        <label class="form-label">Template code</label>
                                        <input class="form-control" name="code" value="{{ old('code') }}"
                                            placeholder="Generated automatically when blank">
                                    </div>
                                    <div class="basv-field-full">
                                        <label class="form-label">Purpose</label>
                                        <textarea class="form-control" name="description"
                                            placeholder="Describe when and how this questionnaire should be used.">{{ old('description') }}</textarea>
                                    </div>
                                </div>
                                <div class="text-end mt-3">
                                    <button class="basv-btn basv-btn-primary" type="submit">
                                        <i class="feather-plus"></i> Create and build
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-xl-5">
                    <div class="basv-card h-100">
                        <div class="basv-card-head">
                            <h2><i class="feather-upload-cloud me-2"></i>Import an Excel questionnaire</h2>
                            <span class="basv-badge">.xlsx</span>
                        </div>
                        <div class="basv-card-body">
                            <p class="small text-muted">The importer detects Part rows, merged topic blocks, descriptions,
                                and verification questions. It creates a draft you can refine before publishing.</p>
                            <form method="POST" action="{{ route('biannual-site-visits.templates.import') }}"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="mb-3">
                                    <label class="form-label">Monitoring workbook</label>
                                    <input class="form-control" type="file" name="questionnaire" accept=".xlsx,.xls" required>
                                </div>
                                <button class="basv-btn basv-btn-ghost w-100" type="submit">
                                    <i class="feather-upload"></i> Import into builder
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <div class="basv-card">
                <div class="basv-card-head">
                    <h2><i class="feather-layers me-2"></i>Template library</h2>
                    <span class="basv-badge">{{ $templates->count() }} versions</span>
                </div>
                <div class="table-responsive">
                    @if ($templates->isEmpty())
                        <div class="basv-empty">
                            <i class="feather-clipboard"></i>
                            <strong>No questionnaire templates yet</strong>
                        </div>
                    @else
                        <table class="basv-table">
                            <thead>
                                <tr>
                                    <th>Template</th>
                                    <th>Version</th>
                                    <th>Structure</th>
                                    <th>Status</th>
                                    <th>Updated</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($templates as $template)
                                    <tr>
                                        <td>
                                            <strong class="basv-record-title">{{ $template->name }}</strong>
                                            <span class="basv-record-meta">{{ $template->code }}</span>
                                        </td>
                                        <td>v{{ $template->version }}</td>
                                        <td>
                                            <strong>{{ $template->sections_count ?? $template->sections->count() }} sections</strong>
                                            <span class="basv-record-meta">{{ $template->questions_count ?? 0 }} questions</span>
                                        </td>
                                        <td><span class="basv-badge {{ $template->status }}">{{ $template->status }}</span></td>
                                        <td>{{ optional($template->updated_at)->diffForHumans() }}</td>
                                        <td>
                                            <div class="d-flex justify-content-end gap-1">
                                                @if ($template->status === 'draft')
                                                    <a class="basv-btn basv-btn-ghost"
                                                        href="{{ route('biannual-site-visits.templates.edit', $template) }}">
                                                        <i class="feather-edit-2"></i> Build
                                                    </a>
                                                    <form method="POST"
                                                        action="{{ route('biannual-site-visits.templates.publish', $template) }}"
                                                        onsubmit="return confirm('Publish this version? Its structure will become immutable.')">
                                                        @csrf
                                                        <button class="basv-btn basv-btn-primary" type="submit">
                                                            <i class="feather-send"></i> Publish
                                                        </button>
                                                    </form>
                                                @endif
                                                <form method="POST"
                                                    action="{{ route('biannual-site-visits.templates.duplicate', $template) }}">
                                                    @csrf
                                                    <button class="basv-btn basv-btn-ghost" type="submit"
                                                        title="Create an editable next version">
                                                        <i class="feather-copy"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </main>
@endsection
