@extends('layouts.app')

@push('styles')
    <style>
        .negotiation-page .hero-card {
            background: linear-gradient(120deg, #0f172a 0%, #1e293b 45%, #0ea5e9 100%);
            color: #fff;
            border: none;
            border-radius: 18px;
        }

        .negotiation-page .hero-card p {
            color: rgba(255, 255, 255, 0.78);
        }

        .negotiation-page .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
        }

        .negotiation-page .stat-title {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .negotiation-page .stat-value {
            font-size: 1.35rem;
            font-weight: 700;
            color: #0f172a;
        }

        .negotiation-page .section-card {
            border: 1px solid #e2e8f0;
            border-radius: 18px;
            box-shadow: 0 18px 30px rgba(15, 23, 42, 0.08);
        }

        .negotiation-page .badge-soft {
            background: #eef2ff;
            color: #4338ca;
            padding: 6px 12px;
            border-radius: 999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .negotiation-page .negotiation-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 18px;
            background: #ffffff;
        }

        .negotiation-page .file-chip {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #0f172a;
            font-size: 0.75rem;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container negotiation-page">
        <div class="card hero-card mb-4">
            <div class="card-body d-flex flex-column flex-lg-row justify-content-between align-items-start">
                <div>
                    <h4 class="fw-bold mb-1">Contract Negotiation</h4>
                    <p class="mb-0">
                        {{ $procurement->title }} ·
                        <span class="badge-soft">{{ $procurement->reference_no ?? 'N/A' }}</span>
                    </p>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
                    <span class="badge bg-light text-dark px-3 py-2 text-capitalize">
                        Status: {{ $procurement->status ?? 'draft' }}
                    </span>
                    <a href="{{ route('procurement.contract-negotiations.index') }}" class="btn btn-light btn-sm">
                        <i class="feather-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
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

        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Total Submissions</div>
                    <div class="stat-value">{{ $submissions->count() }}</div>
                    <div class="text-muted small">Vendor applications received</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Negotiations</div>
                    <div class="stat-value">{{ $negotiations->count() }}</div>
                    <div class="text-muted small">Active & historical records</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card p-3 h-100">
                    <div class="stat-title">Agreed</div>
                    <div class="stat-value">{{ $negotiations->where('status', 'agreed')->count() }}</div>
                    <div class="text-muted small">
                        Estimated Budget:
                        {{ $procurement->estimated_budget ? number_format($procurement->estimated_budget, 2) : 'N/A' }}
                    </div>
                </div>
            </div>
        </div>

        <div class="card section-card mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-semibold">Start Negotiation</h6>
            </div>
            <div class="card-body">
                @if ($submissions->isEmpty())
                    <div class="alert alert-warning mb-0">
                        No vendor submissions found for this procurement yet.
                    </div>
                @else
                    <form method="POST" action="{{ route('procurement.contract-negotiations.store', $procurement) }}"
                        enctype="multipart/form-data" class="row g-3">
                        @csrf
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Vendor Submission</label>
                            <select name="submission_id" class="form-control" required>
                                <option value="">-- Select submission --</option>
                                @foreach ($submissions as $submission)
                                    <option value="{{ $submission->id }}">
                                        {{ $submission->procurement_submission_code }} -
                                        {{ $submission->submitter?->name ?? 'Vendor' }}
                                        ({{ $submission->submitter?->email ?? 'no email' }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Proposed Amount</label>
                            <input type="number" step="0.01" name="proposed_amount" class="form-control" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Attach Documents</label>
                            <input type="file" name="documents[]" class="form-control" multiple>
                            <small class="text-muted">PDF, DOCX, XLSX, JPG, PNG</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Negotiation Notes</label>
                            <textarea name="notes" rows="3" class="form-control"
                                placeholder="Add negotiation notes (optional)"></textarea>
                        </div>
                        <div class="col-12 text-end">
                            <button class="btn btn-primary">
                                <i class="feather-plus-circle me-1"></i> Start Negotiation
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <div class="card section-card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Negotiation Records</h6>
                <span class="badge bg-secondary">{{ $negotiations->count() }} Records</span>
            </div>
            <div class="card-body">
                @forelse ($negotiations as $negotiation)
                    <div class="negotiation-card mb-3">
                        <div class="row g-3 align-items-start">
                            <div class="col-md-4">
                                <div class="text-muted small">Vendor</div>
                                <div class="fw-semibold">{{ $negotiation->submission?->submitter?->name ?? 'Vendor' }}</div>
                                <div class="small text-muted">{{ $negotiation->submission?->submitter?->email ?? 'N/A' }}</div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Status</div>
                                @php
                                    $negotiationBadge = [
                                        'in_progress' => 'primary',
                                        'agreed' => 'success',
                                        'cancelled' => 'secondary',
                                        'terminated' => 'danger',
                                    ];
                                @endphp
                                <span class="badge bg-{{ $negotiationBadge[$negotiation->status] ?? 'secondary' }}">
                                    {{ ucfirst(str_replace('_', ' ', $negotiation->status)) }}
                                </span>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Proposed Amount</div>
                                <div class="fw-semibold">
                                    {{ $negotiation->proposed_amount ? number_format($negotiation->proposed_amount, 2) : 'N/A' }}
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="text-muted small">Agreed Amount</div>
                                <div class="fw-semibold">
                                    {{ $negotiation->agreed_amount ? number_format($negotiation->agreed_amount, 2) : 'N/A' }}
                                </div>
                            </div>
                            <div class="col-md-2 text-md-end">
                                <div class="text-muted small">Submitted</div>
                                <div class="fw-semibold">{{ $negotiation->created_at?->format('d M Y') ?? 'N/A' }}</div>
                            </div>
                        </div>

                        @if ($negotiation->notes)
                            <div class="mt-3 text-muted small">
                                <strong>Notes:</strong> {{ $negotiation->notes }}
                            </div>
                        @endif

                        @if ($negotiation->termination_reason)
                            <div class="alert alert-danger mt-3 mb-0">
                                <strong>Termination Reason:</strong> {{ $negotiation->termination_reason }}
                            </div>
                        @endif

                        <div class="mt-3">
                            <div class="text-muted small mb-2">Documents</div>
                            @if ($negotiation->documents->isEmpty())
                                <div class="text-muted small">No documents uploaded yet.</div>
                            @else
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($negotiation->documents as $document)
                                        <a class="file-chip"
                                            href="{{ route('procurement.contract-negotiations.documents.download', [$procurement, $negotiation, $document]) }}"
                                            target="_blank">
                                            <i class="feather-paperclip me-1"></i>
                                            {{ $document->file_name ?? 'Attachment' }}
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="mt-3">
                            <form method="POST"
                                action="{{ route('procurement.contract-negotiations.documents.store', [$procurement, $negotiation]) }}"
                                enctype="multipart/form-data" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Add Documents</label>
                                    <input type="file" name="documents[]" class="form-control" multiple required>
                                </div>
                                <div class="col-md-3">
                                    <button class="btn btn-outline-primary w-100">
                                        <i class="feather-upload me-1"></i> Upload
                                    </button>
                                </div>
                            </form>
                        </div>

                        @if ($negotiation->status !== 'agreed' && $negotiation->status !== 'terminated')
                            <div class="mt-3">
                                <form method="POST"
                                    action="{{ route('procurement.contract-negotiations.agree', [$procurement, $negotiation]) }}"
                                    class="row g-2 align-items-end">
                                    @csrf
                                    <div class="col-md-3">
                                        <label class="form-label fw-semibold">Approved Amount</label>
                                        <input type="number" step="0.01" name="agreed_amount" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-success w-100" onclick="return confirm('Approve this negotiation?')">
                                            <i class="feather-check-circle me-1"></i> Approve Agreement
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        @if ($procurement->status === 'awarded' && $negotiation->status === 'agreed')
                            <div class="mt-3">
                                <form method="POST"
                                    action="{{ route('procurement.contract-negotiations.terminate', [$procurement, $negotiation]) }}"
                                    class="row g-2 align-items-end">
                                    @csrf
                                    <div class="col-md-6">
                                        <label class="form-label fw-semibold">Termination Reason</label>
                                        <input type="text" name="termination_reason" class="form-control" required>
                                    </div>
                                    <div class="col-md-3">
                                        <button class="btn btn-danger w-100"
                                            onclick="return confirm('Terminate this contract and reopen the procurement?')">
                                            <i class="feather-x-circle me-1"></i> Terminate Contract
                                        </button>
                                    </div>
                                </form>
                            </div>
                        @endif

                        @if ($procurement->status === 'closed' && $negotiation->status === 'agreed')
                            <div class="mt-3">
                                <form method="POST" action="{{ route('statusProcurement.award', $procurement) }}">
                                    @csrf
                                    <button class="btn btn-success"
                                        onclick="return confirm('Award this procurement to this vendor?')">
                                        <i class="feather-award me-1"></i> Award This Vendor
                                    </button>
                                </form>
                            </div>
                        @endif

                        @if ($negotiation->status === 'agreed')
                            <div class="alert alert-info mt-3 mb-0">
                                Vendor invoices will be submitted after award and processed under Budget Execution.
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="alert alert-info mb-0">No negotiations have been created yet.</div>
                @endforelse
            </div>
        </div>
    </div>
@endsection
