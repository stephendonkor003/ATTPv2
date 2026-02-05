@extends('layouts.partner')

@section('content')
<div class="nxl-container">
    <div class="page-header">
        <div>
            <h4 class="fw-bold mb-1">{{ __('partner.funded_programs') }}</h4>
            <p class="text-muted mb-0">{{ __('partner.programs_description') }}</p>
        </div>
    </div>

    <div class="card shadow-sm mt-3">
        <div class="card-body">
            <table id="programsTable" class="table table-striped table-hover {{ $fundings->count() > 0 ? 'data-table' : '' }}" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 50px;" class="text-center">#</th>
                        <th>{{ __('partner.program_name') }}</th>
                        <th>{{ __('partner.governance_node') }}</th>
                        <th style="width: 150px;" class="text-end">{{ __('partner.amount') }}</th>
                        <th style="width: 120px;">{{ __('partner.period') }}</th>
                        <th style="width: 140px;" class="text-center no-sort no-export">{{ __('partner.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($fundings as $funding)
                    <tr>
                        <td class="text-center">{{ $loop->iteration }}</td>
                        <td><strong>{{ $funding->program_name ?? ($funding->program?->name ?? '—') }}</strong></td>
                        <td>
                            <div class="fw-semibold">{{ $funding->governanceNode->name ?? '-' }}</div>
                            <div class="text-muted small">{{ $funding->governanceNode->level->name ?? '' }}</div>
                        </td>
                        <td class="text-end">
                            <strong>{{ $funding->currency ?? $funder->currency }} {{ number_format($funding->approved_amount, 2) }}</strong>
                        </td>
                        <td>{{ $funding->start_year }} - {{ $funding->end_year }}</td>
                        <td class="text-center no-export">
                            <div class="btn-group" role="group">
                                <a href="{{ route('partner.programs.show', $funding->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('partner.view_details') }}">
                                    <i class="feather-eye"></i>
                                </a>
                                <a href="{{ route('partner.programs.report', $funding->id) }}" class="btn btn-sm btn-outline-secondary" title="{{ __('partner.program_report') }}">
                                    <i class="feather-file-text"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center text-muted py-4">{{ __('partner.no_programs') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
