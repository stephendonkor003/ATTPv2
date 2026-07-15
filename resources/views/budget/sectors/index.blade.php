@extends('layouts.app')
@section('title', 'Portfolios')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">

            <!-- Header -->
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">All Portfolios</h4>
                    <p class="text-muted mb-0">View and manage all available portfolios in the system.</p>
                </div>
                @can('sector.create')
                    <a href="{{ route('budget.portfolios.create') }}" class="btn btn-success">
                        <i class="bi bi-plus-circle me-1"></i> Add New Portfolio
                    </a>
                @endcan
            </div>

            <!-- Portfolio Table -->
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <x-data-table
                        id="sectorsTable"
                        :config="[
                            'order' => [[1, 'asc']],
                            'pageLength' => 25,
                            'lengthMenu' => [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                            'dom' => 'Bfrtip',
                            'buttons' => [
                                [
                                    'extend' => 'copy',
                                    'text' => '<i class=\"feather-copy\"></i> Copy',
                                    'className' => 'btn btn-sm btn-secondary'
                                ],
                                [
                                    'extend' => 'excel',
                                    'text' => '<i class=\"feather-file\"></i> Excel',
                                    'className' => 'btn btn-sm btn-success',
                                    'exportOptions' => ['columns' => ':visible:not(:last-child)']
                                ],
                                [
                                    'extend' => 'pdf',
                                    'text' => '<i class=\"feather-file-text\"></i> PDF',
                                    'className' => 'btn btn-sm btn-danger',
                                    'exportOptions' => ['columns' => ':visible:not(:last-child)']
                                ],
                                [
                                    'extend' => 'print',
                                    'text' => '<i class=\"feather-printer\"></i> Print',
                                    'className' => 'btn btn-sm btn-info',
                                    'exportOptions' => ['columns' => ':visible:not(:last-child)']
                                ],
                                [
                                    'extend' => 'colvis',
                                    'text' => '<i class=\"feather-eye\"></i> Columns',
                                    'className' => 'btn btn-sm btn-primary'
                                ]
                            ],
                            'columnDefs' => [
                                ['orderable' => false, 'targets' => [0, -1]],
                                ['searchable' => false, 'targets' => [0, -1]]
                            ]
                        ]"
                    >
                        <thead class="table-light">
                            <tr>
                                <th width="50">#</th>
                                <th>Portfolio Name</th>
                                <th>Description</th>
                                <th width="100">Programs</th>
                                <th width="120">Created</th>
                                <th width="150" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($sectors as $sector)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="fw-semibold text-dark">{{ $sector->name }}</div>
                                    </td>
                                    <td>
                                        <span class="text-muted">{{ Str::limit($sector->description, 60) ?? '—' }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary rounded-pill">{{ $sector->programs->count() }}</span>
                                    </td>
                                    <td>{{ $sector->created_at->format('d M Y') }}</td>
                                    <td class="text-end">
                                        <a href="{{ route('sectors.show', $sector->id) }}"
                                           class="btn btn-sm btn-info"
                                           title="View Details">
                                            <i class="feather-eye"></i>
                                        </a>
                                        <a href="{{ route('sectors.edit', $sector->id) }}"
                                           class="btn btn-sm btn-warning"
                                           title="Edit">
                                            <i class="feather-edit"></i>
                                        </a>
                                        <form action="{{ route('sectors.destroy', $sector->id) }}"
                                              method="POST"
                                              class="d-inline"
                                              onsubmit="return confirm('Delete this portfolio? This action cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-sm btn-danger"
                                                    title="Delete">
                                                <i class="feather-trash-2"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No portfolios found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </x-data-table>
                </div>
            </div>

        </div>
    </main>
@endsection
