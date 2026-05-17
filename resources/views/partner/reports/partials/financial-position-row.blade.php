@php
    $levelClass = [
        'project' => 'table-primary',
        'activity' => 'table-light',
        'sub_activity' => '',
    ][$row['level']] ?? '';
    $padding = 12 + ($depth * 26);
@endphp

<tr class="{{ $levelClass }}">
    <td style="padding-left: {{ $padding }}px;">
        <div class="fw-semibold">{{ $row['label'] }}</div>
        <div class="small text-muted text-capitalize">{{ str_replace('_', ' ', $row['level']) }}</div>
    </td>
    <td class="text-end">{{ number_format($row['budget'], 2) }}</td>
    <td class="text-end">{{ number_format($row['committed'], 2) }}</td>
    <td class="text-end">{{ number_format($row['disbursed'], 2) }}</td>
    <td class="text-end">{{ number_format($row['remaining_budget'], 2) }}</td>
    <td class="text-end">{{ number_format($row['remaining_to_disburse'], 2) }}</td>
    <td class="text-end">{{ number_format($row['commitment_rate'], 1) }}%</td>
    <td class="text-end">{{ number_format($row['disbursement_rate'], 1) }}%</td>
</tr>
