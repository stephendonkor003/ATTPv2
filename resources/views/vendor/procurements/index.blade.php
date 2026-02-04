@extends('layouts.vendor')

@section('title', 'Open Procurements')

@section('content')
    <div class="mb-4">
        <h3 class="mb-1">Open Procurements</h3>
        <p class="text-muted mb-0">Procurements available for your vendor category.</p>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (!$vendorCategory)
        <div class="alert alert-warning">
            Your vendor account does not have a category assigned. Please contact the administrator to gain access to
            group procurements.
        </div>
    @endif

    <div class="row g-4">
        @forelse ($procurements as $procurement)
            <div class="col-lg-6">
                <div class="card vendor-card h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge-soft">{{ $procurement->reference_no ?? 'N/A' }}</span>
                            <span class="status-pill">{{ ucfirst($procurement->status ?? 'published') }}</span>
                        </div>
                        <h5 class="mb-2">{{ $procurement->title }}</h5>
                        <p class="text-muted small mb-3">
                            {{ \Illuminate\Support\Str::limit(strip_tags($procurement->description ?? ''), 140) }}
                        </p>
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div class="small text-muted">
                                Closes:
                                <strong>{{ $procurement->application_end_date?->format('M d, Y') ?? 'N/A' }}</strong>
                            </div>
                            <a href="{{ route('vendor.procurements.show', $procurement) }}" class="btn btn-vendor btn-sm">
                                View & Apply
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12">
                <div class="card vendor-card">
                    <div class="card-body text-muted">
                        No open procurements are available for your vendor category at the moment.
                    </div>
                </div>
            </div>
        @endforelse
    </div>
@endsection
