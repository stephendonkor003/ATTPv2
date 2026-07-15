@extends('layouts.app')

@section('title', $thinkTank->name)

@push('styles')
    <style>
        .tt-profile-hero {
            border: 0;
            border-radius: 10px;
            background: #0f172a;
            color: #ffffff;
            overflow: hidden;
        }

        .tt-profile-hero .kicker {
            color: #facc15;
            font-size: 0.72rem;
            font-weight: 900;
            letter-spacing: 0.06em;
            text-transform: uppercase;
        }

        .tt-profile-hero h3 {
            color: #ffffff;
            font-weight: 900;
        }

        .tt-profile-hero .copy {
            color: #e2e8f0;
            max-width: 780px;
            line-height: 1.65;
        }

        .tt-profile-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            border-radius: 999px;
            padding: 0.28rem 0.58rem;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 0.72rem;
            font-weight: 900;
        }

        .tt-profile-badge.good {
            background: #dcfce7;
            color: #166534;
        }

        .tt-profile-badge.warn {
            background: #fff7ed;
            color: #c2410c;
        }

        .tt-profile-stat,
        .tt-profile-card {
            border: 0;
            border-radius: 8px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .tt-profile-stat .label {
            color: #64748b;
            font-size: 0.72rem;
            font-weight: 850;
            text-transform: uppercase;
        }

        .tt-profile-stat .value {
            color: #0f172a;
            font-size: 1.18rem;
            font-weight: 900;
        }

        .tt-finance-link-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.4rem;
        }

        .tt-link-chip {
            display: inline-flex;
            justify-content: center;
            min-width: 42px;
            border-radius: 999px;
            padding: 0.18rem 0.48rem;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.68rem;
            font-weight: 900;
            text-transform: uppercase;
        }

        .tt-link-chip.po {
            background: #fef3c7;
            color: #92400e;
        }

        .tt-link-chip.pay {
            background: #dcfce7;
            color: #166534;
        }

        .tt-profile-card a {
            color: #0f766e;
            font-weight: 800;
            text-decoration: none;
        }

        .tt-profile-card a:hover {
            color: #0f172a;
            text-decoration: underline;
        }

        .tt-progress-track {
            height: 8px;
            border-radius: 999px;
            background: #e2e8f0;
            overflow: hidden;
        }

        .tt-progress-track span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #0f766e;
        }

        .tt-consortium-mini {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 0.6rem;
        }

        .tt-consortium-mini div {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.7rem;
            background: #f8fafc;
        }

        .tt-consortium-mini span {
            display: block;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 850;
            text-transform: uppercase;
        }

        .tt-consortium-mini strong {
            color: #0f172a;
            font-size: 0.96rem;
            font-weight: 900;
        }

        @media (max-width: 800px) {
            .tt-consortium-mini {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endpush

@section('content')
    @php
        $purchaseOrders = $thinkTank->directoryPurchaseOrders ?? collect();
        $purchaseRequests = $thinkTank->directoryPurchaseRequests ?? collect();
        $paidDisbursements = $thinkTank->directoryDisbursements ?? collect();
        $currency = $purchaseOrders->first()?->resolved_currency ?? $thinkTank->consortium?->currency ?? 'USD';
        $profileAllocated = (float) $thinkTank->budget_allocated + (float) $thinkTank->fundAllocations->sum('amount_allocated');
        $poAmount = (float) ($thinkTank->directory_po_amount ?? 0);
        $paidAmount = (float) ($thinkTank->directory_paid_amount ?? 0);
        $openAmount = (float) ($thinkTank->directory_unpaid_amount ?? max($poAmount - $paidAmount, 0));
        $confirmedAmount = (float) $paidDisbursements->where('recipient_confirmation_status', 'confirmed')->sum('amount');
        $paymentRate = $poAmount > 0 ? min(100, ($paidAmount / $poAmount) * 100) : 0;
        $receiptRate = $paidAmount > 0 ? min(100, ($confirmedAmount / $paidAmount) * 100) : 0;
    @endphp

    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-user text-primary me-2"></i>{{ $thinkTank->name }}</h4>
                <p class="text-muted mb-0">{{ $thinkTank->consortium?->name ?? 'No consortium' }} | {{ ucfirst($thinkTank->status) }}</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="{{ route('think-tanks-admin.directory', $thinkTank->consortium_id ? ['consortium_id' => $thinkTank->consortium_id] : []) }}" class="btn btn-light btn-sm border">
                    <i class="feather-list me-1"></i> Directory
                </a>
                <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-primary btn-sm">
                    <i class="feather-send me-1"></i> Funding
                </a>
            </div>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

        <div class="card tt-profile-hero mb-4">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between gap-3 flex-wrap">
                    <div>
                        <div class="kicker mb-2">Think Tank Profile</div>
                        <h3 class="mb-2">{{ $thinkTank->name }}</h3>
                        <p class="copy mb-3">
                            This profile connects the think tank to its consortium, vendor identity, purchase requests,
                            purchase orders, disbursement payments, reports, research outputs, and procurement activity.
                        </p>
                        <div class="d-flex gap-2 flex-wrap">
                            <span class="tt-profile-badge {{ $thinkTank->thinkDataset ? 'good' : 'warn' }}">
                                <i class="feather-database"></i> {{ $thinkTank->thinkDataset ? 'System DB linked' : 'System DB missing' }}
                            </span>
                            <span class="tt-profile-badge {{ $thinkTank->vendorUser ? 'good' : 'warn' }}">
                                <i class="feather-briefcase"></i> {{ $thinkTank->vendorUser ? 'Vendor linked' : 'Vendor not linked' }}
                            </span>
                            <span class="tt-profile-badge {{ $thinkTank->portalUser ? 'good' : 'warn' }}">
                                <i class="feather-home"></i> {{ $thinkTank->portalUser ? 'Portal linked' : 'Portal not linked' }}
                            </span>
                        </div>
                    </div>
                    <div class="text-end">
                        <div class="text-muted small">Consortium</div>
                        <h5 class="text-white mb-1">{{ $thinkTank->consortium?->name ?? 'Unassigned' }}</h5>
                        <div class="text-muted small">{{ $thinkTank->country ?: 'No country' }} | {{ str_replace('_', ' ', ucfirst($thinkTank->role)) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            @foreach ([
                ['label' => 'Profile Allocation', 'value' => $currency . ' ' . number_format($profileAllocated, 2), 'hint' => 'Base plus fund allocations'],
                ['label' => 'Related PO Value', 'value' => $currency . ' ' . number_format($poAmount, 2), 'hint' => number_format($purchaseOrders->count()) . ' purchase order(s)'],
                ['label' => 'Paid Disbursements', 'value' => $currency . ' ' . number_format($paidAmount, 2), 'hint' => number_format($paymentRate, 1) . '% paid'],
                ['label' => 'Receipt Confirmed', 'value' => $currency . ' ' . number_format($confirmedAmount, 2), 'hint' => number_format($receiptRate, 1) . '% confirmed'],
            ] as $stat)
                <div class="col-md-6 col-xl-3">
                    <div class="card tt-profile-stat h-100">
                        <div class="card-body">
                            <div class="label">{{ $stat['label'] }}</div>
                            <div class="value">{{ $stat['value'] }}</div>
                            <div class="text-muted small">{{ $stat['hint'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-5">
                <div class="card tt-profile-card h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="fw-bold mb-1">Consortium Position</h5>
                        <div class="text-muted small">The think tank sits inside this consortium portfolio.</div>
                    </div>
                    <div class="card-body">
                        <div class="tt-consortium-mini mb-3">
                            <div><span>Think Tanks</span><strong>{{ number_format($consortiumRollup['think_tanks']) }}</strong></div>
                            <div><span>PO Value</span><strong>{{ $consortiumRollup['currency'] }} {{ number_format($consortiumRollup['po_amount'], 2) }}</strong></div>
                            <div><span>Paid</span><strong>{{ $consortiumRollup['currency'] }} {{ number_format($consortiumRollup['paid_amount'], 2) }}</strong></div>
                        </div>
                        @foreach ([
                            ['label' => 'Profile readiness', 'value' => $consortiumRollup['profile_rate']],
                            ['label' => 'Financial payment', 'value' => $consortiumRollup['payment_rate']],
                            ['label' => 'Activity reporting', 'value' => $consortiumRollup['activity_rate']],
                        ] as $progress)
                            <div class="mb-3">
                                <div class="d-flex justify-content-between small fw-bold mb-1">
                                    <span>{{ $progress['label'] }}</span>
                                    <span>{{ number_format($progress['value'], 1) }}%</span>
                                </div>
                                <div class="tt-progress-track"><span style="width: {{ min(100, (float) $progress['value']) }}%"></span></div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="col-xl-7">
                <div class="card tt-profile-card h-100">
                    <div class="card-header bg-white border-0">
                        <h5 class="fw-bold mb-1">Finance Trail</h5>
                        <div class="text-muted small">Vendor-aware links for purchase requests, purchase orders, and paid disbursements.</div>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-3">
                            <div class="tt-finance-link-row">
                                <span class="tt-link-chip">PR</span>
                                @forelse ($purchaseRequests->take(5) as $purchaseRequest)
                                    <a href="{{ route('finance.purchase-requests.show', $purchaseRequest) }}">{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</a>
                                @empty
                                    <span class="text-muted small">No related purchase request</span>
                                @endforelse
                                @if ($purchaseRequests->count() > 5)
                                    <span class="text-muted small">+{{ $purchaseRequests->count() - 5 }}</span>
                                @endif
                            </div>
                            <div class="tt-finance-link-row">
                                <span class="tt-link-chip po">PO</span>
                                @forelse ($purchaseOrders->take(5) as $purchaseOrder)
                                    <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}">{{ $purchaseOrder->reference_no ?: 'Purchase Order' }}</a>
                                @empty
                                    <span class="text-muted small">No related purchase order</span>
                                @endforelse
                                @if ($purchaseOrders->count() > 5)
                                    <span class="text-muted small">+{{ $purchaseOrders->count() - 5 }}</span>
                                @endif
                            </div>
                            <div class="tt-finance-link-row">
                                <span class="tt-link-chip pay">Pay</span>
                                @forelse ($paidDisbursements->take(5) as $disbursement)
                                    <a href="{{ route('procurement.disbursements.show', $disbursement) }}">{{ $disbursement->reference_no ?: 'Disbursement' }}</a>
                                @empty
                                    <span class="text-muted small">No paid disbursement</span>
                                @endforelse
                                @if ($paidDisbursements->count() > 5)
                                    <span class="text-muted small">+{{ $paidDisbursements->count() - 5 }}</span>
                                @endif
                            </div>
                        </div>
                        <hr>
                        <div class="row g-3">
                            <div class="col-md-4"><div class="text-muted small">Open PO balance</div><strong>{{ $currency }} {{ number_format($openAmount, 2) }}</strong></div>
                            <div class="col-md-4"><div class="text-muted small">Reports</div><strong>{{ number_format($thinkTank->reports->count()) }}</strong></div>
                            <div class="col-md-4"><div class="text-muted small">Research outputs</div><strong>{{ number_format($thinkTank->researchOutputs->count()) }}</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        @can('think_tanks.directory.edit')
            @if (auth()->user()?->isAdmin() || auth()->user()?->isSuperAdmin())
                <div class="card tt-profile-card mb-4">
                    <div class="card-header bg-white border-0">
                        <h5 class="mb-1 fw-bold">Portal Logo</h5>
                        <div class="text-muted small">Shown beside the AU identity and as the think tank portal watermark.</div>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 align-items-center">
                            <div class="col-auto">
                                <div class="d-flex align-items-center justify-content-center border rounded bg-light" style="width: 104px; height: 104px;">
                                    @if ($thinkTank->logo_url)
                                        <img src="{{ $thinkTank->logo_url }}" alt="{{ $thinkTank->name }} logo" style="max-width: 86px; max-height: 86px; object-fit: contain;">
                                    @else
                                        <i class="feather-image text-muted" style="font-size: 2rem;"></i>
                                    @endif
                                </div>
                            </div>
                            <div class="col">
                                <form method="POST" action="{{ route('think-tanks-admin.logo.update', $thinkTank) }}" enctype="multipart/form-data">
                                    @csrf
                                    @method('PUT')
                                    <label class="form-label fw-semibold" for="think-tank-logo">Choose logo</label>
                                    <input class="form-control" id="think-tank-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp">
                                    <div class="form-text">PNG, JPEG, or WebP, up to 5 MB. A transparent PNG works best.</div>
                                    @if ($thinkTank->logo_path)
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" id="remove-think-tank-logo" type="checkbox" name="remove_logo" value="1">
                                            <label class="form-check-label" for="remove-think-tank-logo">Remove the current logo</label>
                                        </div>
                                    @endif
                                    <button class="btn btn-primary btn-sm mt-3" type="submit">Save Portal Logo</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="card tt-profile-card mb-4">
                <div class="card-header bg-white border-0"><h5 class="mb-0 fw-bold">Edit Profile</h5></div>
                <div class="card-body">
                    <form class="row g-3" method="POST" action="{{ route('think-tanks-admin.update', $thinkTank) }}">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="consortium_id" value="{{ $thinkTank->consortium_id }}">
                        <div class="col-md-4"><label class="form-label">Name</label><input class="form-control" name="name" value="{{ old('name', $thinkTank->name) }}" required></div>
                        <div class="col-md-2"><label class="form-label">Country</label><input class="form-control" name="country" value="{{ old('country', $thinkTank->country) }}"></div>
                        <div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="{{ old('email', $thinkTank->email) }}"></div>
                        <div class="col-md-3"><label class="form-label">AU SAP Vendor Number</label><input class="form-control" name="au_sap_vendor_number" value="{{ old('au_sap_vendor_number', $thinkTank->au_sap_vendor_number) }}"></div>
                        <div class="col-md-3">
                            <label class="form-label">Role</label>
                            <select class="form-select" name="role">
                                @foreach (['lead', 'member', 'implementing_partner'] as $role)
                                    <option value="{{ $role }}" @selected(old('role', $thinkTank->role) === $role)>{{ str_replace('_', ' ', ucfirst($role)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status">
                                @foreach (['active', 'inactive', 'suspended', 'closed'] as $status)
                                    <option value="{{ $status }}" @selected(old('status', $thinkTank->status) === $status)>{{ ucfirst($status) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Base Approved Amount</label><input class="form-control" type="number" step="0.01" min="0" name="budget_allocated" value="{{ old('budget_allocated', $thinkTank->budget_allocated) }}"></div>
                        <div class="col-md-3"><label class="form-label">Joined At</label><input class="form-control" type="date" name="joined_at" value="{{ old('joined_at', $thinkTank->joined_at?->toDateString()) }}"></div>
                        <div class="col-12"><button class="btn btn-primary">Update Profile</button></div>
                    </form>
                </div>
            </div>
        @endcan

        <div class="card tt-profile-card mb-4">
            <div class="card-header bg-white border-0">
                <h5 class="mb-1 fw-bold">Purchase Order Ledger</h5>
                <div class="text-muted small">Purchase orders where this think tank is directly linked or appears as the vendor.</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Purchase Order</th><th>Purchase Request</th><th>Amount</th><th>Paid</th><th>Open</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($purchaseOrders as $purchaseOrder)
                            @php
                                $purchaseRequest = $purchaseOrder->purchaseRequest ?: $purchaseOrder->budgetCommitment?->purchaseRequest;
                                $poPaid = (float) $purchaseOrder->disbursements->sum('amount');
                                $poOpen = max((float) $purchaseOrder->amount - $poPaid, 0);
                                $poCurrency = $purchaseOrder->resolved_currency ?? $currency;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}">{{ $purchaseOrder->reference_no ?: 'Purchase Order' }}</a>
                                    <div class="text-muted small">{{ $purchaseOrder->issued_at?->format('M d, Y') ?? 'No issued date' }}</div>
                                </td>
                                <td>
                                    @if ($purchaseRequest)
                                        <a href="{{ route('finance.purchase-requests.show', $purchaseRequest) }}">{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</a>
                                        <div class="text-muted small">{{ \Illuminate\Support\Str::limit($purchaseRequest->description, 70) }}</div>
                                    @else
                                        <span class="text-muted small">No linked purchase request</span>
                                    @endif
                                </td>
                                <td>{{ $poCurrency }} {{ number_format((float) $purchaseOrder->amount, 2) }}</td>
                                <td>{{ $poCurrency }} {{ number_format($poPaid, 2) }}</td>
                                <td>{{ $poCurrency }} {{ number_format($poOpen, 2) }}</td>
                                <td><span class="badge bg-light text-dark">{{ str_replace('_', ' ', ucfirst($purchaseOrder->status ?? 'pending')) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No related purchase orders found.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card tt-profile-card">
            <div class="card-header bg-white border-0">
                <h5 class="mb-1 fw-bold">Paid Disbursements</h5>
                <div class="text-muted small">Paid disbursements matched by think-tank profile, vendor account, or related purchase order.</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Reference</th><th>PO / PR</th><th>Amount</th><th>Transfer</th><th>Paid</th><th>Receipt</th></tr></thead>
                        <tbody>
                            @forelse ($paidDisbursements as $transfer)
                                @php
                                    $purchaseOrder = $transfer->purchaseOrder;
                                    $purchaseRequest = $purchaseOrder?->purchaseRequest ?: $purchaseOrder?->budgetCommitment?->purchaseRequest;
                                    $transferCurrency = $transfer->resolved_currency ?? $currency;
                                @endphp
                                <tr>
                                    <td>
                                        <a href="{{ route('procurement.disbursements.show', $transfer) }}">{{ $transfer->transfer_reference ?: $transfer->reference_no }}</a>
                                        <div class="text-muted small">{{ $transfer->reference_no }}</div>
                                    </td>
                                    <td>
                                        @if ($purchaseOrder)
                                            <a href="{{ route('procurement.purchase-orders.show', $purchaseOrder) }}">{{ $purchaseOrder->reference_no ?: 'Purchase Order' }}</a>
                                        @else
                                            <span class="text-muted small">No PO</span>
                                        @endif
                                        <div class="small">
                                            @if ($purchaseRequest)
                                                <a href="{{ route('finance.purchase-requests.show', $purchaseRequest) }}">{{ $purchaseRequest->reference_no ?: 'Purchase Request' }}</a>
                                            @else
                                                <span class="text-muted">No PR</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>{{ $transferCurrency }} {{ number_format((float) $transfer->amount, 2) }}</td>
                                    <td>{{ $transfer->payment_method ?: 'Bank transfer' }}<br><span class="text-muted small">{{ $transfer->transfer_reference ?: 'No transfer reference' }}</span></td>
                                    <td>{{ $transfer->paid_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $transfer->recipient_confirmation_status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ str_replace('_', ' ', ucfirst($transfer->recipient_confirmation_status ?: 'pending')) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="text-center text-muted py-4">No paid disbursements found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        @if ($consortiumMembers->count() > 1)
            <div class="card tt-profile-card mt-4">
                <div class="card-header bg-white border-0">
                    <h5 class="mb-1 fw-bold">Consortium Think Tanks</h5>
                    <div class="text-muted small">Other think tanks under {{ $thinkTank->consortium?->name ?? 'this consortium' }} and their financial progress.</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light"><tr><th>Think Tank</th><th>Vendor</th><th>PO Value</th><th>Paid</th><th>Progress</th><th>Outputs</th></tr></thead>
                            <tbody>
                                @foreach ($consortiumMembers as $member)
                                    @php
                                        $memberPo = (float) ($member->directory_po_amount ?? 0);
                                        $memberPaid = (float) ($member->directory_paid_amount ?? 0);
                                        $memberRate = $memberPo > 0 ? min(100, ($memberPaid / $memberPo) * 100) : 0;
                                    @endphp
                                    <tr>
                                        <td>
                                            <a href="{{ route('think-tanks-admin.show', $member) }}">{{ $member->name }}</a>
                                            <div class="text-muted small">{{ $member->country ?: '-' }} | {{ ucfirst($member->status) }}</div>
                                        </td>
                                        <td>
                                            @if ($member->vendorUser)
                                                <span class="badge bg-success">Linked</span>
                                                <div class="text-muted small">{{ $member->vendorUser->email }}</div>
                                            @else
                                                <span class="badge bg-warning text-dark">Not linked</span>
                                            @endif
                                        </td>
                                        <td>{{ $currency }} {{ number_format($memberPo, 2) }}</td>
                                        <td>{{ $currency }} {{ number_format($memberPaid, 2) }}</td>
                                        <td style="min-width: 160px;">
                                            <div class="d-flex justify-content-between small fw-bold mb-1"><span>Paid</span><span>{{ number_format($memberRate, 1) }}%</span></div>
                                            <div class="tt-progress-track"><span style="width: {{ $memberRate }}%"></span></div>
                                        </td>
                                        <td>
                                            <div class="small">Reports: <strong>{{ number_format($member->reports_count ?? 0) }}</strong></div>
                                            <div class="small">Research: <strong>{{ number_format($member->research_outputs_count ?? 0) }}</strong></div>
                                            <div class="small">Procurement: <strong>{{ number_format($member->procurements_count ?? 0) }}</strong></div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endsection
