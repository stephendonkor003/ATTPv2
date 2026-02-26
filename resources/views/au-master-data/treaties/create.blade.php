@extends('layouts.app')

@section('title', 'Create Treaty')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Create Treaty</h4>
                    <p class="text-muted mb-0">Register a treaty or agreement for member-state tracking.</p>
                </div>
                <a href="{{ route('settings.au.treaties.index') }}" class="btn btn-outline-secondary">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <form action="{{ route('settings.au.treaties.store') }}" method="POST" enctype="multipart/form-data">
                        @csrf

                        @include('au-master-data.treaties._form', ['treaty' => null])

                        <div class="mt-4 d-flex justify-content-between">
                            <a href="{{ route('settings.au.treaties.index') }}" class="btn btn-light">Cancel</a>
                            <button type="submit" class="btn btn-success">
                                <i class="feather-check me-1"></i> Save Treaty
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const wrapper = document.getElementById('supporting-documents-wrapper');
            const addButton = document.getElementById('add-supporting-document-row');
            if (!wrapper || !addButton) {
                return;
            }

            const rowTemplate = `
                <div class="row g-2 supporting-document-row mb-2">
                    <div class="col-md-4">
                        <input type="text" name="supporting_document_titles[]" class="form-control" placeholder="Document title (optional)">
                    </div>
                    <div class="col-md-3">
                        <select name="supporting_document_types[]" class="form-select">
                            <option value="">Type (optional)</option>
                            <option value="Legal Opinion">Legal Opinion</option>
                            <option value="Annex">Annex</option>
                            <option value="Policy Brief">Policy Brief</option>
                            <option value="Implementation Guide">Implementation Guide</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="file" name="supporting_documents[]" class="form-control">
                    </div>
                    <div class="col-md-1 d-flex align-items-center">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-supporting-document-row" title="Remove row">
                            <i class="feather-x"></i>
                        </button>
                    </div>
                </div>
            `;

            addButton.addEventListener('click', function() {
                wrapper.insertAdjacentHTML('beforeend', rowTemplate);
            });

            wrapper.addEventListener('click', function(event) {
                const removeButton = event.target.closest('.remove-supporting-document-row');
                if (!removeButton) {
                    return;
                }
                const rows = wrapper.querySelectorAll('.supporting-document-row');
                if (rows.length <= 1) {
                    const firstRow = rows[0];
                    if (!firstRow) {
                        return;
                    }
                    firstRow.querySelectorAll('input').forEach((input) => {
                        if (input.type === 'file') {
                            input.value = '';
                        } else {
                            input.value = '';
                        }
                    });
                    const select = firstRow.querySelector('select');
                    if (select) {
                        select.value = '';
                    }
                    return;
                }
                removeButton.closest('.supporting-document-row')?.remove();
            });
        });
    </script>
@endsection
