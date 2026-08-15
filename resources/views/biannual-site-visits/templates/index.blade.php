@extends('layouts.app')

@section('title', 'Bi-Annual Questionnaire Templates')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('biannual-site-visits.partials.styles')
    <style>
        .basv-library-card {
            overflow: visible !important;
        }

        .basv-library-head {
            padding: 1.15rem 1.25rem;
            background:
                radial-gradient(circle at 92% 20%, rgba(37, 175, 195, .12), transparent 28%),
                linear-gradient(135deg, #f8fcfb 0%, #eef8f5 100%);
        }

        .basv-library-heading {
            display: flex;
            align-items: center;
            gap: .85rem;
        }

        .basv-library-logo,
        .basv-modal-logo {
            border: 1px solid rgba(8, 118, 95, .18);
            border-radius: .7rem;
            background: #086f91;
            object-fit: cover;
            box-shadow: 0 7px 18px rgba(15, 42, 39, .12);
        }

        .basv-library-logo {
            width: 74px;
            height: 46px;
        }

        .basv-library-heading h2 {
            margin: 0;
            color: var(--basv-ink);
            font-size: 1rem;
            font-weight: 850;
        }

        .basv-library-heading p {
            margin: .2rem 0 0;
            color: var(--basv-muted);
            font-size: .69rem;
        }

        .basv-library-summary {
            display: flex;
            flex-wrap: wrap;
            gap: .45rem;
        }

        .basv-library-summary span {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            min-height: 32px;
            padding: .4rem .65rem;
            border: 1px solid #d8e7e2;
            border-radius: 999px;
            background: rgba(255, 255, 255, .85);
            color: #49605a;
            font-size: .66rem;
            font-weight: 800;
        }

        .basv-library-summary strong {
            color: var(--basv-green-dark);
            font-size: .77rem;
        }

        .basv-library-toolbar {
            display: grid;
            grid-template-columns: minmax(240px, 1fr) minmax(170px, 220px);
            gap: .7rem;
            padding: .85rem 1rem;
            border-bottom: 1px solid var(--basv-border);
            background: #fff;
        }

        .basv-library-control {
            position: relative;
        }

        .basv-library-control > i {
            position: absolute;
            z-index: 2;
            top: 50%;
            left: .8rem;
            color: #78908a;
            transform: translateY(-50%);
            pointer-events: none;
        }

        .basv-library-control .form-control {
            padding-left: 2.4rem;
        }

        .basv-library-table {
            min-width: 1050px;
        }

        .basv-library-table thead th {
            padding-top: .8rem;
            padding-bottom: .8rem;
            background: #f4f8f6;
        }

        .basv-library-table tbody tr {
            transition: background .15s ease, transform .15s ease;
        }

        .basv-library-table tbody tr:hover {
            background: #f8fcfa;
        }

        .basv-template-identity {
            display: flex;
            align-items: flex-start;
            gap: .7rem;
            min-width: 300px;
        }

        .basv-template-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex: 0 0 auto;
            width: 40px;
            height: 40px;
            border-radius: .68rem;
            background: linear-gradient(145deg, #e7f6f1, #d9eee8);
            color: var(--basv-green-dark);
            font-size: 1rem;
        }

        .basv-template-description {
            display: block;
            max-width: 420px;
            margin-top: .28rem;
            color: #71827d;
            font-size: .65rem;
            line-height: 1.45;
        }

        .basv-version-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 42px;
            min-height: 32px;
            border: 1px solid #d5e4df;
            border-radius: .55rem;
            background: #fff;
            color: #31584d;
            font-size: .72rem;
            font-weight: 850;
        }

        .basv-coverage {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem;
            min-width: 185px;
        }

        .basv-coverage span {
            display: inline-flex;
            align-items: center;
            gap: .25rem;
            padding: .28rem .45rem;
            border-radius: .42rem;
            background: #edf4f1;
            color: #4f6861;
            font-size: .63rem;
            font-weight: 750;
        }

        .basv-table-actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-end;
            gap: .35rem;
            min-width: 290px;
        }

        .basv-table-actions .basv-btn {
            min-height: 35px;
            padding: .45rem .62rem;
            font-size: .68rem;
        }

        .basv-table-actions form {
            display: inline-flex;
            margin: 0;
        }

        .basv-library-no-results {
            display: none;
            padding: 2.5rem 1rem;
            color: var(--basv-muted);
            text-align: center;
        }

        .basv-template-modal .modal-content {
            overflow: hidden;
            border: 0;
            border-radius: 1rem;
            box-shadow: 0 28px 70px rgba(15, 42, 39, .28);
        }

        .basv-template-modal .modal-header {
            gap: .8rem;
            padding: 1rem 1.15rem;
            border-bottom-color: #dbe8e4;
            background:
                radial-gradient(circle at 90% 10%, rgba(36, 190, 205, .18), transparent 28%),
                linear-gradient(132deg, #063e35, #08765f);
            color: #fff;
        }

        .basv-modal-logo {
            width: 82px;
            height: 48px;
            border-color: rgba(255, 255, 255, .3);
        }

        .basv-template-modal .modal-title {
            color: #fff;
            font-size: .94rem;
            font-weight: 850;
        }

        .basv-modal-meta {
            margin-top: .2rem;
            color: rgba(255, 255, 255, .78);
            font-size: .67rem;
        }

        .basv-template-modal .btn-close {
            filter: invert(1) grayscale(1) brightness(2);
        }

        .basv-preview-frame-wrap {
            position: relative;
            height: min(76vh, 860px);
            min-height: 520px;
            background: #eef5f2;
        }

        .basv-preview-frame {
            width: 100%;
            height: 100%;
            border: 0;
            background: #f7fbf9;
        }

        .basv-preview-loading {
            position: absolute;
            z-index: 2;
            inset: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .6rem;
            background: #eef5f2;
            color: var(--basv-green-dark);
            font-size: .76rem;
            font-weight: 800;
        }

        .basv-template-modal .modal-footer {
            padding: .75rem 1rem;
            border-top-color: #dbe8e4;
            background: #fbfdfc;
        }

        @media (max-width: 767.98px) {
            .basv-library-head {
                align-items: flex-start !important;
                flex-direction: column;
            }

            .basv-library-toolbar {
                grid-template-columns: 1fr;
            }

            .basv-preview-frame-wrap {
                height: calc(100vh - 150px);
                min-height: 420px;
            }
        }
    </style>
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

            @php
                $publishedTemplateCount = $templates->where('status', 'published')->count();
                $draftTemplateCount = $templates->where('status', 'draft')->count();
                $questionTotal = $templates->sum(
                    static fn ($template): int => (int) ($template->questions_count ?? 0)
                );
            @endphp

            <div class="basv-card basv-library-card">
                <div class="basv-card-head basv-library-head">
                    <div class="basv-library-heading">
                        <img src="{{ asset('assets/images/attp-logo.jpeg') }}"
                            alt="Africa Think Tank Platform" class="basv-library-logo">
                        <div>
                            <h2>Template library</h2>
                            <p>Browse, inspect, download, and manage every questionnaire version from one place.</p>
                        </div>
                    </div>
                    <div class="basv-library-summary" aria-label="Template library summary">
                        <span><strong>{{ $templates->count() }}</strong> versions</span>
                        <span><strong>{{ $publishedTemplateCount }}</strong> published</span>
                        <span><strong>{{ $draftTemplateCount }}</strong> drafts</span>
                        <span><strong>{{ number_format($questionTotal) }}</strong> questions</span>
                    </div>
                </div>

                @if ($templates->isEmpty())
                    <div class="basv-empty">
                        <i class="feather-clipboard"></i>
                        <strong>No questionnaire templates yet</strong>
                        <div class="basv-help">Create a blank template or import an Excel workbook above.</div>
                    </div>
                @else
                    <div class="basv-library-toolbar">
                        <div class="basv-library-control">
                            <i class="feather-search" aria-hidden="true"></i>
                            <label class="visually-hidden" for="template-library-search">Search templates</label>
                            <input class="form-control" id="template-library-search" type="search"
                                placeholder="Search by template name, code, or description">
                        </div>
                        <div>
                            <label class="visually-hidden" for="template-library-status">Filter by status</label>
                            <select class="form-select" id="template-library-status">
                                <option value="">All statuses</option>
                                <option value="published">Published</option>
                                <option value="draft">Draft</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="basv-table basv-library-table">
                            <thead>
                                <tr>
                                    <th>Questionnaire template</th>
                                    <th>Version</th>
                                    <th>Coverage</th>
                                    <th>Status</th>
                                    <th>Last activity</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody id="template-library-body">
                                @foreach ($templates as $template)
                                    @php
                                        $sectionCount = (int) ($template->sections_count ?? 0);
                                        $questionCount = (int) ($template->questions_count ?? 0);
                                        $searchText = \Illuminate\Support\Str::lower(implode(' ', [
                                            $template->name,
                                            $template->code,
                                            $template->description,
                                            $template->status,
                                        ]));
                                        $previewUrl = route('biannual-site-visits.templates.preview', [
                                            'template' => $template,
                                            'embed' => 1,
                                        ]);
                                        $fullPreviewUrl = route(
                                            'biannual-site-visits.templates.preview',
                                            $template
                                        );
                                        $downloadUrl = route(
                                            'biannual-site-visits.templates.preview.pdf',
                                            $template
                                        );
                                    @endphp
                                    <tr data-template-row data-status="{{ $template->status }}"
                                        data-search="{{ $searchText }}">
                                        <td>
                                            <div class="basv-template-identity">
                                                <span class="basv-template-icon">
                                                    <i class="feather-file-text" aria-hidden="true"></i>
                                                </span>
                                                <div>
                                                    <div class="d-flex flex-wrap align-items-center gap-2">
                                                        <strong class="basv-record-title">{{ $template->name }}</strong>
                                                        @if ($template->is_default)
                                                            <span class="basv-badge published">Default</span>
                                                        @endif
                                                    </div>
                                                    <span class="basv-record-meta">{{ $template->code }}</span>
                                                    <span class="basv-template-description">
                                                        {{ \Illuminate\Support\Str::limit(
                                                            $template->description ?: 'Reusable Bi-Annual Site Visit questionnaire template.',
                                                            115
                                                        ) }}
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="basv-version-pill">v{{ $template->version }}</span></td>
                                        <td>
                                            <div class="basv-coverage">
                                                <span><i class="feather-layers"></i>{{ $sectionCount }} sections</span>
                                                <span><i class="feather-help-circle"></i>{{ $questionCount }} questions</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="basv-badge {{ $template->status }}">
                                                {{ ucfirst($template->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <strong>{{ optional($template->updated_at)->format('d M Y') }}</strong>
                                            <span class="basv-record-meta">
                                                {{ optional($template->updated_at)->diffForHumans() }}
                                            </span>
                                        </td>
                                        <td>
                                            <div class="basv-table-actions">
                                                <button type="button" class="basv-btn basv-btn-primary"
                                                    data-template-preview
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#template-preview-modal"
                                                    data-preview-url="{{ $previewUrl }}"
                                                    data-full-preview-url="{{ $fullPreviewUrl }}"
                                                    data-download-url="{{ $downloadUrl }}"
                                                    data-template-name="{{ $template->name }}"
                                                    data-template-meta="{{ $template->code }} · Version {{ $template->version }} · {{ $sectionCount }} sections · {{ $questionCount }} questions">
                                                    <i class="feather-eye"></i> Preview
                                                </button>
                                                <a class="basv-btn basv-btn-ghost" href="{{ $downloadUrl }}">
                                                    <i class="feather-download"></i> PDF
                                                </a>
                                                @if ($template->status === 'draft')
                                                    <a class="basv-btn basv-btn-ghost"
                                                        href="{{ route('biannual-site-visits.templates.edit', $template) }}">
                                                        <i class="feather-edit-2"></i> Edit &amp; update
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
                                                @if ($template->status !== 'draft')
                                                    <form method="POST"
                                                        action="{{ route('biannual-site-visits.templates.editable-draft', $template) }}">
                                                        @csrf
                                                        <button class="basv-btn basv-btn-ghost" type="submit">
                                                            <i class="feather-edit-2"></i> Edit as new version
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="basv-library-no-results" id="template-library-no-results">
                        <i class="feather-search d-block mb-2 fs-4"></i>
                        <strong>No templates match this search</strong>
                        <div class="basv-help">Try another keyword or choose a different status.</div>
                    </div>
                @endif
            </div>
        </div>
    </main>

    <div class="modal fade basv-template-modal" id="template-preview-modal" tabindex="-1"
        aria-labelledby="template-preview-title" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <img src="{{ asset('assets/images/attp-logo.jpeg') }}"
                        alt="Africa Think Tank Platform" class="basv-modal-logo">
                    <div class="flex-grow-1">
                        <h2 class="modal-title" id="template-preview-title">Questionnaire template preview</h2>
                        <div class="basv-modal-meta" id="template-preview-meta">
                            Select a template to inspect its complete structure.
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"
                        aria-label="Close template preview"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="basv-preview-frame-wrap">
                        <div class="basv-preview-loading" id="template-preview-loading">
                            <span class="spinner-border spinner-border-sm" aria-hidden="true"></span>
                            Loading questionnaire preview…
                        </div>
                        <iframe class="basv-preview-frame" id="template-preview-frame"
                            src="about:blank" title="Questionnaire template preview"></iframe>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="basv-btn basv-btn-ghost" data-bs-dismiss="modal">Close</button>
                    <a href="#" class="basv-btn basv-btn-ghost" id="template-full-preview-link"
                        target="_blank" rel="noopener">
                        <i class="feather-external-link"></i> Open full preview
                    </a>
                    <a href="#" class="basv-btn basv-btn-primary" id="template-download-link">
                        <i class="feather-download"></i> Download PDF
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('template-library-search');
            const statusSelect = document.getElementById('template-library-status');
            const rows = [...document.querySelectorAll('[data-template-row]')];
            const noResults = document.getElementById('template-library-no-results');

            const filterTemplates = () => {
                if (!rows.length) return;

                const query = (searchInput?.value || '').trim().toLowerCase();
                const status = statusSelect?.value || '';
                let visibleCount = 0;

                rows.forEach(row => {
                    const matchesSearch = !query || row.dataset.search.includes(query);
                    const matchesStatus = !status || row.dataset.status === status;
                    const visible = matchesSearch && matchesStatus;
                    row.hidden = !visible;
                    if (visible) visibleCount += 1;
                });

                if (noResults) {
                    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
                }
            };

            searchInput?.addEventListener('input', filterTemplates);
            statusSelect?.addEventListener('change', filterTemplates);

            const modal = document.getElementById('template-preview-modal');
            const frame = document.getElementById('template-preview-frame');
            const loading = document.getElementById('template-preview-loading');
            const title = document.getElementById('template-preview-title');
            const meta = document.getElementById('template-preview-meta');
            const fullPreviewLink = document.getElementById('template-full-preview-link');
            const downloadLink = document.getElementById('template-download-link');

            document.querySelectorAll('[data-template-preview]').forEach(button => {
                button.addEventListener('click', () => {
                    title.textContent = button.dataset.templateName;
                    meta.textContent = button.dataset.templateMeta;
                    fullPreviewLink.href = button.dataset.fullPreviewUrl;
                    downloadLink.href = button.dataset.downloadUrl;
                    loading.style.display = 'flex';
                    frame.src = button.dataset.previewUrl;
                });
            });

            frame?.addEventListener('load', () => {
                if (frame.src !== 'about:blank') {
                    loading.style.display = 'none';
                }
            });

            modal?.addEventListener('hidden.bs.modal', () => {
                frame.src = 'about:blank';
                loading.style.display = 'flex';
            });
        });
    </script>
@endpush
