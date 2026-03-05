@extends('layouts.app')
@section('title', 'World Indicators Settings')

@php
    $oldEnabledRegions = old('enabled_regions', $selectedRegions);
    $isPublicEnabled = (string) old('is_public_enabled', $settings->is_public_enabled ? '1' : '0') === '1';
    $isImfEnabled = (string) old('imf_source_enabled', $settings->imf_source_enabled ? '1' : '0') === '1';
    $isWorldBankEnabled = (string) old('world_bank_source_enabled', $settings->world_bank_source_enabled ? '1' : '0') === '1';
@endphp

@section('content')
    <div class="nxl-container">
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
            <div>
                <h4 class="fw-bold mb-1">
                    <i class="feather-globe text-primary me-2"></i>
                    World Indicators
                </h4>
                <p class="text-muted mb-0">Control public page visibility, regions, and data-source endpoint settings.</p>
            </div>
            <a href="{{ route('world.indicators.performance') }}" class="btn btn-outline-primary btn-sm" target="_blank">
                <i class="feather-external-link me-1"></i> Open Public Page
            </a>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <form method="POST" action="{{ route('budget.me.world-indicators.settings.update') }}">
            @csrf
            @method('PUT')

            <div class="card shadow-sm border-0" id="api-controls">
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-lg-8">
                            <label class="form-label fw-semibold">Page Title</label>
                            <input type="text" name="page_title" class="form-control"
                                value="{{ old('page_title', $settings->page_title) }}" required maxlength="150">
                        </div>

                        <div class="col-lg-4">
                            <label class="form-label fw-semibold">Default Region</label>
                            <select name="default_region" class="form-select">
                                <option value="">Auto (first enabled region)</option>
                                @foreach ($regions as $region)
                                    <option value="{{ $region }}"
                                        @selected(old('default_region', $settings->default_region) === $region)>
                                        {{ $regionLabels[$region] ?? $region }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Page Intro Text</label>
                            <textarea name="page_intro" class="form-control" rows="3" maxlength="2000">{{ old('page_intro', $settings->page_intro) }}</textarea>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Enabled Regions</label>
                            <div class="row g-2">
                                @foreach ($regions as $region)
                                    <div class="col-md-3 col-sm-4 col-6">
                                        <label class="border rounded p-2 w-100 h-100 d-flex align-items-center gap-2">
                                            <input type="checkbox" name="enabled_regions[]" value="{{ $region }}"
                                                @checked(in_array($region, (array) $oldEnabledRegions, true))>
                                            <span>{{ $regionLabels[$region] ?? $region }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">If none are selected, all detected regions will be used.</small>
                        </div>

                        <div class="col-md-4">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="is_public_enabled"
                                    value="1" @checked($isPublicEnabled)>
                                <label class="form-check-label fw-semibold">Public Page Enabled</label>
                            </div>
                            <small class="text-muted">When disabled, the public route returns 404.</small>
                        </div>

                        <div class="col-md-4" id="imf-data">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch" name="imf_source_enabled"
                                    value="1" @checked($isImfEnabled)>
                                <label class="form-check-label fw-semibold">Enable IMF Source</label>
                            </div>
                        </div>

                        <div class="col-md-4" id="world-bank-toggle">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="world_bank_source_enabled" value="1" @checked($isWorldBankEnabled)>
                                <label class="form-check-label fw-semibold">Enable World Bank Source</label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">IMF API Base URL (optional)</label>
                            <input type="url" name="imf_api_base_url" class="form-control"
                                value="{{ old('imf_api_base_url', $settings->imf_api_base_url) }}"
                                placeholder="https://...">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">World Bank API Base URL (optional)</label>
                            <input type="url" name="world_bank_api_base_url" class="form-control"
                                value="{{ old('world_bank_api_base_url', $settings->world_bank_api_base_url) }}"
                                placeholder="https://...">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold">Implementation Notes (internal)</label>
                            <textarea name="notes" class="form-control" rows="4" maxlength="4000">{{ old('notes', $settings->notes) }}</textarea>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="feather-save me-1"></i> Save Settings
                    </button>
                </div>
            </div>
        </form>

        <div class="card shadow-sm border-0 mt-3" id="world-bank-data">
            <div class="card-header bg-white">
                <h6 class="mb-0 fw-bold">
                    <i class="feather-database me-2 text-primary"></i>
                    World Bank Data Catalog & Sync
                </h6>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted">Topics</div>
                            <div class="fs-5 fw-bold">{{ number_format((int) ($worldBankStats['topics'] ?? 0)) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted">Indicators</div>
                            <div class="fs-5 fw-bold">{{ number_format((int) ($worldBankStats['indicators'] ?? 0)) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted">Countries</div>
                            <div class="fs-5 fw-bold">{{ number_format((int) ($worldBankStats['countries'] ?? 0)) }}</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="border rounded p-3 h-100">
                            <div class="small text-muted">Observations</div>
                            <div class="fs-5 fw-bold">{{ number_format((int) ($worldBankStats['observations'] ?? 0)) }}</div>
                        </div>
                    </div>
                </div>

                <div class="mt-3 small text-muted">
                    <div>
                        Last catalog sync:
                        {{ !empty($worldBankStats['last_catalog_sync']) ? \Illuminate\Support\Carbon::parse($worldBankStats['last_catalog_sync'])->diffForHumans() : 'Never' }}
                    </div>
                    <div>
                        Last data sync:
                        {{ !empty($worldBankStats['last_data_sync']) ? \Illuminate\Support\Carbon::parse($worldBankStats['last_data_sync'])->diffForHumans() : 'Never' }}
                    </div>
                </div>

                <div class="row g-3 mt-2">
                    <div class="col-lg-5">
                        <form method="POST" action="{{ route('budget.me.world-indicators.settings.sync-catalog') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary">
                                <i class="feather-refresh-cw me-1"></i> Sync World Bank Catalog
                            </button>
                        </form>
                    </div>
                    <div class="col-lg-7">
                        <form method="POST" action="{{ route('budget.me.world-indicators.settings.sync-data') }}"
                            class="row g-2 align-items-end">
                            @csrf
                            <div class="col-sm-3">
                                <label class="form-label fw-semibold">Years</label>
                                <input type="number" name="years" class="form-control" min="1" max="70" value="20">
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Indicator IDs (optional)</label>
                                <input type="text" name="indicator_ids" class="form-control"
                                    placeholder="SP.POP.TOTL, NY.GDP.MKTP.CD">
                            </div>
                            <div class="col-sm-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="feather-download me-1"></i> Sync Data
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
