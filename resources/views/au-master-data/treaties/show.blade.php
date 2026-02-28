@extends('layouts.app')

@section('title', 'Treaty Details')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">{{ $treaty->title }}</h4>
                    <p class="text-muted mb-0">
                        Treaty status tracking across AU member states.
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('settings.au.treaties.index') }}" class="btn btn-outline-secondary">
                        <i class="feather-arrow-left me-1"></i> Back
                    </a>
                    @can('treaties.edit')
                        <a href="{{ route('settings.au.treaties.edit', $treaty->id) }}" class="btn btn-primary">
                            <i class="feather-edit me-1"></i> Edit
                        </a>
                    @endcan
                </div>
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if (session('warning'))
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    {{ session('warning') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h5 class="mb-3">Treaty Information</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="text-muted small">Short Title</div>
                                    <div class="fw-semibold">{{ $treaty->short_title ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Reference Code</div>
                                    <div class="fw-semibold">{{ $treaty->reference_code ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Adoption Date</div>
                                    <div class="fw-semibold">{{ optional($treaty->adoption_date)->format('d M Y') ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Entry Into Force</div>
                                    <div class="fw-semibold">
                                        {{ optional($treaty->entry_into_force_date)->format('d M Y') ?: '—' }}
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small">Description</div>
                                    <div class="fw-semibold" style="white-space: pre-wrap;">{{ $treaty->description ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Overview / Background</div>
                                    <div class="fw-semibold" style="white-space: pre-wrap;">{{ $treaty->overview ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Key Provisions</div>
                                    <div class="fw-semibold" style="white-space: pre-wrap;">{{ $treaty->key_provisions ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Implementation Framework</div>
                                    <div class="fw-semibold" style="white-space: pre-wrap;">{{ $treaty->implementation_framework ?: '—' }}</div>
                                </div>
                                <div class="col-md-6">
                                    <div class="text-muted small">Monitoring and Reporting</div>
                                    <div class="fw-semibold" style="white-space: pre-wrap;">{{ $treaty->monitoring_and_reporting ?: '—' }}</div>
                                </div>
                                <div class="col-12">
                                    <div class="text-muted small">Read More Link</div>
                                    <div class="fw-semibold">
                                        @if($treaty->read_more_url)
                                            <a href="{{ $treaty->read_more_url }}" target="_blank" rel="noopener noreferrer">
                                                {{ $treaty->read_more_url }}
                                            </a>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card shadow-sm border-0 h-100">
                        <div class="card-body">
                            <h5 class="mb-3">Status Summary</h5>
                            @php
                                $signedCount = $treaty->memberStateStatuses->where('is_signed', true)->count();
                                $ratifiedCount = $treaty->memberStateStatuses->where('is_ratified', true)->count();
                                $originalSubmittedCount = $treaty->memberStateStatuses->where('is_original_submitted', true)->count();
                            @endphp
                            <div class="d-flex justify-content-between border rounded p-2 mb-2">
                                <span>Total Member States</span>
                                <strong>{{ $memberStates->count() }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border rounded p-2 mb-2">
                                <span>Signed</span>
                                <strong class="text-info">{{ $signedCount }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border rounded p-2">
                                <span>Ratified</span>
                                <strong class="text-primary">{{ $ratifiedCount }}</strong>
                            </div>
                            <div class="d-flex justify-content-between border rounded p-2 mt-2">
                                <span>Original Submitted to AU Legal</span>
                                <strong class="text-success">{{ $originalSubmittedCount }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0">Treaty Supporting Documents</h5>
                </div>
                <div class="card-body">
                    @if ($treaty->supportingDocuments->count())
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="60">#</th>
                                        <th>Title</th>
                                        <th width="180">Type</th>
                                        <th>File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($treaty->supportingDocuments as $document)
                                        <tr>
                                            <td>{{ $loop->iteration }}</td>
                                            <td>{{ $document->title ?: '—' }}</td>
                                            <td>{{ $document->document_type ?: '—' }}</td>
                                            <td>
                                                <a href="{{ route('treaties.supporting-documents.download', $document->id) }}?download=1"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    <i class="feather-download me-1"></i> {{ $document->file_name }}
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-muted">No supporting documents uploaded yet.</div>
                    @endif
                </div>
            </div>

            <div class="card shadow-sm border-0 mt-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Member State Sign / Ratify Matrix</h5>
                    <small class="text-muted">Signed code can be verified after signing; ratified code can be verified after ratification.</small>
                </div>
                <div class="card-body">
                    @can('treaties.edit')
                        <form method="POST"
                            action="{{ route('settings.au.treaties.member-state-statuses.sync', $treaty->id) }}">
                            @csrf
                    @endcan
                    <div class="table-responsive">
                        <table class="table table-striped align-middle">
                            <thead>
                                <tr>
                                    <th>Member State</th>
                                    <th width="160">Status</th>
                                    <th width="140">Signed At</th>
                                    <th width="140">Ratified At</th>
                                    <th width="170">Original Submitted At</th>
                                    <th width="180">Signed Proof</th>
                                    <th width="180">Ratified Proof</th>
                                    <th width="180">Original Submission</th>
                                    <th width="220">Signed Service Code</th>
                                    <th width="220">Ratified Service Code</th>
                                    <th width="280">AU Legal Code Entry</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($memberStates as $memberState)
                                    @php
                                        $stateStatus = $statusByState->get($memberState->id);
                                        $selectedStatus = 'none';
                                        if ($stateStatus?->is_ratified) {
                                            $selectedStatus = 'ratified';
                                        } elseif ($stateStatus?->is_signed) {
                                            $selectedStatus = 'signed';
                                        }
                                        if ($stateStatus?->is_original_submitted || $stateStatus?->original_document_path) {
                                            $selectedStatus = 'original_submitted';
                                        }
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="fw-semibold">{{ $memberState->name }}</div>
                                            <small class="text-muted">{{ $memberState->code ?: '—' }}</small>
                                        </td>
                                        <td>
                                            @can('treaties.edit')
                                                <select name="status[{{ $memberState->id }}]" class="form-select form-select-sm">
                                                    <option value="none" {{ $selectedStatus === 'none' ? 'selected' : '' }}>
                                                        None
                                                    </option>
                                                    <option value="signed" {{ $selectedStatus === 'signed' ? 'selected' : '' }}>
                                                        Signed
                                                    </option>
                                                    <option value="ratified"
                                                        {{ $selectedStatus === 'ratified' ? 'selected' : '' }}>
                                                        Ratified
                                                    </option>
                                                    <option value="original_submitted"
                                                        {{ $selectedStatus === 'original_submitted' ? 'selected' : '' }}>
                                                        Original Submitted
                                                    </option>
                                                </select>
                                            @else
                                                <span
                                                    class="badge bg-secondary">{{ ucfirst(str_replace('_', ' ', $selectedStatus)) }}</span>
                                            @endcan
                                        </td>
                                        <td>
                                            {{ optional($stateStatus?->signed_at)->format('d M Y') ?: '—' }}
                                        </td>
                                        <td>
                                            {{ optional($stateStatus?->ratified_at)->format('d M Y') ?: '—' }}
                                        </td>
                                        <td>
                                            {{ optional($stateStatus?->original_submitted_at)->format('d M Y') ?: '—' }}
                                        </td>
                                        <td>
                                            @if ($stateStatus?->signed_document_path)
                                                <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $stateStatus->id, 'type' => 'signed']) }}?download=1"
                                                    class="btn btn-sm btn-outline-info">
                                                    <i class="feather-download me-1"></i> Download
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($stateStatus?->ratified_document_path)
                                                <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $stateStatus->id, 'type' => 'ratified']) }}?download=1"
                                                    class="btn btn-sm btn-outline-primary">
                                                    <i class="feather-download me-1"></i> Download
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($stateStatus?->original_document_path)
                                                <a href="{{ route('treaty-statuses.documents.download', ['treatyStatus' => $stateStatus->id, 'type' => 'original']) }}?download=1"
                                                    class="btn btn-sm btn-outline-success">
                                                    <i class="feather-download me-1"></i> Download
                                                </a>
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($stateStatus?->signed_service_code)
                                                <code>{{ $stateStatus->signed_service_code }}</code>
                                                @if ($stateStatus?->signed_service_code_verified_at)
                                                    <div class="small text-success mt-1">
                                                        Verified {{ optional($stateStatus->signed_service_code_verified_at)->format('d M Y H:i') }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($stateStatus?->ratified_service_code)
                                                <code>{{ $stateStatus->ratified_service_code }}</code>
                                                @if ($stateStatus?->ratified_service_code_verified_at)
                                                    <div class="small text-success mt-1">
                                                        Verified {{ optional($stateStatus->ratified_service_code_verified_at)->format('d M Y H:i') }}
                                                    </div>
                                                @endif
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>
                                            @can('treaties.edit')
                                                @if ($stateStatus?->is_signed)
                                                    <input type="text" name="proof_signed_code[{{ $memberState->id }}]"
                                                        value="{{ old('proof_signed_code.' . $memberState->id) }}"
                                                        class="form-control form-control-sm mb-1"
                                                        placeholder="Signed code (XXXX-XXXX-XXXX-XXXX)">
                                                    @if ($stateStatus?->signed_service_code_verified_at)
                                                        <div class="small text-success mb-1">
                                                            Signed code verified
                                                            @if ($stateStatus?->signedServiceCodeVerifiedByUser)
                                                                by {{ $stateStatus->signedServiceCodeVerifiedByUser->name }}
                                                            @endif
                                                        </div>
                                                    @elseif($stateStatus?->signed_service_code)
                                                        <div class="small text-warning mb-1">Signed code verification pending.</div>
                                                    @endif
                                                @else
                                                    <div class="small text-muted mb-1">Signed code entry is available after signing.</div>
                                                @endif

                                                @if ($stateStatus?->is_ratified)
                                                    <input type="text" name="proof_ratified_code[{{ $memberState->id }}]"
                                                        value="{{ old('proof_ratified_code.' . $memberState->id) }}"
                                                        class="form-control form-control-sm mb-1"
                                                        placeholder="Ratified code (XXXX-XXXX-XXXX-XXXX)">
                                                    @if ($stateStatus?->ratified_service_code_verified_at)
                                                        <div class="small text-success mb-1">
                                                            Ratified code verified
                                                            @if ($stateStatus?->ratifiedServiceCodeVerifiedByUser)
                                                                by {{ $stateStatus->ratifiedServiceCodeVerifiedByUser->name }}
                                                            @endif
                                                        </div>
                                                    @elseif($stateStatus?->ratified_service_code)
                                                        <div class="small text-warning mb-1">Ratified code verification pending.</div>
                                                    @endif
                                                @else
                                                    <div class="small text-muted mb-1">Ratified code entry is available after ratification.</div>
                                                @endif

                                                @if (
                                                    $stateStatus?->is_original_submitted &&
                                                        $stateStatus?->signed_service_code_verified_at &&
                                                        $stateStatus?->ratified_service_code_verified_at)
                                                    <div class="small text-success">
                                                        Final step complete
                                                        @if ($stateStatus->ratifiedServiceCodeVerifiedByUser)
                                                            by {{ $stateStatus->ratifiedServiceCodeVerifiedByUser->name }}
                                                        @endif
                                                    </div>
                                                @elseif($stateStatus?->is_original_submitted)
                                                    <div class="small text-warning">Original submission exists; awaiting required code verification.</div>
                                                @elseif($stateStatus?->is_ratified)
                                                    <div class="small text-muted">Original submission file not uploaded yet.</div>
                                                @else
                                                    <div class="small text-muted">Proceed with signing and ratification steps first.</div>
                                                @endif
                                            @else
                                                @if ($stateStatus?->signed_service_code_verified_at && $stateStatus?->ratified_service_code_verified_at)
                                                    <span class="badge bg-success">Signed + Ratified Codes Verified</span>
                                                @elseif($stateStatus?->signed_service_code_verified_at)
                                                    <span class="badge bg-info text-dark">Signed Code Verified</span>
                                                @elseif($stateStatus?->ratified_service_code_verified_at)
                                                    <span class="badge bg-primary">Ratified Code Verified</span>
                                                @elseif($stateStatus?->original_document_path)
                                                    <span class="badge bg-warning text-dark">Pending Verification</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            @endcan
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @can('treaties.edit')
                        <div class="text-end mt-3">
                            <button type="submit" class="btn btn-success">
                                <i class="feather-save me-1"></i> Save Status Changes
                            </button>
                        </div>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </main>
@endsection
