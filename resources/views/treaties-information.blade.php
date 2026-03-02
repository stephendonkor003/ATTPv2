<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>AU Treaties Information</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="{{ asset('assets/style.css') }}" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <style>
        :root{--gold:#fbbc05;--magenta:#a70d53;--wine:#522b39;--light:#f7f4f2}
        body{font-family:Inter,sans-serif;background:var(--light);margin:0}
        .hero{height:320px;display:flex;align-items:center;justify-content:center;text-align:center;color:#fff;background:linear-gradient(120deg,rgba(82,43,57,.9),rgba(167,13,83,.85)),url('{{ asset('assets/images/au3.jpg') }}') center/cover no-repeat}
        .hero .content{max-width:900px;padding:0 1rem}
        .hero h1{color:var(--gold);font-size:2.2rem;margin:0 0 .6rem}
        .hero p{margin:0;line-height:1.7}
        .filter-wrap{max-width:1280px;margin:-52px auto 1rem;padding:0 1rem;position:relative;z-index:10}
        .filter-bar{background:#fff;border-radius:12px;box-shadow:0 10px 24px rgba(0,0,0,.12);padding:1rem;display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:.75rem}
        .filter-bar label{display:block;font-size:.82rem;color:#6b7280;font-weight:600;margin-bottom:.34rem}
        .filter-bar select,.filter-bar button{width:100%;padding:.56rem .62rem;border-radius:8px;border:1px solid #d1d5db}
        .filter-bar button{border:0;background:var(--magenta);color:#fff;font-weight:600}
        .content{max-width:1280px;margin:0 auto 2.8rem;padding:0 1rem}
        .panel{background:#fff;border-radius:14px;box-shadow:0 8px 18px rgba(0,0,0,.08);padding:1rem;margin-bottom:1rem}
        .panel h2{color:var(--wine);font-size:1.15rem;margin:0 0 .35rem}
        .meta{margin:0 0 .65rem;color:#6b7280;font-size:.9rem}
        #treaties-map{width:100%;height:clamp(360px,62vh,620px);border-radius:10px;background:linear-gradient(135deg,#ecfeff,#eff6ff)}
        .legend{display:flex;flex-wrap:wrap;gap:.7rem 1rem;margin-top:.7rem;font-size:.83rem}
        .dot{width:12px;height:12px;border-radius:3px;border:1px solid rgba(0,0,0,.2);display:inline-block;margin-right:.4rem}
        .pill{font-size:.76rem;font-weight:700;border-radius:999px;padding:.22rem .55rem;display:inline-flex}
        .pill.signed{background:#fef9c3;color:#854d0e}.pill.ratified{background:#dcfce7;color:#166534}.pill.original{background:#ccfbf1;color:#115e59}.pill.none{background:#f3f4f6;color:#4b5563}
        #treaties-table thead th{background:var(--wine)!important;color:#fff!important;border-bottom:none!important;font-size:.84rem}
        @media(max-width:980px){.filter-bar{grid-template-columns:1fr}}
    </style>
</head>
<body>
    <header class="navbar">
        <div class="logo"><img src="{{ asset('assets/images/au.png ') }}" alt="ATTP" class="logo logo-sm"></div>
        <nav class="nav-links">
            <a href="{{ route('landing.index') }}">Home</a>
            <a href="{{ route('impact.map') }}">Impact Map</a>
            <a href="{{ route('events') }}">Events / Webinars</a>
            <a href="#contact">Contact</a>
            <a href="{{ route('careers.index') }}">Career</a>
        </nav>
        <div class="nav-actions">
            <a href="{{ route('login') }}" class="btn btn-login">Login</a>
            <a href="{{ route('public.procurement.index') }}" class="btn btn-primary">Policy Programs & Research</a>
        </div>
    </header>

    <section class="hero">
        <div class="content">
            <h1>African Union Treaties Information</h1>
            <p>Filter by treaty, member state, and status to redraw map indicators and review detailed records below.</p>
        </div>
    </section>

    <div class="filter-wrap">
        <div class="filter-bar">
            <div>
                <label for="filter-treaty">Treaty</label>
                <select id="filter-treaty">
                    <option value="">All Treaties</option>
                    @foreach ($treatiesData as $treaty)
                        <option value="{{ $treaty['id'] }}">{{ $treaty['short_title'] ?: $treaty['title'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-member">Member State</label>
                <select id="filter-member">
                    <option value="">All Member States</option>
                    @foreach ($memberStates as $state)
                        <option value="{{ $state['country_code'] }}">{{ $state['country_name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="filter-status">Status</label>
                <select id="filter-status">
                    <option value="">All Statuses</option>
                    <option value="signed">Signed</option>
                    <option value="ratified">Ratified</option>
                    <option value="original_submitted">Original Submitted</option>
                    <option value="no_action">No Recorded Action</option>
                </select>
            </div>
            <div>
                <label>&nbsp;</label>
                <button type="button" id="reset-filters">Reset Filters</button>
            </div>
        </div>
    </div>

    <main class="content">
        <section class="panel">
            <h2>Shape Map Indicators</h2>
            <p class="meta" id="map-caption">Combined view across all treaties.</p>
            <div id="treaties-map"></div>
            <p class="meta" id="map-status" style="margin-top:.65rem;">Loading map indicators...</p>
            <div class="legend">
                <span><span class="dot" style="background:#0f766e;"></span>Original Submitted</span>
                <span><span class="dot" style="background:#2e7d32;"></span>Ratified</span>
                <span><span class="dot" style="background:#facc15;"></span>Signed</span>
                <span><span class="dot" style="background:#d1d5db;"></span>No Action</span>
            </div>
        </section>
        <section class="panel">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h2 style="margin:0;">Treaty Country Status Table</h2>
                <small id="table-count" class="text-muted">0 record(s)</small>
            </div>
            <div class="table-responsive">
                <table id="treaties-table" class="table table-striped w-100">
                    <thead>
                        <tr>
                            <th>Treaty</th>
                            <th>Member State</th>
                            <th>Status</th>
                            <th>Signed Date</th>
                            <th>Ratified Date</th>
                            <th>Original Submitted Date</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </section>
    </main>

    <footer id="contact" class="footer" style="margin-top:4rem;">
        <div class="footer-content">
            <div class="footer-logo"><h3>ATTP<span> Administration</span></h3><p>{{ __('landing.footer_description') }}</p></div>
            <div class="footer-links">
                <h4>{{ __('landing.footer_links_title') }}</h4>
                <a href="{{ route('landing.index') }}">{{ __('landing.footer_link_home') }}</a>
                <a href="{{ route('impact.map') }}">{{ __('navigation.impact_map') }}</a>
                <a href="{{ route('careers.index') }}">{{ __('navigation.careers') }}</a>
                <a href="#contact">{{ __('navigation.contact') }}</a>
            </div>
            <div class="footer-contact"><h4>{{ __('landing.footer_contact_title') }}</h4><p>{{ __('landing.footer_email') }}</p><p>{{ __('landing.footer_copyright', ['year' => date('Y')]) }}</p></div>
        </div>
    </footer>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/shpjs@6.2.0/dist/shp.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <script>
        const shapeFiles = @json($shapeFiles ?? []);
        const treatiesData = @json($treatiesData ?? []);
        const tableRows = @json($statusTableRows ?? []);
        const map = L.map('treaties-map', {center:[0,20],zoom:3,minZoom:3,maxZoom:8,scrollWheelZoom:true,zoomControl:true,attributionControl:false});
        const mapLayer = L.featureGroup().addTo(map);
        const mapStatusEl = document.getElementById('map-status');
        const mapCaptionEl = document.getElementById('map-caption');
        const filterTreatyEl = document.getElementById('filter-treaty');
        const filterMemberEl = document.getElementById('filter-member');
        const filterStatusEl = document.getElementById('filter-status');
        const resetFiltersEl = document.getElementById('reset-filters');
        const tableBodyEl = document.querySelector('#treaties-table tbody');
        const tableCountEl = document.getElementById('table-count');
        const countryLayersByCode = {};
        const defaultStatus = {is_signed:false,is_ratified:false,is_original_submitted:false,signed_at:null,ratified_at:null,original_submitted_at:null};
        let activeTreatyId = String(filterTreatyEl.value || '');
        let activeMemberCode = String(filterMemberEl.value || '').toUpperCase();
        let activeStatus = String(filterStatusEl.value || '');

        function toId(v){return v===null||v===undefined?'':String(v)}
        function norm(v){const i=(v||'').toString();const n=typeof i.normalize==='function'?i.normalize('NFD'):i;return n.replace(/[\u0300-\u036f]/g,'').replace(/[\u2019']/g,'').replace(/[^a-zA-Z0-9 ]/g,' ').replace(/\s+/g,' ').trim().toLowerCase()}
        const alias={ 'cabo verde':'CV','cape verde':'CV','ivory coast':'CI','cote divoire':'CI','c te divoire':'CI','swaziland':'SZ','eswatini':'SZ','sao tome and principe':'ST','sao tome principe':'ST','democratic republic of congo':'CD','democratic republic of the congo':'CD','dr congo':'CD','drc':'CD','republic of congo':'CG','congo republic':'CG','congo brazzaville':'CG' };
        const codeByName = tableRows.reduce((a,r)=>{const c=String(r.country_code||'').toUpperCase();const n=norm(r.country_name||'');if(n&&/^[A-Z]{2}$/.test(c))a[n]=c;return a;},{});
        const treatyIndexById = treatiesData.reduce((a,t)=>{a[toId(t.id)] = (t.statuses||[]).reduce((x,row)=>{const c=String(row.country_code||'').toUpperCase(); if(/^[A-Z]{2}$/.test(c)){x[c]={is_signed:!!row.is_signed,is_ratified:!!row.is_ratified,is_original_submitted:!!row.is_original_submitted,signed_at:row.signed_at||null,ratified_at:row.ratified_at||null,original_submitted_at:row.original_submitted_at||null};} return x;},{}); return a;},{});
        const combinedIndex = Object.values(treatyIndexById).reduce((a,idx)=>{Object.keys(idx).forEach(c=>{const i=idx[c],k=a[c]||{...defaultStatus};a[c]={is_signed:k.is_signed||i.is_signed,is_ratified:k.is_ratified||i.is_ratified,is_original_submitted:k.is_original_submitted||i.is_original_submitted,signed_at:k.signed_at||i.signed_at,ratified_at:k.ratified_at||i.ratified_at,original_submitted_at:k.original_submitted_at||i.original_submitted_at};}); return a;},{});
        function codeOf(name){const n=norm(name); if(!n) return null; return alias[n] || codeByName[n] || null;}
        function statusOfCountry(name){const c=codeOf(name); if(!c) return defaultStatus; if(activeTreatyId && treatyIndexById[activeTreatyId]) return treatyIndexById[activeTreatyId][c]||defaultStatus; return combinedIndex[c]||defaultStatus;}
        function statusLabel(s){if(s.is_original_submitted) return 'Original Submitted'; if(s.is_ratified) return 'Ratified'; if(s.is_signed) return 'Signed'; return 'No Recorded Action'}
        function statusClass(s){if(s.is_original_submitted) return 'original'; if(s.is_ratified) return 'ratified'; if(s.is_signed) return 'signed'; return 'none'}
        function statusColor(s){if(s.is_original_submitted) return '#0f766e'; if(s.is_ratified) return '#2e7d32'; if(s.is_signed) return '#facc15'; return '#d1d5db'}
        function matchStatus(s){if(!activeStatus) return true; if(activeStatus==='signed') return !!s.is_signed; if(activeStatus==='ratified') return !!s.is_ratified; if(activeStatus==='original_submitted') return !!s.is_original_submitted; if(activeStatus==='no_action') return !s.is_signed&&!s.is_ratified&&!s.is_original_submitted; return true}
        function setMapStatus(msg){if(mapStatusEl) mapStatusEl.textContent = msg}
        function fDate(v){if(!v) return '-'; const d=new Date(`${v}T00:00:00`); return Number.isNaN(d.getTime())?v:d.toLocaleDateString(undefined,{day:'2-digit',month:'short',year:'numeric'})}
        function rowStatus(r){return {is_signed:!!r.is_signed,is_ratified:!!r.is_ratified,is_original_submitted:!!r.is_original_submitted}}
        function treatyName(r){return (r.treaty_short_title||'').trim() || (r.treaty_title||'').trim() || 'Treaty'}
        function getName(feature,url){const p=feature&&feature.properties?feature.properties:{};return p.NAME||p.ADMIN||p.COUNTRY||p.name||decodeURIComponent((url.split('/').pop()||'').replace(/\.shp$/i,''))||'Country'}
        function toCollection(raw){if(!raw) return {type:'FeatureCollection',features:[]}; if(raw.type==='FeatureCollection') return raw; if(raw.type==='Feature') return {type:'FeatureCollection',features:[raw]}; if(Array.isArray(raw)){const f=[]; raw.forEach(i=>f.push(...toCollection(i).features)); return {type:'FeatureCollection',features:f};} if(typeof raw==='object'){const f=[]; Object.keys(raw).forEach(k=>f.push(...toCollection(raw[k]).features)); return {type:'FeatureCollection',features:f};} return {type:'FeatureCollection',features:[]};}
        function styleFor(name,code){const s=statusOfCountry(name); const visible = matchStatus(s) && (!activeMemberCode || code===activeMemberCode); if(!visible) return {fillColor:'#e5e7eb',weight:.8,opacity:.75,color:'#fff',fillOpacity:.26}; return {fillColor:statusColor(s),weight:2.1,opacity:1,color:'#111827',fillOpacity:.9};}
        function updateStyle(layer){if(layer&&typeof layer.setStyle==='function') layer.setStyle(styleFor(layer._countryName||'', layer._countryCode||''))}
        function hover(e){const l=e.target,n=l._countryName||'Country',s=statusOfCountry(n);l.setStyle({weight:3,color:'#111827',fillOpacity:.96});const t = treatiesData.find(x=>toId(x.id)===activeTreatyId); const scope = t ? ((t.short_title||'').trim()||t.title||'Treaty') : 'All Treaties'; l.bindPopup(`<div class="popup-content"><div class="popup-country">${n}</div><div class="popup-stat"><span>Treaty Scope:</span><span>${scope}</span></div><div class="popup-stat"><span>Status:</span><span>${statusLabel(s)}</span></div><div class="popup-stat"><span>Signed:</span><span>${s.signed_at||'-'}</span></div><div class="popup-stat"><span>Ratified:</span><span>${s.ratified_at||'-'}</span></div><div class="popup-stat"><span>Original Submitted:</span><span>${s.original_submitted_at||'-'}</span></div></div>`).openPopup();}
        function unhover(e){updateStyle(e.target); e.target.closePopup()}
        function addShape(raw,url){const fc = toCollection(raw); if(!fc.features.length) return; L.geoJSON(fc,{style:f=>{const n=getName(f,url),c=codeOf(n)||''; return styleFor(n,c)}, onEachFeature:(f,l)=>{const n=getName(f,url),c=codeOf(n)||''; l._countryName=n; l._countryCode=c; if(c){countryLayersByCode[c]=countryLayersByCode[c]||[]; countryLayersByCode[c].push(l);} l.on({mouseover:hover,mouseout:unhover});}}).addTo(mapLayer)}
        function refreshMap(){mapLayer.eachLayer(g=>{if(typeof g.eachLayer==='function') g.eachLayer(updateStyle)})}
        function fitMap(anim=true){if(activeMemberCode && countryLayersByCode[activeMemberCode] && countryLayersByCode[activeMemberCode].length){const fg=L.featureGroup(countryLayersByCode[activeMemberCode]); map.fitBounds(fg.getBounds(),{padding:[34,34],maxZoom:5,animate:anim}); return;} if(mapLayer.getLayers().length){map.fitBounds(mapLayer.getBounds(),{padding:[26,26],maxZoom:4,animate:anim});}}
        function resolveUrl(u){const raw=(u||'').toString().trim(); if(!raw) return ''; try{const r=new URL(raw,window.location.href); const isHttp=r.protocol==='http:'||r.protocol==='https:'; const isShape=/\/assets\/Africa\/[^/]+\.shp$/i.test(r.pathname); if(isShape && r.host!==window.location.host) return new URL(`${r.pathname}${r.search}${r.hash}`,window.location.origin).toString(); if(isHttp && r.protocol!==window.location.protocol) r.protocol=window.location.protocol; return r.toString();}catch(e){return raw.startsWith('/')?`${window.location.origin}${raw}`:raw;}}
        function refreshCaption(){if(!mapCaptionEl) return; const treatyText=activeTreatyId?(filterTreatyEl.options[filterTreatyEl.selectedIndex]?.text||'Selected Treaty'):'All Treaties'; const memberText=activeMemberCode?(filterMemberEl.options[filterMemberEl.selectedIndex]?.text||'Selected Member State'):'All Member States'; const statusText=activeStatus?(filterStatusEl.options[filterStatusEl.selectedIndex]?.text||'Selected Status'):'All Statuses'; mapCaptionEl.textContent = `${treatyText} | ${memberText} | ${statusText}`;}
        function renderTable(){const rows = tableRows.filter(r=>{if(activeTreatyId && toId(r.treaty_id)!==activeTreatyId) return false; if(activeMemberCode && String(r.country_code||'').toUpperCase()!==activeMemberCode) return false; return matchStatus(rowStatus(r));}).sort((a,b)=>{const t=treatyName(a).localeCompare(treatyName(b)); return t!==0?t:String(a.country_name||'').localeCompare(String(b.country_name||''));}); if(tableCountEl) tableCountEl.textContent=`${rows.length} record(s)`; tableBodyEl.innerHTML = rows.map(r=>`<tr><td>${treatyName(r)}</td><td>${r.country_name||''}</td><td><span class="pill ${statusClass(rowStatus(r))}">${statusLabel(rowStatus(r))}</span></td><td>${fDate(r.signed_at)}</td><td>${fDate(r.ratified_at)}</td><td>${fDate(r.original_submitted_at)}</td></tr>`).join(''); if($.fn.DataTable.isDataTable('#treaties-table')) $('#treaties-table').DataTable().destroy(); $('#treaties-table').DataTable({pageLength:15,lengthMenu:[[15,25,50,-1],[15,25,50,'All']],order:[[0,'asc'],[1,'asc']],language:{search:'Search records:',lengthMenu:'Show _MENU_ rows',info:'Showing _START_ to _END_ of _TOTAL_ rows'},dom:'<\"row\"<\"col-sm-12 col-md-6\"l><\"col-sm-12 col-md-6\"f>>rtip'});}
        function applyFilters(){activeTreatyId=toId(filterTreatyEl.value||''); activeMemberCode=String(filterMemberEl.value||'').toUpperCase(); activeStatus=String(filterStatusEl.value||''); refreshCaption(); refreshMap(); fitMap(false); renderTable();}
        function loadShapes(){if(!shapeFiles.length){setMapStatus('No shapefiles found in public/assets/Africa.'); return;} setMapStatus(`Loading ${shapeFiles.length} shapefiles...`); let loaded=0; const loaders=shapeFiles.map(u=>{const r=resolveUrl(u); return shp(r).then(raw=>{addShape(raw,r); loaded+=1; setMapStatus(`Loaded ${loaded}/${shapeFiles.length} shapefiles...`)}).catch(e=>{throw {shapeUrl:r,error:e};});}); Promise.allSettled(loaders).then(res=>{const failed=res.filter(x=>x.status==='rejected'); failed.forEach(x=>{if(x.reason&&x.reason.shapeUrl) console.warn('Failed to load shapefile:',x.reason.shapeUrl,x.reason.error)}); refreshMap(); fitMap(false); setMapStatus(failed.length?`Map loaded with ${failed.length} shapefile(s) skipped due to read errors.`:'Treaty map indicators loaded.');});}
        filterTreatyEl.addEventListener('change', applyFilters); filterMemberEl.addEventListener('change', applyFilters); filterStatusEl.addEventListener('change', applyFilters);
        resetFiltersEl.addEventListener('click', ()=>{filterTreatyEl.value=''; filterMemberEl.value=''; filterStatusEl.value=''; applyFilters();});
        window.addEventListener('resize', ()=>setTimeout(()=>map.invalidateSize(),120));
        document.addEventListener('DOMContentLoaded', ()=>{refreshCaption(); renderTable(); loadShapes(); setTimeout(()=>map.invalidateSize(),120);});
    </script>
</body>
</html>
