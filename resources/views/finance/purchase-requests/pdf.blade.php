<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Purchase Request {{ $purchaseRequest->reference_no }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 12px; color: #111; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; vertical-align: top; }
        th { background: #f2f2f2; text-align: left; }
        .header {
            background: #0f172a;
            color: #fff;
            padding: 14px 18px;
            border-bottom: 4px solid #22c55e;
        }
        .header .title { font-size: 18px; font-weight: bold; }
        .header .meta { font-size: 11px; margin-top: 6px; color: #e2e8f0; }
        .section { margin: 18px 0; }
        .footer {
            position: fixed;
            bottom: -10px;
            left: 0;
            right: 0;
            height: 42px;
            background: #0f172a;
            color: #e2e8f0;
            font-size: 10px;
            padding: 10px 16px;
            border-top: 3px solid #f59e0b;
        }
        .right { text-align: right; }
        .no-border td, .no-border th { border: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ config('app.name') }} - Purchase Request</div>
        <div class="meta">
            Reference: {{ $purchaseRequest->reference_no }} |
            Created: {{ $purchaseRequest->created_at?->format('Y-m-d H:i') ?? 'N/A' }} |
            Status: {{ strtoupper($purchaseRequest->status ?? 'N/A') }}
        </div>
    </div>

    @php
        $currency = $purchaseRequest->currency ?? $purchaseRequest->programFunding?->program?->currency ?? '';
        $yearSplits = $purchaseRequest->commitments
            ->groupBy('commitment_year')
            ->map(fn ($rows) => round((float) $rows->sum('commitment_amount'), 2))
            ->sortKeys();
    @endphp

    <div class="section">
        <h3 style="margin: 0 0 8px 0; color: #0f172a;">Summary</h3>
        <table>
            <tr>
                <th style="width: 180px;">Program</th>
                <td>{{ $purchaseRequest->programFunding?->program?->name ?? $purchaseRequest->programFunding?->program_name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Governance Node</th>
                <td>{{ $purchaseRequest->governanceNode?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Sub-Activity</th>
                <td>{{ $purchaseRequest->subActivity?->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Start Year</th>
                <td>{{ $purchaseRequest->start_year }}</td>
            </tr>
            <tr>
                <th>Commitment Date</th>
                <td>{{ $purchaseRequest->commitment_date?->format('F j, Y') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Delivery Date</th>
                <td>{{ $purchaseRequest->delivery_date?->format('F j, Y') ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Total Amount</th>
                <td><strong>{{ $currency }} {{ number_format((float) $purchaseRequest->total_amount, 2) }}</strong></td>
            </tr>
            <tr>
                <th>Created By</th>
                <td>{{ $purchaseRequest->creator?->name ?? 'N/A' }}</td>
            </tr>
            @if (!empty($purchaseRequest->description))
                <tr>
                    <th>Description</th>
                    <td>{{ $purchaseRequest->description }}</td>
                </tr>
            @endif
        </table>
    </div>

    <div class="section">
        <h3 style="margin: 0 0 8px 0; color: #0f172a;">Requested Items</h3>
        <table>
            <tr>
                <th style="width: 40px;">#</th>
                <th style="width: 22%;">Category</th>
                <th style="width: 20%;">Resource Item</th>
                <th style="width: 24%;">Milestone / Description</th>
                <th style="width: 16%;">Milestone Date</th>
                <th class="right" style="width: 110px;">Amount</th>
            </tr>
            @foreach ($purchaseRequest->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->resourceCategory?->name ?? 'N/A' }}</td>
                    <td>{{ $item->resource?->name ?? 'N/A' }}</td>
                    <td>{{ $item->milestone ?? '—' }}</td>
                    <td>{{ $item->milestone_date?->format('Y-m-d') ?? '—' }}</td>
                    <td class="right">{{ $currency }} {{ number_format((float) $item->amount, 2) }}</td>
                </tr>
            @endforeach
            <tr>
                <th colspan="5" class="right">Total</th>
                <th class="right">{{ $currency }} {{ number_format((float) $purchaseRequest->total_amount, 2) }}</th>
            </tr>
        </table>
    </div>

    <div class="section">
        <h3 style="margin: 0 0 8px 0; color: #0f172a;">Year Contributions</h3>
        <table>
            <tr>
                <th>Year</th>
                <th class="right">Amount</th>
            </tr>
            @forelse ($yearSplits as $year => $amount)
                <tr>
                    <td>{{ $year }}</td>
                    <td class="right">{{ $currency }} {{ number_format((float) $amount, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">N/A</td>
                </tr>
            @endforelse
        </table>
    </div>

    <div class="footer">
        {{ config('app.name') }} | Purchase Request {{ $purchaseRequest->reference_no }} | Generated: {{ now()->format('Y-m-d H:i') }}
    </div>
</body>
</html>
