@extends('layouts.app')

@section('title', 'M&E Matrix Control Centre')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.matrices.partials.styles')
@endpush

@section('content')
@php
    $activeFilterCount = collect($filters)
        ->except(['matrix_id', 'sort', 'per_page'])
        ->filter(fn ($value) => filled($value))
        ->count();
    $preservedFilters = collect($filters)
        ->except('matrix_id')
        ->reject(fn ($value) => $value === null || $value === '')
        ->reject(fn ($value, $key) => ($key === 'sort' && $value === 'newest') || ($key === 'per_page' && (int) $value === 25))
        ->all();
    $matrixUrl = fn ($matrix) => route('budget.me.matrices.index', array_merge($preservedFilters, ['matrix_id' => $matrix->id])).'#matrix-detail';
    $statusTone = fn ($status) => match ($status) {
        'active' => 'success',
        'retired' => 'neutral',
        default => 'warning',
    };
    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1).' KB';
        return number_format($bytes).' B';
    };
    $selectedInspection = $selectedMatrix?->inspectionTotals() ?? [
        'sheet_count' => 0,
        'data_rows' => 0,
        'data_columns' => 0,
        'formula_cells' => 0,
        'validated_cells' => 0,
    ];
    $oldContext = old('form_context');
@endphp

<div class="mel-matrices">
    <header class="mx-header">
        <div class="mx-header-copy">
            <span class="mx-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>M&amp;E Matrix control centre</h1>
            <p>Govern the official ATTP results matrix through controlled versions, workbook inspection, effective dates, approvals and repository-backed audit evidence.</p>
        </div>
        <div class="mx-header-side">
            <span class="mx-generated">Register updated {{ $generatedAt->format('d M Y, H:i') }}</span>
            <div class="mx-actions">
                <a class="mx-btn mx-btn-header" href="{{ route('budget.me.rebuild.knowledge-repository') }}">Evidence repository</a>
                <a class="mx-btn mx-btn-header" href="{{ route('budget.me.matrices.pdf', $exportQuery) }}">Download PDF register</a>
                @if($canManage)
                    <a class="mx-btn mx-btn-header mx-btn-solid" href="#matrix-upload" data-matrix-open="matrix-upload">Upload version</a>
                @endif
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="mx-alert success" role="status"><span>OK</span><div><strong>Matrix register updated</strong><p>{{ session('success') }}</p></div></div>
    @endif
    @if($errors->any())
        <div class="mx-alert danger" role="alert"><span>!</span><div><strong>The matrix change could not be completed</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <aside class="mx-governance" aria-label="Matrix governance guidance">
        <span class="mx-governance-mark">CTRL</span>
        <div>
            <strong>One controlled source of truth</strong>
            <p>Uploads are retained as drafts until activation. Activating a version validates its repository record and retires the previous active version with the same matrix code; retired files remain downloadable for audit.</p>
        </div>
    </aside>

    <section class="mx-metrics" aria-label="Matrix portfolio summary">
        <a class="mx-metric" style="--metric:#075c7a" href="{{ route('budget.me.matrices.index', $preservedFilters) }}"><span>Matrix versions</span><strong>{{ number_format($metrics['total']) }}</strong><small>{{ number_format($metrics['codes']) }} controlled matrix {{ str('code')->plural($metrics['codes']) }}</small></a>
        <a class="mx-metric" style="--metric:#187459" href="{{ route('budget.me.matrices.index', array_merge($preservedFilters, ['status' => 'active'])) }}"><span>Active versions</span><strong>{{ number_format($metrics['active']) }}</strong><small>{{ number_format($metrics['active_coverage'], 1) }}% code activation coverage</small></a>
        <a class="mx-metric" style="--metric:#b8791f" href="{{ route('budget.me.matrices.index', array_merge($preservedFilters, ['status' => 'draft'])) }}"><span>Drafts awaiting action</span><strong>{{ number_format($metrics['draft']) }}</strong><small>Inspect and activate or remove</small></a>
        <a class="mx-metric" style="--metric:#6b63a8" href="#matrix-analytics"><span>Workbook sheets</span><strong>{{ number_format($metrics['sheets']) }}</strong><small>{{ number_format($metrics['rows']) }} inspected rows</small></a>
        <a class="mx-metric" style="--metric:#1676b8" href="#matrix-analytics"><span>Workbook controls</span><strong>{{ number_format($metrics['validations']) }}</strong><small>{{ number_format($metrics['formulas']) }} formula cells</small></a>
        <a class="mx-metric" style="--metric:#4d6b74" href="#matrix-register"><span>Controlled storage</span><strong>{{ $formatBytes($metrics['storage_bytes']) }}</strong><small>{{ number_format($metrics['portfolios']) }} represented {{ str('portfolio')->plural($metrics['portfolios']) }}</small></a>
    </section>

    <details class="mx-panel mx-filter" @if($activeFilterCount > 0) open @endif>
        <summary class="mx-panel-head">
            <div><h2>Search and register scope</h2><p>Every metric, chart, table row and consolidated PDF uses this same authorized scope.</p></div>
            <div class="mx-summary-right"><span class="mx-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span><span class="mx-chevron">⌄</span></div>
        </summary>
        <div class="mx-panel-body">
            <form method="GET" action="{{ route('budget.me.matrices.index') }}" class="mx-filter-grid" data-matrix-filter>
                <div class="mx-field mx-field-wide">
                    <label for="matrix-search">Search matrix register</label>
                    <input class="form-control" id="matrix-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Title, matrix code, change summary, portfolio or filename">
                    <small>Search is case-insensitive and updates the analytics and register together.</small>
                </div>
                <div class="mx-field"><label for="matrix-portfolio">Portfolio</label><select class="form-select" id="matrix-portfolio" name="portfolio_id"><option value="">All authorized portfolios</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected((string) $filters['portfolio_id'] === (string) $portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                <div class="mx-field"><label for="matrix-status">Lifecycle status</label><select class="form-select" id="matrix-status" name="status"><option value="">All statuses</option>@foreach($statuses as $value => $label)<option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="mx-field"><label for="matrix-format">File format</label><select class="form-select" id="matrix-format" name="format"><option value="">All formats</option>@foreach(['XLSX','XLS','CSV','PDF'] as $format)<option value="{{ $format }}" @selected($filters['format'] === $format)>{{ $format }}</option>@endforeach</select></div>
                <div class="mx-field"><label for="matrix-effective">Effective dates</label><select class="form-select" id="matrix-effective" name="effective_state"><option value="">All effective dates</option><option value="current" @selected($filters['effective_state'] === 'current')>Current</option><option value="upcoming" @selected($filters['effective_state'] === 'upcoming')>Upcoming</option><option value="expired" @selected($filters['effective_state'] === 'expired')>Expired</option><option value="undated" @selected($filters['effective_state'] === 'undated')>Not dated</option></select></div>
                <div class="mx-field"><label for="matrix-sort">Sort register</label><select class="form-select" id="matrix-sort" name="sort"><option value="newest" @selected($filters['sort'] === 'newest')>Recently uploaded</option><option value="oldest" @selected($filters['sort'] === 'oldest')>Oldest uploaded</option><option value="title" @selected($filters['sort'] === 'title')>Document title</option><option value="code" @selected($filters['sort'] === 'code')>Matrix code</option><option value="version" @selected($filters['sort'] === 'version')>Highest version</option><option value="status" @selected($filters['sort'] === 'status')>Lifecycle priority</option></select></div>
                <div class="mx-field"><label for="matrix-page-size">Rows per page</label><select class="form-select" id="matrix-page-size" name="per_page">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected((int) $filters['per_page'] === $size)>{{ $size }} rows</option>@endforeach</select></div>
                <div class="mx-filter-actions">
                    <p><strong>Reporting tip:</strong> apply filters before downloading the PDF to produce a matching control register.</p>
                    <div class="mx-actions"><a class="mx-btn mx-btn-secondary" href="{{ route('budget.me.matrices.index') }}">Clear filters</a><button class="mx-btn mx-btn-primary" type="submit">Apply scope</button></div>
                </div>
            </form>
        </div>
    </details>

    <section class="mx-analytics" id="matrix-analytics" aria-label="Matrix analytics">
        <article class="mx-panel mx-chart-panel">
            <div class="mx-panel-head"><div><h2>Lifecycle distribution</h2><p>Controlled versions by governance state and file format.</p></div><span class="mx-badge">{{ number_format($metrics['total']) }} versions</span></div>
            <div id="matrix-status-chart" class="mx-chart" role="img" aria-label="Donut chart of matrix versions by lifecycle status"></div>
            <div class="mx-legend">@foreach($charts['formats'] as $format)<a href="{{ route('budget.me.matrices.index', array_merge($preservedFilters, ['format' => $format['key']])) }}"><i style="--legend:{{ $format['color'] }}"></i><span>{{ $format['label'] }}</span><strong>{{ $format['count'] }}</strong></a>@endforeach</div>
        </article>
        <article class="mx-panel mx-chart-panel mx-chart-panel-wide">
            <div class="mx-panel-head"><div><h2>Version activity</h2><p>New matrix versions recorded during the last twelve months.</p></div><span class="mx-badge">12 months</span></div>
            <div id="matrix-activity-chart" class="mx-chart" role="img" aria-label="Area chart of matrix uploads during the last twelve months"></div>
        </article>
        <article class="mx-panel mx-chart-panel">
            <div class="mx-panel-head"><div><h2>Portfolio coverage</h2><p>Version volume across the eight leading portfolios in this scope.</p></div><span class="mx-badge">Top 8</span></div>
            <div id="matrix-portfolio-chart" class="mx-chart mx-chart-tall" role="img" aria-label="Horizontal bar chart of matrix versions by portfolio"></div>
        </article>
    </section>

    <section class="mx-panel mx-register" id="matrix-register" aria-labelledby="matrix-register-title">
        <div class="mx-panel-head">
            <div><h2 id="matrix-register-title">Matrix version register</h2><p>{{ number_format($matrices->total()) }} matching {{ str('version')->plural($matrices->total()) }} · horizontally scroll the controlled fields on smaller screens.</p></div>
            <div class="mx-actions"><a class="mx-btn mx-btn-small mx-btn-secondary" href="{{ route('budget.me.matrices.pdf', $exportQuery) }}">Export filtered PDF</a>@if($canManage)<a class="mx-btn mx-btn-small mx-btn-primary" href="#matrix-upload" data-matrix-open="matrix-upload">Upload version</a>@endif</div>
        </div>
        @if($matrices->isNotEmpty())
            <div class="mx-table-wrap">
                <table class="mx-table">
                    <thead><tr><th>Controlled matrix</th><th>Portfolio</th><th>Version / status</th><th>Workbook inspection</th><th>Effective period</th><th>Repository control</th><th>Uploaded</th><th class="mx-actions-column">Actions</th></tr></thead>
                    <tbody>
                    @foreach($matrices as $matrix)
                        @php
                            $inspection = $matrix->inspectionTotals();
                            $isSelected = $selectedMatrix && (string) $selectedMatrix->id === (string) $matrix->id;
                        @endphp
                        <tr class="{{ $isSelected ? 'selected' : '' }}" data-matrix-row data-href="{{ $matrixUrl($matrix) }}">
                            <td><div class="mx-document-cell"><span>{{ $matrix->formatLabel() }}</span><div><a href="{{ $matrixUrl($matrix) }}">{{ $matrix->title }}</a><small>{{ $matrix->matrix_code }} · {{ $matrix->repositoryItem?->original_filename ?: 'Repository filename unavailable' }}</small><p>{{ str($matrix->change_summary)->limit(120) }}</p></div></div></td>
                            <td><strong class="mx-cell-strong">{{ $matrix->portfolio?->name ?: 'Portfolio unavailable' }}</strong></td>
                            <td><strong>Version {{ $matrix->version_number }}</strong><span class="mx-status {{ $statusTone($matrix->status) }}">{{ $statuses[$matrix->status] ?? str($matrix->status)->headline() }}</span></td>
                            <td><div class="mx-inspection-cell"><span><strong>{{ number_format($inspection['sheet_count']) }}</strong> sheets</span><span><strong>{{ number_format($inspection['formula_cells']) }}</strong> formulas</span><span><strong>{{ number_format($inspection['validated_cells']) }}</strong> validations</span></div></td>
                            <td><strong class="mx-cell-strong">{{ $matrix->effective_from?->format('d M Y') ?: 'Not dated' }}</strong><small class="mx-cell-note">to {{ $matrix->effective_to?->format('d M Y') ?: 'open ended' }}</small></td>
                            <td><span class="mx-source {{ $matrix->matrix_file_available ? '' : 'danger' }}">{{ $matrix->matrix_file_available ? 'File available' : 'File missing' }}</span><small class="mx-cell-note">{{ $formatBytes($matrix->repositoryItem?->file_size) }}</small></td>
                            <td><strong class="mx-cell-strong">{{ $matrix->created_at?->format('d M Y') }}</strong><small class="mx-cell-note">{{ $matrix->createdBy?->name ?: 'System migration' }}</small></td>
                            <td><div class="mx-row-actions"><a href="{{ $matrixUrl($matrix) }}">Inspect</a>@if($matrix->matrix_file_available)<a href="{{ route('budget.me.matrices.download', $matrix) }}">Original</a>@endif<a href="{{ route('budget.me.matrices.pdf', ['matrix_id' => $matrix->id]) }}">PDF</a></div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mx-register-footer"><span>Showing {{ number_format($matrices->firstItem()) }}–{{ number_format($matrices->lastItem()) }} of {{ number_format($matrices->total()) }}</span><span>The PDF export includes all records in the active filter scope, not only this page.</span></div>
            @if($matrices->hasPages())<div class="mx-pagination">{{ $matrices->links() }}</div>@endif
        @else
            <div class="mx-empty"><span>MAT</span><strong>No matrix versions match this scope</strong><p>Clear one or more filters or upload the first controlled matrix version.</p>@if($canManage)<a class="mx-btn mx-btn-primary" href="#matrix-upload" data-matrix-open="matrix-upload">Upload matrix version</a>@endif</div>
        @endif
    </section>

    <section class="mx-panel mx-detail" id="matrix-detail" aria-labelledby="matrix-detail-title">
        @if($selectedMatrix)
            <div class="mx-detail-head">
                <div class="mx-detail-mark">{{ $selectedMatrix->formatLabel() }}</div>
                <div><span class="mx-eyebrow-dark">Selected controlled version</span><h2 id="matrix-detail-title">{{ $selectedMatrix->title }}</h2><p>{{ $selectedMatrix->matrix_code }} · version {{ $selectedMatrix->version_number }}</p></div>
                <span class="mx-status {{ $statusTone($selectedMatrix->status) }}">{{ $statuses[$selectedMatrix->status] ?? str($selectedMatrix->status)->headline() }}</span>
            </div>
            <div class="mx-detail-actions">
                @if($selectedMatrix->matrix_file_available)<a class="mx-btn mx-btn-primary" href="{{ route('budget.me.matrices.download', $selectedMatrix) }}">Download original</a>@endif
                <a class="mx-btn mx-btn-secondary" href="{{ route('budget.me.matrices.pdf', ['matrix_id' => $selectedMatrix->id]) }}">Control sheet PDF</a>
                @if($selectedMatrix->repository_item_id)<a class="mx-btn mx-btn-secondary" href="{{ route('budget.me.rebuild.knowledge-repository', ['document_id' => $selectedMatrix->repository_item_id]) }}">Repository record</a>@endif
            </div>
            @if(!$selectedMatrix->matrix_file_available)<div class="mx-inline-alert danger"><strong>Stored file unavailable</strong><p>The register record exists, but the storage object cannot be found. Preserve this audit record and investigate storage before relying on the version.</p></div>@endif

            <div class="mx-detail-grid">
                <section>
                    <h3>Control metadata</h3>
                    <dl class="mx-metadata">
                        <div><dt>Portfolio</dt><dd>{{ $selectedMatrix->portfolio?->name ?: 'Unavailable' }}</dd></div>
                        <div><dt>Matrix code</dt><dd>{{ $selectedMatrix->matrix_code }}</dd></div>
                        <div><dt>Version</dt><dd>{{ $selectedMatrix->version_number }}</dd></div>
                        <div><dt>File format</dt><dd>{{ $selectedMatrix->formatLabel() }}</dd></div>
                        <div><dt>Effective from</dt><dd>{{ $selectedMatrix->effective_from?->format('d M Y') ?: 'Not dated' }}</dd></div>
                        <div><dt>Effective to</dt><dd>{{ $selectedMatrix->effective_to?->format('d M Y') ?: 'Open ended' }}</dd></div>
                        <div><dt>Uploaded by</dt><dd>{{ $selectedMatrix->createdBy?->name ?: 'System migration' }}</dd></div>
                        <div><dt>Approved by</dt><dd>{{ $selectedMatrix->approvedBy?->name ?: 'Not approved' }}</dd></div>
                        <div><dt>Approved at</dt><dd>{{ $selectedMatrix->approved_at?->format('d M Y, H:i') ?: 'Not approved' }}</dd></div>
                        <div><dt>File size</dt><dd>{{ $formatBytes($selectedMatrix->repositoryItem?->file_size) }}</dd></div>
                    </dl>
                    <div class="mx-change-summary"><strong>Version change summary</strong><p>{{ $selectedMatrix->change_summary ?: 'No change summary was recorded.' }}</p></div>
                </section>
                <section>
                    <div class="mx-section-head"><div><h3>Workbook inspection</h3><p>Structural checks captured when this version was uploaded.</p></div><span class="mx-badge">{{ $selectedInspection['sheet_count'] }} sheets</span></div>
                    <div class="mx-inspection-metrics"><span><strong>{{ number_format($selectedInspection['data_rows']) }}</strong> rows</span><span><strong>{{ number_format($selectedInspection['data_columns']) }}</strong> sheet columns</span><span><strong>{{ number_format($selectedInspection['formula_cells']) }}</strong> formulas</span><span><strong>{{ number_format($selectedInspection['validated_cells']) }}</strong> validations</span></div>
                    <div class="mx-sheet-list">
                        @forelse(data_get($selectedMatrix->import_summary, 'sheets', []) as $sheet)
                            <article><span>{{ str(data_get($sheet, 'name', 'Sheet'))->substr(0, 2)->upper() }}</span><div><strong>{{ data_get($sheet, 'name', 'Worksheet') }}</strong><p>{{ number_format((int) data_get($sheet, 'data_rows', 0)) }} rows · {{ number_format((int) data_get($sheet, 'data_columns', 0)) }} columns</p></div><small>{{ number_format((int) data_get($sheet, 'formula_cells', 0)) }} formulas<br>{{ number_format((int) data_get($sheet, 'validated_cells', 0)) }} validations</small>@if(data_get($sheet, 'inspection_limited'))<em>Large sheet: deep cell inspection limited</em>@endif</article>
                        @empty
                            <div class="mx-empty mx-empty-compact"><span>{{ $selectedMatrix->formatLabel() }}</span><strong>Spreadsheet structure is not available</strong><p>{{ data_get($selectedMatrix->import_summary, 'message', 'No sheet-level inspection was stored for this version.') }}</p></div>
                        @endforelse
                    </div>
                </section>
            </div>

            @if($canManage)
                <div class="mx-lifecycle-actions">
                    <div><strong>Lifecycle control</strong><p>Only drafts may be activated or deleted. Active versions may be retired, and all retired versions remain in the audit trail.</p></div>
                    <div class="mx-actions">
                        @if($selectedMatrix->status === 'draft')
                            <form method="POST" action="{{ route('budget.me.matrices.activate', $selectedMatrix) }}" data-confirm="Activate this version and retire the currently active version with the same matrix code?">@csrf<button class="mx-btn mx-btn-success" type="submit">Activate version</button></form>
                            <form method="POST" action="{{ route('budget.me.matrices.destroy', $selectedMatrix) }}" data-confirm="Permanently delete this draft matrix version and its repository file?">@csrf @method('DELETE')<button class="mx-btn mx-btn-danger" type="submit">Delete draft</button></form>
                        @elseif($selectedMatrix->status === 'active')
                            <form method="POST" action="{{ route('budget.me.matrices.retire', $selectedMatrix) }}" data-confirm="Retire this active version while retaining it for audit history?">@csrf<button class="mx-btn mx-btn-secondary" type="submit">Retire version</button></form>
                        @else
                            <span class="mx-audit-lock">Audit locked · upload a new version to supersede it</span>
                        @endif
                    </div>
                </div>
            @endif
        @else
            <div class="mx-empty"><span>SEL</span><strong>Select a matrix version</strong><p>Choose a register row to inspect workbook structure, control metadata, approvals and lifecycle actions.</p></div>
        @endif
    </section>

    @if($canManage)
        <section class="mx-modal {{ $oldContext === 'matrix_upload' ? 'is-open' : '' }}" id="matrix-upload" role="dialog" aria-modal="true" aria-labelledby="matrix-upload-title" data-matrix-modal @if($oldContext === 'matrix_upload') data-auto-open @endif>
            <a class="mx-modal-backdrop" href="#" data-matrix-close aria-label="Close upload dialog"></a>
            <div class="mx-modal-card">
                <header><div><span>Controlled version intake</span><h2 id="matrix-upload-title">Upload M&amp;E Matrix version</h2><p>The workbook is inspected, stored in the Knowledge Repository and registered as a draft. Uploading never overwrites indicator results.</p></div><a class="mx-modal-close" href="#" data-matrix-close aria-label="Close">×</a></header>
                <form method="POST" action="{{ route('budget.me.matrices.store') }}" enctype="multipart/form-data" data-matrix-upload>
                    @csrf
                    <input type="hidden" name="form_context" value="matrix_upload">
                    <div class="mx-modal-body">
                        <div class="mx-form-grid">
                            <div class="mx-field"><label for="upload-portfolio">Portfolio *</label><select class="form-select" id="upload-portfolio" name="portfolio_id" required><option value="">Select portfolio</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected((string) old('portfolio_id') === (string) $portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                            <div class="mx-field mx-field-wide"><label for="upload-title">Document title *</label><input class="form-control" id="upload-title" name="title" value="{{ old('title', 'ATTP Unified Indicator Performance Tracking Matrix') }}" maxlength="255" required></div>
                            <div class="mx-field"><label for="upload-code">Matrix code *</label><input class="form-control" id="upload-code" name="matrix_code" value="{{ old('matrix_code', 'ATTP-MEL-MATRIX') }}" maxlength="80" pattern="[A-Za-z0-9][A-Za-z0-9._-]*" required data-matrix-code><small>Letters, numbers, dots, underscores and hyphens only.</small></div>
                            <div class="mx-field"><label for="upload-version">Version number</label><input class="form-control" id="upload-version" type="number" name="version_number" value="{{ old('version_number') }}" min="1" max="9999" placeholder="Automatic"><small>Leave blank to assign the next controlled version.</small></div>
                            <div class="mx-field"><label for="upload-effective-from">Effective from</label><input class="form-control" id="upload-effective-from" type="date" name="effective_from" value="{{ old('effective_from') }}" data-effective-from></div>
                            <div class="mx-field"><label for="upload-effective-to">Effective to</label><input class="form-control" id="upload-effective-to" type="date" name="effective_to" value="{{ old('effective_to') }}" data-effective-to></div>
                            <div class="mx-field mx-field-wide"><label for="upload-file">Matrix file *</label><label class="mx-file-drop" for="upload-file"><span>↑</span><strong data-file-name>Choose matrix file</strong><small>XLSX, XLS, CSV or PDF · maximum 30 MB</small></label><input class="mx-visually-hidden" id="upload-file" type="file" name="matrix_file" accept=".xlsx,.xls,.csv,.pdf" required data-file-input><p class="mx-file-feedback" data-file-feedback></p></div>
                            <div class="mx-field mx-field-full"><label for="upload-summary">Change summary *</label><textarea class="form-control" id="upload-summary" name="change_summary" rows="4" maxlength="5000" required placeholder="State what was added, corrected or approved in this version.">{{ old('change_summary') }}</textarea><small>This becomes part of the permanent version and repository history.</small></div>
                        </div>
                    </div>
                    <footer><a class="mx-btn mx-btn-secondary" href="#" data-matrix-close>Cancel</a><button class="mx-btn mx-btn-primary" type="submit">Inspect and upload draft</button></footer>
                </form>
            </div>
        </section>
    @endif
</div>
@endsection

@push('scripts')
<script src="{{ asset('admin/assets/vendors/js/apexcharts.min.js') }}"></script>
<script>
(function () {
    const ready = function () {
        const body = document.body;
        const openModal = function (id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            document.querySelectorAll('[data-matrix-modal].is-open').forEach(item => item.classList.remove('is-open'));
            modal.classList.add('is-open');
            body.classList.add('mx-modal-open');
            window.setTimeout(() => modal.querySelector('input:not([type="hidden"]),select,textarea,button')?.focus(), 20);
        };
        const closeModal = function (modal) {
            modal?.classList.remove('is-open');
            if (!document.querySelector('[data-matrix-modal].is-open')) body.classList.remove('mx-modal-open');
            if (window.location.hash === '#matrix-upload') history.replaceState(null, '', window.location.pathname + window.location.search);
        };
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-matrix-open]');
            if (opener) { event.preventDefault(); openModal(opener.dataset.matrixOpen); return; }
            const closer = event.target.closest('[data-matrix-close]');
            if (closer) { event.preventDefault(); closeModal(closer.closest('[data-matrix-modal]')); return; }
            const row = event.target.closest('[data-matrix-row]');
            if (row && !event.target.closest('a,button,input,select,textarea,form')) window.location.href = row.dataset.href;
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(document.querySelector('[data-matrix-modal].is-open')); });
        document.querySelectorAll('[data-matrix-modal][data-auto-open]').forEach(modal => openModal(modal.id));
        if (window.location.hash === '#matrix-upload') openModal('matrix-upload');
        document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', function (event) {
            if (!window.confirm(this.dataset.confirm)) event.preventDefault();
        }));

        const fileInput = document.querySelector('[data-file-input]');
        const fileName = document.querySelector('[data-file-name]');
        const fileFeedback = document.querySelector('[data-file-feedback]');
        fileInput?.addEventListener('change', function () {
            const file = this.files?.[0];
            if (fileName) fileName.textContent = file?.name || 'Choose matrix file';
            if (!fileFeedback) return;
            if (!file) { fileFeedback.textContent = ''; return; }
            const extension = file.name.split('.').pop()?.toLowerCase();
            const allowed = ['xlsx', 'xls', 'csv', 'pdf'];
            if (!allowed.includes(extension)) {
                fileFeedback.textContent = 'Unsupported file type. Choose XLSX, XLS, CSV or PDF.';
                fileFeedback.className = 'mx-file-feedback danger';
                this.setCustomValidity('Unsupported matrix file type.');
            } else if (file.size > 30 * 1024 * 1024) {
                fileFeedback.textContent = 'This file exceeds the 30 MB upload limit.';
                fileFeedback.className = 'mx-file-feedback danger';
                this.setCustomValidity('The matrix file exceeds 30 MB.');
            } else {
                fileFeedback.textContent = (file.size / 1024 / 1024).toFixed(2) + ' MB · ready for structural inspection';
                fileFeedback.className = 'mx-file-feedback success';
                this.setCustomValidity('');
            }
        });
        const codeInput = document.querySelector('[data-matrix-code]');
        codeInput?.addEventListener('input', function () { this.value = this.value.toUpperCase().replace(/[^A-Z0-9._-]/g, ''); });
        const effectiveFrom = document.querySelector('[data-effective-from]');
        const effectiveTo = document.querySelector('[data-effective-to]');
        const alignDates = function () {
            if (!effectiveTo) return;
            effectiveTo.min = effectiveFrom?.value || '';
            if (effectiveFrom?.value && effectiveTo.value && effectiveTo.value < effectiveFrom.value) effectiveTo.setCustomValidity('Effective to must be on or after effective from.');
            else effectiveTo.setCustomValidity('');
        };
        effectiveFrom?.addEventListener('change', alignDates);
        effectiveTo?.addEventListener('change', alignDates);
        alignDates();

        const status = @json($charts['status']);
        const activity = @json($charts['activity']);
        const portfolios = @json($charts['portfolios']);
        const baseUrl = @json(route('budget.me.matrices.index'));
        const baseFilters = @json($preservedFilters);
        const openFiltered = function (extra) {
            const parameters = new URLSearchParams({...baseFilters, ...extra});
            window.location.href = baseUrl + '?' + parameters.toString();
        };
        const render = function (selector, options) {
            const target = document.querySelector(selector);
            if (!target) return;
            if (typeof window.ApexCharts !== 'function') {
                target.innerHTML = '<div class="mx-chart-unavailable"><strong>Chart unavailable</strong><span>The register and PDF remain available.</span></div>';
                return;
            }
            new window.ApexCharts(target, options).render();
        };
        const base = {chart:{fontFamily:'Inter, Arial, sans-serif',foreColor:'#657980',toolbar:{show:false},animations:{speed:380}},grid:{borderColor:'#e4ecee',strokeDashArray:3},tooltip:{theme:'light'},dataLabels:{style:{fontSize:'10px',fontWeight:700}},noData:{text:'No matrix data in this scope'}};
        render('#matrix-status-chart', {...base, chart:{...base.chart,type:'donut',height:285,events:{dataPointSelection:(_event,_chart,selection)=>{const item=status[selection.dataPointIndex];if(item)openFiltered({status:item.key});}}}, series:status.map(item=>item.count), labels:status.map(item=>item.label), colors:status.map(item=>item.color), stroke:{colors:['#fff'],width:3}, dataLabels:{enabled:false}, legend:{position:'bottom',fontSize:'11px'}, plotOptions:{pie:{donut:{size:'67%',labels:{show:true,total:{show:true,label:'Versions',formatter:()=>status.reduce((sum,item)=>sum+item.count,0)}}}}}});
        render('#matrix-activity-chart', {...base, chart:{...base.chart,type:'area',height:285}, series:[{name:'New versions',data:activity.map(item=>item.count)}], colors:['#075c7a'], stroke:{curve:'smooth',width:3}, fill:{type:'gradient',gradient:{shadeIntensity:1,opacityFrom:.36,opacityTo:.04,stops:[0,95,100]}}, xaxis:{categories:activity.map(item=>item.label),labels:{rotate:-35,style:{fontSize:'10px'}}}, yaxis:{min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}}, markers:{size:4,strokeWidth:2,strokeColors:'#fff'}, dataLabels:{enabled:false}});
        render('#matrix-portfolio-chart', {...base, chart:{...base.chart,type:'bar',height:Math.max(285,portfolios.length*44),events:{dataPointSelection:(_event,_chart,selection)=>{const item=portfolios[selection.dataPointIndex];if(item && item.key!=='unassigned')openFiltered({portfolio_id:item.key});}}}, series:[{name:'Versions',data:portfolios.map(item=>item.count)}], colors:['#3f8aa0'], plotOptions:{bar:{horizontal:true,borderRadius:4,barHeight:'58%'}}, xaxis:{categories:portfolios.map(item=>item.label),min:0,forceNiceScale:true,labels:{formatter:value=>Math.round(value)}}, yaxis:{labels:{maxWidth:190,style:{fontSize:'11px'}}}, dataLabels:{enabled:true,formatter:value=>Math.round(value)}, legend:{show:false}});
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, {once:true}); else ready();
})();
</script>
@endpush
