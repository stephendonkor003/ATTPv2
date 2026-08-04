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
    .doc-row { display: flex; gap: 12px; align-items: center; padding: 13px 0; border-bottom: 1px solid #e8edf3; }
    .doc-row:last-child { border-bottom: 0; }
    .doc-icon { flex: 0 0 40px; width: 40px; height: 40px; display: grid; place-items: center; border-radius: 11px; background: var(--aa-mint); color: var(--aa-teal); }
    @media(max-width:575.98px) { .context-grid { grid-template-columns: 1fr; } }
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
                    <span class="badge bg-light text-dark border">Max 20MB each</span>
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
                        <div><div class="fw-bold">Invoice <span class="text-muted fw-normal">(optional)</span></div><div class="small text-muted">PDF, Word, Excel, PowerPoint or image</div></div>
                    </div>
                    <input type="file" name="invoice_document" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" data-file-input>
                    <div class="small text-success mt-2 d-none" data-file-summary></div>
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
                    <input type="file" name="supporting_documents[]" class="form-control" multiple
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip" data-file-input>
                    <div class="small text-success mt-2 d-none" data-file-summary></div>
                </div>

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
@endsection

@push('scripts')
<script>
    document.querySelectorAll('[data-file-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const box = input.closest('[data-upload-box]');
            const summary = box.querySelector('[data-file-summary]');
            const count = input.files?.length || 0;
            box.classList.toggle('has-files', count > 0);
            summary.classList.toggle('d-none', count === 0);
            summary.textContent = count === 1 ? input.files[0].name : `${count} files selected`;
        });
    });

    document.getElementById('evidenceUploadForm')?.addEventListener('submit', () => {
        const button = document.getElementById('uploadButton');
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Uploading and linking...';
    });
</script>
@endpush
