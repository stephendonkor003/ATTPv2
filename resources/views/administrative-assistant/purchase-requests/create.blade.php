@extends('layouts.administrative-assistant')

@section('title', 'Create Purchase Request')
@section('workspace-kicker', 'Purchase request intake')
@section('workspace-heading', 'Submit a simple request for back-office completion')

@push('styles')
<style>
    .pr-intake-lead { max-width: 760px; }
    .pr-section-title { color: var(--aa-navy); font-weight: 800; }
    .pr-required { color: #b42318; }
    .pr-item-row { padding: 16px; border: 1px solid var(--aa-border); border-radius: 14px; background: #f8fafc; }
    .pr-item-row + .pr-item-row { margin-top: 12px; }
    .pr-document-box { border: 2px dashed #b8c8d9; border-radius: 16px; padding: 20px; background: #f8fbfd; }
    .pr-document-box.has-files { border-color: var(--aa-teal); background: var(--aa-mint); }
    .pr-selected-file { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-top: 1px solid rgba(16, 35, 63, .1); }
    .pr-selected-file:first-child { margin-top: 12px; }
    .pr-file-icon { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; color: var(--aa-teal); background: #fff; border: 1px solid #c9ddd9; border-radius: 11px; overflow: hidden; }
    .pr-file-icon img { width: 100%; height: 100%; object-fit: cover; }
    .pr-file-name { min-width: 0; flex: 1; }
    .pr-file-actions { display: flex; gap: 6px; }
    .pr-file-actions button { border: 1px solid #b8c8d9; background: #fff; border-radius: 9px; padding: 6px 9px; color: #24405b; }
    .pr-file-actions button:hover { border-color: var(--aa-teal); color: var(--aa-teal); }
    .pr-file-actions .remove-file { color: #b42318; }
    .pr-recent-row { display: block; padding: 14px 0; color: inherit; text-decoration: none; border-bottom: 1px solid var(--aa-border); }
    .pr-recent-row:last-child { border-bottom: 0; }
    .pr-recent-row:hover .pr-reference { color: var(--aa-teal); }
    .pr-reference { color: var(--aa-navy); font-weight: 800; }
    .pr-preview-modal[hidden] { display: none; }
    .pr-preview-modal { position: fixed; inset: 0; z-index: 2000; display: grid; place-items: center; padding: 18px; }
    .pr-preview-backdrop { position: absolute; inset: 0; background: rgba(10, 24, 43, .72); }
    .pr-preview-dialog { position: relative; width: min(960px, 100%); max-height: calc(100vh - 36px); display: flex; flex-direction: column; overflow: hidden; background: #fff; border-radius: 18px; box-shadow: 0 30px 80px rgba(0, 0, 0, .35); }
    .pr-preview-header, .pr-preview-footer { padding: 14px 18px; border-bottom: 1px solid var(--aa-border); }
    .pr-preview-header { display: flex; align-items: center; justify-content: space-between; gap: 14px; }
    .pr-preview-footer { border-top: 1px solid var(--aa-border); border-bottom: 0; color: #667085; font-size: .8rem; }
    .pr-preview-body { min-height: 280px; height: min(68vh, 720px); display: grid; place-items: center; overflow: auto; background: #edf2f7; }
    .pr-preview-image { max-width: 100%; max-height: 100%; object-fit: contain; }
    .pr-preview-frame { width: 100%; height: 100%; border: 0; background: #fff; }
    .pr-preview-text { align-self: stretch; justify-self: stretch; min-height: 100%; margin: 0; padding: 22px; overflow: auto; white-space: pre-wrap; overflow-wrap: anywhere; color: #172033; background: #fff; }
    .pr-preview-unavailable { max-width: 540px; padding: 30px; text-align: center; }
    body.pr-preview-open { overflow: hidden; }
</style>
@endpush

@section('content')
    @php
        $itemRows = old('items', [['name' => '', 'quantity' => 1, 'notes' => '']]);
    @endphp

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-end gap-3 mb-4">
        <div class="pr-intake-lead">
            <div class="aa-topbar-kicker mb-2">Purchase request intake</div>
            <h1 class="aa-page-title mb-2">Create PR</h1>
            <p class="text-muted mb-0">Tell the back office what is needed. They will complete the funding, coding, budget checks, and formal procurement details.</p>
        </div>
        <span class="badge bg-light text-dark border px-3 py-2">Simple request &middot; Back-office processing</span>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('administrative-assistant.purchase-requests.store') }}" enctype="multipart/form-data" id="purchaseRequestIntakeForm" class="aa-card p-4 p-lg-5">
                @csrf

                <section class="mb-5">
                    <h4 class="pr-section-title mb-3">What do you need?</h4>
                    <div class="mb-3">
                        <label for="requestTitle" class="form-label fw-bold">Request title <span class="pr-required">*</span></label>
                        <input id="requestTitle" type="text" name="title" value="{{ old('title') }}" maxlength="255" required
                               class="form-control form-control-lg @error('title') is-invalid @enderror"
                               placeholder="Example: Workshop venue and participant materials">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-0">
                        <label for="requestDescription" class="form-label fw-bold">Purpose and details <span class="pr-required">*</span></label>
                        <textarea id="requestDescription" name="description" rows="5" maxlength="5000" required
                                  class="form-control @error('description') is-invalid @enderror"
                                  placeholder="Explain what is required, why it is needed, and any important delivery information.">{{ old('description') }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </section>

                <section class="mb-5">
                    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
                        <div>
                            <h4 class="pr-section-title mb-1">Requested items</h4>
                            <div class="small text-muted">Use plain language. The back office will assign the formal resource codes.</div>
                        </div>
                        <button type="button" class="btn btn-aa-soft btn-sm" id="addRequestItem">
                            <i class="feather-plus me-1"></i> Add item
                        </button>
                    </div>

                    <div id="requestItems">
                        @foreach ($itemRows as $index => $itemRow)
                            <div class="pr-item-row" data-item-row>
                                <div class="row g-3">
                                    <div class="col-md-8">
                                        <label class="form-label fw-bold">Item or service <span class="pr-required">*</span></label>
                                        <input type="text" name="items[{{ $index }}][name]" value="{{ $itemRow['name'] ?? '' }}" maxlength="255" required
                                               class="form-control @error("items.$index.name") is-invalid @enderror" data-item-field="name"
                                               placeholder="Example: Conference room hire">
                                        @error("items.$index.name")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Quantity <span class="pr-required">*</span></label>
                                        <input type="number" name="items[{{ $index }}][quantity]" value="{{ $itemRow['quantity'] ?? 1 }}" min="0.01" step="0.01" required
                                               class="form-control @error("items.$index.quantity") is-invalid @enderror" data-item-field="quantity">
                                        @error("items.$index.quantity")<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label fw-bold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                                        <textarea name="items[{{ $index }}][notes]" rows="2" maxlength="1000" class="form-control" data-item-field="notes"
                                                  placeholder="Specifications, preferred size, location, or other helpful details">{{ $itemRow['notes'] ?? '' }}</textarea>
                                    </div>
                                    <div class="col-12 text-end">
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-remove-item>
                                            <i class="feather-trash-2 me-1"></i> Remove item
                                        </button>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                    @error('items')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                </section>

                <section class="mb-5">
                    <h4 class="pr-section-title mb-3">Timing and estimate</h4>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label for="neededBy" class="form-label fw-bold">Needed by <span class="text-muted fw-normal">(optional)</span></label>
                            <input id="neededBy" type="date" name="needed_by" min="{{ today()->format('Y-m-d') }}" value="{{ old('needed_by') }}"
                                   class="form-control @error('needed_by') is-invalid @enderror">
                            @error('needed_by')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="requestPriority" class="form-label fw-bold">Priority <span class="pr-required">*</span></label>
                            <select id="requestPriority" name="priority" class="form-select @error('priority') is-invalid @enderror" required>
                                @foreach (['normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label)
                                    <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                        <div class="col-md-4">
                            <label for="estimatedAmount" class="form-label fw-bold">Estimated amount <span class="text-muted fw-normal">(optional)</span></label>
                            <div class="input-group">
                                <input type="text" name="currency" value="{{ old('currency', 'USD') }}" maxlength="3" required aria-label="Currency"
                                       class="form-control @error('currency') is-invalid @enderror" style="max-width: 82px; text-transform: uppercase;">
                                <input id="estimatedAmount" type="number" name="estimated_amount" value="{{ old('estimated_amount') }}" min="0.01" step="0.01"
                                       class="form-control @error('estimated_amount') is-invalid @enderror" placeholder="0.00">
                            </div>
                            @error('currency')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            @error('estimated_amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="mb-4">
                    <h4 class="pr-section-title mb-1">Supporting documents</h4>
                    <p class="small text-muted mb-3">Optional: add quotations, specifications, approvals, images, or other useful files.</p>
                    <div class="pr-document-box" data-document-box>
                        <input id="requestDocuments" type="file" name="documents[]" multiple data-document-input
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip"
                               class="form-control @error('documents') is-invalid @enderror @error('documents.*') is-invalid @enderror">
                        <div class="form-text">Up to 10 files, 20MB each, and 60MB combined. Images, PDFs, and text files can be previewed before submission.</div>
                        <div class="small text-success fw-semibold mt-2 d-none" data-document-summary aria-live="polite"></div>
                        <div class="d-none" data-document-list></div>
                        <div class="small text-danger mt-2 d-none" data-document-error role="alert"></div>
                    </div>
                    @error('documents')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
                    @if ($errors->has('documents.*'))<div class="text-danger small mt-2">{{ $errors->first('documents.*') }}</div>@endif
                </section>

                <div class="alert alert-light border d-flex gap-2 align-items-start mb-4">
                    <i class="feather-info text-primary mt-1"></i>
                    <div class="small">Submitting this form does not reserve or approve budget. The back office will review and complete the formal purchase request.</div>
                </div>

                <button type="submit" class="btn btn-aa btn-lg w-100" id="submitPurchaseRequest">
                    <i class="feather-send me-2"></i> Submit PR to back office
                </button>
            </form>
        </div>

        <div class="col-xl-4">
            <aside class="aa-card p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                    <h5 class="fw-bold mb-0">My recent PRs</h5>
                    <span class="badge bg-light text-dark border">{{ $recentSubmissions->count() }}</span>
                </div>

                @forelse ($recentSubmissions as $submission)
                    <a href="{{ route('administrative-assistant.purchase-requests.show', $submission) }}" class="pr-recent-row">
                        <div class="d-flex justify-content-between gap-2 mb-1">
                            <span class="pr-reference">{{ $submission->reference_no }}</span>
                            <span class="badge {{ $submission->status === 'converted' ? 'bg-success' : 'bg-warning text-dark' }}">
                                {{ \Illuminate\Support\Str::headline($submission->status) }}
                            </span>
                        </div>
                        <div class="fw-semibold text-truncate">{{ $submission->title }}</div>
                        <div class="small text-muted mt-1">
                            {{ $submission->created_at?->format('d M Y') }} &middot;
                            {{ $submission->items_count }} item(s) &middot;
                            {{ $submission->documents_count }} file(s)
                        </div>
                    </a>
                @empty
                    <div class="text-center py-4">
                        <i class="feather-inbox fs-3 text-muted"></i>
                        <p class="text-muted small mb-0 mt-2">Your submitted purchase requests will appear here.</p>
                    </div>
                @endforelse
            </aside>
        </div>
    </div>

    <template id="requestItemTemplate">
        <div class="pr-item-row" data-item-row>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label fw-bold">Item or service <span class="pr-required">*</span></label>
                    <input type="text" maxlength="255" required class="form-control" data-item-field="name" placeholder="Example: Conference room hire">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Quantity <span class="pr-required">*</span></label>
                    <input type="number" value="1" min="0.01" step="0.01" required class="form-control" data-item-field="quantity">
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea rows="2" maxlength="1000" class="form-control" data-item-field="notes" placeholder="Specifications, preferred size, location, or other helpful details"></textarea>
                </div>
                <div class="col-12 text-end">
                    <button type="button" class="btn btn-sm btn-outline-danger" data-remove-item><i class="feather-trash-2 me-1"></i> Remove item</button>
                </div>
            </div>
        </div>
    </template>

    <div class="pr-preview-modal" data-preview-modal hidden>
        <div class="pr-preview-backdrop" data-close-preview></div>
        <section class="pr-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="requestPreviewTitle" tabindex="-1" data-preview-dialog>
            <header class="pr-preview-header">
                <div class="min-w-0">
                    <strong id="requestPreviewTitle" class="d-block text-truncate" data-preview-title>Document preview</strong>
                    <span class="small text-muted" data-preview-meta></span>
                </div>
                <button type="button" class="btn btn-sm btn-light border" data-close-preview aria-label="Close preview"><i class="feather-x"></i></button>
            </header>
            <div class="pr-preview-body" data-preview-body></div>
            <footer class="pr-preview-footer">This preview stays in your browser. Files are uploaded only when you submit the purchase request.</footer>
        </section>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('purchaseRequestIntakeForm');
    if (!form) return;

    const items = document.getElementById('requestItems');
    const itemTemplate = document.getElementById('requestItemTemplate');
    const addItem = document.getElementById('addRequestItem');

    const reindexItems = () => {
        const rows = Array.from(items.querySelectorAll('[data-item-row]'));
        rows.forEach((row, index) => {
            row.querySelectorAll('[data-item-field]').forEach((field) => {
                field.name = `items[${index}][${field.dataset.itemField}]`;
            });
            const remove = row.querySelector('[data-remove-item]');
            if (remove) remove.disabled = rows.length === 1;
        });
    };

    addItem?.addEventListener('click', () => {
        if (items.querySelectorAll('[data-item-row]').length >= 25) return;
        items.appendChild(itemTemplate.content.cloneNode(true));
        reindexItems();
        items.lastElementChild?.querySelector('[data-item-field="name"]')?.focus();
    });

    items.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-remove-item]');
        if (!remove || items.querySelectorAll('[data-item-row]').length <= 1) return;
        remove.closest('[data-item-row]')?.remove();
        reindexItems();
    });
    reindexItems();

    const maxFiles = 10;
    const maxFileBytes = 20 * 1024 * 1024;
    const maxCombinedBytes = 60 * 1024 * 1024;
    const maxTextPreviewBytes = 200 * 1024;
    const input = form.querySelector('[data-document-input]');
    const box = form.querySelector('[data-document-box]');
    const summary = box?.querySelector('[data-document-summary]');
    const list = box?.querySelector('[data-document-list]');
    const error = box?.querySelector('[data-document-error]');
    const submit = document.getElementById('submitPurchaseRequest');
    const originalSubmitLabel = submit?.innerHTML || '';
    let selectedFiles = [];
    let thumbnailUrls = [];

    const modal = document.querySelector('[data-preview-modal]');
    const modalDialog = modal?.querySelector('[data-preview-dialog]');
    const modalTitle = modal?.querySelector('[data-preview-title]');
    const modalMeta = modal?.querySelector('[data-preview-meta]');
    const modalBody = modal?.querySelector('[data-preview-body]');
    let modalUrl = null;
    let returnFocus = null;

    const extension = (file) => {
        const name = String(file?.name || '');
        const dot = name.lastIndexOf('.');
        return dot >= 0 ? name.slice(dot + 1).toLowerCase() : '';
    };
    const kind = (file) => {
        const ext = extension(file);
        if (file.type?.startsWith('image/') || ['jpg', 'jpeg', 'png'].includes(ext)) return 'image';
        if (file.type === 'application/pdf' || ext === 'pdf') return 'pdf';
        if (file.type?.startsWith('text/') || ['txt', 'csv'].includes(ext)) return 'text';
        return 'unavailable';
    };
    const icon = (file) => {
        const ext = extension(file);
        if (kind(file) === 'image') return 'image';
        if (ext === 'pdf') return 'file-text';
        if (['xls', 'xlsx', 'csv'].includes(ext)) return 'grid';
        if (['ppt', 'pptx'].includes(ext)) return 'monitor';
        if (ext === 'zip') return 'archive';
        return 'file';
    };
    const fileKey = (file) => [file.name, file.size, file.lastModified, file.type].join('::');
    const bytesLabel = (bytes) => {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
        return `${Number((bytes / (1024 * 1024)).toFixed(1))} MB`;
    };
    const totalBytes = (files = selectedFiles) => files.reduce((total, file) => total + file.size, 0);

    const syncInput = () => {
        if (typeof DataTransfer !== 'function') return false;
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        return true;
    };
    const setError = (message = '') => {
        if (!error) return;
        error.textContent = message;
        error.classList.toggle('d-none', message === '');
    };
    const validateFiles = (files) => {
        if (files.length > maxFiles) return 'Choose no more than 10 supporting documents.';
        const oversized = files.find((file) => file.size > maxFileBytes);
        if (oversized) return `${oversized.name} is larger than 20MB.`;
        if (totalBytes(files) > maxCombinedBytes) return 'The combined size of all documents must not be larger than 60MB.';
        return '';
    };

    const closePreview = () => {
        if (!modal || modal.hidden) return;
        modal.hidden = true;
        modalBody?.replaceChildren();
        document.body.classList.remove('pr-preview-open');
        if (modalUrl) URL.revokeObjectURL(modalUrl);
        modalUrl = null;
        if (returnFocus?.isConnected) returnFocus.focus();
        returnFocus = null;
    };

    const unavailablePreview = (file) => {
        const wrap = document.createElement('div');
        wrap.className = 'pr-preview-unavailable';
        const symbol = document.createElement('i');
        symbol.className = `feather-${icon(file)} fs-1 text-muted`;
        const title = document.createElement('h5');
        title.className = 'fw-bold mt-3';
        title.textContent = file.name;
        const copy = document.createElement('p');
        copy.className = 'text-muted mb-0';
        copy.textContent = 'This file is selected and ready, but your browser cannot display this format locally.';
        wrap.append(symbol, title, copy);
        modalBody?.replaceChildren(wrap);
    };

    const openPreview = async (file, trigger) => {
        if (!modal || !modalBody || !modalTitle || !modalMeta) return;
        if (modalUrl) URL.revokeObjectURL(modalUrl);
        modalUrl = null;
        returnFocus = trigger;
        modalTitle.textContent = file.name;
        modalMeta.textContent = `${extension(file).toUpperCase() || 'FILE'} - ${bytesLabel(file.size)}`;
        modalBody.replaceChildren();
        modal.hidden = false;
        document.body.classList.add('pr-preview-open');

        try {
            if (kind(file) === 'image') {
                modalUrl = URL.createObjectURL(file);
                const image = document.createElement('img');
                image.className = 'pr-preview-image';
                image.src = modalUrl;
                image.alt = `Preview of ${file.name}`;
                modalBody.appendChild(image);
            } else if (kind(file) === 'pdf') {
                modalUrl = URL.createObjectURL(file);
                const frame = document.createElement('iframe');
                frame.className = 'pr-preview-frame';
                frame.src = modalUrl;
                frame.title = `Preview of ${file.name}`;
                modalBody.appendChild(frame);
            } else if (kind(file) === 'text') {
                const preview = document.createElement('pre');
                preview.className = 'pr-preview-text';
                const contents = await file.slice(0, maxTextPreviewBytes).text();
                preview.textContent = contents + (file.size > maxTextPreviewBytes ? '\n\n[Preview limited to the first 200 KB]' : '');
                modalBody.replaceChildren(preview);
            } else {
                unavailablePreview(file);
            }
        } catch (previewError) {
            console.warn('The selected document could not be previewed.', previewError);
            unavailablePreview(file);
        }
        window.requestAnimationFrame(() => modalDialog?.focus());
    };

    const renderFiles = () => {
        thumbnailUrls.forEach((url) => URL.revokeObjectURL(url));
        thumbnailUrls = [];
        list?.replaceChildren();
        box?.classList.toggle('has-files', selectedFiles.length > 0);
        list?.classList.toggle('d-none', selectedFiles.length === 0);
        summary?.classList.toggle('d-none', selectedFiles.length === 0);
        if (summary) summary.textContent = selectedFiles.length
            ? `${selectedFiles.length} file(s) ready - ${bytesLabel(totalBytes())}`
            : '';

        selectedFiles.forEach((file, index) => {
            const row = document.createElement('div');
            row.className = 'pr-selected-file';
            const symbol = document.createElement('span');
            symbol.className = 'pr-file-icon';
            if (kind(file) === 'image') {
                const url = URL.createObjectURL(file);
                thumbnailUrls.push(url);
                const image = document.createElement('img');
                image.src = url;
                image.alt = '';
                symbol.appendChild(image);
            } else {
                const glyph = document.createElement('i');
                glyph.className = `feather-${icon(file)}`;
                symbol.appendChild(glyph);
            }
            const details = document.createElement('div');
            details.className = 'pr-file-name';
            const name = document.createElement('div');
            name.className = 'fw-semibold text-truncate';
            name.textContent = file.name;
            const meta = document.createElement('div');
            meta.className = 'small text-muted';
            meta.textContent = `${extension(file).toUpperCase() || 'FILE'} - ${bytesLabel(file.size)}`;
            details.append(name, meta);
            const actions = document.createElement('div');
            actions.className = 'pr-file-actions';
            const preview = document.createElement('button');
            preview.type = 'button';
            preview.title = 'Preview file';
            preview.setAttribute('aria-label', `Preview ${file.name}`);
            preview.innerHTML = '<i class="feather-eye" aria-hidden="true"></i>';
            preview.addEventListener('click', () => openPreview(file, preview));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'remove-file';
            remove.title = 'Remove file';
            remove.setAttribute('aria-label', `Remove ${file.name}`);
            remove.innerHTML = '<i class="feather-trash-2" aria-hidden="true"></i>';
            remove.addEventListener('click', () => {
                selectedFiles.splice(index, 1);
                syncInput();
                setError(validateFiles(selectedFiles));
                renderFiles();
            });
            actions.append(preview, remove);
            row.append(symbol, details, actions);
            list?.appendChild(row);
        });
    };

    input?.addEventListener('change', () => {
        const merged = [...selectedFiles, ...Array.from(input.files || [])];
        const unique = Array.from(new Map(merged.map((file) => [fileKey(file), file])).values());
        const message = validateFiles(unique);
        if (message) {
            setError(message);
            syncInput();
            return;
        }
        selectedFiles = unique;
        setError();
        syncInput();
        renderFiles();
    });

    modal?.querySelectorAll('[data-close-preview]').forEach((button) => button.addEventListener('click', closePreview));
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closePreview();
    });
    window.addEventListener('beforeunload', () => {
        thumbnailUrls.forEach((url) => URL.revokeObjectURL(url));
        if (modalUrl) URL.revokeObjectURL(modalUrl);
    });

    form.addEventListener('submit', (event) => {
        const message = validateFiles(selectedFiles);
        if (message) {
            event.preventDefault();
            setError(message);
            error?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        syncInput();
        if (submit) {
            submit.disabled = true;
            submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Submitting PR...';
            window.setTimeout(() => {
                submit.disabled = false;
                submit.innerHTML = originalSubmitLabel;
            }, 15000);
        }
    });
})();
</script>
@endpush
