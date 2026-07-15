(() => {
    'use strict';

    const monitor = document.getElementById('live-discussion-monitor');
    if (!monitor) return;

    const feed = document.getElementById('live-contribution-feed');
    const emptyState = document.getElementById('live-feed-empty');
    const topicFilter = document.getElementById('live-topic-filter');
    const indicator = document.getElementById('live-monitor-indicator');
    const syncLabel = document.getElementById('last-live-sync');
    const messageBox = document.getElementById('live-feed-message');

    const boundedNumber = (value, fallback, minimum, maximum) => {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? Math.min(maximum, Math.max(minimum, parsed)) : fallback;
    };

    const pollInterval = boundedNumber(monitor.dataset.pollInterval, 4000, 2500, 30000);
    const hiddenPollInterval = boundedNumber(monitor.dataset.hiddenPollInterval, 15000, pollInterval, 60000);
    const requestTimeout = boundedNumber(monitor.dataset.requestTimeout, 8000, 3000, 30000);
    const statsRefreshInterval = boundedNumber(monitor.dataset.statsRefreshInterval, 15000, 10000, 60000);
    const fullRefreshInterval = boundedNumber(monitor.dataset.fullRefreshInterval, 60000, 30000, 300000);
    const maxCards = boundedNumber(monitor.dataset.maxCards, 40, 10, 60);

    let pollTimer = null;
    let inFlight = null;
    let activeRequest = null;
    let actionInFlight = false;
    let queuedRefresh = false;
    let stopped = false;
    let hasLoaded = false;
    let syncCursor = null;
    let generation = 0;
    let consecutiveFailures = 0;
    let immediateRecoveryAttempts = 0;
    let lastStatsAt = 0;
    let lastFullSnapshotAt = 0;
    let selectedTopicValue = topicFilter.value;
    const maxImmediateRecoveryAttempts = 2;
    const deferredUpdates = new Map();

    const setElementText = (element, value) => {
        if (!element) return;
        const nextValue = String(value);
        if (element.textContent !== nextValue) element.textContent = nextValue;
    };

    const setCount = (id, value) => {
        const element = document.getElementById(id);
        setElementText(element, Number(value || 0).toLocaleString());
    };

    const showMessage = (message, type = 'success') => {
        messageBox.replaceChildren();
        if (!message) return;

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} border-0 shadow-sm`;
        alert.textContent = message;
        messageBox.appendChild(alert);
        window.setTimeout(() => alert.remove(), 5000);
    };

    const cardFromHtml = (html) => {
        const template = document.createElement('template');
        template.innerHTML = String(html || '').trim();
        return template.content.firstElementChild;
    };

    const currentCards = () => Array.from(feed.querySelectorAll('[data-live-post-id]'));

    const syncCards = (items, visibleIds) => {
        const activeElement = document.activeElement;
        const normalizedIds = visibleIds.slice(0, maxCards).map(String);
        const visibleSet = new Set(normalizedIds);
        const updates = new Map(deferredUpdates);
        deferredUpdates.clear();

        items.forEach((item) => updates.set(String(item.id), item));

        updates.forEach((item, id) => {
            if (!visibleSet.has(id)) return;

            let card = document.getElementById(`live-post-${id}`);
            const isBeingEdited = card && activeElement && card.contains(activeElement);

            if (isBeingEdited && card.dataset.liveVersion !== item.version) {
                deferredUpdates.set(id, item);
                return;
            }

            if (!card) {
                card = cardFromHtml(item.html);
                if (!card) return;
                if (hasLoaded) card.classList.add('is-new');
                feed.appendChild(card);
            } else if (card.dataset.liveVersion !== item.version) {
                const replacement = cardFromHtml(item.html);
                if (replacement) {
                    card.replaceWith(replacement);
                    card = replacement;
                }
            }
        });

        currentCards().forEach((card) => {
            if (visibleSet.has(card.dataset.livePostId)) return;

            if (activeElement && card.contains(activeElement)) {
                return;
            }

            deferredUpdates.delete(card.dataset.livePostId);
            card.remove();
        });

        const desiredCards = normalizedIds
            .map((id) => document.getElementById(`live-post-${id}`))
            .filter(Boolean);
        const activeCard = activeElement?.closest?.('[data-live-post-id]') || null;
        let insertionPoint = emptyState.nextElementSibling;

        desiredCards.forEach((card) => {
            // Moving a focused card through a fragment makes Chrome drop textarea
            // focus and selection. Move only cards that are out of position, and
            // always leave the actively edited card anchored in the document.
            if (card === activeCard) {
                insertionPoint = card.nextElementSibling;
                return;
            }

            if (card !== insertionPoint) {
                feed.insertBefore(card, insertionPoint);
            }
            insertionPoint = card.nextElementSibling;
        });

        // Keep the configured window plus, at most, one card containing an active
        // moderation form so an editor never loses a reason they are typing.
        const retainedCards = currentCards();
        const hardLimit = maxCards + (activeCard && !visibleSet.has(activeCard.dataset.livePostId) ? 1 : 0);
        retainedCards.slice(hardLimit).forEach((card) => {
            if (card !== activeCard) card.remove();
        });

        emptyState.hidden = normalizedIds.length > 0;
        if (normalizedIds.length === 0) {
            setElementText(emptyState.querySelector('.fw-bold'), 'No contributions found for this discussion');
            setElementText(
                emptyState.querySelector('.forum-muted'),
                'The stream will update automatically when someone contributes.'
            );
        }

        hasLoaded = true;

        return !normalizedIds.some((id) => !document.getElementById(`live-post-${id}`));
    };

    const syncReactionCounts = (counts) => {
        if (!counts || typeof counts !== 'object') return;

        const targets = new Map(Array.from(feed.querySelectorAll('[data-live-reactions-count]'))
            .map((element) => [element.dataset.liveReactionsCount, element]));
        Object.entries(counts).forEach(([postId, count]) => {
            const element = targets.get(String(postId));
            setElementText(element, Number(count || 0).toLocaleString());
        });
    };

    const schedulePoll = (delay = null) => {
        window.clearTimeout(pollTimer);
        if (stopped) return;

        if (actionInFlight) {
            queuedRefresh = true;
            return;
        }

        const baseDelay = document.hidden ? hiddenPollInterval : pollInterval;
        const backoffDelay = consecutiveFailures
            ? Math.min(30000, baseDelay * (2 ** Math.min(consecutiveFailures, 3)))
            : baseDelay;

        pollTimer = window.setTimeout(() => void poll(), delay === null ? backoffDelay : delay);
    };

    const runPoll = async (requestGeneration) => {
        const endpoint = new URL(monitor.dataset.feedUrl, window.location.origin);
        if (topicFilter.value) endpoint.searchParams.set('topic_id', topicFilter.value);
        const forceFullSnapshot = !syncCursor || (Date.now() - lastFullSnapshotAt >= fullRefreshInterval);
        if (!forceFullSnapshot) endpoint.searchParams.set('cursor', syncCursor);
        if (!lastStatsAt || (Date.now() - lastStatsAt >= statsRefreshInterval)) {
            endpoint.searchParams.set('include_stats', '1');
        }

        const requestState = {
            controller: new AbortController(),
            reason: null,
        };
        activeRequest = requestState;
        const timeout = window.setTimeout(() => {
            requestState.reason = 'timeout';
            requestState.controller.abort();
        }, requestTimeout);

        try {
            const response = await fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: requestState.controller.signal,
            });

            if (!response.ok) throw new Error(`Live stream returned ${response.status}`);
            const payload = await response.json();

            if (requestGeneration !== generation || stopped) return;

            const items = Array.isArray(payload.items) ? payload.items : [];
            const visibleIds = Array.isArray(payload.visible_ids)
                ? payload.visible_ids
                : items.map((item) => item.id);

            syncCursor = typeof payload.sync_cursor === 'string' ? payload.sync_cursor : null;
            const completeSnapshot = syncCards(items, visibleIds);
            syncReactionCounts(payload.reaction_counts);
            if (payload.stats && typeof payload.stats === 'object') {
                setCount('live-count', payload.stats.live);
                setCount('removed-count', payload.stats.removed);
                setCount('recent-count', payload.stats.last_hour);
                lastStatsAt = Date.now();
            }
            setElementText(syncLabel, new Date(payload.synced_at).toLocaleTimeString());

            if (completeSnapshot) {
                immediateRecoveryAttempts = 0;
                consecutiveFailures = 0;
                if (payload.meta?.is_delta === false) lastFullSnapshotAt = Date.now();
                setElementText(indicator, 'Monitoring live');
                indicator.classList.remove('is-offline');
            } else {
                syncCursor = null;
                immediateRecoveryAttempts += 1;

                if (immediateRecoveryAttempts <= maxImmediateRecoveryAttempts) {
                    queuedRefresh = true;
                } else {
                    consecutiveFailures = Math.min(consecutiveFailures + 1, 3);
                    setElementText(indicator, 'Stream refresh delayed');
                    indicator.classList.add('is-offline');
                }
            }
        } catch (error) {
            const intentionalAbort = error.name === 'AbortError'
                && ['refresh', 'moderation', 'stop'].includes(requestState.reason);

            if (!intentionalAbort && requestGeneration === generation && !stopped) {
                consecutiveFailures += 1;
                setElementText(indicator, requestState.reason === 'timeout' ? 'Stream timed out' : 'Reconnecting…');
                indicator.classList.add('is-offline');
                setElementText(syncLabel, requestState.reason === 'timeout'
                    ? 'request timed out'
                    : 'connection interrupted');
            }
        } finally {
            window.clearTimeout(timeout);
            if (activeRequest === requestState) activeRequest = null;
        }
    };

    function poll() {
        if (stopped || actionInFlight) {
            queuedRefresh = true;
            return Promise.resolve();
        }

        if (inFlight) {
            queuedRefresh = true;
            return inFlight;
        }

        window.clearTimeout(pollTimer);
        feed.setAttribute('aria-busy', 'true');
        const requestGeneration = generation;

        inFlight = runPoll(requestGeneration).finally(() => {
            inFlight = null;
            feed.setAttribute('aria-busy', 'false');

            if (stopped) return;
            if (queuedRefresh) {
                queuedRefresh = false;
                schedulePoll(0);
            } else {
                schedulePoll();
            }
        });

        return inFlight;
    }

    const requestImmediateRefresh = ({ reset = false } = {}) => {
        window.clearTimeout(pollTimer);

        if (reset) {
            generation += 1;
            syncCursor = null;
            hasLoaded = false;
            immediateRecoveryAttempts = 0;
            lastStatsAt = 0;
            lastFullSnapshotAt = 0;
            deferredUpdates.clear();
        }

        if (inFlight && activeRequest) {
            queuedRefresh = true;
            activeRequest.reason = 'refresh';
            activeRequest.controller.abort();
            return;
        }

        queuedRefresh = false;
        schedulePoll(0);
    };

    feed.addEventListener('submit', async (event) => {
        const form = event.target.closest('.js-live-remove-form');
        if (!form) return;
        event.preventDefault();

        if (actionInFlight) {
            showMessage('Another moderation action is still being processed.', 'warning');
            return;
        }

        if (!form.reportValidity()) return;
        if (!window.confirm('Remove this contribution from the public discussion?')) return;

        const button = form.querySelector('button[type="submit"]');
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Removing…';
        actionInFlight = true;
        window.clearTimeout(pollTimer);

        if (activeRequest) {
            activeRequest.reason = 'moderation';
            activeRequest.controller.abort();
        }
        if (inFlight) await inFlight;

        const controller = new AbortController();
        const timeout = window.setTimeout(() => controller.abort(), requestTimeout);

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData(form),
                credentials: 'same-origin',
                signal: controller.signal,
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok) {
                const validationMessage = payload.errors?.reason?.[0];
                throw new Error(validationMessage || payload.message || 'The contribution could not be removed.');
            }

            showMessage(payload.message || 'Contribution removed from the public discussion.');
            form.closest('details')?.removeAttribute('open');
        } catch (error) {
            const message = error.name === 'AbortError'
                ? 'The moderation request timed out. Please try again.'
                : (error.message || 'The contribution could not be removed.');
            showMessage(message, 'danger');
        } finally {
            window.clearTimeout(timeout);
            actionInFlight = false;
            button.disabled = false;
            button.textContent = originalText;
            lastStatsAt = 0;
            requestImmediateRefresh();
        }
    });

    topicFilter.addEventListener('change', () => {
        const nextTopicValue = topicFilter.value;
        const hasUnsentReason = currentCards().some((card) => {
            const reason = card.querySelector('.js-remove-reason');
            return reason && reason.value.length > 0;
        });

        if (hasUnsentReason && !window.confirm('Switch discussions and discard the moderation reason you are typing?')) {
            topicFilter.value = selectedTopicValue;
            return;
        }

        selectedTopicValue = nextTopicValue;
        currentCards().forEach((card) => card.remove());
        emptyState.hidden = false;
        setElementText(emptyState.querySelector('.fw-bold'), 'Switching live discussion stream…');
        requestImmediateRefresh({ reset: true });
    });

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) requestImmediateRefresh();
    });

    window.addEventListener('pagehide', () => {
        stopped = true;
        window.clearTimeout(pollTimer);
        if (activeRequest) {
            activeRequest.reason = 'stop';
            activeRequest.controller.abort();
        }
    }, { once: true });

    schedulePoll(0);
})();
