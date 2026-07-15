{{--
    Vue owns the content in this partial. Blade only provides a progressively
    enhanced shell; all forum records are loaded from the discussion API.
--}}
<div class="forum-app-shell" v-cloak>
    <div class="forum-system-alert forum-system-alert--danger" v-if="blockedMessage" role="alert">
        <span class="forum-system-alert__icon" aria-hidden="true">!</span>
        <div>
            <strong>Participation unavailable</strong>
            <p v-text="blockedMessage"></p>
        </div>
        <button type="button" class="forum-icon-button" @click="blockedMessage = ''" aria-label="Dismiss message">&times;</button>
    </div>

    <div class="forum-system-alert forum-system-alert--danger" v-if="bootError" role="alert">
        <span class="forum-system-alert__icon" aria-hidden="true">!</span>
        <div>
            <strong>We could not load the forum</strong>
            <p v-text="bootError"></p>
        </div>
        <button type="button" class="forum-button forum-button--small forum-button--outline" @click="retryBoot">Try again</button>
    </div>

    <div class="forum-toast" :class="'forum-toast--' + notice.type" v-if="notice.message" role="status" aria-live="polite">
        <span v-text="notice.message"></span>
        <button type="button" @click="clearNotice" aria-label="Dismiss notification">&times;</button>
    </div>

    <nav class="forum-tabs" aria-label="Discussion forum sections">
        <a :href="config.urls.themes" :class="{ active: view === 'themes' }" @click.prevent="switchView('themes')">
            <span class="forum-tab-icon" aria-hidden="true">#</span>
            <span>Thematic areas</span>
        </a>
        <a :href="config.urls.current" :class="{ active: view === 'topics' }" @click.prevent="switchView('topics')">
            <span class="forum-tab-icon" aria-hidden="true">◌</span>
            <span>Active discussions</span>
        </a>
        <a :href="config.urls.join" :class="{ active: view === 'account' }" @click.prevent="switchView('account')">
            <span class="forum-tab-icon" aria-hidden="true">+</span>
            <span v-text="participant ? 'My participant account' : 'Join discussion'"></span>
        </a>
    </nav>

    <section class="forum-stat-grid" aria-label="Discussion forum statistics">
        <article class="forum-stat-card">
            <span class="forum-stat-card__icon forum-stat-card__icon--green" aria-hidden="true">#</span>
            <span class="forum-stat-card__value" v-text="overviewValue('themes')"></span>
            <span class="forum-stat-card__label">Thematic areas</span>
        </article>
        <article class="forum-stat-card">
            <span class="forum-stat-card__icon forum-stat-card__icon--gold" aria-hidden="true">◌</span>
            <span class="forum-stat-card__value" v-text="overviewValue('active_discussions')"></span>
            <span class="forum-stat-card__label">Active discussions</span>
        </article>
        <article class="forum-stat-card">
            <span class="forum-stat-card__icon forum-stat-card__icon--orange" aria-hidden="true">◎</span>
            <span class="forum-stat-card__value" v-text="overviewValue('participants')"></span>
            <span class="forum-stat-card__label">Registered participants</span>
        </article>
        <article class="forum-stat-card">
            <span class="forum-stat-card__icon forum-stat-card__icon--blue" aria-hidden="true">↗</span>
            <span class="forum-stat-card__value" v-text="overviewValue('published_contributions')"></span>
            <span class="forum-stat-card__label">Published contributions</span>
        </article>
    </section>

    <div class="forum-refresh-line" aria-live="polite">
        <span class="forum-live-dot" aria-hidden="true"></span>
        <span>Live forum data</span>
        <span aria-hidden="true">&middot;</span>
        <span v-text="lastRefreshedText"></span>
        <button type="button" class="forum-text-button" @click="refreshCurrentView" :disabled="refreshing">
            <span :class="{ 'forum-spin': refreshing }" aria-hidden="true">↻</span>
            <span>Refresh</span>
        </button>
    </div>

    {{-- Thematic areas --}}
    <section v-show="view === 'themes'" class="forum-view" aria-labelledby="forum-themes-heading">
        <header class="forum-section-header">
            <div>
                <span class="forum-kicker">Explore by policy priority</span>
                <h2 id="forum-themes-heading">Thematic areas</h2>
                <p>Find the policy and research community that matches your expertise, then enter its active conversations.</p>
            </div>
            <button type="button" class="forum-button forum-button--primary" @click="switchView('topics')">
                Browse all discussions <span aria-hidden="true">→</span>
            </button>
        </header>

        <div class="forum-loading-grid" v-if="loading.themes" aria-label="Loading thematic areas" aria-busy="true">
            <div class="forum-skeleton forum-skeleton--theme" v-for="index in 6" :key="index"></div>
        </div>

        <div class="forum-empty" v-else-if="themes.length === 0">
            <span class="forum-empty__icon" aria-hidden="true">#</span>
            <h3>Thematic areas are being prepared</h3>
            <p>Please check again soon. New policy communities will appear here as they are opened.</p>
        </div>

        <div class="forum-theme-grid" v-else>
            <article
                class="forum-theme-card"
                v-for="theme in themes"
                :key="theme.id"
                :style="{ '--theme-accent': safeColor(theme.color) }"
            >
                <div class="forum-theme-card__top">
                    <span class="forum-theme-card__icon" aria-hidden="true" v-text="themeIcon(theme.icon)"></span>
                    <span class="forum-count-pill">
                        <strong v-text="formatNumber(theme.active_discussions_count)"></strong>
                        <span v-text="theme.active_discussions_count === 1 ? ' active discussion' : ' active discussions'"></span>
                    </span>
                </div>
                <h3 v-text="theme.name"></h3>
                <p v-text="theme.description || 'Explore evidence, experience and emerging questions in this thematic area.'"></p>
                <button type="button" class="forum-card-link" @click="openTheme(theme)">
                    View conversations <span aria-hidden="true">→</span>
                </button>
            </article>
        </div>
    </section>

    {{-- Active topics and individual topic view --}}
    <section v-show="view === 'topics'" class="forum-view" aria-labelledby="forum-topics-heading">
        <header class="forum-section-header" v-if="!selectedTopic">
            <div>
                <span class="forum-kicker">Evidence-led public dialogue</span>
                <h2 id="forum-topics-heading">Active discussions</h2>
                <p>Read the latest exchanges, search by subject, or contribute your perspective as a registered participant.</p>
            </div>
            <button type="button" class="forum-button forum-button--primary" @click="beginParticipation">
                <span v-text="participant ? 'Choose a discussion' : 'Join the conversation'"></span>
                <span aria-hidden="true">→</span>
            </button>
        </header>

        <div class="forum-browser" v-if="!selectedTopic">
            <aside class="forum-filter-panel" aria-label="Filter discussions">
                <div class="forum-filter-panel__heading">
                    <h3>Browse forum</h3>
                    <button type="button" class="forum-text-button" @click="clearFilters" v-if="activeTheme || filters.search || filters.status !== 'open'">Clear</button>
                </div>

                <label class="forum-field forum-field--search">
                    <span class="forum-field__label">Search discussions</span>
                    <span class="forum-search-wrap">
                        <span aria-hidden="true">⌕</span>
                        <input
                            type="search"
                            v-model.trim="filters.search"
                            @input="scheduleTopicSearch"
                            placeholder="Keyword or question"
                            autocomplete="off"
                        >
                    </span>
                </label>

                <fieldset class="forum-filter-group">
                    <legend>Status</legend>
                    <label v-for="option in statusOptions" :key="option.value">
                        <input type="radio" name="discussion-status" :value="option.value" v-model="filters.status" @change="statusFilterChanged">
                        <span v-text="option.label"></span>
                    </label>
                </fieldset>

                <fieldset class="forum-filter-group">
                    <legend>Thematic area</legend>
                    <label>
                        <input type="radio" name="discussion-theme" value="" v-model="activeTheme" @change="themeFilterChanged">
                        <span>All thematic areas</span>
                    </label>
                    <label v-for="theme in themes" :key="theme.id">
                        <input type="radio" name="discussion-theme" :value="theme.slug" v-model="activeTheme" @change="themeFilterChanged">
                        <span v-text="theme.name"></span>
                        <small v-text="theme.active_discussions_count"></small>
                    </label>
                </fieldset>
            </aside>

            <div class="forum-topic-panel">
                <div class="forum-result-bar">
                    <p>
                        <strong v-text="formatNumber(topicMeta.total)"></strong>
                        <span v-text="topicMeta.total === 1 ? ' discussion' : ' discussions'"></span>
                        <span v-if="activeThemeName"> in </span>
                        <strong v-if="activeThemeName" v-text="activeThemeName"></strong>
                    </p>
                    <span class="forum-result-bar__hint">Featured and recently active first</span>
                </div>

                <div class="forum-topic-list" v-if="loading.topics" aria-label="Loading discussions" aria-busy="true">
                    <div class="forum-skeleton forum-skeleton--topic" v-for="index in 5" :key="index"></div>
                </div>

                <div class="forum-empty" v-else-if="topics.length === 0">
                    <span class="forum-empty__icon" aria-hidden="true">⌕</span>
                    <h3>No discussions match these filters</h3>
                    <p>Try a different search phrase, thematic area, or status.</p>
                    <button type="button" class="forum-button forum-button--outline" @click="clearFilters">Clear filters</button>
                </div>

                <div class="forum-topic-list" v-else>
                    <article class="forum-topic-card" v-for="topic in topics" :key="topic.id">
                        <div class="forum-topic-card__marker" :style="{ backgroundColor: safeColor(topic.theme && topic.theme.color) }" aria-hidden="true"></div>
                        <div class="forum-topic-card__body">
                            <div class="forum-topic-card__badges">
                                <span class="forum-featured-badge" v-if="topic.is_featured">Featured</span>
                                <span class="forum-theme-badge" v-if="topic.theme">
                                    <span aria-hidden="true" v-text="themeIcon(topic.theme.icon)"></span>
                                    <span v-text="topic.theme.name"></span>
                                </span>
                                <span class="forum-status-badge" :class="'forum-status-badge--' + topic.status" v-text="topic.status"></span>
                            </div>
                            <button type="button" class="forum-topic-card__title" @click="openTopic(topic)">
                                <span v-text="topic.title"></span>
                            </button>
                            <p v-text="topic.summary || 'Open this discussion to read the context and community contributions.'"></p>
                            <div class="forum-topic-card__meta">
                                <span><strong v-text="formatNumber(topic.contributions_count)"></strong> contributions</span>
                                <span aria-hidden="true">&middot;</span>
                                <span v-text="'Active ' + timeAgo(topic.last_activity_at)"></span>
                                <span v-if="topic.closes_at" aria-hidden="true">&middot;</span>
                                <span v-if="topic.closes_at" v-text="'Closes ' + formatDate(topic.closes_at)"></span>
                            </div>
                        </div>
                        <button type="button" class="forum-topic-card__open" @click="openTopic(topic)" :aria-label="'Open ' + topic.title">
                            <span aria-hidden="true">→</span>
                        </button>
                    </article>
                </div>

                <nav class="forum-pagination" aria-label="Discussion result pages" v-if="topicMeta.last_page > 1">
                    <button type="button" @click="loadTopics(topicMeta.current_page - 1)" :disabled="topicMeta.current_page <= 1">Previous</button>
                    <span>Page <strong v-text="topicMeta.current_page"></strong> of <strong v-text="topicMeta.last_page"></strong></span>
                    <button type="button" @click="loadTopics(topicMeta.current_page + 1)" :disabled="topicMeta.current_page >= topicMeta.last_page">Next</button>
                </nav>
            </div>
        </div>

        <article class="forum-thread" v-else aria-labelledby="forum-thread-title">
            <button type="button" class="forum-back-button" @click="closeTopic">
                <span aria-hidden="true">←</span> Back to discussions
            </button>

            <aside
                class="forum-topic-presence-toast"
                :key="'topic-presence-' + (activeTopicJoiner ? activeTopicJoiner.id : 'open')"
                role="status"
                aria-live="polite"
                aria-label="Live participation in this discussion"
                @mouseenter="topicToastPaused = true"
                @mouseleave="topicToastPaused = false"
                @focusin="topicToastPaused = true"
                @focusout="topicToastPaused = false"
            >
                <span class="forum-topic-presence-toast__signal" aria-hidden="true"><i></i></span>
                <div class="forum-topic-presence-toast__body">
                    <span class="forum-topic-presence-toast__eyebrow">Live discussion community</span>
                    <template v-if="activeTopicJoiner">
                        <strong>
                            <span class="forum-topic-presence-toast__flag" aria-hidden="true">
                                <img v-if="activeTopicJoiner.flag_url" :src="activeTopicJoiner.flag_url" alt="">
                                <span v-else v-text="activeTopicJoiner.flag || '🌍'"></span>
                            </span>
                            <span v-text="activeTopicJoiner.display_name + ' joined this discussion'"></span>
                        </strong>
                        <small v-text="(activeTopicJoiner.country || 'ATTP community') + ' · ' + timeAgo(activeTopicJoiner.joined_at)"></small>
                    </template>
                    <template v-else>
                        <strong>This discussion is open for participation</strong>
                        <small>Registered participants will appear here as they join.</small>
                    </template>
                </div>
                <div class="forum-topic-presence-toast__stats">
                    <span>
                        <strong v-text="formatNumber(topicParticipation.participants_count)"></strong>
                        <span v-text="Number(topicParticipation.participants_count) === 1 ? ' participant' : ' participants'"></span>
                    </span>
                    <span>
                        <strong v-text="formatNumber(topicParticipation.countries_count)"></strong>
                        <span v-text="Number(topicParticipation.countries_count) === 1 ? ' country' : ' countries'"></span>
                    </span>
                    <small v-if="topicParticipation.active_now_count" v-text="formatNumber(topicParticipation.active_now_count) + ' active now'"></small>
                </div>
            </aside>

            <div class="forum-thread-loading" v-if="loading.topic" aria-busy="true">
                <div class="forum-skeleton forum-skeleton--thread"></div>
            </div>

            <template v-else>
                <div class="forum-thread-grid">
                <div class="forum-thread-main">
                <header class="forum-thread-header">
                    <div class="forum-topic-card__badges">
                        <span class="forum-theme-badge" v-if="selectedTopic.theme">
                            <span aria-hidden="true" v-text="themeIcon(selectedTopic.theme.icon)"></span>
                            <span v-text="selectedTopic.theme.name"></span>
                        </span>
                        <span class="forum-status-badge" :class="'forum-status-badge--' + selectedTopic.status" v-text="selectedTopic.status"></span>
                        <span class="forum-featured-badge" v-if="selectedTopic.is_featured">Featured</span>
                    </div>
                    <h2 id="forum-thread-title" v-text="selectedTopic.title"></h2>
                    <p class="forum-thread-header__summary" v-text="selectedTopic.summary"></p>
                    <div class="forum-thread-header__meta">
                        <span><strong v-text="formatNumber(selectedTopic.posts ? selectedTopic.posts.length : selectedTopic.contributions_count)"></strong> published contributions</span>
                        <span aria-hidden="true">&middot;</span>
                        <span v-text="'Opened ' + formatDate(selectedTopic.created_at)"></span>
                        <span v-if="selectedTopic.closes_at" aria-hidden="true">&middot;</span>
                        <span v-if="selectedTopic.closes_at" v-text="'Closes ' + formatDate(selectedTopic.closes_at)"></span>
                    </div>
                </header>

                <div class="forum-thread-context" v-if="selectedTopic.body">
                    <h3>Discussion context</h3>
                    <p v-text="selectedTopic.body"></p>
                </div>

                <section class="forum-composer" v-if="participant && selectedTopic.accepts_posts" aria-labelledby="forum-compose-heading">
                    <div class="forum-avatar" aria-hidden="true" v-text="initials(participant.display_name)"></div>
                    <form @submit.prevent="submitPost">
                        <div class="forum-composer__heading">
                            <div>
                                <h3 id="forum-compose-heading" v-text="replyingTo ? 'Reply to ' + replyingTo.author.display_name : 'Add your contribution'"></h3>
                                <p>Your contribution appears immediately. ATTP moderators may remove content that violates the community rules.</p>
                            </div>
                            <button type="button" class="forum-text-button" v-if="replyingTo" @click="cancelReply">Cancel reply</button>
                        </div>
                        <label class="forum-sr-only" for="forum-post-body">Your contribution</label>
                        <textarea
                            id="forum-post-body"
                            ref="postBody"
                            v-model="postForm.body"
                            rows="5"
                            maxlength="5000"
                            placeholder="Share evidence, an experience, or a constructive response…"
                            required
                        ></textarea>
                        <div class="forum-composer__tools">
                            <div class="forum-emoji-control">
                                <button
                                    type="button"
                                    class="forum-emoji-trigger"
                                    @click="toggleEmojiPicker"
                                    :aria-expanded="emojiPickerOpen ? 'true' : 'false'"
                                    aria-controls="forum-emoji-picker"
                                >
                                    <span aria-hidden="true">&#9786;</span>
                                    <span>Add emoji</span>
                                </button>
                                <div
                                    class="forum-emoji-picker"
                                    id="forum-emoji-picker"
                                    v-if="emojiPickerOpen"
                                    role="dialog"
                                    aria-label="Choose an emoji"
                                    @keydown.esc.stop="closeEmojiPicker"
                                >
                                    <div class="forum-emoji-picker__header">
                                        <strong>Choose an emoji</strong>
                                        <button type="button" @click="closeEmojiPicker" aria-label="Close emoji picker">&times;</button>
                                    </div>
                                    <div class="forum-emoji-picker__grid">
                                        <button
                                            type="button"
                                            v-for="emoji in emojiOptions"
                                            :key="emoji.label"
                                            @click="insertEmoji(emoji.value)"
                                            :aria-label="emoji.label"
                                            :title="emoji.label"
                                            v-text="emoji.value"
                                        ></button>
                                    </div>
                                </div>
                            </div>
                            <span v-if="replyingTo">Your response will be linked to the selected contribution.</span>
                            <span v-else>Use reactions for quick feedback, or write a constructive response.</span>
                        </div>
                        <div class="forum-field-error" v-if="postError" v-text="postError"></div>
                        <div class="forum-composer__footer">
                            <span><strong v-text="postForm.body.length"></strong> / 5,000 characters</span>
                            <button type="submit" class="forum-button forum-button--primary" :disabled="posting || postForm.body.trim().length < 2">
                                <span class="forum-button-spinner" v-if="posting" aria-hidden="true"></span>
                                <span v-text="posting ? 'Publishing…' : 'Publish contribution'"></span>
                            </button>
                        </div>
                    </form>
                </section>

                <section class="forum-join-prompt" v-else-if="!participant && selectedTopic.accepts_posts">
                    <div class="forum-join-prompt__icon" aria-hidden="true">+</div>
                    <div>
                        <h3>Have something useful to add?</h3>
                        <p>Create a simple participant account or sign in to contribute and respond to others.</p>
                    </div>
                    <button type="button" class="forum-button forum-button--primary" @click="openAccountForTopic">Join this discussion</button>
                </section>

                <div class="forum-system-alert forum-system-alert--neutral" v-else-if="!selectedTopic.accepts_posts">
                    <span class="forum-system-alert__icon" aria-hidden="true">i</span>
                    <div>
                        <strong>This discussion is not accepting new contributions</strong>
                        <p>You can still read all published contributions below.</p>
                    </div>
                </div>

                <section class="forum-contributions" aria-labelledby="forum-contributions-heading">
                    <div class="forum-contributions__heading">
                        <h3 id="forum-contributions-heading">Community contributions</h3>
                        <span v-text="formatNumber(selectedTopic.posts ? selectedTopic.posts.length : 0) + ' published'"></span>
                    </div>

                    <div class="forum-empty forum-empty--compact" v-if="!selectedTopic.posts || selectedTopic.posts.length === 0">
                        <span class="forum-empty__icon" aria-hidden="true">✦</span>
                        <h3>Start the conversation</h3>
                        <p>There are no published contributions yet. Registered participants can be the first to respond.</p>
                    </div>

                    <ol class="forum-post-list" v-else>
                        <li
                            class="forum-post"
                            :class="{ 'forum-post--reply': post.parent_id, 'forum-post--highlighted': highlightedPostId === post.id }"
                            v-for="post in selectedTopic.posts"
                            :key="post.id"
                            :id="'post-' + post.id"
                            :tabindex="highlightedPostId === post.id ? 0 : -1"
                        >
                            <div class="forum-post__reply-context" v-if="post.parent_id">
                                <span aria-hidden="true">↳</span>
                                <span v-text="'Reply to ' + parentAuthor(post.parent_id)"></span>
                            </div>
                            <div class="forum-post__layout">
                                <div class="forum-avatar forum-avatar--small" aria-hidden="true" v-text="initials(post.author.display_name)"></div>
                                <div class="forum-post__content">
                                    <header>
                                        <div>
                                            <strong v-text="post.author.display_name"></strong>
                                            <span v-if="post.author.organization" v-text="post.author.organization"></span>
                                            <span v-if="post.author.country" v-text="post.author.country"></span>
                                        </div>
                                        <time :datetime="post.created_at" v-text="timeAgo(post.created_at)"></time>
                                    </header>
                                    <p v-text="post.body"></p>
                                    <footer class="forum-post__footer">
                                        <div class="forum-reaction-toolbar" role="group" :aria-label="'React to ' + post.author.display_name + '\'s contribution'" v-if="participant">
                                            <button
                                                type="button"
                                                v-for="reaction in reactionOptions"
                                                :key="reaction.type"
                                                class="forum-reaction-button"
                                                :class="{ active: hasViewerReaction(post, reaction.type) }"
                                                @click="toggleReaction(post, reaction.type)"
                                                :disabled="isReactionBusy(post, reaction.type)"
                                                :aria-pressed="hasViewerReaction(post, reaction.type) ? 'true' : 'false'"
                                                :aria-label="reaction.label + ', ' + reactionCount(post, reaction.type)"
                                                :title="reaction.label"
                                            >
                                                <span class="forum-reaction-button__emoji" aria-hidden="true" v-text="reaction.emoji"></span>
                                                <span class="forum-reaction-button__label" v-text="reaction.label"></span>
                                                <strong v-if="reactionCount(post, reaction.type)" v-text="formatNumber(reactionCount(post, reaction.type))"></strong>
                                            </button>
                                        </div>
                                        <div class="forum-reaction-summary" v-else aria-label="Contribution reactions">
                                            <template v-for="reaction in reactionOptions" :key="reaction.type">
                                                <span v-if="reactionCount(post, reaction.type) > 0" :title="reaction.label">
                                                    <span aria-hidden="true" v-text="reaction.emoji"></span>
                                                    <strong v-text="formatNumber(reactionCount(post, reaction.type))"></strong>
                                                </span>
                                            </template>
                                            <span class="forum-reaction-summary__empty" v-if="totalReactionCount(post) === 0">No reactions yet</span>
                                        </div>
                                        <div class="forum-post__actions">
                                            <button type="button" class="forum-post-action" v-if="participant && selectedTopic.accepts_posts" @click="replyTo(post)">
                                                <span aria-hidden="true">&#8617;</span> Reply
                                            </button>
                                            <button
                                                type="button"
                                                class="forum-post-action"
                                                @click="sharePost(post)"
                                                :disabled="shareBusyPost === post.id"
                                                :aria-label="'Share contribution by ' + post.author.display_name"
                                            >
                                                <span aria-hidden="true">&#8599;</span>
                                                <span v-text="shareBusyPost === post.id ? 'Sharing...' : 'Share'"></span>
                                            </button>
                                        </div>
                                    </footer>
                                </div>
                            </div>
                        </li>
                    </ol>
                </section>
                </div>

                <aside class="forum-topic-rail" aria-label="Discussion information and resources">
                    <div class="forum-topic-rail__sticky">
                        <section class="forum-rail-card forum-rail-card--brief">
                            <div class="forum-rail-card__heading">
                                <span class="forum-rail-card__icon" aria-hidden="true" v-text="selectedTopic.theme ? themeIcon(selectedTopic.theme.icon) : '#' "></span>
                                <div>
                                    <span>Discussion brief</span>
                                    <h3 v-text="selectedTopic.title"></h3>
                                </div>
                            </div>
                            <p v-text="selectedTopic.summary || 'Read the discussion context and add an evidence-led contribution.'"></p>
                            <dl class="forum-rail-facts">
                                <div>
                                    <dt>Status</dt>
                                    <dd><span class="forum-status-badge" :class="'forum-status-badge--' + selectedTopic.status" v-text="selectedTopic.status"></span></dd>
                                </div>
                                <div>
                                    <dt>Contributions</dt>
                                    <dd v-text="formatNumber(selectedTopic.posts ? selectedTopic.posts.length : selectedTopic.contributions_count)"></dd>
                                </div>
                                <div v-if="selectedTopic.closes_at">
                                    <dt>Closes</dt>
                                    <dd v-text="formatDate(selectedTopic.closes_at)"></dd>
                                </div>
                                <div>
                                    <dt>Publishing</dt>
                                    <dd>Live &middot; post-moderated</dd>
                                </div>
                            </dl>
                            <button type="button" class="forum-button forum-button--primary forum-button--wide forum-share-topic" @click="shareTopic" :disabled="shareBusyTopic">
                                <span aria-hidden="true">&#8599;</span>
                                <span v-text="shareBusyTopic ? 'Sharing discussion...' : 'Share discussion'"></span>
                            </button>
                            <nav class="forum-rail-jump-links" aria-label="Jump within discussion">
                                <a href="#forum-contributions-heading">View contributions</a>
                                <a href="#forum-compose-heading" v-if="participant && selectedTopic.accepts_posts">Add your response</a>
                            </nav>
                        </section>

                        <button
                            type="button"
                            class="forum-rail-resource-toggle"
                            v-if="topicResourceCount"
                            @click="mobileResourcesOpen = !mobileResourcesOpen"
                            :aria-expanded="mobileResourcesOpen ? 'true' : 'false'"
                        >
                            <span><strong v-text="topicResourceCount"></strong> topic resources</span>
                            <span aria-hidden="true" v-text="mobileResourcesOpen ? '−' : '+'"></span>
                        </button>

                        <section class="forum-rail-card" :class="{ 'forum-rail-card--mobile-hidden': !mobileResourcesOpen }" v-if="topicRelatedLinks.length">
                            <div class="forum-rail-section-title">
                                <span aria-hidden="true">&#8599;</span>
                                <h3>Related links</h3>
                            </div>
                            <div class="forum-resource-list">
                                <a
                                    v-for="(link, index) in topicRelatedLinks"
                                    :key="'link-' + index"
                                    :href="resourceUrl(link)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="forum-resource-item"
                                >
                                    <span class="forum-resource-item__icon" aria-hidden="true">&#8599;</span>
                                    <span>
                                        <strong v-text="resourceTitle(link, 'Related resource')"></strong>
                                        <small v-if="resourceDescription(link)" v-text="resourceDescription(link)"></small>
                                    </span>
                                </a>
                            </div>
                        </section>

                        <section class="forum-rail-card" :class="{ 'forum-rail-card--mobile-hidden': !mobileResourcesOpen }" v-if="topicMaterials.length">
                            <div class="forum-rail-section-title">
                                <span aria-hidden="true">&#9638;</span>
                                <h3>Discussion materials</h3>
                            </div>
                            <div class="forum-material-list">
                                <div class="forum-material-item" v-for="(material, index) in topicMaterials" :key="'material-' + index">
                                    <span class="forum-material-item__type" v-text="resourceType(material)"></span>
                                    <strong v-text="resourceTitle(material, 'Discussion material')"></strong>
                                    <p v-if="resourceDescription(material)" v-text="resourceDescription(material)"></p>
                                    <a v-if="hasResourceUrl(material)" :href="resourceUrl(material)" target="_blank" rel="noopener noreferrer">Open material <span aria-hidden="true">&#8599;</span></a>
                                </div>
                            </div>
                        </section>

                        <section class="forum-rail-card forum-rail-card--documents" :class="{ 'forum-rail-card--mobile-hidden': !mobileResourcesOpen }" v-if="topicDocuments.length">
                            <div class="forum-rail-section-title">
                                <span aria-hidden="true">&#8681;</span>
                                <h3>Downloadable documents</h3>
                            </div>
                            <div class="forum-document-list">
                                <div
                                    v-for="(document, index) in topicDocuments"
                                    :key="'document-' + index"
                                    class="forum-document-item"
                                >
                                    <span class="forum-document-item__icon" aria-hidden="true">&#8595;</span>
                                    <span class="forum-document-item__content">
                                        <strong v-text="resourceTitle(document, 'Discussion document')"></strong>
                                        <small v-text="documentMeta(document)"></small>
                                        <span class="forum-document-item__actions">
                                            <a
                                                v-if="documentReadUrl(document) !== '#'"
                                                :href="documentReadUrl(document)"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="forum-document-item__action forum-document-item__action--read"
                                                v-text="isUploadedDocument(document) ? 'Read / open' : 'Open document'"
                                            ></a>
                                            <a
                                                v-if="isUploadedDocument(document) && hasResourceUrl(document)"
                                                :href="resourceUrl(document)"
                                                class="forum-document-item__action"
                                                download
                                            >Download</a>
                                        </span>
                                    </span>
                                </div>
                            </div>
                        </section>
                    </div>
                </aside>
                </div>
            </template>
        </article>
    </section>

    {{-- Participant account, login, and two-step registration --}}
    <section v-show="view === 'account'" class="forum-view" aria-labelledby="forum-account-heading">
        <aside
            class="forum-country-toast-wrap"
            aria-label="Participant community by country"
            @mouseenter="countryToastPaused = true"
            @mouseleave="countryToastPaused = false"
            @focusin="countryToastPaused = true"
            @focusout="countryToastPaused = false"
        >
            <div class="forum-country-toast forum-country-toast--loading" v-if="loading.countries" aria-busy="true">
                <span class="forum-country-toast__flag forum-country-toast__flag--skeleton" aria-hidden="true"></span>
                <div>
                    <span class="forum-country-toast__eyebrow">Community by country</span>
                    <strong>Loading participant locations...</strong>
                </div>
            </div>

            <article
                class="forum-country-toast"
                v-else-if="activeCountryToast"
                :key="activeCountryToast.iso2"
                role="group"
                aria-label="Participant community by country"
                aria-live="off"
            >
                <span class="forum-country-toast__flag" aria-hidden="true">
                    <img v-if="activeCountryToast.flag_url" :src="activeCountryToast.flag_url" alt="">
                    <span v-else v-text="activeCountryToast.flag || '🌍'"></span>
                </span>
                <div class="forum-country-toast__content">
                    <span class="forum-country-toast__eyebrow">ATTP community by country</span>
                    <strong>
                        <span v-text="formatNumber(activeCountryToast.participants_count)"></span>
                        <span v-text="Number(activeCountryToast.participants_count) === 1 ? ' participant' : ' participants'"></span>
                        <span> from </span>
                        <span v-text="activeCountryToast.name"></span>
                    </strong>
                    <small>
                        <span v-text="formatNumber(representedCountries.length)"></span>
                        <span v-text="representedCountries.length === 1 ? ' country represented' : ' countries represented'"></span>
                    </small>
                </div>
                <div class="forum-country-toast__controls" v-if="representedCountries.length > 1" aria-label="Browse represented countries">
                    <button type="button" @click="previousCountryToast" aria-label="Previous country">&larr;</button>
                    <button type="button" @click="nextCountryToast" aria-label="Next country">&rarr;</button>
                </div>
            </article>

            <article class="forum-country-toast forum-country-toast--empty" v-else>
                <span class="forum-country-toast__flag" aria-hidden="true">🌍</span>
                <div class="forum-country-toast__content">
                    <span class="forum-country-toast__eyebrow">ATTP community by country</span>
                    <strong>Be the first to represent your country</strong>
                    <small>Country participation will appear here as the community grows.</small>
                </div>
            </article>
        </aside>

        <div class="forum-account-layout" v-if="participant && !passwordMode">
            <article class="forum-member-card">
                <span class="forum-kicker">Participant account</span>
                <div class="forum-member-card__identity">
                    <div class="forum-avatar forum-avatar--large" aria-hidden="true" v-text="initials(participant.display_name)"></div>
                    <div>
                        <h2 id="forum-account-heading">Welcome, <span v-text="participant.display_name"></span></h2>
                        <p v-text="participant.email"></p>
                    </div>
                </div>
                <dl class="forum-member-details">
                    <div>
                        <dt>Country</dt>
                        <dd v-text="participant.country || 'Not provided'"></dd>
                    </div>
                    <div>
                        <dt>Organisation</dt>
                        <dd v-text="participant.organization || 'Not provided'"></dd>
                    </div>
                    <div>
                        <dt>Participant since</dt>
                        <dd v-text="formatDate(participant.joined_at)"></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd><span class="forum-status-badge forum-status-badge--open">Active</span></dd>
                    </div>
                </dl>
                <div class="forum-member-card__actions">
                    <button type="button" class="forum-button forum-button--primary" @click="continueAfterAccount">Participate in active discussions</button>
                    <button type="button" class="forum-button forum-button--outline" @click="openPasswordRecovery">Reset password</button>
                    <button type="button" class="forum-button forum-button--outline" @click="logout" :disabled="authBusy">Sign out</button>
                </div>
            </article>

            <aside class="forum-community-note">
                <span class="forum-community-note__icon" aria-hidden="true">✓</span>
                <h3>You are ready to participate</h3>
                <p>Your participant account lets you publish constructive contributions and react to the community.</p>
                <ul>
                    <li>Keep contributions relevant and evidence-led.</li>
                    <li>Respect different perspectives and lived experience.</li>
                    <li>Contributions appear immediately and may be removed if they violate the community rules.</li>
                </ul>
            </aside>
        </div>

        <div class="forum-account-layout" v-else>
            <article class="forum-auth-card">
                <header class="forum-auth-card__header">
                    <span class="forum-kicker">Participate in active discussions</span>
                    <h2 id="forum-account-heading" v-if="passwordMode === 'forgot'">Reset your participant password</h2>
                    <h2 id="forum-account-heading" v-else-if="passwordMode === 'reset'">Choose a new participant password</h2>
                    <h2 id="forum-account-heading" v-else>Join the ATTP policy community</h2>
                    <p v-if="passwordMode === 'forgot'">Enter your participant email address. If it can be used, we will send a secure reset link.</p>
                    <p v-else-if="passwordMode === 'reset'">Create a strong new password for your discussion participant account.</p>
                    <p v-else>Registration is free and takes about a minute. This device will keep you signed in until you sign out.</p>
                </header>

                <div class="forum-auth-tabs" role="tablist" aria-label="Participant access" v-if="!passwordMode">
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="authMode === 'register' ? 'true' : 'false'"
                        :class="{ active: authMode === 'register' }"
                        @click="setAuthMode('register')"
                    >Create account</button>
                    <button
                        type="button"
                        role="tab"
                        :aria-selected="authMode === 'login' ? 'true' : 'false'"
                        :class="{ active: authMode === 'login' }"
                        @click="setAuthMode('login')"
                    >Sign in</button>
                </div>

                <div class="forum-auth-error" v-if="authError && !passwordMode" role="alert">
                    <strong>We could not continue</strong>
                    <p v-text="authError"></p>
                    <ul v-if="authValidationMessages.length">
                        <li v-for="message in authValidationMessages" :key="message" v-text="message"></li>
                    </ul>
                </div>

                <div class="forum-auth-error" v-if="passwordError" role="alert">
                    <strong>We could not reset the password</strong>
                    <p v-text="passwordError"></p>
                </div>

                <div class="forum-system-alert forum-system-alert--neutral" v-if="passwordMessage" role="status">
                    <span class="forum-system-alert__icon" aria-hidden="true">✓</span>
                    <div>
                        <strong>Check your email</strong>
                        <p v-text="passwordMessage"></p>
                    </div>
                </div>

                <form v-if="passwordMode === 'forgot'" class="forum-auth-form" @submit.prevent="requestPasswordReset" novalidate>
                    <label class="forum-field">
                        <span class="forum-field__label">Participant email address</span>
                        <input type="email" v-model.trim="forgotPasswordForm.email" autocomplete="email" inputmode="email" required placeholder="you@example.org">
                    </label>
                    <button type="submit" class="forum-button forum-button--primary forum-button--wide" :disabled="passwordBusy">
                        <span class="forum-button-spinner" v-if="passwordBusy" aria-hidden="true"></span>
                        <span v-text="passwordBusy ? 'Sending reset link…' : 'Send password reset link'"></span>
                    </button>
                    <p class="forum-auth-switch"><button type="button" @click="cancelPasswordRecovery">Back to participant sign in</button>.</p>
                </form>

                <form v-else-if="passwordMode === 'reset'" class="forum-auth-form" @submit.prevent="resetParticipantPassword" novalidate>
                    <div class="forum-registration-summary">
                        <div class="forum-avatar forum-avatar--small" aria-hidden="true">@</div>
                        <div>
                            <strong>Participant account</strong>
                            <span v-text="resetPasswordForm.email"></span>
                        </div>
                    </div>
                    <div class="forum-form-row">
                        <label class="forum-field">
                            <span class="forum-field__label">New password <em>Required</em></span>
                            <input type="password" v-model="resetPasswordForm.password" autocomplete="new-password" minlength="8" required placeholder="At least 8 letters and numbers">
                        </label>
                        <label class="forum-field">
                            <span class="forum-field__label">Confirm password <em>Required</em></span>
                            <input type="password" v-model="resetPasswordForm.password_confirmation" autocomplete="new-password" minlength="8" required placeholder="Repeat your new password">
                        </label>
                    </div>
                    <p class="forum-password-hint">Use at least 8 characters with both letters and numbers.</p>
                    <div class="forum-step-actions">
                        <button type="button" class="forum-button forum-button--outline" @click="cancelPasswordRecovery">Cancel</button>
                        <button type="submit" class="forum-button forum-button--primary" :disabled="passwordBusy">
                            <span class="forum-button-spinner" v-if="passwordBusy" aria-hidden="true"></span>
                            <span v-text="passwordBusy ? 'Resetting password…' : 'Reset password'"></span>
                        </button>
                    </div>
                </form>

                <form v-else-if="authMode === 'login'" class="forum-auth-form" @submit.prevent="login" novalidate>
                    <label class="forum-field">
                        <span class="forum-field__label">Email address</span>
                        <input type="email" v-model.trim="loginForm.email" autocomplete="email" inputmode="email" required placeholder="you@example.org">
                    </label>
                    <label class="forum-field">
                        <span class="forum-field__label">Password</span>
                        <input type="password" v-model="loginForm.password" autocomplete="current-password" required placeholder="Your password">
                    </label>
                    <button type="submit" class="forum-button forum-button--primary forum-button--wide" :disabled="authBusy">
                        <span class="forum-button-spinner" v-if="authBusy" aria-hidden="true"></span>
                        <span v-text="authBusy ? 'Signing in…' : 'Sign in and participate'"></span>
                    </button>
                    <p class="forum-auth-switch"><button type="button" @click="openPasswordRecovery">Forgot your password?</button></p>
                    <p class="forum-auth-switch">New to the forum? <button type="button" @click="setAuthMode('register')">Create a participant account</button>.</p>
                </form>

                <form v-else class="forum-auth-form" @submit.prevent="register" novalidate>
                    <div class="forum-stepper" aria-label="Account registration progress">
                        <div :class="{ active: registerStep >= 1, current: registerStep === 1 }">
                            <span>1</span>
                            <strong>About you</strong>
                        </div>
                        <span class="forum-stepper__line" :class="{ active: registerStep === 2 }"></span>
                        <div :class="{ active: registerStep >= 2, current: registerStep === 2 }">
                            <span>2</span>
                            <strong>Secure account</strong>
                        </div>
                    </div>

                    <div v-show="registerStep === 1" class="forum-form-step">
                        <div class="forum-form-row">
                            <label class="forum-field">
                                <span class="forum-field__label">Display name <em>Required</em></span>
                                <input type="text" v-model.trim="registerForm.display_name" autocomplete="name" maxlength="100" required placeholder="Name shown in discussions">
                            </label>
                            <label class="forum-field">
                                <span class="forum-field__label">Email address <em>Required</em></span>
                                <input type="email" v-model.trim="registerForm.email" autocomplete="email" inputmode="email" required placeholder="you@example.org">
                            </label>
                        </div>
                        <div class="forum-form-row">
                            <label class="forum-field">
                                <span class="forum-field__label">Country <em>Required</em></span>
                                <select
                                    id="forum-country-select"
                                    ref="countrySelect"
                                    class="forum-country-select"
                                    v-model="registerForm.country"
                                    autocomplete="country-name"
                                    aria-describedby="forum-country-hint"
                                    required
                                    :disabled="loading.countries"
                                >
                                    <option value="" disabled v-text="loading.countries ? 'Loading countries...' : 'Select your country'"></option>
                                    <option
                                        v-for="country in countries"
                                        :key="country.id"
                                        :value="country.name"
                                        :data-flag-url="country.flag_url"
                                        :data-iso2="country.iso2"
                                        v-text="country.name"
                                    ></option>
                                </select>
                                <small class="forum-field__hint" id="forum-country-hint">African Union member states are listed alphabetically.</small>
                            </label>
                            <label class="forum-field">
                                <span class="forum-field__label">Organisation</span>
                                <input type="text" v-model.trim="registerForm.organization" autocomplete="organization" maxlength="255" placeholder="Think tank, institution, or independent">
                            </label>
                        </div>
                        <button type="button" class="forum-button forum-button--primary forum-button--wide" @click="nextRegistrationStep">
                            Continue to secure account <span aria-hidden="true">→</span>
                        </button>
                    </div>

                    <div v-show="registerStep === 2" class="forum-form-step">
                        <div class="forum-registration-summary">
                            <div class="forum-avatar forum-avatar--small" aria-hidden="true" v-text="initials(registerForm.display_name)"></div>
                            <div>
                                <strong v-text="registerForm.display_name"></strong>
                                <span v-text="registerForm.email"></span>
                            </div>
                            <button type="button" class="forum-text-button" @click="registerStep = 1">Edit</button>
                        </div>
                        <div class="forum-form-row">
                            <label class="forum-field">
                                <span class="forum-field__label">Password <em>Required</em></span>
                                <input type="password" v-model="registerForm.password" autocomplete="new-password" minlength="8" required placeholder="At least 8 letters and numbers">
                            </label>
                            <label class="forum-field">
                                <span class="forum-field__label">Confirm password <em>Required</em></span>
                                <input type="password" v-model="registerForm.password_confirmation" autocomplete="new-password" minlength="8" required placeholder="Repeat your password">
                            </label>
                        </div>
                        <p class="forum-password-hint">Use at least 8 characters with both letters and numbers.</p>
                        <label class="forum-check-field">
                            <input type="checkbox" v-model="registerForm.terms">
                            <span>I agree to participate respectfully and understand that ATTP moderators may remove contributions that violate the forum community standards.</span>
                        </label>
                        <div class="forum-step-actions">
                            <button type="button" class="forum-button forum-button--outline" @click="registerStep = 1">Back</button>
                            <button type="submit" class="forum-button forum-button--primary" :disabled="authBusy">
                                <span class="forum-button-spinner" v-if="authBusy" aria-hidden="true"></span>
                                <span v-text="authBusy ? 'Creating account…' : 'Create account and participate'"></span>
                            </button>
                        </div>
                    </div>
                </form>
            </article>

            <aside class="forum-community-note">
                <span class="forum-community-note__icon" aria-hidden="true">✦</span>
                <h3>One community, many perspectives</h3>
                <p>Join policy researchers, practitioners, institutions, and people with lived experience from across Africa.</p>
                <ul>
                    <li>Browse all discussions without an account.</li>
                    <li>Register only when you are ready to contribute.</li>
                    <li>Your email address is never shown publicly.</li>
                </ul>
                <div class="forum-community-note__stat">
                    <strong v-text="overviewValue('participants')"></strong>
                    <span>registered participants and growing</span>
                </div>
            </aside>
        </div>
    </section>
</div>
