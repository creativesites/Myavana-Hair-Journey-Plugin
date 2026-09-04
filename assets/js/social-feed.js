/**
 * MYAVANA Community Feed JavaScript
 * Handles feed loading, filtering, likes, comments, and post creation
 *
 * Dependencies: jQuery
 */

(function($) {
    'use strict';

    // Settings from PHP
    const settings = window.myavanaCommunitySettings || {};

    // State
    let currentFilter = settings.currentFilter || 'all';
    let currentPage = 1;
    let isLoading = false;
    let hasMorePosts = true;
    let searchQuery = settings.initialSearch || '';
    let hashtagQuery = settings.initialHashtag || '';
    let mediaFilter = settings.initialMediaFilter || '';
    let feedMode = settings.initialMode || 'discover';
    let circleFilter = settings.initialCircle || '';
    const postCache = new Map();

    /**
     * Initialize community feed
     */
    function initCommunityFeed() {
        // Setup event listeners
        setupFilterButtons();
        setupDiscoveryPanel();
        setupCreatePostModal();
        setupLoadMore();

        if (feedMode === 'following') {
            currentFilter = 'following';
            $('.myavana-community-mode-btn').removeClass('active');
            $('.myavana-community-mode-btn[data-mode="following"]').addClass('active');
            $('.myavana-filter-btn').removeClass('active');
            $('.myavana-filter-btn[data-filter="following"]').addClass('active');
        }

        if (circleFilter) {
            $('.myavana-circle-filter-btn').removeClass('active');
            $(`.myavana-circle-filter-btn[data-circle="${circleFilter}"]`).addClass('active');
        }

        // Load initial data
        loadDiscoveryData();
        loadPosts();
    }

    /**
     * Expose reload function globally for entry selector
     */
    window.MyavanaSocialFeed = {
        loadPosts: function() {
            currentPage = 1;
            loadPosts(false);
        },
        reload: function() {
            currentPage = 1;
            loadPosts(false);
        }
    };

    /**
     * Load posts from server
     */
    function loadPosts(append = false) {
        if (isLoading) return;

        isLoading = true;
        if (!append) {
            showLoading();
        }

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_community_feed',
                nonce: settings.nonce,
                filter: currentFilter,
                mode: feedMode,
                circle: circleFilter,
                search: searchQuery,
                hashtag: hashtagQuery,
                media_filter: mediaFilter,
                page: currentPage,
                per_page: settings.perPage || 10
            },   
            success: function(response) {
                if (response.success) {
                    const posts = response.data;

                    if (posts.length === 0) {
                        if (currentPage === 1) {
                            showEmptyState();
                        } else {
                            hasMorePosts = false;
                            $('#myavana-feed-load-more').hide();
                        }
                    } else {
                        if (append) {
                            appendPosts(posts);
                        } else {
                            renderPosts(posts);
                        }

                        // Check if there are more posts
                        if (posts.length < Number(settings.perPage || 10)) {
                            hasMorePosts = false;
                            $('#myavana-feed-load-more').hide();
                            $('.myavana-feed-caught-up').remove();
                            if (currentPage > 1 || posts.length > 0) {
                                $('#myavana-feed-load-more').after('<p class="myavana-feed-caught-up">You’re all caught up.</p>');
                            }
                        } else {
                            $('.myavana-feed-caught-up').remove();
                            $('#myavana-feed-load-more').show();
                        }
                    }
                } else {
                    showError('Failed to load posts. Please try again.');
                }
            },
            error: function() {
                showError('Network error. Please check your connection.');
            },
            complete: function() {
                isLoading = false;
                if (!append) {
                    hideLoading();
                }
            }
        });
    }

    /**
     * Render posts to the grid
     */
    function mergePostData(nextPost, existingPost = {}) {
        const merged = Object.assign({}, existingPost, nextPost || {});
        merged.id = Number(merged.id || existingPost.id || 0);
        merged.title = merged.title || '';
        merged.content = merged.content || '';
        merged.image_url = merged.image_url || '';
        merged.video_url = merged.video_url || '';
        merged.post_type = merged.post_type || 'general';
        merged.privacy_level = merged.privacy_level || 'public';
        merged.hashtags = merged.hashtags || '';
        merged.likes_count = Number(merged.likes_count || 0);
        merged.comments_count = Number(merged.comments_count || 0);
        merged.reactions = merged.reactions || existingPost.reactions || {};
        merged.user_reaction = merged.user_reaction || existingPost.user_reaction || null;
        merged.formatted_date = merged.formatted_date || existingPost.formatted_date || '';
        return merged;
    }

    function renderPosts(posts) {
        const $grid = $('#myavana-feed-grid');
        $grid.empty();
        $('.myavana-feed-caught-up').remove();
        postCache.clear();

        posts.forEach(post => {
            const normalizedPost = mergePostData(post);
            postCache.set(normalizedPost.id, normalizedPost);
            $grid.append(createPostCard(normalizedPost));
        });

        $('#myavana-feed-empty').hide();
        $grid.show();
    }

    /**
     * Append posts to existing grid
     */
    function appendPosts(posts) {
        const $grid = $('#myavana-feed-grid');

        posts.forEach(post => {
            const normalizedPost = mergePostData(post);
            postCache.set(normalizedPost.id, normalizedPost);
            $grid.append(createPostCard(normalizedPost));
        });
    }

    function upsertPostCard(postData) {
        const postId = Number(postData && postData.id ? postData.id : 0);
        if (!postId) {
            return null;
        }

        const existingPost = postCache.get(postId) || {};
        const normalizedPost = mergePostData(postData, existingPost);
        postCache.set(postId, normalizedPost);

        const $grid = $('#myavana-feed-grid');
        const $existingCard = $grid.find(`.myavana-post-card[data-post-id="${postId}"]`);
        const $newCard = $(createPostCard(normalizedPost));

        if ($existingCard.length) {
            $existingCard.replaceWith($newCard);
        } else if ($grid.length) {
            $grid.prepend($newCard);
        }

        return normalizedPost;
    }

    /**
     * Create HTML for a single post card
     */
    function createPostCard(post) {
        const reactions = post.reactions || {};
        const userReaction = post.user_reaction || null;
        const isLiked = post.is_liked || false;
        const postTitle = normalizeCommunityText(post.title || '');
        const postContent = normalizeCommunityText(post.content || '');
        const hasText = postContent.trim().length > 0;

        // Calculate total reactions - use likes_count if reactions are not available
        const totalReactions = post.likes_count || Object.values(reactions).reduce((sum, count) => sum + count, 0);

        const videoHtml = post.video_url ? `
            <div class="myavana-post-video-wrapper">
                <video class="myavana-post-video" controls preload="metadata" playsinline>
                    <source src="${escapeHtml(post.video_url)}">
                </video>
            </div>
        ` : '';

        const imageHtml = post.image_url ? `
            <div class="myavana-post-image-wrapper">
                <img src="${escapeHtml(post.image_url)}"
                     alt="${escapeHtml(postTitle)}"
                     class="myavana-post-image"
                     loading="lazy">
            </div>
        ` : '';

        const typeLabels = {
            'progress': 'Progress Update',
            'transformation': 'Transformation',
            'routine': 'Routine',
            'products': 'Product Review',
            'tips': 'Tips & Advice',
            'video': 'Video Update',
            'general': 'General'
        };

        // Reaction counts HTML
        const reactionEmojis = {
            'like': '❤️',
            'love': '😍',
            'celebrate': '🎉',
            'insightful': '💡'
        };

        let reactionCountsHtml = '';
        if (totalReactions > 0) {
            const reactionItems = Object.entries(reactions)
                .filter(([type, count]) => count > 0)
                .map(([type, count]) => `
                    <div class="myavana-reaction-count-item" data-reaction="${type}">
                        <span class="reaction-emoji">${reactionEmojis[type]}</span>
                        <span class="reaction-count">${count}</span>
                    </div>
                `).join('');

            reactionCountsHtml = `
                <div class="myavana-reaction-counts">
                    ${reactionItems}
                </div>
            `;
        }

        const hashtagChips = String(post.hashtags || '')
            .split(',')
            .map((tag) => tag.trim().replace(/^#+/, '').toLowerCase())
            .filter((tag) => tag.length > 1)
            .slice(0, 8)
            .map((tag) => `<button type="button" class="myavana-post-hashtag" data-hashtag="${escapeHtml(tag)}">#${escapeHtml(tag)}</button>`)
            .join('');

        return `
            <article class="myavana-post-card" data-post-id="${post.id}">
                <div class="myavana-post-header">
                    <img src="${escapeHtml(post.user_avatar)}"
                         alt="${escapeHtml(post.display_name)}"
                         class="myavana-post-avatar clickable-avatar"
                         data-user-id="${post.user_id}"
                         title="View ${escapeHtml(post.display_name)}'s profile">
                    <div class="myavana-post-user-info">
                        <h3 class="myavana-post-username clickable-username" data-user-id="${post.user_id}">${escapeHtml(post.display_name)}</h3>
                        ${post.is_verified_journey ? '<span class="myavana-verified-journey-badge">Verified Journey</span>' : ''}
                        <time class="myavana-post-time">${escapeHtml(post.formatted_date)}</time>
                    </div>
                    <div class="myavana-post-header-meta">
                        <span class="myavana-post-type-badge">${typeLabels[post.post_type] || 'General'}</span>
                        ${post.user_id == settings.userId ? `
                            <div class="myavana-post-actions-menu">
                                <button class="myavana-ci-pin-btn ${post.is_pinned ? 'pinned' : ''}" data-post-id="${post.id}" title="${post.is_pinned ? 'Unpin post' : 'Pin post'}">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="${post.is_pinned ? 'var(--myavana-coral)' : 'none'}" stroke="currentColor" stroke-width="2">
                                        <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"></path>
                                    </svg>
                                </button>
                                <button class="myavana-post-edit-btn" data-post-id="${post.id}" title="Edit post">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                                    </svg>
                                </button>
                                <button class="myavana-post-delete-btn" data-post-id="${post.id}" title="Delete post">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"></polyline>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                                        <line x1="10" y1="11" x2="10" y2="17"></line>
                                        <line x1="14" y1="11" x2="14" y2="17"></line>
                                    </svg>
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>

                ${videoHtml}
                ${imageHtml}

                <div class="myavana-post-content">
                    ${postTitle ? `<h2 class="myavana-post-title">${escapeHtml(postTitle)}</h2>` : ''}
                    ${hasText ? `<p class="myavana-post-text ${postContent.length > 200 ? 'truncated' : ''}">${parseTextWithMentionsAndHashtags(escapeHtml(postContent))}</p>` : ''}
                    ${hasText && postContent.length > 200 ? '<a href="#" class="myavana-read-more">Read more</a>' : ''}
                    ${hashtagChips ? `<div class="myavana-post-hashtags">${hashtagChips}</div>` : ''}
                </div>

                ${reactionCountsHtml}

                <div class="myavana-post-actions">
                    <div class="myavana-reactions-picker" data-post-id="${post.id}">
                        <button class="myavana-reaction-option" data-reaction="like" title="Like">❤️</button>
                        <button class="myavana-reaction-option" data-reaction="love" title="Love">😍</button>
                        <button class="myavana-reaction-option" data-reaction="celebrate" title="Celebrate">🎉</button>
                        <button class="myavana-reaction-option" data-reaction="insightful" title="Insightful">💡</button>
                    </div>

                    <button class="myavana-action-btn myavana-ci-react-btn ${(userReaction || isLiked) ? 'reacted' : ''}"
                            data-post-id="${post.id}"
                            data-user-reaction="${userReaction || (isLiked ? 'like' : '')}">
                        <span class="reaction-display">${userReaction ? reactionEmojis[userReaction] : '❤️'}</span>
                        <span class="myavana-action-count">${totalReactions || 0}</span>
                    </button>

                    <button class="myavana-action-btn comment-btn" data-post-id="${post.id}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                        </svg>
                        <span class="myavana-action-count">${post.comments_count || 0}</span>
                    </button>

                    <button class="myavana-action-btn share-btn" data-post-id="${post.id}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="18" cy="5" r="3"></circle>
                            <circle cx="6" cy="12" r="3"></circle>
                            <circle cx="18" cy="19" r="3"></circle>
                            <line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
                            <line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
                        </svg>
                    </button>

                    <button class="myavana-action-btn bookmark-btn ${post.is_bookmarked || post.is_saved ? 'bookmarked' : ''}" data-post-id="${post.id}" title="${post.is_bookmarked || post.is_saved ? 'Saved' : 'Save post'}">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="${post.is_bookmarked || post.is_saved ? 'var(--myavana-coral)' : 'none'}" stroke="currentColor" stroke-width="2">
                            <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                        </svg>
                    </button>

                    ${post.user_id != settings.userId ? `
                        <button class="myavana-action-btn myavana-ci-report-btn" data-post-id="${post.id}" data-content-type="post" title="Report post">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                                <line x1="12" y1="9" x2="12" y2="13"></line>
                                <line x1="12" y1="17" x2="12.01" y2="17"></line>
                            </svg>
                        </button>
                    ` : ''}
                </div>

                <!-- Comments Section -->
                <div class="myavana-post-comments" id="comments-${post.id}" style="display: none;">
                    <div class="myavana-comments-list"></div>
                    <div class="myavana-comment-form">
                        <div class="myavana-comment-input-wrapper">
                            <textarea class="myavana-comment-input" placeholder="Write a comment..." rows="1"></textarea>
                            <button class="myavana-comment-submit" data-post-id="${post.id}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </article>
        `;
    }

    

    /**
     * Setup filter button handlers
     */
    function setupFilterButtons() {
        $('.myavana-filter-btn').on('click', function() {
            const filter = $(this).data('filter');

            if (filter === currentFilter) return;

            // Update UI
            $('.myavana-filter-btn').removeClass('active');
            $(this).addClass('active');

            // Update state and reload
            currentFilter = filter;
            if (filter === 'following') {
                feedMode = 'following';
                $('.myavana-community-mode-btn').removeClass('active');
                $('.myavana-community-mode-btn[data-mode="following"]').addClass('active');
            } else if (feedMode === 'following') {
                feedMode = 'discover';
                $('.myavana-community-mode-btn').removeClass('active');
                $('.myavana-community-mode-btn[data-mode="discover"]').addClass('active');
            }
            currentPage = 1;
            hasMorePosts = true;

            loadPosts();
        });
    }

    /**
     * Setup discovery, search, and media filters.
     */
    function setupDiscoveryPanel() {
        $('.myavana-community-mode-btn').on('click', function() {
            const nextMode = String($(this).data('mode') || 'discover');
            if (nextMode === feedMode) {
                return;
            }

            feedMode = nextMode;
            $('.myavana-community-mode-btn').removeClass('active');
            $(this).addClass('active');

            if (feedMode === 'following') {
                currentFilter = 'following';
                $('.myavana-filter-btn').removeClass('active');
                $('.myavana-filter-btn[data-filter="following"]').addClass('active');
            } else if (currentFilter === 'following') {
                currentFilter = 'all';
                $('.myavana-filter-btn').removeClass('active');
                $('.myavana-filter-btn[data-filter="all"]').addClass('active');
            }

            currentPage = 1;
            hasMorePosts = true;
            loadPosts(false);
        });

        $('.myavana-circle-filter-btn').on('click', function() {
            const nextCircle = String($(this).data('circle') || '');
            if (nextCircle === circleFilter) {
                return;
            }

            circleFilter = nextCircle;
            $('.myavana-circle-filter-btn').removeClass('active');
            $(this).addClass('active');
            currentPage = 1;
            hasMorePosts = true;
            loadPosts(false);
        });

        $('#myavana-community-search-btn').on('click', function() {
            const query = ($('#myavana-community-search-input').val() || '').trim();
            if (query.startsWith('#')) {
                hashtagQuery = query.replace(/^#+/, '').toLowerCase();
                searchQuery = '';
            } else {
                searchQuery = query;
                hashtagQuery = '';
            }
            currentPage = 1;
            hasMorePosts = true;
            loadPosts(false);
        });

        $('#myavana-community-search-input').on('keydown', function(event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                $('#myavana-community-search-btn').trigger('click');
            }
        });

        $('#myavana-community-search-clear-btn').on('click', function() {
            searchQuery = '';
            hashtagQuery = '';
            $('#myavana-community-search-input').val('');
            $('.myavana-media-filter-btn').removeClass('active');
            $('.myavana-media-filter-btn[data-media-filter=""]').addClass('active');
            mediaFilter = '';
            circleFilter = '';
            $('.myavana-circle-filter-btn').removeClass('active');
            $('.myavana-circle-filter-btn[data-circle=""]').addClass('active');
            currentPage = 1;
            hasMorePosts = true;
            loadPosts(false);
        });

        $('.myavana-media-filter-btn').on('click', function() {
            const nextFilter = String($(this).data('media-filter') || '');
            if (nextFilter === mediaFilter) {
                return;
            }

            $('.myavana-media-filter-btn').removeClass('active');
            $(this).addClass('active');
            mediaFilter = nextFilter;
            currentPage = 1;
            hasMorePosts = true;
            loadPosts(false);
        });
    }

    function loadDiscoveryData() {
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_discovery',
                nonce: settings.nonce
            },
            success: function(response) {
                if (!response.success || !response.data) {
                    return;
                }

                renderDiscoveryHashtags(response.data.trending_hashtags || []);
                renderDiscoveryCreators(response.data.suggested_creators || []);
                renderDiscoveryChallenges(response.data.active_challenges || []);
            }
        });
    }

    function renderDiscoveryHashtags(items) {
        const $container = $('#myavana-discovery-hashtags');
        if (!$container.length) {
            return;
        }

        if (!items.length) {
            $container.html('<span class="myavana-discovery-empty">No hashtags yet.</span>');
            return;
        }

        $container.html(items.map((item) => `
            <button type="button" class="myavana-discovery-hashtag" data-hashtag="${escapeHtml(item.tag)}">
                #${escapeHtml(item.tag)} <span>${Number(item.count || 0)}</span>
            </button>
        `).join(''));
    }

    function renderDiscoveryCreators(items) {
        const $container = $('#myavana-discovery-creators');
        if (!$container.length) {
            return;
        }

        if (!items.length) {
            $container.html('<span class="myavana-discovery-empty">No creator suggestions yet.</span>');
            return;
        }

        $container.html(items.map((item) => `
            <button type="button" class="myavana-discovery-creator clickable-avatar" data-user-id="${item.user_id}">
                <img src="${escapeHtml(item.avatar)}" alt="${escapeHtml(item.display_name)}">
                <span class="myavana-discovery-creator-name">${escapeHtml(item.display_name)}</span>
                <span class="myavana-discovery-creator-meta">${Number(item.followers_count || 0)} followers</span>
            </button>
        `).join(''));
    }

    function renderDiscoveryChallenges(items) {
        const $container = $('#myavana-discovery-challenges');
        if (!$container.length) {
            return;
        }

        if (!items.length) {
            $container.html('<span class="myavana-discovery-empty">No active challenges right now.</span>');
            return;
        }

        $container.html(items.map((item) => `
            <button type="button" class="myavana-discovery-challenge ${item.hashtag ? 'has-hashtag' : ''}" data-hashtag="${escapeHtml(String(item.hashtag || '').replace(/^#/, ''))}">
                <span class="myavana-discovery-challenge-title">${escapeHtml(item.title)}</span>
                <span class="myavana-discovery-challenge-meta">${Number(item.participants_count || 0)} participants</span>
                ${item.hashtag ? `<span class="myavana-discovery-challenge-tag">${escapeHtml(item.hashtag)}</span>` : ''}
            </button>
        `).join(''));
    }

    $(document).on('click', '.myavana-discovery-hashtag, .myavana-post-hashtag, .myavana-hashtag, .myavana-discovery-challenge.has-hashtag', function(event) {
        event.preventDefault();
        event.stopPropagation();

        const tag = String($(this).data('hashtag') || '').trim().replace(/^#+/, '').toLowerCase();
        if (!tag) {
            return;
        }

        hashtagQuery = tag;
        $('#myavana-community-search-input').val(`#${tag}`);
        currentPage = 1;
        hasMorePosts = true;
        loadPosts(false);
    });

    /**
     * Setup create post modal
     */
    function setupCreatePostModal() {
        const $modal = $('#myavana-create-post-modal');
        const $form = $('#myavana-create-post-form');
        const $uploadArea = $('#myavana-upload-area');
        const $fileInput = $('#myavana-post-image');
        const $uploadPreview = $('#myavana-upload-preview');
        const $videoUploadArea = $('#myavana-video-upload-area');
        const $videoFileInput = $('#myavana-post-video');
        const $videoUploadPreview = $('#myavana-video-upload-preview');
        const $videoUrlInput = $('#myavana-post-video-url');
        const $titleInput = $('#myavana-post-title');
        const $contentInput = $('#myavana-post-content');
        const $postTypeInput = $('#myavana-post-type');
        const $quickModeButtons = $form.find('.myavana-quick-post-btn[data-mode]');
        const $advancedDetails = $form.find('.myavana-composer-advanced');
        const $hashtagsInput = $('#myavana-post-hashtags');
        const $aiMetadataInput = $('#myavana-post-ai-metadata');
        const $aiResponse = $('#myavana-ai-response');
        const $aiHashtags = $('#myavana-ai-hashtags');
        const $aiButtons = $form.find('.myavana-ai-assist-btn');

        console.log('Modal setup:', {
            modal: $modal.length,
            form: $form.length,
            uploadArea: $uploadArea.length,
            fileInput: $fileInput.length
        });

        function normalizeHashtags(hashtags) {
            if (!hashtags) {
                return [];
            }

            const list = Array.isArray(hashtags) ? hashtags : String(hashtags).split(/[,\s]+/);
            const cleaned = list
                .map((tag) => String(tag).trim().replace(/^#+/, '').replace(/[^\w]/g, '').toLowerCase())
                .filter((tag) => tag.length > 1);

            return [...new Set(cleaned)].slice(0, 12);
        }

        function renderAiHashtags(hashtags) {
            if (!hashtags || hashtags.length === 0) {
                $aiHashtags.empty().hide();
                return;
            }

            $aiHashtags
                .html(hashtags.map((tag) => `<span class="myavana-ai-hashtag-chip">#${escapeHtml(tag)}</span>`).join(''))
                .css('display', 'flex');
        }

        function setAiResponse(message, type = 'info') {
            if (!message) {
                $aiResponse.removeClass('success error').empty().hide();
                return;
            }

            $aiResponse
                .removeClass('success error')
                .addClass(type === 'error' ? 'error' : 'success')
                .text(message)
                .show();
        }

        function createFallbackTitle(postType, hasImage, hasVideo) {
            if (hasVideo || postType === 'video') {
                return 'Video Hair Update';
            }
            if (hasImage) {
                return 'Hair Journey Photo Update';
            }

            const typeLabelMap = {
                progress: 'Hair Progress Update',
                transformation: 'Transformation Update',
                routine: 'Routine Update',
                products: 'Product Review',
                tips: 'Hair Care Tip',
                general: 'Hair Journey Update'
            };

            return typeLabelMap[postType] || 'Hair Journey Update';
        }

        function resetCreatePostForm() {
            $form[0].reset();
            $quickModeButtons.removeClass('active');
            $quickModeButtons.filter('[data-mode="text"]').addClass('active');
            if ($advancedDetails.length) {
                $advancedDetails.prop('open', false);
            }
            $fileInput.val('');
            $uploadPreview.hide().empty();
            $uploadArea.find('.myavana-upload-prompt').show();
            $videoFileInput.val('');
            $videoUploadPreview.hide().empty();
            $videoUploadArea.find('.myavana-upload-prompt').show();
            $videoUrlInput.val('');
            $hashtagsInput.val('');
            $aiMetadataInput.val('');
            setAiResponse('');
            renderAiHashtags([]);
        }

        function closeCreatePostModal() {
            $modal.removeClass('active');
            $('body').css('overflow', '');
            resetCreatePostForm();
        }

        // Open modal
        $('#myavana-create-post-btn, .myavana-feed-empty .myavana-btn-primary').on('click', function() {
            console.log('Opening modal...');
            $modal.addClass('active');
            $('body').css('overflow', 'hidden');
        });

        $quickModeButtons.on('click', function(e) {
            e.preventDefault();
            const mode = String($(this).data('mode') || 'text');
            $quickModeButtons.removeClass('active');
            $(this).addClass('active');

            if (mode === 'photo') {
                $postTypeInput.val('progress');
                $fileInput.trigger('click');
                return;
            }

            if (mode === 'video') {
                $postTypeInput.val('video');
                $videoFileInput.trigger('click');
                return;
            }

            $postTypeInput.val('general');
            $contentInput.trigger('focus');
        });

        $('#myavana-open-entry-selector-inline').on('click', function(e) {
            e.preventDefault();
            closeCreatePostModal();
            $('.share-existing-entry-btn').trigger('click');
        });

        // Close modal - Fixed to prevent closing when clicking inside modal
        $('#myavana-close-modal, #myavana-cancel-post').on('click', function() {
            closeCreatePostModal();
        });

        // Close modal when clicking overlay (outside modal content)
        $modal.find('.myavana-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeCreatePostModal();
            }
        });

        // File upload handling - Fixed to prevent infinite loop
        $uploadArea.on('click', function(e) {
            // Don't trigger if clicking the file input itself
            if (e.target !== $fileInput[0]) {
                e.preventDefault();
                e.stopPropagation();
                $fileInput.trigger('click');
            }
        });

        $fileInput.on('change', function(e) {
            const file = e.target.files[0];
            if (file && file.type.startsWith('image/')) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    $uploadPreview.html(`
                        <img src="${event.target.result}" alt="Preview">
                        <button type="button" class="myavana-remove-image" style="position: absolute; top: 8px; right: 8px; background: var(--myavana-coral); color: white; border: none; border-radius: 50%; width: 32px; height: 32px; cursor: pointer;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    `).show();
                    $uploadArea.find('.myavana-upload-prompt').hide();
                };
                reader.readAsDataURL(file);
            }
        });

        // Remove image
        $uploadPreview.on('click', '.myavana-remove-image', function(e) {
            e.stopPropagation();
            $fileInput.val('');
            $uploadPreview.hide().empty();
            $uploadArea.find('.myavana-upload-prompt').show();
        });

        $videoUploadArea.on('click', function(e) {
            if (e.target !== $videoFileInput[0]) {
                e.preventDefault();
                e.stopPropagation();
                $videoFileInput.trigger('click');
            }
        });

        $videoFileInput.on('change', function(e) {
            const file = e.target.files[0];
            if (!file || !file.type.startsWith('video/')) {
                return;
            }

            const objectUrl = URL.createObjectURL(file);
            $videoUploadPreview.html(`
                <video src="${escapeHtml(objectUrl)}" controls preload="metadata"></video>
                <button type="button" class="myavana-remove-video">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `).show();
            $videoUploadArea.find('.myavana-upload-prompt').hide();
            $postTypeInput.val('video');
        });

        $videoUploadPreview.on('click', '.myavana-remove-video', function(e) {
            e.preventDefault();
            e.stopPropagation();
            $videoFileInput.val('');
            $videoUploadPreview.hide().empty();
            $videoUploadArea.find('.myavana-upload-prompt').show();
        });

        $videoUrlInput.on('input', function() {
            if (($(this).val() || '').trim().length > 0) {
                $postTypeInput.val('video');
            }
        });

        // AI assist actions
        $aiButtons.on('click', function(e) {
            e.preventDefault();

            const $btn = $(this);
            const assistType = $btn.data('ai-action');
            const title = normalizeCommunityText($titleInput.val() || '').trim();
            const content = normalizeCommunityText($contentInput.val() || '').trim();
            const postType = ($postTypeInput.val() || 'general').trim();

            if (assistType === 'improve' && !content) {
                showNotification('Write a draft first, then use Improve Draft.', 'info');
                return;
            }

            if (assistType === 'hashtags' && !title && !content) {
                showNotification('Add a title or story first to generate hashtags.', 'info');
                return;
            }

            $aiButtons.prop('disabled', true);
            $btn.text('Thinking...');
            setAiResponse('Generating AI suggestions...');

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'myavana_ci_ai_post_assist',
                    nonce: settings.nonce,
                    assist_type: assistType,
                    title: title,
                    content: content,
                    post_type: postType
                },
                success: function(response) {
                    if (!response.success || !response.data) {
                        setAiResponse(response.data || 'AI assistant is unavailable right now.', 'error');
                        return;
                    }

                    const data = response.data;
                    const hashtags = normalizeHashtags(data.hashtags || data.hashtag_string || '');

                    if (assistType === 'caption') {
                        if (data.title) {
                            $titleInput.val(data.title);
                        }
                        if (data.content) {
                            $contentInput.val(data.content);
                        }
                    } else if (assistType === 'improve' && data.content) {
                        $contentInput.val(data.content);
                    }

                    if (hashtags.length > 0) {
                        $hashtagsInput.val(hashtags.join(','));
                        renderAiHashtags(hashtags);
                    }

                    $aiMetadataInput.val(JSON.stringify({
                        assisted: true,
                        assist_type: assistType,
                        generated_at: new Date().toISOString(),
                        source: data.source || 'ai',
                        hashtags: hashtags
                    }));

                    setAiResponse(data.message || 'AI suggestions are ready.');
                },
                error: function() {
                    setAiResponse('Network error while using AI assistant.', 'error');
                },
                complete: function() {
                    $aiButtons.prop('disabled', false);
                    $aiButtons.each(function() {
                        const action = $(this).data('ai-action');
                        const labelMap = {
                            caption: 'Generate Caption',
                            improve: 'Improve Draft',
                            hashtags: 'Suggest Hashtags'
                        };
                        $(this).text(labelMap[action] || 'Try Again');
                    });
                }
            });
        });

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();

            const postType = ($postTypeInput.val() || '').trim();
            const title = normalizeCommunityText($titleInput.val() || '').trim();
            const content = normalizeCommunityText($contentInput.val() || '').trim();
            const videoUrlValue = ($videoUrlInput.val() || '').trim();
            const selectedVideoInput = $videoFileInput[0];
            const hasVideoFile = !!(selectedVideoInput && selectedVideoInput.files && selectedVideoInput.files.length > 0);
            const selectedImageInput = $fileInput[0];
            const hasImageFile = !!(selectedImageInput && selectedImageInput.files && selectedImageInput.files.length > 0);

            if (!title && !content && !hasImageFile && !hasVideoFile && !videoUrlValue) {
                showNotification('Add text, photo, or video before posting.', 'error');
                return;
            }

            if (postType === 'video' && !videoUrlValue && !hasVideoFile) {
                showNotification('Video posts require a video URL or uploaded video.', 'error');
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'create_community_post');
            formData.append('nonce', settings.nonce);
            formData.set('title', title || createFallbackTitle(postType, hasImageFile, hasVideoFile || !!videoUrlValue));
            formData.set('content', content);

            // Get file if exists
            const fileInput = $fileInput[0];
            if (fileInput.files.length > 0) {
                formData.append('image', fileInput.files[0]);
            }

            if (hasVideoFile) {
                formData.append('video', selectedVideoInput.files[0]);
            }

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        // Close modal
                        closeCreatePostModal();

                        // Reload feed
                        currentPage = 1;
                        loadPosts();
                        loadDiscoveryData();

                        // Show success message
                        showNotification('Your post has been shared successfully!', 'success');
                    } else {
                        showNotification(response.data || 'Failed to create post', 'error');
                    }
                },
                error: function() {
                    showNotification('Network error. Please try again.', 'error');
                }
            });
        });

        // Save as Draft
        $('#myavana-ci-save-draft-btn').on('click', function(e) {
            e.preventDefault();

            const title = normalizeCommunityText($titleInput.val() || '').trim();
            const content = normalizeCommunityText($contentInput.val() || '').trim();
            const postType = ($postTypeInput.val() || 'general').trim();

            if (!title && !content) {
                showNotification('Add a title or story before saving your draft.', 'error');
                return;
            }

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'myavana_ci_manage_draft',
                    nonce: settings.nonce,
                    action_type: 'save',
                    title: title,
                    content: content,
                    post_type: postType
                },
                success: function(response) {
                    if (response.success) {
                        closeCreatePostModal();
                        const draftMessage = response.data && response.data.message
                            ? response.data.message
                            : 'Draft saved successfully!';
                        showNotification(draftMessage, 'success');
                    } else {
                        showNotification(response.data || 'Failed to save draft', 'error');
                    }
                },
                error: function() {
                    showNotification('Network error. Please try again.', 'error');
                }
            });
        });
    }

    /**
     * Setup load more button
     */
    function setupLoadMore() {
        $('#myavana-load-more-btn').on('click', function() {
            if (!isLoading && hasMorePosts) {
                currentPage++;
                loadPosts(true);
            }
        });
    }

    /**
     * Setup infinite scroll (optional enhancement)
     */
    function setupInfiniteScroll() {
        // Deliberately retained as an extension point. Feed continuation uses the
        // visible Load More button so pagination is predictable and never races.
    }

    /**
     * Handle like button clicks
     */
    $(document).on('click', '.like-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        const $count = $btn.find('.myavana-action-count');
        const $svg = $btn.find('svg');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'like_post',
                nonce: settings.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    const { action, likes_count } = response.data;

                    // Update UI
                    $count.text(likes_count);

                    if (action === 'liked') {
                        $btn.addClass('liked');
                        $svg.attr('fill', 'var(--myavana-coral)');
                    } else {
                        $btn.removeClass('liked');
                        $svg.attr('fill', 'none');
                    }
                }
            }
        });
    });

    /**
     * Handle comment button clicks - Toggle comments section
     */
    $(document).on('click', '.comment-btn', function(e) {
        e.preventDefault();
        const postId = $(this).data('post-id');
        const $commentsSection = $(`#comments-${postId}`);

        if ($commentsSection.is(':visible')) {
            $commentsSection.slideUp(300);
        } else {
            $commentsSection.slideDown(300);
            // Load comments if not already loaded
            if ($commentsSection.find('.myavana-comments-list').children().length === 0) {
                loadComments(postId);
            }
            // Focus on comment input
            $commentsSection.find('.myavana-comment-input').focus();
        }
    });

    /**
     * Load comments for a post
     */
    function loadComments(postId, page = 1, append = false) {
        const $commentsSection = $(`#comments-${postId}`);
        const $commentsList = $commentsSection.find('.myavana-comments-list');
        const perPage = 10; // Load 10 comments at a time

        if (!append) {
            $commentsList.html('<div class="myavana-comments-loading">Loading comments...</div>');
        } else {
            // Show loading state in load more button
            const $loadMoreBtn = $commentsSection.find('.myavana-ci-load-more-comments');
            $loadMoreBtn.prop('disabled', true).text('Loading...');
        }

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_load_comments',
                nonce: settings.nonce,
                post_id: postId,
                page: page,
                per_page: perPage
            },
            success: function(response) {
                if (response.success && response.data) {
                    const { comments, has_more, total_count } = response.data;

                    if (!append) {
                        $commentsList.empty();
                    } else {
                        // Remove loading message if appending
                        $commentsList.find('.myavana-comments-loading').remove();
                    }

                    if (comments.length === 0 && !append) {
                        $commentsList.html('<div class="myavana-comments-empty">Be the first to comment!</div>');
                    } else {
                        comments.forEach(comment => {
                            $commentsList.append(createCommentHTML(comment));
                        });

                        // Handle load more button
                        let $loadMoreBtn = $commentsSection.find('.myavana-ci-load-more-comments');

                        if (has_more) {
                            if ($loadMoreBtn.length === 0) {
                                // Create load more button
                                $loadMoreBtn = $(`
                                    <div class="myavana-ci-load-more-comments-container">
                                        <button class="myavana-ci-load-more-comments" data-post-id="${postId}" data-page="${page + 1}">
                                            Load more comments
                                        </button>
                                    </div>
                                `);
                                $commentsList.after($loadMoreBtn);
                            } else {
                                // Update page number
                                $loadMoreBtn.data('page', page + 1).prop('disabled', false).text('Load more comments');
                            }
                        } else {
                            // No more comments, remove button
                            $loadMoreBtn.remove();
                        }
                    }
                } else {
                    if (!append) {
                        $commentsList.html('<div class="myavana-comments-error">Failed to load comments</div>');
                    } else {
                        showNotification('Failed to load more comments', 'error');
                    }
                }
            },
            error: function() {
                if (!append) {
                    $commentsList.html('<div class="myavana-comments-error">Failed to load comments</div>');
                } else {
                    showNotification('Network error. Please try again.', 'error');
                    const $loadMoreBtn = $commentsSection.find('.myavana-ci-load-more-comments');
                    $loadMoreBtn.prop('disabled', false).text('Load more comments');
                }
            }
        });
    }

    /**
     * Create HTML for a comment
     */
    function createCommentHTML(comment, isReply = false) {
        const isLiked = comment.is_liked ? 'liked' : '';
        const likeFillColor = comment.is_liked ? 'var(--myavana-coral)' : 'none';
        const replyCount = comment.reply_count || 0;
        const avatarUrl = comment.user_avatar || 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="%23e7a690"%3E%3Cpath d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z"/%3E%3C/svg%3E';

        return `
            <div class="myavana-comment ${isReply ? 'myavana-comment-reply' : ''}" data-comment-id="${comment.id}" data-post-id="${comment.post_id || ''}">
                
                <div class="myavana-comment-content">
                    <div class="myavana-comment-header">
                    <img src="${escapeHtml(avatarUrl)}" alt="${escapeHtml(comment.display_name)}" class="myavana-comment-avatar" onerror="this.src='data:image/svg+xml,%3Csvg xmlns=\\'http://www.w3.org/2000/svg\\' viewBox=\\'0 0 24 24\\' fill=\\'%23e7a690\\'%3E%3Cpath d=\\'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3zm0 14.2c-2.5 0-4.71-1.28-6-3.22.03-1.99 4-3.08 6-3.08 1.99 0 5.97 1.09 6 3.08-1.29 1.94-3.5 3.22-6 3.22z\\'/%3E%3C/svg%3E'">
                        <span class="myavana-comment-author">${escapeHtml(comment.display_name)}</span>
                        <span class="myavana-comment-time">${escapeHtml(comment.formatted_date)}</span>
                    </div>
                    <p class="myavana-comment-text">${escapeHtml(comment.content)}</p>
                    <div class="myavana-comment-actions">
                        <button class="myavana-comment-like-btn ${isLiked}" data-comment-id="${comment.id}">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="${likeFillColor}" stroke="currentColor" stroke-width="2">
                                <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path>
                            </svg>
                            <span class="myavana-comment-like-count">${comment.likes_count || 0}</span>
                        </button>
                        ${!isReply ? `<button class="myavana-comment-reply-btn" data-comment-id="${comment.id}">Reply</button>` : ''}
                        ${replyCount > 0 && !isReply ? `
                            <button class="myavana-view-replies-btn" data-comment-id="${comment.id}" data-reply-count="${replyCount}">
                                View ${replyCount} ${replyCount === 1 ? 'reply' : 'replies'}
                            </button>
                        ` : ''}
                        ${comment.user_id != settings.userId ? `
                            <button class="myavana-ci-report-btn" data-content-id="${comment.id}" data-content-type="comment" title="Report comment">Report</button>
                        ` : ''}
                    </div>
                    <div class="myavana-reply-form-container" style="display: none;"></div>
                    <div class="myavana-replies-container" style="display: none;"></div>
                </div>
            </div>
        `;
    }

    /**
     * Handle comment submission
     */
    $(document).on('click', '.myavana-comment-submit', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        const $form = $btn.closest('.myavana-comment-form');
        const $input = $form.find('.myavana-comment-input');
        const content = $input.val().trim();

        if (!content) {
            showNotification('Please enter a comment', 'error');
            return;
        }

        // Disable button and show loading
        $btn.prop('disabled', true).addClass('loading');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'comment_on_post',
                nonce: settings.nonce,
                post_id: postId,
                content: content,
                parent_id: 0
            },
            success: function(response) {
                if (response.success) {
                    // Clear input
                    $input.val('').css('height', 'auto');

                    // Add comment to list
                    const $commentsList = $(`#comments-${postId} .myavana-comments-list`);
                    const $emptyMsg = $commentsList.find('.myavana-comments-empty');

                    if ($emptyMsg.length) {
                        $emptyMsg.remove();
                    }

                    $commentsList.append(createCommentHTML(response.data.comment));

                    // Update comment count
                    const $commentBtn = $(`.comment-btn[data-post-id="${postId}"]`);
                    const $count = $commentBtn.find('.myavana-action-count');
                    const currentCount = parseInt($count.text()) || 0;
                    $count.text(currentCount + 1);

                    showNotification('Comment added!', 'success');
                } else {
                    showNotification(response.data || 'Failed to add comment', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('loading');
            }
        });
    });

    /**
     * Auto-resize comment textarea
     */
    $(document).on('input', '.myavana-comment-input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    /**
     * Handle share button clicks - Open share modal
     */
    $(document).on('click', '.share-btn', function(e) {
        e.preventDefault();
        const postId = $(this).data('post-id');
        const $postCard = $(this).closest('.myavana-post-card');
        const title = $postCard.find('.myavana-post-title').text();
        const content = $postCard.find('.myavana-post-text').text();

        openShareModal(postId, title, content);
    });

    /**
     * Open share modal
     */
    function openShareModal(postId, title, description) {
        const shareUrl = window.location.origin + window.location.pathname + '?post=' + postId;

        // Try native Web Share API first (mobile browsers)
        if (navigator.share) {
            navigator.share({
                title: title,
                text: description,
                url: shareUrl
            }).then(() => {
                trackShare(postId, 'native');
                showNotification('Thanks for sharing!', 'success');
            }).catch((error) => {
                // User cancelled or error occurred, show custom modal
                if (error.name !== 'AbortError') {
                    showCustomShareModal(postId, shareUrl, title, description);
                }
            });
        } else {
            // Fallback to custom share modal
            showCustomShareModal(postId, shareUrl, title, description);
        }
    }

    /**
     * Show custom share modal
     */
    function showCustomShareModal(postId, shareUrl, title, description) {
        const encodedUrl = encodeURIComponent(shareUrl);
        const encodedTitle = encodeURIComponent(title);
        const encodedDesc = encodeURIComponent(description);

        const shareLinks = {
            facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
            twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}&text=${encodedTitle}`,
            linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
            whatsapp: `https://wa.me/?text=${encodedTitle}%20${encodedUrl}`,
            pinterest: `https://pinterest.com/pin/create/button/?url=${encodedUrl}&description=${encodedTitle}`,
            email: `mailto:?subject=${encodedTitle}&body=${encodedDesc}%0A%0A${encodedUrl}`
        };

        const $modal = $(`
            <div class="myavana-share-modal" id="share-modal-${postId}">
                <div class="myavana-modal-overlay"></div>
                <div class="myavana-share-modal-content">
                    <div class="myavana-share-header">
                        <h3>Share this post</h3>
                        <button class="myavana-share-close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="myavana-share-options">
                        <button class="myavana-share-option" data-platform="facebook" data-url="${shareLinks.facebook}">
                            <div class="myavana-share-icon facebook">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                            </div>
                            <span>Facebook</span>
                        </button>
                        <button class="myavana-share-option" data-platform="twitter" data-url="${shareLinks.twitter}">
                            <div class="myavana-share-icon twitter">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z"/></svg>
                            </div>
                            <span>Twitter</span>
                        </button>
                        <button class="myavana-share-option" data-platform="linkedin" data-url="${shareLinks.linkedin}">
                            <div class="myavana-share-icon linkedin">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg>
                            </div>
                            <span>LinkedIn</span>
                        </button>
                        <button class="myavana-share-option" data-platform="whatsapp" data-url="${shareLinks.whatsapp}">
                            <div class="myavana-share-icon whatsapp">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.890-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                            </div>
                            <span>WhatsApp</span>
                        </button>
                        <button class="myavana-share-option" data-platform="pinterest" data-url="${shareLinks.pinterest}">
                            <div class="myavana-share-icon pinterest">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12.017 0C5.396 0 .029 5.367.029 11.987c0 5.079 3.158 9.417 7.618 11.162-.105-.949-.199-2.403.041-3.439.219-.937 1.406-5.957 1.406-5.957s-.359-.72-.359-1.781c0-1.663.967-2.911 2.168-2.911 1.024 0 1.518.769 1.518 1.688 0 1.029-.653 2.567-.992 3.992-.285 1.193.6 2.165 1.775 2.165 2.128 0 3.768-2.245 3.768-5.487 0-2.861-2.063-4.869-5.008-4.869-3.41 0-5.409 2.562-5.409 5.199 0 1.033.394 2.143.889 2.741.099.12.112.225.085.345-.09.375-.293 1.199-.334 1.363-.053.225-.172.271-.401.165-1.495-.69-2.433-2.878-2.433-4.646 0-3.776 2.748-7.252 7.92-7.252 4.158 0 7.392 2.967 7.392 6.923 0 4.135-2.607 7.462-6.233 7.462-1.214 0-2.354-.629-2.758-1.379l-.749 2.848c-.269 1.045-1.004 2.352-1.498 3.146 1.123.345 2.306.535 3.55.535 6.607 0 11.985-5.365 11.985-11.987C23.97 5.39 18.592.026 11.985.026L12.017 0z"/></svg>
                            </div>
                            <span>Pinterest</span>
                        </button>
                        <button class="myavana-share-option" data-platform="email" data-url="${shareLinks.email}">
                            <div class="myavana-share-icon email">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path><polyline points="22,6 12,13 2,6"></polyline></svg>
                            </div>
                            <span>Email</span>
                        </button>
                    </div>
                    <div class="myavana-share-link">
                        <label>Or copy link</label>
                        <div class="myavana-copy-link-wrapper">
                            <input type="text" readonly value="${shareUrl}" class="myavana-copy-link-input">
                            <button class="myavana-copy-link-btn" data-url="${shareUrl}">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                    <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                                </svg>
                                Copy
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `);

        $('body').append($modal);
        setTimeout(() => $modal.addClass('active'), 10);

        // Close modal handlers
        const closeShareModal = () => {
            $modal.removeClass('active');
            setTimeout(() => $modal.remove(), 300);
        };

        $modal.find('.myavana-share-close').on('click', function(e) {
            e.preventDefault();
            closeShareModal();
        });

        $modal.find('.myavana-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeShareModal();
            }
        });

        // Share option clicks
        $modal.find('.myavana-share-option').on('click', function() {
            const platform = $(this).data('platform');
            const url = $(this).data('url');

            window.open(url, '_blank', 'width=600,height=400');
            trackShare(postId, platform);
            showNotification('Sharing to ' + platform + '...', 'success');
        });

        // Copy link
        $modal.find('.myavana-copy-link-btn').on('click', function() {
            const url = $(this).data('url');
            const $input = $modal.find('.myavana-copy-link-input');

            $input.select();
            document.execCommand('copy');

            $(this).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg> Copied!');

            trackShare(postId, 'copy_link');
            showNotification('Link copied to clipboard!', 'success');

            setTimeout(() => {
                $(this).html('<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path></svg> Copy');
            }, 2000);
        });
    }

    /**
     * Track share analytics
     */
    function trackShare(postId, platform) {
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'track_post_share',
                nonce: settings.nonce,
                post_id: postId,
                platform: platform
            }
        });
    }

    /**
     * Handle bookmark button clicks - Long press for collections, quick click for default save
     */
    let bookmarkPressTimer = null;
    let bookmarkPickerShown = false;
    let bookmarkTouchHandledAt = 0;

    function toggleDefaultBookmark($btn, postId) {
        const $svg = $btn.find('svg');

        $btn.prop('disabled', true);

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'bookmark_post',
                nonce: settings.nonce,
                post_id: postId,
                collection: 'saved'
            },
            success: function(response) {
                if (response.success) {
                    const action = response.data && response.data.action ? response.data.action : '';
                    const message = response.data && response.data.message ? response.data.message : 'Saved state updated.';

                    if (action === 'bookmarked') {
                        $btn.addClass('bookmarked');
                        $svg.attr('fill', 'var(--myavana-coral)');
                        showNotification(message, 'success');
                    } else {
                        $btn.removeClass('bookmarked');
                        $svg.attr('fill', 'none');
                        showNotification(message, 'info');
                    }
                } else {
                    showNotification(response.data || 'Failed to save post', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    }

    $(document).on('mousedown touchstart', '.bookmark-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const postId = $btn.data('post-id');

        bookmarkPickerShown = false;

        // Long press to show collections modal
        bookmarkPressTimer = setTimeout(function() {
            bookmarkPickerShown = true;
            openCollectionsModal(postId);
        }, 500); // 500ms long press
    });

    $(document).on('mouseup mouseleave touchend touchcancel', '.bookmark-btn', function(e) {
        clearTimeout(bookmarkPressTimer);

        // If it was a quick click (not long press), toggle default bookmark
        if (!bookmarkPickerShown && (e.type === 'mouseup' || e.type === 'touchend')) {
            // Ignore synthetic mouseup that often follows touchend on mobile
            if (e.type === 'mouseup' && Date.now() - bookmarkTouchHandledAt < 450) {
                return;
            }

            const $btn = $(this);
            const postId = $btn.data('post-id');
            if (e.type === 'touchend') {
                bookmarkTouchHandledAt = Date.now();
            }

            toggleDefaultBookmark($btn, postId);
        }
    });

    /**
     * Open collections modal for saving posts
     */
    function openCollectionsModal(postId) {
        // Fetch user's collections first
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_manage_collection',
                nonce: settings.nonce,
                action_type: 'list',
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    showCollectionsModal(postId, response.data.collections || []);
                } else {
                    showNotification('Failed to load collections', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Show collections modal
     */
    function showCollectionsModal(postId, collections) {
        const collectionsHTML = collections.map(col => `
            <label class="myavana-ci-collection-item">
                <input type="checkbox" name="collection" value="${col.id}" ${Number(col.has_post) === 1 ? 'checked' : ''}>
                <span>${escapeHtml(col.name)}</span>
                <span class="myavana-ci-collection-count">(${col.post_count || 0})</span>
            </label>
        `).join('');

        const $modal = $(`
            <div class="myavana-ci-collections-modal active" id="collections-modal-${postId}">
                <div class="myavana-modal-overlay"></div>
                <div class="myavana-ci-collections-modal-content">
                    <div class="myavana-ci-collections-header">
                        <h3>Save to Collection</h3>
                        <button class="myavana-ci-collections-close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="myavana-ci-collections-body">
                        <div class="myavana-ci-collections-list">
                            ${collectionsHTML || '<p class="myavana-ci-no-collections">No collections yet. Create one below!</p>'}
                        </div>
                        <div class="myavana-ci-create-collection">
                            <input type="text" class="myavana-ci-collection-name-input" placeholder="New collection name" maxlength="50">
                            <button class="myavana-ci-create-collection-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Create
                            </button>
                        </div>
                    </div>
                    <div class="myavana-ci-collections-footer">
                        <button class="myavana-btn-secondary myavana-ci-collections-cancel">Cancel</button>
                        <button class="myavana-btn-primary myavana-ci-collections-save" data-post-id="${postId}">Save</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append($modal).css('overflow', 'hidden');

        // Close handlers
        const closeCollectionsModal = () => {
            $modal.removeClass('active');
            setTimeout(() => {
                $modal.remove();
                $('body').css('overflow', '');
            }, 300);
        };

        $modal.find('.myavana-ci-collections-close, .myavana-ci-collections-cancel').on('click', function(e) {
            e.preventDefault();
            closeCollectionsModal();
        });

        $modal.find('.myavana-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeCollectionsModal();
            }
        });

        // Create collection handler
        $modal.find('.myavana-ci-create-collection-btn').on('click', function() {
            const $input = $modal.find('.myavana-ci-collection-name-input');
            const name = $input.val().trim();

            if (!name) {
                showNotification('Please enter a collection name', 'error');
                return;
            }

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'myavana_ci_manage_collection',
                    nonce: settings.nonce,
                    action_type: 'create',
                    collection_name: name
                },
                success: function(response) {
                    if (response.success) {
                        const newCollection = {
                            id: response.data.collection_id,
                            name: response.data.name
                        };
                        const $list = $modal.find('.myavana-ci-collections-list');
                        $list.find('.myavana-ci-no-collections').remove();
                        $list.append(`
                            <label class="myavana-ci-collection-item">
                                <input type="checkbox" name="collection" value="${newCollection.id}">
                                <span>${escapeHtml(newCollection.name)}</span>
                                <span class="myavana-ci-collection-count">(0)</span>
                            </label>
                        `);
                        $input.val('');
                        showNotification('Collection created!', 'success');
                    } else {
                        showNotification(response.data || 'Failed to create collection', 'error');
                    }
                },
                error: function() {
                    showNotification('Network error. Please try again.', 'error');
                }
            });
        });

        // Save to collections handler
        $modal.find('.myavana-ci-collections-save').on('click', function() {
            const $saveBtn = $(this);
            const selectedCollections = [];
            $modal.find('input[name="collection"]:checked').each(function() {
                selectedCollections.push($(this).val());
            });

            $saveBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'myavana_ci_manage_collection',
                    nonce: settings.nonce,
                    action_type: 'add_post',
                    post_id: postId,
                    collection_ids: selectedCollections.join(',')
                },
                success: function(response) {
                    if (response.success) {
                        const collectionMessage = response.data && response.data.message
                            ? response.data.message
                            : 'Collections updated!';
                        showNotification(collectionMessage, 'success');
                        $modal.removeClass('active');
                        setTimeout(() => {
                            $modal.remove();
                            $('body').css('overflow', '');
                        }, 300);
                    } else {
                        showNotification(response.data || 'Failed to save to collections', 'error');
                        $saveBtn.prop('disabled', false).text('Save');
                    }
                },
                error: function() {
                    showNotification('Network error. Please try again.', 'error');
                    $saveBtn.prop('disabled', false).text('Save');
                }
            });
        });
    }

    /**
     * Handle edit post
     */
    $(document).on('click', '.myavana-post-edit-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const postId = Number($(this).data('post-id') || 0);
        if (!postId) {
            showNotification('Invalid post selected.', 'error');
            return;
        }

        const cachedPost = postCache.get(postId) || {};
        const $postCard = $(`.myavana-post-card[data-post-id="${postId}"]`);
        const postData = mergePostData({
            id: postId,
            title: normalizeCommunityText(cachedPost.title || $postCard.find('.myavana-post-title').text().trim()),
            content: normalizeCommunityText(cachedPost.content || $postCard.find('.myavana-post-text').text().trim()),
            image_url: cachedPost.image_url || $postCard.find('.myavana-post-image').attr('src') || '',
            video_url: cachedPost.video_url || $postCard.find('.myavana-post-video source').attr('src') || '',
            post_type: cachedPost.post_type || 'general',
            privacy_level: cachedPost.privacy_level || 'public',
            hashtags: cachedPost.hashtags || ''
        }, cachedPost);

        $('#myavana-edit-post-modal').remove();

        const postTypeOptions = [
            { value: 'progress', label: 'Progress Update' },
            { value: 'transformation', label: 'Before & After' },
            { value: 'routine', label: 'Routine Share' },
            { value: 'products', label: 'Product Review' },
            { value: 'tips', label: 'Tips & Advice' },
            { value: 'video', label: 'Video Update' },
            { value: 'general', label: 'General' }
        ];

        const postTypeSelectHtml = postTypeOptions.map((option) => `
            <option value="${option.value}" ${postData.post_type === option.value ? 'selected' : ''}>${option.label}</option>
        `).join('');

        const hashtagsValue = String(postData.hashtags || '')
            .split(',')
            .map((tag) => tag.trim().replace(/^#+/, ''))
            .filter((tag) => tag.length > 1)
            .join(', ');

        const $modal = $(`
            <div class="myavana-modal myavana-edit-post-modal active" id="myavana-edit-post-modal">
                <div class="myavana-modal-overlay"></div>
                <div class="myavana-modal-content myavana-edit-post-modal-content">
                    <div class="myavana-modal-header">
                        <h2 class="myavana-subheader">Edit Post</h2>
                        <button class="myavana-modal-close myavana-edit-close" aria-label="Close edit post modal">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="myavana-modal-body">
                        <form id="myavana-edit-post-form" novalidate>
                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="myavana-edit-post-title">Title</label>
                                <input type="text" id="myavana-edit-post-title" class="myavana-form-input" maxlength="200" value="${escapeHtml(postData.title || '')}" required>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="myavana-edit-post-content">Story</label>
                                <textarea id="myavana-edit-post-content" class="myavana-form-textarea" rows="5" maxlength="5000" required>${escapeHtml(postData.content || '')}</textarea>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="myavana-edit-post-hashtags">Hashtags</label>
                                <input type="text" id="myavana-edit-post-hashtags" class="myavana-form-input" value="${escapeHtml(hashtagsValue)}" placeholder="growth, naturalhair, washday">
                            </div>

                            <div class="myavana-edit-post-meta-grid">
                                <div class="myavana-form-group">
                                    <label class="myavana-form-label" for="myavana-edit-post-type">Post Type</label>
                                    <select id="myavana-edit-post-type" class="myavana-form-select">
                                        ${postTypeSelectHtml}
                                    </select>
                                </div>
                                <div class="myavana-form-group">
                                    <label class="myavana-form-label">Privacy</label>
                                    <div class="myavana-edit-privacy-segmented">
                                        <label class="myavana-edit-privacy-option">
                                            <input type="radio" name="myavana-edit-privacy" value="public" ${postData.privacy_level === 'public' ? 'checked' : ''}>
                                            <span>Public</span>
                                        </label>
                                        <label class="myavana-edit-privacy-option">
                                            <input type="radio" name="myavana-edit-privacy" value="followers" ${postData.privacy_level === 'followers' ? 'checked' : ''}>
                                            <span>Followers</span>
                                        </label>
                                        <label class="myavana-edit-privacy-option">
                                            <input type="radio" name="myavana-edit-privacy" value="private" ${postData.privacy_level === 'private' ? 'checked' : ''}>
                                            <span>Private</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label">Photo</label>
                                <div class="myavana-upload-area myavana-edit-upload-area" id="myavana-edit-upload-area">
                                    <input type="file" id="myavana-edit-post-image" accept="image/*" style="display: none;">
                                    <div class="myavana-upload-prompt">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--myavana-coral)" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                            <polyline points="21 15 16 10 5 21"></polyline>
                                        </svg>
                                        <p class="myavana-body">Tap to replace or add a photo</p>
                                    </div>
                                    <div class="myavana-upload-preview" id="myavana-edit-upload-preview" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="myavana-edit-post-video-url">Video URL</label>
                                <input type="url" id="myavana-edit-post-video-url" class="myavana-form-input" value="${escapeHtml(postData.video_url || '')}" placeholder="https://example.com/video.mp4">
                                <div class="myavana-upload-area myavana-upload-area-video myavana-edit-upload-area" id="myavana-edit-video-upload-area">
                                    <input type="file" id="myavana-edit-post-video" accept="video/*" style="display: none;">
                                    <div class="myavana-upload-prompt">
                                        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--myavana-coral)" stroke-width="2">
                                            <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                            <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                        </svg>
                                        <p class="myavana-body">Upload or replace video</p>
                                    </div>
                                    <div class="myavana-upload-preview" id="myavana-edit-video-upload-preview" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="myavana-modal-footer">
                                <button type="button" class="myavana-btn-secondary myavana-edit-cancel">Cancel</button>
                                <button type="submit" class="myavana-btn-primary myavana-edit-submit">Save Changes</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `);

        $('body').append($modal).css('overflow', 'hidden');

        const $form = $modal.find('#myavana-edit-post-form');
        const $titleInput = $modal.find('#myavana-edit-post-title');
        const $contentInput = $modal.find('#myavana-edit-post-content');
        const $hashtagsInput = $modal.find('#myavana-edit-post-hashtags');
        const $postTypeInput = $modal.find('#myavana-edit-post-type');
        const $privacyInputs = $modal.find('input[name="myavana-edit-privacy"]');

        const $imageUploadArea = $modal.find('#myavana-edit-upload-area');
        const $imageFileInput = $modal.find('#myavana-edit-post-image');
        const $imagePreview = $modal.find('#myavana-edit-upload-preview');

        const $videoUploadArea = $modal.find('#myavana-edit-video-upload-area');
        const $videoFileInput = $modal.find('#myavana-edit-post-video');
        const $videoUrlInput = $modal.find('#myavana-edit-post-video-url');
        const $videoPreview = $modal.find('#myavana-edit-video-upload-preview');
        const $saveBtn = $modal.find('.myavana-edit-submit');

        let removeImage = false;
        let removeVideo = false;
        let videoObjectUrl = '';
        const existingImageUrl = String(postData.image_url || '').trim();
        const existingVideoUrl = String(postData.video_url || '').trim();

        const closeEditModal = () => {
            if (videoObjectUrl) {
                URL.revokeObjectURL(videoObjectUrl);
                videoObjectUrl = '';
            }
            $modal.removeClass('active');
            setTimeout(() => {
                $modal.remove();
                $('body').css('overflow', '');
            }, 200);
        };

        function renderImagePreview(source, label = '') {
            if (!source) {
                $imagePreview.hide().empty();
                $imageUploadArea.find('.myavana-upload-prompt').show();
                return;
            }

            $imagePreview.html(`
                <img src="${escapeHtml(source)}" alt="Image preview">
                ${label ? `<span class="myavana-edit-media-chip">${escapeHtml(label)}</span>` : ''}
                <button type="button" class="myavana-remove-image myavana-edit-remove-media" aria-label="Remove image">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `).show();
            $imageUploadArea.find('.myavana-upload-prompt').hide();
        }

        function renderVideoPreview(source, label = '') {
            if (!source) {
                $videoPreview.hide().empty();
                $videoUploadArea.find('.myavana-upload-prompt').show();
                return;
            }

            $videoPreview.html(`
                <video src="${escapeHtml(source)}" controls preload="metadata" playsinline></video>
                ${label ? `<span class="myavana-edit-media-chip">${escapeHtml(label)}</span>` : ''}
                <button type="button" class="myavana-remove-video myavana-edit-remove-media" aria-label="Remove video">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            `).show();
            $videoUploadArea.find('.myavana-upload-prompt').hide();
        }

        if (existingImageUrl) {
            renderImagePreview(existingImageUrl, 'Current Photo');
        }
        if (existingVideoUrl) {
            renderVideoPreview(existingVideoUrl, 'Current Video');
        }

        $modal.find('.myavana-edit-close, .myavana-edit-cancel').on('click', function(evt) {
            evt.preventDefault();
            closeEditModal();
        });

        $modal.find('.myavana-modal-overlay').on('click', function(evt) {
            if (evt.target === this) {
                closeEditModal();
            }
        });

        $imageUploadArea.on('click', function(evt) {
            if (evt.target !== $imageFileInput[0]) {
                evt.preventDefault();
                $imageFileInput.trigger('click');
            }
        });

        $imageFileInput.on('change', function(evt) {
            const file = evt.target.files && evt.target.files[0];
            if (!file) {
                return;
            }
            if (!file.type.startsWith('image/')) {
                showNotification('Please choose a valid image file.', 'error');
                this.value = '';
                return;
            }

            removeImage = false;
            const reader = new FileReader();
            reader.onload = function(loadEvent) {
                renderImagePreview(loadEvent.target.result, 'New Photo');
            };
            reader.readAsDataURL(file);
        });

        $imagePreview.on('click', '.myavana-remove-image', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();

            const hasReplacementFile = !!($imageFileInput[0].files && $imageFileInput[0].files.length > 0);
            if (hasReplacementFile && existingImageUrl) {
                $imageFileInput.val('');
                removeImage = false;
                renderImagePreview(existingImageUrl, 'Current Photo');
                return;
            }

            removeImage = true;
            $imageFileInput.val('');
            renderImagePreview('');
        });

        $videoUploadArea.on('click', function(evt) {
            if (evt.target !== $videoFileInput[0]) {
                evt.preventDefault();
                $videoFileInput.trigger('click');
            }
        });

        $videoFileInput.on('change', function(evt) {
            const file = evt.target.files && evt.target.files[0];
            if (!file) {
                return;
            }
            if (!file.type.startsWith('video/')) {
                showNotification('Please choose a valid video file.', 'error');
                this.value = '';
                return;
            }

            if (videoObjectUrl) {
                URL.revokeObjectURL(videoObjectUrl);
            }
            videoObjectUrl = URL.createObjectURL(file);
            removeVideo = false;
            $videoUrlInput.val('');
            renderVideoPreview(videoObjectUrl, 'New Video');
            $postTypeInput.val('video');
        });

        $videoPreview.on('click', '.myavana-remove-video', function(evt) {
            evt.preventDefault();
            evt.stopPropagation();

            const hasReplacementFile = !!($videoFileInput[0].files && $videoFileInput[0].files.length > 0);
            if (hasReplacementFile && existingVideoUrl) {
                if (videoObjectUrl) {
                    URL.revokeObjectURL(videoObjectUrl);
                    videoObjectUrl = '';
                }
                $videoFileInput.val('');
                removeVideo = false;
                $videoUrlInput.val(existingVideoUrl);
                renderVideoPreview(existingVideoUrl, 'Current Video');
                return;
            }

            removeVideo = true;
            if (videoObjectUrl) {
                URL.revokeObjectURL(videoObjectUrl);
                videoObjectUrl = '';
            }
            $videoFileInput.val('');
            $videoUrlInput.val('');
            renderVideoPreview('');
        });

        $videoUrlInput.on('input', function() {
            const value = ($(this).val() || '').trim();
            if (value) {
                if (videoObjectUrl) {
                    URL.revokeObjectURL(videoObjectUrl);
                    videoObjectUrl = '';
                }
                $videoFileInput.val('');
                removeVideo = false;
                renderVideoPreview(value, 'Video URL');
                $postTypeInput.val('video');
                return;
            }

            if (!removeVideo && !$videoFileInput[0].files.length && existingVideoUrl) {
                renderVideoPreview(existingVideoUrl, 'Current Video');
                return;
            }
            renderVideoPreview('');
        });

        $form.on('submit', function(evt) {
            evt.preventDefault();

            const title = normalizeCommunityText($titleInput.val() || '').trim();
            const content = normalizeCommunityText($contentInput.val() || '').trim();
            const postType = ($postTypeInput.val() || 'general').trim();
            const privacyLevel = ($privacyInputs.filter(':checked').val() || 'public').trim();
            const hashtags = ($hashtagsInput.val() || '').trim();
            const videoUrl = ($videoUrlInput.val() || '').trim();
            const hasImageFile = !!($imageFileInput[0].files && $imageFileInput[0].files.length > 0);
            const hasVideoFile = !!($videoFileInput[0].files && $videoFileInput[0].files.length > 0);
            const hasKeptExistingVideo = !!(!removeVideo && !hasVideoFile && !videoUrl && existingVideoUrl);

            if (!title || !content) {
                showNotification('Title and story are required.', 'error');
                return;
            }

            if (postType === 'video' && !hasVideoFile && !videoUrl && !hasKeptExistingVideo) {
                showNotification('Video posts require a video URL or uploaded video.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'myavana_ci_edit_post');
            formData.append('nonce', settings.nonce);
            formData.append('post_id', postId);
            formData.append('title', title);
            formData.append('content', content);
            formData.append('post_type', postType);
            formData.append('privacy_level', privacyLevel);
            formData.append('hashtags', hashtags);
            formData.append('video_url', videoUrl);
            formData.append('remove_image', removeImage ? '1' : '0');
            formData.append('remove_video', removeVideo ? '1' : '0');

            if (hasImageFile) {
                formData.append('image', $imageFileInput[0].files[0]);
            }
            if (hasVideoFile) {
                formData.append('video', $videoFileInput[0].files[0]);
            }

            $saveBtn.prop('disabled', true).text('Saving...');

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (!response.success) {
                        showNotification(response.data || 'Failed to update post', 'error');
                        return;
                    }

                    const responsePost = response.data && response.data.post ? response.data.post : {};
                    responsePost.id = postId;
                    const updatedPost = upsertPostCard(mergePostData(responsePost, postData));

                    if (!updatedPost) {
                        currentPage = 1;
                        loadPosts(false);
                    }

                    loadDiscoveryData();
                    closeEditModal();
                    showNotification((response.data && response.data.message) || 'Post updated successfully', 'success');
                },
                error: function() {
                    showNotification('Network error. Please try again.', 'error');
                },
                complete: function() {
                    $saveBtn.prop('disabled', false).text('Save Changes');
                }
            });
        });
    });

    /**
     * Handle pin post toggle
     */
    $(document).on('click', '.myavana-ci-pin-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        const isPinned = $btn.hasClass('pinned');
        const $svg = $btn.find('svg');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_edit_post',
                nonce: settings.nonce,
                post_id: postId,
                is_pinned: isPinned ? 0 : 1
            },
            success: function(response) {
                if (response.success) {
                    if (isPinned) {
                        $btn.removeClass('pinned').attr('title', 'Pin post');
                        $svg.attr('fill', 'none');
                        showNotification('Post unpinned', 'info');
                    } else {
                        $btn.addClass('pinned').attr('title', 'Unpin post');
                        $svg.attr('fill', 'var(--myavana-coral)');
                        showNotification('Post pinned to top!', 'success');
                    }
                } else {
                    showNotification(response.data || 'Failed to pin post', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            }
        });
    });

    /**
     * Handle delete post
     */
    $(document).on('click', '.myavana-post-delete-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const postId = $(this).data('post-id');
        const $postCard = $(this).closest('.myavana-post-card');

        if (!confirm('Are you sure you want to delete this post? This action cannot be undone.')) {
            return;
        }

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_delete_post',
                nonce: settings.nonce,
                post_id: postId
            },
            success: function(response) {
                if (response.success) {
                    $postCard.fadeOut(300, function() {
                        $(this).remove();
                    });
                    showNotification('Post deleted successfully', 'success');
                } else {
                    showNotification(response.data || 'Failed to delete post', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            }
        });
    });

    /**
     * Handle comment like
     */
    $(document).on('click', '.myavana-comment-like-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const commentId = $btn.data('comment-id');
        const $svg = $btn.find('svg');
        const $count = $btn.find('.myavana-comment-like-count');

        $btn.prop('disabled', true);

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_like_comment',
                nonce: settings.nonce,
                comment_id: commentId
            },
            success: function(response) {
                if (response.success) {
                    const {action, likes_count} = response.data;

                    if (action === 'liked') {
                        $btn.addClass('liked');
                        $svg.attr('fill', 'var(--myavana-coral)');
                    } else {
                        $btn.removeClass('liked');
                        $svg.attr('fill', 'none');
                    }

                    $count.text(likes_count || 0);
                } else {
                    showNotification(response.data || 'Failed to like comment', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    /**
     * Handle reaction button click - Show reactions picker on long press, quick react on click
     */
    let reactionPressTimer = null;
    let reactionPickerShown = false;

    $(document).on('mousedown touchstart', '.myavana-ci-react-btn', function(e) {
        e.preventDefault();
        const $btn = $(this);
        const $picker = $btn.siblings('.myavana-reactions-picker');

        reactionPickerShown = false;

        // Long press to show reactions picker
        reactionPressTimer = setTimeout(function() {
            reactionPickerShown = true;
            // Hide all other pickers
            $('.myavana-reactions-picker').removeClass('show');
            // Show this picker
            $picker.addClass('show');
        }, 500); // 500ms long press
    });

    $(document).on('mouseup mouseleave touchend touchcancel', '.myavana-ci-react-btn', function(e) {
        clearTimeout(reactionPressTimer);

        // If it was a quick click (not long press), toggle current reaction
        if (!reactionPickerShown && e.type === 'mouseup') {
            const $btn = $(this);
            const postId = $btn.data('post-id');
            const currentReaction = $btn.data('user-reaction');

            // If user already has a reaction, remove it; otherwise add default 'like'
            if (currentReaction) {
                reactToPost(postId, currentReaction, true); // Remove reaction
            } else {
                reactToPost(postId, 'like', false); // Add like reaction
            }
        }
    });

    /**
     * Handle reaction option selection
     */
    $(document).on('click', '.myavana-reaction-option', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $option = $(this);
        const reactionType = $option.data('reaction');
        const $picker = $option.closest('.myavana-reactions-picker');
        const postId = $picker.data('post-id');

        // Hide picker
        $picker.removeClass('show');

        // Add reaction
        reactToPost(postId, reactionType, false);
    });

    /**
     * Close reactions picker when clicking outside
     */
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.myavana-ci-react-btn, .myavana-reactions-picker').length) {
            $('.myavana-reactions-picker').removeClass('show');
        }
    });

    /**
     * React to a post
     */
    function reactToPost(postId, reactionType, removeReaction = false) {
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_react_to_post',
                nonce: settings.nonce,
                post_id: postId,
                reaction_type: reactionType,
                remove: removeReaction
            },
            success: function(response) {
                if (response.success) {
                    const { reactions, user_reaction } = response.data;

                    // Update the post card UI
                    updatePostReactions(postId, reactions, user_reaction);

                    // Show notification
                    if (!removeReaction) {
                        const reactionNames = {
                            'like': 'liked',
                            'love': 'loved',
                            'celebrate': 'celebrated',
                            'insightful': 'found this insightful'
                        };
                        showNotification(`You ${reactionNames[reactionType]} this post!`, 'success');
                    }
                } else {
                    showNotification(response.data || 'Failed to react to post', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Update post reactions UI
     */
    function updatePostReactions(postId, reactions, userReaction) {
        const $postCard = $(`.myavana-post-card[data-post-id="${postId}"]`);
        const $reactBtn = $postCard.find('.myavana-ci-react-btn');
        const $reactionCounts = $postCard.find('.myavana-reaction-counts');

        const reactionEmojis = {
            'like': '❤️',
            'love': '😍',
            'celebrate': '🎉',
            'insightful': '💡'
        };

        // Calculate total reactions
        const totalReactions = Object.values(reactions).reduce((sum, count) => sum + count, 0);

        // Update reaction button
        $reactBtn.data('user-reaction', userReaction || '');
        $reactBtn.find('.reaction-display').text(userReaction ? reactionEmojis[userReaction] : '❤️');
        $reactBtn.find('.myavana-action-count').text(totalReactions || 0);

        if (userReaction) {
            $reactBtn.addClass('reacted');
        } else {
            $reactBtn.removeClass('reacted');
        }

        // Update reaction counts display
        if (totalReactions > 0) {
            const reactionItems = Object.entries(reactions)
                .filter(([type, count]) => count > 0)
                .map(([type, count]) => `
                    <div class="myavana-reaction-count-item" data-reaction="${type}">
                        <span class="reaction-emoji">${reactionEmojis[type]}</span>
                        <span class="reaction-count">${count}</span>
                    </div>
                `).join('');

            if ($reactionCounts.length) {
                $reactionCounts.html(reactionItems);
            } else {
                // Insert reaction counts before post actions
                $postCard.find('.myavana-post-actions').before(`
                    <div class="myavana-reaction-counts">
                        ${reactionItems}
                    </div>
                `);
            }
        } else {
            // Remove reaction counts if no reactions
            $reactionCounts.remove();
        }
    }

    /**
     * Handle comment reply button click
     */
    $(document).on('click', '.myavana-comment-reply-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const commentId = $btn.data('comment-id');
        const $comment = $btn.closest('.myavana-comment');
        const $replyFormContainer = $comment.find('.myavana-reply-form-container').first();

        // If form already exists, just toggle it
        if ($replyFormContainer.children().length > 0) {
            $replyFormContainer.toggle();
            return;
        }

        // Create reply form
        const $replyForm = $(`
            <div class="myavana-comment-reply-form">
                <div class="myavana-comment-input-wrapper">
                    <textarea class="myavana-reply-input" placeholder="Write a reply..." rows="1"></textarea>
                    <div class="myavana-reply-actions">
                        <button class="myavana-reply-cancel-btn" data-comment-id="${commentId}">Cancel</button>
                        <button class="myavana-reply-submit-btn" data-comment-id="${commentId}">Reply</button>
                    </div>
                </div>
            </div>
        `);

        $replyFormContainer.html($replyForm).show();
        $replyFormContainer.find('.myavana-reply-input').focus();
    });

    /**
     * Handle reply cancel
     */
    $(document).on('click', '.myavana-reply-cancel-btn', function(e) {
        e.preventDefault();
        $(this).closest('.myavana-reply-form-container').hide();
    });

    /**
     * Handle reply submission
     */
    $(document).on('click', '.myavana-reply-submit-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const parentCommentId = $btn.data('comment-id');
        const $form = $btn.closest('.myavana-comment-reply-form');
        const $input = $form.find('.myavana-reply-input');
        const content = $input.val().trim();

        const $comment = $btn.closest('.myavana-comment');
        const postId = $comment.data('post-id');

        if (!content) {
            showNotification('Please enter a reply', 'error');
            return;
        }

        $btn.prop('disabled', true).text('Posting...');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_reply_to_comment',
                nonce: settings.nonce,
                post_id: postId,
                parent_comment_id: parentCommentId,
                content: content
            },
            success: function(response) {
                if (response.success) {
                    // Hide reply form
                    $form.closest('.myavana-reply-form-container').hide();

                    // Add reply to replies container
                    const $repliesContainer = $comment.find('.myavana-replies-container').first();
                    const replyHTML = createCommentHTML(response.data.comment, true);

                    if ($repliesContainer.is(':hidden')) {
                        $repliesContainer.html(replyHTML).show();
                    } else {
                        $repliesContainer.append(replyHTML);
                    }

                    // Update "View replies" button or create one
                    let $viewRepliesBtn = $comment.find('.myavana-view-replies-btn').first();
                    const currentCount = parseInt($viewRepliesBtn.data('reply-count') || 0) + 1;

                    if ($viewRepliesBtn.length) {
                        $viewRepliesBtn.data('reply-count', currentCount);
                        $viewRepliesBtn.text(`View ${currentCount} ${currentCount === 1 ? 'reply' : 'replies'}`);
                    } else {
                        // Add view replies button
                        const $actions = $comment.find('.myavana-comment-actions').first();
                        $actions.append(`
                            <button class="myavana-view-replies-btn" data-comment-id="${parentCommentId}" data-reply-count="${currentCount}">
                                View ${currentCount} ${currentCount === 1 ? 'reply' : 'replies'}
                            </button>
                        `);
                    }

                    showNotification('Reply added!', 'success');
                } else {
                    showNotification(response.data || 'Failed to add reply', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false).text('Reply');
            }
        });
    });

    /**
     * Handle view replies button click
     */
    $(document).on('click', '.myavana-view-replies-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const commentId = $btn.data('comment-id');
        const $comment = $btn.closest('.myavana-comment');
        const $repliesContainer = $comment.find('.myavana-replies-container').first();

        // If replies are already loaded, just toggle visibility
        if ($repliesContainer.children().length > 0) {
            $repliesContainer.toggle();
            $btn.text($repliesContainer.is(':visible') ? 'Hide replies' : `View ${$btn.data('reply-count')} replies`);
            return;
        }

        // Load replies from server
        $btn.prop('disabled', true).text('Loading...');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_get_replies',
                nonce: settings.nonce,
                comment_id: commentId
            },
            success: function(response) {
                if (response.success && response.data.replies) {
                    const replies = response.data.replies;

                    if (replies.length > 0) {
                        const repliesHTML = replies.map(reply => createCommentHTML(reply, true)).join('');
                        $repliesContainer.html(repliesHTML).show();
                        $btn.text('Hide replies');
                    } else {
                        showNotification('No replies found', 'info');
                    }
                } else {
                    showNotification('Failed to load replies', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    /**
     * Auto-resize reply textarea
     */
    $(document).on('input', '.myavana-reply-input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    });

    /**
     * Handle load more comments button click
     */
    $(document).on('click', '.myavana-ci-load-more-comments', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        const page = $btn.data('page');

        loadComments(postId, page, true);
    });

    /**
     * Handle report button click
     */
    $(document).on('click', '.myavana-ci-report-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();

        const $btn = $(this);
        const contentId = $btn.data('content-id') || $btn.data('post-id');
        const contentType = $btn.data('content-type');

        openReportModal(contentId, contentType);
    });

    /**
     * Open report modal
     */
    function openReportModal(contentId, contentType) {
        const $modal = $(`
            <div class="myavana-ci-report-modal active" id="report-modal-${contentId}">
                <div class="myavana-modal-overlay"></div>
                <div class="myavana-ci-report-modal-content">
                    <div class="myavana-ci-report-header">
                        <h3>Report ${contentType === 'post' ? 'Post' : 'Comment'}</h3>
                        <button class="myavana-ci-report-close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="myavana-ci-report-body">
                        <p>Why are you reporting this ${contentType}?</p>
                        <div class="myavana-ci-report-reasons">
                            <label class="myavana-ci-report-reason">
                                <input type="radio" name="report_reason" value="spam" required>
                                <span>Spam or misleading</span>
                            </label>
                            <label class="myavana-ci-report-reason">
                                <input type="radio" name="report_reason" value="harassment" required>
                                <span>Harassment or hate speech</span>
                            </label>
                            <label class="myavana-ci-report-reason">
                                <input type="radio" name="report_reason" value="inappropriate" required>
                                <span>Inappropriate content</span>
                            </label>
                            <label class="myavana-ci-report-reason">
                                <input type="radio" name="report_reason" value="false_information" required>
                                <span>False information</span>
                            </label>
                            <label class="myavana-ci-report-reason">
                                <input type="radio" name="report_reason" value="other" required>
                                <span>Other</span>
                            </label>
                        </div>
                        <textarea class="myavana-ci-report-details" placeholder="Additional details (optional)" rows="3" maxlength="500"></textarea>
                    </div>
                    <div class="myavana-ci-report-footer">
                        <button class="myavana-btn-secondary myavana-ci-report-cancel">Cancel</button>
                        <button class="myavana-btn-primary myavana-ci-report-submit" data-content-id="${contentId}" data-content-type="${contentType}">Submit Report</button>
                    </div>
                </div>
            </div>
        `);

        $('body').append($modal).css('overflow', 'hidden');

        // Close handlers
        const closeReportModal = () => {
            $modal.removeClass('active');
            setTimeout(() => {
                $modal.remove();
                $('body').css('overflow', '');
            }, 300);
        };

        $modal.find('.myavana-ci-report-close, .myavana-ci-report-cancel').on('click', function(e) {
            e.preventDefault();
            closeReportModal();
        });

        $modal.find('.myavana-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeReportModal();
            }
        });

        // Submit handler
        $modal.find('.myavana-ci-report-submit').on('click', function() {
            const $submitBtn = $(this);
            const reason = $modal.find('input[name="report_reason"]:checked').val();
            const details = $modal.find('.myavana-ci-report-details').val().trim();

            if (!reason) {
                showNotification('Please select a reason', 'error');
                return;
            }

            $submitBtn.prop('disabled', true).text('Submitting...');

            $.ajax({
                url: settings.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'myavana_ci_report_content',
                    nonce: settings.nonce,
                    content_type: contentType,
                    content_id: contentId,
                    reason: reason,
                    details: details
                },
                success: function(response) {
                    if (response.success) {
                        showNotification('Report submitted successfully. Thank you for helping keep our community safe.', 'success');
                        $modal.removeClass('active');
                        setTimeout(() => {
                            $modal.remove();
                            $('body').css('overflow', '');
                        }, 300);
                    } else {
                        showNotification(response.data || 'Failed to submit report', 'error');
                        $submitBtn.prop('disabled', false).text('Submit Report');
                    }
                },
                error: function() {
                    showNotification('Network error. Please try again.', 'error');
                    $submitBtn.prop('disabled', false).text('Submit Report');
                }
            });
        });
    }

    /**
     * Handle reaction count click - Show analytics
     */
    $(document).on('click', '.myavana-reaction-count-item, .myavana-ci-react-btn', function(e) {
        // Only handle if clicking on reaction count item or if user owns the post
        if ($(this).hasClass('myavana-reaction-count-item') ||
            ($(this).hasClass('myavana-ci-react-btn') && $(this).closest('.myavana-post-card').find('.myavana-post-edit-btn').length > 0)) {
            e.preventDefault();
            e.stopPropagation();

            const $postCard = $(this).closest('.myavana-post-card');
            const postId = $postCard.data('post-id');

            openPostAnalyticsModal(postId);
        }
    });

    /**
     * Open post analytics modal
     */
    function openPostAnalyticsModal(postId) {
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_get_post_analytics',
                nonce: settings.nonce,
                post_id: postId,
                analytics_type: 'reactions'
            },
            success: function(response) {
                if (response.success) {
                    showPostAnalyticsModal(postId, response.data);
                } else {
                    showNotification('Failed to load analytics', 'error');
                }
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
            }
        });
    }

    /**
     * Show post analytics modal
     */
    function showPostAnalyticsModal(postId, data) {
        const reactions = data.reactions || [];

        const reactionEmojis = {
            'like': '❤️',
            'love': '😍',
            'celebrate': '🎉',
            'insightful': '💡'
        };

        let reactionsHTML = '';
        if (reactions && reactions.length > 0) {
            const groupedReactions = {};
            reactions.forEach(r => {
                if (!groupedReactions[r.reaction_type]) {
                    groupedReactions[r.reaction_type] = [];
                }
                groupedReactions[r.reaction_type].push(r);
            });

            reactionsHTML = Object.entries(groupedReactions).map(([type, users]) => `
                <div class="myavana-ci-analytics-section">
                    <h4><span class="reaction-emoji">${reactionEmojis[type]}</span> ${type.charAt(0).toUpperCase() + type.slice(1)} (${users.length})</h4>
                    <div class="myavana-ci-analytics-users">
                        ${users.map(u => `
                            <div class="myavana-ci-analytics-user">
                                <img src="${escapeHtml(u.user_avatar || u.avatar || '')}" alt="${escapeHtml(u.display_name)}">
                                <span>${escapeHtml(u.display_name)}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `).join('');
        } else {
            reactionsHTML = '<p class="myavana-ci-no-analytics">No reactions yet</p>';
        }

        const $modal = $(`
            <div class="myavana-ci-analytics-modal active" id="analytics-modal-${postId}">
                <div class="myavana-modal-overlay"></div>
                <div class="myavana-ci-analytics-modal-content">
                    <div class="myavana-ci-analytics-header">
                        <h3>Post Analytics</h3>
                        <button class="myavana-ci-analytics-close">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="myavana-ci-analytics-body">
                        ${reactionsHTML}
                    </div>
                </div>
            </div>
        `);

        $('body').append($modal).css('overflow', 'hidden');

        // Close handlers
        const closeAnalyticsModal = () => {
            $modal.removeClass('active');
            setTimeout(() => {
                $modal.remove();
                $('body').css('overflow', '');
            }, 300);
        };

        $modal.find('.myavana-ci-analytics-close').on('click', function(e) {
            e.preventDefault();
            closeAnalyticsModal();
        });

        $modal.find('.myavana-modal-overlay').on('click', function(e) {
            if (e.target === this) {
                closeAnalyticsModal();
            }
        });
    }

    /**
     * Handle avatar/username clicks - Open user profile
     */
    $(document).on('click', '.clickable-avatar, .clickable-username', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const userId = $(this).data('user-id');
        openUserProfileModal(userId);
    });

    /**
     * Open user profile modal
     */
    function openUserProfileModal(userId) {
        // Show loading state
        const $loadingModal = $(`
            <div class="myavana-upm-modal active" id="upm-modal-${userId}">
                <div class="myavana-upm-overlay"></div>
                <div class="myavana-upm-content">
                    <div class="myavana-upm-loading">
                        <div class="myavana-loader-spinner"></div>
                        <p>Loading profile...</p>
                    </div>
                </div>
            </div>
        `);

        $('body').append($loadingModal).css('overflow', 'hidden');

        // Fetch user profile data
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_user_profile',
                nonce: settings.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    $loadingModal.find('.myavana-upm-content').html(renderUserProfile(response.data));
                } else {
                    $loadingModal.find('.myavana-upm-content').html(`
                        <div class="myavana-upm-error">
                            <p>Failed to load profile</p>
                            <button class="myavana-btn-secondary" onclick="$(this).closest('.myavana-upm-modal').remove(); $('body').css('overflow', '');">Close</button>
                        </div>
                    `);
                }
            },
            error: function() {
                $loadingModal.find('.myavana-upm-content').html(`
                    <div class="myavana-upm-error">
                        <p>Network error. Please try again.</p>
                        <button class="myavana-btn-secondary" onclick="$(this).closest('.myavana-upm-modal').remove(); $('body').css('overflow', '');">Close</button>
                    </div>
                `);
            }
        });
    }

    /**
     * Render user profile
     */
    function renderUserProfile(profile) {
        const isOwnProfile = profile.user_id == settings.userId;
        const isFollowing = profile.is_following || false;
        const journeyStats = profile.hair_journey_stats || {};
        const journeyPreview = profile.hair_journey_preview || {};
        const entryPreview = Array.isArray(journeyPreview.entries) ? journeyPreview.entries : [];
        const goalsPreview = Array.isArray(journeyPreview.goals) ? journeyPreview.goals : [];
        const routinePreview = Array.isArray(journeyPreview.routine) ? journeyPreview.routine : [];
        const avgHealthValue = (journeyStats.avg_health_rating === 0 || journeyStats.avg_health_rating)
            ? `${journeyStats.avg_health_rating}/10`
            : '--';

        return `
            <div class="myavana-upm-header">
                <button class="myavana-upm-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>

            <div class="myavana-upm-info">
                <div class="myavana-upm-avatar-large">
                    <img src="${escapeHtml(profile.avatar)}" alt="${escapeHtml(profile.display_name)}">
                </div>
                <h2 class="myavana-upm-name">${escapeHtml(profile.display_name)}</h2>
                ${profile.bio ? `<p class="myavana-upm-bio">${escapeHtml(profile.bio)}</p>` : ''}

                <div class="myavana-upm-stats">
                    <div class="myavana-upm-stat">
                        <span class="myavana-upm-stat-number">${profile.stats.posts_count || 0}</span>
                        <span class="myavana-upm-stat-label">Posts</span>
                    </div>
                    <div class="myavana-upm-stat">
                        <span class="myavana-upm-stat-number">${profile.stats.followers_count || 0}</span>
                        <span class="myavana-upm-stat-label">Followers</span>
                    </div>
                    <div class="myavana-upm-stat">
                        <span class="myavana-upm-stat-number">${profile.stats.following_count || 0}</span>
                        <span class="myavana-upm-stat-label">Following</span>
                    </div>
                </div>

                ${!isOwnProfile ? `
                    <button class="myavana-btn-primary follow-user-btn ${isFollowing ? 'following' : ''}"
                            data-user-id="${profile.user_id}">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${isFollowing ?
                                '<polyline points="20 6 9 17 4 12"></polyline>' :
                                '<path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="8.5" cy="7" r="4"></circle><line x1="20" y1="8" x2="20" y2="14"></line><line x1="23" y1="11" x2="17" y2="11"></line>'
                            }
                        </svg>
                        ${isFollowing ? 'Following' : 'Follow'}
                    </button>
                ` : ''}
            </div>

            <div class="myavana-upm-tabs">
                <button class="myavana-upm-tab active" data-tab="posts">Posts</button>
                <button class="myavana-upm-tab" data-tab="journey">Hair Journey</button>
            </div>

            <div class="myavana-upm-tab-wrapper">
                <div class="myavana-upm-tab-content active" data-tab-content="posts">
                    <div class="myavana-upm-posts-grid">
                        ${profile.recent_posts && profile.recent_posts.length > 0 ?
                            profile.recent_posts.map(post => `
                                <div class="myavana-upm-post-item" data-post-id="${post.id}">
                                    ${post.image_url ? `
                                        <img src="${escapeHtml(post.image_url)}" alt="${escapeHtml(post.title)}">
                                    ` : `
                                        <div class="myavana-upm-post-no-image">
                                            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                                <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                                <polyline points="21 15 16 10 5 21"></polyline>
                                            </svg>
                                        </div>
                                    `}
                                    <div class="myavana-upm-post-overlay">
                                        <div class="myavana-upm-post-stats">
                                            <span><svg width="20" height="20" viewBox="0 0 24 24" fill="white" stroke="white" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"></path></svg> ${post.likes_count || 0}</span>
                                            <span><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg> ${post.comments_count || 0}</span>
                                        </div>
                                    </div>
                                </div>
                            `).join('') :
                            '<div class="myavana-upm-empty">No posts yet</div>'
                        }
                    </div>
                </div>

                <div class="myavana-upm-tab-content" data-tab-content="journey">
                    <div class="myavana-upm-journey">
                        <div class="myavana-upm-journey-stats">
                            <div class="myavana-upm-journey-stat">
                                <span class="myavana-upm-journey-label">Total Entries</span>
                                <span class="myavana-upm-journey-value">${journeyStats.total_entries || 0}</span>
                            </div>
                            <div class="myavana-upm-journey-stat">
                                <span class="myavana-upm-journey-label">Journey Started</span>
                                <span class="myavana-upm-journey-value">${escapeHtml(journeyStats.journey_start || 'Recently')}</span>
                            </div>
                            <div class="myavana-upm-journey-stat">
                                <span class="myavana-upm-journey-label">Entries (30 days)</span>
                                <span class="myavana-upm-journey-value">${journeyStats.entries_last_30_days || 0}</span>
                            </div>
                            <div class="myavana-upm-journey-stat">
                                <span class="myavana-upm-journey-label">Avg Health</span>
                                <span class="myavana-upm-journey-value">${escapeHtml(avgHealthValue)}</span>
                            </div>
                            <div class="myavana-upm-journey-stat">
                                <span class="myavana-upm-journey-label">Goals</span>
                                <span class="myavana-upm-journey-value">${journeyStats.goals_count || 0}</span>
                            </div>
                            <div class="myavana-upm-journey-stat">
                                <span class="myavana-upm-journey-label">Routine Steps</span>
                                <span class="myavana-upm-journey-value">${journeyStats.routine_steps_count || 0}</span>
                            </div>
                        </div>

                        <div class="myavana-upm-journey-preview-grid">
                            <section class="myavana-upm-journey-panel">
                                <h4>Recent Entries</h4>
                                ${entryPreview.length ? `
                                    <ul class="myavana-upm-journey-list">
                                        ${entryPreview.map((entry) => `
                                            <li>
                                                <span class="myavana-upm-journey-item-title">${escapeHtml(entry.title || 'Hair Entry')}</span>
                                                <span class="myavana-upm-journey-item-meta">${escapeHtml(entry.date || '')}${entry.health_rating ? ` • Health ${escapeHtml(entry.health_rating)}/10` : ''}</span>
                                            </li>
                                        `).join('')}
                                    </ul>
                                ` : '<div class="myavana-upm-empty-inline">No entries yet.</div>'}
                            </section>

                            <section class="myavana-upm-journey-panel">
                                <h4>Active Goals</h4>
                                ${goalsPreview.length ? `
                                    <ul class="myavana-upm-journey-list">
                                        ${goalsPreview.map((goal) => `
                                            <li>
                                                <span class="myavana-upm-journey-item-title">${escapeHtml(goal.title || 'Hair Goal')}</span>
                                                <span class="myavana-upm-journey-item-meta">${escapeHtml(String(goal.progress || 0))}% complete${goal.target_date ? ` • Target ${escapeHtml(goal.target_date)}` : ''}</span>
                                            </li>
                                        `).join('')}
                                    </ul>
                                ` : '<div class="myavana-upm-empty-inline">No goals shared yet.</div>'}
                            </section>

                            <section class="myavana-upm-journey-panel">
                                <h4>Routine Snapshot</h4>
                                ${routinePreview.length ? `
                                    <ul class="myavana-upm-journey-list">
                                        ${routinePreview.map((step) => `
                                            <li>
                                                <span class="myavana-upm-journey-item-title">${escapeHtml(step.name || 'Routine Step')}</span>
                                                <span class="myavana-upm-journey-item-meta">${escapeHtml(step.frequency || 'daily')}</span>
                                            </li>
                                        `).join('')}
                                    </ul>
                                ` : '<div class="myavana-upm-empty-inline">No routine steps yet.</div>'}
                            </section>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Handle profile modal close button
     */
    $(document).on('click', '.myavana-upm-close', function(e) {
        e.preventDefault();
        $(this).closest('.myavana-upm-modal').removeClass('active');
        setTimeout(() => {
            $(this).closest('.myavana-upm-modal').remove();
            $('body').css('overflow', '');
        }, 300);
    });

    /**
     * Handle profile modal overlay click
     */
    $(document).on('click', '.myavana-upm-overlay', function(e) {
        if (e.target === this) {
            $(this).closest('.myavana-upm-modal').removeClass('active');
            setTimeout(() => {
                $(this).closest('.myavana-upm-modal').remove();
                $('body').css('overflow', '');
            }, 300);
        }
    });

    /**
     * Handle profile tabs
     */
    $(document).on('click', '.myavana-upm-tab', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent event from bubbling to modal overlay

        const tab = $(this).data('tab');
        const $modal = $(this).closest('.myavana-upm-content');

        $modal.find('.myavana-upm-tab').removeClass('active');
        $(this).addClass('active');

        $modal.find('.myavana-upm-tab-content').removeClass('active');
        $modal.find(`[data-tab-content="${tab}"]`).addClass('active');
    });

    /**
     * Handle follow button in profile
     */
    $(document).on('click', '.follow-user-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const userId = $btn.data('user-id');
        const $svg = $btn.find('svg');

        $btn.prop('disabled', true);

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'follow_user',
                nonce: settings.nonce,
                user_id: userId
            },
            success: function(response) {
                if (response.success) {
                    const {action, follower_count} = response.data;

                    if (action === 'followed') {
                        $btn.addClass('following').html(`
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"></polyline>
                            </svg>
                            Following
                        `);
                    } else {
                        $btn.removeClass('following').html(`
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                                <circle cx="8.5" cy="7" r="4"></circle>
                                <line x1="20" y1="8" x2="20" y2="14"></line>
                                <line x1="23" y1="11" x2="17" y2="11"></line>
                            </svg>
                            Follow
                        `);
                    }

                    // Update follower count
                    $btn.closest('.myavana-profile-modal-content').find('.myavana-profile-stats .myavana-stat-number').eq(1).text(follower_count);
                }
            },
            error: function() {
                showNotification('Failed to follow user', 'error');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    /**
     * View my profile
     */
    function launchGlobalProfileEdit() {
        // 1) Unified profile offcanvas (profile page)
        if (typeof window.myavanaUpOpenEditOffcanvas === 'function' && $('#myavanaUpEditOffcanvas').length) {
            window.myavanaUpOpenEditOffcanvas();
            return;
        }

        // 2) Legacy sidebar offcanvas (timeline shell)
        if (typeof window.openProfileEditOffcanvas === 'function' && $('.offcanvas-hjn.profile-edit').length) {
            window.openProfileEditOffcanvas();
            return;
        }

        // 3) Fallback: route to profile and auto-open there
        const fallbackSettings = window.myavanaCommunitySettings || {};
        const profileUrl = (settings.profileUrl || fallbackSettings.profileUrl || '/profile/').toString();
        const separator = profileUrl.indexOf('?') === -1 ? '?' : '&';
        window.location.href = `${profileUrl}${separator}open_profile_edit=1`;
    }

    window.myavanaCommunityOpenProfileEdit = launchGlobalProfileEdit;

    // Keep backward compatibility for legacy inline handlers used in community widget
    if (typeof window.myavanaUpOpenEditOffcanvas !== 'function') {
        window.myavanaUpOpenEditOffcanvas = launchGlobalProfileEdit;
    }

    window.viewMyProfile = function() {
        const fallbackSettings = window.myavanaCommunitySettings || {};
        const userId = settings.userId || fallbackSettings.userId;
        if (userId) {
            openUserProfileModal(userId);
        }
    };

    /**
     * Edit my profile (opens profile edit modal)
     */
    window.editMyProfile = function() {
        // TODO: Implement profile editing modal in Phase 4
        alert('Profile editing coming soon! For now, you can update your profile from WordPress settings.');
    };

    function closeSavedPostsModal() {
        const $modal = $('#myavana-saved-posts-modal');
        if ($modal.length) {
            $modal.removeClass('active');
            setTimeout(() => {
                $modal.remove();
                $('body').css('overflow', '');
            }, 250);
        }
    }

    function renderSavedPostsContent(posts) {
        if (!posts || posts.length === 0) {
            return `
                <div class="myavana-saved-posts-empty">
                    <p>No saved posts yet. Tap the bookmark icon on community posts to save them here.</p>
                </div>
            `;
        }

        return `
            <div class="myavana-saved-posts-grid">
                ${posts.map((post) => {
                    const title = normalizeCommunityText(post.title || 'Saved post');
                    const content = normalizeCommunityText(post.content || '');
                    const shortContent = content.substring(0, 140);
                    const truncated = content.length > 140 ? '...' : '';
                    return `
                        <article class="myavana-saved-post-card" data-post-id="${post.id}">
                            ${post.image_url ? `<img src="${escapeHtml(post.image_url)}" alt="${escapeHtml(title)}" class="myavana-saved-post-image">` : ''}
                            <div class="myavana-saved-post-content">
                                <h4>${escapeHtml(title)}</h4>
                                <p>${escapeHtml(shortContent)}${truncated}</p>
                                <div class="myavana-saved-post-meta">By ${escapeHtml(post.display_name || 'Community member')} • ${escapeHtml(post.formatted_date || '')}</div>
                                <div class="myavana-saved-post-actions">
                                    <button class="myavana-btn-secondary myavana-saved-share-btn" data-post-id="${post.id}" data-title="${escapeHtml(title || 'Myavana Community Post')}" data-content="${escapeHtml(content || '')}">
                                        Share
                                    </button>
                                    <button class="myavana-btn-secondary myavana-saved-view-btn" data-post-id="${post.id}">
                                        View
                                    </button>
                                    <button class="myavana-btn-secondary myavana-saved-remove-btn" data-post-id="${post.id}">
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </article>
                    `;
                }).join('')}
            </div>
        `;
    }

    function scrollToPostInFeed(postId, attempt = 0) {
        const $target = $(`.myavana-post-card[data-post-id="${postId}"]`);
        if ($target.length) {
            $('html, body').animate({ scrollTop: Math.max($target.offset().top - 120, 0) }, 350);
            $target.addClass('myavana-saved-post-highlight');
            setTimeout(() => $target.removeClass('myavana-saved-post-highlight'), 1800);
            return;
        }

        if (attempt < 10) {
            setTimeout(() => scrollToPostInFeed(postId, attempt + 1), 300);
        }
    }

    /**
     * View saved posts
     */
    window.viewSavedPosts = function() {
        closeSavedPostsModal();

        const modalHTML = `
            <div class="myavana-saved-posts-modal active" id="myavana-saved-posts-modal">
                <div class="myavana-modal-overlay"></div>
                <div class="myavana-saved-posts-modal-content">
                    <div class="myavana-saved-posts-header">
                        <h3>Saved Posts</h3>
                        <button class="myavana-saved-posts-close" type="button" aria-label="Close saved posts">
                            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>
                    <div class="myavana-saved-posts-body">
                        <div class="myavana-comments-loading">Loading saved posts...</div>
                    </div>
                </div>
            </div>
        `;

        $('body').append(modalHTML).css('overflow', 'hidden');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_get_saved_posts',
                nonce: settings.nonce
            },
            success: function(response) {
                const $body = $('#myavana-saved-posts-modal .myavana-saved-posts-body');
                if (response.success) {
                    $body.html(renderSavedPostsContent(response.data.posts || []));
                } else {
                    $body.html('<div class="myavana-comments-error">Failed to load saved posts.</div>');
                }
            },
            error: function() {
                $('#myavana-saved-posts-modal .myavana-saved-posts-body').html('<div class="myavana-comments-error">Network error. Please try again.</div>');
            }
        });
    };

    $(document).on('click', '.myavana-saved-posts-close', function(e) {
        e.preventDefault();
        closeSavedPostsModal();
    });

    $(document).on('click', '#myavana-saved-posts-modal .myavana-modal-overlay', function(e) {
        if (e.target === this) {
            closeSavedPostsModal();
        }
    });

    $(document).on('click', '.myavana-saved-share-btn', function(e) {
        e.preventDefault();
        const postId = $(this).data('post-id');
        const title = $(this).data('title') || 'Myavana Community Post';
        const content = $(this).data('content') || 'Check out this post from Myavana Community.';
        openShareModal(postId, title, content);
    });

    $(document).on('click', '.myavana-saved-view-btn', function(e) {
        e.preventDefault();

        const postId = $(this).data('post-id');
        closeSavedPostsModal();

        currentFilter = 'all';
        currentPage = 1;
        hasMorePosts = true;
        $('.myavana-filter-btn').removeClass('active');
        $('.myavana-filter-btn[data-filter="all"]').addClass('active');
        loadPosts(false);
        scrollToPostInFeed(postId);
    });

    $(document).on('click', '.myavana-saved-remove-btn', function(e) {
        e.preventDefault();

        const $btn = $(this);
        const postId = $btn.data('post-id');
        $btn.prop('disabled', true).text('Removing...');

        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'bookmark_post',
                nonce: settings.nonce,
                post_id: postId,
                collection: 'saved'
            },
            success: function(response) {
                if (!response.success) {
                    showNotification(response.data || 'Failed to remove saved post.', 'error');
                    $btn.prop('disabled', false).text('Remove');
                    return;
                }

                const action = response.data && response.data.action ? response.data.action : '';
                if (action && action !== 'unbookmarked') {
                    showNotification('Post bookmark state changed. Refreshing saved posts...', 'info');
                    window.viewSavedPosts();
                    return;
                }

                const $card = $btn.closest('.myavana-saved-post-card');
                $card.fadeOut(200, function() {
                    $(this).remove();
                    if (!$('.myavana-saved-post-card').length) {
                        $('#myavana-saved-posts-modal .myavana-saved-posts-body').html(renderSavedPostsContent([]));
                    }
                });

                const $bookmarkBtn = $(`.bookmark-btn[data-post-id="${postId}"]`);
                $bookmarkBtn.removeClass('bookmarked');
                $bookmarkBtn.find('svg').attr('fill', 'none');

                showNotification('Post removed from saved.', 'success');
            },
            error: function() {
                showNotification('Network error. Please try again.', 'error');
                $btn.prop('disabled', false).text('Remove');
            }
        });
    });

    /**
     * Handle read more clicks
     */
    $(document).on('click', '.myavana-read-more', function(e) {
        e.preventDefault();
        const $text = $(this).prev('.myavana-post-text');
        $text.removeClass('truncated');
        $(this).remove();
    });

    /**
     * Show loading state
     */
    function showLoading() {
        $('#myavana-feed-loading').show();
        $('#myavana-feed-grid').attr('aria-busy', 'true');
        $('#myavana-feed-grid').hide();
        $('#myavana-feed-empty').hide();
    }

    /**
     * Hide loading state
     */
    function hideLoading() {
        $('#myavana-feed-loading').hide();
        $('#myavana-feed-grid').attr('aria-busy', 'false');
        $('#myavana-feed-grid').show();
    }

    /**
     * Show empty state
     */
    function showEmptyState() {
        $('#myavana-feed-loading').hide();
        $('#myavana-feed-grid').attr('aria-busy', 'false');
        $('#myavana-feed-grid').hide();
        $('#myavana-feed-empty').show();
    }

    /**
     * Show error message
     */
    function showError(message) {
        showNotification(message, 'error');
        hideLoading();
    }

    /**
     * Show notification toast
     */
    function showNotification(message, type = 'info') {
        // Icons for different notification types
        const icons = {
            success: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>',
            error: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>',
            info: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>'
        };

        // Ensure toast container exists
        if (!$('.myavana-toast-container').length) {
            $('body').append('<div class="myavana-toast-container"></div>');
        }

        const $toast = $(`
            <div class="myavana-toast ${type}">
                <div class="myavana-toast-icon">${icons[type]}</div>
                <div class="myavana-toast-message">${escapeHtml(message)}</div>
            </div>
        `);

        $('.myavana-toast-container').append($toast);

        // Trigger animation
        setTimeout(() => {
            $toast.addClass('show');
        }, 10);

        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            $toast.removeClass('show').addClass('hide');
            setTimeout(() => {
                $toast.remove();
            }, 400);
        }, 3000);
    }

    /**
     * Parse text with @mentions and #hashtags
     */
    function decodeHtmlEntities(text) {
        const source = text === null || text === undefined ? '' : String(text);
        if (!source) {
            return '';
        }

        const textarea = document.createElement('textarea');
        textarea.innerHTML = source;
        return textarea.value;
    }

    function normalizeCommunityText(text) {
        let normalized = text === null || text === undefined ? '' : String(text);
        if (!normalized) {
            return '';
        }

        normalized = normalized
            .replace(/\\+(&#0*39;|&#x0*27;|&apos;)/gi, "'$1")
            .replace(/\\+(&quot;|&amp;)/gi, '$1')
            // Old content occasionally includes its own Markdown read-more link;
            // the feed supplies the accessible native control below the post.
            .replace(/\s*\[\*\*Read more\*\*\]\([^)]*\)\s*$/i, '');

        // Decode repeatedly because some legacy records were entity-encoded twice.
        for (let attempt = 0; attempt < 3; attempt += 1) {
            const decoded = decodeHtmlEntities(normalized);
            if (decoded === normalized) break;
            normalized = decoded;
        }
        return normalized.replace(/\\+&#0*39;|\\+&#x0*27;|\\+&apos;/gi, "'");
    }

    function parseTextWithMentionsAndHashtags(text) {
        if (!text) return '';

        // Parse @mentions
        text = text.replace(/@(\w+)/g, '<span class="myavana-mention">@$1</span>');

        // Parse #hashtags
        text = text.replace(/#(\w+)/g, '<span class="myavana-hashtag" data-hashtag="$1">#$1</span>');

        // Parse simple formatting (bold, italic)
        text = text.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        text = text.replace(/\*(.+?)\*/g, '<em>$1</em>');

        return text;
    }

    /**
     * Escape HTML to prevent XSS
     */
    function escapeHtml(text) {
        // Handle null, undefined, or non-string values
        if (text === null || text === undefined) {
            return '';
        }

        // Convert to string if not already
        text = String(text);

        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    /**
     * Activity Polling - Check for new posts/activity
     */
    let lastActivityCheck = Date.now();
    let activityPollInterval = null;
    let newActivityAvailable = false;

    function startActivityPolling() {
        // Check for new activity every 30 seconds
        activityPollInterval = setInterval(checkForNewActivity, 30000);
    }

    function checkForNewActivity() {
        $.ajax({
            url: settings.ajaxUrl,
            method: 'POST',
            data: {
                action: 'myavana_ci_check_activity',
                nonce: settings.nonce,
                last_check: Math.floor(lastActivityCheck / 1000),
                filter: currentFilter
            },
            success: function(response) {
                if (response.success && response.data.has_new_activity) {
                    showNewActivityBanner(response.data.count);
                    newActivityAvailable = true;
                }
            }
        });
    }

    function showNewActivityBanner(count) {
        // Remove existing banner if present
        $('.myavana-ci-new-activity-banner').remove();

        const bannerHTML = `
            <div class="myavana-ci-new-activity-banner">
                <div class="myavana-ci-new-activity-content">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                    <span>${count} new ${count === 1 ? 'post' : 'posts'} available</span>
                    <button class="myavana-ci-refresh-feed-btn">Refresh</button>
                </div>
            </div>
        `;

        $('.myavana-community-header').after(bannerHTML);

        // Animate banner in
        setTimeout(() => {
            $('.myavana-ci-new-activity-banner').addClass('show');
        }, 10);
    }

    // Click banner to refresh feed
    $(document).on('click', '.myavana-ci-refresh-feed-btn, .myavana-ci-new-activity-banner', function(e) {
        e.preventDefault();
        currentPage = 1;
        lastActivityCheck = Date.now();
        newActivityAvailable = false;

        // Animate banner out
        $('.myavana-ci-new-activity-banner').removeClass('show');
        setTimeout(() => {
            $('.myavana-ci-new-activity-banner').remove();
        }, 300);

        // Reload feed
        loadPosts(false);
    });

    // Stop polling when user leaves page
    $(window).on('beforeunload', function() {
        if (activityPollInterval) {
            clearInterval(activityPollInterval);
        }
    });

    /**
     * Image Lightbox/Gallery - Click to view fullscreen
     */
    let currentImageIndex = 0;
    let galleryImages = [];

    $(document).on('click', 'img.myavana-post-image', function(e) {
        e.preventDefault();
        const $postCard = $(this).closest('.myavana-post-card');

        // Get all images in this post
        galleryImages = [];
        $postCard.find('img.myavana-post-image').each(function() {
            galleryImages.push($(this).attr('src'));
        });

        // Find index of clicked image
        currentImageIndex = galleryImages.indexOf($(this).attr('src'));

        openImageLightbox();
    });

    function openImageLightbox() {
        const lightboxHTML = `
            <div class="myavana-ci-image-lightbox">
                <div class="myavana-ci-lightbox-overlay"></div>
                <button class="myavana-ci-lightbox-close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
                ${galleryImages.length > 1 ? `
                    <button class="myavana-ci-lightbox-prev">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="15 18 9 12 15 6"></polyline>
                        </svg>
                    </button>
                    <button class="myavana-ci-lightbox-next">
                        <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 18 15 12 9 6"></polyline>
                        </svg>
                    </button>
                ` : ''}
                <div class="myavana-ci-lightbox-content">
                    <img src="${galleryImages[currentImageIndex]}" alt="Fullscreen Image">
                    ${galleryImages.length > 1 ? `
                        <div class="myavana-ci-lightbox-counter">
                            ${currentImageIndex + 1} / ${galleryImages.length}
                        </div>
                    ` : ''}
                </div>
            </div>
        `;

        $('body').append(lightboxHTML);
        $('body').css('overflow', 'hidden');

        // Animate in
        setTimeout(() => {
            $('.myavana-ci-image-lightbox').addClass('show');
        }, 10);
    }

    function closeLightbox() {
        $('.myavana-ci-image-lightbox').removeClass('show');
        setTimeout(() => {
            $('.myavana-ci-image-lightbox').remove();
            $('body').css('overflow', '');
        }, 300);
    }

    function showNextImage() {
        currentImageIndex = (currentImageIndex + 1) % galleryImages.length;
        updateLightboxImage();
    }

    function showPrevImage() {
        currentImageIndex = (currentImageIndex - 1 + galleryImages.length) % galleryImages.length;
        updateLightboxImage();
    }

    function updateLightboxImage() {
        $('.myavana-ci-lightbox-content img').attr('src', galleryImages[currentImageIndex]);
        $('.myavana-ci-lightbox-counter').text(`${currentImageIndex + 1} / ${galleryImages.length}`);
    }

    // Lightbox event handlers
    $(document).on('click', '.myavana-ci-lightbox-close, .myavana-ci-lightbox-overlay', closeLightbox);
    $(document).on('click', '.myavana-ci-lightbox-next', showNextImage);
    $(document).on('click', '.myavana-ci-lightbox-prev', showPrevImage);

    // Keyboard navigation for lightbox
    $(document).on('keydown', function(e) {
        if ($('.myavana-ci-image-lightbox').length) {
            if (e.key === 'Escape') closeLightbox();
            if (e.key === 'ArrowRight') showNextImage();
            if (e.key === 'ArrowLeft') showPrevImage();
            return;
        }

        if (e.key === 'Escape') {
            const $activeModal = $([
                '#myavana-edit-post-modal.active',
                '#myavana-create-post-modal.active',
                '.myavana-share-modal.active',
                '.myavana-ci-collections-modal.active',
                '.myavana-ci-report-modal.active',
                '.myavana-ci-analytics-modal.active',
                '.myavana-upm-modal.active',
                '#myavana-saved-posts-modal.active',
                '.myavana-modal.active'
            ].join(',')).last();

            if (!$activeModal.length) {
                return;
            }

            const $closeBtn = $activeModal.find([
                '.myavana-edit-close',
                '.myavana-modal-close',
                '.myavana-share-close',
                '.myavana-ci-collections-close',
                '.myavana-ci-report-close',
                '.myavana-ci-analytics-close',
                '.myavana-upm-close',
                '.myavana-saved-posts-close',
                '#myavana-close-modal'
            ].join(',')).first();

            if ($closeBtn.length) {
                $closeBtn.trigger('click');
                return;
            }

            const $overlay = $activeModal.find('.myavana-modal-overlay, .myavana-upm-overlay').first();
            if ($overlay.length) {
                $overlay.trigger('click');
                return;
            }

            $activeModal.removeClass('active');
            setTimeout(() => {
                if ($activeModal.attr('id') !== 'myavana-create-post-modal') {
                    $activeModal.remove();
                }
                $('body').css('overflow', '');
            }, 260);
        }
    });

    // Initialize when document is ready
    $(document).ready(function() {
        if ($('.myavana-community-container').length) {
            initCommunityFeed();
            startActivityPolling();
        }
    });

})(jQuery);
