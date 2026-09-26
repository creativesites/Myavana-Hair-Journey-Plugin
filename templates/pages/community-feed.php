<?php
/**
 * Community Feed Template
 * Displays social feed of shared hair journeys
 *
 * MYAVANA Brand Standards Applied:
 * - Color palette: Onyx, Coral, Blueberry, Stone
 * - Typography: Archivo Black for headers, Archivo for body
 * - Mobile-first responsive design
 */
if (!function_exists('myavana_community_feed_shortcode')) {
function myavana_community_feed_shortcode($atts = []) { 
    // Public visitors can browse real, privacy-safe (public-only) posts.
    // Creating and interacting with posts remains a member action, guarded
    // by a contextual sign-up prompt rather than hiding the feed entirely.
    if (!is_user_logged_in()) {
        global $wpdb;
        $member_count = (int) count_users()['total_users'];
        $entries_count = (int) ($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'hair_journey_entry' AND post_status = 'publish'"
        ) ?: 0);
        $public_posts_count = (int) ($wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}myavana_community_posts WHERE privacy_level = 'public'"
        ) ?: 0);

        ob_start();
        ?>
        <div class="myavana-community-guest">
            <div class="myavana-community-guest-grid">

                <!-- Feed column -->
                <main class="myavana-guest-feed" aria-label="Public community posts">
                    <div class="myavana-feed-filters">
                        <button class="myavana-filter-btn active" data-filter="all">All Posts</button>
                        <button class="myavana-filter-btn" data-filter="trending">Trending</button>
                        <button class="myavana-filter-btn" data-filter="featured">Featured</button>
                    </div>

                    <div class="myavana-feed-content">
                        <div class="myavana-feed-loading" id="myavana-feed-loading" role="status" aria-live="polite">
                            <div class="myavana-loader-spinner"></div>
                            <p class="myavana-body">Loading inspiring journeys...</p>
                        </div>

                        <div class="myavana-feed-grid" id="myavana-feed-grid" aria-live="polite" aria-busy="false"></div>

                        <div class="myavana-feed-empty" id="myavana-feed-empty" style="display: none;">
                            <div class="myavana-empty-icon">
                                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--myavana-coral)" stroke-width="1.5">
                                    <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                                    <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                                </svg>
                            </div>
                            <h3 class="myavana-subheader">No Public Posts Yet</h3>
                            <p class="myavana-body">Be the first to share your hair journey with the community!</p>
                            <a href="#auth" data-open-auth="signup" class="myavana-btn-primary">Create your free account</a>
                        </div>

                        <div class="myavana-feed-load-more" id="myavana-feed-load-more" style="display: none;">
                            <button class="myavana-btn-secondary" id="myavana-load-more-btn">Load More Stories</button>
                        </div>
                    </div>
                </main>

                <!-- Sidebar: sticky on desktop, a stacked set of banner cards on mobile -->
                <aside class="myavana-guest-sidebar" aria-label="Join MYAVANA">
                    <div class="myavana-guest-sidebar-inner">

                        <div class="myavana-guest-cta-card">
                            <span class="myavana-preheader">MYAVANA COMMUNITY</span>
                            <h1 class="myavana-guest-cta-title">A place for every stage of your hair journey.</h1>
                            <p class="myavana-body">Real progress, routines, and wins from people on the same path as you.</p>
                            <a href="#auth" data-open-auth="signup" class="myavana-btn myavana-btn-primary myavana-guest-cta-btn">Create your free account</a>
                            <a href="#auth" data-open-auth="signin" class="myavana-guest-cta-signin">Already have an account? Sign in</a>
                        </div>

                        <?php if ($member_count >= 500): ?>
                        <div class="myavana-guest-stats" role="group" aria-label="Community stats">
                            <div><strong><?php echo esc_html(number_format_i18n($member_count)); ?></strong><span>Members</span></div>
                            <div><strong><?php echo esc_html(number_format_i18n($entries_count)); ?></strong><span>Entries logged</span></div>
                            <div><strong><?php echo esc_html(number_format_i18n($public_posts_count)); ?></strong><span>Posts shared</span></div>
                        </div>
                        <?php else: ?>
                        <div class="myavana-guest-stats" role="group" aria-label="Community Highlights" style="grid-template-columns: 1fr; text-align: left; padding: 12px 16px;">
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <span style="font-size: 20px;">✨</span>
                                <div>
                                    <strong style="display: block; font-size: 13px; color: var(--myavana-onyx, #222323);">Private &amp; Supportive Sisterhood</strong>
                                    <span style="font-size: 12px; color: var(--myavana-stone-dark, #666);">Real textured hair progress &amp; shared regimens</span>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Gamified goal picker: a light, low-commitment first step that
                             ends on the same signup CTA rather than a real quiz result. -->
                        <div class="myavana-guest-goal-card" id="myavana-guest-goal-card" data-step="1">
                            <div class="myavana-guest-goal-progress" aria-hidden="true">
                                <span class="myavana-guest-goal-dot active" data-dot="1"></span>
                                <span class="myavana-guest-goal-dot" data-dot="2"></span>
                            </div>
                            <h3>What's your hair goal?</h3>
                            <p class="myavana-guest-goal-hint">Pick one to preview your starting point.</p>
                            <div class="myavana-guest-goal-options" role="group" aria-label="Choose a hair goal">
                                <button type="button" data-guest-goal="Stronger, healthier hair">💪 Stronger hair</button>
                                <button type="button" data-guest-goal="More moisture and definition">💧 Moisture &amp; definition</button>
                                <button type="button" data-guest-goal="A consistent growth routine">🌱 Growth routine</button>
                            </div>
                            <div class="myavana-guest-goal-result" hidden>
                                <p><strong id="myavana-guest-goal-result-title"></strong> is one of the top goals in the MYAVANA community right now. Create a free account to get a routine built around it.</p>
                                <a href="#auth" data-open-auth="signup" class="myavana-btn myavana-btn-primary myavana-guest-cta-btn">Unlock my plan</a>
                            </div>
                        </div>

                        <section class="myavana-guest-discovery-card" aria-label="Trending in the community">
                            <h3>Trending hashtags</h3>
                            <div class="myavana-discovery-chip-list" id="myavana-discovery-hashtags">
                                <span class="myavana-discovery-empty">Loading hashtags...</span>
                            </div>
                        </section>

                        <section class="myavana-guest-discovery-card" aria-label="Active community challenges">
                            <h3>Active challenges</h3>
                            <div class="myavana-discovery-challenges" id="myavana-discovery-challenges">
                                <span class="myavana-discovery-empty">Loading challenges...</span>
                            </div>
                        </section>

                        <ul class="myavana-guest-highlights">
                            <li><strong>Learn together</strong><span>Practical care ideas and supportive conversations.</span></li>
                            <li><strong>Celebrate progress</strong><span>See how consistent care becomes visible over time.</span></li>
                            <li><strong>Share when ready</strong><span>Your journey stays private unless you choose to share it.</span></li>
                        </ul>

                        <div class="myavana-guest-sidebar-legal">
                            <a href="<?php echo esc_url(home_url('/privacy/')); ?>">Privacy</a>
                            <a href="<?php echo esc_url(home_url('/terms/')); ?>">Terms</a>
                            <span>© <?php echo esc_html(wp_date('Y')); ?> MYAVANA</span>
                        </div>
                    </div>
                </aside>

            </div>
        </div>

        <script>
            window.myavanaCommunitySettings = {
                ajaxUrl: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                nonce: '<?php echo esc_js(wp_create_nonce('myavana_nonce')); ?>',
                userId: 0,
                isGuest: true,
                currentFilter: 'all',
                initialSearch: '',
                initialHashtag: '',
                initialMediaFilter: '',
                initialMode: 'discover',
                initialCircle: '',
                perPage: 10,
                currentPage: 1
            };

            // Guests can read the public feed and browse trending hashtags/
            // challenges, but any action that would touch a member-only
            // endpoint (like, comment, save, follow, post, open a profile)
            // routes to sign-up instead.
            document.addEventListener('click', function(event) {
                const scope = event.target.closest('#myavana-feed-grid, .myavana-guest-discovery-card');
                if (!scope) return;

                // Looking is free: photos open in the lightbox, hashtags and
                // challenges re-filter the public feed. Only the social
                // actions ask for an account.
                if (event.target.closest('.myavana-post-image-wrapper, .myavana-guest-feed-cta')) return;

                const control = event.target.closest('button, a');
                if (!control) return;
                const isSafeControl = control.matches(
                    '.myavana-discovery-hashtag, .myavana-discovery-challenge.has-hashtag'
                );
                if (isSafeControl) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                if (window.MyavanaNext && MyavanaNext.Auth) {
                    MyavanaNext.Auth.open('signup');
                }
            }, true);

            // A light, low-stakes first step: pick a goal, see a one-line
            // reveal, then the same signup CTA everything else leads to.
            (function() {
                const card = document.getElementById('myavana-guest-goal-card');
                if (!card) return;
                const result = card.querySelector('.myavana-guest-goal-result');
                const resultTitle = document.getElementById('myavana-guest-goal-result-title');
                card.querySelectorAll('[data-guest-goal]').forEach((button) => {
                    button.addEventListener('click', () => {
                        card.dataset.step = '2';
                        card.querySelector('[data-dot="2"]').classList.add('active');
                        card.querySelector('.myavana-guest-goal-options').hidden = true;
                        resultTitle.textContent = button.dataset.guestGoal;
                        result.hidden = false;
                    });
                });
            })();
        </script>
        <?php
        return ob_get_clean();
    }
    $is_logged_in = is_user_logged_in();
    $current_user = wp_get_current_user();

    // Parse shortcode attributes
    $atts = shortcode_atts([
        'filter' => 'all', // all, following, trending, featured
        'per_page' => '10',
        'show_filters' => 'true',
        'show_create_post' => 'true'
    ], $atts, 'myavana_community_feed');  

    // Get user's hair diary entries from custom table
    global $wpdb;
    $user_id = get_current_user_id();
    $entries_table = $wpdb->prefix . 'myavana_hair_diary_entries';
    $shared_table = $wpdb->prefix . 'myavana_shared_entries';

    // Get entries with their sharing status
    // $entries = $wpdb->get_results($wpdb->prepare("
    //     SELECT e.*,
    //            (SELECT community_post_id FROM {$shared_table} WHERE entry_id = e.id) as shared_post_id
    //     FROM {$entries_table} e
    //     WHERE e.user_id = %d
    //     ORDER BY e.entry_date DESC, e.created_at DESC
    //     LIMIT 100
    // ", $user_id));
    $entries_args = [
        'post_type' => 'hair_journey_entry',
        'author' => $user_id,
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'orderby' => 'post_date',
        'order' => 'DESC',
    ];
    $entries = get_posts($entries_args);
    $total_entries = count($entries);

    ob_start();
    ?>

    <div class="myavana-community-container" data-theme="light">
        <!-- Community Header -->
        <header class="myavana-community-header">
            <div class="myavana-community-header-content">
                <div class="myavana-community-title-section">
                    <span class="myavana-preheader">MYAVANA COMMUNITY</span>
                    <h1 class="myavana-community-title">Hair Journey Inspiration</h1>
                    <p class="myavana-body">Share your progress, discover transformations, and connect with others on their hair journey.</p>
                </div>

                <?php if ($atts['show_create_post'] === 'true') : ?>
                <div class="myavana-community-actions">
                    <button class="myavana-btn-primary" id="myavana-create-post-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                        Create New Post
                    </button>
                    <button class="myavana-btn-secondary share-existing-entry-btn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        Share Existing Entry
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </header>

        <!-- User Profile Widget -->
        <?php
        $current_user_id = get_current_user_id();
        $current_user_data = get_userdata($current_user_id);
        $is_logged_in = is_user_logged_in();
        $custom_avatar = get_user_meta($current_user_id, 'myavana_custom_avatar_url', true);
        $user_avatar = !empty($custom_avatar) ? $custom_avatar : get_avatar_url($current_user_id, ['size' => 80]);

        // Resolve profile page URL (shortcode page if available)
        $profile_page_url = home_url('/profile/');
        $profile_page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status = 'publish'
               AND post_content LIKE %s
             ORDER BY ID ASC
             LIMIT 1",
            '%[myavana_unified_profile%'
        ));
        if ($profile_page_id) {
            $resolved_profile_url = get_permalink((int) $profile_page_id);
            if (!empty($resolved_profile_url)) {
                $profile_page_url = $resolved_profile_url;
            }
        }

        // Get user stats
        global $wpdb;
        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $followers_table = $wpdb->prefix . 'myavana_user_followers';

        $user_posts_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $posts_table WHERE user_id = %d",
            $current_user_id
        ));

        $followers_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followers_table WHERE following_id = %d",
            $current_user_id
        ));

        $following_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $followers_table WHERE follower_id = %d",
            $current_user_id
        ));
        ?>

        <aside class="myavana-community-sidebar" aria-label="Your community tools">
        <div class="myavana-profile-widget">
            <div class="myavana-profile-widget-header">
                <div class="myavana-profile-widget-avatar-section">
                    <img src="<?php echo esc_url($user_avatar); ?>"
                         alt="<?php echo esc_attr($current_user_data->display_name); ?>"
                         class="myavana-profile-widget-avatar clickable-avatar"
                         data-user-id="<?php echo $current_user_id; ?>">
                    <div class="myavana-profile-widget-info">
                        <h3 class="myavana-profile-widget-name clickable-username"
                            data-user-id="<?php echo $current_user_id; ?>">
                            <?php echo esc_html($current_user_data->display_name); ?>
                        </h3>
                        <p class="myavana-profile-widget-username">@<?php echo esc_html($current_user_data->user_login); ?></p>
                    </div>
                </div>
                <a class="myavana-profile-widget-edit" onclick="myavanaCommunityOpenProfileEdit()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                    </svg>
                </a>
            </div>

            <div class="myavana-profile-widget-stats">
                <div class="myavana-profile-widget-stat">
                    <span class="myavana-widget-stat-number"><?php echo $user_posts_count; ?></span>
                    <span class="myavana-widget-stat-label">Posts</span>
                </div>
                <div class="myavana-profile-widget-stat">
                    <span class="myavana-widget-stat-number"><?php echo $followers_count; ?></span>
                    <span class="myavana-widget-stat-label">Followers</span>
                </div>
                <div class="myavana-profile-widget-stat">
                    <span class="myavana-widget-stat-number"><?php echo $following_count; ?></span>
                    <span class="myavana-widget-stat-label">Following</span>
                </div>
            </div>

            <div class="myavana-profile-widget-actions">
                <button class="myavana-profile-widget-btn" onclick="viewMyProfile()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                    View Profile
                </button>
                <button class="myavana-profile-widget-btn myavana-btn-icon" onclick="viewSavedPosts()" title="Saved Posts">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <section class="myavana-community-discovery" aria-label="Community discovery tools">
            <div class="myavana-community-mode-row" role="group" aria-label="Feed mode">
                <button type="button" class="myavana-community-mode-btn active" data-mode="discover">Discover</button>
                <button type="button" class="myavana-community-mode-btn" data-mode="following">Following</button>
            </div>

            <div class="myavana-circle-filter-row" role="group" aria-label="Community circles">
                <button type="button" class="myavana-circle-filter-btn active" data-circle="">All Circles</button>
                <button type="button" class="myavana-circle-filter-btn" data-circle="type-4c">Type 4C Circle</button>
                <button type="button" class="myavana-circle-filter-btn" data-circle="transitioning">Transitioning</button>
                <button type="button" class="myavana-circle-filter-btn" data-circle="length-retention">Length Retention</button>
                <button type="button" class="myavana-circle-filter-btn" data-circle="protective-style">Protective Style</button>
            </div>

            <div class="myavana-discovery-search-row">
                <label class="screen-reader-text" for="myavana-community-search-input">Search community posts</label>
                <input
                    type="search"
                    id="myavana-community-search-input"
                    class="myavana-community-search-input"
                    placeholder="Search posts, topics, and creators">
                <button type="button" class="myavana-btn-secondary" id="myavana-community-search-btn">Search</button>
                <button type="button" class="myavana-btn-secondary" id="myavana-community-search-clear-btn">Clear</button>
            </div>

            <div class="myavana-media-filter-row">
                <button type="button" class="myavana-media-filter-btn active" data-media-filter="">All Media</button>
                <button type="button" class="myavana-media-filter-btn" data-media-filter="image">Images</button>
                <button type="button" class="myavana-media-filter-btn" data-media-filter="video">Videos</button>
                <button type="button" class="myavana-media-filter-btn" data-media-filter="text">Text</button>
            </div>

            <div class="myavana-discovery-grid">
                <article class="myavana-discovery-card">
                    <h3>Trending Hashtags</h3>
                    <div class="myavana-discovery-chip-list" id="myavana-discovery-hashtags">
                        <span class="myavana-discovery-empty">Loading hashtags...</span>
                    </div>
                </article>
                <article class="myavana-discovery-card">
                    <h3>Suggested Creators</h3>
                    <div class="myavana-discovery-creators" id="myavana-discovery-creators">
                        <span class="myavana-discovery-empty">Loading creators...</span>
                    </div>
                </article>
                <article class="myavana-discovery-card">
                    <h3>Active Challenges</h3>
                    <div class="myavana-discovery-challenges" id="myavana-discovery-challenges">
                        <span class="myavana-discovery-empty">Loading challenges...</span>
                    </div>
                </article>
            </div>
        </section>
        </aside>

        <!-- Filter Tabs -->
        <?php if ($atts['show_filters'] === 'true') : ?>
        <div class="myavana-feed-filters">
            <button class="myavana-filter-btn active" data-filter="all">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="7" height="7"></rect>
                    <rect x="14" y="3" width="7" height="7"></rect>
                    <rect x="14" y="14" width="7" height="7"></rect>
                    <rect x="3" y="14" width="7" height="7"></rect>
                </svg>
                All Posts
            </button>
            <button class="myavana-filter-btn" data-filter="following">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                    <circle cx="9" cy="7" r="4"></circle>
                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                    <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                </svg>
                Following
            </button>
            <button class="myavana-filter-btn" data-filter="trending">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="23 6 13.5 15.5 8.5 10.5 1 18"></polyline>
                    <polyline points="17 6 23 6 23 12"></polyline>
                </svg>
                Trending
            </button>
            <button class="myavana-filter-btn" data-filter="featured">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                Featured
            </button>
            <button class="myavana-filter-btn" data-filter="media_image">Images</button>
            <button class="myavana-filter-btn" data-filter="media_video">Videos</button>
            <button class="myavana-filter-btn" data-filter="media_text">Text</button>
        </div>
        <?php endif; ?>

        <!-- Feed Container -->
        <div class="myavana-feed-content">

            <!-- Loading State -->
            <div class="myavana-feed-loading" id="myavana-feed-loading" role="status" aria-live="polite">
                <div class="myavana-loader-spinner"></div>
                <p class="myavana-body">Loading inspiring journeys...</p>
            </div>

            <!-- Posts Grid -->
            <div class="myavana-feed-grid" id="myavana-feed-grid" aria-live="polite" aria-busy="false">
                <!-- Posts will be dynamically loaded here via JavaScript -->
            </div>

            <!-- Empty State -->
            <div class="myavana-feed-empty" id="myavana-feed-empty" style="display: none;">
                <div class="myavana-empty-icon">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="var(--myavana-coral)" stroke-width="1.5">
                        <path d="M12 2L2 7l10 5 10-5-10-5z"></path>
                        <path d="M2 17l10 5 10-5M2 12l10 5 10-5"></path>
                    </svg>
                </div>
                <h3 class="myavana-subheader">No Posts Yet</h3>
                <p class="myavana-body">Be the first to share your hair journey with the community!</p>
                <button class="myavana-btn-primary" onclick="document.getElementById('myavana-create-post-btn').click()">
                    Share Your First Post
                </button>
            </div>

            <!-- Load More Button -->
            <div class="myavana-feed-load-more" id="myavana-feed-load-more" style="display: none;">
                <button class="myavana-btn-secondary" id="myavana-load-more-btn">
                    Load More Stories
                </button>
            </div>
        </div>
    </div>

    <!-- Create Post Modal -->
    <div class="myavana-modal" id="myavana-create-post-modal">
        <div class="myavana-modal-overlay"></div>
        <div class="myavana-modal-content">
            <div class="myavana-modal-header">
                <h2 class="myavana-subheader">Share Your Hair Journey</h2>
                <button class="myavana-modal-close" id="myavana-close-modal">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="myavana-modal-body">
                <form id="myavana-create-post-form">
                    <div class="myavana-quick-post-toolbar" role="group" aria-label="Quick post mode">
                        <button type="button" class="myavana-quick-post-btn active" data-mode="text">Text</button>
                        <button type="button" class="myavana-quick-post-btn" data-mode="photo">Photo</button>
                        <button type="button" class="myavana-quick-post-btn" data-mode="video">Video</button>
                        <button type="button" class="myavana-quick-post-btn" id="myavana-open-entry-selector-inline">From Journey</button>
                    </div>

                    <p class="myavana-quick-post-hint">Post in seconds. Title and details are optional.</p>

                    <div class="myavana-form-group">
                        <label class="myavana-form-label">Title (Optional)</label>
                        <input type="text"
                               id="myavana-post-title"
                               name="title"
                               class="myavana-form-input"
                               placeholder="e.g., Wash day wins">
                    </div>

                    <div class="myavana-form-group">
                        <label class="myavana-form-label">Caption (Optional)</label>
                        <textarea id="myavana-post-content"
                                  name="content"
                                  class="myavana-form-textarea"
                                  rows="4"
                                  placeholder="Share your update, tip, or result..."></textarea>
                    </div>

                    <div class="myavana-form-group myavana-ai-composer">
                        <label class="myavana-form-label">AI Community Assistant</label>
                        <div class="myavana-ai-actions">
                            <button type="button" class="myavana-btn-secondary myavana-ai-assist-btn" data-ai-action="caption">
                                Generate Caption
                            </button>
                            <button type="button" class="myavana-btn-secondary myavana-ai-assist-btn" data-ai-action="improve">
                                Improve Draft
                            </button>
                            <button type="button" class="myavana-btn-secondary myavana-ai-assist-btn" data-ai-action="hashtags">
                                Suggest Hashtags
                            </button>
                        </div>
                        <p class="myavana-ai-helper-text">Use AI to draft copy, polish tone, and create relevant hashtags before posting.</p>
                        <div class="myavana-ai-response" id="myavana-ai-response" aria-live="polite"></div>
                        <div class="myavana-ai-hashtags" id="myavana-ai-hashtags"></div>
                        <input type="hidden" id="myavana-post-hashtags" name="hashtags" value="">
                        <input type="hidden" id="myavana-post-ai-metadata" name="ai_metadata" value="">
                    </div>

                    <div class="myavana-form-group">
                        <label class="myavana-form-label">Add Photos (Optional)</label>
                        <div class="myavana-upload-area" id="myavana-upload-area">
                            <input type="file"
                                   id="myavana-post-image"
                                   name="post_image"
                                   accept="image/*"
                                   capture="environment"
                                   style="display: none;">
                            <div class="myavana-upload-prompt">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--myavana-coral)" stroke-width="2">
                                    <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                                    <circle cx="8.5" cy="8.5" r="1.5"></circle>
                                    <polyline points="21 15 16 10 5 21"></polyline>
                                </svg>
                                <p class="myavana-body">Upload a photo or use camera</p>
                            </div>
                            <div class="myavana-upload-preview" id="myavana-upload-preview" style="display: none;"></div>
                        </div>
                    </div>

                    <div class="myavana-form-group">
                        <label class="myavana-form-label">Video (Optional)</label>
                        <input type="url"
                               id="myavana-post-video-url"
                               name="video_url"
                               class="myavana-form-input"
                               placeholder="Paste a video URL (mp4/webm or hosted link)">
                        <div class="myavana-upload-area myavana-upload-area-video" id="myavana-video-upload-area">
                            <input type="file"
                                   id="myavana-post-video"
                                   name="post_video"
                                   accept="video/*"
                                   capture="environment"
                                   style="display: none;">
                            <div class="myavana-upload-prompt">
                                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--myavana-coral)" stroke-width="2">
                                    <polygon points="23 7 16 12 23 17 23 7"></polygon>
                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"></rect>
                                </svg>
                                <p class="myavana-body">Upload or record a short video</p>
                            </div>
                            <div class="myavana-upload-preview" id="myavana-video-upload-preview" style="display: none;"></div>
                        </div>
                    </div>

                    <details class="myavana-composer-advanced">
                        <summary>Advanced Details</summary>
                        <div class="myavana-composer-advanced-body">
                            <div class="myavana-form-group">
                                <label class="myavana-form-label">Post Type</label>
                                <select id="myavana-post-type" name="post_type" class="myavana-form-select">
                                    <option value="progress">Progress Update</option>
                                    <option value="transformation">Before & After</option>
                                    <option value="routine">Routine Share</option>
                                    <option value="products">Product Review</option>
                                    <option value="tips">Tips & Advice</option>
                                    <option value="video">Video Update</option>
                                    <option value="general">General</option>
                                </select>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label">Privacy</label>
                                <div class="myavana-radio-group">
                                    <label class="myavana-radio-label">
                                        <input type="radio" name="privacy_level" value="public" checked>
                                        <span class="myavana-radio-custom"></span>
                                        <span class="myavana-body">Public - Everyone can see</span>
                                    </label>
                                    <label class="myavana-radio-label">
                                        <input type="radio" name="privacy_level" value="followers">
                                        <span class="myavana-radio-custom"></span>
                                        <span class="myavana-body">Followers Only</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </details>

                    <div class="myavana-modal-footer">
                        <button type="button" class="myavana-btn-secondary" id="myavana-cancel-post">
                            Cancel
                        </button>
                        <div class="myavana-modal-footer-actions">
                            <button type="button" class="myavana-btn-secondary" id="myavana-ci-save-draft-btn">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"></path>
                                    <polyline points="17 21 17 13 7 13 7 21"></polyline>
                                    <polyline points="7 3 7 8 15 8"></polyline>
                                </svg>
                                Save Draft
                            </button>
                            <button type="submit" class="myavana-btn-primary">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 2L11 13"></path>
                                    <path d="M22 2L15 22L11 13L2 9L22 2Z"></path>
                                </svg>
                                Share Post
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Share Existing Entry Modal -->
    <div class="myavana-modal" id="myavana-entry-selector-modal" style="display: none;">
        <div class="myavana-modal-overlay"></div>
        <div class="myavana-modal-container">
            <div class="myavana-modal-header">
                <h2>Select Entries to Share</h2>
                <button class="myavana-modal-close">&times;</button>
            </div>

            <div class="myavana-modal-body is-overflow-y">
                <!-- Search and Filter -->
                <div class="entry-selector-filters">
                    <input type="text"
                           id="entry-search"
                           class="myavana-input"
                           placeholder="Search entries...">
                    <select id="entry-filter-photos" class="myavana-select">
                        <option value="">All Entries</option>
                        <option value="with-photos">With Photos</option>
                        <option value="no-photos">Without Photos</option>
                    </select>
                </div>

                <?php if (empty($entries)): ?>
                    <!-- Empty State -->
                    <div class="entry-selector-empty">
                        <p>No entries found. Create your first hair journey entry!</p>
                    </div>
                <?php else: ?>
                    <!-- Entries Grid -->
                    <div class="entry-selector-grid">
                        <?php if ( ! empty( $entries ) ) : ?>
                            <?php foreach ( $entries as $entry ) :
                                $post_id = $entry->ID;
                                $entry_title = get_the_title($post_id);
                                $entry_date = get_the_date('', $post_id);
                                $entry_date_formatted = get_the_date('F j, Y', $post_id);

                                // Check if this entry has been shared to community
                                $is_shared = $wpdb->get_var($wpdb->prepare(
                                    "SELECT id FROM {$shared_table} WHERE entry_id = %d",
                                    $post_id
                                ));

                                // Try to get post thumbnail first
                                $thumbnail = get_the_post_thumbnail_url($post_id, 'medium');
                                
                                // If no thumbnail, check for featured_image_index meta and gallery
                                if (empty($thumbnail)) {
                                    $featured_image_index = get_post_meta($post_id, 'featured_image_index', true);
                                    $gallery_images = get_post_meta($post_id, '_entry_gallery', true);
                                    
                                    // If we have a featured_image_index and gallery images exist
                                    if ($featured_image_index !== '' && !empty($gallery_images) && is_array($gallery_images)) {
                                        // Ensure the index is valid
                                        $index = intval($featured_image_index);
                                        if (isset($gallery_images[$index])) {
                                            $thumbnail = wp_get_attachment_image_url($gallery_images[$index], 'medium');
                                        }
                                    }
                                    
                                    // If still no thumbnail, try the first gallery image
                                    if (empty($thumbnail) && !empty($gallery_images) && is_array($gallery_images)) {
                                        $thumbnail = wp_get_attachment_image_url($gallery_images[0], 'medium');
                                    }
                                }
                                
                                $content = wp_strip_all_tags($entry->post_content);
                                $excerpt = wp_trim_words($content, 20);
                                // lets corectly set photo count
                                $photo_count = 0;

                                // Get entry metadata
                                $rating = get_post_meta($post_id, 'health_rating', true);
                                $mood = get_post_meta($post_id, 'mood_demeanor', true);
                                $products = get_post_meta($post_id, 'products_used', true);

                                // Sort date
                                $sort_date = strtotime($entry->post_date);
                            ?>
                            <div class="list-item-hjn entry-selector-card list-item-entry-hjn <?php echo $is_shared ? 'already-shared' : ''; ?>"
                                data-type="entries"
                                data-title="<?php echo esc_attr(strtolower($entry_title)); ?>"
                                data-date="<?php echo esc_attr($sort_date); ?>"
                                data-entry-id="<?php echo esc_attr($post_id); ?>"
                                data-has-photos="<?php echo $photo_count > 0 ? 'yes' : 'no'; ?>"
                                >

                                <?php if ($thumbnail): ?>
                                <div class="list-item-thumbnail-hjn">
                                    <img src="<?php echo esc_url($thumbnail); ?>" alt="<?php echo esc_attr($entry_title); ?>" loading="lazy" />
                                    <?php if ($rating): ?>
                                    <div class="thumbnail-rating-hjn">
                                        <svg viewBox="0 0 24 24" width="14" height="14">
                                            <path fill="currentColor" d="M12,17.27L18.18,21L16.54,13.97L22,9.24L14.81,8.62L12,2L9.19,8.62L2,9.24L7.45,13.97L5.82,21L12,17.27Z"/>
                                        </svg>
                                        <?php echo esc_html($rating); ?>/10
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <div class="list-item-icon-hjn list-icon-entry-hjn">
                                    <svg viewBox="0 0 24 24" width="24" height="24">
                                        <path fill="currentColor" d="M4,4H7L9,2H15L17,4H20A2,2 0 0,1 22,6V18A2,2 0 0,1 20,20H4A2,2 0 0,1 2,18V6A2,2 0 0,1 4,4M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,12A5,5 0 0,0 12,7M12,9A3,3 0 0,1 15,12A3,3 0 0,1 12,15A3,3 0 0,1 9,12A3,3 0 0,1 12,9Z"/>
                                    </svg>
                                </div>
                                <?php endif; ?>

                                <div class="list-item-content-hjn">
                                    <div class="list-item-header-hjn">
                                        <h3 class="list-item-title-hjn"><?php echo esc_html($entry_title); ?></h3>
                                    </div>

                                    <div class="list-item-date-hjn">
                                        <svg viewBox="0 0 24 24" width="14" height="14">
                                            <path fill="currentColor" d="M12,20A8,8 0 0,0 20,12A8,8 0 0,0 12,4A8,8 0 0,0 4,12A8,8 0 0,0 12,20M12,2A10,10 0 0,1 22,12A10,10 0 0,1 12,22C6.47,22 2,17.5 2,12A10,10 0 0,1 12,2M12.5,7V12.25L17,14.92L16.25,16.15L11,13V7H12.5Z"/>
                                        </svg>
                                        <?php echo esc_html($entry_date_formatted); ?>
                                    </div>

                                    <p class="list-item-description-hjn"><?php echo esc_html($excerpt); ?></p>

                                    <div class="list-item-meta-hjn">
                                        <span class="meta-tag-hjn tag-type-hjn">Entry</span>
                                        <?php if ($mood): ?>
                                        <span class="meta-tag-hjn"><?php echo esc_html($mood); ?></span>
                                        <?php endif; ?>
                                        <?php if ($products): ?>
                                        <span class="meta-tag-hjn">
                                            <?php
                                            $product_count = count(array_filter(explode(',', $products)));
                                            echo $product_count . ' Product' . ($product_count !== 1 ? 's' : '');
                                            ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="entry-card-actions">
                                    <?php if ($is_shared): ?>
                                        <span class="entry-shared-badge">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="20 6 9 17 4 12"></polyline>
                                            </svg>
                                            Already Shared
                                        </span>
                                    <?php else: ?>
                                        <input type="checkbox" class="entry-selector-checkbox" value="<?php echo esc_attr($post_id); ?>">
                                    <?php endif; ?>
                                </div>

                                
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Selection Controls -->
                    <div class="entry-selector-controls">
                        <div class="entry-selection-info">
                            <span id="selected-count">0</span> selected (max 10)
                        </div>
                        <div class="entry-selector-buttons">
                            <button type="button" id="select-all-entries" class="myavana-btn-secondary">Select All</button>
                            <button type="button" id="deselect-all-entries" class="myavana-btn-secondary">Deselect All</button>
                        </div>
                    </div>

                    <!-- Privacy Selection -->
                    <div class="entry-selector-privacy">
                        <label class="myavana-form-label">Privacy for shared entries:</label>
                        <div class="myavana-radio-group">
                            <label class="myavana-radio-label">
                                <input type="radio" name="bulk_privacy" value="public" checked>
                                <span class="myavana-radio-custom"></span>
                                <span>Public</span>
                            </label>
                            <label class="myavana-radio-label">
                                <input type="radio" name="bulk_privacy" value="followers">
                                <span class="myavana-radio-custom"></span>
                                <span>Followers Only</span>
                            </label>
                        </div>
                    </div>

                    <!-- Share Button -->
                    <div class="entry-selector-footer">
                        <button type="button" class="myavana-btn-secondary" id="cancel-entry-selection">Cancel</button>
                        <button type="button" class="myavana-btn-primary" id="share-selected-entries" disabled>
                            Share Selected Entries
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Image Lightbox Modal -->
    <div id="myavana-image-lightbox" class="myavana-lightbox" style="display: none;">
        <div class="myavana-lightbox-overlay"></div>
        <div class="myavana-lightbox-content">
            <button class="myavana-lightbox-close" aria-label="Close lightbox">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <img id="myavana-lightbox-image" src="" alt="Full size image" class="myavana-lightbox-img">
        </div>
    </div>

    <script>
        // Pass data to JavaScript
        window.myavanaCommunitySettings = {
            ajaxUrl: '<?php echo admin_url('admin-ajax.php'); ?>',
            nonce: '<?php echo wp_create_nonce('myavana_nonce'); ?>',
            userId: <?php echo get_current_user_id(); ?>,
            userAvatar: '<?php echo esc_url(myavana_get_user_avatar_url(get_current_user_id(), 40)); ?>',
            profileUrl: '<?php echo esc_url($profile_page_url); ?>',
            currentFilter: '<?php echo esc_js($atts['filter']); ?>',
            initialSearch: '',
            initialHashtag: '',
            initialMediaFilter: '',
            initialMode: 'discover',
            initialCircle: '',
            perPage: <?php echo intval($atts['per_page']); ?>,
            currentPage: 1
        };
    </script>

    <?php
    return ob_get_clean();
}
}
