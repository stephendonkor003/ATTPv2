<x-think-tank.partials.shell :member="$member" title="Purchase Order">
    @php
        $portalRouteParams = (auth()->user()?->isSuperAdmin() || auth()->user()?->isAdmin())
            ? ['think_tank_member_id' => $member->id]
            : [];
    @endphp

    <div class="card">
        <div class="card-body d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <div class="label">Purchase Order Reference</div>
                <h4 class="fw-bold mb-1">{{ $purchaseOrder->reference_no }}</h4>
                <p class="text-muted mb-0">Submitted to ATTP Secretariat for fund disbursement.</p>
            </div>
            <div class="d-flex flex-wrap gap-2 align-items-start">
                <a href="{{ route('think-tank.purchase-orders.pdf', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder])) }}" class="btn btn-light">
                    <i class="feather-eye me-1"></i> View PDF
                </a>
                <a href="{{ route('think-tank.purchase-orders.download', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder])) }}" class="btn btn-primary">
                    <i class="feather-download me-1"></i> Download PDF
                </a>
                <a href="{{ route('think-tank.purchase-orders', $portalRouteParams) }}" class="btn light">
                    <i class="feather-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>
    </div>

    <div class="grid section">
        <div class="card"><div class="card-body"><div class="label">Amount</div><div class="metric">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->amount, 2) }}</div></div></div>
        <div class="card"><div class="card-body"><div class="label">Disbursed</div><div class="metric">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->paidAmount(), 2) }}</div></div></div>
        <div class="card"><div class="card-body"><div class="label">Remaining</div><div class="metric">{{ $purchaseOrder->currency }} {{ number_format($purchaseOrder->remainingAmount(), 2) }}</div></div></div>
        <div class="card"><div class="card-body"><div class="label">Status</div><div class="metric text-capitalize">{{ str_replace('_', ' ', $purchaseOrder->status) }}</div></div></div>
    </div>

    <div class="section card">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Details</h5>
            <table>
                <tbody>
                    <tr><td>Think Tank</td><td><strong>{{ $member->name }}</strong></td></tr>
                    <tr><td>Consortium</td><td>{{ $member->consortium?->name ?? '-' }}</td></tr>
                    <tr><td>AU SAP Vendor Number</td><td>{{ $member->au_sap_vendor_number ?? '-' }}</td></tr>
                    <tr><td>Issued At</td><td>{{ $purchaseOrder->issued_at?->format('M d, Y') ?? '-' }}</td></tr>
                    <tr><td>Payment Vendor Record</td><td>{{ $purchaseOrder->vendor?->name ?? '-' }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="section card">
        <div class="card-body">
            <h5 class="fw-bold mb-3">Transfer Receipt Confirmation</h5>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Transfer Ref</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Paid At</th>
                            <th>Receipt Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($purchaseOrder->disbursements as $disbursement)
                            <tr>
                                <td><strong>{{ $disbursement->transfer_reference ?: $disbursement->reference_no }}</strong></td>
                                <td>{{ $disbursement->currency }} {{ number_format($disbursement->amount, 2) }}</td>
                                <td>{{ $disbursement->payment_method ?? '-' }}</td>
                                <td>{{ $disbursement->paid_at?->format('M d, Y H:i') ?? '-' }}</td>
                                <td>
                                    <span class="badge {{ $disbursement->recipient_confirmation_status === 'confirmed' ? 'good' : '' }}">
                                        {{ str_replace('_', ' ', $disbursement->recipient_confirmation_status ?? 'pending') }}
                                    </span>
                                    @if ($disbursement->recipient_confirmed_at)
                                        <div class="text-muted small">{{ $disbursement->recipient_confirmed_at->format('M d, Y H:i') }}</div>
                                    @endif
                                </td>
                                <td>
                                    @if ($disbursement->recipient_confirmation_status === 'confirmed')
                                        <span class="text-muted small">Confirmed</span>
                                    @else
                                        <form method="POST" action="{{ route('think-tank.purchase-orders.disbursements.confirm', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder, 'disbursement' => $disbursement])) }}" class="stack">
                                            @csrf
                                            <textarea name="recipient_confirmation_notes" rows="2" placeholder="Optional receipt note"></textarea>
                                            <button class="btn btn-primary btn-sm" type="submit">Confirm Received</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">No disbursement record is linked to this order yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-think-tank.partials.shell>
