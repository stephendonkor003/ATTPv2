(function () {
    'use strict';

    var root = document.getElementById('discussion-app');
    var config = window.ATTP_DISCUSSION_CONFIG || {};

    if (!root) {
        return;
    }

    var showForumStartupFailure = function (error) {
        root.querySelectorAll('[v-cloak]').forEach(function (element) {
            element.removeAttribute('v-cloak');
        });

        var content = document.getElementById('discussion-content');
        if (content) {
            content.innerHTML = '<div class="forum-system-alert forum-system-alert--danger" role="alert"><span class="forum-system-alert__icon">!</span><div><strong>The live forum could not start</strong><p>Please refresh the page. If the problem continues, contact the ATTP support team.</p></div></div>';
        }

        if (error && window.console && typeof window.console.error === 'function') {
            window.console.error('ATTP discussion forum startup failed.', error);
        }
    };

    if (!window.Vue || typeof window.Vue.createApp !== 'function') {
        showForumStartupFailure();
        return;
    }

    var TOKEN_KEY = 'attp_discussion_participant_token';

    try {
        window.Vue.createApp({
        data: function () {
            return {
                config: config,
                view: ['themes', 'topics', 'account'].indexOf(config.initialView) >= 0 ? config.initialView : 'themes',
                overview: null,
                themes: [],
                countries: [],
                countryMeta: {
                    represented_countries: 0,
                    participants_with_country: 0
                },
                topics: [],
                topicMeta: {
                    current_page: 1,
                    last_page: 1,
                    per_page: 12,
                    total: 0
                },
                selectedTopic: null,
                requestedTopicSlug: '',
                activeTheme: '',
                filters: {
                    search: '',
                    status: 'open'
                },
                statusOptions: [
                    { value: 'open', label: 'Open discussions' },
                    { value: 'closed', label: 'Closed discussions' },
                    { value: 'all', label: 'All discussions' }
                ],
                loading: {
                    overview: true,
                    themes: true,
                    countries: true,
                    topics: true,
                    topic: false
                },
                refreshing: false,
                bootError: '',
                lastRefreshedAt: null,
                notice: {
                    type: 'success',
                    message: ''
                },
                participant: null,
                blockedMessage: '',
                authMode: 'register',
                authBusy: false,
                authError: '',
                authValidation: {},
                passwordMode: '',
                passwordBusy: false,
                passwordError: '',
                passwordMessage: '',
                forgotPasswordForm: {
                    email: ''
                },
                resetPasswordForm: {
                    email: '',
                    token: '',
                    password: '',
                    password_confirmation: ''
                },
                registerStep: 1,
                loginForm: {
                    email: '',
                    password: ''
                },
                registerForm: {
                    display_name: '',
                    email: '',
                    country: '',
                    organization: '',
                    password: '',
                    password_confirmation: '',
                    terms: false
                },
                returnToTopic: '',
                postForm: {
                    body: ''
                },
                postError: '',
                posting: false,
                replyingTo: null,
                reactingPost: '',
                reactionOptions: [
                    { type: 'like', label: 'Like', emoji: '\u{1F44D}' },
                    { type: 'support', label: 'Support', emoji: '\u{1F49A}' },
                    { type: 'insightful', label: 'Insightful', emoji: '\u{1F4A1}' },
                    { type: 'agree', label: 'Agree', emoji: '\u{1F91D}' },
                    { type: 'celebrate', label: 'Celebrate', emoji: '\u{1F389}' }
                ],
                emojiOptions: [
                    { value: '\u{1F600}', label: 'Grinning face' },
                    { value: '\u{1F642}', label: 'Slightly smiling face' },
                    { value: '\u{1F914}', label: 'Thinking face' },
                    { value: '\u{1F44D}', label: 'Thumbs up' },
                    { value: '\u{1F44F}', label: 'Applause' },
                    { value: '\u{1F91D}', label: 'Handshake' },
                    { value: '\u{1F4A1}', label: 'Light bulb' },
                    { value: '\u{1F4CC}', label: 'Pushpin' },
                    { value: '\u{1F4CA}', label: 'Chart' },
                    { value: '\u{1F30D}', label: 'Africa and Europe globe' },
                    { value: '\u{1F331}', label: 'Seedling' },
                    { value: '\u{2705}', label: 'Check mark' },
                    { value: '\u{26A0}\u{FE0F}', label: 'Warning' },
                    { value: '\u{2753}', label: 'Question mark' },
                    { value: '\u{1F389}', label: 'Celebration' },
                    { value: '\u{2764}\u{FE0F}', label: 'Heart' }
                ],
                emojiPickerOpen: false,
                shareBusyPost: '',
                shareBusyTopic: false,
                highlightedPostId: '',
                highlightTimer: null,
                mobileResourcesOpen: false,
                countryToastIndex: 0,
                countryToastPaused: false,
                topicJoinerIndex: 0,
                topicToastPaused: false,
                joiningTopicSlug: '',
                countryToastTimer: null,
                topicActivityTimer: null,
                topicActivityBusy: false,
                topicActivityRevision: '',
                pollTimer: null,
                searchTimer: null,
                noticeTimer: null,
                visibilityHandler: null,
                popstateHandler: null
            };
        },

        computed: {
            activeThemeName: function () {
                var slug = this.activeTheme;
                var theme = this.themes.find(function (item) {
                    return item.slug === slug || item.id === slug;
                });

                return theme ? theme.name : '';
            },

            authValidationMessages: function () {
                return Object.keys(this.authValidation || {}).reduce(function (messages, key) {
                    var value = this.authValidation[key];
                    if (Array.isArray(value)) {
                        return messages.concat(value);
                    }
                    if (value) {
                        messages.push(String(value));
                    }
                    return messages;
                }, []);
            },

            representedCountries: function () {
                return this.countries
                    .filter(function (country) {
                        return Number(country.participants_count) > 0;
                    })
                    .slice()
                    .sort(function (left, right) {
                        return Number(right.participants_count) - Number(left.participants_count)
                            || String(left.name).localeCompare(String(right.name));
                    });
            },

            activeCountryToast: function () {
                if (!this.representedCountries.length) {
                    return null;
                }

                return this.representedCountries[this.countryToastIndex % this.representedCountries.length];
            },

            topicParticipation: function () {
                return this.selectedTopic && this.selectedTopic.participation
                    ? this.selectedTopic.participation
                    : {
                        participants_count: 0,
                        countries_count: 0,
                        active_now_count: 0,
                        recent_joiners: []
                    };
            },

            topicRecentJoiners: function () {
                return Array.isArray(this.topicParticipation.recent_joiners)
                    ? this.topicParticipation.recent_joiners
                    : [];
            },

            activeTopicJoiner: function () {
                if (!this.topicRecentJoiners.length) {
                    return null;
                }

                return this.topicRecentJoiners[this.topicJoinerIndex % this.topicRecentJoiners.length];
            },

            topicRelatedLinks: function () {
                return this.normaliseResourceList(this.selectedTopic && this.selectedTopic.related_links);
            },

            topicMaterials: function () {
                return this.normaliseResourceList(this.selectedTopic && this.selectedTopic.materials);
            },

            topicDocuments: function () {
                return this.normaliseResourceList(this.selectedTopic && this.selectedTopic.documents)
                    .filter(this.hasResourceUrl);
            },

            topicResourceCount: function () {
                return this.topicRelatedLinks.length + this.topicMaterials.length + this.topicDocuments.length;
            },

            lastRefreshedText: function () {
                if (!this.lastRefreshedAt) {
                    return 'Connecting...';
                }

                return 'updated ' + this.timeAgo(this.lastRefreshedAt);
            }
        },

        mounted: function () {
            var self = this;
            this.readLocationState();

            this.popstateHandler = function () {
                self.handlePopState();
            };
            window.addEventListener('popstate', this.popstateHandler);

            this.visibilityHandler = function () {
                if (!document.hidden) {
                    self.pollForum();
                }
            };
            document.addEventListener('visibilitychange', this.visibilityHandler);

            this.startForum();
        },

        beforeUnmount: function () {
            this.destroyCountrySelector();
            window.clearInterval(this.pollTimer);
            window.clearInterval(this.countryToastTimer);
            window.clearInterval(this.topicActivityTimer);
            window.clearTimeout(this.searchTimer);
            window.clearTimeout(this.noticeTimer);
            window.clearTimeout(this.highlightTimer);
            window.removeEventListener('popstate', this.popstateHandler);
            document.removeEventListener('visibilitychange', this.visibilityHandler);
        },

        methods: {
            normaliseView: function (view) {
                return ['themes', 'topics', 'account'].indexOf(view) >= 0 ? view : 'themes';
            },

            startForum: async function () {
                var tasks = [
                    this.loadOverview(),
                    this.loadThemes(),
                    this.loadTopics(1),
                    this.loadCountries(),
                    this.restoreSession()
                ];

                var results = await Promise.allSettled(tasks);
                var contentFailures = results.slice(0, 4).filter(function (result) {
                    return result.status === 'rejected';
                });

                if (contentFailures.length === 4) {
                    this.bootError = 'The discussion service is temporarily unavailable. Please check your connection and try again.';
                }

                if (this.requestedTopicSlug) {
                    try {
                        await this.loadTopic(this.requestedTopicSlug);
                    } catch (error) {
                        // loadTopic reports unavailable or removed discussions in the forum UI.
                    }
                }

                var interval = Number(this.config.pollInterval) || 30000;
                this.pollTimer = window.setInterval(this.pollForum.bind(this), Math.max(interval, 15000));
                this.countryToastTimer = window.setInterval(this.rotateCountryToast.bind(this), 5200);
                this.topicActivityTimer = window.setInterval(this.loadTopicActivity.bind(this), 5000);
            },

            retryBoot: async function () {
                this.bootError = '';
                var results = await Promise.allSettled([
                    this.loadOverview(),
                    this.loadThemes(),
                    this.loadTopics(1),
                    this.loadCountries()
                ]);

                if (results.every(function (result) { return result.status === 'rejected'; })) {
                    this.bootError = 'The discussion service is still unavailable. Please try again shortly.';
                }
            },

            readLocationState: function () {
                var params = new URLSearchParams(window.location.search);
                var status = params.get('status');
                this.activeTheme = params.get('theme') || '';
                this.filters.search = params.get('search') || '';
                this.filters.status = ['open', 'closed', 'all'].indexOf(status) >= 0 ? status : 'open';
                this.requestedTopicSlug = params.get('topic') || '';

                if (params.get('password_reset') === '1') {
                    this.passwordMode = 'reset';
                    this.authMode = 'login';
                    this.resetPasswordForm.email = params.get('email') || '';
                    this.resetPasswordForm.token = params.get('token') || '';
                    this.passwordError = this.resetPasswordForm.email && this.resetPasswordForm.token
                        ? ''
                        : 'This password reset link is incomplete. Request a new link and try again.';
                }
            },

            handlePopState: async function () {
                var currentPath = window.location.pathname.replace(/\/$/, '');
                var themesPath = this.pathFromUrl(this.config.urls.themes);
                var topicsPath = this.pathFromUrl(this.config.urls.current);
                var accountPath = this.pathFromUrl(this.config.urls.join);

                if (currentPath === topicsPath) {
                    this.view = 'topics';
                } else if (currentPath === accountPath) {
                    this.view = 'account';
                } else if (currentPath === themesPath) {
                    this.view = 'themes';
                }

                this.selectedTopic = null;
                this.requestedTopicSlug = '';
                this.readLocationState();

                if (this.view === 'topics') {
                    await this.loadTopics(1);
                    if (this.requestedTopicSlug) {
                        await this.loadTopic(this.requestedTopicSlug);
                    }
                }
            },

            pathFromUrl: function (url) {
                if (!url) {
                    return '';
                }
                return new URL(url, window.location.origin).pathname.replace(/\/$/, '');
            },

            updateForumUrl: function (replace) {
                var target;
                if (this.view === 'topics') {
                    target = new URL(this.config.urls.current, window.location.origin);
                    if (this.activeTheme) {
                        target.searchParams.set('theme', this.activeTheme);
                    }
                    if (this.filters.search) {
                        target.searchParams.set('search', this.filters.search);
                    }
                    if (this.filters.status !== 'open') {
                        target.searchParams.set('status', this.filters.status);
                    }
                    if (this.selectedTopic && this.selectedTopic.slug) {
                        target.searchParams.set('topic', this.selectedTopic.slug);
                    }
                } else if (this.view === 'account') {
                    target = new URL(this.config.urls.join, window.location.origin);
                } else {
                    target = new URL(this.config.urls.themes, window.location.origin);
                }

                var relativeUrl = target.pathname + target.search + target.hash;
                if (replace) {
                    window.history.replaceState({ discussionForum: true }, '', relativeUrl);
                } else {
                    window.history.pushState({ discussionForum: true }, '', relativeUrl);
                }
            },

            switchView: async function (view, options) {
                options = options || {};
                this.view = this.normaliseView(view);

                if (this.view !== 'topics') {
                    this.selectedTopic = null;
                    this.replyingTo = null;
                }

                if (!options.skipHistory) {
                    this.updateForumUrl(false);
                }

                if (this.view === 'topics' && this.topics.length === 0 && !this.loading.topics) {
                    await this.loadTopics(1);
                }

                if (this.view === 'account' && this.authMode === 'register' && !this.participant) {
                    this.queueCountrySelector();
                }

                this.scrollToForum();
            },

            scrollToForum: function () {
                this.$nextTick(function () {
                    var content = document.getElementById('discussion-content');
                    if (content) {
                        content.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    }
                });
            },

            loadOverview: async function (silent) {
                if (!silent) {
                    this.loading.overview = true;
                }

                try {
                    var payload = await this.api('/overview');
                    this.overview = payload.data || {};
                    this.markRefreshed(payload.refreshed_at);
                    return payload;
                } finally {
                    this.loading.overview = false;
                }
            },

            loadThemes: async function (silent) {
                if (!silent) {
                    this.loading.themes = true;
                }

                try {
                    var payload = await this.api('/themes');
                    this.themes = Array.isArray(payload.data) ? payload.data : [];
                    return payload;
                } finally {
                    this.loading.themes = false;
                }
            },

            loadCountries: async function (silent) {
                if (!silent) {
                    this.loading.countries = true;
                }

                try {
                    var payload = await this.api('/countries');
                    this.countries = Array.isArray(payload.data) ? payload.data : [];
                    this.countryMeta = Object.assign({
                        represented_countries: 0,
                        participants_with_country: 0
                    }, payload.meta || {});

                    if (this.representedCountries.length) {
                        this.countryToastIndex %= this.representedCountries.length;
                    } else {
                        this.countryToastIndex = 0;
                    }

                    return payload;
                } finally {
                    this.loading.countries = false;
                    this.queueCountrySelector();
                }
            },

            queueCountrySelector: function () {
                var self = this;
                this.$nextTick(function () {
                    self.initialiseCountrySelector();
                });
            },

            initialiseCountrySelector: function () {
                var element = this.$refs.countrySelect;
                var jquery = window.jQuery;

                if (!element || !jquery || !jquery.fn || typeof jquery.fn.select2 !== 'function') {
                    return;
                }

                var self = this;
                var select = jquery(element);
                select.prop('disabled', Boolean(this.loading.countries));

                if (!select.data('select2')) {
                    select.select2({
                        width: '100%',
                        minimumResultsForSearch: 8,
                        placeholder: 'Select your country',
                        dropdownCssClass: 'forum-country-select-dropdown',
                        selectionCssClass: 'forum-country-select-selection',
                        templateResult: function (option) {
                            return self.renderCountryOption(option, false);
                        },
                        templateSelection: function (option) {
                            return self.renderCountryOption(option, true);
                        }
                    });
                }

                select
                    .off('change.forumCountry')
                    .val(this.registerForm.country || '')
                    .trigger('change.select2')
                    .on('change.forumCountry', function () {
                        self.registerForm.country = String(jquery(this).val() || '');
                        self.authError = '';
                        self.authValidation = {};
                    });
            },

            renderCountryOption: function (option, selected) {
                if (!option || !option.id || !option.element) {
                    return option ? option.text : '';
                }

                var source = option.element;
                var flagUrl = source.getAttribute('data-flag-url') || '';
                var iso2 = (source.getAttribute('data-iso2') || '').toUpperCase();
                var row = document.createElement('span');
                row.className = 'forum-country-option' + (selected ? ' forum-country-option--selected' : '');

                var visual = document.createElement('span');
                visual.className = 'forum-country-option__visual';
                visual.setAttribute('aria-hidden', 'true');

                var fallback = document.createElement('span');
                fallback.className = 'forum-country-option__fallback';
                fallback.textContent = iso2 || 'AF';
                visual.appendChild(fallback);

                if (flagUrl) {
                    var flag = document.createElement('img');
                    flag.className = 'forum-country-option__flag';
                    flag.alt = '';
                    flag.loading = 'lazy';
                    flag.decoding = 'async';
                    flag.addEventListener('error', function () {
                        flag.remove();
                    }, { once: true });
                    flag.src = flagUrl;
                    visual.appendChild(flag);
                }

                var label = document.createElement('span');
                label.className = 'forum-country-option__label';
                label.textContent = option.text || '';

                row.appendChild(visual);
                row.appendChild(label);

                if (!selected && iso2) {
                    var code = document.createElement('span');
                    code.className = 'forum-country-option__code';
                    code.textContent = iso2;
                    row.appendChild(code);
                }

                return row;
            },

            destroyCountrySelector: function () {
                var element = this.$refs.countrySelect;
                var jquery = window.jQuery;

                if (!element || !jquery || !jquery.fn) {
                    return;
                }

                var select = jquery(element);
                select.off('change.forumCountry');
                if (select.data('select2') && typeof select.select2 === 'function') {
                    select.select2('destroy');
                }
            },

            loadTopics: async function (page, silent) {
                page = Math.max(Number(page) || 1, 1);
                if (!silent) {
                    this.loading.topics = true;
                }

                var params = new URLSearchParams();
                params.set('page', String(page));
                params.set('status', this.filters.status || 'open');
                if (this.activeTheme) {
                    params.set('theme', this.activeTheme);
                }
                if (this.filters.search) {
                    params.set('search', this.filters.search);
                }

                try {
                    var payload = await this.api('/topics?' + params.toString());
                    this.topics = Array.isArray(payload.data) ? payload.data : [];
                    this.topicMeta = Object.assign({
                        current_page: page,
                        last_page: 1,
                        per_page: 12,
                        total: this.topics.length
                    }, payload.meta || {});
                    this.markRefreshed(payload.refreshed_at);
                    return payload;
                } catch (error) {
                    if (!silent) {
                        this.showNotice(error.message || 'Discussions could not be loaded.', 'error');
                    }
                    throw error;
                } finally {
                    this.loading.topics = false;
                }
            },

            loadTopic: async function (topicOrSlug, silent) {
                var slug = typeof topicOrSlug === 'string' ? topicOrSlug : topicOrSlug && topicOrSlug.slug;
                if (!slug) {
                    return;
                }

                if (!silent) {
                    this.loading.topic = true;
                }

                try {
                    var payload = await this.api('/topics/' + encodeURIComponent(slug), {
                        auth: Boolean(this.getToken()),
                        optionalAuth: true
                    });
                    this.selectedTopic = payload.data || null;
                    this.topicActivityRevision = this.selectedTopic
                        ? String(this.selectedTopic.activity_revision || '')
                        : '';
                    var joiners = this.selectedTopic
                        && this.selectedTopic.participation
                        && Array.isArray(this.selectedTopic.participation.recent_joiners)
                        ? this.selectedTopic.participation.recent_joiners
                        : [];
                    this.topicJoinerIndex = joiners.length ? this.topicJoinerIndex % joiners.length : 0;
                    this.markRefreshed(payload.refreshed_at);
                    if (this.participant) {
                        this.joinTopicPresence(slug);
                    }
                    if (!silent) {
                        this.$nextTick(this.focusDeepLinkedPost);
                    }
                    return payload;
                } catch (error) {
                    if (!silent) {
                        this.showNotice(error.status === 404 ? 'This discussion is no longer available.' : error.message, 'error');
                        this.selectedTopic = null;
                        this.updateForumUrl(true);
                    }
                    throw error;
                } finally {
                    this.loading.topic = false;
                }
            },

            joinTopicPresence: async function (slug) {
                slug = String(slug || '');
                if (!this.participant || !slug || this.joiningTopicSlug === slug) {
                    return;
                }

                this.joiningTopicSlug = slug;
                try {
                    var payload = await this.api('/topics/' + encodeURIComponent(slug) + '/presence', {
                        method: 'POST',
                        auth: true,
                        quietAuthFailure: true
                    });

                    if (this.selectedTopic && this.selectedTopic.slug === slug && payload.data) {
                        this.selectedTopic.participation = payload.data;
                        var joiners = Array.isArray(payload.data.recent_joiners) ? payload.data.recent_joiners : [];
                        this.topicJoinerIndex = joiners.length ? this.topicJoinerIndex % joiners.length : 0;
                    }
                } catch (error) {
                    // Topic content remains readable if the optional live-presence
                    // update is temporarily unavailable.
                } finally {
                    if (this.joiningTopicSlug === slug) {
                        this.joiningTopicSlug = '';
                    }
                }
            },

            loadTopicActivity: async function () {
                if (document.hidden || this.topicActivityBusy || !this.selectedTopic || this.view !== 'topics') {
                    return;
                }

                var slug = this.selectedTopic.slug;
                this.topicActivityBusy = true;
                try {
                    var payload = await this.api('/topics/' + encodeURIComponent(slug) + '/activity', {
                        auth: Boolean(this.getToken()),
                        optionalAuth: true
                    });

                    if (!this.selectedTopic || this.selectedTopic.slug !== slug || !payload.data) {
                        return;
                    }

                    this.selectedTopic.participation = payload.data.participation || this.selectedTopic.participation;
                    var nextRevision = String(payload.data.activity_revision || '');
                    var changed = Boolean(this.topicActivityRevision)
                        && nextRevision !== this.topicActivityRevision;
                    this.topicActivityRevision = nextRevision;

                    if (changed) {
                        await this.loadTopic(slug, true);
                    }
                } catch (error) {
                    // The regular forum poll remains as a slower fallback.
                } finally {
                    this.topicActivityBusy = false;
                }
            },

            openTheme: async function (theme) {
                this.activeTheme = theme.slug || theme.id;
                this.filters.status = 'open';
                this.filters.search = '';
                this.selectedTopic = null;
                this.view = 'topics';
                this.updateForumUrl(false);
                await this.loadTopics(1);
                this.scrollToForum();
            },

            themeFilterChanged: async function () {
                this.selectedTopic = null;
                this.updateForumUrl(true);
                await this.loadTopics(1);
            },

            statusFilterChanged: async function () {
                this.selectedTopic = null;
                this.updateForumUrl(true);
                await this.loadTopics(1);
            },

            scheduleTopicSearch: function () {
                var self = this;
                window.clearTimeout(this.searchTimer);
                this.searchTimer = window.setTimeout(async function () {
                    self.selectedTopic = null;
                    self.updateForumUrl(true);
                    try {
                        await self.loadTopics(1);
                    } catch (error) {
                        // loadTopics already reports the public-facing error.
                    }
                }, 350);
            },

            clearFilters: async function () {
                this.activeTheme = '';
                this.filters.search = '';
                this.filters.status = 'open';
                this.selectedTopic = null;
                this.updateForumUrl(true);
                await this.loadTopics(1);
            },

            openTopic: async function (topic) {
                this.view = 'topics';
                this.selectedTopic = topic;
                this.replyingTo = null;
                this.postForm.body = '';
                this.emojiPickerOpen = false;
                this.mobileResourcesOpen = false;
                this.updateForumUrl(false);
                this.scrollToForum();
                try {
                    await this.loadTopic(topic);
                } catch (error) {
                    // loadTopic already reports the public-facing error.
                }
            },

            closeTopic: function () {
                this.selectedTopic = null;
                this.topicActivityRevision = '';
                this.topicJoinerIndex = 0;
                this.replyingTo = null;
                this.postForm.body = '';
                this.postError = '';
                this.emojiPickerOpen = false;
                this.highlightedPostId = '';
                this.updateForumUrl(false);
                this.scrollToForum();
            },

            beginParticipation: function () {
                if (!this.participant) {
                    this.authMode = 'register';
                    this.switchView('account');
                    return;
                }

                var firstTopic = document.querySelector('.forum-topic-card__title');
                if (firstTopic) {
                    firstTopic.focus({ preventScroll: true });
                    firstTopic.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            },

            openAccountForTopic: function () {
                this.returnToTopic = this.selectedTopic ? this.selectedTopic.slug : '';
                this.authMode = 'register';
                this.switchView('account');
            },

            continueAfterAccount: async function () {
                var slug = this.returnToTopic;
                this.returnToTopic = '';
                this.view = 'topics';
                this.updateForumUrl(false);
                if (slug) {
                    try {
                        await this.loadTopic(slug);
                        this.updateForumUrl(true);
                    } catch (error) {
                        await this.loadTopics(1);
                    }
                } else {
                    await this.loadTopics(1);
                }
                this.scrollToForum();
            },

            setAuthMode: function (mode) {
                this.destroyCountrySelector();
                this.passwordMode = '';
                this.passwordError = '';
                this.passwordMessage = '';
                this.authMode = mode === 'login' ? 'login' : 'register';
                this.authError = '';
                this.authValidation = {};
                if (this.authMode === 'register') {
                    this.queueCountrySelector();
                }
            },

            openPasswordRecovery: function () {
                this.destroyCountrySelector();
                this.passwordMode = 'forgot';
                this.passwordError = '';
                this.passwordMessage = '';
                this.forgotPasswordForm.email = (this.participant && this.participant.email)
                    || this.loginForm.email
                    || '';
            },

            cancelPasswordRecovery: function () {
                this.passwordMode = '';
                this.passwordError = '';
                this.passwordMessage = '';
                this.authMode = 'login';
                this.clearPasswordResetParameters();
            },

            requestPasswordReset: async function () {
                this.passwordError = '';
                this.passwordMessage = '';
                if (!this.isValidEmail(this.forgotPasswordForm.email)) {
                    this.passwordError = 'Enter a valid participant email address.';
                    return;
                }

                this.passwordBusy = true;
                try {
                    var payload = await this.api('/participants/password/forgot', {
                        method: 'POST',
                        body: { email: this.forgotPasswordForm.email }
                    });
                    this.passwordMessage = payload.message || 'If the email can be used, a password reset link will be sent shortly.';
                } catch (error) {
                    this.passwordError = this.firstValidationMessage(error.details)
                        || error.message
                        || 'The password reset request could not be completed.';
                } finally {
                    this.passwordBusy = false;
                }
            },

            resetParticipantPassword: async function () {
                this.passwordError = '';
                if (!this.resetPasswordForm.email || !this.resetPasswordForm.token) {
                    this.passwordError = 'This password reset link is incomplete. Request a new link and try again.';
                    return;
                }

                if (this.resetPasswordForm.password.length < 8
                    || !/[A-Za-z]/.test(this.resetPasswordForm.password)
                    || !/\d/.test(this.resetPasswordForm.password)) {
                    this.passwordError = 'Use a password of at least 8 characters containing letters and numbers.';
                    return;
                }

                if (this.resetPasswordForm.password !== this.resetPasswordForm.password_confirmation) {
                    this.passwordError = 'The password confirmation does not match.';
                    return;
                }

                this.passwordBusy = true;
                try {
                    var payload = await this.api('/participants/password/reset', {
                        method: 'POST',
                        body: this.resetPasswordForm
                    });
                    this.clearSession();
                    this.loginForm.email = this.resetPasswordForm.email;
                    this.loginForm.password = '';
                    this.resetPasswordForm.token = '';
                    this.resetPasswordForm.password = '';
                    this.resetPasswordForm.password_confirmation = '';
                    this.passwordMode = '';
                    this.authMode = 'login';
                    this.clearPasswordResetParameters();
                    this.showNotice(payload.message || 'Your password has been reset. Sign in with your new password.', 'success');
                } catch (error) {
                    this.passwordError = this.firstValidationMessage(error.details)
                        || error.message
                        || 'The password reset link is invalid or has expired.';
                } finally {
                    this.passwordBusy = false;
                }
            },

            clearPasswordResetParameters: function () {
                var url = new URL(window.location.href);
                ['password_reset', 'email', 'token'].forEach(function (parameter) {
                    url.searchParams.delete(parameter);
                });
                window.history.replaceState({ discussionForum: true }, '', url.pathname + url.search + url.hash);
            },

            nextRegistrationStep: function () {
                this.authError = '';
                this.authValidation = {};

                if (this.registerForm.display_name.trim().length < 2) {
                    this.authError = 'Enter a display name with at least two characters.';
                    return;
                }

                if (!this.isValidEmail(this.registerForm.email)) {
                    this.authError = 'Enter a valid email address.';
                    return;
                }

                if (!this.registerForm.country) {
                    this.authError = 'Select your country.';
                    return;
                }

                this.registerStep = 2;
            },

            register: async function () {
                this.authError = '';
                this.authValidation = {};

                if (this.registerStep !== 2) {
                    this.nextRegistrationStep();
                    return;
                }

                if (this.registerForm.password.length < 8 || !/[A-Za-z]/.test(this.registerForm.password) || !/\d/.test(this.registerForm.password)) {
                    this.authError = 'Use a password of at least 8 characters containing letters and numbers.';
                    return;
                }

                if (this.registerForm.password !== this.registerForm.password_confirmation) {
                    this.authError = 'The password confirmation does not match.';
                    return;
                }

                if (!this.registerForm.terms) {
                    this.authError = 'Accept the forum community standards to create your account.';
                    return;
                }

                this.authBusy = true;
                try {
                    var payload = await this.api('/participants/register', {
                        method: 'POST',
                        body: {
                            display_name: this.registerForm.display_name,
                            email: this.registerForm.email,
                            country: this.registerForm.country || null,
                            organization: this.registerForm.organization || null,
                            password: this.registerForm.password,
                            password_confirmation: this.registerForm.password_confirmation,
                            terms: this.registerForm.terms
                        }
                    });
                    this.completeAuthentication(payload);
                    // Keep the country participation count fresh without making
                    // entry into the discussion wait for a secondary API request.
                    this.loadCountries(true).catch(function () {
                        // The scheduled forum refresh will retry this quietly.
                    });
                    this.showNotice(payload.message || 'Your participant account is ready.', 'success');
                    await this.afterAuthentication();
                } catch (error) {
                    this.captureAuthError(error);
                } finally {
                    this.authBusy = false;
                }
            },

            login: async function () {
                this.authError = '';
                this.authValidation = {};

                if (!this.isValidEmail(this.loginForm.email) || !this.loginForm.password) {
                    this.authError = 'Enter your email address and password.';
                    return;
                }

                this.authBusy = true;
                try {
                    var payload = await this.api('/participants/login', {
                        method: 'POST',
                        body: this.loginForm
                    });
                    this.completeAuthentication(payload);
                    this.loginForm.password = '';
                    this.showNotice(payload.message || 'Welcome back to the discussion forum.', 'success');
                    await this.afterAuthentication();
                } catch (error) {
                    this.captureAuthError(error);
                } finally {
                    this.authBusy = false;
                }
            },

            afterAuthentication: async function () {
                if (this.selectedTopic && this.view === 'topics') {
                    await this.loadTopic(this.selectedTopic.slug, true);
                    return;
                }

                // Account creation and sign-in are entry points to participation,
                // so always leave the registration screen for the requested topic
                // or the active-discussion list once authentication succeeds.
                await this.continueAfterAccount();
            },

            completeAuthentication: function (payload) {
                this.destroyCountrySelector();
                this.participant = payload.participant || null;
                this.blockedMessage = '';
                if (payload.remembered_device === true) {
                    // The protected cookie is authoritative for browser sessions;
                    // do not leave a new long-lived credential readable by scripts.
                    this.removeStoredToken();
                } else if (payload.token) {
                    this.setToken(payload.token);
                }
                this.authError = '';
                this.authValidation = {};
            },

            restoreSession: async function () {
                try {
                    // The API also accepts a first-party HttpOnly remembered-device
                    // cookie, so restoration must run even when localStorage is
                    // empty or unavailable in the browser.
                    var payload = await this.api('/participants/me', {
                        auth: true,
                        quietAuthFailure: true
                    });
                    this.destroyCountrySelector();
                    this.participant = payload.participant || null;
                    if (payload.session && payload.session.remembered_device === true) {
                        // Successful Bearer restoration seeds an HttpOnly cookie on
                        // the response, allowing safe removal of legacy storage.
                        this.removeStoredToken();
                    }
                } catch (error) {
                    if (error.status !== 401 && error.status !== 403) {
                        this.showNotice('Your participant session could not be restored.', 'error');
                    }
                }
            },

            logout: async function () {
                this.authBusy = true;
                try {
                    // Always call logout: the remembered device credential is
                    // HttpOnly and therefore is deliberately invisible to Vue.
                    await this.api('/participants/logout', {
                        method: 'POST',
                        auth: true,
                        quietAuthFailure: true
                    });
                } catch (error) {
                    // The local session still needs to be cleared if the token expired.
                } finally {
                    this.clearSession();
                    this.authBusy = false;
                    this.authMode = 'login';
                    this.showNotice('You have been signed out.', 'success');
                }
            },

            captureAuthError: function (error) {
                this.authError = error.message || 'The participant account request could not be completed.';
                this.authValidation = error.details || {};
                if (error.status === 403) {
                    this.blockedMessage = error.reason ? this.authError + ' Reason: ' + error.reason : this.authError;
                }
            },

            submitPost: async function () {
                if (!this.participant || !this.selectedTopic) {
                    this.openAccountForTopic();
                    return;
                }

                var body = this.postForm.body.trim();
                if (body.length < 2) {
                    this.postError = 'Write at least two characters before sending your contribution.';
                    return;
                }

                this.posting = true;
                this.postError = '';
                try {
                    var payload = await this.api('/topics/' + encodeURIComponent(this.selectedTopic.slug) + '/posts', {
                        method: 'POST',
                        auth: true,
                        body: {
                            body: body,
                            parent_id: this.replyingTo ? this.replyingTo.id : null
                        }
                    });
                    this.postForm.body = '';
                    this.replyingTo = null;
                    this.emojiPickerOpen = false;
                    this.showNotice(payload.message || 'Your contribution was received.', 'success');
                    await this.loadTopic(this.selectedTopic.slug, true);
                } catch (error) {
                    this.postError = this.firstValidationMessage(error.details) || error.message || 'Your contribution could not be sent.';
                    if (error.status === 401 || error.status === 403) {
                        this.returnToTopic = this.selectedTopic.slug;
                    }
                } finally {
                    this.posting = false;
                }
            },

            replyTo: function (post) {
                this.replyingTo = post;
                this.postError = '';
                this.emojiPickerOpen = false;
                this.$nextTick(function () {
                    if (this.$refs.postBody) {
                        this.$refs.postBody.focus();
                        this.$refs.postBody.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                });
            },

            cancelReply: function () {
                this.replyingTo = null;
                this.emojiPickerOpen = false;
            },

            toggleEmojiPicker: function () {
                this.emojiPickerOpen = !this.emojiPickerOpen;
                if (this.emojiPickerOpen) {
                    this.$nextTick(function () {
                        var firstEmoji = document.querySelector('#forum-emoji-picker .forum-emoji-picker__grid button');
                        if (firstEmoji) {
                            firstEmoji.focus();
                        }
                    });
                }
            },

            closeEmojiPicker: function () {
                this.emojiPickerOpen = false;
                this.$nextTick(function () {
                    if (this.$refs.postBody) {
                        this.$refs.postBody.focus();
                    }
                });
            },

            insertEmoji: function (emoji) {
                var textarea = this.$refs.postBody;
                var body = this.postForm.body || '';
                var start = textarea && Number.isInteger(textarea.selectionStart) ? textarea.selectionStart : body.length;
                var end = textarea && Number.isInteger(textarea.selectionEnd) ? textarea.selectionEnd : start;
                this.postForm.body = body.slice(0, start) + emoji + body.slice(end);
                this.emojiPickerOpen = false;
                this.$nextTick(function () {
                    if (this.$refs.postBody) {
                        var cursor = start + emoji.length;
                        this.$refs.postBody.focus();
                        this.$refs.postBody.setSelectionRange(cursor, cursor);
                    }
                });
            },

            reactionCount: function (post, type) {
                if (post && post.reactions && typeof post.reactions === 'object') {
                    return Number(post.reactions[type]) || 0;
                }
                return type === 'like' ? Number(post && post.reactions_count) || 0 : 0;
            },

            totalReactionCount: function (post) {
                var self = this;
                return this.reactionOptions.reduce(function (total, reaction) {
                    return total + self.reactionCount(post, reaction.type);
                }, 0);
            },

            hasViewerReaction: function (post, type) {
                if (!post) {
                    return false;
                }
                if (Array.isArray(post.viewer_reactions)) {
                    return post.viewer_reactions.indexOf(type) >= 0;
                }
                if (post.viewer_reactions && typeof post.viewer_reactions === 'object') {
                    return Boolean(post.viewer_reactions[type]);
                }
                return type === 'like' && Boolean(post.viewer_reacted);
            },

            isReactionBusy: function (post, type) {
                return this.reactingPost === post.id + ':' + type;
            },

            toggleReaction: async function (post, type) {
                if (!this.participant || this.reactingPost) {
                    return;
                }

                this.reactingPost = post.id + ':' + type;
                try {
                    var payload = await this.api('/posts/' + encodeURIComponent(post.id) + '/reaction', {
                        method: 'POST',
                        auth: true,
                        body: {
                            type: type
                        }
                    });
                    if (payload.reactions && typeof payload.reactions === 'object') {
                        post.reactions = Object.assign({}, payload.reactions);
                    } else {
                        var nextReactions = Object.assign({}, post.reactions || {});
                        nextReactions[type] = Number(payload.reaction_count !== undefined ? payload.reaction_count : payload.reactions_count) || 0;
                        post.reactions = nextReactions;
                    }

                    if (payload.viewer_reactions !== undefined) {
                        post.viewer_reactions = payload.viewer_reactions;
                    } else {
                        var viewerReactions = Array.isArray(post.viewer_reactions) ? post.viewer_reactions.slice() : [];
                        var existingIndex = viewerReactions.indexOf(type);
                        if (payload.reacted && existingIndex < 0) {
                            viewerReactions.push(type);
                        } else if (!payload.reacted && existingIndex >= 0) {
                            viewerReactions.splice(existingIndex, 1);
                        }
                        post.viewer_reactions = viewerReactions;
                    }

                    post.reactions_count = payload.reactions_count !== undefined
                        ? Number(payload.reactions_count) || 0
                        : this.totalReactionCount(post);
                    post.viewer_reacted = this.hasViewerReaction(post, 'like');
                } catch (error) {
                    this.showNotice(error.message || 'Your reaction could not be saved.', 'error');
                } finally {
                    this.reactingPost = '';
                }
            },

            topicPermalink: function () {
                var url = new URL(this.config.urls.current, window.location.origin);
                if (this.selectedTopic && this.selectedTopic.slug) {
                    url.searchParams.set('topic', this.selectedTopic.slug);
                }
                return url.toString();
            },

            postPermalink: function (post) {
                var url = new URL(this.topicPermalink());
                url.hash = 'post-' + post.id;
                return url.toString();
            },

            shareTopic: async function () {
                if (!this.selectedTopic || this.shareBusyTopic) {
                    return;
                }
                this.shareBusyTopic = true;
                try {
                    await this.shareContent({
                        title: this.selectedTopic.title,
                        text: this.selectedTopic.summary || 'Join this ATTP public discussion.',
                        url: this.topicPermalink(),
                        copiedMessage: 'Discussion link copied.'
                    });
                } finally {
                    this.shareBusyTopic = false;
                }
            },

            sharePost: async function (post) {
                if (!post || this.shareBusyPost) {
                    return;
                }
                this.shareBusyPost = post.id;
                try {
                    await this.shareContent({
                        title: this.selectedTopic ? this.selectedTopic.title : 'ATTP public discussion',
                        text: 'Contribution by ' + post.author.display_name + ': ' + this.truncateText(post.body, 140),
                        url: this.postPermalink(post),
                        copiedMessage: 'Contribution link copied.'
                    });
                } finally {
                    this.shareBusyPost = '';
                }
            },

            shareContent: async function (content) {
                if (navigator.share) {
                    try {
                        await navigator.share({
                            title: content.title,
                            text: content.text,
                            url: content.url
                        });
                        return;
                    } catch (error) {
                        if (error && error.name === 'AbortError') {
                            return;
                        }
                    }
                }

                try {
                    await this.copyText(content.url);
                    this.showNotice(content.copiedMessage || 'Link copied.', 'success');
                } catch (error) {
                    this.showNotice('Copy this link: ' + content.url, 'error');
                }
            },

            copyText: async function (text) {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(text);
                    return;
                }

                var helper = document.createElement('textarea');
                helper.value = text;
                helper.setAttribute('readonly', '');
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                var copied = document.execCommand('copy');
                helper.remove();
                if (!copied) {
                    throw new Error('Copy unavailable');
                }
            },

            focusDeepLinkedPost: function () {
                var match = String(window.location.hash || '').match(/^#post-([A-Za-z0-9-]+)$/);
                if (!match) {
                    return;
                }

                var postId = match[1];
                var element = document.getElementById('post-' + postId);
                if (!element) {
                    return;
                }

                this.highlightedPostId = postId;
                element.focus({ preventScroll: true });
                element.scrollIntoView({ behavior: 'smooth', block: 'center' });
                window.clearTimeout(this.highlightTimer);
                var self = this;
                this.highlightTimer = window.setTimeout(function () {
                    self.highlightedPostId = '';
                }, 9000);
            },

            normaliseResourceList: function (resources) {
                if (!Array.isArray(resources)) {
                    return [];
                }
                return resources.filter(function (resource) {
                    return typeof resource === 'string' || (resource && typeof resource === 'object');
                });
            },

            rawResourceUrl: function (resource) {
                if (typeof resource === 'string') {
                    return /^https?:\/\//i.test(resource) || /^\//.test(resource) ? resource : '';
                }
                return resource && (resource.url || resource.href || resource.download_url || resource.file_url) || '';
            },

            hasResourceUrl: function (resource) {
                return this.resourceUrl(resource) !== '#';
            },

            resourceUrl: function (resource) {
                var raw = String(this.rawResourceUrl(resource) || '').trim();
                if (!raw) {
                    return '#';
                }
                try {
                    var url = new URL(raw, window.location.origin);
                    return ['http:', 'https:'].indexOf(url.protocol) >= 0 ? url.href : '#';
                } catch (error) {
                    return '#';
                }
            },

            isUploadedDocument: function (resource) {
                return Boolean(resource && typeof resource === 'object'
                    && (resource.source === 'upload' || resource.download_url));
            },

            documentReadUrl: function (resource) {
                var raw = resource && typeof resource === 'object'
                    ? String(resource.view_url || '')
                    : '';

                if (!raw && !this.isUploadedDocument(resource)) {
                    return this.resourceUrl(resource);
                }

                if (!raw) {
                    return '#';
                }

                try {
                    var url = new URL(raw, window.location.origin);
                    return ['http:', 'https:'].indexOf(url.protocol) >= 0 ? url.href : '#';
                } catch (error) {
                    return '#';
                }
            },

            resourceTitle: function (resource, fallback) {
                if (typeof resource === 'string') {
                    if (!/^https?:\/\//i.test(resource)) {
                        return resource;
                    }
                    try {
                        return new URL(resource).hostname.replace(/^www\./, '');
                    } catch (error) {
                        return fallback;
                    }
                }
                return resource && (resource.title || resource.label || resource.name || resource.filename) || fallback;
            },

            resourceDescription: function (resource) {
                return resource && typeof resource === 'object'
                    ? String(resource.description || resource.summary || '')
                    : '';
            },

            resourceType: function (resource) {
                return resource && typeof resource === 'object'
                    ? String(resource.type || resource.kind || 'Resource')
                    : 'Resource';
            },

            documentMeta: function (documentResource) {
                if (!documentResource || typeof documentResource !== 'object') {
                    return 'Document';
                }
                var parts = [documentResource.type || documentResource.file_type || documentResource.extension];
                if (documentResource.size || documentResource.file_size) {
                    parts.push(documentResource.size || documentResource.file_size);
                }
                return parts.filter(Boolean).join(' · ') || 'Document';
            },

            truncateText: function (text, length) {
                var clean = String(text || '').replace(/\s+/g, ' ').trim();
                return clean.length > length ? clean.slice(0, length - 1) + '…' : clean;
            },

            parentAuthor: function (parentId) {
                if (!this.selectedTopic || !Array.isArray(this.selectedTopic.posts)) {
                    return 'an earlier contribution';
                }
                var parent = this.selectedTopic.posts.find(function (post) {
                    return post.id === parentId;
                });
                return parent && parent.author ? parent.author.display_name : 'an earlier contribution';
            },

            rotateCountryToast: function () {
                var reducedMotion = window.matchMedia
                    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

                if (document.hidden || reducedMotion) {
                    return;
                }

                if (!this.countryToastPaused) {
                    this.nextCountryToast();
                }

                if (!this.topicToastPaused) {
                    this.nextTopicJoiner();
                }
            },

            nextTopicJoiner: function () {
                if (this.topicRecentJoiners.length < 2) {
                    return;
                }

                this.topicJoinerIndex = (this.topicJoinerIndex + 1) % this.topicRecentJoiners.length;
            },

            nextCountryToast: function () {
                if (this.representedCountries.length < 2) {
                    return;
                }

                this.countryToastIndex = (this.countryToastIndex + 1) % this.representedCountries.length;
            },

            previousCountryToast: function () {
                if (this.representedCountries.length < 2) {
                    return;
                }

                this.countryToastIndex = (this.countryToastIndex - 1 + this.representedCountries.length)
                    % this.representedCountries.length;
            },

            refreshCurrentView: async function () {
                if (this.refreshing) {
                    return;
                }

                this.refreshing = true;
                var tasks = [this.loadOverview(true), this.loadThemes(true), this.loadCountries(true)];
                if (this.view === 'topics') {
                    if (this.selectedTopic) {
                        tasks.push(this.loadTopic(this.selectedTopic.slug, true));
                    } else {
                        tasks.push(this.loadTopics(this.topicMeta.current_page, true));
                    }
                }

                var results = await Promise.allSettled(tasks);
                this.refreshing = false;
                if (results.some(function (result) { return result.status === 'fulfilled'; })) {
                    this.showNotice('Forum information refreshed.', 'success');
                } else {
                    this.showNotice('The forum could not be refreshed.', 'error');
                }
            },

            pollForum: async function () {
                if (document.hidden || this.refreshing) {
                    return;
                }

                var tasks = [this.loadOverview(true)];
                if (this.view === 'themes') {
                    tasks.push(this.loadThemes(true));
                } else if (this.view === 'topics') {
                    if (this.selectedTopic) {
                        tasks.push(this.loadTopic(this.selectedTopic.slug, true));
                    } else {
                        tasks.push(this.loadTopics(this.topicMeta.current_page, true));
                    }
                } else if (this.view === 'account') {
                    tasks.push(this.loadCountries(true));
                }
                await Promise.allSettled(tasks);
            },

            api: async function (path, options) {
                options = options || {};
                var base = String(this.config.apiBase || '').replace(/\/$/, '');
                var headers = {
                    Accept: 'application/json'
                };

                if (options.body !== undefined) {
                    headers['Content-Type'] = 'application/json';
                }

                if (options.auth) {
                    var token = this.getToken();
                    if (token) {
                        headers.Authorization = 'Bearer ' + token;
                    }
                }

                var response;
                try {
                    response = await window.fetch(base + path, {
                        method: options.method || 'GET',
                        headers: headers,
                        body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
                        credentials: 'same-origin'
                    });
                } catch (networkError) {
                    var connectionError = new Error('Unable to reach the discussion service. Check your connection and try again.');
                    connectionError.status = 0;
                    throw connectionError;
                }

                var payload = {};
                try {
                    payload = await response.json();
                } catch (jsonError) {
                    payload = {};
                }

                if (!response.ok) {
                    var message = payload.message || 'The discussion request could not be completed.';
                    var error = new Error(message);
                    error.status = response.status;
                    error.code = payload.code || '';
                    error.details = payload.errors || {};
                    error.reason = payload.reason || '';

                    if (response.status === 401 && options.auth && !options.optionalAuth) {
                        this.clearSession();
                        if (!options.quietAuthFailure) {
                            this.showNotice('Your participant session has expired. Please sign in again.', 'error');
                        }
                    }

                    if (response.status === 403 && (payload.code === 'participant_blocked' || options.auth)) {
                        this.clearSession();
                        this.blockedMessage = payload.reason ? message + ' Reason: ' + payload.reason : message;
                    }

                    throw error;
                }

                return payload;
            },

            getToken: function () {
                try {
                    return window.localStorage.getItem(TOKEN_KEY) || '';
                } catch (error) {
                    return '';
                }
            },

            setToken: function (token) {
                try {
                    window.localStorage.setItem(TOKEN_KEY, token);
                } catch (error) {
                    this.showNotice('Your browser could not save the participant session. You may need to sign in again after leaving this page.', 'error');
                }
            },

            clearSession: function () {
                this.participant = null;
                this.removeStoredToken();
            },

            removeStoredToken: function () {
                try {
                    window.localStorage.removeItem(TOKEN_KEY);
                } catch (error) {
                    // The server-side cookie remains authoritative and HttpOnly.
                }
            },

            markRefreshed: function (timestamp) {
                this.lastRefreshedAt = timestamp || new Date().toISOString();
            },

            showNotice: function (message, type) {
                var self = this;
                window.clearTimeout(this.noticeTimer);
                this.notice = {
                    type: type === 'error' ? 'error' : 'success',
                    message: message || ''
                };
                this.noticeTimer = window.setTimeout(function () {
                    self.clearNotice();
                }, 5200);
            },

            clearNotice: function () {
                window.clearTimeout(this.noticeTimer);
                this.notice.message = '';
            },

            overviewValue: function (key) {
                if (this.loading.overview && !this.overview) {
                    return '--';
                }
                return this.formatNumber(this.overview && this.overview[key] !== undefined ? this.overview[key] : 0);
            },

            formatNumber: function (value) {
                try {
                    return new Intl.NumberFormat(this.config.locale || 'en').format(Number(value) || 0);
                } catch (error) {
                    return String(Number(value) || 0);
                }
            },

            formatDate: function (value) {
                if (!value) {
                    return 'Not set';
                }
                var date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return 'Not set';
                }
                try {
                    return new Intl.DateTimeFormat(this.config.locale || 'en', {
                        day: 'numeric',
                        month: 'short',
                        year: 'numeric'
                    }).format(date);
                } catch (error) {
                    return date.toLocaleDateString();
                }
            },

            timeAgo: function (value) {
                if (!value) {
                    return 'recently';
                }
                var date = new Date(value);
                if (Number.isNaN(date.getTime())) {
                    return 'recently';
                }

                var seconds = Math.round((date.getTime() - Date.now()) / 1000);
                var absolute = Math.abs(seconds);
                var unit = 'second';
                var amount = seconds;

                if (absolute >= 31536000) {
                    unit = 'year';
                    amount = Math.round(seconds / 31536000);
                } else if (absolute >= 2592000) {
                    unit = 'month';
                    amount = Math.round(seconds / 2592000);
                } else if (absolute >= 86400) {
                    unit = 'day';
                    amount = Math.round(seconds / 86400);
                } else if (absolute >= 3600) {
                    unit = 'hour';
                    amount = Math.round(seconds / 3600);
                } else if (absolute >= 60) {
                    unit = 'minute';
                    amount = Math.round(seconds / 60);
                }

                try {
                    return new Intl.RelativeTimeFormat(this.config.locale || 'en', { numeric: 'auto' }).format(amount, unit);
                } catch (error) {
                    if (amount === 0) {
                        return 'just now';
                    }
                    return amount < 0 ? Math.abs(amount) + ' ' + unit + (Math.abs(amount) === 1 ? '' : 's') + ' ago' : 'in ' + amount + ' ' + unit + (amount === 1 ? '' : 's');
                }
            },

            initials: function (name) {
                var parts = String(name || 'Participant').trim().split(/\s+/).filter(Boolean);
                return parts.slice(0, 2).map(function (part) {
                    return part.charAt(0).toUpperCase();
                }).join('') || 'P';
            },

            safeColor: function (color) {
                return /^#[0-9a-f]{3}([0-9a-f]{3})?$/i.test(String(color || '')) ? color : '#006b3f';
            },

            themeIcon: function (icon) {
                var icons = {
                    shield: '◆',
                    'trending-up': '↗',
                    cpu: '⌘',
                    users: '◎',
                    globe: '◉',
                    leaf: '◇',
                    scale: '⚖',
                    book: '▤',
                    health: '+',
                    climate: '◌'
                };
                var value = String(icon || '').toLowerCase();
                return icons[value] || (value.length <= 2 ? value : '#');
            },

            isValidEmail: function (email) {
                return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(email || '').trim());
            },

            firstValidationMessage: function (details) {
                var keys = Object.keys(details || {});
                if (!keys.length) {
                    return '';
                }
                var first = details[keys[0]];
                return Array.isArray(first) ? first[0] : String(first || '');
            }
        }
        }).mount(root);
    } catch (error) {
        showForumStartupFailure(error);
    }
}());
