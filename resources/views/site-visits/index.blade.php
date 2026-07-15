@extends('layouts.app')
@section('title', 'Site Visits')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <div class="page-header">
                <div class="page-header-left">
                    <h5 class="m-b-10">Site Visits</h5>
                </div>

                @can('site_visits.create')
                    <a href="{{ route('site-visits.create') }}" class="btn btn-primary">
                        Create Site Visit
                    </a>
                @endcan

            </div>

            <div class="main-content">
                <div class="card">
                    <div class="card-body">

                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Applicant</th>
                                    <th>Procurement</th>
                                    <th>Visit Date</th>
                                    <th>Assignment</th>
                                    <th>Status</th>
                                    <th width="120">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($siteVisits as $visit)
                                    <tr>
                                        <td>
                                            {{ $visit->submission?->display_name ?? '-' }}
                                        </td>
                                        <td>
                                            {{ $visit->procurement->title ?? '-' }}
                                        </td>
                                        <td>
                                            {{ $visit->visit_date->format('d M Y') }}
                                        </td>
                                        <td>
                                            {{ ucfirst($visit->assignment_type) }}
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                {{ ucfirst($visit->status) }}
                                            </span>
                                        </td>
                                        <td>
                                            <a href="{{ route('site-visits.show', $visit) }}"
                                                class="btn btn-sm btn-outline-primary">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>

                        @if ($siteVisits->isEmpty())
                            <div class="border-top text-center text-muted py-4">
                                No site visits found for your portfolio.
                            </div>
                        @endif

                    </div>
                </div>
            </div>

        </div>
    </main>
@endsection
