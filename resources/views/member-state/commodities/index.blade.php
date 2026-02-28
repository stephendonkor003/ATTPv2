@extends('layouts.app')

@section('title', 'Member State Commodities')

@section('content')
    <main class="nxl-container">
    <div class="page-header mb-4">
        <div>
            <h4 class="mb-1">Commodities and Trend Reporting</h4>
            <p class="mb-0 text-muted">
                Shared commodity catalog (no duplicates) and country-level trend data.
            </p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong>Add New Commodity (Global Catalog)</strong>
                </div>
                <div class="card-body">
                    <form action="{{ route('member-state.commodities.catalog.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-12">
                            <label class="form-label">Commodity Name</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="form-control @error('name') is-invalid @enderror"
                                   placeholder="e.g., Gold" required>
                            @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <input type="text" name="category" value="{{ old('category') }}"
                                   class="form-control @error('category') is-invalid @enderror"
                                   placeholder="Mineral, Energy, Agriculture">
                            @error('category') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Unit of Measure</label>
                            <input type="text" name="unit_of_measure" value="{{ old('unit_of_measure') }}"
                                   class="form-control @error('unit_of_measure') is-invalid @enderror"
                                   placeholder="tons, barrels, kg">
                            @error('unit_of_measure') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="3"
                                      class="form-control @error('description') is-invalid @enderror"
                                      placeholder="Commodity context and strategic value">{{ old('description') }}</textarea>
                            @error('description') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary">Add Commodity</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white">
                    <strong>Add Commodity Trend Data</strong>
                </div>
                <div class="card-body">
                    <form action="{{ route('member-state.commodities.trends.store') }}" method="POST" class="row g-3">
                        @csrf
                        <div class="col-md-4">
                            <label class="form-label">Commodity</label>
                            <select name="commodity_id" class="form-select @error('commodity_id') is-invalid @enderror" required>
                                <option value="">Select commodity</option>
                                @foreach($commodities as $commodity)
                                    <option value="{{ $commodity->id }}" @selected(old('commodity_id') == $commodity->id)>
                                        {{ $commodity->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('commodity_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Recorded On</label>
                            <input type="date" name="recorded_on"
                                   class="form-control @error('recorded_on') is-invalid @enderror"
                                   value="{{ old('recorded_on', now()->toDateString()) }}" required>
                            @error('recorded_on') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Growth Rate (%)</label>
                            <input type="number" step="0.001" name="growth_rate_pct"
                                   class="form-control @error('growth_rate_pct') is-invalid @enderror"
                                   value="{{ old('growth_rate_pct') }}">
                            @error('growth_rate_pct') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Production Volume</label>
                            <input type="number" step="0.001" name="production_volume"
                                   class="form-control @error('production_volume') is-invalid @enderror"
                                   value="{{ old('production_volume') }}">
                            @error('production_volume') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Export Volume</label>
                            <input type="number" step="0.001" name="export_volume"
                                   class="form-control @error('export_volume') is-invalid @enderror"
                                   value="{{ old('export_volume') }}">
                            @error('export_volume') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Export Value (USD)</label>
                            <input type="number" step="0.01" name="export_value_usd"
                                   class="form-control @error('export_value_usd') is-invalid @enderror"
                                   value="{{ old('export_value_usd') }}">
                            @error('export_value_usd') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Trend Summary</label>
                            <textarea name="trend_summary" rows="2" class="form-control @error('trend_summary') is-invalid @enderror"
                                      placeholder="Explain market and production trend for this period...">{{ old('trend_summary') }}</textarea>
                            @error('trend_summary') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <label class="form-label">Impact Notes</label>
                            <textarea name="impact_notes" rows="2" class="form-control @error('impact_notes') is-invalid @enderror"
                                      placeholder="How this commodity trend is helping your country...">{{ old('impact_notes') }}</textarea>
                            @error('impact_notes') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary">Save Trend Data</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Commodity Trend Records</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead>
                            <tr>
                                <th>Date</th>
                                <th>Commodity</th>
                                <th>Production</th>
                                <th>Export</th>
                                <th>Value (USD)</th>
                                <th>Growth</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($trends as $trend)
                                <tr>
                                    <td>{{ optional($trend->recorded_on)->format('d M Y') }}</td>
                                    <td>
                                        <div class="fw-semibold">{{ $trend->commodity?->name }}</div>
                                        @if($trend->trend_summary)
                                            <small class="text-muted">{{ \Illuminate\Support\Str::limit($trend->trend_summary, 70) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $trend->production_volume !== null ? number_format((float) $trend->production_volume, 3) : '-' }}</td>
                                    <td>{{ $trend->export_volume !== null ? number_format((float) $trend->export_volume, 3) : '-' }}</td>
                                    <td>{{ $trend->export_value_usd !== null ? number_format((float) $trend->export_value_usd, 2) : '-' }}</td>
                                    <td>{{ $trend->growth_rate_pct !== null ? number_format((float) $trend->growth_rate_pct, 3) . '%' : '-' }}</td>
                                    <td class="text-end">
                                        <form action="{{ route('member-state.commodities.trends.destroy', $trend) }}" method="POST"
                                              onsubmit="return confirm('Delete this trend record?');">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No commodity trends submitted yet.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($trends->hasPages())
                    <div class="card-footer bg-white">
                        {{ $trends->links() }}
                    </div>
                @endif
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <strong>Growth Summary by Commodity</strong>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead>
                            <tr>
                                <th>Commodity</th>
                                <th>Avg Growth</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($growthByCommodity as $row)
                                <tr>
                                    <td>{{ $row->commodity_name }}</td>
                                    <td>{{ number_format((float) $row->avg_growth_rate, 2) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="2" class="text-center text-muted py-3">No summary available.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </main>
@endsection
