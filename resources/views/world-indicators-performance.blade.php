<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $settings->page_title }}</title>

    <link rel="stylesheet" href="{{ asset('assets/style.css') }}">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">

    <style>
        :root {
            --wine: #5a1f2b;
            --gold: #c0a96a;
            --deep-blue: #0a3d62;
            --slate: #475569;
            --soft-bg: #f8fafc;
        }

        body {
            background: linear-gradient(180deg, #f8fafc 0%, #eef2ff 45%, #f1f5f9 100%);
            color: #0f172a;
            padding-top: 96px;
        }

        .navbar {
            z-index: 1200;
        }

        .hero-world {
            margin: 1.4rem auto 0;
            width: min(1220px, 94%);
            background: linear-gradient(120deg, #0f172a 0%, #0a3d62 55%, #1d4ed8 100%);
            color: #f8fafc;
            border-radius: 16px;
            padding: 1.4rem 1.5rem;
            box-shadow: 0 18px 36px rgba(15, 23, 42, 0.22);
        }

        .hero-world h1 {
            margin: 0 0 0.6rem;
            font-size: clamp(1.4rem, 2.2vw, 2rem);
        }

        .hero-world p {
            margin: 0 0 0.8rem;
            color: rgba(248, 250, 252, 0.9);
            max-width: 900px;
        }

        .source-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.45rem;
        }

        .source-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            border: 1px solid rgba(248, 250, 252, 0.45);
            padding: 0.28rem 0.66rem;
            border-radius: 999px;
            font-size: 0.78rem;
            background: rgba(248, 250, 252, 0.14);
        }

        .summary-grid {
            width: min(1220px, 94%);
            margin: 1rem auto 0;
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem;
        }

        .summary-card {
            background: #fff;
            border: 1px solid #dbe4ef;
            border-radius: 12px;
            padding: 0.8rem 0.95rem;
            box-shadow: 0 10px 18px rgba(15, 23, 42, 0.06);
        }

        .summary-card .label {
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            font-weight: 700;
        }

        .summary-card .value {
            font-size: 1.2rem;
            font-weight: 800;
            margin-top: 0.2rem;
            color: #0f172a;
        }

        .world-content {
            width: min(1220px, 94%);
            margin: 1rem auto 2rem;
            display: grid;
            grid-template-columns: 1.35fr 0.85fr;
            gap: 1rem;
        }

        .world-panel {
            background: #fff;
            border: 1px solid #dbe4ef;
            border-radius: 14px;
            box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }

        .world-panel-head {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        }

        .world-panel-head h3 {
            margin: 0 0 0.2rem;
            color: var(--deep-blue);
            font-size: 1rem;
        }

        .world-panel-head p {
            margin: 0;
            font-size: 0.82rem;
            color: var(--slate);
        }

        .world-controls {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            padding: 0.8rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .world-controls label {
            display: block;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .world-controls select {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.44rem 0.5rem;
            font-size: 0.88rem;
            color: #0f172a;
            background: #fff;
        }

        .world-controls input {
            width: 100%;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0.44rem 0.5rem;
            font-size: 0.88rem;
            color: #0f172a;
            background: #fff;
        }

        #compareCountriesSelect,
        #compareContinentsSelect {
            min-height: 180px;
        }

        #worldMap {
            width: 100%;
            min-height: 560px;
            background: #e2e8f0;
        }

        .map-status {
            padding: 0.65rem 0.9rem;
            border-top: 1px solid #e2e8f0;
            font-size: 0.82rem;
            color: #334155;
            background: #f8fafc;
        }

        .map-status.error {
            color: #991b1b;
            background: #fef2f2;
        }

        .snapshot-wrap {
            padding: 0.95rem 1rem;
        }

        .snapshot-country {
            margin: 0;
            color: var(--wine);
            font-size: 1.05rem;
        }

        .snapshot-hint {
            margin: 0.4rem 0 0.9rem;
            color: #475569;
            font-size: 0.82rem;
            line-height: 1.4;
        }

        .metric-source {
            border: 1px solid #dbe4ef;
            border-radius: 10px;
            background: #f8fafc;
            padding: 0.7rem;
            margin-bottom: 0.75rem;
        }

        .metric-source h4 {
            margin: 0;
            font-size: 0.9rem;
            color: #0f172a;
        }

        .metric-source .note {
            margin: 0.2rem 0 0.6rem;
            color: #64748b;
            font-size: 0.75rem;
            line-height: 1.4;
        }

        .metric-list {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .metric-list li {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            border-top: 1px dashed #d1d5db;
            padding: 0.45rem 0;
            font-size: 0.8rem;
        }

        .metric-list li:first-child {
            border-top: 0;
            padding-top: 0;
        }

        .metric-list strong {
            color: var(--deep-blue);
            font-size: 0.83rem;
            white-space: nowrap;
        }

        .empty-box {
            border: 1px dashed #94a3b8;
            background: #f8fafc;
            border-radius: 10px;
            padding: 0.9rem;
            color: #475569;
            font-size: 0.82rem;
        }

        @media (max-width: 1024px) {
            .summary-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .world-content {
                grid-template-columns: 1fr;
            }

            #worldMap {
                min-height: 500px;
            }
        }

        @media (max-width: 640px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }

            .world-controls {
                grid-template-columns: 1fr;
            }

            #worldMap {
                min-height: 420px;
            }
        }

        @media (max-width: 768px) {
            body {
                padding-top: 150px;
            }
        }
    </style>
