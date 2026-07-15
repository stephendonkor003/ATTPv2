@php
    $uploadedDocuments = $topic->exists ? $topic->uploadedDocuments : collect();
@endphp

<section class="forum-resource-group forum-upload-group">
    <div class="d-flex flex-column flex-md-row align-items-md-start justify-content-between gap-2 mb-3">
        <div>
            <h3 class="mb-1"><i class="feather-upload-cloud me-2"></i>Upload Documents</h3>
            <p class="small forum-muted mb-0">
                Upload files directly from your device. Files are stored privately and released through controlled
                read and download links when this discussion is public.
            </p>
        </div>
        <span class="badge bg-light text-dark border text-nowrap">Maximum 2 MB each</span>
    </div>

    <label class="forum-upload-dropzone" for="document_uploads">
        <span class="forum-upload-dropzone__icon"><i class="feather-file-plus"></i></span>
        <span>
            <strong>Choose one or more documents</strong>
            <small>PDF, Word, Excel, PowerPoint, text, CSV, JPG, PNG, or ZIP. Up to 10 files per save.</small>
        </span>
        <input id="document_uploads" type="file" name="document_uploads[]" multiple
            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.zip"
            class="form-control @error('document_uploads') is-invalid @enderror @error('document_uploads.*') is-invalid @enderror">
    </label>
    @error('document_uploads')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
    @error('document_uploads.*')
        <div class="text-danger small mt-2">{{ $message }}</div>
    @enderror
    <p class="form-text mb-0 mt-2">For security, executable files and web pages cannot be uploaded.</p>

    @if ($uploadedDocuments->isNotEmpty())
        <div class="forum-uploaded-list mt-4">
            <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                <strong class="small text-dark">Currently uploaded</strong>
                <span class="small forum-muted">{{ $uploadedDocuments->count() }} of 20 files</span>
            </div>

            @foreach ($uploadedDocuments as $document)
                @php
                    $inputPrefix = "uploaded_documents.{$document->id}";
                    $fieldPrefix = "uploaded_documents[{$document->id}]";
                @endphp
                <article class="forum-uploaded-document">
                    <header class="forum-uploaded-document__header">
                        <span class="forum-uploaded-document__icon"><i class="feather-file-text"></i></span>
                        <span class="min-w-0">
                            <strong>{{ $document->file_name }}</strong>
                            <small>{{ strtoupper($document->extension() ?: 'FILE') }} &middot; {{ $document->humanReadableSize() }}</small>
                        </span>
                        <a class="btn btn-sm btn-light border ms-md-auto"
                            href="{{ route('system.discussions.topics.documents.open', [$topic, $document]) }}"
                            target="_blank" rel="noopener">
                            <i class="feather-eye me-1"></i> Open
                        </a>
                    </header>

                    <div class="row g-3 mt-1">
                        <div class="col-md-5">
                            <label for="uploaded-document-title-{{ $document->id }}" class="form-label">Public title</label>
                            <input id="uploaded-document-title-{{ $document->id }}" type="text"
                                name="{{ $fieldPrefix }}[title]" maxlength="180" required
                                value="{{ old("{$inputPrefix}.title", $document->title) }}"
                                class="form-control @error("{$inputPrefix}.title") is-invalid @enderror">
                            @error("{$inputPrefix}.title")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-7">
                            <label for="uploaded-document-description-{{ $document->id }}" class="form-label">
                                Description <span class="forum-muted fw-normal">(optional)</span>
                            </label>
                            <input id="uploaded-document-description-{{ $document->id }}" type="text"
                                name="{{ $fieldPrefix }}[description]" maxlength="500"
                                value="{{ old("{$inputPrefix}.description", $document->description) }}"
                                class="form-control @error("{$inputPrefix}.description") is-invalid @enderror">
                            @error("{$inputPrefix}.description")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-8">
                            <label for="uploaded-document-replacement-{{ $document->id }}" class="form-label">
                                Replace file <span class="forum-muted fw-normal">(optional)</span>
                            </label>
                            <input id="uploaded-document-replacement-{{ $document->id }}" type="file"
                                name="{{ $fieldPrefix }}[replacement]"
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.zip"
                                class="form-control @error("{$inputPrefix}.replacement") is-invalid @enderror">
                            @error("{$inputPrefix}.replacement")
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <input type="hidden" name="{{ $fieldPrefix }}[remove]" value="0">
                            <div class="form-check border border-danger-subtle rounded px-3 py-2 w-100">
                                <input id="uploaded-document-remove-{{ $document->id }}" class="form-check-input"
                                    type="checkbox" name="{{ $fieldPrefix }}[remove]" value="1"
                                    @checked(old("{$inputPrefix}.remove") === '1')>
                                <label class="form-check-label text-danger fw-semibold" for="uploaded-document-remove-{{ $document->id }}">
                                    Remove on save
                                </label>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</section>
