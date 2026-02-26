@extends('layouts.app')

@section('title', 'Treaties and Agreements')

@section('content')
    <main class="nxl-container">
        <div class="nxl-content">
            <div class="page-header d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="mb-1">Treaties and Agreements</h4>
                    <p class="text-muted mb-0">Manage treaties and monitor which member states have signed or ratified.</p>
                </div>
                @can('treaties.create')
                    <a href="{{ route('settings.au.treaties.create') }}" class="btn btn-success">
                        <i class="feather-plus-circle me-1"></i> Add Treaty
                    </a>
                @endcan
            </div>

            @if (session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <x-data-table id="treatiesTable" :config="['order' => [[4, 'desc']], 'pageLength' => 25]">
                        <thead class="table-light">
                            <tr>
                                <th width="60">#</th>
                                <th>Treaty</th>
                                <th width="120">Reference</th>
                                <th width="120">Status</th>
                                <th width="140">Adoption Date</th>
                                <th width="120">Signed</th>
                                <th width="120">Ratified</th>
                                <th width="150" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($treaties as $treaty)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $treaty->title }}</div>
                                        @if ($treaty->short_title)
                                            <small class="text-muted">{{ $treaty->short_title }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($treaty->reference_code)
                                            <span class="badge bg-light text-dark">{{ $treaty->reference_code }}</span>
                                        @else
                                            <span class="text-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($treaty->status === 'active')
                                            <span class="badge bg-success">Active</span>
                                        @elseif($treaty->status === 'draft')
                                            <span class="badge bg-warning text-dark">Draft</span>
                                        @else
                                            <span class="badge bg-secondary">Archived</span>
                                        @endif
                                    </td>
                                    <td>{{ optional($treaty->adoption_date)->format('d M Y') ?? '—' }}</td>
                                    <td>
                                        <span class="badge bg-info">{{ (int) $treaty->signed_count }}</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary">{{ (int) $treaty->ratified_count }}</span>
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('settings.au.treaties.show', $treaty->id) }}"
                                            class="btn btn-sm btn-outline-primary">
                                            <i class="feather-eye"></i>
                                        </a>
                                        @can('treaties.edit')
                                            <a href="{{ route('settings.au.treaties.edit', $treaty->id) }}"
                                                class="btn btn-sm btn-outline-warning">
                                                <i class="feather-edit"></i>
                                            </a>
                                        @endcan
                                        @can('treaties.delete')
                                            <form action="{{ route('settings.au.treaties.destroy', $treaty->id) }}"
                                                method="POST" class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                                    onclick="return confirm('Delete this treaty?')">
                                                    <i class="feather-trash-2"></i>
                                                </button>
                                            </form>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                </div>
            </div>
        </div>
    </main>
@endsection
