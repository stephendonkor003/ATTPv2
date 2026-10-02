<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $item->item_code }} Procurement Worksheet</title>
    <style>
        @page{margin:28px 30px 42px}*{box-sizing:border-box}body{margin:0;color:#263946;font-family:DejaVu Sans,Arial,sans-serif;font-size:8px;line-height:1.42}.header,.context,.facts,.docs,.timeline,.milestones,.footer-table{width:100%;border-collapse:collapse}.header td{padding:0 0 12px;border-bottom:4px solid #e9b949;vertical-align:middle}.logo{width:54px;height:54px;object-fit:contain}.brand{color:#102f43;font-size:15px;font-weight:900}.brand-sub{margin-top:2px;color:#176b87;font-size:8px;font-weight:800}.classification{text-align:right}.classification strong{display:inline-block;padding:4px 7px;border-radius:10px;background:#fff1d4;color:#845a14;font-size:6px;text-transform:uppercase}.classification span{display:block;margin-top:4px;color:#71828c;font-size:6px}.title{margin:15px 0 2px;color:#102f43;font-size:18px;font-weight:900}.subtitle{margin-bottom:12px;color:#637985;font-size:8px}.section{margin-top:12px;page-break-inside:auto}.section-title{padding:5px 7px;background:#173f55;color:#fff;font-size:7px;font-weight:900;letter-spacing:.45px;text-transform:uppercase}.context td,.facts td{padding:7px;border:1px solid #d9e3e8;vertical-align:top}.label{display:block;color:#70838e;font-size:5.8px;font-weight:900;letter-spacing:.25px;text-transform:uppercase}.value{display:block;margin-top:3px;color:#203a48;font-size:7px;font-weight:800}.status{display:inline-block;padding:3px 6px;border-radius:8px;background:#e8f2f6;color:#26667c;font-size:5.5px;font-weight:900;text-transform:uppercase}.status.approved{background:#fff1d4;color:#85590f}.status.no_objection_obtained,.status.published{background:#e4f5eb;color:#216646}.status.revision_requested,.status.rejected{background:#fff0e8;color:#93452f}.description{padding:8px;border:1px solid #d9e3e8;border-top:0;background:#f8fafb;color:#526873}.facts td{width:33.333%}.note{padding:7px;border:1px solid #efd5ab;background:#fff9ef;color:#765020}.docs th,.docs td,.milestones th,.milestones td{padding:5px 6px;border:1px solid #d9e3e8;text-align:left;vertical-align:top}.docs th,.milestones th{background:#eaf2f5;color:#254354;font-size:6px;text-transform:uppercase}.docs tr,.milestones tr,.event{page-break-inside:avoid}.muted{color:#72858f}.timeline td{padding:6px;border-bottom:1px solid #e2e9ec;vertical-align:top}.timeline .marker{width:22px;color:#176b87;font-weight:900}.timeline .time{width:96px;color:#70818a;text-align:right;white-space:nowrap}.event-title{color:#203b49;font-weight:900}.clearance{padding:8px;border:1px solid #b9decd;background:#f0faf5;color:#245e49}.footer{position:fixed;right:0;bottom:-28px;left:0;padding-top:6px;border-top:2px solid #e9b949;color:#73838b;font-size:5.7px}.footer-table td{width:33.333%}.footer-table td:nth-child(2){text-align:center}.footer-table td:last-child{text-align:right}
    </style>
</head>
<body>
@php
    $statusLabels = [
        'draft' => 'Draft at Think Tank', 'submitted' => 'Submitted to AUC-ATTP',
        'revision_requested' => 'Returned for correction', 'rejected' => 'Rejected',
        'approved' => 'Pending World Bank no-objection',
        'no_objection_obtained' => 'No-objection received / ready to execute',
        'published' => 'Execution underway / published',
    ];
    $milestones = collect($item->planned_milestones ?? [])->filter(fn ($row) => is_array($row));
@endphp

<table class="header"><tr>
    <td style="width:65px">@if(filled($logoDataUri ?? null))<img class="logo" src="{{ $logoDataUri }}" alt="ATTP">@endif</td>
    <td><div class="brand">{{ $platformName }}</div><div class="brand-sub">AUC-ATTP Secretariat · Procurement oversight worksheet</div></td>
    <td class="classification"><strong>Controlled internal record</strong><span>Generated {{ $generatedAt->format('d M Y, H:i') }}</span></td>
</tr></table>

<h1 class="title">{{ $item->title }}</h1>
<div class="subtitle">{{ $item->item_code }} · {{ $plan->member?->name ?: 'Think Tank not set' }} · {{ $plan->plan_code }} · FY {{ $plan->fiscal_year }}</div>

<div class="section">
    <div class="section-title">Submission and workflow context</div>
    <table class="context"><tr>
        <td><span class="label">Think Tank</span><span class="value">{{ $plan->member?->name ?: 'Not set' }}</span><span class="muted">{{ $plan->member?->country ?: 'Country not set' }}</span></td>
        <td><span class="label">Annual plan</span><span class="value">{{ $plan->plan_code }}</span><span class="muted">Version {{ $plan->version }} · {{ Str::headline($plan->status) }}</span></td>
        <td><span class="label">Item position</span><span class="status {{ $item->status }}">{{ $statusLabels[$item->status] ?? Str::headline($item->status) }}</span></td>
        <td><span class="label">Estimated amount</span><span class="value">{{ $item->currency ?: 'Unspecified' }} {{ number_format((float) $item->estimated_amount, 2) }}</span></td>
    </tr></table>
    @if($item->description)<div class="description">{{ $item->description }}</div>@endif
</div>

<div class="section">
    <div class="section-title">Procurement item fields</div>
    <table class="facts">
        <tr><td><span class="label">Category</span><span class="value">{{ Str::headline($item->procurement_category) ?: 'Not set' }}</span></td><td><span class="label">Method</span><span class="value">{{ $item->procurement_method ?: 'Not set' }}</span></td><td><span class="label">Review type</span><span class="value">{{ $item->review_type ?: 'Not set' }}</span></td></tr>
        <tr><td><span class="label">Market approach</span><span class="value">{{ $item->market_approach ?: 'Not set' }}</span></td><td><span class="label">Document type</span><span class="value">{{ $item->source_document_type ?: 'Not set' }}</span></td><td><span class="label">SEA / SH risk</span><span class="value">{{ $item->source_sea_sh_risk ?: 'Not set' }}</span></td></tr>
        <tr><td><span class="label">Loan / credit no.</span><span class="value">{{ $item->loan_credit_no ?: 'Not set' }}</span></td><td><span class="label">Component</span><span class="value">{{ $item->component ?: 'Not set' }}</span></td><td><span class="label">Source reference</span><span class="value">{{ $item->source_reference ?: 'Not set' }}</span></td></tr>
        <tr><td><span class="label">Planned quarter</span><span class="value">{{ $item->planned_quarter ?: 'Not set' }}</span></td><td><span class="label">Planned start</span><span class="value">{{ $item->planned_start_date?->format('d M Y') ?: 'Not set' }}</span></td><td><span class="label">Planned end</span><span class="value">{{ $item->planned_end_date?->format('d M Y') ?: 'Not set' }}</span></td></tr>
        <tr><td><span class="label">Process status</span><span class="value">{{ $item->source_process_status ?: 'Not set' }}</span></td><td><span class="label">Activity status</span><span class="value">{{ $item->source_activity_status ?: $item->workflowActivityStatus() }}</span></td><td><span class="label">In process</span><span class="value">{{ $item->source_in_process ?: 'Not set' }}</span></td></tr>
    </table>
</div>

@if($item->limited_selection_justification || $item->budget_reference || $item->bank_comment || $item->action_taken)
<div class="section">
    <div class="section-title">Workbook controls and comments</div>
    <table class="facts"><tr>
        <td><span class="label">Limited selection justification</span><span class="value">{{ $item->limited_selection_justification ?: 'Not applicable' }}</span></td>
        <td><span class="label">Budget reference</span><span class="value">{{ $item->budget_reference ?: 'Not recorded' }}</span></td>
        <td><span class="label">World Bank comment / action</span><span class="value">{{ $item->bank_comment ?: 'No comment' }}@if($item->action_taken)<br>{{ $item->action_taken }}@endif</span></td>
    </tr></table>
</div>
@endif

<div class="section">
    <div class="section-title">Private document register</div>
    <table class="docs"><thead><tr><th style="width:23%">Type</th><th>Document</th><th style="width:18%">Size</th><th style="width:25%">Uploaded by</th></tr></thead><tbody>
    @forelse($item->documents as $document)
        <tr><td>{{ Str::headline($document->document_type) }}</td><td>{{ $document->document_name ?: $document->original_name }}<br><span class="muted">{{ $document->original_name }}</span></td><td>{{ $document->formatted_size }}</td><td>{{ $document->uploader?->name ?: 'System user' }}</td></tr>
    @empty
        <tr><td colspan="4" class="muted">No document is attached to this item.</td></tr>
    @endforelse
    </tbody></table>
</div>

@if($milestones->isNotEmpty())
<div class="section">
    <div class="section-title">Method milestone schedule</div>
    <table class="milestones"><thead><tr><th>Milestone</th><th style="width:22%">Timing</th><th style="width:28%">Date / value</th></tr></thead><tbody>
    @foreach($milestones as $milestone)
        <tr><td>{{ $milestone['milestone'] ?? Str::headline($milestone['key'] ?? 'Milestone') }}</td><td>{{ Str::headline($milestone['timing'] ?? 'Schedule') }}</td><td>{{ $milestone['date'] ?? $milestone['value'] ?? 'Not scheduled' }}</td></tr>
    @endforeach
    </tbody></table>
</div>
@endif

@if($item->review_reason)
<div class="section"><div class="section-title">Latest Secretariat review instruction</div><div class="note">{{ $item->review_reason }}</div></div>
@endif

@if(in_array($item->status, ['no_objection_obtained', 'published'], true))
<div class="section">
    <div class="section-title">World Bank no-objection and readiness record</div>
    <div class="clearance"><strong>No-objection received / ready to execute</strong><br>STEP reference: {{ $item->step_reference ?: 'Not recorded' }} · Decision reference: {{ $item->no_objection_reference ?: 'Not recorded' }} · Decision date: {{ $item->no_objection_date?->format('d M Y') ?: 'Not recorded' }}@if($item->no_objection_notes)<br>{{ $item->no_objection_notes }}@endif</div>
</div>
@endif

<div class="section">
    <div class="section-title">Status and decision timeline</div>
    <table class="timeline">
    @forelse($timeline as $event)
        <tr class="event"><td class="marker">{{ $loop->iteration }}</td><td><span class="event-title">{{ Str::headline($event->action) }} @if(!$event->item_id)· Plan event @endif</span><br><span class="muted">{{ $event->actor?->name ?: 'System' }}@if($event->from_status || $event->to_status) · {{ Str::headline($event->from_status ?: 'created') }} to {{ Str::headline($event->to_status ?: 'recorded') }}@endif</span>@if($event->reason)<br>{{ $event->reason }}@endif @if(($event->notifications_queued_count + $event->notifications_sent_count + $event->notifications_failed_count) > 0)<br><span class="muted">Notification delivery: {{ $event->notifications_queued_count }} queued · {{ $event->notifications_sent_count }} sent · {{ $event->notifications_failed_count }} failed</span>@endif</td><td class="time">{{ $event->created_at?->format('d M Y H:i') }}</td></tr>
    @empty
        <tr><td class="muted">No workflow event has been recorded.</td></tr>
    @endforelse
    </table>
</div>

<footer class="footer"><table class="footer-table"><tr><td>{{ $platformName }}</td><td>{{ $item->item_code }} · {{ $plan->plan_code }}</td><td>{{ $platformUrl }}</td></tr></table></footer>
</body>
</html>
