@extends('layouts.app')

@section('title', 'Vendor Management')

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex justify-content-between align-items-center mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-briefcase text-primary me-2"></i>
                    Vendor Management
                </h4>
                <p class="text-muted mb-0">Upload, manage, and control vendor access.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('vendors.categories.index') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="feather-tag me-1"></i> Vendor Categories
                </a>
                <a href="{{ route('vendors.template') }}" class="btn btn-outline-primary btn-sm">
                    <i class="feather-download me-1"></i> Download Upload Template
                </a>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if (session('import_errors'))
            <div class="alert alert-danger">
                <strong>Import Errors</strong>
                <ul class="mb-0">
                    @foreach (session('import_errors') as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('import_duplicates'))
            @php
                $duplicates = session('import_duplicates');
            @endphp
            @if (!empty($duplicates))
                <div class="alert alert-warning">
                    <strong>Duplicate emails skipped:</strong>
                    <div class="small mt-1">{{ implode(', ', $duplicates) }}</div>
                </div>
            @endif
        @endif
        @if (session('import_mail_failures'))
            @php
                $mailFailures = session('import_mail_failures');
            @endphp
            @if (!empty($mailFailures))
                <div class="alert alert-warning">
                    <strong>Email queueing failed for some vendors:</strong>
                    <ul class="mb-0">
                        @foreach ($mailFailures as $failure)
                            <li>{{ $failure['email'] }} — {{ $failure['error'] }}</li>
                        @endforeach
                    </ul>
                    <div class="small mt-2">
                        The vendor accounts were created, but email delivery failed. Please verify mail/queue settings.
                    </div>
                </div>
            @endif
        @endif

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light">
                <h6 class="mb-0 fw-semibold">Bulk Vendor Upload</h6>
            </div>
            <div class="card-body">
                <form action="{{ route('vendors.import') }}" method="POST" enctype="multipart/form-data"
                    class="row g-3 align-items-end">
                    @csrf
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Upload File</label>
                        <input type="file" name="file" class="form-control" required>
                        <small class="text-muted">
                            Accepted formats: .xlsx, .xls, .csv. Vendor categories must already exist.
                        </small>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="feather-upload me-1"></i> Upload Vendors
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">Vendors Directory</h6>
                <span class="badge bg-secondary">{{ $vendors->count() }} Vendors</span>
            </div>
            <div class="card-body">
                <x-data-table id="vendorsTable">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Vendor</th>
                            <th>Email</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th class="text-center" width="220">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($vendors as $vendor)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">{{ $vendor->name }}</div>
                                </td>
                                <td>{{ $vendor->email }}</td>
                                <td>{{ $vendor->vendor_category ?? '—' }}</td>
                                <td>
                                    @if ($vendor->is_blacklisted)
                                        <span class="badge bg-danger">Blacklisted</span>
                                    @elseif ($vendor->is_disabled)
                                        <span class="badge bg-warning text-dark">Disabled</span>
                                    @else
                                        <span class="badge bg-success">Active</span>
                                    @endif
                                </td>
                                <td>{{ $vendor->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a href="{{ route('vendors.edit', $vendor) }}" class="btn btn-sm btn-outline-primary"
                                            title="Edit Vendor">
                                            <i class="feather-edit"></i>
                                        </a>
                                        @if ($vendor->is_disabled)
                                            <form action="{{ route('vendors.enable', $vendor) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-sm btn-outline-success" title="Enable Vendor">
                                                    <i class="feather-unlock"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('vendors.disable', $vendor) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-sm btn-outline-warning" title="Disable Vendor">
                                                    <i class="feather-lock"></i>
                                                </button>
                                            </form>
                                        @endif

                                        @if ($vendor->is_blacklisted)
                                            <form action="{{ route('vendors.unblacklist', $vendor) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-sm btn-outline-secondary" title="Unblacklist Vendor">
                                                    <i class="feather-shield-off"></i>
                                                </button>
                                            </form>
                                        @else
                                            <form action="{{ route('vendors.blacklist', $vendor) }}" method="POST">
                                                @csrf
                                                @method('PUT')
                                                <button class="btn btn-sm btn-outline-danger" title="Blacklist Vendor">
                                                    <i class="feather-shield"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            </div>
        </div>
    </div>
@endsection
