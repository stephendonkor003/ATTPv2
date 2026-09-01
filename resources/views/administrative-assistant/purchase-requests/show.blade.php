@extends('layouts.administrative-assistant')

@section('title', $intake->reference_no)
@section('workspace-kicker', 'Purchase request intake')
@section('workspace-heading', 'Track your request and supporting documents')

@push('styles')
<style>
    .pr-show-label { color: #667085; font-size: .72rem; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
    .pr-show-value { color: var(--aa-navy); font-weight: 750; }
    .pr-status-submitted { color: #854d0e; background: #fef3c7; border: 1px solid #fde68a; }
    .pr-status-converted { color: #067647; background: #ecfdf3; border: 1px solid #abefc6; }
    .pr-item { padding: 16px 0; border-bottom: 1px solid var(--aa-border); }
    .pr-item:last-child { border-bottom: 0; }
    .pr-doc { display: flex; align-items: center; gap: 12px; padding: 13px 0; border-bottom: 1px solid var(--aa-border); }
    .pr-doc:last-child { border-bottom: 0; }
    .pr-doc-icon { width: 42px; height: 42px; flex: 0 0 42px; display: grid; place-items: center; color: var(--aa-teal); background: var(--aa-mint); border: 1px solid #b9e8de; border-radius: 11px; }
</style>
@endpush

@section('content')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-start gap-3 mb-4">
        <div>
            <div class="aa-topbar-kicker mb-2">Purchase request intake</div>
            <h1 class="aa-page-title mb-2">{{ $intake->reference_no }}</h1>
            <p class="text-muted mb-0">Submitted {{ $intake->created_at?->format('D, j M Y \a\t H:i') }} for back-office completion.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <span class="badge rounded-pill px-3 py-2 {{ $intake->status === 'converted' ? 'pr-status-converted' : 'pr-status-submitted' }}">
                <i class="feather-{{ $intake->status === 'converted' ? 'check-circle' : 'clock' }} me-1"></i>
                {{ \Illuminate\Support\Str::headline($intake->status) }}
            </span>
            <a href="{{ route('administrative-assistant.purchase-requests.create') }}" class="btn btn-aa-soft btn-sm">
                <i class="feather-plus me-1"></i> Create another PR
            </a>
        </div>
    </div>

    @if ($intake->status === 'converted')
        <div class="alert alert-success border-0 shadow-sm mb-4">
            <i class="feather-check-circle me-2"></i>
            The back office completed this intake
            @if ($intake->convertedPurchaseRequest)
                as purchase request <strong>{{ $intake->convertedPurchaseRequest->reference_no }}</strong>
            @endif.
        </div>
    @else
        <div class="alert alert-info border-0 shadow-sm mb-4">
            <i class="feather-info me-2"></i>
            This request is waiting for the back office to assign funding, confirm budget availability, and complete the formal PR.
        </div>
    @endif

    <div class="row g-4 align-items-start">
        <div class="col-xl-8">
            <section class="aa-card p-4 mb-4">
                <h4 class="fw-bold mb-4">Request details</h4>
                <div class="row g-4 mb-4">
                    <div class="col-md-6">
                        <div class="pr-show-label mb-1">Title</div>
                        <div class="pr-show-value">{{ $intake->title }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="pr-show-label mb-1">Priority</div>
                        <div class="pr-show-value">{{ \Illuminate\Support\Str::headline($intake->priority) }}</div>
                    </div>
                    <div class="col-md-3">
                        <div class="pr-show-label mb-1">Needed by</div>
                        <div class="pr-show-value">{{ $intake->needed_by?->format('d M Y') ?? 'Not specified' }}</div>
                    </div>
                    <div class="col-md-6">
                        <div class="pr-show-label mb-1">Estimated amount</div>
                        <div class="pr-show-value">
                            @if ($intake->estimated_amount !== null)
                                {{ $intake->currency }} {{ number_format((float) $intake->estimated_amount, 2) }}
                            @else
                                Not specified
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="pr-show-label mb-1">Governance node</div>
                        <div class="pr-show-value">{{ $intake->governanceNode?->name ?? 'To be assigned by back office' }}</div>
                    </div>
                </div>

                <div class="pr-show-label mb-2">Purpose and details</div>
                <div class="text-break" style="white-space: pre-line;">{{ $intake->description }}</div>
            </section>

            <section class="aa-card p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                    <h4 class="fw-bold mb-0">Requested items</h4>
                    <span class="badge bg-light text-dark border">{{ $intake->items->count() }}</span>
                </div>
                @foreach ($intake->items as $item)
                    <div class="pr-item">
                        <div class="d-flex justify-content-between align-items-start gap-3">
                            <div class="min-w-0">
                                <div class="fw-bold text-break">{{ $item->name }}</div>
                                @if ($item->notes)
                                    <div class="small text-muted mt-1 text-break" style="white-space: pre-line;">{{ $item->notes }}</div>
                                @endif
                            </div>
                            <span class="badge bg-light text-dark border flex-shrink-0">Qty {{ number_format((float) $item->quantity, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </section>
        </div>

        <div class="col-xl-4">
            <aside class="aa-card p-4">
                <div class="d-flex justify-content-between align-items-center gap-3 mb-2">
                    <h5 class="fw-bold mb-0">Supporting documents</h5>
                    <span class="badge bg-light text-dark border">{{ $intake->documents->count() }}</span>
                </div>

                @forelse ($intake->documents as $document)
                    @php
                        $extension = strtolower(pathinfo($document->file_name, PATHINFO_EXTENSION));
                        $icon = match (true) {
                            in_array($extension, ['jpg', 'jpeg', 'png'], true) => 'image',
                            in_array($extension, ['xls', 'xlsx', 'csv'], true) => 'grid',
                            in_array($extension, ['ppt', 'pptx'], true) => 'monitor',
                            $extension === 'zip' => 'archive',
                            default => 'file-text',
                        };
                        $size = (int) $document->file_size_bytes;
                        $sizeLabel = $size >= 1024 * 1024
                            ? number_format($size / (1024 * 1024), 1).' MB'
                            : max(1, (int) ceil($size / 1024)).' KB';
                    @endphp
                    <div class="pr-doc">
                        <span class="pr-doc-icon"><i class="feather-{{ $icon }}"></i></span>
                        <div class="min-w-0 flex-grow-1">
                            <div class="fw-semibold text-truncate" title="{{ $document->file_name }}">{{ $document->file_name }}</div>
                            <div class="small text-muted">{{ strtoupper($extension ?: 'FILE') }} &middot; {{ $sizeLabel }}</div>
                            <div class="d-flex flex-wrap gap-2 mt-2">
                                <a href="{{ route('administrative-assistant.purchase-requests.documents.download', [$intake, $document]) }}" target="_blank" rel="noopener" class="btn btn-sm btn-aa-soft">
                                    <i class="feather-eye me-1"></i> Open
                                </a>
                                <a href="{{ route('administrative-assistant.purchase-requests.documents.download', [$intake, $document, 'download' => 1]) }}" class="btn btn-sm btn-light border">
                                    <i class="feather-download me-1"></i> Download
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-4">
                        <i class="feather-file fs-3 text-muted"></i>
                        <p class="text-muted small mb-0 mt-2">No supporting documents were attached.</p>
                    </div>
                @endforelse
            </aside>
        </div>
    </div>
@endsection
