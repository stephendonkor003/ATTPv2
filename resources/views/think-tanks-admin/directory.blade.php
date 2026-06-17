@extends('layouts.app')

@section('title', 'Think Tank Directory')

@push('styles')
    <style>
        .tt-directory-hero {
            border: 0;
            border-radius: 10px;
            background: #0f172a;
            color: #ffffff;
        }

        .tt-directory-hero .kicker {
            color: #facc15;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .tt-directory-hero .copy {
            color: #e2e8f0;
            max-width: 760px;
            line-height: 1.65;
        }

        .tt-directory-hero .hero-title {
            color: #ffffff;
            font-weight: 900;
        }

        .tt-directory-stat {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.06);
        }

        .tt-directory-stat .label {
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
        }

        .tt-directory-stat .value {
            color: #0f172a;
            font-size: 1.24rem;
            font-weight: 900;
        }

        .tt-directory-table-card {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .tt-db-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.3rem 0.55rem;
            background: #e0f2fe;
            color: #075985;
            font-size: 0.72rem;
            font-weight: 900;
        }

        .tt-db-pill.linked {
            background: #dcfce7;
            color: #166534;
        }

        .tt-db-pill.missing {
            background: #fef3c7;
            color: #92400e;
        }

        .tt-directory-filter {
            display: grid;
            grid-template-columns: minmax(230px, 1fr) minmax(180px, .7fr) minmax(130px, .45fr) minmax(130px, .45fr) minmax(130px, .45fr) auto;
            gap: 0.65rem;
            align-items: end;
        }

        .tt-directory-filter label {
            color: #334155;
            font-size: 0.72rem;
            font-weight: 850;
        }

        @media (max-width: 1100px) {
            .tt-directory-filter {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 700px) {
            .tt-directory-filter {
                grid-template-columns: 1fr;
            }
        }

        .tt-create-modal .modal-dialog,
        .tt-update-modal .modal-dialog {
            max-width: min(980px, calc(100vw - 2rem));
        }

        .tt-create-modal .modal-content,
        .tt-update-modal .modal-content {
            border: 0;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.28);
        }

        .tt-create-modal .modal-header,
        .tt-update-modal .modal-header {
            background: #0f172a;
            color: #ffffff;
            border: 0;
        }

        .tt-create-modal .modal-title,
        .tt-update-modal .modal-title {
            color: #ffffff;
            font-weight: 900;
        }

        .tt-create-modal .modal-subtitle,
        .tt-update-modal .modal-subtitle {
            color: #facc15;
            font-weight: 800;
        }

        .tt-create-modal .btn-close,
        .tt-update-modal .btn-close {
            filter: invert(1);
        }

        .tt-modal-note {
            border-left: 4px solid #0ea5e9;
            border-radius: 8px;
            background: #f0f9ff;
            color: #0f172a;
            padding: 0.9rem 1rem;
        }

        .tt-finance-trail {
            min-width: 230px;
        }

        .tt-finance-trail-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.35rem;
            align-items: center;
            margin-bottom: 0.35rem;
        }

        .tt-finance-trail-row:last-child {
            margin-bottom: 0;
        }

        .tt-finance-chip {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 34px;
            border-radius: 999px;
            padding: 0.18rem 0.45rem;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.68rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .tt-finance-trail a {
            color: #0f766e;
            font-size: 0.75rem;
            font-weight: 800;
            text-decoration: none;
        }

        .tt-finance-trail a:hover {
            color: #0f172a;
            text-decoration: underline;
        }
    </style>
@endpush

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-users text-primary me-2"></i>Think Tank Directory</h4>
                <p class="text-muted mb-0">Manage think tank profiles, consortium links, portal access, and operating allocations.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-light btn-sm border">
                    <i class="feather-send me-1"></i> Funding Dashboard
                </a>
                @can('think_tanks.directory.create')
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#createThinkTankModal">
                        <i class="feather-plus me-1"></i> Create Think Tank
                    </button>
                @endcan
            </div>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if (session('error')) <div class="alert alert-danger">{{ session('error') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

        <div class="card tt-directory-hero mb-4">
            <div class="card-body p-4">
                <div class="kicker mb-2">Think Tank Registry</div>
                <h3 class="hero-title mb-2">A single directory for every supported think tank.</h3>
                <p class="copy mb-0">
                    Use this page to keep institutional profiles clean, connect each think tank to the system think tank database,
                    link each profile to its consortium, and verify portal access for reporting, procurement, and payment receipt confirmation.
                </p>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Think Tanks', 'value' => number_format($summary['total'])],
                ['label' => 'System Dataset', 'value' => number_format($summary['system_dataset'])],
                ['label' => 'Linked to System DB', 'value' => number_format($summary['dataset_linked'])],
                ['label' => 'Active Profiles', 'value' => number_format($summary['active'])],
                ['label' => 'Portal Linked', 'value' => number_format($summary['portal_linked'])],
                ['label' => 'Approved Ops Amount', 'value' => 'USD ' . number_format($summary['approved_ops'], 2)],
                ['label' => 'Linked PO Amount', 'value' => 'USD ' . number_format($summary['linked_po_amount'], 2)],
                ['label' => 'Paid from POs', 'value' => 'USD ' . number_format($summary['transferred'], 2)],
            ] as $stat)
                <div class="col-md-6 col-xl">
                    <div class="card tt-directory-stat h-100">
                        <div class="card-body">
                            <div class="label">{{ $stat['label'] }}</div>
                            <div class="value">{{ $stat['value'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="card tt-directory-table-card">
            <div class="card-header bg-white border-0">
                <form>
                    <div class="me-auto">
                        <h5 class="mb-1 fw-bold">Directory Index</h5>
                        <div class="text-muted small">Search operational profiles backed by the system think tank dataset.</div>
                    </div>
                    <div class="tt-directory-filter mt-3">
                        <div>
                            <label class="form-label mb-1">Search</label>
                            <input class="form-control form-control-sm" name="q" value="{{ request('q') }}" placeholder="Name, country, email, OTTD ID">
                        </div>
                        <div>
                            <label class="form-label mb-1">Consortium</label>
                            <select class="form-select form-select-sm" name="consortium_id">
                                <option value="">All consortia</option>
                                @foreach ($consortia as $consortium)
                                    <option value="{{ $consortium->id }}" @selected(request('consortium_id') === $consortium->id)>{{ $consortium->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1">Status</label>
                            <select class="form-select form-select-sm" name="status">
                                <option value="">All statuses</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1">Portal</label>
                            <select class="form-select form-select-sm" name="portal">
                                <option value="">All</option>
                                <option value="linked" @selected(request('portal') === 'linked')>Linked</option>
                                <option value="unlinked" @selected(request('portal') === 'unlinked')>Not linked</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label mb-1">System DB</label>
                            <select class="form-select form-select-sm" name="dataset">
                                <option value="">All</option>
                                <option value="linked" @selected(request('dataset') === 'linked')>Linked</option>
                                <option value="unlinked" @selected(request('dataset') === 'unlinked')>Not linked</option>
                            </select>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button class="btn btn-light btn-sm border"><i class="feather-search me-1"></i> Search</button>
                            @if (request()->hasAny(['q', 'consortium_id', 'status', 'portal', 'dataset']))
                                <a href="{{ route('think-tanks-admin.directory') }}" class="btn btn-light btn-sm border">Clear</a>
                            @endif
                        </div>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table id="thinkTankDirectoryTable" class="table table-hover align-middle mb-0 data-table">
                        <thead class="table-light">
                            <tr>
                                <th>Think Tank</th>
                                <th>System DB</th>
                                <th>Consortium</th>
                                <th>PO Linked Amount</th>
                                <th>Paid from POs</th>
                                <th>Finance Trail</th>
                                <th>Outputs</th>
                                <th>Portal</th>
                                <th>Status</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($thinkTanks as $thinkTank)
                                @php
                                    $profileAllocated = (float) $thinkTank->budget_allocated + (float) $thinkTank->fund_allocations_sum_amount_allocated;
                                    $purchaseOrders = $thinkTank->purchaseOrders ?? collect();
                                    $purchaseRequests = $purchaseOrders
                                        ->map(fn ($purchaseOrder) => $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest)
                                        ->filter()
                                        ->unique('id')
                                        ->values();
                                    $paidDisbursements = $thinkTank->transferDisbursements ?? collect();
                                    $linkedPoAmount = (float) $thinkTank->purchase_orders_sum_amount;
                                    $paidAmount = (float) $thinkTank->paid_transfer_disbursements_sum_amount;
                                @endphp
                                <tr>
                                    <td>
                                        <strong>{{ $thinkTank->name }}</strong>
                                        <br><span class="text-muted small">{{ $thinkTank->country ?: '-' }} | {{ str_replace('_', ' ', $thinkTank->role) }}</span>
                                        @if ($thinkTank->email)
                                            <br><span class="text-muted small">{{ $thinkTank->email }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($thinkTank->thinkDataset)
                                            <span class="tt-db-pill linked"><i class="feather-database"></i> Linked</span>
                                            <div class="text-muted small mt-1">{{ $thinkTank->thinkDataset->ottd_id ?: 'System dataset' }}</div>
                                            @if ($thinkTank->thinkDataset->website)
                                                <a class="small" href="{{ $thinkTank->thinkDataset->website }}" target="_blank" rel="noopener">Website</a>
                                            @endif
                                        @else
                                            <span class="tt-db-pill missing"><i class="feather-alert-triangle"></i> Not linked</span>
                                            <div class="text-muted small mt-1">Select a record from think_datasets.</div>
                                        @endif
                                    </td>
                                    <td>{{ $thinkTank->consortium?->name ?? '-' }}</td>
                                    <td>
                                        <strong>USD {{ number_format($linkedPoAmount, 2) }}</strong>
                                        <div class="text-muted small">Profile: USD {{ number_format($profileAllocated, 2) }}</div>
                                    </td>
                                    <td>
                                        <strong>USD {{ number_format($paidAmount, 2) }}</strong>
                                        <div class="text-muted small">{{ number_format($thinkTank->paid_transfer_disbursements_count) }} paid receipt(s)</div>
                                    </td>
                                    <td>
                                        <div class="tt-finance-trail">
                                            <div class="tt-finance-trail-row">
                                                <span class="tt-finance-chip">PR</span>
                                                @forelse ($purchaseRequests->take(2) as $purchaseRequest)
                                                    <a href="{{ route('finance.purchase-requests.show', $purchaseRequest) }}">{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</a>
                                                @empty
                                                    <span class="text-muted small">No linked PR</span>
                                                @endforelse
                                                @if ($purchaseRequests->count() > 2)
                                                    <span class="text-muted small">+{{ $purchaseRequests->count() - 2 }}</span>
                                                @endif
                                            </div>
                                            <div class="tt-finance-trail-row">
                                                <span class="tt-finance-chip">PO</span>
                                                @forelse ($purchaseOrders->take(2) as $purchaseOrder)
                                                    <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}">{{ $purchaseOrder->reference_no ?: 'Purchase Order' }}</a>
                                                @empty
                                                    <span class="text-muted small">No linked PO</span>
                                                @endforelse
                                                @if ($purchaseOrders->count() > 2)
                                                    <span class="text-muted small">+{{ $purchaseOrders->count() - 2 }}</span>
                                                @endif
                                            </div>
                                            <div class="tt-finance-trail-row">
                                                <span class="tt-finance-chip">Pay</span>
                                                @forelse ($paidDisbursements->take(2) as $disbursement)
                                                    <a href="{{ route('procurement.disbursements.show', $disbursement) }}">{{ $disbursement->reference_no ?: 'Disbursement' }}</a>
                                                @empty
                                                    <span class="text-muted small">No paid disbursement</span>
                                                @endforelse
                                                @if ($paidDisbursements->count() > 2)
                                                    <span class="text-muted small">+{{ $paidDisbursements->count() - 2 }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="small">Reports: <strong>{{ number_format($thinkTank->reports_count) }}</strong></div>
                                        <div class="small">Research: <strong>{{ number_format($thinkTank->research_outputs_count) }}</strong></div>
                                        <div class="small">Procurement: <strong>{{ number_format($thinkTank->procurements_count) }}</strong></div>
                                    </td>
                                    <td>
                                        @if ($thinkTank->portalUser)
                                            <span class="badge bg-success">Linked</span>
                                            <div class="text-muted small mt-1">{{ $thinkTank->portalUser->email }}</div>
                                        @else
                                            <span class="badge bg-warning text-dark">Not linked</span>
                                        @endif
                                    </td>
                                    <td><span class="badge bg-light text-dark">{{ ucfirst($thinkTank->status) }}</span></td>
                                    <td class="text-end">
                                        @can('think_tanks.directory.edit')
                                            <button type="button" class="btn btn-sm btn-light border" data-bs-toggle="modal" data-bs-target="#updateThinkTankModal{{ str_replace('-', '', $thinkTank->id) }}">
                                                <i class="feather-edit-2 me-1"></i> Update
                                            </button>
                                        @endcan
                                        <a class="btn btn-sm btn-primary" href="{{ route('think-tanks-admin.show', $thinkTank) }}">
                                            <i class="feather-eye me-1"></i> Profile
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                {{-- DataTables will render the empty state. --}}
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($thinkTanks->isEmpty())
                    <div class="text-center text-muted py-4">No think tanks found.</div>
                @endif
            </div>
        </div>
    </div>

    @can('think_tanks.directory.create')
        <div class="modal fade tt-create-modal" id="createThinkTankModal" tabindex="-1" aria-labelledby="createThinkTankModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <div>
                            <div class="small modal-subtitle">Create directory profile</div>
                            <h5 class="modal-title" id="createThinkTankModalLabel">New Think Tank</h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form method="POST" action="{{ route('think-tanks-admin.store') }}">
                        @csrf
                        <div class="modal-body">
                            <div class="tt-modal-note mb-4">
                                If an email is provided, the system creates or links a Think Tank User account so the institution can access the portal.
                            </div>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">System Think Tank Database Record</label>
                                    <select class="form-select" name="think_dataset_id" data-tt-dataset-select>
                                        <option value="">Create without system dataset link</option>
                                        @foreach ($thinkDatasets as $dataset)
                                            <option value="{{ $dataset->id }}" @selected(old('think_dataset_id') === $dataset->id)>
                                                {{ $dataset->tt_name_en ?: 'Unnamed think tank' }}
                                                @if ($dataset->country) | {{ $dataset->country }} @endif
                                                @if ($dataset->ottd_id) | {{ $dataset->ottd_id }} @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">This links the directory profile to the master `think_datasets` table used by the system.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Think Tank Name</label>
                                    <input class="form-control" name="name" value="{{ old('name') }}" data-tt-autofill="name" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Consortium</label>
                                    <select class="form-select" name="consortium_id" required>
                                        <option value="">Select consortium</option>
                                        @foreach ($consortia as $consortium)
                                            <option value="{{ $consortium->id }}" @selected(old('consortium_id') === $consortium->id)>{{ $consortium->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Role</label>
                                    <select class="form-select" name="role" required>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role }}" @selected(old('role', 'member') === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        @foreach (['active', 'inactive', 'suspended', 'closed'] as $status)
                                            <option value="{{ $status }}" @selected(old('status', 'active') === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Country</label>
                                    <input class="form-control" name="country" value="{{ old('country') }}" data-tt-autofill="country">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Email / Portal Login</label>
                                    <input class="form-control" type="email" name="email" value="{{ old('email') }}" data-tt-autofill="email">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Approved Operating Amount</label>
                                    <input class="form-control" type="number" step="0.01" min="0" name="budget_allocated" value="{{ old('budget_allocated', 0) }}">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">AU SAP Vendor Number</label>
                                    <input class="form-control" name="au_sap_vendor_number" value="{{ old('au_sap_vendor_number') }}">
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light">
                            <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary"><i class="feather-save me-1"></i> Save Think Tank</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endcan

    @can('think_tanks.directory.edit')
        @foreach ($thinkTanks as $thinkTank)
            @php $updateModalId = 'updateThinkTankModal' . str_replace('-', '', $thinkTank->id); @endphp
            <div class="modal fade tt-update-modal" id="{{ $updateModalId }}" tabindex="-1" aria-labelledby="{{ $updateModalId }}Label" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <div class="small modal-subtitle">Update directory profile</div>
                                <h5 class="modal-title" id="{{ $updateModalId }}Label">{{ $thinkTank->name }}</h5>
                            </div>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <form method="POST" action="{{ route('think-tanks-admin.update', $thinkTank) }}">
                            @csrf
                            @method('PUT')
                            <div class="modal-body">
                                <div class="tt-modal-note mb-4">
                                    Update profile details carefully. Portal access remains linked through the selected email/user relationship.
                                </div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">System Think Tank Database Record</label>
                                        <select class="form-select" name="think_dataset_id" data-tt-dataset-select>
                                            <option value="">No system dataset link</option>
                                            @foreach ($thinkDatasets as $dataset)
                                                <option value="{{ $dataset->id }}" @selected(old('think_dataset_id', $thinkTank->think_dataset_id) === $dataset->id)>
                                                    {{ $dataset->tt_name_en ?: 'Unnamed think tank' }}
                                                    @if ($dataset->country) | {{ $dataset->country }} @endif
                                                    @if ($dataset->ottd_id) | {{ $dataset->ottd_id }} @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <div class="form-text">Use the master `think_datasets` record where possible so directory data stays tied to the system database.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Think Tank Name</label>
                                        <input class="form-control" name="name" value="{{ old('name', $thinkTank->name) }}" data-tt-autofill="name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Consortium</label>
                                        <select class="form-select" name="consortium_id" required>
                                            @foreach ($consortia as $consortium)
                                                <option value="{{ $consortium->id }}" @selected(old('consortium_id', $thinkTank->consortium_id) === $consortium->id)>{{ $consortium->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Role</label>
                                        <select class="form-select" name="role" required>
                                            @foreach ($roles as $role)
                                                <option value="{{ $role }}" @selected(old('role', $thinkTank->role) === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            @foreach (['active', 'inactive', 'suspended', 'closed'] as $status)
                                                <option value="{{ $status }}" @selected(old('status', $thinkTank->status) === $status)>{{ ucfirst($status) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Country</label>
                                        <input class="form-control" name="country" value="{{ old('country', $thinkTank->country) }}" data-tt-autofill="country">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email / Portal Login</label>
                                        <input class="form-control" type="email" name="email" value="{{ old('email', $thinkTank->email) }}" data-tt-autofill="email">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Approved Operating Amount</label>
                                        <input class="form-control" type="number" step="0.01" min="0" name="budget_allocated" value="{{ old('budget_allocated', $thinkTank->budget_allocated) }}">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">AU SAP Vendor Number</label>
                                        <input class="form-control" name="au_sap_vendor_number" value="{{ old('au_sap_vendor_number', $thinkTank->au_sap_vendor_number) }}">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light">
                                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                                <button class="btn btn-primary"><i class="feather-save me-1"></i> Update Think Tank</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endforeach
    @endcan
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const datasetLookup = @json($datasetLookup);

            document.querySelectorAll('[data-tt-dataset-select]').forEach(function (select) {
                select.addEventListener('change', function () {
                    const dataset = datasetLookup[this.value];
                    const form = this.closest('form');
                    if (!dataset || !form) {
                        return;
                    }

                    ['name', 'country', 'email'].forEach(function (field) {
                        const input = form.querySelector('[data-tt-autofill="' + field + '"]');
                        if (!input || !dataset[field]) {
                            return;
                        }

                        if (!input.value || input.dataset.autoFilled === '1') {
                            input.value = dataset[field];
                            input.dataset.autoFilled = '1';
                        }
                    });
                });
            });

            if (window.jQuery && $.fn.DataTable && !$.fn.DataTable.isDataTable('#thinkTankDirectoryTable')) {
                $('#thinkTankDirectoryTable').DataTable($.extend(true, {}, window.dataTableConfig || {}, {
                    pageLength: 25,
                    order: [[0, 'asc']],
                    autoWidth: false
                }));
            }
        });
    </script>

    @if ($errors->any())
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const modal = document.getElementById('createThinkTankModal');
                if (modal && window.bootstrap) {
                    new bootstrap.Modal(modal).show();
                }
            });
        </script>
    @endif
@endpush