</head>

<body>
    <header class="navbar">
        <div class="logo">
            <img src="{{ asset('assets/images/au.png') }}" class="logo logo-sm" alt="ATTP">
        </div>

        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">{{ __('navigation.home') }}</a>
            <a href="{{ route('events') }}">{{ __('navigation.events') }}</a>
            <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
            <a href="{{ route('world.indicators.performance') }}"
                class="active">{{ __('navigation.world_indicators_performance') }}</a>
            <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
            <a href="{{ route('applicants.faq') }}">{{ __('navigation.faqs') }}</a>
        </nav>

        <div class="nav-actions">
            <x-language-selector style="landing" />
            <a href="{{ route('login') }}" class="btn btn-login">{{ __('navigation.login') }}</a>
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">
                {{ __('landing.policy_programs') }}
            </a>
        </div>
    </header>

    <section class="hero-world">
        <h1>{{ $settings->page_title }}</h1>
        <p>{{ $settings->page_intro ?: 'Explore country and regional indicators. IMF and World Bank live data will be attached after API mapping is configured.' }}
        </p>
        <div class="source-pills">
            @forelse ($enabledSources as $source)
                <span class="source-pill">{{ $source['label'] }}</span>
            @empty
                <span class="source-pill">No source is enabled in back office settings</span>
            @endforelse
        </div>
    </section>

    <section class="summary-grid">
        <article class="summary-card">
            <div class="label">Regions Enabled</div>
            <div class="value">{{ $summary['regions'] }}</div>
        </article>
        <article class="summary-card">
            <div class="label">Country Shapes</div>
            <div class="value">{{ $summary['countries'] }}</div>
        </article>
        <article class="summary-card">
            <div class="label">Shapefiles Loaded</div>
            <div class="value">{{ $summary['shape_files'] }}</div>
        </article>
        <article class="summary-card">
            <div class="label">Default Region</div>
            <div class="value">{{ $regionLabels[$defaultRegion] ?? 'Auto' }}</div>
        </article>
        <article class="summary-card">
            <div class="label">WB Topics</div>
            <div class="value">{{ $worldBankSummary['topics'] ?? 0 }}</div>
        </article>
        <article class="summary-card">
            <div class="label">WB Indicators</div>
            <div class="value">{{ $worldBankSummary['indicators'] ?? 0 }}</div>
        </article>
    </section>

    <section class="world-content">
        <article class="world-panel">
            <div class="world-panel-head">
                <h3>Interactive World Shape Viewer</h3>
                <p>Select a region, then click a country on the map to view its indicator snapshot.</p>
            </div>

            <div class="world-controls">
                <div>
                    <label for="regionSelect">Region</label>
                    <select id="regionSelect">
                        @foreach ($enabledRegions as $region)
                            <option value="{{ $region }}" @selected($defaultRegion === $region)>
                                {{ $regionLabels[$region] ?? $region }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="countrySelect">Country</label>
                    <select id="countrySelect">
                        <option value="">Select a country</option>
                    </select>
                </div>
            </div>

            <div id="worldMap"></div>
            <div class="map-status" id="mapStatus">Preparing map...</div>
        </article>

        <aside class="world-panel">
            <div class="world-panel-head">
                <h3>Country Indicator Snapshot</h3>
                <p>Preview API-ready fields from IMF and World Bank source slots.</p>
            </div>
            <div class="snapshot-wrap">
                <h4 class="snapshot-country" id="snapshotCountry">No country selected</h4>
                <p class="snapshot-hint" id="snapshotHint">
                    Select a region and click a country shape to load indicator placeholders.
                </p>
                <div id="snapshotMetrics" class="empty-box">
                    Indicators will appear here when a country is selected.
                </div>
            </div>
        </aside>
    </section>

    <section class="world-content" id="worldbank-compare">
        <article class="world-panel">
            <div class="world-panel-head">
                <h3>World Bank Comparison Studio</h3>
                <p>Compare two or more countries, or compare continents year-by-year across World Bank indicator groups.</p>
            </div>
            <div class="world-controls" style="grid-template-columns: repeat(3, minmax(0, 1fr));">
                <div>
                    <label for="compareTopicSelect">Indicator Group</label>
                    <select id="compareTopicSelect">
                        <option value="">Loading groups...</option>
                    </select>
                </div>
                <div>
                    <label for="compareIndicatorSelect">Indicator</label>
                    <select id="compareIndicatorSelect">
                        <option value="">Select indicator</option>
                    </select>
                </div>
                <div>
                    <label for="compareModeSelect">Compare By</label>
                    <select id="compareModeSelect">
                        <option value="country" selected>Country</option>
                        <option value="continent">Continent</option>
                    </select>
                </div>
                <div>
                    <label for="compareAggregationSelect">Aggregation</label>
                    <select id="compareAggregationSelect">
                        <option value="avg" selected>Average</option>
                        <option value="sum">Sum</option>
                    </select>
                </div>
                <div>
                    <label for="compareYearFrom">Year From</label>
                    <input type="number" id="compareYearFrom" class="form-control" min="1960" step="1">
                </div>
                <div>
                    <label for="compareYearTo">Year To</label>
                    <input type="number" id="compareYearTo" class="form-control" min="1960" step="1">
                </div>
                <div id="compareCountriesWrap" style="grid-column: span 3;">
                    <label for="compareCountriesSelect">Countries (select at least 2)</label>
                    <select id="compareCountriesSelect" multiple size="8" class="form-select"></select>
                </div>
                <div id="compareContinentsWrap" style="grid-column: span 3; display: none;">
                    <label for="compareContinentsSelect">Continents (select at least 2)</label>
                    <select id="compareContinentsSelect" multiple size="8" class="form-select"></select>
                </div>
                <div style="grid-column: span 3; display:flex; justify-content:flex-end;">
                    <button id="runCompareBtn" class="btn btn-primary" type="button">
                        Run Comparison
                    </button>
                </div>
            </div>
            <div class="map-status" id="compareStatus">Load an indicator group and run a comparison.</div>
            <div style="padding: 1rem; border-top: 1px solid #e2e8f0;">
                <div style="height: 360px;">
                    <canvas id="compareChart"></canvas>
                </div>
            </div>
            <div style="padding: 0 1rem 1rem;">
                <div class="table-responsive">
                    <table class="table table-sm table-striped" id="compareTable">
                        <thead>
                            <tr id="compareTableHeadRow">
                                <th>Year</th>
                            </tr>
                        </thead>
                        <tbody id="compareTableBody">
                            <tr>
                                <td class="text-muted">No comparison loaded yet.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </article>
    </section>

    <footer id="contact" class="footer">
        <div class="footer-content">
            <div class="footer-logo">
                <h3>ATTP<span> Administration</span></h3>
                <p>{{ __('landing.footer_description') }}</p>
            </div>
            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
                <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
                <a href="{{ route('world.indicators.performance') }}">{{ __('navigation.world_indicators_performance') }}</a>
                <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
            </div>
            <div class="footer-contact">
                <h4>{{ __('landing.footer_contact_title') }}</h4>
                <p>{{ __('landing.footer_email') }}</p>
                <p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p>
            </div>
        </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/shpjs@6.2.0/dist/shp.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <script>
        const shapeFilesByRegion = @json($shapeFilesByRegion);
        const countriesByRegion = @json($countriesByRegion);
        const regionLabels = @json($regionLabels);
        const defaultRegion = @json($defaultRegion);
        const countryMetricsUrl = @json(route('world.indicators.country-metrics'));
        const worldBankTopicsUrl = @json(route('world.indicators.topics'));
        const worldBankIndicatorsUrl = @json(route('world.indicators.indicators'));
        const worldBankCountriesUrl = @json(route('world.indicators.countries'));
        const worldBankContinentsUrl = @json(route('world.indicators.continents'));
        const worldBankCompareUrl = @json(route('world.indicators.compare'));
        const appBaseUrl = @json(rtrim(request()->getBaseUrl(), '/'));

        const regionSelect = document.getElementById('regionSelect');
        const countrySelect = document.getElementById('countrySelect');
        const mapStatus = document.getElementById('mapStatus');
        const snapshotCountry = document.getElementById('snapshotCountry');
        const snapshotHint = document.getElementById('snapshotHint');
        const snapshotMetrics = document.getElementById('snapshotMetrics');
        const compareTopicSelect = document.getElementById('compareTopicSelect');
        const compareIndicatorSelect = document.getElementById('compareIndicatorSelect');
        const compareModeSelect = document.getElementById('compareModeSelect');
        const compareAggregationSelect = document.getElementById('compareAggregationSelect');
        const compareYearFromInput = document.getElementById('compareYearFrom');
        const compareYearToInput = document.getElementById('compareYearTo');
        const compareCountriesWrap = document.getElementById('compareCountriesWrap');
        const compareCountriesSelect = document.getElementById('compareCountriesSelect');
        const compareContinentsWrap = document.getElementById('compareContinentsWrap');
        const compareContinentsSelect = document.getElementById('compareContinentsSelect');
        const runCompareBtn = document.getElementById('runCompareBtn');
        const compareStatus = document.getElementById('compareStatus');
        const compareChartCanvas = document.getElementById('compareChart');
        const compareTableHeadRow = document.getElementById('compareTableHeadRow');
        const compareTableBody = document.getElementById('compareTableBody');
        const pageNavbar = document.querySelector('header.navbar');

        function syncNavbarOffset() {
            if (!pageNavbar) {
                return;
            }

            const offset = Math.ceil(pageNavbar.getBoundingClientRect().height + 10);
            document.body.style.paddingTop = `${offset}px`;
        }

        syncNavbarOffset();
        window.addEventListener('load', syncNavbarOffset);
        window.addEventListener('resize', syncNavbarOffset);

        const map = L.map('worldMap', {
            center: [18, 0],
            zoom: 2,
            minZoom: 2,
            maxZoom: 9,
            worldCopyJump: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        let activeLayers = [];
        let compareChart = null;
        let cachedCompareCountries = [];
        let cachedCompareContinents = [];

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = String(value ?? '');
            return div.innerHTML;
        }

        function setMapStatus(message, isError = false) {
            mapStatus.textContent = message;
            mapStatus.classList.toggle('error', Boolean(isError));
        }

        function resetSnapshot() {
            snapshotCountry.textContent = 'No country selected';
            snapshotHint.textContent = 'Select a region and click a country shape to load indicator placeholders.';
            snapshotMetrics.className = 'empty-box';
            snapshotMetrics.innerHTML = 'Indicators will appear here when a country is selected.';
        }

        function getCountryName(feature, shapeFile = '') {
            const props = feature?.properties || {};
            const propertyName = props.NAME || props.name || props.COUNTRY || props.Country || props.ADMIN || props
                .SOVEREIGNT || props.NAME_EN;
            if (propertyName) {
                return propertyName;
            }

            const fileName = decodeURIComponent((shapeFile.split('/').pop() || '').replace(/\.shp$/i, ''));
            return fileName || 'Unknown';
        }

        function toFeatureCollection(raw) {
            if (!raw) {
                return {
                    type: 'FeatureCollection',
                    features: []
                };
            }

            if (raw.type === 'FeatureCollection') {
                return raw;
            }

            if (raw.type === 'Feature') {
                return {
                    type: 'FeatureCollection',
                    features: [raw]
                };
            }

            if (Array.isArray(raw)) {
                const combined = [];
                raw.forEach((item) => {
                    const collection = toFeatureCollection(item);
                    combined.push(...collection.features);
                });

                return {
                    type: 'FeatureCollection',
                    features: combined
                };
            }

            if (typeof raw === 'object') {
                const combined = [];
                Object.keys(raw).forEach((key) => {
                    const collection = toFeatureCollection(raw[key]);
                    combined.push(...collection.features);
                });

                return {
                    type: 'FeatureCollection',
                    features: combined
                };
            }

            return {
                type: 'FeatureCollection',
                features: []
            };
        }

        function applyRegionCountries(region) {
            const countries = countriesByRegion[region] || [];
            countrySelect.innerHTML = '<option value="">Select a country</option>';
            countries.forEach((country) => {
                const option = document.createElement('option');
                option.value = country;
                option.textContent = country;
                countrySelect.appendChild(option);
            });
        }

        function clearMapLayers() {
            activeLayers.forEach((layer) => map.removeLayer(layer));
            activeLayers = [];
        }

        function normalizeAssetUrl(url) {
            try {
                const parsed = new URL(url, window.location.href);
                let normalizedPath = `${parsed.pathname}${parsed.search}${parsed.hash}`;

                if (appBaseUrl && normalizedPath.startsWith('/assets/')) {
                    normalizedPath = `${appBaseUrl}${normalizedPath}`;
                }

                return new URL(normalizedPath, window.location.origin).toString();
            } catch (error) {
                if ((url || '').toString().startsWith('/')) {
                    return `${window.location.origin}${url}`;
                }

                return (url || '').toString();
            }
        }

        function resolveShapeFileUrl(shapeUrl) {
            const rawUrl = (shapeUrl || '').toString().trim();
            if (!rawUrl) {
                return '';
            }

            try {
                const resolved = new URL(rawUrl, window.location.href);
                const isHttpUrl = resolved.protocol === 'http:' || resolved.protocol === 'https:';
                const looksLikeShape = /\/assets\/Worldshapes\/.+\.shp$/i.test(resolved.pathname);

                if (looksLikeShape && resolved.host !== window.location.host) {
                    return new URL(
                        `${resolved.pathname}${resolved.search}${resolved.hash}`,
                        window.location.origin
                    ).toString();
                }

                if (isHttpUrl && resolved.protocol !== window.location.protocol) {
                    resolved.protocol = window.location.protocol;
                }

                return resolved.toString();
            } catch (error) {
                if (rawUrl.startsWith('/')) {
                    return `${window.location.origin}${rawUrl}`;
                }

                return rawUrl;
            }
        }

        async function loadGeoJsonFromShape(shapeFile) {
            const resolvedShapeUrl = resolveShapeFileUrl(shapeFile);
            const normalizedShapeFile = normalizeAssetUrl(resolvedShapeUrl);

            try {
                return await shp(normalizedShapeFile);
            } catch (primaryError) {
                const shpResponse = await fetch(normalizedShapeFile);
                if (!shpResponse.ok) {
                    throw new Error(`Could not fetch SHP (${shpResponse.status})`);
                }

                const payload = {
                    shp: await shpResponse.arrayBuffer()
                };

                try {
                    const dbfResponse = await fetch(normalizeAssetUrl(normalizedShapeFile.replace(/\.shp$/i, '.dbf')));
                    if (dbfResponse.ok) {
                        payload.dbf = await dbfResponse.arrayBuffer();
                    }
                } catch (error) {
                    console.warn('DBF fetch failed for', normalizedShapeFile, error);
                }

                try {
                    const prjResponse = await fetch(normalizeAssetUrl(normalizedShapeFile.replace(/\.shp$/i, '.prj')));
                    if (prjResponse.ok) {
                        payload.prj = await prjResponse.text();
                    }
                } catch (error) {
                    console.warn('PRJ fetch failed for', normalizedShapeFile, error);
                }

                try {
                    const cpgResponse = await fetch(normalizeAssetUrl(normalizedShapeFile.replace(/\.shp$/i, '.cpg')));
                    if (cpgResponse.ok) {
                        payload.cpg = await cpgResponse.text();
                    }
                } catch (error) {
                    console.warn('CPG fetch failed for', normalizedShapeFile, error);
                }

                return await shp(payload);
            }
        }

        async function loadRegion(region) {
            clearMapLayers();
            applyRegionCountries(region);

            const shapeFiles = shapeFilesByRegion[region] || [];
            if (!shapeFiles.length) {
                setMapStatus('No shapefiles found for this region.', true);
                return;
            }

            const label = regionLabels[region] || region;
            setMapStatus(`Loading ${shapeFiles.length} shapefiles for ${label}...`);

            let loadedCount = 0;
            let failedCount = 0;
            let lastError = null;
            for (const shapeFile of shapeFiles) {
                try {
                    const geojson = await loadGeoJsonFromShape(shapeFile);
                    const featureCollection = toFeatureCollection(geojson);
                    if (!featureCollection.features.length) {
                        continue;
                    }

                    const layer = L.geoJSON(featureCollection, {
                        style: {
                            color: '#1e3a8a',
                            weight: 0.9,
                            fillColor: '#60a5fa',
                            fillOpacity: 0.33
                        },
                        onEachFeature: (feature, leafletLayer) => {
                            const countryName = getCountryName(feature, shapeFile);
                            leafletLayer.bindTooltip(countryName, {
                                direction: 'auto'
                            });
                            leafletLayer.on('click', () => selectCountry(countryName));
                        }
                    }).addTo(map);

                    activeLayers.push(layer);
                    loadedCount += 1;
                    setMapStatus(`Loaded ${loadedCount}/${shapeFiles.length} shapefiles for ${label}...`);
                } catch (error) {
                    failedCount += 1;
                    lastError = error;
                    console.error('Could not load shapefile:', shapeFile, error);
                }
            }

            if (!loadedCount) {
                const reason = lastError && lastError.message ? ` Last error: ${lastError.message}` : '';
                setMapStatus(`All shapefiles failed to load for this region.${reason}`, true);
                return;
            }

            const boundsLayer = L.featureGroup(activeLayers);
            if (boundsLayer.getBounds().isValid()) {
                map.fitBounds(boundsLayer.getBounds(), {
                    padding: [20, 20]
                });
            }

            if (failedCount > 0) {
                setMapStatus(`Loaded ${loadedCount} shapefiles for ${label}; ${failedCount} failed to load.`);
                return;
            }

            setMapStatus(`Loaded ${loadedCount} shapefiles for ${label}.`);
        }

        async function selectCountry(country) {
            if (!country) {
                return;
            }

            snapshotCountry.textContent = country;
            snapshotHint.textContent = 'Loading indicator snapshot...';
            snapshotMetrics.className = '';
            snapshotMetrics.innerHTML = '';
            countrySelect.value = country;

            try {
                const response = await fetch(`${countryMetricsUrl}?country=${encodeURIComponent(country)}`);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const payload = await response.json();
                renderSnapshot(payload);
            } catch (error) {
                console.error('Failed to fetch metrics', error);
                snapshotHint.textContent = 'Could not fetch indicators for this country.';
                snapshotMetrics.className = 'empty-box';
                snapshotMetrics.innerHTML = 'Please try another country or refresh the page.';
            }
        }

        function renderSnapshot(payload) {
            const sources = payload?.sources || [];
            const placeholder = Boolean(payload?.placeholder);
            snapshotHint.textContent = placeholder ?
                'Preview values are placeholders until IMF and World Bank API mappings are finalized.' :
                'Live values fetched from configured source endpoints.';

            if (!sources.length) {
                snapshotMetrics.className = 'empty-box';
                snapshotMetrics.innerHTML = 'No data source is currently enabled in back office settings.';
                return;
            }

            snapshotMetrics.className = '';
            snapshotMetrics.innerHTML = sources.map((source) => {
                const rows = (source.metrics || []).map((metric) => `
                    <li>
                        <span>${escapeHtml(metric.label)}</span>
                        <strong>${escapeHtml(metric.value)}</strong>
                    </li>
                `).join('');

                return `
                    <div class="metric-source">
                        <h4>${escapeHtml(source.label)}</h4>
                        <p class="note">${escapeHtml(source.note || '')}</p>
                        <ul class="metric-list">${rows}</ul>
                    </div>
                `;
            }).join('');
        }

        function setCompareStatus(message, isError = false) {
            if (!compareStatus) {
                return;
            }
            compareStatus.textContent = message;
            compareStatus.classList.toggle('error', Boolean(isError));
        }

        function getSelectedValues(selectEl) {
            if (!selectEl) {
                return [];
            }
            return Array.from(selectEl.selectedOptions)
                .map((option) => option.value)
                .filter((value) => value);
        }

        function syncCompareMode() {
            const mode = compareModeSelect?.value || 'country';
            const isContinent = mode === 'continent';

            if (compareCountriesWrap) {
                compareCountriesWrap.style.display = isContinent ? 'none' : 'block';
            }
            if (compareContinentsWrap) {
                compareContinentsWrap.style.display = isContinent ? 'block' : 'none';
            }
            if (compareAggregationSelect) {
                compareAggregationSelect.disabled = !isContinent;
            }
        }

        async function loadCompareTopics() {
            if (!compareTopicSelect) {
                return;
            }

            const response = await fetch(worldBankTopicsUrl);
            if (!response.ok) {
                throw new Error(`Topic load failed: HTTP ${response.status}`);
            }

            const payload = await response.json();
            const topics = Array.isArray(payload?.data) ? payload.data : [];
            compareTopicSelect.innerHTML = '';

            if (!topics.length) {
                compareTopicSelect.innerHTML = '<option value="">No indicator groups available</option>';
                return;
            }

            topics.forEach((topic) => {
                const option = document.createElement('option');
                option.value = String(topic.id);
                option.textContent = `${topic.name} (${topic.indicator_count ?? 0})`;
                compareTopicSelect.appendChild(option);
            });

            compareTopicSelect.value = String(topics[0].id);
            await loadCompareIndicators();
        }

        async function loadCompareIndicators() {
            if (!compareIndicatorSelect) {
                return;
            }

            const topicId = compareTopicSelect?.value || '';
            const url = new URL(worldBankIndicatorsUrl, window.location.origin);
            if (topicId !== '') {
                url.searchParams.set('topic_id', topicId);
            }
            url.searchParams.set('limit', '1000');

            const response = await fetch(url.toString());
            if (!response.ok) {
                throw new Error(`Indicator load failed: HTTP ${response.status}`);
            }

            const payload = await response.json();
            const indicators = Array.isArray(payload?.data) ? payload.data : [];
            compareIndicatorSelect.innerHTML = '';

            if (!indicators.length) {
                compareIndicatorSelect.innerHTML = '<option value="">No indicators found</option>';
                return;
            }

            indicators.forEach((indicator) => {
                const option = document.createElement('option');
                option.value = String(indicator.id);
                option.textContent = `${indicator.name} [${indicator.id}]`;
                compareIndicatorSelect.appendChild(option);
            });
        }

        async function loadCompareCountries() {
            if (!compareCountriesSelect) {
                return;
            }

            const response = await fetch(worldBankCountriesUrl);
            if (!response.ok) {
                throw new Error(`Country load failed: HTTP ${response.status}`);
            }

            const payload = await response.json();
            const countries = Array.isArray(payload?.data) ? payload.data : [];
            cachedCompareCountries = countries;
            compareCountriesSelect.innerHTML = '';

            countries.forEach((country) => {
                const option = document.createElement('option');
                option.value = String(country.iso2);
                option.textContent = `${country.name} (${country.iso2})`;
                compareCountriesSelect.appendChild(option);
            });

            if (countries.length >= 2) {
                compareCountriesSelect.options[0].selected = true;
                compareCountriesSelect.options[1].selected = true;
            }
        }

        async function loadCompareContinents() {
            if (!compareContinentsSelect) {
                return;
            }

            const response = await fetch(worldBankContinentsUrl);
            if (!response.ok) {
                throw new Error(`Continent load failed: HTTP ${response.status}`);
            }

            const payload = await response.json();
            const continents = Array.isArray(payload?.data) ? payload.data : [];
            cachedCompareContinents = continents;
            compareContinentsSelect.innerHTML = '';

            continents.forEach((continent) => {
                const option = document.createElement('option');
                option.value = String(continent);
                option.textContent = String(continent);
                compareContinentsSelect.appendChild(option);
            });

            if (continents.length >= 2) {
                compareContinentsSelect.options[0].selected = true;
                compareContinentsSelect.options[1].selected = true;
            }
        }

        function formatCompareNumber(value) {
            if (value === null || value === undefined || Number.isNaN(Number(value))) {
                return 'N/A';
            }
            return Number(value).toLocaleString(undefined, {
                maximumFractionDigits: 2
            });
        }

        function renderCompareTable(payload) {
            if (!compareTableHeadRow || !compareTableBody) {
                return;
            }

            const years = Array.isArray(payload?.years) ? payload.years : [];
            const series = Array.isArray(payload?.series) ? payload.series : [];

            compareTableHeadRow.innerHTML = '<th>Year</th>';
            series.forEach((item) => {
                const th = document.createElement('th');
                th.textContent = item.label;
                compareTableHeadRow.appendChild(th);
            });

            if (!years.length || !series.length) {
                compareTableBody.innerHTML = '<tr><td class="text-muted">No comparison data available.</td></tr>';
                return;
            }

            const rowsHtml = years.map((year, yearIndex) => {
                const cells = series.map((item) => {
                    const point = Array.isArray(item.points) ? item.points[yearIndex] : null;
                    return `<td>${formatCompareNumber(point?.value ?? null)}</td>`;
                }).join('');

                return `<tr><td>${year}</td>${cells}</tr>`;
            }).join('');

            compareTableBody.innerHTML = rowsHtml;
        }

        function renderCompareChart(payload) {
            if (!compareChartCanvas || typeof Chart === 'undefined') {
                return;
            }

            const years = Array.isArray(payload?.years) ? payload.years : [];
            const series = Array.isArray(payload?.series) ? payload.series : [];
            const labels = years.map((year) => String(year));
            const palette = ['#1d4ed8', '#e11d48', '#0f766e', '#d97706', '#7c3aed', '#0891b2', '#65a30d', '#475569'];

            const datasets = series.map((item, index) => {
                const color = palette[index % palette.length];
                return {
                    label: item.label,
                    data: Array.isArray(item.points) ? item.points.map((point) => point?.value ?? null) : [],
                    borderColor: color,
                    backgroundColor: color,
                    borderWidth: 2,
                    tension: 0.2,
                    spanGaps: true
                };
            });

            if (compareChart) {
                compareChart.destroy();
            }

            compareChart = new Chart(compareChartCanvas, {
                type: 'line',
                data: {
                    labels,
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'nearest',
                        intersect: false
                    },
                    scales: {
                        y: {
                            beginAtZero: false
                        }
                    }
                }
            });
        }

        async function runComparison() {
            const indicatorId = compareIndicatorSelect?.value || '';
            const compareMode = compareModeSelect?.value || 'country';
            const yearFrom = parseInt(compareYearFromInput?.value || '', 10);
            const yearTo = parseInt(compareYearToInput?.value || '', 10);
            const selectedCountries = getSelectedValues(compareCountriesSelect);
            const selectedContinents = getSelectedValues(compareContinentsSelect);
            const aggregation = compareAggregationSelect?.value || 'avg';

            if (!indicatorId) {
                setCompareStatus('Select an indicator first.', true);
                return;
            }

            if (!Number.isFinite(yearFrom) || !Number.isFinite(yearTo)) {
                setCompareStatus('Provide a valid year range.', true);
                return;
            }

            if (compareMode === 'country' && selectedCountries.length < 2) {
                setCompareStatus('Select at least 2 countries.', true);
                return;
            }

            if (compareMode === 'continent' && selectedContinents.length < 2) {
                setCompareStatus('Select at least 2 continents.', true);
                return;
            }

            const url = new URL(worldBankCompareUrl, window.location.origin);
            url.searchParams.set('indicator_id', indicatorId);
            url.searchParams.set('compare_mode', compareMode);
            url.searchParams.set('year_from', String(yearFrom));
            url.searchParams.set('year_to', String(yearTo));

            if (compareMode === 'country') {
                selectedCountries.forEach((countryIso2) => {
                    url.searchParams.append('countries[]', countryIso2);
                });
            } else {
                selectedContinents.forEach((continent) => {
                    url.searchParams.append('continents[]', continent);
                });
                url.searchParams.set('aggregation', aggregation);
            }

            setCompareStatus('Running comparison and syncing fresh data if needed...');
            runCompareBtn.disabled = true;

            try {
                const response = await fetch(url.toString());
                if (!response.ok) {
                    throw new Error(`Comparison failed with HTTP ${response.status}`);
                }

                const payload = await response.json();
                renderCompareChart(payload);
                renderCompareTable(payload);

                const seriesCount = Array.isArray(payload?.series) ? payload.series.length : 0;
                const indicatorName = payload?.indicator?.name || indicatorId;
                setCompareStatus(`Loaded ${seriesCount} series for "${indicatorName}".`);
            } catch (error) {
                console.error('World Bank comparison failed', error);
                setCompareStatus('Could not complete comparison. Please try again.', true);
            } finally {
                runCompareBtn.disabled = false;
            }
        }

        async function initializeComparisonStudio() {
            const currentYear = new Date().getFullYear();
            if (compareYearFromInput) {
                compareYearFromInput.value = String(Math.max(1960, currentYear - 15));
            }
            if (compareYearToInput) {
                compareYearToInput.value = String(currentYear);
            }

            setCompareStatus('Loading World Bank groups, indicators, countries, and continents...');

            await loadCompareTopics();
            await Promise.all([
                loadCompareCountries(),
                loadCompareContinents(),
            ]);

            syncCompareMode();
            setCompareStatus('Ready. Choose an indicator and run comparison.');
        }

        regionSelect.addEventListener('change', () => {
            resetSnapshot();
            loadRegion(regionSelect.value);
        });

        countrySelect.addEventListener('change', () => {
            const country = countrySelect.value;
            if (!country) {
                resetSnapshot();
                return;
            }
            selectCountry(country);
        });

        if (compareTopicSelect) {
            compareTopicSelect.addEventListener('change', async () => {
                try {
                    await loadCompareIndicators();
                } catch (error) {
                    console.error('Could not refresh indicators for selected topic', error);
                    setCompareStatus('Could not load indicators for selected group.', true);
                }
            });
        }

        if (compareModeSelect) {
            compareModeSelect.addEventListener('change', syncCompareMode);
        }

        if (runCompareBtn) {
            runCompareBtn.addEventListener('click', runComparison);
        }

        const initialRegion = (defaultRegion && shapeFilesByRegion[defaultRegion]) ?
            defaultRegion :
            (regionSelect.options.length ? regionSelect.options[0].value : null);

        if (initialRegion) {
            regionSelect.value = initialRegion;
            loadRegion(initialRegion);
        } else {
            setMapStatus('No world-shape region is available. Check public/assets/Worldshapes.', true);
        }

        initializeComparisonStudio().catch((error) => {
            console.error('Comparison studio initialization failed', error);
            setCompareStatus('Could not initialize comparison tools. Please refresh the page.', true);
        });
    </script>
</body>

</html>
