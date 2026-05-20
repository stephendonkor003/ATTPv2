@php
    $levelClass = [
        'project' => 'row-project',
        'activity' => 'row-activity',
        'sub_activity' => 'row-sub',
    ][$row['level']] ?? 'row-sub';
    $padding = 5 + ($depth * 9);
    $ref = fn (string $key) => $row['references'][$key]['display'] ?? '-';
@endphp

<tr class="{{ $levelClass }}">
    <td class="structure" style="padding-left: {{ $padding }}px;">
        <strong>{{ $row['label'] }}</strong>
        <span>{{ str_replace('_', ' ', $row['level']) }}</span>
    </td>
    <td class="num">{{ number_format($row['budget'], 2) }}</td>
    <td class="num">{{ number_format($row['committed'], 2) }}</td>
    <td class="num">{{ number_format($row['purchase_orders'], 2) }}</td>
    <td class="num">{{ number_format($row['invoiced'], 2) }}</td>
    <td class="num">{{ number_format($row['disbursed'], 2) }}</td>
    <td class="num {{ $row['uncommitted_budget'] < 0 ? 'negative' : 'positive' }}">{{ number_format($row['uncommitted_budget'], 2) }}</td>
    <td class="num {{ $row['unpaid_commitments'] < 0 ? 'negative' : '' }}">{{ number_format($row['unpaid_commitments'], 2) }}</td>
    <td class="num">{{ number_format($row['commitment_rate'], 1) }}%</td>
    <td class="num">{{ number_format($row['disbursement_rate'], 1) }}%</td>
    <td>{{ $ref('pr') }}</td>
    <td>{{ $ref('po') }}</td>
    <td>{{ $ref('invoice') }}</td>
    <td>{{ $ref('disbursement') }}</td>
</tr>
