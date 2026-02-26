<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>African Map</title>

    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Inter", Arial, sans-serif;
            background: #f3f4f6;
            color: #111827;
        }

        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 20px;
            background: #0f172a;
            color: #fff;
            gap: 16px;
            flex-wrap: wrap;
        }

        .topbar a {
            color: #fff;
            text-decoration: none;
            font-weight: 600;
        }

        .topbar .links {
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
        }

        .page {
            padding: 20px;
        }

        .page h1 {
            margin: 0 0 6px;
            font-size: 28px;
        }

        .page p {
            margin: 0 0 14px;
            color: #374151;
        }

        #africa-map {
            width: 100%;
            height: calc(100vh - 210px);
            min-height: 560px;
            border: 1px solid #d1d5db;
            border-radius: 12px;
            background: #e5e7eb;
        }

        .status {
            margin-top: 10px;
            color: #1f2937;
            font-size: 14px;
        }

        @media (max-width: 768px) {
            #africa-map {
                min-height: 420px;
                height: calc(100vh - 260px);
            }
        }
    </style>
</head>

<body>
    <header class="topbar">
        <a href="{{ route('landing.index') }}">ATTP</a>
        <div class="links">
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="{{ route('impact.map') }}">Impact Map</a>
            <a href="{{ route('landing.african_map') }}">AFRICAN MAP</a>
        </div>
    </header>

    <main class="page">
        <h1>AFRICAN MAP</h1>
        <p>Complete map rendered from shapefiles in <code>public/assets/Africa</code>.</p>
        <div id="africa-map"></div>
        <div id="map-status" class="status">Loading map shapefiles...</div>
    </main>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script src="https://unpkg.com/shpjs@6.2.0/dist/shp.min.js"></script>
    <script>
        const shapeFiles = @json($shapeFiles);
        const statusEl = document.getElementById('map-status');

        const map = L.map('africa-map', {
            center: [4, 20],
            zoom: 3,
            minZoom: 2,
            maxZoom: 9,
            scrollWheelZoom: true
        });

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        const africaLayerGroup = L.featureGroup().addTo(map);

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
                raw.forEach(function(item) {
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
                Object.keys(raw).forEach(function(key) {
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

        function getCountryName(sourceUrl, properties) {
            const nameFromProps = properties && (properties.NAME || properties.ADMIN || properties.COUNTRY || properties.name);
            if (nameFromProps) {
                return nameFromProps;
            }

            const file = decodeURIComponent((sourceUrl.split('/').pop() || '').replace(/\.shp$/i, ''));
            return file || 'Country';
        }

        function addShapeToMap(rawShape, shapeUrl) {
            const featureCollection = toFeatureCollection(rawShape);

            L.geoJSON(featureCollection, {
                style: function() {
                    return {
                        color: '#0f172a',
                        weight: 1,
                        fillColor: '#16a34a',
                        fillOpacity: 0.55
                    };
                },
                onEachFeature: function(feature, layer) {
                    const countryName = getCountryName(shapeUrl, feature.properties || {});
                    layer.bindTooltip(countryName, {
                        sticky: true
                    });
                }
            }).addTo(africaLayerGroup);
        }

        if (!shapeFiles.length) {
            statusEl.textContent = 'No shapefiles found in public/assets/Africa.';
        } else {
            let loaded = 0;
            statusEl.textContent = `Loading ${shapeFiles.length} shapefiles...`;

            const loaders = shapeFiles.map(function(shapeUrl) {
                return shp(shapeUrl).then(function(rawShape) {
                    addShapeToMap(rawShape, shapeUrl);
                    loaded += 1;
                    statusEl.textContent = `Loaded ${loaded}/${shapeFiles.length} shapefiles...`;
                });
            });

            Promise.allSettled(loaders).then(function(results) {
                const failed = results.filter(function(result) {
                    return result.status === 'rejected';
                }).length;

                if (africaLayerGroup.getLayers().length > 0) {
                    map.fitBounds(africaLayerGroup.getBounds(), {
                        padding: [16, 16],
                        maxZoom: 5
                    });
                }

                if (failed > 0) {
                    statusEl.textContent = `Map loaded with ${failed} shapefile(s) skipped due to read errors.`;
                } else {
                    statusEl.textContent = 'Africa map loaded successfully.';
                }
            });
        }
    </script>
</body>

</html>
