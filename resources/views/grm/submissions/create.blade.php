@extends(auth()->user()?->user_type === 'funding_partner' ? 'layouts.partner' : (auth()->user()?->user_type === 'vendor' ? 'layouts.vendor' : (auth()->user()?->user_type === 'ttl' ? 'layouts.ttl' : 'layouts.app')))

@section('title', 'Grievance Redress Mechanism')
@section('partner_page_title', 'Grievance Redress Mechanism')
@section('partner_page_subtitle', 'Log a program-linked grievance for formal ATTP follow up.')
@section('ttl_page_title', 'Grievance Redress Mechanism')
@section('ttl_page_subtitle', 'Log a program-linked grievance for formal ATTP follow up.')

@section('content')
    <style>
        .grm-hero {
            border-radius: 8px;
            padding: 22px;
            color: #fff;
            background: linear-gradient(135deg, #064e3b 0%, #0f766e 58%, #522b39 100%);
            box-shadow: 0 18px 36px rgba(6, 78, 59, 0.18);
        }
        .grm-hero h4,
        .grm-hero p { color: #fff; }
        .grm-shell {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 16px;
            align-items: start;
        }
        .grm-card {
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.06);
        }
        .grm-card-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            border-radius: 8px 8px 0 0;
        }
        .grm-card-body { padding: 18px; }
        .grm-icon {
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            color: #fff;
            background: #0f766e;
        }
        .grm-step {
            display: flex;
            gap: 12px;
            padding: 13px;
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #f8fafc;
        }
        .grm-step + .grm-step { margin-top: 10px; }
        .grm-identity.is-anonymous { display: none; }
        .grm-anonymous-note {
            display: none;
            border: 1px solid #bbf7d0;
            background: #ecfdf5;
            color: #166534;
            border-radius: 8px;
            padding: 12px 14px;
            font-weight: 700;
        }
        .grm-anonymous-note.is-visible { display: flex; gap: 10px; align-items: center; }
        .grm-document-row {
            display: grid;
            grid-template-columns: minmax(180px, .8fr) minmax(220px, 1fr) auto;
            gap: 10px;
            align-items: end;
            border: 1px solid #dbe5df;
            border-radius: 8px;
            background: #f8fafc;
            padding: 12px;
        }
        .grm-document-remove {
            width: 42px;
            height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 991px) {
            .grm-shell { grid-template-columns: 1fr; }
            .grm-document-row { grid-template-columns: 1fr; }
            .grm-document-remove { width: 100%; }
        }
    </style>

    <div class="container-fluid">
        <div class="grm-hero mb-3">
            <div class="d-flex flex-wrap justify-content-between gap-3 align-items-center">
                <div>
                    <span class="badge bg-light text-success mb-2">Grievance Redress Mechanism</span>
                    <h4 class="mb-1">Log a Grievance</h4>
                    <p class="mb-0">Capture program-linked grievances, notify the responsible program Grievance Redress Mechanism officer, and generate a traceable case number.</p>
                </div>
                @can('grm.view')
                    <a href="{{ route('grm.logs.index') }}" class="btn btn-light text-success fw-bold">
                        <i class="feather-clipboard me-1"></i> View Logs
                    </a>
                @endcan
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger border-0 shadow-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="grm-shell">
            <form method="POST" action="{{ route('grm.submissions.store') }}" class="grm-card" enctype="multipart/form-data">
                @csrf
                <div class="grm-card-header">
                    <div class="d-flex align-items-center gap-2">
                        <span class="grm-icon"><i class="feather-edit-3"></i></span>
                        <div>
                            <h6 class="mb-0 fw-bold">Case Intake</h6>
                            <small class="text-muted">Every submission receives a unique case number.</small>
                        </div>
                    </div>
                </div>
                <div class="grm-card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label class="form-label fw-semibold">Program *</label>
                            <select name="program_id" id="grmProgramSelect" class="form-select" required>
                                <option value="">Select program</option>
                                @foreach ($programs as $program)
                                    <option value="{{ $program->id }}" @selected(old('program_id') === $program->id)>
                                        {{ $program->name }}{{ $program->sector ? ' - ' . $program->sector->name : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label fw-semibold">Grievance Level</label>
                            <select name="level_id" id="grmLevelSelect" class="form-select">
                                <option value="">General grievance</option>
                                @foreach ($levels as $level)
                                    <option value="{{ $level->id }}" data-program-id="{{ $level->program_id }}" @selected(old('level_id') === $level->id)>
                                        {{ $level->name }}{{ $level->program ? ' - ' . $level->program->name : ' - Global' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Channel *</label>
                            <select name="channel" class="form-select" required>
                                @foreach ($channels as $value => $label)
                                    <option value="{{ $value }}" @selected(old('channel', 'portal') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Subject *</label>
                            <input type="text" name="subject" class="form-control" value="{{ old('subject') }}" required maxlength="255">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description *</label>
                            <textarea name="description" rows="6" class="form-control" required>{{ old('description') }}</textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <h6 class="fw-bold mb-0">Complainant Details</h6>
                        <div class="form-check form-switch">
                            <input type="hidden" name="is_anonymous" value="0">
                            <input class="form-check-input" type="checkbox" role="switch" name="is_anonymous" value="1" id="anonymousSwitch" @checked(old('is_anonymous'))>
                            <label class="form-check-label" for="anonymousSwitch">Anonymous case</label>
                        </div>
                    </div>

                    <div class="grm-anonymous-note mb-3" id="anonymousNote">
                        <i class="feather-shield"></i>
                        <span>Anonymous mode is active. Name, email, phone, and visible submitter identity will not be attached to this case.</span>
                    </div>

                    <div class="row g-3 grm-identity" id="complainantFields">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Name</label>
                            <input type="text" name="submitter_name" class="form-control" value="{{ old('submitter_name', auth()->user()?->name) }}" maxlength="255" data-anonymous-field>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email</label>
                            <input type="email" name="submitter_email" class="form-control" value="{{ old('submitter_email', auth()->user()?->email) }}" maxlength="255" data-anonymous-field>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="text" name="submitter_phone" class="form-control" value="{{ old('submitter_phone') }}" maxlength="60" data-anonymous-field>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                        <div>
                            <h6 class="fw-bold mb-1">Supporting Documents</h6>
                            <p class="text-muted small mb-0">Attach evidence such as PDFs, Word files, images, spreadsheets, text files, or ZIP archives.</p>
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm" id="addGrmDocument">
                            <i class="feather-plus me-1"></i> Add Document
                        </button>
                    </div>

                    <div class="d-grid gap-2" id="grmDocumentRows">
                        <div class="grm-document-row" data-document-row>
                            <div>
                                <label class="form-label fw-semibold">Document Name</label>
                                <input type="text" name="supporting_documents[0][title]" class="form-control" placeholder="Example: signed statement">
                            </div>
                            <div>
                                <label class="form-label fw-semibold">File</label>
                                <input type="file" name="supporting_documents[0][file]" class="form-control" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.jpg,.jpeg,.png,.zip">
                            </div>
                            <button type="button" class="btn btn-outline-danger grm-document-remove" data-remove-document title="Remove document">
                                <i class="feather-trash-2"></i>
                            </button>
                        </div>
                    </div>

                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-success px-4">
                            <i class="feather-save me-1"></i> Submit Grievance
                        </button>
                    </div>
                </div>
            </form>

            <aside class="grm-card">
                <div class="grm-card-header">
                    <h6 class="fw-bold mb-0">Case Tracking Flow</h6>
                </div>
                <div class="grm-card-body">
                    <div class="grm-step">
                        <span class="grm-icon"><i class="feather-hash"></i></span>
                        <div>
                            <strong>Case number</strong>
                            <p class="text-muted small mb-0">A unique Grievance Redress Mechanism reference is generated immediately.</p>
                        </div>
                    </div>
                    <div class="grm-step">
                        <span class="grm-icon"><i class="feather-clock"></i></span>
                        <div>
                            <strong>Response clock</strong>
                            <p class="text-muted small mb-0">Deadlines come from escalation settings, selected levels, or the standard Grievance Redress Mechanism response clock.</p>
                        </div>
                    </div>
                    <div class="grm-step">
                        <span class="grm-icon"><i class="feather-bar-chart-2"></i></span>
                        <div>
                            <strong>Email notifications</strong>
                            <p class="text-muted small mb-0">The submitter receives an acknowledgement and the responsible program Grievance Redress Mechanism officer is notified.</p>
                        </div>
                    </div>
                    <div class="grm-step">
                        <span class="grm-icon"><i class="feather-shield"></i></span>
                        <div>
                            <strong>Anonymous option</strong>
                            <p class="text-muted small mb-0">When enabled, identity fields are removed from the case intake and timeline.</p>
                        </div>
                    </div>
                    <div class="grm-step">
                        <span class="grm-icon"><i class="feather-paperclip"></i></span>
                        <div>
                            <strong>Evidence upload</strong>
                            <p class="text-muted small mb-0">Supporting files are stored privately and linked to the generated case number.</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const programSelect = document.getElementById('grmProgramSelect');
            const levelSelect = document.getElementById('grmLevelSelect');
            if (!programSelect || !levelSelect) return;

            const options = Array.from(levelSelect.options).map((option) => ({
                value: option.value,
                text: option.text,
                programId: option.dataset.programId || '',
                selected: option.selected
            }));

            function syncLevelOptions() {
                const programId = programSelect.value;
                const currentValue = levelSelect.value;
                levelSelect.innerHTML = '';

                options.forEach((item) => {
                    if (item.value && item.programId && item.programId !== programId) return;

                    const option = new Option(item.text, item.value);
                    option.dataset.programId = item.programId;
                    option.selected = item.value === currentValue || (!currentValue && item.selected);
                    levelSelect.add(option);
                });
            }

            programSelect.addEventListener('change', syncLevelOptions);
            syncLevelOptions();

            const anonymousSwitch = document.getElementById('anonymousSwitch');
            const complainantFields = document.getElementById('complainantFields');
            const anonymousNote = document.getElementById('anonymousNote');
            const identityInputs = Array.from(document.querySelectorAll('[data-anonymous-field]'));

            function syncAnonymousMode() {
                const isAnonymous = anonymousSwitch && anonymousSwitch.checked;

                complainantFields?.classList.toggle('is-anonymous', isAnonymous);
                anonymousNote?.classList.toggle('is-visible', isAnonymous);

                identityInputs.forEach((input) => {
                    if (isAnonymous) {
                        input.dataset.previousValue = input.value;
                        input.value = '';
                        input.disabled = true;
                    } else {
                        input.disabled = false;
                        if (!input.value && input.dataset.previousValue) {
                            input.value = input.dataset.previousValue;
                        }
                    }
                });
            }

            anonymousSwitch?.addEventListener('change', syncAnonymousMode);
            syncAnonymousMode();

            const documentRows = document.getElementById('grmDocumentRows');
            const addDocumentButton = document.getElementById('addGrmDocument');

            function renumberDocumentRows() {
                if (!documentRows) return;

                Array.from(documentRows.querySelectorAll('[data-document-row]')).forEach((row, index) => {
                    const titleInput = row.querySelector('input[type="text"]');
                    const fileInput = row.querySelector('input[type="file"]');

                    if (titleInput) {
                        titleInput.name = `supporting_documents[${index}][title]`;
                    }

                    if (fileInput) {
                        fileInput.name = `supporting_documents[${index}][file]`;
                    }
                });
            }

            function createDocumentRow() {
                const template = documentRows?.querySelector('[data-document-row]');
                if (!template) return null;

                const row = template.cloneNode(true);
                row.querySelectorAll('input').forEach((input) => {
                    input.value = '';
                });

                return row;
            }

            addDocumentButton?.addEventListener('click', function () {
                const row = createDocumentRow();
                if (!row || !documentRows) return;

                documentRows.appendChild(row);
                renumberDocumentRows();
            });

            documentRows?.addEventListener('click', function (event) {
                const removeButton = event.target.closest('[data-remove-document]');
                if (!removeButton) return;

                const rows = Array.from(documentRows.querySelectorAll('[data-document-row]'));
                const row = removeButton.closest('[data-document-row]');

                if (rows.length <= 1) {
                    row?.querySelectorAll('input').forEach((input) => {
                        input.value = '';
                    });
                    return;
                }

                row?.remove();
                renumberDocumentRows();
            });
        });
    </script>
@endpush
