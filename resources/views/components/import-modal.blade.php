@props([
    'id' => 'importModal',
    'title' => 'Import Data',
    'action',
    'templateRoute' => null,
    'templateName' => 'template.xlsx',
])

<div class="modal fade" id="{{ $id }}" tabindex="-1" aria-labelledby="{{ $id }}Label" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="{{ $action }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $id }}Label">
                        <i class="feather-upload me-2"></i>{{ $title }}
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="importFile" class="form-label">Select Excel/CSV File</label>
                        <input type="file" class="form-control" id="importFile" name="file"
                            accept=".xlsx,.xls,.csv" required>
                        <small class="text-muted">Supported formats: .xlsx, .xls, .csv</small>
                    </div>

                    @if($templateRoute)
                        <div class="alert alert-info py-2">
                            <i class="feather-info me-2"></i>
                            <strong>Need a template?</strong>
                            <a href="{{ $templateRoute }}" class="alert-link">
                                Download {{ $templateName }}
                            </a>
                        </div>
                    @endif

                    <div class="alert alert-warning py-2 mb-0">
                        <i class="feather-alert-triangle me-2"></i>
                        <small>
                            <strong>Note:</strong> Make sure your file follows the template format.
                            The first row should contain column headers.
                        </small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-upload me-1"></i> Import
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
