@php
    $currency = $purchaseOrders->first()?->resolved_currency
        ?? $member->consortium?->currency
        ?? 'USD';
@endphp


<x-think-tank.partials.shell :member="$member" title="Finance">
    <div class="tt-finance-page">
        <header class="tt-page-intro">
            <div>
                <div class="tt-page-eyebrow">Finance workspace</div>
                <h1>Funding and payment receipts</h1>
                <p>Review ATTP funding transfers and confirm payments received by {{ $member->name }}.</p>
            </div>
        </header>

        <section class="tt-finance-stats" aria-label="Finance summary">
            <article class="tt-finance-stat">
                <span class="tt-stat-icon"><i class="feather-file-text" aria-hidden="true"></i></span>
                <div class="tt-stat-value">{{ number_format((int) ($stats['total'] ?? 0)) }}</div>
                <div class="tt-stat-label">Funding records</div>
            </article>
            <article class="tt-finance-stat">
                <span class="tt-stat-icon"><i class="feather-credit-card" aria-hidden="true"></i></span>
                <div class="tt-stat-value">{{ $currency }} {{ number_format((float) ($stats['amount'] ?? 0), 2) }}</div>
                <div class="tt-stat-label">Total committed</div>
            </article>
            <article class="tt-finance-stat">
                <span class="tt-stat-icon"><i class="feather-check-circle" aria-hidden="true"></i></span>
                <div class="tt-stat-value">{{ $currency }} {{ number_format((float) ($stats['paid'] ?? 0), 2) }}</div>
                <div class="tt-stat-label">Paid to the think tank</div>
            </article>
            <article class="tt-finance-stat">
                <span class="tt-stat-icon"><i class="feather-clock" aria-hidden="true"></i></span>
                <div class="tt-stat-value">{{ $currency }} {{ number_format((float) ($stats['remaining'] ?? 0), 2) }}</div>
                <div class="tt-stat-label">Remaining balance</div>
            </article>
        </section>

        <section class="tt-finance-layout">
            <div class="tt-finance-panel">
                <div class="tt-panel-head">
                    <div>
                        <h2>Funding transfers</h2>
                        <p class="tt-panel-copy">Open a record to review the payment and confirm its receipt.</p>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="tt-finance-table">
                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Amount</th>
                                <th>Paid</th>
                                <th>Receipt</th>
                                <th>Issued</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($purchaseOrders as $purchaseOrder)
                                @php
                                    $receiptCount = $purchaseOrder->disbursements->count();
                                    $pendingReceipts = $purchaseOrder->disbursements
                                        ->where('recipient_confirmation_status', '!=', 'confirmed')
                                        ->count();
                                    $receiptComplete = $receiptCount > 0 && $pendingReceipts === 0;
                                    $receiptLabel = $receiptCount === 0
                                        ? 'No payment yet'
                                        : ($receiptComplete ? 'Confirmed' : $pendingReceipts.' pending');
                                @endphp
                                <tr>
                                    <td><strong>{{ $purchaseOrder->reference_no }}</strong></td>
                                    <td>{{ $purchaseOrder->resolved_currency }} {{ number_format((float) $purchaseOrder->amount, 2) }}</td>
                                    <td>{{ $purchaseOrder->resolved_currency }} {{ number_format($purchaseOrder->paidAmount(), 2) }}</td>
                                    <td>
                                        <span class="tt-status-pill {{ $receiptComplete ? 'is-complete' : '' }}">
                                            {{ $receiptLabel }}
                                        </span>
                                    </td>
                                    <td>{{ $purchaseOrder->issued_at?->format('d M Y') ?? 'Not recorded' }}</td>
                                    <td>
                                        <a class="btn btn-sm btn-outline-success" href="{{ route('think-tank.purchase-orders.show', array_merge($portalRouteParams, ['purchaseOrder' => $purchaseOrder])) }}">
                                            Review
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6"><div class="tt-finance-empty">No funding transfer has been recorded for this think tank yet.</div></td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($purchaseOrders->hasPages())
                    <div class="mt-3">{{ $purchaseOrders->links() }}</div>
                @endif
            </div>

            <aside class="d-grid gap-3">
                <div class="tt-finance-note">
                    <h2>What to do</h2>
                    <ul class="tt-guide-list">
                        <li>Review each payment against your bank records.</li>
                        <li>Open the transfer and confirm only after the funds arrive.</li>
                        <li>Add a clear note if an amount needs follow-up.</li>
                    </ul>
                </div>

                @if ($allocations->isNotEmpty())
                    <div class="tt-finance-note">
                        <h2>Current allocations</h2>
                        <ul class="tt-allocation-list">
                            @foreach ($allocations->take(5) as $allocation)
                                <li>
                                    <strong>{{ $allocation->budget_line }}</strong>
                                    {{ $allocation->currency ?: $currency }} {{ number_format((float) $allocation->amount_allocated, 2) }}
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </aside>
        </section>
    </div>
</x-think-tank.partials.shell>
