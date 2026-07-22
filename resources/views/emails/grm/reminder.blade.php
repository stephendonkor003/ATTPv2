@php
    $isEscalation = $noticeType === 'escalation';
    $emailTitle = ($isEscalation ? 'Grievance escalation: ' : 'Grievance reminder: ').$grievance->case_number;
    $emailPreheader = 'Case '.$grievance->case_number.' requires attention.';
    $emailEyebrow = $isEscalation ? 'Escalation notice' : 'Response reminder';
    $emailHeading = $isEscalation ? 'This grievance has reached escalation' : 'This grievance requires attention';
    $emailSubheading = 'Please review the case timeline and record the next action in the secured GRM workspace.';
    $emailAccent = $isEscalation ? '#dc2626' : '#d4a017';
@endphp

@extends('emails.grm.layout')

@section('content')
    <p style="margin:0 0 18px;">A grievance case requires attention based on the configured acknowledgement, response, and resolution timeline.</p>

    @if (! $isEscalation && $rule?->reminder_body)
        <div style="margin:0 0 20px;white-space:pre-line;">{{ $rule->reminder_body }}</div>
    @endif

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="margin:0 0 20px;border-collapse:separate;border-spacing:0;border:1px solid #dfe7e3;border-radius:9px;overflow:hidden;">
        <tr>
            <td style="width:34%;padding:11px 13px;background:#f7faf8;border-bottom:1px solid #e3ebe7;color:#607068;font-size:12px;font-weight:700;">Case number</td>
            <td style="padding:11px 13px;border-bottom:1px solid #e3ebe7;color:#10543b;font-weight:800;">{{ $grievance->case_number }}</td>
        </tr>
        <tr>
            <td style="padding:11px 13px;background:#f7faf8;border-bottom:1px solid #e3ebe7;color:#607068;font-size:12px;font-weight:700;">Subject</td>
            <td style="padding:11px 13px;border-bottom:1px solid #e3ebe7;color:#25362d;">{{ $grievance->subject }}</td>
        </tr>
        <tr>
            <td style="padding:11px 13px;background:#f7faf8;border-bottom:1px solid #e3ebe7;color:#607068;font-size:12px;font-weight:700;">Program</td>
            <td style="padding:11px 13px;border-bottom:1px solid #e3ebe7;color:#25362d;">{{ $grievance->program?->name ?? 'Not specified' }}</td>
        </tr>
        <tr>
            <td style="padding:11px 13px;background:#f7faf8;border-bottom:1px solid #e3ebe7;color:#607068;font-size:12px;font-weight:700;">Current status</td>
            <td style="padding:11px 13px;border-bottom:1px solid #e3ebe7;color:#25362d;">{{ $grievance->status_label }}</td>
        </tr>
        <tr>
            <td style="padding:11px 13px;background:#f7faf8;color:#607068;font-size:12px;font-weight:700;">Response due</td>
            <td style="padding:11px 13px;color:{{ $isEscalation ? '#b91c1c' : '#9a3412' }};font-weight:700;">{{ $grievance->due_response_at?->format('d M Y, H:i') ?? 'Not set' }}</td>
        </tr>
    </table>

    <div style="margin:0;padding:14px 16px;border-left:4px solid {{ $emailAccent }};background:{{ $isEscalation ? '#fef2f2' : '#fff9e8' }};color:{{ $isEscalation ? '#7f1d1d' : '#5d4a13' }};border-radius:0 8px 8px 0;">
        <strong style="display:block;margin-bottom:3px;">Required next step</strong>
        Open the GRM logs, review the incident details, and record the acknowledgement, response, assignment, or escalation action.
    </div>
@endsection
