@extends('layouts.app')

@section('title', 'Knowledge and Evidence Repository')
@section('lean_admin_scripts', '1')

@push('styles')
    @include('me.knowledge-evidence.partials.styles')
@endpush

@section('content')
@php
    $activeFilterCount = collect($filters)
        ->except(['document_id','sort','per_page'])
        ->filter(fn ($value) => filled($value))
        ->count();
    $preservedFilters = collect($filters)
        ->except('document_id')
        ->reject(fn ($value) => $value === null || $value === '')
        ->reject(fn ($value, $key) => ($key === 'sort' && $value === 'newest') || ($key === 'per_page' && (int)$value === 25))
        ->all();
    $documentUrl = fn ($item) => route('budget.me.rebuild.knowledge-repository', array_merge($preservedFilters, ['document_id' => $item->id])).'#document-detail';
    $folderUrl = fn ($folder) => route('budget.me.rebuild.knowledge-repository', array_filter([
        'portfolio_id' => $folder->portfolio_id,
        'folder_id' => $folder->id,
        'document_type' => $filters['document_type'],
        'validation_status' => $filters['validation_status'],
        'source' => $filters['source'],
        'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
        'per_page' => $filters['per_page'] !== 25 ? $filters['per_page'] : null,
    ], fn ($value) => filled($value)));
    $allDocumentsUrl = route('budget.me.rebuild.knowledge-repository', array_filter([
        'portfolio_id' => $filters['portfolio_id'],
        'document_type' => $filters['document_type'],
        'validation_status' => $filters['validation_status'],
        'source' => $filters['source'],
        'sort' => $filters['sort'] !== 'newest' ? $filters['sort'] : null,
        'per_page' => $filters['per_page'] !== 25 ? $filters['per_page'] : null,
    ], fn ($value) => filled($value)));
    $navigationFolders = $folders->when(
        $filters['portfolio_id'],
        fn ($collection) => $collection->where('portfolio_id', $filters['portfolio_id'])
    );
    $activeFolder = $filters['folder_id'] ? $folders->firstWhere('id', $filters['folder_id']) : null;
    $linkedCount = $selectedItem
        ? (int)$selectedItem->indicators_count + (int)$selectedItem->links_count + (int)$selectedItem->report_documents_count + (int)$selectedItem->matrix_versions_count
        : 0;
    $statusTone = fn ($status) => match ($status) {
        'validated' => 'success',
        'rejected' => 'danger',
        default => 'warning',
    };
    $typeMark = fn ($type) => match ($type) {
        'means_of_verification' => 'MOV',
        'meal_plan' => 'MEAL',
        'theory_of_change' => 'TOC',
        'evaluation' => 'EVA',
        'research' => 'RES',
        'supporting_evidence' => 'SUP',
        'me_matrix' => 'MAT',
        default => 'DOC',
    };
    $formatBytes = function ($bytes) {
        $bytes = (int)$bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 1).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 1).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 1).' KB';
        return number_format($bytes).' B';
    };
    $oldContext = old('form_context');
@endphp

