@extends('layouts.app')

@section('title', $thinkTank->name)

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="feather-user text-primary me-2"></i>{{ $thinkTank->name }}</h4>
                <p class="text-muted mb-0">{{ $thinkTank->consortium?->name ?? 'No consortium' }} | {{ ucfirst($thinkTank->status) }}</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('think-tanks-admin.funding') }}" class="btn btn-primary btn-sm">Fund Think Tank</a>
                <a href="{{ route('think-tanks-admin.directory') }}" class="btn btn-light btn-sm border">Back</a>
            </div>
        </div>

        @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
        @if ($errors->any()) <div class="alert alert-danger">{{ $errors->first() }}</div> @endif

        @php
            $currency = $thinkTank->consortium?->currency ?? 'USD';
            $allocated = (float) $thinkTank->budget_allocated + (float) $thinkTank->fundAllocations->sum('amount_allocated');
            $transferred = (float) $thinkTank->transferDisbursements->sum('amount');
            $confirmed = (float) $thinkTank->transferDisbursements->where('recipient_confirmation_status', 'confirmed')->sum('amount');
        @endphp

        <div class="row g-3 mb-4">
            @foreach ([
                'Approved operations amount' => $currency . ' ' . number_format($allocated, 2),
                'Transferred' => $currency . ' ' . number_format($transferred, 2),
                'Confirmed received' => $currency . ' ' . number_format($confirmed, 2),
                'Research outputs' => number_format($thinkTank->researchOutputs->count()),
            ] as $label => $value)
                <div class="col-md-6 col-xl-3">
                    <div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small">{{ $label }}</div><h4 class="mb-0">{{ $value }}</h4></div></div>
                </div>
            @endforeach
        </div>

        @can('consortiums.manage')
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white border-0"><h5 class="mb-0">Edit Profile</h5></div>
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

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0"><h5 class="mb-0">Funding Transfers</h5></div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light"><tr><th>Reference</th><th>Amount</th><th>Transfer Info</th><th>Paid</th><th>Receipt</th></tr></thead>
                        <tbody>
                            @forelse ($thinkTank->transferDisbursements->sortByDesc('paid_at') as $transfer)
                                <tr>
                                    <td><strong>{{ $transfer->reference_no }}</strong><br><span class="text-muted small">{{ $transfer->purchaseOrder?->reference_no }}</span></td>
                                    <td>{{ $transfer->currency }} {{ number_format($transfer->amount, 2) }}</td>
                                    <td>{{ $transfer->payment_method }}<br><span class="text-muted small">{{ $transfer->transfer_reference ?: 'No transfer reference' }}</span></td>
                                    <td>{{ $transfer->paid_at?->format('M d, Y H:i') ?? '-' }}</td>
                                    <td>
                                        <span class="badge {{ $transfer->recipient_confirmation_status === 'confirmed' ? 'bg-success' : 'bg-warning text-dark' }}">
                                            {{ str_replace('_', ' ', ucfirst($transfer->recipient_confirmation_status)) }}
                                        </span>
                                        @if ($transfer->recipient_confirmed_at)
                                            <div class="text-muted small">{{ $transfer->recipient_confirmed_at->format('M d, Y H:i') }}</div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="text-center text-muted py-4">No funding transfers recorded.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
