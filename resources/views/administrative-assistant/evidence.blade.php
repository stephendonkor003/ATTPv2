@extends('layouts.administrative-assistant')

@section('title', 'Upload Documents')

@push('styles')
<style>
    .step-chip { display: flex; gap: 11px; align-items: center; padding: 13px; border-radius: 13px; background: #f7fafc; }
    .step-number { flex: 0 0 31px; width: 31px; height: 31px; display: grid; place-items: center; border-radius: 50%; background: var(--aa-teal); color: #fff; font-weight: 800; }
    .context-grid { display: grid; grid-template-columns: repeat(2,minmax(0,1fr)); gap: 12px; }
    .context-item { padding: 13px; border-radius: 12px; background: #f8fafc; }
    .context-item small { display: block; color: #78869a; margin-bottom: 2px; }
    .upload-box { position: relative; padding: 22px; border: 2px dashed #c9d6e4; border-radius: 15px; background: #f9fbfd; transition: .15s ease; }
    .upload-box:hover, .upload-box.has-files { border-color: var(--aa-teal); background: #f0fbf8; }
    .upload-box input[type=file] { width: 100%; }
    .selected-files { display: grid; gap: 9px; margin-top: 14px; }
    .selected-file { display: grid; grid-template-columns: 48px minmax(0, 1fr) auto; gap: 11px; align-items: center; padding: 10px; border: 1px solid #dbe5ee; border-radius: 12px; background: #fff; }
    .selected-file.is-invalid { border-color: #e7a7ad; background: #fff7f8; }
    .selected-file-thumb { width: 48px; height: 48px; display: grid; place-items: center; overflow: hidden; border-radius: 10px; background: var(--aa-mint); color: var(--aa-teal); font-size: 1.15rem; }
    .selected-file-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .selected-file-copy { min-width: 0; }
    .selected-file-name { overflow: hidden; color: var(--aa-navy); font-weight: 700; text-overflow: ellipsis; white-space: nowrap; }
    .selected-file-actions { display: flex; gap: 6px; }
    .selected-file-actions .btn { white-space: nowrap; }
    .upload-selection-feedback { padding: 10px 12px; border-radius: 10px; background: #fff4e5; color: #8b5100; }
    .file-preview-modal[hidden] { display: none; }
    .file-preview-modal { position: fixed; inset: 0; z-index: 1055; display: grid; place-items: center; padding: 20px; }
    .file-preview-backdrop { position: absolute; inset: 0; background: rgba(10, 24, 43, .72); backdrop-filter: blur(3px); }
    .file-preview-dialog { position: relative; width: min(920px, 100%); max-height: calc(100vh - 40px); display: flex; flex-direction: column; overflow: hidden; border-radius: 18px; background: #fff; box-shadow: 0 28px 80px rgba(5, 16, 30, .38); }
    .file-preview-header { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 17px 20px; border-bottom: 1px solid var(--aa-border); }
    .file-preview-title { min-width: 0; }
    .file-preview-title strong { display: block; overflow: hidden; color: var(--aa-navy); text-overflow: ellipsis; white-space: nowrap; }
    .file-preview-close { flex: 0 0 auto; width: 36px; height: 36px; display: grid; place-items: center; border: 0; border-radius: 50%; background: #edf2f7; color: var(--aa-navy); font-size: 1.15rem; }
    .file-preview-body { min-height: 320px; height: min(68vh, 680px); display: grid; place-items: center; overflow: auto; background: #e8eef4; }
    .file-preview-frame { width: 100%; height: 100%; border: 0; background: #fff; }
    .file-preview-image { display: block; max-width: 100%; max-height: 100%; margin: auto; object-fit: contain; }
    .file-preview-text { align-self: stretch; justify-self: stretch; min-height: 100%; margin: 0; padding: 24px; overflow: auto; background: #fff; color: #1f2937; font: .85rem/1.55 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; white-space: pre-wrap; word-break: break-word; }
    .file-preview-unavailable { max-width: 540px; padding: 34px; text-align: center; }
    .file-preview-unavailable .doc-icon { margin: 0 auto 14px; width: 58px; height: 58px; }
    .file-preview-footer { padding: 11px 20px; border-top: 1px solid var(--aa-border); background: #f8fafc; color: #607087; font-size: .78rem; }
    body.file-preview-open { overflow: hidden; }
    .doc-row { display: flex; gap: 12px; align-items: center; padding: 13px 0; border-bottom: 1px solid #e8edf3; }
    .doc-row:last-child { border-bottom: 0; }
    .doc-icon { flex: 0 0 40px; width: 40px; height: 40px; display: grid; place-items: center; border-radius: 11px; background: var(--aa-mint); color: var(--aa-teal); }
    @media(max-width:575.98px) {
        .context-grid { grid-template-columns: 1fr; }
        .selected-file { grid-template-columns: 42px minmax(0, 1fr); }
        .selected-file-thumb { width: 42px; height: 42px; }
        .selected-file-actions { grid-column: 1 / -1; }
        .selected-file-actions .btn { flex: 1; }
        .file-preview-modal { padding: 10px; }
        .file-preview-dialog { max-height: calc(100vh - 20px); }
    }
</style>
@endpush

@section('content')
    @php
        $returnYear = (int) request('year');
        $returnMonth = (int) request('month');
        $backUrl = $returnYear && $returnMonth
            ? route('administrative-assistant.dashboard', ['year' => $returnYear, 'month' => $returnMonth])
            : route('administrative-assistant.dashboard');
    @endphp
    <a href="{{ $backUrl }}" class="btn btn-sm btn-light border mb-3"><i class="feather-arrow-left me-1"></i> Back to {{ $returnMonth ? 'vendor cards' : 'year folders' }}</a>

    <div class="mb-4">
        <div class="aa-topbar-kicker mb-2">Monthly deliverable</div>
        <h1 class="aa-page-title mb-2">{{ $item->milestone ?: ($item->deliverable?->title ?? 'Upload documents') }}</h1>
        <p class="text-muted mb-0">Everything you upload here is automatically connected to the purchase request, vendor account, and invoice register.</p>
    </div>

    <div class="row g-4 align-items-start">
        <div class="col-xl-5">
            <div class="aa-card p-4 mb-4">
                <h5 class="fw-bold mb-3">Where these files will go</h5>
                <div class="context-grid">
                    <div class="context-item"><small>Vendor</small><strong>{{ $purchaseOrder->vendor?->name ?? 'N/A' }}</strong></div>
                    <div class="context-item"><small>Due date</small><strong>{{ $suggestedDate?->format('d M Y') ?? 'Not set' }}</strong></div>
                    <div class="context-item"><small>Purchase request</small><strong>{{ $purchaseRequest->reference_no ?? 'N/A' }}</strong></div>
                    <div class="context-item"><small>Purchase order</small><strong>{{ $purchaseOrder->reference_no ?? 'N/A' }}</strong></div>
                    <div class="context-item"><small>Value</small><strong>{{ $purchaseOrder->resolved_currency }} {{ number_format((float) $item->amount, 2) }}</strong></div>
                    <div class="context-item"><small>Invoice register</small><strong>{{ $invoice?->reference_no ?? 'Created after invoice upload' }}</strong></div>
                </div>
            </div>

            <div class="aa-card p-4">
                <h5 class="fw-bold mb-3">How it works</h5>
                <div class="d-grid gap-2">
                    <div class="step-chip"><span class="step-number">1</span><div><strong>Confirm the date</strong><div class="small text-muted">The scheduled date is already filled in.</div></div></div>
                    <div class="step-chip"><span class="step-number">2</span><div><strong>Choose your files</strong><div class="small text-muted">Add an invoice, supporting documents, or both.</div></div></div>
                    <div class="step-chip"><span class="step-number">3</span><div><strong>Press upload</strong><div class="small text-muted">ATTP handles all links and records automatically.</div></div></div>
                </div>
            </div>
        </div>

        <div class="col-xl-7">
            <form method="POST" action="{{ route('administrative-assistant.evidence.store', [$purchaseOrder, $item]) }}" enctype="multipart/form-data" class="aa-card p-4" id="evidenceUploadForm">
                @csrf
                @if ($returnYear && $returnMonth)
                    <input type="hidden" name="return_year" value="{{ $returnYear }}">
                    <input type="hidden" name="return_month" value="{{ $returnMonth }}">
                @endif
                <div class="d-flex justify-content-between align-items-start gap-3 mb-4">
                    <div>
                        <h4 class="fw-bold mb-1">Add files</h4>
                        <div class="text-muted small">An invoice or at least one supporting document is required.</div>
                    </div>
                    <span class="badge bg-light text-dark border">20 files &middot; 20MB each &middot; 60MB total</span>
                </div>

                <div class="mb-4">
                    <label for="deliverableDate" class="form-label fw-bold">Deliverable date <span class="text-danger">*</span></label>
                    <input id="deliverableDate" type="date" name="deliverable_date" class="form-control form-control-lg @error('deliverable_date') is-invalid @enderror"
                           value="{{ old('deliverable_date', $suggestedDate?->format('Y-m-d')) }}" required>
                    <div class="form-text">Use the date shown on the monthly deliverable.</div>
                </div>

                <div class="upload-box mb-3" data-upload-box>
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <span class="doc-icon"><i class="feather-file-text"></i></span>
                        <div><div class="fw-bold">Invoice documents <span class="text-muted fw-normal">(optional)</span></div><div class="small text-muted">Select one or more PDF, Word, Excel, PowerPoint or image files</div></div>
                    </div>
                    <input id="invoiceDocuments" type="file" name="invoice_documents[]" class="form-control @error('invoice_documents') is-invalid @enderror @error('invoice_documents.*') is-invalid @enderror"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" multiple data-file-input data-file-category="invoice">
                    <div class="form-text">Choose several files together, or open the chooser again to add more.</div>
                    <div class="small text-success mt-2 d-none" data-file-summary aria-live="polite"></div>
                    <div class="selected-files d-none" data-file-list></div>
                    <div class="small text-danger mt-2 d-none" data-file-error role="alert"></div>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-md-7">
                        <label class="form-label fw-bold">Invoice number <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" name="invoice_reference" value="{{ old('invoice_reference', $invoice?->reference_no) }}" class="form-control" placeholder="Leave blank and ATTP will create one">
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Invoice amount</label>
                        <div class="input-group">
                            <span class="input-group-text">{{ $purchaseOrder->resolved_currency }}</span>
                            <input type="number" min="0.01" step="0.01" name="invoice_amount" value="{{ old('invoice_amount', $invoice?->amount ?? $item->amount) }}" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="upload-box mb-4" data-upload-box>
                    <div class="d-flex gap-3 align-items-start mb-3">
                        <span class="doc-icon"><i class="feather-paperclip"></i></span>
                        <div><div class="fw-bold">Supporting documents <span class="text-muted fw-normal">(optional)</span></div><div class="small text-muted">Reports, receipts, approvals, photos or other evidence</div></div>
                    </div>
                    <input id="supportingDocuments" type="file" name="supporting_documents[]" class="form-control @error('supporting_documents') is-invalid @enderror @error('supporting_documents.*') is-invalid @enderror" multiple
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip" data-file-input data-file-category="supporting">
                    <div class="form-text">Choose several files together, or open the chooser again to add more.</div>
                    <div class="small text-success mt-2 d-none" data-file-summary aria-live="polite"></div>
                    <div class="selected-files d-none" data-file-list></div>
                    <div class="small text-danger mt-2 d-none" data-file-error role="alert"></div>
                </div>

                <div class="upload-selection-feedback d-none mb-4" data-upload-selection-feedback role="alert" tabindex="-1"></div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Short note <span class="text-muted fw-normal">(optional)</span></label>
                    <textarea name="notes" rows="3" maxlength="3000" class="form-control" placeholder="Example: July report and signed invoice received by email">{{ old('notes') }}</textarea>
                </div>

                <button type="submit" class="btn btn-aa btn-lg w-100" id="uploadButton">
                    <i class="feather-upload-cloud me-2"></i> Upload and link everything
                </button>
                <div class="small text-muted text-center mt-2">Please keep this page open until the upload finishes.</div>
            </form>

            @php($documents = collect($evidence?->documents ?? [])->filter(fn ($document) => is_array($document)))
            @if ($documents->isNotEmpty())
                <div class="aa-card p-4 mt-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="fw-bold mb-0">Already uploaded</h5>
                        <span class="badge bg-success-subtle text-success">{{ $documents->count() }} file(s)</span>
                    </div>
                    @foreach ($documents as $index => $document)
                        <div class="doc-row">
                            <span class="doc-icon"><i class="feather-{{ ($document['document_type'] ?? '') === 'invoice' ? 'file-text' : 'paperclip' }}"></i></span>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold text-truncate">{{ $document['display_name'] ?? $document['name'] ?? 'Document' }}</div>
                                <div class="small text-muted">
                                    {{ ucfirst($document['document_type'] ?? 'evidence') }}
                                    @if (!empty($document['uploaded_at'])) · {{ \Carbon\Carbon::parse($document['uploaded_at'])->format('d M Y H:i') }} @endif
                                    @if (!empty($document['uploaded_by_name'])) · {{ $document['uploaded_by_name'] }} @endif
                                </div>
                            </div>
                            <a href="{{ route('administrative-assistant.evidence.documents.download', [$purchaseOrder, $item, $evidence, $index, 'download' => 1]) }}" class="btn btn-sm btn-aa-soft"><i class="feather-download"></i><span class="d-none d-sm-inline ms-1">Download</span></a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="file-preview-modal" data-file-preview-modal hidden>
        <div class="file-preview-backdrop" data-close-file-preview></div>
        <section class="file-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="filePreviewTitle" tabindex="-1" data-file-preview-dialog>
            <header class="file-preview-header">
                <div class="file-preview-title">
                    <strong id="filePreviewTitle" data-file-preview-title>Document preview</strong>
                    <span class="small text-muted" data-file-preview-meta></span>
                </div>
                <button type="button" class="file-preview-close" data-close-file-preview aria-label="Close document preview">
                    <i class="feather-x" aria-hidden="true"></i>
                </button>
            </header>
            <div class="file-preview-body" data-file-preview-body></div>
            <footer class="file-preview-footer">
                This local preview stays in your browser. Files are uploaded only after you press <strong>Upload and link everything</strong>.
            </footer>
        </section>
    </div>
@endsection

@push('scripts')
<script>
    (() => {
        const form = document.getElementById('evidenceUploadForm');
        if (!form) return;

        const maxFiles = 20;
        const maxFileBytes = 20 * 1024 * 1024;
        const maxCombinedBytes = 60 * 1024 * 1024;
        const textPreviewBytes = 200 * 1024;
        const uploadFeedback = form.querySelector('[data-upload-selection-feedback]');
        const uploadButton = document.getElementById('uploadButton');
        const uploadButtonLabel = uploadButton?.innerHTML || '';
        const modal = document.querySelector('[data-file-preview-modal]');
        const modalDialog = modal?.querySelector('[data-file-preview-dialog]');
        const modalTitle = modal?.querySelector('[data-file-preview-title]');
        const modalMeta = modal?.querySelector('[data-file-preview-meta]');
        const modalBody = modal?.querySelector('[data-file-preview-body]');
        let modalObjectUrl = null;
        let previewReturnFocus = null;
        let previewSequence = 0;

        const controllers = Array.from(form.querySelectorAll('[data-file-input]')).map((input) => {
            const box = input.closest('[data-upload-box]');

            return {
                input,
                box,
                summary: box?.querySelector('[data-file-summary]'),
                list: box?.querySelector('[data-file-list]'),
                error: box?.querySelector('[data-file-error]'),
                files: [],
                thumbnailUrls: [],
                notice: '',
            };
        });

        const fileExtension = (file) => {
            const name = String(file?.name || '');
            const separator = name.lastIndexOf('.');
            return separator >= 0 ? name.slice(separator + 1).toLowerCase() : '';
        };

        const previewKind = (file) => {
            const extension = fileExtension(file);
            if (file.type?.startsWith('image/') || ['jpg', 'jpeg', 'png'].includes(extension)) return 'image';
            if (file.type === 'application/pdf' || extension === 'pdf') return 'pdf';
            if (file.type?.startsWith('text/') || ['txt', 'csv'].includes(extension)) return 'text';
            return 'unavailable';
        };

        const fileIcon = (file) => {
            const extension = fileExtension(file);
            if (previewKind(file) === 'image') return 'image';
            if (extension === 'pdf') return 'file-text';
            if (['doc', 'docx'].includes(extension)) return 'file-text';
            if (['xls', 'xlsx', 'csv'].includes(extension)) return 'grid';
            if (['ppt', 'pptx'].includes(extension)) return 'monitor';
            if (extension === 'zip') return 'archive';
            return 'file';
        };

        const fileKey = (file) => [file.name, file.size, file.lastModified, file.type].join('::');

        const formatFileSize = (bytes) => {
            if (bytes < 1024) return `${bytes} B`;
            if (bytes < 1024 * 1024) return `${Math.max(1, Math.round(bytes / 1024))} KB`;
            return `${Number((bytes / (1024 * 1024)).toFixed(1))} MB`;
        };

        const selectedFiles = () => controllers.flatMap((controller) => controller.files);
        const selectedFileCount = () => selectedFiles().length;
        const selectedByteCount = () => selectedFiles().reduce((total, file) => total + file.size, 0);

        const replaceInputFiles = (controller) => {
            if (typeof DataTransfer !== 'function') return false;

            try {
                const transfer = new DataTransfer();
                controller.files.forEach((file) => transfer.items.add(file));
                controller.input.files = transfer.files;
                return true;
            } catch (error) {
                console.warn('This browser could not update the selected document list.', error);
                return false;
            }
        };

        const revokeThumbnailUrls = (controller) => {
            controller.thumbnailUrls.forEach((url) => URL.revokeObjectURL(url));
            controller.thumbnailUrls = [];
        };

        const appendButtonContent = (button, iconName, label) => {
            const icon = document.createElement('i');
            icon.className = `feather-${iconName}`;
            icon.setAttribute('aria-hidden', 'true');
            const copy = document.createElement('span');
            copy.textContent = label;
            button.append(icon, copy);
        };

        const showUnavailablePreview = (file, message) => {
            const unavailable = document.createElement('div');
            unavailable.className = 'file-preview-unavailable';

            const iconWrap = document.createElement('span');
            iconWrap.className = 'doc-icon';
            const icon = document.createElement('i');
            icon.className = `feather-${fileIcon(file)}`;
            icon.setAttribute('aria-hidden', 'true');
            iconWrap.appendChild(icon);

            const heading = document.createElement('h5');
            heading.className = 'fw-bold';
            heading.textContent = file.name;

            const copy = document.createElement('p');
            copy.className = 'text-muted mb-2';
            copy.textContent = message;

            const details = document.createElement('div');
            details.className = 'small fw-semibold';
            details.textContent = `${fileExtension(file).toUpperCase() || 'FILE'} - ${formatFileSize(file.size)}`;

            unavailable.append(iconWrap, heading, copy, details);
            modalBody?.replaceChildren(unavailable);
        };

        const releaseModalObjectUrl = () => {
            if (!modalObjectUrl) return;
            URL.revokeObjectURL(modalObjectUrl);
            modalObjectUrl = null;
        };

        const openPreview = async (file, trigger) => {
            if (!modal || !modalBody || !modalTitle || !modalMeta) return;

            previewSequence += 1;
            const currentSequence = previewSequence;
            releaseModalObjectUrl();
            previewReturnFocus = trigger;
            modalTitle.textContent = file.name;
            modalMeta.textContent = `${fileExtension(file).toUpperCase() || 'FILE'} - ${formatFileSize(file.size)}`;
            modalBody.replaceChildren();
            modal.hidden = false;
            document.body.classList.add('file-preview-open');

            const kind = previewKind(file);
            try {
                if (kind === 'image') {
                    modalObjectUrl = URL.createObjectURL(file);
                    const image = document.createElement('img');
                    image.className = 'file-preview-image';
                    image.src = modalObjectUrl;
                    image.alt = `Preview of ${file.name}`;
                    modalBody.appendChild(image);
                } else if (kind === 'pdf') {
                    modalObjectUrl = URL.createObjectURL(file);
                    const frame = document.createElement('iframe');
                    frame.className = 'file-preview-frame';
                    frame.src = modalObjectUrl;
                    frame.title = `Preview of ${file.name}`;
                    modalBody.appendChild(frame);
                } else if (kind === 'text') {
                    const loading = document.createElement('div');
                    loading.className = 'text-muted';
                    loading.textContent = 'Preparing local preview...';
                    modalBody.appendChild(loading);

                    const contents = await file.slice(0, textPreviewBytes).text();
                    if (currentSequence !== previewSequence || modal.hidden) return;

                    const preview = document.createElement('pre');
                    preview.className = 'file-preview-text';
                    preview.textContent = contents + (file.size > textPreviewBytes ? '\n\n[Preview limited to the first 200 KB]' : '');
                    modalBody.replaceChildren(preview);
                } else {
                    showUnavailablePreview(
                        file,
                        'This file is selected and ready. Your browser cannot display this format locally; check its name and size, or remove it before uploading.'
                    );
                }
            } catch (error) {
                console.warn('The selected document could not be previewed.', error);
                showUnavailablePreview(file, 'This file could not be rendered in the local preview. You can remove it or continue with the upload.');
            }

            window.requestAnimationFrame(() => modalDialog?.focus());
        };

        const closePreview = () => {
            if (!modal || modal.hidden) return;

            previewSequence += 1;
            modal.hidden = true;
            modalBody?.replaceChildren();
            document.body.classList.remove('file-preview-open');
            releaseModalObjectUrl();

            const focusTarget = previewReturnFocus;
            previewReturnFocus = null;
            if (focusTarget?.isConnected) focusTarget.focus();
        };

        const renderController = (controller) => {
            revokeThumbnailUrls(controller);
            controller.list?.replaceChildren();
            const count = controller.files.length;
            const bytes = controller.files.reduce((total, file) => total + file.size, 0);
            controller.box?.classList.toggle('has-files', count > 0);

            if (controller.summary) {
                controller.summary.classList.toggle('d-none', count === 0);
                controller.summary.textContent = count === 0
                    ? ''
                    : `${count} ${count === 1 ? 'file' : 'files'} ready - ${formatFileSize(bytes)}`;
            }
            controller.list?.classList.toggle('d-none', count === 0);

            controller.files.forEach((file, index) => {
                const row = document.createElement('div');
                row.className = 'selected-file';
                if (file.size > maxFileBytes) row.classList.add('is-invalid');

                const thumbnail = document.createElement('div');
                thumbnail.className = 'selected-file-thumb';
                if (previewKind(file) === 'image') {
                    const url = URL.createObjectURL(file);
                    controller.thumbnailUrls.push(url);
                    const image = document.createElement('img');
                    image.src = url;
                    image.alt = '';
                    thumbnail.appendChild(image);
                } else {
                    const icon = document.createElement('i');
                    icon.className = `feather-${fileIcon(file)}`;
                    icon.setAttribute('aria-hidden', 'true');
                    thumbnail.appendChild(icon);
                }

                const copy = document.createElement('div');
                copy.className = 'selected-file-copy';
                const name = document.createElement('div');
                name.className = 'selected-file-name';
                name.title = file.name;
                name.textContent = file.name;
                const meta = document.createElement('div');
                meta.className = file.size > maxFileBytes ? 'small text-danger' : 'small text-muted';
                meta.textContent = `${fileExtension(file).toUpperCase() || 'FILE'} - ${formatFileSize(file.size)}${file.size > maxFileBytes ? ' - exceeds 20MB' : ''}`;
                copy.append(name, meta);

                const actions = document.createElement('div');
                actions.className = 'selected-file-actions';
                const previewButton = document.createElement('button');
                previewButton.type = 'button';
                previewButton.className = 'btn btn-sm btn-aa-soft';
                previewButton.setAttribute('aria-label', `Preview ${file.name}`);
                appendButtonContent(previewButton, 'eye', previewKind(file) === 'unavailable' ? 'Review' : 'Preview');
                previewButton.addEventListener('click', () => openPreview(file, previewButton));

                const removeButton = document.createElement('button');
                removeButton.type = 'button';
                removeButton.className = 'btn btn-sm btn-outline-danger';
                removeButton.setAttribute('aria-label', `Remove ${file.name}`);
                appendButtonContent(removeButton, 'trash-2', 'Remove');
                removeButton.addEventListener('click', () => {
                    controller.files.splice(index, 1);
                    controller.notice = '';
                    if (!replaceInputFiles(controller)) {
                        controller.input.value = '';
                        controller.files = [];
                        controller.notice = 'This browser could not remove one file, so this selection was cleared. Choose the files together and try again.';
                    }
                    renderAll();
                });

                actions.append(previewButton, removeButton);
                row.append(thumbnail, copy, actions);
                controller.list?.appendChild(row);
            });

            const errors = [];
            if (controller.notice) errors.push(controller.notice);
            if (controller.files.some((file) => file.size > maxFileBytes)) {
                errors.push('Remove files larger than 20MB before uploading.');
            }

            if (controller.error) {
                controller.error.textContent = errors.join(' ');
                controller.error.classList.toggle('d-none', errors.length === 0);
            }
        };

        const updateValidation = () => {
            const files = selectedFiles();
            const totalBytes = selectedByteCount();
            let globalMessage = '';

            if (files.length > maxFiles) {
                globalMessage = `Choose no more than ${maxFiles} documents in one upload.`;
            } else if (totalBytes > maxCombinedBytes) {
                globalMessage = `The selected files total ${formatFileSize(totalBytes)}. Remove files until the combined size is 60MB or less.`;
            }

            controllers.forEach((controller) => {
                const hasOversizedFile = controller.files.some((file) => file.size > maxFileBytes);
                controller.input.setCustomValidity(hasOversizedFile ? 'Each document must not be larger than 20MB.' : '');
            });

            if (globalMessage && !controllers.some((controller) => controller.input.validationMessage)) {
                controllers[0]?.input.setCustomValidity(globalMessage);
            }

            if (uploadFeedback) {
                uploadFeedback.textContent = globalMessage;
                uploadFeedback.classList.toggle('d-none', globalMessage === '');
            }
        };

        function renderAll() {
            controllers.forEach(renderController);
            updateValidation();
        }

        controllers.forEach((controller) => {
            controller.input.addEventListener('change', () => {
                const incomingFiles = Array.from(controller.input.files || []);
                const knownFiles = new Set(controller.files.map(fileKey));
                let rejectedFiles = 0;
                controller.notice = '';

                incomingFiles.forEach((file) => {
                    const key = fileKey(file);
                    if (knownFiles.has(key)) return;
                    if (selectedFileCount() >= maxFiles) {
                        rejectedFiles += 1;
                        return;
                    }

                    controller.files.push(file);
                    knownFiles.add(key);
                });

                if (rejectedFiles > 0) {
                    controller.notice = `${rejectedFiles} ${rejectedFiles === 1 ? 'file was' : 'files were'} not added because one upload can contain no more than ${maxFiles} documents.`;
                }

                if (!replaceInputFiles(controller)) {
                    controller.files = incomingFiles.slice(0, maxFiles);
                    controller.notice = 'This browser cannot retain files from separate chooser actions. Select all required files together.';
                }

                renderAll();
            });
        });

        modal?.querySelectorAll('[data-close-file-preview]').forEach((button) => {
            button.addEventListener('click', closePreview);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal && !modal.hidden) closePreview();
        });

        form.addEventListener('submit', (event) => {
            updateValidation();
            if (selectedFileCount() === 0) {
                event.preventDefault();
                const message = 'Choose an invoice or at least one supporting document to upload.';
                if (uploadFeedback) {
                    uploadFeedback.textContent = message;
                    uploadFeedback.classList.remove('d-none');
                    uploadFeedback.focus();
                }
                controllers[0]?.input.setCustomValidity(message);
                controllers[0]?.input.reportValidity();
                return;
            }

            if (!form.checkValidity()) {
                event.preventDefault();
                form.reportValidity();
                return;
            }

            if (uploadButton) {
                uploadButton.disabled = true;
                uploadButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Uploading and linking...';
            }
        });

        window.addEventListener('pageshow', () => {
            if (!uploadButton) return;
            uploadButton.disabled = false;
            uploadButton.innerHTML = uploadButtonLabel;
        });

        window.addEventListener('beforeunload', () => {
            releaseModalObjectUrl();
            controllers.forEach(revokeThumbnailUrls);
        });

        renderAll();
    })();
</script>
@endpush
