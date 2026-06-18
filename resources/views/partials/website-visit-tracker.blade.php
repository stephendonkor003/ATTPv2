<script>
    (function () {
        if (window.__attpWebsiteVisitTracker) return;
        window.__attpWebsiteVisitTracker = true;

        const startUrl = @json($startUrl);
        const heartbeatUrl = @json($heartbeatUrl);
        const visitorKey = 'attp_website_visitor_uuid';
        const visitKey = 'attp_website_visit_id';
        const startedKey = 'attp_website_visit_started_at';

        function uuid() {
            if (window.crypto && typeof window.crypto.randomUUID === 'function') {
                return window.crypto.randomUUID();
            }

            return 'visit-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
        }

        function storageGet(storage, key) {
            try { return storage.getItem(key); } catch (error) { return null; }
        }

        function storageSet(storage, key, value) {
            try { storage.setItem(key, value); } catch (error) {}
        }

        let visitorUuid = storageGet(localStorage, visitorKey);
        if (!visitorUuid) {
            visitorUuid = uuid();
            storageSet(localStorage, visitorKey, visitorUuid);
        }

        let visitId = storageGet(sessionStorage, visitKey);
        let startedAt = Number(storageGet(sessionStorage, startedKey) || 0);
        if (!startedAt) {
            startedAt = Date.now();
            storageSet(sessionStorage, startedKey, String(startedAt));
        }

        function durationSeconds() {
            return Math.max(0, Math.round((Date.now() - startedAt) / 1000));
        }

        function payload(ended) {
            return {
                visitor_uuid: visitorUuid,
                visit_id: visitId,
                url: window.location.href,
                path: window.location.pathname + window.location.search,
                title: document.title || '',
                referrer: document.referrer || '',
                duration_seconds: durationSeconds(),
                ended: Boolean(ended),
                timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || '',
                screen: window.screen ? `${window.screen.width}x${window.screen.height}` : ''
            };
        }

        function send(url, data, useBeacon) {
            const body = JSON.stringify(data);

            if (useBeacon && navigator.sendBeacon) {
                const blob = new Blob([body], { type: 'application/json' });
                navigator.sendBeacon(url, blob);
                return Promise.resolve();
            }

            return fetch(url, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                body,
                credentials: 'same-origin',
                keepalive: Boolean(useBeacon)
            }).then(function (response) {
                if (!response.ok) return null;
                return response.json().catch(function () { return null; });
            });
        }

        send(startUrl, payload(false), false).then(function (data) {
            if (data && data.visit_id) {
                visitId = data.visit_id;
                storageSet(sessionStorage, visitKey, visitId);
            }
        }).catch(function () {});

        setInterval(function () {
            if (!visitId || document.visibilityState === 'hidden') return;
            send(heartbeatUrl, payload(false), false).catch(function () {});
        }, 30000);

        document.addEventListener('visibilitychange', function () {
            if (!visitId) return;
            send(heartbeatUrl, payload(document.visibilityState === 'hidden'), document.visibilityState === 'hidden').catch(function () {});
        });

        window.addEventListener('pagehide', function () {
            if (!visitId) return;
            send(heartbeatUrl, payload(true), true);
        });
    })();
</script>