<div class="mel-repository">
    <header class="kr-header">
        <div>
            <span class="kr-eyebrow">Monitoring, Evaluation and Learning</span>
            <h1>Knowledge and evidence repository</h1>
            <p>A controlled workspace for indicator evidence, MEAL plans, evaluations, research products and institutional knowledge—with validation decisions and every file version retained.</p>
        </div>
        <div class="kr-header-side">
            <span class="kr-generated">Updated {{ $generatedAt->format('d M Y, H:i') }}</span>
            <div class="kr-actions">
                <a class="kr-btn kr-btn-header" href="{{ route('budget.me.indicators.index') }}">Indicator register</a>
                @if($canManage)
                    <a class="kr-btn kr-btn-header" href="#repo-new-folder" data-repo-open="repo-new-folder">Create folder</a>
                    <a class="kr-btn kr-btn-header kr-btn-solid" href="#repo-upload" data-repo-open="repo-upload">Add document</a>
                @endif
            </div>
        </div>
    </header>

    @if(session('success'))
        <div class="kr-alert success" role="status"><span>✓</span><div><strong>Repository updated</strong><p>{{ session('success') }}</p></div></div>
    @endif
    @if($errors->any())
        <div class="kr-alert danger" role="alert"><span>!</span><div><strong>The requested change could not be completed</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>
    @endif

    <aside class="kr-governance" aria-label="Repository controls">
        <span class="kr-governance-mark">CTRL</span>
        <div><strong>Controlled evidence, not a shared file dump</strong><p>Folders inherit portfolio and indicator context. Replacing a file creates a new version and returns it to pending validation; linked evidence cannot be deleted from the audit record.</p></div>
    </aside>

    <section class="kr-metrics" aria-label="Repository summary">
        <a class="kr-metric" style="--metric:#075c7a" href="{{ $allDocumentsUrl }}"><span>Documents</span><strong>{{ number_format($metrics['documents']) }}</strong><small>Active records in the selected scope</small></a>
        <a class="kr-metric" style="--metric:#a56a17" href="{{ route('budget.me.rebuild.knowledge-repository', array_merge($preservedFilters,['validation_status'=>'pending'])) }}"><span>Awaiting validation</span><strong>{{ number_format($metrics['pending']) }}</strong><small>Reviewer decision still required</small></a>
        <a class="kr-metric" style="--metric:#187459" href="{{ route('budget.me.rebuild.knowledge-repository', array_merge($preservedFilters,['validation_status'=>'validated'])) }}"><span>Validation coverage</span><strong>{{ number_format($metrics['validation_rate'],1) }}%</strong><small>{{ number_format($metrics['validated']) }} validated documents</small></a>
        <a class="kr-metric" style="--metric:#ae3f3d" href="{{ route('budget.me.rebuild.knowledge-repository', array_merge($preservedFilters,['validation_status'=>'rejected'])) }}"><span>Returned evidence</span><strong>{{ number_format($metrics['rejected']) }}</strong><small>Rejected records needing correction</small></a>
        <a class="kr-metric" style="--metric:#6b63a8" href="#document-register"><span>Controlled versions</span><strong>{{ number_format($metrics['versions']) }}</strong><small>Current plus retained file versions</small></a>
        <a class="kr-metric" style="--metric:#1676b8" href="#document-register"><span>Managed storage</span><strong>{{ $formatBytes($metrics['storage_bytes']) }}</strong><small>{{ number_format($metrics['file_documents']) }} files · {{ number_format($metrics['external_documents']) }} links</small></a>
    </section>

    <details class="kr-panel kr-filter" @if($activeFilterCount > 0) open @endif>
        <summary class="kr-panel-head">
            <div><h2>Search and repository scope</h2><p>Filter the document register without changing folder definitions or version history.</p></div>
            <div class="kr-summary-right"><span class="kr-badge">{{ $activeFilterCount }} active {{ str('filter')->plural($activeFilterCount) }}</span><span class="kr-chevron">⌄</span></div>
        </summary>
        <div class="kr-panel-body">
            <form class="kr-filter-grid" method="GET" action="{{ route('budget.me.rebuild.knowledge-repository') }}" data-repo-filter>
                <div class="kr-field kr-field-wide"><label for="repository-search">Search documents</label><input class="form-control" id="repository-search" type="search" name="q" value="{{ $filters['q'] }}" placeholder="Title, description, filename, folder, portfolio or source URL"><small>Search is case-insensitive and applies to summary metrics and the register.</small></div>
                <div class="kr-field"><label for="repository-portfolio">Portfolio</label><select class="form-select" id="repository-portfolio" name="portfolio_id" data-repo-portfolio><option value="">All authorized portfolios</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected((string)$filters['portfolio_id']===(string)$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                <div class="kr-field"><label for="repository-folder">Folder</label><select class="form-select" id="repository-folder" name="folder_id" data-repo-folder-filter><option value="">All folders</option>@foreach($folders as $folder)<option value="{{ $folder->id }}" data-portfolio-id="{{ $folder->portfolio_id }}" @selected((string)$filters['folder_id']===(string)$folder->id)>{{ $folder->name }}</option>@endforeach</select></div>
                <div class="kr-field"><label for="repository-type">Document type</label><select class="form-select" id="repository-type" name="document_type"><option value="">All document types</option>@foreach($documentTypes as $value=>$label)<option value="{{ $value }}" @selected($filters['document_type']===$value)>{{ $label }}</option>@endforeach</select></div>
                <div class="kr-field"><label for="repository-status">Validation status</label><select class="form-select" id="repository-status" name="validation_status"><option value="">All decisions</option><option value="pending" @selected($filters['validation_status']==='pending')>Pending validation</option><option value="validated" @selected($filters['validation_status']==='validated')>Validated</option><option value="rejected" @selected($filters['validation_status']==='rejected')>Rejected / returned</option></select></div>
                <div class="kr-field"><label for="repository-source">Source</label><select class="form-select" id="repository-source" name="source"><option value="">Files and links</option><option value="file" @selected($filters['source']==='file')>Has stored file</option><option value="external" @selected($filters['source']==='external')>Has external URL</option></select></div>
                <div class="kr-field"><label for="repository-sort">Sort records</label><select class="form-select" id="repository-sort" name="sort"><option value="newest" @selected($filters['sort']==='newest')>Recently updated</option><option value="oldest" @selected($filters['sort']==='oldest')>Oldest added</option><option value="title" @selected($filters['sort']==='title')>Document title</option><option value="version" @selected($filters['sort']==='version')>Highest version</option><option value="validation" @selected($filters['sort']==='validation')>Validation priority</option></select></div>
                <div class="kr-field"><label for="repository-page-size">Rows per page</label><select class="form-select" id="repository-page-size" name="per_page">@foreach([15,25,50,100] as $size)<option value="{{ $size }}" @selected((int)$filters['per_page']===$size)>{{ $size }} rows</option>@endforeach</select></div>
                <div class="kr-filter-actions"><p><strong>Tip:</strong> select a folder from the left workspace to preserve its indicator context while filtering its documents.</p><div class="kr-actions"><a class="kr-btn kr-btn-secondary" href="{{ route('budget.me.rebuild.knowledge-repository') }}">Clear filters</a><button class="kr-btn kr-btn-primary" type="submit">Apply scope</button></div></div>
            </form>
        </div>
    </details>

    <div class="kr-workspace">
        <aside class="kr-panel kr-folder-panel" aria-labelledby="folder-navigation-title">
            <div class="kr-panel-head"><div><h2 id="folder-navigation-title">Repository folders</h2><p>Portfolio and indicator filing structure.</p></div>@if($canManage)<a href="#repo-new-folder" data-repo-open="repo-new-folder" class="kr-icon-btn" title="Create folder">+</a>@endif</div>
            <div class="kr-folder-scroll">
                <a class="kr-folder-row {{ $filters['folder_id'] ? '' : 'active' }}" href="{{ $allDocumentsUrl }}"><span class="kr-folder-mark">ALL</span><span><strong>All documents</strong><small>Across visible repository folders</small></span><em>{{ number_format($metrics['documents']) }}</em></a>
                @forelse($navigationFolders->groupBy(fn ($folder) => $folder->portfolio?->name ?: 'Portfolio unavailable') as $portfolioName=>$portfolioFolders)
                    <div class="kr-folder-group"><span>{{ $portfolioName }}</span></div>
                    @foreach($portfolioFolders as $folder)
                        <a class="kr-folder-row {{ (string)$filters['folder_id']===(string)$folder->id ? 'active' : '' }}" href="{{ $folderUrl($folder) }}">
                            <span class="kr-folder-mark">DIR</span><span><strong>{{ $folder->name }}</strong><small>{{ $folder->indicators->count() }} linked {{ str('indicator')->plural($folder->indicators->count()) }}</small></span><em>{{ number_format($folder->documents_count) }}</em>
                            @if($folder->pending_documents_count > 0)<i title="Pending validation">{{ $folder->pending_documents_count }}</i>@endif
                        </a>
                    @endforeach
                @empty
                    <div class="kr-empty kr-empty-compact"><span>DIR</span><strong>No folders configured</strong><p>Create the first indicator-linked repository folder.</p></div>
                @endforelse
            </div>
            @if($activeFolder)
                <div class="kr-folder-context">
                    <span>Selected folder</span><strong>{{ $activeFolder->name }}</strong><p>{{ $activeFolder->description ?: 'No folder description has been recorded.' }}</p>
                    <div class="kr-chip-list">@foreach($activeFolder->indicators as $indicator)<span>{{ $indicator->indicator_code }}</span>@endforeach</div>
                    @if($canManage)<a class="kr-btn kr-btn-small kr-btn-secondary" href="#repo-manage-folder" data-repo-open="repo-manage-folder">Manage folder</a>@endif
                </div>
            @endif
        </aside>

        <section class="kr-panel kr-register" id="document-register" aria-labelledby="document-register-title">
            <div class="kr-panel-head"><div><h2 id="document-register-title">Document register</h2><p>{{ number_format($items->total()) }} matching {{ str('record')->plural($items->total()) }} · select a row for controlled-document details.</p></div>@if($canManage)<a class="kr-btn kr-btn-small kr-btn-primary" href="#repo-upload" data-repo-open="repo-upload">Add document</a>@endif</div>
            @if($items->isNotEmpty())
                <div class="kr-table-wrap">
                    <table class="kr-table">
                        <thead><tr><th>Document</th><th>Validation</th><th>Folder</th><th>Type</th><th>Version</th><th>Source</th><th>Updated</th><th></th></tr></thead>
                        <tbody>
                        @foreach($items as $item)
                            @php
                                $isSelected = $selectedItem && (string)$selectedItem->id === (string)$item->id;
                                $itemLinkedCount = (int)$item->indicators_count + (int)$item->links_count + (int)$item->report_documents_count + (int)$item->matrix_versions_count;
                            @endphp
                            <tr class="{{ $isSelected ? 'selected' : '' }}" data-repo-row data-href="{{ $documentUrl($item) }}">
                                <td><div class="kr-document-cell"><span>{{ $typeMark($item->document_type) }}</span><div><a href="{{ $documentUrl($item) }}">{{ $item->title }}</a><small>{{ $item->original_filename ?: ($item->external_url ? parse_url($item->external_url, PHP_URL_HOST) : 'Source unavailable') }} · {{ $itemLinkedCount }} linked {{ str('record')->plural($itemLinkedCount) }}</small></div></div></td>
                                <td><span class="kr-status {{ $statusTone($item->validation_status) }}">{{ str($item->validation_status ?: 'pending')->headline() }}</span></td>
                                <td><strong class="kr-cell-strong">{{ $item->folder?->name ?: 'Unfiled' }}</strong><small class="kr-cell-note">{{ $item->portfolio?->name }}</small></td>
                                <td>{{ $item->typeLabel() }}</td>
                                <td><strong>v{{ $item->version_number ?: 1 }}</strong><small class="kr-cell-note">{{ $item->versions_count }} retained</small></td>
                                <td>@if($item->file_path)<span class="kr-source {{ $item->repository_file_available ? '' : 'danger' }}">{{ $item->repository_file_available ? 'Stored file' : 'File missing' }}</span>@endif @if($item->external_url)<span class="kr-source">External URL</span>@endif</td>
                                <td>{{ $item->updated_at?->format('d M Y') }}<small class="kr-cell-note">{{ $item->updated_at?->format('H:i') }}</small></td>
                                <td><a class="kr-open-row" href="{{ $documentUrl($item) }}" aria-label="Open {{ $item->title }}">→</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="kr-register-footer"><span>Showing {{ number_format($items->firstItem()) }}–{{ number_format($items->lastItem()) }} of {{ number_format($items->total()) }}</span><span>Horizontal scrolling is available on smaller screens.</span></div>
                @if($items->hasPages())<div class="kr-pagination">{{ $items->links() }}</div>@endif
            @else
                <div class="kr-empty"><span>DOC</span><strong>No documents match this scope</strong><p>Clear one or more filters, choose another folder, or add a new controlled document.</p>@if($canManage)<a class="kr-btn kr-btn-primary" href="#repo-upload" data-repo-open="repo-upload">Add first document</a>@endif</div>
            @endif
        </section>

        <aside class="kr-panel kr-detail" id="document-detail" aria-labelledby="document-detail-title">
            @if($selectedItem)
                <div class="kr-detail-head">
                    <div class="kr-detail-mark">{{ $typeMark($selectedItem->document_type) }}</div>
                    <div><span class="kr-eyebrow-dark">Controlled document</span><h2 id="document-detail-title">{{ $selectedItem->title }}</h2><div class="kr-detail-badges"><span class="kr-status {{ $statusTone($selectedItem->validation_status) }}">{{ str($selectedItem->validation_status ?: 'pending')->headline() }}</span><span class="kr-badge">Version {{ $selectedItem->version_number ?: 1 }}</span></div></div>
                </div>
                <div class="kr-detail-actions">
                    @if($selectedItem->file_path && $selectedItem->repository_file_available)
                        @if($selectedItem->isPreviewable())<a class="kr-btn kr-btn-primary" href="{{ route('budget.me.knowledge-evidence.preview',$selectedItem) }}" target="_blank">Preview</a>@endif
                        <a class="kr-btn kr-btn-secondary" href="{{ route('budget.me.knowledge-evidence.download',$selectedItem) }}">Download</a>
                    @endif
                    @if($selectedItem->external_url)<a class="kr-btn kr-btn-secondary" href="{{ $selectedItem->external_url }}" target="_blank" rel="noopener noreferrer">Open source</a>@endif
                    @if($canManage)<a class="kr-btn kr-btn-secondary" href="#repo-manage-document" data-repo-open="repo-manage-document">Manage</a>@endif
                </div>
                @if($selectedItem->file_path && !$selectedItem->repository_file_available)
                    <div class="kr-inline-alert danger"><strong>Stored file unavailable</strong><p>The database record exists, but its current storage object is missing. Upload a replacement version before using this evidence.</p></div>
                @endif
                <dl class="kr-metadata">
                    <div><dt>Folder</dt><dd>{{ $selectedItem->folder?->name ?: 'Unfiled' }}</dd></div>
                    <div><dt>Portfolio</dt><dd>{{ $selectedItem->portfolio?->name ?: 'Not assigned' }}</dd></div>
                    <div><dt>Document type</dt><dd>{{ $selectedItem->typeLabel() }}</dd></div>
                    <div><dt>Current source</dt><dd>{{ $selectedItem->original_filename ?: ($selectedItem->external_url ? 'External URL' : 'Unavailable') }}</dd></div>
                    <div><dt>File size</dt><dd>{{ $selectedItem->formattedSize() }}</dd></div>
                    <div><dt>Added by</dt><dd>{{ $selectedItem->creator?->name ?: 'System migration' }} · {{ $selectedItem->created_at?->format('d M Y') }}</dd></div>
                </dl>
                @if($selectedItem->description)<div class="kr-description"><strong>Description</strong><p>{{ $selectedItem->description }}</p></div>@endif

                <section class="kr-detail-section"><div class="kr-detail-section-head"><h3>Linked indicators</h3><span>{{ $selectedItem->folder?->indicators?->count() ?? 0 }}</span></div><p class="kr-section-tip">Documents inherit these indicator links from their folder.</p><div class="kr-chip-list">@forelse($selectedItem->folder?->indicators ?? collect() as $indicator)<span title="{{ $indicator->name }}">{{ $indicator->indicator_code }} · {{ str($indicator->name)->limit(42) }}</span>@empty<em>No folder indicator links are configured.</em>@endforelse</div></section>

                <section class="kr-detail-section"><div class="kr-detail-section-head"><h3>Record linkage</h3><span>{{ $linkedCount }}</span></div><div class="kr-link-grid"><span><strong>{{ $selectedItem->report_documents_count }}</strong>Reports</span><span><strong>{{ $selectedItem->links_count }}</strong>Achievements</span><span><strong>{{ $selectedItem->indicators_count }}</strong>Legacy MOV</span><span><strong>{{ $selectedItem->matrix_versions_count }}</strong>Matrices</span></div></section>

                <section class="kr-detail-section"><div class="kr-detail-section-head"><h3>Validation decision</h3><span class="kr-status {{ $statusTone($selectedItem->validation_status) }}">{{ str($selectedItem->validation_status ?: 'pending')->headline() }}</span></div>
                    @if($selectedItem->validation_notes)<div class="kr-decision"><strong>{{ $selectedItem->validatedBy?->name ?: 'Reviewer' }} · {{ $selectedItem->validated_at?->format('d M Y, H:i') }}</strong><p>{{ $selectedItem->validation_notes }}</p></div>@else<p class="kr-section-tip">No validation decision note has been recorded for the current version.</p>@endif
                    @if($canValidate)
                        <details class="kr-native-details" @if($oldContext==='validation') open @endif><summary>Record a validation decision</summary><form method="POST" action="{{ route('budget.me.knowledge-evidence.validate',$selectedItem) }}">@csrf<input type="hidden" name="form_context" value="validation"><div class="kr-field"><label for="validation-status">Decision</label><select class="form-select" id="validation-status" name="validation_status" required><option value="validated">Validate evidence</option><option value="rejected">Reject and return</option></select></div><div class="kr-field"><label for="validation-notes">Decision note *</label><textarea class="form-control" id="validation-notes" name="validation_notes" rows="3" maxlength="5000" required placeholder="Explain the evidence review and the reason for this decision">{{ old('validation_notes') }}</textarea></div><button class="kr-btn kr-btn-primary" type="submit">Record decision</button></form></details>
                    @endif
                </section>

                <details class="kr-detail-section kr-native-details" @if($oldContext==='document_replace') open @endif><summary>Complete version history <span>{{ $selectedItem->versions->count() }}</span></summary><div class="kr-timeline">@forelse($selectedItem->versions as $version)<article><span>v{{ $version->version_number }}</span><div><strong>{{ $version->original_filename }}</strong><p>{{ $version->formattedSize() }} · {{ $version->created_at?->format('d M Y, H:i') }} · {{ $version->uploadedBy?->name ?: 'System migration' }}</p>@if($version->change_notes)<small>{{ $version->change_notes }}</small>@endif</div><a href="{{ route('budget.me.knowledge-evidence.versions.download',[$selectedItem,$version]) }}">Download</a></article>@empty<div class="kr-empty kr-empty-compact"><span>VER</span><strong>No stored file versions</strong><p>This external-link record has no uploaded files.</p></div>@endforelse</div></details>
            @else
                <div class="kr-empty"><span>SEL</span><strong>Select a document</strong><p>Choose a register row to inspect metadata, indicator links, validation decisions and complete version history.</p></div>
            @endif
        </aside>
    </div>

    @if($canManage)
        <section class="kr-modal {{ $oldContext==='folder_create' ? 'is-open' : '' }}" id="repo-new-folder" role="dialog" aria-modal="true" aria-labelledby="new-folder-title" data-repo-modal @if($oldContext==='folder_create') data-auto-open @endif>
            <a class="kr-modal-backdrop" href="#" data-repo-close aria-label="Close create folder dialog"></a><div class="kr-modal-card"><header><div><span>Repository structure</span><h2 id="new-folder-title">Create indicator folder</h2><p>Create one clear filing location and connect it to the indicators whose evidence it will hold.</p></div><a href="#" data-repo-close class="kr-modal-close" aria-label="Close">×</a></header><form method="POST" action="{{ route('budget.me.knowledge-evidence.folders.store') }}" data-indicator-form>@csrf<input type="hidden" name="form_context" value="folder_create"><div class="kr-modal-body"><div class="kr-form-grid"><div class="kr-field"><label for="new-folder-portfolio">Portfolio *</label><select class="form-select" id="new-folder-portfolio" name="portfolio_id" required data-indicator-portfolio><option value="">Select portfolio</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected((string)old('portfolio_id')===(string)$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div><div class="kr-field"><label for="new-folder-name">Folder name *</label><input class="form-control" id="new-folder-name" name="name" value="{{ old('name') }}" maxlength="180" required placeholder="e.g. PDO 1 — Policy uptake evidence"></div><div class="kr-field kr-field-full"><label for="new-folder-description">Folder description</label><textarea class="form-control" id="new-folder-description" name="description" rows="3" maxlength="5000" placeholder="Explain what belongs in this folder and how it supports the linked indicators.">{{ old('description') }}</textarea></div></div><div class="kr-indicator-picker"><div class="kr-picker-head"><div><strong>Linked indicators *</strong><p>Select at least one indicator from the chosen portfolio.</p></div><div><button type="button" data-select-visible>Select visible</button><button type="button" data-clear-visible>Clear</button><span data-selected-count>0 selected</span></div></div><div class="kr-indicator-search"><input class="form-control" type="search" placeholder="Search indicator code or name" data-indicator-search></div><div class="kr-indicator-options">@foreach($indicators as $indicator)<label data-indicator-option data-portfolio-id="{{ $indicator->projectComponent?->program?->sector_id }}" data-search="{{ str($indicator->indicator_code.' '.$indicator->name)->lower() }}"><input type="checkbox" name="indicator_ids[]" value="{{ $indicator->id }}" @checked(collect(old('indicator_ids',[]))->contains($indicator->id))><span><strong>{{ $indicator->indicator_code }}</strong><small>{{ $indicator->name }}</small></span></label>@endforeach<div class="kr-picker-empty" data-indicator-empty>Select a portfolio to display its indicators.</div></div></div></div><footer><a class="kr-btn kr-btn-secondary" href="#" data-repo-close>Cancel</a><button class="kr-btn kr-btn-primary" type="submit">Create folder</button></footer></form></div>
        </section>

        <section class="kr-modal {{ $oldContext==='document_upload' ? 'is-open' : '' }}" id="repo-upload" role="dialog" aria-modal="true" aria-labelledby="upload-title" data-repo-modal @if($oldContext==='document_upload') data-auto-open @endif>
            <a class="kr-modal-backdrop" href="#" data-repo-close aria-label="Close upload dialog"></a><div class="kr-modal-card"><header><div><span>Controlled document intake</span><h2 id="upload-title">Add document to repository</h2><p>The selected folder supplies the portfolio and indicator context. Provide a stored file, an HTTP/HTTPS source URL, or both.</p></div><a href="#" data-repo-close class="kr-modal-close" aria-label="Close">×</a></header><form method="POST" action="{{ route('budget.me.knowledge-evidence.store') }}" enctype="multipart/form-data" data-upload-form>@csrf<input type="hidden" name="form_context" value="document_upload"><div class="kr-modal-body"><div class="kr-form-grid"><div class="kr-field kr-field-wide"><label for="upload-folder">Repository folder *</label><select class="form-select" id="upload-folder" name="folder_id" required data-upload-folder><option value="">Select an indicator-linked folder</option>@foreach($folders->groupBy(fn($folder)=>$folder->portfolio?->name ?: 'Portfolio unavailable') as $portfolioName=>$portfolioFolders)<optgroup label="{{ $portfolioName }}">@foreach($portfolioFolders as $folder)<option value="{{ $folder->id }}" data-portfolio="{{ $portfolioName }}" data-indicators="{{ $folder->indicators->pluck('indicator_code')->join(', ') }}" @selected((string)old('folder_id',$activeFolder?->id)===(string)$folder->id)>{{ $folder->name }}</option>@endforeach</optgroup>@endforeach</select><small data-upload-folder-context>Select a folder to see its inherited portfolio and indicator context.</small></div><div class="kr-field"><label for="upload-type">Document type *</label><select class="form-select" id="upload-type" name="document_type" required>@foreach($documentTypes as $value=>$label)<option value="{{ $value }}" @selected(old('document_type','means_of_verification')===$value)>{{ $label }}</option>@endforeach</select></div><div class="kr-field kr-field-full"><label for="upload-title-field">Document title *</label><input class="form-control" id="upload-title-field" name="title" value="{{ old('title') }}" maxlength="255" required placeholder="Use a precise, searchable document title"></div><div class="kr-field"><label for="upload-file">Upload file</label><label class="kr-file-drop" for="upload-file"><span>↑</span><strong data-file-name>Choose a file</strong><small>PDF, Office, CSV, text or image · maximum 20 MB</small></label><input class="kr-visually-hidden" id="upload-file" type="file" name="evidence_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png" data-file-input></div><div class="kr-source-divider"><span>and / or</span></div><div class="kr-field"><label for="upload-url">External source URL</label><input class="form-control" id="upload-url" type="url" name="external_url" value="{{ old('external_url') }}" maxlength="2000" placeholder="https://example.org/document"><small>An uploaded file or HTTP/HTTPS URL is required.</small></div><div class="kr-field kr-field-full"><label for="upload-description">Description</label><textarea class="form-control" id="upload-description" name="description" rows="3" maxlength="5000" placeholder="Describe the evidence, reporting context and intended use.">{{ old('description') }}</textarea></div></div></div><footer><a class="kr-btn kr-btn-secondary" href="#" data-repo-close>Cancel</a><button class="kr-btn kr-btn-primary" type="submit">Add controlled document</button></footer></form></div>
        </section>

        @if($activeFolder)
            <section class="kr-modal {{ $oldContext==='folder_update' ? 'is-open' : '' }}" id="repo-manage-folder" role="dialog" aria-modal="true" aria-labelledby="manage-folder-title" data-repo-modal @if($oldContext==='folder_update') data-auto-open @endif>
                <a class="kr-modal-backdrop" href="#" data-repo-close></a><div class="kr-modal-card"><header><div><span>Repository structure</span><h2 id="manage-folder-title">Manage folder</h2><p>{{ $activeFolder->portfolio?->name }} · {{ $activeFolder->documents_count }} documents</p></div><a href="#" data-repo-close class="kr-modal-close">×</a></header><form method="POST" action="{{ route('budget.me.knowledge-evidence.folders.update',$activeFolder) }}" data-indicator-form>@csrf @method('PUT')<input type="hidden" name="form_context" value="folder_update"><input type="hidden" data-indicator-portfolio value="{{ $activeFolder->portfolio_id }}"><div class="kr-modal-body"><div class="kr-form-grid"><div class="kr-field kr-field-full"><label for="manage-folder-name">Folder name *</label><input class="form-control" id="manage-folder-name" name="name" value="{{ old('name',$activeFolder->name) }}" maxlength="180" required></div><div class="kr-field kr-field-full"><label for="manage-folder-description">Description</label><textarea class="form-control" id="manage-folder-description" name="description" rows="3" maxlength="5000">{{ old('description',$activeFolder->description) }}</textarea></div></div><div class="kr-indicator-picker"><div class="kr-picker-head"><div><strong>Linked indicators *</strong><p>Documents in this folder inherit this set.</p></div><div><button type="button" data-select-visible>Select visible</button><button type="button" data-clear-visible>Clear</button><span data-selected-count>0 selected</span></div></div><div class="kr-indicator-search"><input class="form-control" type="search" placeholder="Search indicator code or name" data-indicator-search></div><div class="kr-indicator-options">@foreach($indicators as $indicator)<label data-indicator-option data-portfolio-id="{{ $indicator->projectComponent?->program?->sector_id }}" data-search="{{ str($indicator->indicator_code.' '.$indicator->name)->lower() }}"><input type="checkbox" name="indicator_ids[]" value="{{ $indicator->id }}" @checked(collect(old('indicator_ids',$activeFolder->indicators->pluck('id')->all()))->contains($indicator->id))><span><strong>{{ $indicator->indicator_code }}</strong><small>{{ $indicator->name }}</small></span></label>@endforeach<div class="kr-picker-empty" data-indicator-empty>No indicators are available for this portfolio.</div></div></div><div class="kr-danger-zone"><div><strong>Delete empty folder</strong><p>Indicator shortcuts are detached automatically. Documents must be moved or deleted first.</p></div><button class="kr-btn kr-btn-danger" type="submit" form="delete-active-folder" @disabled($activeFolder->documents_count > 0)>Delete folder</button></div></div><footer><a class="kr-btn kr-btn-secondary" href="#" data-repo-close>Cancel</a><button class="kr-btn kr-btn-primary" type="submit">Save folder</button></footer></form><form id="delete-active-folder" method="POST" action="{{ route('budget.me.knowledge-evidence.folders.destroy',$activeFolder) }}" data-confirm="Delete this empty folder and detach its indicator shortcuts?">@csrf @method('DELETE')</form></div>
            </section>
        @endif

        @if($selectedItem)
            <section class="kr-modal {{ in_array($oldContext,['document_update','document_replace'],true) ? 'is-open' : '' }}" id="repo-manage-document" role="dialog" aria-modal="true" aria-labelledby="manage-document-title" data-repo-modal @if(in_array($oldContext,['document_update','document_replace'],true)) data-auto-open @endif>
                <a class="kr-modal-backdrop" href="#" data-repo-close></a><div class="kr-modal-card kr-modal-card-wide"><header><div><span>Controlled document administration</span><h2 id="manage-document-title">Manage document</h2><p>{{ $selectedItem->title }} · current version {{ $selectedItem->version_number ?: 1 }}</p></div><a href="#" data-repo-close class="kr-modal-close">×</a></header><div class="kr-modal-body"><div class="kr-manage-grid"><section><h3>Document metadata</h3><p>Update classification or move the record within the same portfolio.</p><form method="POST" action="{{ route('budget.me.knowledge-evidence.update',$selectedItem) }}" class="kr-form-stack">@csrf @method('PUT')<input type="hidden" name="form_context" value="document_update"><div class="kr-field"><label for="manage-document-title-field">Document title *</label><input class="form-control" id="manage-document-title-field" name="title" value="{{ old('title',$selectedItem->title) }}" maxlength="255" required></div><div class="kr-field"><label for="manage-document-folder">Folder *</label><select class="form-select" id="manage-document-folder" name="folder_id" required>@foreach($folders->where('portfolio_id',$selectedItem->portfolio_id) as $targetFolder)<option value="{{ $targetFolder->id }}" @selected((string)old('folder_id',$selectedItem->folder_id)===(string)$targetFolder->id)>{{ $targetFolder->name }}</option>@endforeach</select></div><div class="kr-field"><label for="manage-document-type">Document type *</label><select class="form-select" id="manage-document-type" name="document_type" required>@foreach($documentTypes as $value=>$label)<option value="{{ $value }}" @selected(old('document_type',$selectedItem->document_type)===$value)>{{ $label }}</option>@endforeach</select></div><div class="kr-field"><label for="manage-document-url">External URL</label><input class="form-control" id="manage-document-url" type="url" name="external_url" value="{{ old('external_url',$selectedItem->external_url) }}" maxlength="2000" placeholder="https://"></div><div class="kr-field"><label for="manage-document-description">Description</label><textarea class="form-control" id="manage-document-description" name="description" rows="4" maxlength="5000">{{ old('description',$selectedItem->description) }}</textarea></div><button class="kr-btn kr-btn-primary" type="submit">Save details</button></form></section><section><h3>Upload next version</h3><p>The current and earlier files remain in complete version history. The new version returns to pending validation.</p><form method="POST" action="{{ route('budget.me.knowledge-evidence.replace-file',$selectedItem) }}" enctype="multipart/form-data" class="kr-form-stack">@csrf<input type="hidden" name="form_context" value="document_replace"><div class="kr-field"><label for="replacement-file">Replacement file *</label><label class="kr-file-drop" for="replacement-file"><span>↑</span><strong data-file-name>Choose replacement file</strong><small>Maximum 20 MB · must differ from the current and folder files</small></label><input class="kr-visually-hidden" id="replacement-file" type="file" name="replacement_file" required data-file-input></div><div class="kr-field"><label for="change-notes">What changed? *</label><textarea class="form-control" id="change-notes" name="change_notes" rows="4" maxlength="5000" required placeholder="Describe the corrections or updates in this version.">{{ old('change_notes') }}</textarea></div><button class="kr-btn kr-btn-primary" type="submit">Create version {{ ((int)$selectedItem->version_number)+1 }}</button></form><div class="kr-danger-zone"><div><strong>Delete repository document</strong><p>{{ $linkedCount > 0 ? 'Deletion is locked because this evidence supports other records.' : 'This unlinked record and all stored versions will be permanently removed.' }}</p></div><button class="kr-btn kr-btn-danger" type="submit" form="delete-selected-document" @disabled($linkedCount > 0)>Delete</button></div></section></div></div><footer><a class="kr-btn kr-btn-secondary" href="#" data-repo-close>Close</a></footer><form id="delete-selected-document" method="POST" action="{{ route('budget.me.knowledge-evidence.destroy',$selectedItem) }}" data-confirm="Delete this unlinked repository document and every stored file version?">@csrf @method('DELETE')</form></div>
            </section>
        @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const ready = function () {
        const body = document.body;
        const openModal = function (id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            document.querySelectorAll('[data-repo-modal].is-open').forEach(item => item.classList.remove('is-open'));
            modal.classList.add('is-open');
            body.classList.add('kr-modal-open');
            const focusTarget = modal.querySelector('input:not([type="hidden"]),select,textarea,button');
            window.setTimeout(() => focusTarget?.focus(), 20);
        };
        const closeModal = function (modal) {
            modal?.classList.remove('is-open');
            if (!document.querySelector('[data-repo-modal].is-open')) body.classList.remove('kr-modal-open');
            if (window.location.hash?.startsWith('#repo-')) history.replaceState(null, '', window.location.pathname + window.location.search);
        };
        document.addEventListener('click', function (event) {
            const opener = event.target.closest('[data-repo-open]');
            if (opener) { event.preventDefault(); openModal(opener.dataset.repoOpen); return; }
            const closer = event.target.closest('[data-repo-close]');
            if (closer) { event.preventDefault(); closeModal(closer.closest('[data-repo-modal]')); return; }
            const row = event.target.closest('[data-repo-row]');
            if (row && !event.target.closest('a,button,input,select,textarea')) window.location.href = row.dataset.href;
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape') closeModal(document.querySelector('[data-repo-modal].is-open')); });
        document.querySelectorAll('[data-repo-modal][data-auto-open]').forEach(modal => openModal(modal.id));
        if (window.location.hash?.startsWith('#repo-')) openModal(window.location.hash.slice(1));

        document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', function (event) {
            if (!window.confirm(this.dataset.confirm)) event.preventDefault();
        }));
        document.querySelectorAll('[data-file-input]').forEach(input => input.addEventListener('change', function () {
            const target = this.closest('.kr-field')?.querySelector('[data-file-name]');
            if (target) target.textContent = this.files?.[0]?.name || 'Choose a file';
        }));

        const portfolio = document.querySelector('[data-repo-portfolio]');
        const folderFilter = document.querySelector('[data-repo-folder-filter]');
        if (folderFilter) {
            const folders = Array.from(folderFilter.options).slice(1).map(option => ({value:option.value,text:option.textContent,portfolio:option.dataset.portfolioId}));
            const rebuildFolders = function () {
                const current = folderFilter.value;
                folderFilter.innerHTML = '<option value="">All folders</option>';
                folders.filter(folder => !portfolio?.value || folder.portfolio === portfolio.value).forEach(folder => {
                    const option = new Option(folder.text, folder.value, false, folder.value === current);
                    option.dataset.portfolioId = folder.portfolio;
                    folderFilter.add(option);
                });
                if (![...folderFilter.options].some(option => option.value === current)) folderFilter.value = '';
            };
            portfolio?.addEventListener('change', rebuildFolders);
            rebuildFolders();
        }

        document.querySelectorAll('[data-indicator-form]').forEach(form => {
            const portfolioInput = form.querySelector('[data-indicator-portfolio]');
            const search = form.querySelector('[data-indicator-search]');
            const options = Array.from(form.querySelectorAll('[data-indicator-option]'));
            const empty = form.querySelector('[data-indicator-empty]');
            const count = form.querySelector('[data-selected-count]');
            const refresh = function () {
                const portfolioId = portfolioInput?.value || '';
                const term = search?.value.trim().toLowerCase() || '';
                let visible = 0;
                options.forEach(option => {
                    const show = portfolioId !== '' && option.dataset.portfolioId === portfolioId && (!term || option.dataset.search.includes(term));
                    option.hidden = !show;
                    if (show) visible++;
                    if (!portfolioId || option.dataset.portfolioId !== portfolioId) option.querySelector('input').checked = false;
                });
                if (empty) { empty.hidden = visible > 0; empty.textContent = portfolioId ? 'No matching indicators in this portfolio.' : 'Select a portfolio to display its indicators.'; }
                if (count) count.textContent = options.filter(option => option.querySelector('input').checked).length + ' selected';
            };
            portfolioInput?.addEventListener('change', refresh);
            search?.addEventListener('input', refresh);
            options.forEach(option => option.querySelector('input').addEventListener('change', refresh));
            form.querySelector('[data-select-visible]')?.addEventListener('click', () => { options.filter(option => !option.hidden).forEach(option => option.querySelector('input').checked = true); refresh(); });
            form.querySelector('[data-clear-visible]')?.addEventListener('click', () => { options.filter(option => !option.hidden).forEach(option => option.querySelector('input').checked = false); refresh(); });
            refresh();
        });

        const uploadFolder = document.querySelector('[data-upload-folder]');
        const uploadContext = document.querySelector('[data-upload-folder-context]');
        const refreshUploadContext = function () {
            const option = uploadFolder?.selectedOptions?.[0];
            if (!uploadContext) return;
            uploadContext.textContent = option?.value ? (option.dataset.portfolio + ' · linked indicators: ' + (option.dataset.indicators || 'none')) : 'Select a folder to see its inherited portfolio and indicator context.';
        };
        uploadFolder?.addEventListener('change', refreshUploadContext);
        refreshUploadContext();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', ready, {once:true}); else ready();
})();
</script>
@endpush
