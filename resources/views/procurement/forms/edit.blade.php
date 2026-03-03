@extends('layouts.app')

@section('content')
    <div class="nxl-container">

        {{-- ================= PAGE HEADER ================= --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <h4 class="fw-bold text-dark">Edit Procurement Form</h4>

            <a href="{{ route('forms.index') }}" class="btn btn-outline-secondary btn-sm">
                <i class="feather-arrow-left me-1"></i> Back to Forms
            </a>
        </div>

        {{-- ================= MAIN CARD ================= --}}
        <div class="card mt-3 shadow-sm">
            <div class="card-body">

                {{-- FLASH MESSAGES --}}
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                {{-- ================= FORM META ================= --}}
                <div class="mb-4">
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Form Name</strong><br>
                            {{ $form->name }}
                        </div>
                        <div class="col-md-4">
                            <strong>Applies To</strong><br>
                            {{ ucfirst($form->applies_to) }}
                        </div>
                        <div class="col-md-4">
                            <strong>Status</strong><br>
                            <span
                                class="badge bg-{{ $form->status === 'approved'
                                    ? 'success'
                                    : ($form->status === 'submitted'
                                        ? 'warning'
                                        : ($form->status === 'rejected'
                                            ? 'danger'
                                            : 'secondary')) }}">
                                {{ ucfirst($form->status) }}
                            </span>
                        </div>
                    </div>

                    {{-- Rejection Reason --}}
                    @if ($form->status === 'rejected' && $form->rejection_reason)
                        <div class="alert alert-danger mt-3">
                            <strong>Rejection Reason:</strong><br>
                            {{ $form->rejection_reason }}
                        </div>
                    @endif

                    <div class="alert alert-info mt-3 mb-0">
                        <strong>Default fields enforced:</strong>
                        Name and Email are automatically included on all procurement forms.
                    </div>
                </div>

                <hr>

                {{-- ================= APPROVAL ACTIONS ================= --}}
                <div class="mb-4 d-flex gap-2">

                    @if (in_array($form->status, ['draft', 'rejected']))
                        @can('forms.submit')
                            <form method="POST" action="{{ route('forms.submit', $form->id) }}">
                                @csrf
                                <button class="btn btn-warning btn-sm">
                                    <i class="feather-send me-1"></i> Submit for Approval
                                </button>
                            </form>
                        @endcan
                    @endif

                    @if ($form->status === 'submitted')
                        @can('forms.approve')
                            <form method="POST" action="{{ route('forms.approve', $form->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-success btn-sm">
                                    <i class="feather-check-circle me-1"></i> Approve
                                </button>
                            </form>

                            @can('forms.reject')
                                <button class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="feather-x-circle me-1"></i> Reject
                                </button>
                            @endcan
                        @endcan
                    @endif

                    @if (($form->submissions_count ?? 0) === 0)
                        <form method="POST" action="{{ route('forms.destroy', $form->id) }}"
                            onsubmit="return confirm('Delete this form? This action cannot be undone.')">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm">
                                <i class="feather-trash-2 me-1"></i> Delete Form
                            </button>
                        </form>
                    @endif

                </div>

                <hr>

                {{-- ================= ADD FIELD ================= --}}
                @if ($form->canEdit())
                    <h6 class="fw-bold mb-3">Add New Field</h6>

                    <form method="POST" action="{{ route('forms.fields.store', $form->id) }}">
                        @csrf
                        <input type="hidden" name="form_id" value="{{ $form->id }}">

                        <div class="row">

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Label</label>
                                <input type="text" name="label" class="form-control" required>
                            </div>

                            <div class="col-md-4 mb-3">
                                <label class="form-label fw-semibold">Field Type</label>
                                <select name="field_type" id="fieldType" class="form-control" required>

                                    <optgroup label="Basic">
                                        <option value="text">Text</option>
                                        <option value="textarea">Textarea</option>
                                        <option value="number">Number</option>
                                        <option value="email">Email</option>
                                        <option value="tel">Phone</option>
                                    </optgroup>

                                    <optgroup label="Selection">
                                        <option value="select">Dropdown</option>
                                        <option value="multiselect">Multi Select</option>
                                        <option value="radio">Radio</option>
                                        <option value="checkbox_group">Checkbox Group</option>
                                    </optgroup>

                                    <optgroup label="File & Date">
                                        <option value="file">File Upload</option>
                                        <option value="date">Date</option>
                                        <option value="datetime-local">Date & Time</option>
                                    </optgroup>

                                </select>
                            </div>

                            <div class="col-md-3 mb-3 d-none" id="optionsBox">
                                <label class="form-label fw-semibold">Options</label>
                                <input type="text" name="options" class="form-control" placeholder="Option 1, Option 2">
                            </div>

                            <div class="col-md-1 mb-3 d-flex align-items-center">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="is_required" value="1">
                                    <label class="form-check-label">Required</label>
                                </div>
                            </div>

                        </div>

                        <button class="btn btn-primary btn-sm">
                            <i class="feather-plus me-1"></i> Add Field
                        </button>
                    </form>
                @else
                    <div class="alert alert-warning">
                        This form already has submissions and can no longer be edited.
                    </div>
                @endif

                <hr>

                {{-- ================= EXISTING FIELDS ================= --}}
                <h6 class="fw-bold mb-3">Existing Fields</h6>

                @if ($form->fields->count())
                    @php
                        $globalFieldKeys = \App\Models\DynamicForm::globalFieldKeys();
                    @endphp
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Label</th>
                                <th>Key</th>
                                <th>Type</th>
                                <th>Required</th>
                                <th width="90">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($form->fields as $field)
                                @php
                                    $isGlobal = in_array($field->field_key, $globalFieldKeys, true);
                                @endphp
                                <tr>
                                    <td>{{ $field->label }}</td>
                                    <td><code>{{ $field->field_key }}</code></td>
                                    <td>{{ ucfirst(str_replace('_', ' ', $field->field_type)) }}</td>
                                    <td>
                                        @if ($field->is_required)
                                            <span class="badge bg-success">Yes</span>
                                        @else
                                            <span class="badge bg-secondary">No</span>
                                        @endif
                                        @if ($isGlobal)
                                            <span class="badge bg-info ms-1">Global</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if ($form->canEdit() && !$isGlobal)
                                            <form method="POST" action="{{ route('forms.fields.destroy', $field->id) }}"
                                                onsubmit="return confirm('Remove this field?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="btn btn-sm btn-outline-danger">
                                                    <i class="feather-trash"></i>
                                                </button>
                                            </form>
                                        @else
                                            <span class="text-muted">Locked</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @else
                    <p class="text-muted">No fields added yet.</p>
                @endif

            </div>
        </div>
    </div>

    {{-- ================= REJECT MODAL ================= --}}
    @can('forms.reject')
        <div class="modal fade" id="rejectModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" action="{{ route('forms.reject', $form->id) }}">
                    @csrf
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Reject Form</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <label class="form-label">Reason for rejection</label>
                            <textarea name="rejection_reason" class="form-control" required></textarea>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-danger">Reject</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @endcan

    {{-- ================= JS ================= --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fieldType = document.getElementById('fieldType');
            const optionsBox = document.getElementById('optionsBox');

            const optionTypes = ['select', 'multiselect', 'radio', 'checkbox_group'];

            if (fieldType) {
                fieldType.addEventListener('change', function() {
                    optionsBox.classList.toggle('d-none', !optionTypes.includes(this.value));
                });
            }
        });
    </script>
@endsection
