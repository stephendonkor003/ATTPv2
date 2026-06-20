<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <title>{{ $document['display_name'] ?? $document['name'] ?? 'Signed Document' }}</title>
        <style>
            @page { margin: 28px 32px; }
            * { box-sizing: border-box; }
            body {
                color: #0f172a;
                font-family: DejaVu Sans, sans-serif;
                font-size: 12px;
                line-height: 1.5;
            }
            .header {
                background: #064e3b;
                color: #fff;
                padding: 18px 20px;
                border-radius: 12px;
            }
            .title { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
            .subtitle { color: #bbf7d0; font-size: 11px; }
            .section {
                border: 1px solid #dbe4ef;
                border-radius: 10px;
                margin-top: 16px;
                padding: 14px 16px;
            }
            .section-title {
                color: #64748b;
                font-size: 10px;
                font-weight: 700;
                letter-spacing: .12em;
                margin-bottom: 10px;
                text-transform: uppercase;
            }
            table { border-collapse: collapse; width: 100%; }
            td { padding: 4px 0; vertical-align: top; }
            .label { color: #64748b; width: 34%; }
            .value { font-weight: 600; }
            .signed-image {
                border: 1px solid #cbd5e1;
                border-radius: 10px;
                margin-top: 12px;
                max-width: 100%;
                padding: 8px;
            }
            .note {
                color: #64748b;
                font-size: 10px;
                margin-top: 12px;
            }
        </style>
    </head>
    <body>
        @php
            $documentName = $document['display_name'] ?? $document['name'] ?? 'Signed document';
            $signedAt = ! empty($document['signed_at'])
                ? \Illuminate\Support\Carbon::parse($document['signed_at'])->format('M d, Y H:i')
                : (! empty($document['uploaded_at']) ? \Illuminate\Support\Carbon::parse($document['uploaded_at'])->format('M d, Y H:i') : 'N/A');
            $sourceDocument = $document['source_document_name'] ?? $documentName;
            $signedBy = $document['signed_by_name'] ?? optional($disbursement->vendor)->name ?? 'ATTP user';
            $verificationCode = $document['digital_signature_code'] ?? 'N/A';
        @endphp

        <div class="header">
            <div class="title">Signed Payment Document</div>
            <div class="subtitle">{{ config('app.name') }} | {{ $disbursement->reference_no ?? 'Payment receipt' }}</div>
        </div>

        <div class="section">
            <div class="section-title">Signature Record</div>
            <table>
                <tr>
                    <td class="label">Document</td>
                    <td class="value">{{ $documentName }}</td>
                </tr>
                <tr>
                    <td class="label">Source Document</td>
                    <td class="value">{{ $sourceDocument }}</td>
                </tr>
                <tr>
                    <td class="label">Signed By</td>
                    <td class="value">
                        {{ $signedBy }}
                        @if (! empty($document['signed_by_email']))
                            <br><span style="font-weight:400;">{{ $document['signed_by_email'] }}</span>
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="label">Signed At</td>
                    <td class="value">{{ $signedAt }}</td>
                </tr>
                <tr>
                    <td class="label">Digital Code</td>
                    <td class="value">{{ $verificationCode }}</td>
                </tr>
                <tr>
                    <td class="label">Signature Position</td>
                    <td class="value">{{ $document['signature_position'] ?? 'Recorded in signature workspace' }}</td>
                </tr>
            </table>
        </div>

        <div class="section">
            <div class="section-title">Payment Reference</div>
            <table>
                <tr>
                    <td class="label">Purchase Order</td>
                    <td class="value">{{ $disbursement->purchaseOrder?->reference_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Receipt</td>
                    <td class="value">{{ $disbursement->reference_no ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="label">Amount</td>
                    <td class="value">{{ $disbursement->resolved_currency }} {{ number_format((float) $disbursement->amount, 2) }}</td>
                </tr>
                <tr>
                    <td class="label">Paid Line</td>
                    <td class="value">
                        {{ $disbursement->purchaseRequestItem?->resource?->name
                            ?? $disbursement->purchaseRequestItem?->resourceCategory?->name
                            ?? 'N/A' }}
                    </td>
                </tr>
                <tr>
                    <td class="label">Deliverable</td>
                    <td class="value">{{ $disbursement->purchaseRequestItem?->milestone ?: ($disbursement->deliverable?->title ?? 'N/A') }}</td>
                </tr>
            </table>
        </div>

        @if ($imageDataUri)
            <div class="section">
                <div class="section-title">Stored Signed Copy</div>
                <img src="{{ $imageDataUri }}" class="signed-image" alt="Signed document">
            </div>
        @endif

        <div class="note">
            This PDF was generated from the stored signed payment document and may be viewed by ATTP and the linked vendor.
        </div>
    </body>
</html>
