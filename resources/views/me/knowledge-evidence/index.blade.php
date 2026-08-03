@extends('layouts.app')

@section('title', 'Knowledge and Evidence Repository')

@push('styles')
<style>
    .repository-shell{--repo-green:#0b5c45;--repo-soft:#edf8f3;--repo-line:#dce8e2}.repository-hero{background:linear-gradient(135deg,#073f32,#0b6b51);border-radius:20px;color:#fff;padding:24px}.repository-hero p{color:#d8eee6}.repository-folder{border:1px solid var(--repo-line);border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 8px 28px rgba(18,65,50,.07)}.repository-folder__head{padding:18px 20px;background:linear-gradient(180deg,#fff,#f7fbf9);border-bottom:1px solid var(--repo-line)}.repository-folder__icon{width:44px;height:44px;border-radius:13px;display:grid;place-items:center;background:#fff2cc;color:#9b6a00;font-size:21px}.repository-chip{display:inline-flex;align-items:center;border:1px solid #cfe2da;background:var(--repo-soft);color:var(--repo-green);border-radius:999px;padding:3px 9px;font-size:11px;font-weight:700}.repository-document{padding:16px 20px;border-bottom:1px solid #edf2ef}.repository-document:last-child{border-bottom:0}.repository-document__title{font-weight:800;color:#173d31}.repository-meta{font-size:12px;color:#6b7d75}.repository-actions{display:flex;flex-wrap:wrap;justify-content:flex-end;gap:6px}.repository-manage{background:#f8faf9;border:1px solid var(--repo-line);border-radius:12px;padding:14px;margin-top:12px}.repository-empty{padding:34px;text-align:center;color:#718078}.repository-form-card{border:0;border-radius:16px;box-shadow:0 8px 24px rgba(26,61,49,.07)}.repository-form-card .card-header{background:#fff;border-bottom:1px solid var(--repo-line);padding:16px 18px}.repository-history{margin:8px 0 0;padding-left:18px;font-size:12px}.repository-history li{margin:4px 0}@media(max-width:767px){.repository-actions{justify-content:flex-start}.repository-hero{padding:20px}}
</style>
@endpush

@section('content')
<div class="container-fluid repository-shell">
    <header class="repository-hero mb-4">
        <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-start gap-3">
            <div>
                <div class="small fw-bold text-uppercase opacity-75 mb-1">Monitoring &amp; Evaluation</div>
                <h2 class="mb-2">Knowledge and Evidence Repository</h2>
                <p class="mb-0">Organize evidence in named folders linked to indicators. Every replacement remains available in controlled document version history.</p>
            </div>
            <a href="{{ route('budget.me.indicators.index') }}" class="btn btn-light"><i class="feather-target me-1"></i>Indicator register</a>
        </div>
    </header>

    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please correct the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    @can('me.configuration.manage')
        <div class="row g-3 mb-4">
            <div class="col-xl-6">
                <div class="card repository-form-card h-100">
                    <div class="card-header"><h5 class="mb-1"><i class="feather-folder-plus me-2 text-warning"></i>Create indicator folder</h5><div class="text-muted small">A folder must be linked to one or more indicators before documents are added.</div></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('budget.me.knowledge-evidence.folders.store') }}" class="row g-3" data-folder-form>
                            @csrf
                            <div class="col-md-5"><label class="form-label">Portfolio *</label><select name="portfolio_id" class="form-select" required data-folder-portfolio><option value="">Select portfolio</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected(old('portfolio_id')===$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
                            <div class="col-md-7"><label class="form-label">Folder name *</label><input name="name" value="{{ old('name') }}" maxlength="180" class="form-control" placeholder="e.g. PDO 1 - Policy uptake evidence" required></div>
                            <div class="col-12"><label class="form-label">Linked indicator(s) *</label><select name="indicator_ids[]" class="form-select" size="5" multiple required data-folder-indicators>@foreach($indicators as $indicator)<option value="{{ $indicator->id }}" data-portfolio-id="{{ $indicator->projectComponent?->program?->sector_id }}">{{ $indicator->indicator_code }} - {{ $indicator->name }}</option>@endforeach</select><div class="form-text">Use Ctrl/Command to select multiple indicators. Only indicators from the selected portfolio are shown.</div></div>
                            <div class="col-12"><label class="form-label">Folder description</label><textarea name="description" rows="2" maxlength="5000" class="form-control">{{ old('description') }}</textarea></div>
                            <div class="col-12 text-end"><button class="btn btn-warning"><i class="feather-folder-plus me-1"></i>Create folder</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-xl-6">
                <div class="card repository-form-card h-100">
                    <div class="card-header"><h5 class="mb-1"><i class="feather-upload-cloud me-2 text-primary"></i>Add document to a folder</h5><div class="text-muted small">The document automatically inherits the folder's portfolio and indicator links.</div></div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('budget.me.knowledge-evidence.store') }}" enctype="multipart/form-data" class="row g-3">
                            @csrf
                            <div class="col-md-7"><label class="form-label">Repository folder *</label><select name="folder_id" class="form-select" required><option value="">Select folder</option>@foreach($folders->groupBy('portfolio.name') as $portfolioName=>$portfolioFolders)<optgroup label="{{ $portfolioName }}">@foreach($portfolioFolders as $folder)<option value="{{ $folder->id }}" @selected(old('folder_id')===$folder->id)>{{ $folder->name }}</option>@endforeach</optgroup>@endforeach</select></div>
                            <div class="col-md-5"><label class="form-label">Document type *</label><select name="document_type" class="form-select" required>@foreach($documentTypes as $value=>$label)<option value="{{ $value }}" @selected(old('document_type','means_of_verification')===$value)>{{ $label }}</option>@endforeach</select></div>
                            <div class="col-12"><label class="form-label">Document title *</label><input name="title" value="{{ old('title') }}" maxlength="255" class="form-control" placeholder="Use a clear, searchable title" required></div>
                            <div class="col-md-6"><label class="form-label">Upload file</label><input type="file" name="evidence_file" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png"><div class="form-text">Maximum 20 MB. A file or URL is required.</div></div>
                            <div class="col-md-6"><label class="form-label">External URL</label><input type="url" name="external_url" value="{{ old('external_url') }}" maxlength="2000" class="form-control" placeholder="https://"></div>
                            <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="2" maxlength="5000" class="form-control">{{ old('description') }}</textarea></div>
                            <div class="col-12 text-end"><button class="btn btn-primary"><i class="feather-upload me-1"></i>Add document</button></div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    @endcan

    <div class="card repository-form-card mb-4"><div class="card-body">
        <form method="GET" action="{{ route('budget.me.rebuild.knowledge-repository') }}" class="row g-2 align-items-end">
            <div class="col-lg-4"><label class="form-label">Search folders and documents</label><input type="search" name="q" value="{{ $search }}" class="form-control" placeholder="Folder, title, description, file or URL"></div>
            <div class="col-lg-3"><label class="form-label">Portfolio</label><select name="portfolio_id" class="form-select"><option value="">All portfolios</option>@foreach($portfolios as $portfolio)<option value="{{ $portfolio->id }}" @selected($selectedPortfolioId===$portfolio->id)>{{ $portfolio->name }}</option>@endforeach</select></div>
            <div class="col-lg-3"><label class="form-label">Folder</label><select name="folder_id" class="form-select"><option value="">All folders</option>@foreach($folders as $folder)<option value="{{ $folder->id }}" @selected($selectedFolderId===$folder->id)>{{ $folder->name }}</option>@endforeach</select></div>
            <div class="col-lg-2 d-flex gap-2"><button class="btn btn-outline-primary flex-grow-1">Filter</button><a href="{{ route('budget.me.rebuild.knowledge-repository') }}" class="btn btn-light border">Clear</a></div>
        </form>
    </div></div>

    @php
        $visibleFolders = $folders->filter(fn ($folder) =>
            ($selectedFolderId === '' || $selectedFolderId === (string) $folder->id)
            && ($search === '' || $items->getCollection()->where('folder_id', $folder->id)->isNotEmpty())
        );
    @endphp
    <div class="d-grid gap-3">
        @forelse($visibleFolders as $folder)
            @php($folderItems = $items->getCollection()->where('folder_id', $folder->id))
            <section class="repository-folder">
                <div class="repository-folder__head">
                    <div class="d-flex flex-column flex-lg-row justify-content-between gap-3">
                        <div class="d-flex gap-3">
                            <span class="repository-folder__icon"><i class="feather-folder"></i></span>
                            <div><h5 class="mb-1">{{ $folder->name }}</h5><div class="repository-meta">{{ $folder->portfolio?->name }} · {{ number_format($folder->documents_count) }} document(s)</div>@if($folder->description)<p class="small text-muted mb-2 mt-2">{{ $folder->description }}</p>@endif<div class="d-flex flex-wrap gap-1">@foreach($folder->indicators as $indicator)<span class="repository-chip">{{ $indicator->indicator_code }} · {{ $indicator->name }}</span>@endforeach</div></div>
                        </div>
                        @can('me.configuration.manage')
                            <details><summary class="btn btn-sm btn-light border">Manage folder</summary><div class="repository-manage" style="max-width:620px">
                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.folders.update',$folder) }}" class="row g-2">@csrf @method('PUT')
                                    <div class="col-md-5"><label class="form-label small">Folder name</label><input name="name" value="{{ $folder->name }}" maxlength="180" class="form-control" required></div>
                                    <div class="col-md-7"><label class="form-label small">Linked indicators</label><select name="indicator_ids[]" class="form-select" size="3" multiple required>@foreach($indicators->filter(fn($indicator)=>(string)$indicator->projectComponent?->program?->sector_id===(string)$folder->portfolio_id) as $indicator)<option value="{{ $indicator->id }}" @selected($folder->indicators->contains('id',$indicator->id))>{{ $indicator->indicator_code }} - {{ $indicator->name }}</option>@endforeach</select></div>
                                    <div class="col-12"><label class="form-label small">Description</label><textarea name="description" rows="2" maxlength="5000" class="form-control">{{ $folder->description }}</textarea></div>
                                    <div class="col-12 text-end"><button class="btn btn-sm btn-primary">Save folder</button></div>
                                </form>
                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.folders.destroy',$folder) }}" class="text-end mt-2" onsubmit="return confirm('Delete this unlinked empty folder?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" @disabled($folder->documents_count > 0 || $folder->indicators->isNotEmpty())>Delete unlinked empty folder</button></form>
                            </div></details>
                        @endcan
                    </div>
                </div>

                @forelse($folderItems as $item)
                    @php($linkedCount=(int)$item->indicators_count+(int)$item->links_count+(int)$item->report_documents_count+(int)$item->matrix_versions_count)
                    <article class="repository-document">
                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                            <div class="flex-grow-1"><div class="d-flex flex-wrap align-items-center gap-2"><span class="repository-document__title">{{ $item->title }}</span><span class="badge bg-primary-subtle text-primary">{{ $item->typeLabel() }}</span><span class="badge {{ $item->validation_status==='validated'?'bg-success':($item->validation_status==='rejected'?'bg-danger':'bg-warning text-dark') }}">{{ str($item->validation_status?:'pending')->headline() }}</span></div>@if($item->description)<div class="small text-muted mt-1">{{ str($item->description)->limit(180) }}</div>@endif<div class="repository-meta mt-1">Version {{ $item->version_number?:1 }} · {{ $linkedCount }} linked record(s) · added {{ $item->created_at?->format('d M Y') }}@if($item->creator) by {{ $item->creator->name }}@endif</div>@if($item->validation_notes)<div class="small mt-2"><strong>Decision note:</strong> {{ $item->validation_notes }}</div>@endif
                                @if($item->versions->isNotEmpty())<details class="mt-2"><summary class="small text-primary fw-semibold">Complete version history ({{ $item->versions->count() }})</summary><ol class="repository-history">@foreach($item->versions->sortByDesc('version_number') as $version)<li><a href="{{ route('budget.me.knowledge-evidence.versions.download',[$item,$version]) }}">v{{ $version->version_number }} · {{ $version->original_filename }}</a> · {{ $version->created_at?->format('d M Y H:i') }}@if($version->change_notes) · {{ $version->change_notes }}@endif</li>@endforeach</ol></details>@endif
                            </div>
                            <div class="repository-actions">@if($item->file_path)<a href="{{ route('budget.me.knowledge-evidence.download',$item) }}" class="btn btn-sm btn-light border"><i class="feather-download me-1"></i>Download</a>@endif @if($item->external_url)<a href="{{ $item->external_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-light border"><i class="feather-external-link me-1"></i>Open</a>@endif @can('me.configuration.manage')<details><summary class="btn btn-sm btn-outline-primary">Manage</summary><div class="repository-manage" style="width:min(680px,85vw)">
                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.update',$item) }}" class="row g-2 mb-3">@csrf @method('PUT')<div class="col-md-7"><label class="form-label small">Document title</label><input name="title" value="{{ $item->title }}" class="form-control" required></div><div class="col-md-5"><label class="form-label small">Folder</label><select name="folder_id" class="form-select" required>@foreach($folders->where('portfolio_id',$item->portfolio_id) as $targetFolder)<option value="{{ $targetFolder->id }}" @selected($item->folder_id===$targetFolder->id)>{{ $targetFolder->name }}</option>@endforeach</select></div><div class="col-md-5"><label class="form-label small">Type</label><select name="document_type" class="form-select">@foreach($documentTypes as $value=>$label)<option value="{{ $value }}" @selected($item->document_type===$value)>{{ $label }}</option>@endforeach</select></div><div class="col-md-7"><label class="form-label small">External URL</label><input type="url" name="external_url" value="{{ $item->external_url }}" class="form-control"></div><div class="col-12"><textarea name="description" rows="2" class="form-control" placeholder="Description">{{ $item->description }}</textarea></div><div class="col-12 text-end"><button class="btn btn-sm btn-primary">Save details</button></div></form>
                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.replace-file',$item) }}" enctype="multipart/form-data" class="row g-2 mb-3">@csrf<div class="col-md-6"><label class="form-label small">Upload next version</label><input type="file" name="replacement_file" class="form-control" required></div><div class="col-md-6"><label class="form-label small">What changed? *</label><input name="change_notes" class="form-control" required maxlength="5000"></div><div class="col-12 text-end"><button class="btn btn-sm btn-outline-primary">Create version {{ ((int)$item->version_number)+1 }}</button></div></form>
                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.validate',$item) }}" class="row g-2 mb-3">@csrf<div class="col-md-4"><label class="form-label small">Decision</label><select name="validation_status" class="form-select"><option value="validated">Approve / validate</option><option value="rejected">Reject / return</option></select></div><div class="col-md-8"><label class="form-label small">Decision note *</label><input name="validation_notes" class="form-control" required maxlength="5000" placeholder="Explain why this document is approved or returned"></div><div class="col-12 text-end"><button class="btn btn-sm btn-outline-success">Record decision</button></div></form>
                                <form method="POST" action="{{ route('budget.me.knowledge-evidence.destroy',$item) }}" onsubmit="return confirm('Delete this unlinked repository document and its files?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" @disabled($linkedCount>0)>Delete unlinked document</button></form>
                            </div></details>@endcan</div>
                        </div>
                    </article>
                @empty
                    <div class="repository-empty"><i class="feather-file-plus fs-3 d-block mb-2"></i>No matching documents in this folder.</div>
                @endforelse
            </section>
        @empty
            <div class="repository-empty repository-folder"><i class="feather-folder fs-2 d-block mb-2"></i>No repository folders match this view. Create a folder and link it to at least one indicator.</div>
        @endforelse
    </div>

    @if($items->hasPages())<div class="mt-3">{{ $items->links() }}</div>@endif
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded',()=>{
    const portfolio=document.querySelector('[data-folder-portfolio]');
    const indicators=document.querySelector('[data-folder-indicators]');
    const filterIndicators=()=>{const selected=portfolio?.value||'';indicators?.querySelectorAll('option').forEach(option=>{option.hidden=selected!==''&&option.dataset.portfolioId!==selected;if(option.hidden)option.selected=false;});};
    portfolio?.addEventListener('change',filterIndicators);filterIndicators();
});
</script>
@endpush
