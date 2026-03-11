<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Partner CRM Report</title>
    <style>
        @page {
            margin: 28px 24px 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            line-height: 1.45;
            color: #1f2937;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .header {
            background: #0f172a;
            color: #ffffff;
            padding: 18px 20px;
            margin-bottom: 14px;
        }

        .header td {
            vertical-align: top;
        }

        .header-eyebrow {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.16em;
            color: #d4af37;
            margin-bottom: 5px;
        }

        .header-title {
            font-size: 23px;
            font-weight: bold;
            margin: 0 0 4px;
            color: #ffffff;
        }

        .header-subtitle {
            font-size: 9.5px;
            color: #cbd5e1;
        }

        .status-band {
            margin-top: 10px;
        }

        .status-tag {
            display: inline-block;
            padding: 4px 10px;
            margin-right: 6px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .tag-navy { background: #1e293b; color: #ffffff; }
        .tag-teal { background: #ccfbf1; color: #115e59; }
        .tag-gold { background: #fef3c7; color: #92400e; }
        .tag-slate { background: #e2e8f0; color: #334155; }
        .tag-red { background: #fee2e2; color: #991b1b; }

        .meta-card {
            border: 1px solid rgba(255, 255, 255, 0.14);
            background: #172554;
            padding: 10px 12px;
        }

        .meta-card td {
            font-size: 8.5px;
            color: #dbeafe;
            padding: 3px 0;
        }

        .meta-card td:first-child {
            width: 40%;
            color: #93c5fd;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .summary-table {
            margin-bottom: 14px;
        }

        .summary-table td {
            width: 25%;
            padding-right: 8px;
            vertical-align: top;
        }

        .summary-table td:last-child {
            padding-right: 0;
        }

        .summary-card {
            border: 1px solid #dbe4ee;
            border-top: 4px solid #0f766e;
            background: #ffffff;
            padding: 11px 12px;
        }

        .summary-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.09em;
            color: #64748b;
        }

        .summary-value {
            margin-top: 7px;
            font-size: 17px;
            font-weight: bold;
            color: #0f172a;
        }

        .summary-note {
            margin-top: 4px;
            font-size: 8.5px;
            color: #64748b;
        }

        .section {
            margin-bottom: 14px;
        }

        .section-title {
            border-bottom: 2px solid #c48b2a;
            padding-bottom: 5px;
            margin-bottom: 8px;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0f172a;
        }

        .two-col td {
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }

        .two-col td:last-child {
            padding-right: 0;
        }

        .panel {
            border: 1px solid #dbe4ee;
            background: #ffffff;
            padding: 10px 12px;
            page-break-inside: avoid;
        }

        .panel-heading {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #0f172a;
            margin-bottom: 8px;
        }

        .detail-table td {
            padding: 5px 0;
            border-bottom: 1px solid #edf2f7;
            vertical-align: top;
        }

        .detail-table tr:last-child td {
            border-bottom: none;
        }

        .detail-table td:first-child {
            width: 38%;
            color: #64748b;
            padding-right: 8px;
        }

        .narrative-box {
            margin-top: 10px;
            border-left: 4px solid #0f766e;
            background: #f8fafc;
            padding: 10px 12px;
        }

        .mini-status-table td {
            width: 33.333%;
            padding-right: 8px;
            vertical-align: top;
        }

        .mini-status-table td:last-child {
            padding-right: 0;
        }

        .mini-status-card {
            border: 1px solid #dbe4ee;
            background: #ffffff;
            padding: 9px 10px;
        }

        .mini-status-card .label {
            font-size: 7.5px;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: #64748b;
        }

        .mini-status-card .value {
            margin-top: 5px;
            font-size: 13px;
            font-weight: bold;
            color: #0f172a;
        }

        .data-table thead {
            display: table-header-group;
        }

        .data-table th {
            background: #0f172a;
            color: #ffffff;
            text-align: left;
            font-size: 8.5px;
            font-weight: bold;
            padding: 8px;
            border: 1px solid #0f172a;
        }

        .data-table td {
            border: 1px solid #dbe4ee;
            padding: 7px 8px;
            vertical-align: top;
            font-size: 8.8px;
        }

        .data-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .amount {
            text-align: right;
            white-space: nowrap;
            font-weight: bold;
            color: #065f46;
        }

        .muted {
            color: #64748b;
        }

        .center {
            text-align: center;
        }

        .empty {
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            color: #64748b;
            text-align: center;
            padding: 10px;
        }

        .footer-note {
            margin-top: 16px;
            font-size: 8px;
            color: #64748b;
            text-align: right;
        }
    </style>
</head>
<body>
    @php
        $latestCommunication = $funder->latest_communication;
        $currencyBreakdown = collect($funder->currency_breakdown ?? []);
        $requestCount = $funder->informationRequests->count();
        $statusTag = match ($funder->partnership_status) {
            'active' => 'tag-teal',
            'at_risk' => 'tag-gold',
            'closed' => 'tag-navy',
            default => 'tag-slate',
        };
        $communicationTag = match (data_get($latestCommunication, 'status')) {
            'attended' => 'tag-teal',
            'follow_up_needed' => 'tag-gold',
            'pending' => 'tag-red',
            default => 'tag-slate',
        };
    @endphp

    <div class="header">
        <table>
            <tr>
                <td>
                    <div class="header-eyebrow">ATTP Partner Relationship Dossier</div>
                    <div class="header-title">{{ $funder->name }}</div>
                    <div class="header-subtitle">
                        Elegant CRM summary covering relationship ownership, communication health, supported programs, and portfolio performance.
                    </div>

                    <div class="status-band">
                        <span class="status-tag tag-navy">{{ $funder->formatStatusLabel($funder->type) }}</span>
                        <span class="status-tag {{ $statusTag }}">{{ $funder->formatStatusLabel($funder->partnership_status) }}</span>
                        <span class="status-tag {{ $funder->hasPortalAccess() ? 'tag-teal' : 'tag-slate' }}">
                            {{ $funder->hasPortalAccess() ? 'Portal Enabled' : 'Portal Disabled' }}
                        </span>
                    </div>
                </td>
                <td style="width: 270px;">
                    <div class="meta-card">
                        <table>
                            <tr>
                                <td>Report Date</td>
                                <td>{{ now()->format('d M Y') }}</td>
                            </tr>
                            <tr>
                                <td>Generated At</td>
                                <td>{{ now()->format('H:i') }}</td>
                            </tr>
                            <tr>
                                <td>Manager</td>
                                <td>{{ $funder->relationshipManager?->name ?: 'Unassigned' }}</td>
                            </tr>
                            <tr>
                                <td>Partner ID</td>
                                <td>{{ strtoupper(\Illuminate\Support\Str::limit($funder->id, 12, '')) }}</td>
                            </tr>
                            <tr>
                                <td>Next Follow-up</td>
                                <td>{{ optional($funder->next_follow_up_at)->format('d M Y') ?: 'Not scheduled' }}</td>
                            </tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <table class="summary-table">
        <tr>
            <td>
                <div class="summary-card">
                    <div class="summary-label">Programs Supported</div>
                    <div class="summary-value">{{ number_format((int) ($funder->total_programs_supported ?? 0)) }}</div>
                    <div class="summary-note">{{ number_format($funder->programFundings->count()) }} funding records linked</div>
                </div>
            </td>
            <td>
                <div class="summary-card">
                    <div class="summary-label">USD Portfolio</div>
                    <div class="summary-value">USD {{ number_format((float) ($funder->total_amount_usd ?? 0), 2) }}</div>
                    <div class="summary-note">Aggregated from USD-denominated entries</div>
                </div>
            </td>
            <td>
                <div class="summary-card">
                    <div class="summary-label">Open Requests</div>
                    <div class="summary-value">{{ number_format((int) ($funder->open_requests_count ?? 0)) }}</div>
                    <div class="summary-note">{{ number_format((int) ($funder->resolved_requests_count ?? 0)) }} resolved</div>
                </div>
            </td>
            <td>
                <div class="summary-card">
                    <div class="summary-label">Last Engagement</div>
                    <div class="summary-value" style="font-size: 13px;">
                        {{ optional($funder->last_engagement_at)->format('d M Y') ?: 'Not logged' }}
                    </div>
                    <div class="summary-note">{{ optional($funder->last_engagement_at)->format('H:i') ?: 'Awaiting first touchpoint' }}</div>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">Executive Overview</div>
        <table class="two-col">
            <tr>
                <td>
                    <div class="panel">
                        <div class="panel-heading">Partner Profile</div>
                        <table class="detail-table">
                            <tr><td>Primary contact</td><td>{{ $funder->contact_person ?: 'Not set' }}</td></tr>
                            <tr><td>Email</td><td>{{ $funder->contact_email ?: 'Not set' }}</td></tr>
                            <tr><td>Phone</td><td>{{ $funder->contact_phone ?: 'Not set' }}</td></tr>
                            <tr><td>Default currency</td><td>{{ strtoupper($funder->currency ?: 'N/A') }}</td></tr>
                            <tr><td>Portal account</td><td>{{ $funder->portalUser?->email ?: 'Portal disabled' }}</td></tr>
                            <tr><td>Created on</td><td>{{ optional($funder->created_at)->format('d M Y H:i') ?: 'N/A' }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <div class="panel-heading">Relationship Management</div>
                        <table class="detail-table">
                            <tr><td>Correspondent / manager</td><td>{{ $funder->relationshipManager?->name ?: 'Unassigned' }}</td></tr>
                            <tr><td>Partnership status</td><td>{{ $funder->formatStatusLabel($funder->partnership_status) }}</td></tr>
                            <tr><td>Partnership started</td><td>{{ optional($funder->partnership_started_at)->format('d M Y') ?: 'Not set' }}</td></tr>
                            <tr><td>Next follow-up</td><td>{{ optional($funder->next_follow_up_at)->format('d M Y') ?: 'Not scheduled' }}</td></tr>
                            <tr><td>Last manual communication</td><td>{{ optional($funder->last_contact_at)->format('d M Y H:i') ?: 'Not logged' }}</td></tr>
                            <tr><td>Handled by</td><td>{{ $funder->lastContactOwner?->name ?: 'Not assigned' }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="narrative-box">
            <strong>Executive brief:</strong>
            {{ $funder->name }} is currently in the
            <strong>{{ strtolower($funder->formatStatusLabel($funder->partnership_status)) }}</strong> stage,
            supports <strong>{{ number_format((int) ($funder->total_programs_supported ?? 0)) }}</strong> programs,
            and has <strong>{{ number_format((int) ($funder->open_requests_count ?? 0)) }}</strong> open partner request(s).
        </div>
    </div>

    <div class="section">
        <div class="section-title">Communication & CRM Notes</div>
        <table class="two-col">
            <tr>
                <td>
                    <div class="panel">
                        <div class="panel-heading">Latest Communication</div>
                        <table class="detail-table">
                            <tr><td>Source</td><td>{{ data_get($latestCommunication, 'label', 'No communication logged') }}</td></tr>
                            <tr><td>Subject</td><td>{{ data_get($latestCommunication, 'subject', 'N/A') }}</td></tr>
                            <tr>
                                <td>Status</td>
                                <td><span class="status-tag {{ $communicationTag }}">{{ $funder->formatStatusLabel(data_get($latestCommunication, 'status')) }}</span></td>
                            </tr>
                            <tr><td>Handled by</td><td>{{ data_get($latestCommunication, 'owner_name', 'Unassigned') }}</td></tr>
                            <tr><td>Occurred at</td><td>{{ optional(data_get($latestCommunication, 'occurred_at'))->format('d M Y H:i') ?: 'N/A' }}</td></tr>
                        </table>

                        <div class="narrative-box">
                            {!! nl2br(e(data_get($latestCommunication, 'notes', 'No communication notes captured yet.'))) !!}
                        </div>
                    </div>
                </td>
                <td>
                    <div class="panel">
                        <div class="panel-heading">CRM Notes & Immediate Action</div>
                        <div class="narrative-box" style="margin-top: 0;">
                            {!! nl2br(e($funder->notes ?: 'No internal CRM notes added yet.')) !!}
                        </div>

                        <table class="mini-status-table" style="margin-top: 10px;">
                            <tr>
                                <td>
                                    <div class="mini-status-card">
                                        <div class="label">Requests Logged</div>
                                        <div class="value">{{ number_format($requestCount) }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="mini-status-card">
                                        <div class="label">Resolved</div>
                                        <div class="value">{{ number_format((int) ($funder->resolved_requests_count ?? 0)) }}</div>
                                    </div>
                                </td>
                                <td>
                                    <div class="mini-status-card">
                                        <div class="label">Follow-up</div>
                                        <div class="value">{{ optional($funder->next_follow_up_at)->format('d M') ?: 'TBD' }}</div>
                                    </div>
                                </td>
                            </tr>
                        </table>

                        @if($funder->latest_request)
                            <div class="narrative-box">
                                <strong>Latest request:</strong> {{ $funder->latest_request->subject }}.
                                Status:
                                <strong>{{ strtolower($funder->formatStatusLabel($funder->latest_request->status)) }}</strong>.
                                Requested on {{ optional($funder->latest_request->created_at)->format('d M Y H:i') ?: 'N/A' }}.
                            </div>
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Funding Portfolio</div>

        @if($currencyBreakdown->isNotEmpty())
            <table class="data-table" style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th style="width: 30%;">Currency</th>
                        <th style="width: 18%;">Funding Records</th>
                        <th>Total Amount</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($currencyBreakdown as $row)
                        <tr>
                            <td>{{ $row['currency'] }}</td>
                            <td class="center">{{ $row['count'] }}</td>
                            <td class="amount">{{ $row['currency'] }} {{ number_format((float) $row['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <table class="data-table">
            <thead>
                <tr>
                    <th>Program</th>
                    <th>Department</th>
                    <th>Governance Node</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse($funder->programFundings as $funding)
                    <tr>
                        <td>
                            {{ optional($funding->program)->name ?: ($funding->program_name ?: 'Unnamed program') }}
                            @if($funding->creator)
                                <div class="muted">Created by {{ $funding->creator->name }}</div>
                            @endif
                        </td>
                        <td>{{ $funding->department?->name ?: 'N/A' }}</td>
                        <td>{{ $funding->governanceNode?->name ?: 'N/A' }}</td>
                        <td>{{ $funding->start_year ?: 'N/A' }} - {{ $funding->end_year ?: 'N/A' }}</td>
                        <td>{{ $funder->formatStatusLabel($funding->status) }}</td>
                        <td class="amount">{{ strtoupper($funding->currency ?: $funder->currency ?: 'USD') }} {{ number_format((float) ($funding->approved_amount ?? 0), 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6">
                            <div class="empty">No funding records linked to this partner.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Partner Request Register</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Subject</th>
                    <th>Type</th>
                    <th>Requested</th>
                    <th>Status</th>
                    <th>Responded By</th>
                </tr>
            </thead>
            <tbody>
                @forelse($funder->informationRequests->take(10) as $request)
                    <tr>
                        <td>
                            {{ $request->subject }}
                            <div class="muted">{{ optional($request->programFunding?->program)->name ?: 'General request' }}</div>
                        </td>
                        <td>{{ $funder->formatStatusLabel($request->request_type) }}</td>
                        <td>{{ optional($request->created_at)->format('d M Y H:i') ?: 'N/A' }}</td>
                        <td>{{ $funder->formatStatusLabel($request->status) }}</td>
                        <td>
                            {{ $request->responder?->name ?: 'Awaiting response' }}
                            @if($request->responded_at)
                                <div class="muted">{{ $request->responded_at->format('d M Y H:i') }}</div>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5">
                            <div class="empty">No information requests recorded yet.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Recent Portal Activity</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 24%;">Activity</th>
                    <th style="width: 18%;">User</th>
                    <th style="width: 18%;">When</th>
                    <th>Metadata</th>
                </tr>
            </thead>
            <tbody>
                @forelse($funder->activityLogs as $activity)
                    <tr>
                        <td>{{ $activity->getActionDescription() }}</td>
                        <td>{{ $activity->user?->name ?: 'System' }}</td>
                        <td>{{ optional($activity->created_at)->format('d M Y H:i') ?: 'N/A' }}</td>
                        <td>
                            @if(!empty($activity->metadata))
                                {{ collect($activity->metadata)->map(fn ($value, $key) => \Illuminate\Support\Str::headline($key) . ': ' . $value)->implode(' | ') }}
                            @else
                                <span class="muted">No extra metadata</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4">
                            <div class="empty">No activity recorded for this partner yet.</div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="footer-note">
        ATTP CRM export | {{ now()->format('d M Y H:i') }}
    </div>
</body>
</html>
