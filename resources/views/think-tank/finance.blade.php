@php
    $currency = $purchaseOrders->first()?->resolved_currency
        ?? $member->consortium?->currency
        ?? 'USD';
@endphp

@push('styles')
    <style>
        .tt-finance-page {
            display: grid;
            gap: 1rem;
        }

        .tt-page-intro,
        .tt-finance-stat,
        .tt-finance-panel,
        .tt-finance-note {
            border: 1px solid var(--tt-border, #dfe8e3);
            border-radius: 10px;
            background: var(--tt-surface, #fff);
            box-shadow: none;
        }

        .tt-page-intro {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0 0 1rem;
            border-width: 0 0 1px;
            border-radius: 0;
            background: transparent;
        }

        .tt-page-eyebrow {
            color: var(--tt-brand, #176b4b);
            font-size: .72rem;
            font-weight: 850;
            letter-spacing: .055em;
            text-transform: uppercase;
        }

        .tt-page-intro h1,
        .tt-finance-panel h2,
        .tt-finance-note h2 {
            color: var(--tt-ink, #17241d);
            font-weight: 850;
        }

        .tt-page-intro h1 {
            margin: .2rem 0 .3rem;
            font-size: clamp(1.45rem, 2vw, 1.9rem);
        }

        .tt-page-intro p,
        .tt-panel-copy {
            margin: 0;
            color: var(--tt-muted, #607066);
            font-size: .86rem;
        }

        .tt-finance-stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: .8rem;
        }

        .tt-finance-stat {
            padding: 1rem;
        }

        .tt-stat-icon {
            display: inline-flex;
            width: 36px;
            height: 36px;
            align-items: center;
            justify-content: center;
            margin-bottom: .75rem;
            border-radius: 11px;
            background: var(--tt-brand-soft, #e7f2ec);
            color: var(--tt-brand, #176b4b);
        }

        .tt-stat-value {
            color: var(--tt-ink, #17241d);
            font-size: 1.15rem;
            font-weight: 850;
            line-height: 1.25;
        }

        .tt-stat-label {
            margin-top: .25rem;
            color: var(--tt-muted, #607066);
            font-size: .76rem;
        }

        .tt-finance-layout {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(250px, .3fr);
            gap: 1rem;
            align-items: start;
        }

        .tt-finance-panel,
        .tt-finance-note {
            padding: 1.1rem;
        }

        .tt-panel-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: .8rem;
            margin-bottom: .9rem;
        }

        .tt-finance-panel h2,
        .tt-finance-note h2 {
            margin: 0 0 .2rem;
            font-size: 1rem;
        }

        .tt-finance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .tt-finance-table th,
        .tt-finance-table td {
            padding: .78rem .65rem;
            border-bottom: 1px solid #e8efeb;
            text-align: left;
            vertical-align: middle;
            font-size: .78rem;
        }

        .tt-finance-table th {
            color: #66776d;
            background: #f7faf8;
            font-size: .68rem;
            font-weight: 850;
            letter-spacing: .03em;
            text-transform: uppercase;
        }

        .tt-status-pill {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .28rem .55rem;
            border-radius: 999px;
            background: #fef3c7;
            color: #854d0e;
            font-size: .68rem;
            font-weight: 800;
            text-transform: capitalize;
        }

        .tt-status-pill.is-complete {
            background: #e5f5ec;
            color: #166534;
        }

        .tt-finance-empty {
            padding: 2.5rem 1rem;
            color: var(--tt-muted, #607066);
            text-align: center;
        }

        .tt-guide-list,
        .tt-allocation-list {
            display: grid;
            gap: .65rem;
            margin: .8rem 0 0;
            padding: 0;
            list-style: none;
        }

        .tt-guide-list li,
        .tt-allocation-list li {
            padding: .72rem;
            border-radius: 11px;
            background: #f7faf8;
            color: #4f6157;
            font-size: .76rem;
        }

        .tt-allocation-list strong {
            display: block;
            margin-bottom: .15rem;
            color: var(--tt-ink, #17241d);
        }

        @media (max-width: 991.98px) {
            .tt-finance-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .tt-finance-layout {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 575.98px) {
            .tt-page-intro,
            .tt-panel-head {
                align-items: flex-start;
                flex-direction: column;
            }

            .tt-finance-stats {
                grid-template-columns: 1fr;
            }

            .tt-finance-table thead {
                display: none;
            }

            .tt-finance-table tr,
            .tt-finance-table td {
                display: block;
            }

            .tt-finance-table tr {
                padding: .65rem 0;
                border-bottom: 1px solid #e8efeb;
            }

            .tt-finance-table td {
                padding: .32rem 0;
                border: 0;
            }
        }
    </style>
@endpush

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
