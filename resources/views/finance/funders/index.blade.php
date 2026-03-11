@extends('layouts.app')

@push('styles')
    @include('finance.funders.partials.crm-styles')
@endpush

@section('content')
    @php
        $totalPrograms = $funders->sum('total_programs_supported');
        $totalUsd = $funders->sum('total_amount_usd');
        $activePartners = $funders->where('partnership_status', 'active')->count();
        $portalEnabled = $funders->filter(fn ($partner) => $partner->hasPortalAccess())->count();
    @endphp

    <div class="nxl-container">
        <div class="page-header d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3">
            <div>
                <h4 class="fw-bold mb-1">Partners</h4>
                <p class="text-muted mb-0">
                    Track partner ownership, lifecycle, communication history, supported programs, and portfolio totals.
                </p>
            </div>

            @can('finance.funders.create')
                <a href="{{ route('finance.funders.create') }}" class="btn btn-primary">
                    <i class="feather-plus-circle me-1"></i>
                    Add New Partner
                </a>
            @endcan
        </div>

        @if (session('success'))
            <div class="alert alert-success mt-3">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mt-3">
                {{ $errors->first() }}
            </div>
        @endif

        <div class="row g-3 mt-1">
            <div class="col-md-6 col-xl-3">
                <div class="partner-page-stat p-3">
                    <div class="label mb-2">Total Partners</div>
                    <div class="value">{{ number_format($funders->count()) }}</div>
                    <div class="text-muted small">All registered partner organizations</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="partner-page-stat p-3">
                    <div class="label mb-2">Active Partnerships</div>
                    <div class="value">{{ number_format($activePartners) }}</div>
                    <div class="text-muted small">{{ number_format($portalEnabled) }} with portal access enabled</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="partner-page-stat p-3">
                    <div class="label mb-2">Programs Supported</div>
                    <div class="value">{{ number_format($totalPrograms) }}</div>
                    <div class="text-muted small">Distinct programs linked across partner portfolios</div>
                </div>
            </div>
            <div class="col-md-6 col-xl-3">
                <div class="partner-page-stat p-3">
                    <div class="label mb-2">Total USD Portfolio</div>
                    <div class="value" style="font-size: 1.35rem;">USD {{ number_format((float) $totalUsd, 2) }}</div>
                    <div class="text-muted small">Sum of partner funding records already stored in USD</div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm mt-4 border-0">
            <div class="card-body">
                <x-data-table id="fundersTable">
                    <thead class="table-light">
                        <tr>
                            <th width="50">#</th>
                            <th>Partner</th>
                            <th>Type</th>
                            <th>Correspondent</th>
                            <th>Programs</th>
                            <th>Total USD</th>
                            <th>Last Contact</th>
                            <th width="140" class="text-end">Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($funders as $funder)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>
                                    <div class="fw-semibold text-dark">{{ $funder->name }}</div>
                                    <div class="small text-muted">
                                        {{ $funder->contact_email ?: ($funder->portalUser?->email ?: 'No contact email') }}
                                    </div>
                                </td>
                                <td>
                                    <span class="badge {{ $funder->getTypeBadgeClass() }}">
                                        {{ $funder->formatStatusLabel($funder->type) }}
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $funder->relationshipManager?->name ?: 'Unassigned' }}</div>
                                    <div class="small text-muted">
                                        {{ $funder->partnership_status ? $funder->formatStatusLabel($funder->partnership_status) : 'Lifecycle not set' }}
                                    </div>
                                </td>
                                <td>{{ number_format((int) ($funder->total_programs_supported ?? 0)) }}</td>
                                <td>USD {{ number_format((float) ($funder->total_amount_usd ?? 0), 2) }}</td>
                                <td>
                                    @if($funder->last_contact_at)
                                        <div>{{ $funder->last_contact_at->format('d M Y') }}</div>
                                        <div class="small text-muted">{{ $funder->last_contact_at->format('H:i') }}</div>
                                    @else
                                        <span class="text-muted">Not logged</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <button type="button"
                                        class="btn btn-sm btn-outline-primary partner-crm-trigger"
                                        data-bs-toggle="modal"
                                        data-bs-target="#partnerCrmModal"
                                        data-partner-url="{{ route('finance.funders.show', ['funder' => $funder, 'modal' => 1]) }}"
                                        data-partner-name="{{ $funder->name }}"
                                        title="View Partner CRM">
                                        <i class="feather-eye"></i>
                                    </button>

                                    @can('finance.funders.edit')
                                        <a href="{{ route('finance.funders.edit', $funder) }}"
                                            class="btn btn-sm btn-outline-warning"
                                            title="Edit Partner">
                                            <i class="feather-edit"></i>
                                        </a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>
    </div>

    <div class="modal fade partner-crm-modal" id="partnerCrmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-scrollable">
            <div class="modal-content border-0">
                <div class="modal-header border-0 pb-0">
                    <div>
                        <div class="text-muted small text-uppercase">Partner CRM</div>
                        <h5 class="modal-title fw-bold mb-0" data-partner-modal-title>Partner Profile</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body pt-3" data-partner-modal-body>
                    <div class="partner-crm-loader">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <div>Loading partner CRM snapshot...</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const modalEl = document.getElementById('partnerCrmModal');

            if (!modalEl) {
                return;
            }

            const modalTitle = modalEl.querySelector('[data-partner-modal-title]');
            const modalBody = modalEl.querySelector('[data-partner-modal-body]');

            modalEl.addEventListener('show.bs.modal', function (event) {
                const trigger = event.relatedTarget;

                if (!trigger) {
                    return;
                }

                const url = trigger.getAttribute('data-partner-url');
                const partnerName = trigger.getAttribute('data-partner-name') || 'Partner Profile';

                modalTitle.textContent = partnerName;
                modalBody.innerHTML = `
                    <div class="partner-crm-loader">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status"></div>
                            <div>Loading partner CRM snapshot...</div>
                        </div>
                    </div>
                `;

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then((response) => {
                        if (!response.ok) {
                            throw new Error('Unable to load the partner profile.');
                        }

                        return response.text();
                    })
                    .then((html) => {
                        modalBody.innerHTML = html;
                    })
                    .catch((error) => {
                        modalBody.innerHTML = `
                            <div class="alert alert-danger mb-0">
                                ${error.message || 'Unable to load the partner profile at the moment.'}
                            </div>
                        `;
                    });
            });
        });
    </script>
@endpush
