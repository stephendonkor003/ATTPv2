@php
    $levelClass = [
        'project' => 'pfp-row-project',
        'activity' => 'pfp-row-activity',
        'sub_activity' => 'pfp-row-sub',
    ][$row['level']] ?? 'pfp-row-sub';
    $padding = 18 + ($depth * 28);
    $reference = fn (string $key) => $row['references'][$key]['display'] ?? '-';
    $referenceFull = fn (string $key) => $row['references'][$key]['full'] ?? '';
@endphp

<tr class="{{ $levelClass }}">
    <td style="padding-left: {{ $padding }}px;">
        <div class="fw-bold">{{ $row['label'] }}</div>
        <div class="small text-muted text-capitalize">{{ str_replace('_', ' ', $row['level']) }}</div>
    </td>
    <td class="text-end">{{ number_format($row['budget'], 2) }}</td>
    <td class="text-end">{{ number_format($row['committed'], 2) }}</td>
    <td class="text-end">{{ number_format($row['purchase_orders'], 2) }}</td>
    <td class="text-end">{{ number_format($row['invoiced'], 2) }}</td>
    <td class="text-end">{{ number_format($row['disbursed'], 2) }}</td>
    <td class="text-end {{ $row['uncommitted_budget'] < 0 ? 'text-danger' : 'text-success' }}">
        {{ number_format($row['uncommitted_budget'], 2) }}
    </td>
    <td class="text-end {{ $row['unpaid_commitments'] < 0 ? 'text-danger' : '' }}">
        {{ number_format($row['unpaid_commitments'], 2) }}
    </td>
    <td class="text-end">{{ number_format($row['commitment_rate'], 1) }}%</td>
    <td class="text-end">{{ number_format($row['disbursement_rate'], 1) }}%</td>
    <td title="{{ $referenceFull('pr') }}">{{ $reference('pr') }}</td>
    <td title="{{ $referenceFull('po') }}">{{ $reference('po') }}</td>
    <td title="{{ $referenceFull('invoice') }}">{{ $reference('invoice') }}</td>
    <td title="{{ $referenceFull('disbursement') }}">{{ $reference('disbursement') }}</td>
</tr>
