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
                <h5 class="fw-bold mb-3">Receipt Confirmation</h5>
                <p class="text-muted mb-0">ATTP Secretariat records transfers after funds are sent. Use the detail page to confirm when the payment has arrived in your bank account.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Vendor Details</h5>
                <table>
                    <tbody>
                        <tr><td>AU SAP Vendor Number</td><td>{{ $member->au_sap_vendor_number ?? '-' }}</td></tr>
                        <tr><td>Portal Email</td><td>{{ $member->portalUser?->email ?? $member->email ?? '-' }}</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="section card">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Funding Transfers from ATTP Secretariat</h5>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>PO Reference</th>
                            <th>Amount</th>
                            <th>Disbursed</th>
                            <th>Remaining</th>
                            <th>Receipt</th>
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
                                <td>
                                    @php $pendingReceipt = $purchaseOrder->disbursements->where('recipient_confirmation_status', '!=', 'confirmed')->count(); @endphp
                                    <span class="badge {{ $pendingReceipt ? '' : 'good' }}">{{ $pendingReceipt ? 'pending' : 'confirmed' }}</span>
                                </td>
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
                                <td colspan="8" class="text-center text-muted py-4">No funding transfers recorded yet.</td>
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
