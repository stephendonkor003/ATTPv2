<x-think-tank.partials.shell :member="$member" title="Think Tank Purchase Orders">
    @php
        $currency = $member->consortium?->currency ?? 'USD';
        $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    @endphp

    <div class="grid">
        <div class="card"><div class="card-body"><div class="label">Purchase Orders</div><div class="metric">{{ number_format($stats['total']) }}</div></div></div>
        <div class="card"><div class="card-body"><div class="label">PO Amount</div><div class="metric">{{ $currency }} {{ number_format($stats['amount'], 2) }}</div></div></div>
        <div class="card"><div class="card-body"><div class="label">Disbursed</div><div class="metric">{{ $currency }} {{ number_format($stats['paid'], 2) }}</div></div></div>
        <div class="card"><div class="card-body"><div class="label">Remaining</div><div class="metric">{{ $currency }} {{ number_format($stats['remaining'], 2) }}</div></div></div>
    </div>

    <div class="section grid two">
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Create Purchase Order</h5>
                <form method="POST" action="{{ route('think-tank.purchase-orders.store', $portalRouteParams) }}" class="stack">
                    @csrf
                    <div>
                        <label class="form-label">AU SAP Vendor Number</label>
                        <input type="text" name="au_sap_vendor_number"
                            value="{{ old('au_sap_vendor_number', $member->au_sap_vendor_number) }}"
                            placeholder="Enter once, for example SAP-00012345"
                            @if($member->au_sap_vendor_number) readonly @endif>
                        @if ($member->au_sap_vendor_number)
                            <div class="text-muted small mt-1">Saved for this think tank and reused on future purchase orders.</div>
                        @endif
                    </div>
                    <div>
                        <label class="form-label">Funding Allocation</label>
                        <select name="fund_allocation_id">
                            <option value="">General transfer</option>
                            @foreach ($allocations as $allocation)
                                <option value="{{ $allocation->id }}" @selected(old('fund_allocation_id') === $allocation->id)>
                                    {{ $allocation->budget_line }} - {{ $allocation->currency }} {{ number_format($allocation->amount_allocated, 2) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="row">
                        <div>
                            <label class="form-label">Amount</label>
                            <input type="number" step="0.01" min="0.01" name="amount" value="{{ old('amount') }}" required>
                        </div>
                        <div>
                            <label class="form-label">Currency</label>
                            <input type="text" name="currency" maxlength="10" value="{{ old('currency', $currency) }}">
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Issued At</label>
                        <input type="date" name="issued_at" value="{{ old('issued_at', now()->toDateString()) }}">
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea name="notes" placeholder="Purpose of the transfer">{{ old('notes') }}</textarea>
                    </div>
                    <button class="btn btn-primary">
                        <i class="feather-save me-1"></i> Create Purchase Order
                    </button>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">PO Code Format</h5>
                <p class="text-muted mb-2">
                    Each purchase order uses ATTP year/month, consortium code, and think tank code.
                </p>
                <div class="p-3 bg-light rounded fw-bold">
                    PO-ATTP-{{ now()->format('Ym') }}-{{ $member->consortium?->code ?? 'CONS' }}-THINKTANK-001
                </div>
                <p class="text-muted small mt-3 mb-0">
                    The final sequence is generated automatically so every PO remains unique.
                </p>
            </div>
        </div>
    </div>

    <div class="section card">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Purchase Orders Submitted to ATTP Secretariat</h5>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PO Reference</th>
                            <th>Amount</th>
                            <th>Disbursed</th>
                            <th>Remaining</th>
                            <th>Status</th>
                            <th>Issued</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrders as $purchaseOrder)
                            <tr>
                                <td><strong>{{ $purchaseOrder->reference_no }}</strong></td>
                                <td>{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}</td>
                                <td>{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->paidAmount(), 2) }}</td>
                                <td>{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->remainingAmount(), 2) }}</td>
                                <td><span class="badge">{{ str_replace('_', ' ', $purchaseOrder->status) }}</span></td>
                                <td>{{ $purchaseOrder->issued_at?->format('M d, Y') ?? '-' }}</td>
                                <td>
                                    <a href="{{ route('think-tank.purchase-orders.show', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder])) }}" class="btn btn-sm btn-light">
                                        View
                                    </a>
                                    <a href="{{ route('think-tank.purchase-orders.download', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder])) }}" class="btn btn-sm btn-primary">
                                        PDF
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No purchase orders created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $purchaseOrders->links() }}
            </div>
        </div>
    </div>
</x-think-tank.partials.shell>
