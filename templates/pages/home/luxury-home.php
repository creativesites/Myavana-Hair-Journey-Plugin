<?php
/**
 * MYAVANA Hair Journey - Luxury Homepage Template
 *
 * A beautiful, user-centric homepage that appeals to both registered and non-registered users
 * Uses luxury MYAVANA branding: onyx (#222323), white (#ffffff), and coral (#e7a690)
 */

function myavana_luxury_home_shortcode($atts = []) {
    return \Myavana\Next\Core\Router::renderShortcode(array_merge((array) $atts, ['tab' => 'home']));
}

function myavana_luxury_home_view() {
    // Check if user is logged in
    $is_logged_in = is_user_logged_in();
    $current_user = wp_get_current_user();

    // Get user profile data if logged in
    $user_profile = null;
    if ($is_logged_in) {
        global $wpdb;
        $user_profile = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}myavana_profiles WHERE user_id = %d",
            $current_user->ID
        ));
    }

    // Get user stats for logged-in users
    $user_stats = [];
    $is_new_user = false;
    $active_goals = [];
    $current_routines = [];
    $completed_goals = 0;
    if ($is_logged_in) {
        global $wpdb;
        $entries_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_author = %d AND post_type = 'hair_journey_entry' AND post_status = 'publish'",
            $current_user->ID
        ));
        $days_active_raw = $wpdb->get_var($wpdb->prepare(
            "SELECT DATEDIFF(CURDATE(), MIN(post_date)) FROM {$wpdb->prefix}posts WHERE post_author = %d AND post_type = 'hair_journey_entry'",
            $current_user->ID
        ));
        $entries_this_month = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}posts WHERE post_author = %d AND post_type = 'hair_journey_entry' AND post_status = 'publish' AND post_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)",
            $current_user->ID
        ));
        $entry_days = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(post_date) FROM {$wpdb->prefix}posts WHERE post_author = %d AND post_type = 'hair_journey_entry' AND post_status = 'publish' ORDER BY DATE(post_date) DESC",
            $current_user->ID
        ));

        $days_active = 0;
        if ($entries_count) {
            $days_active = max(1, intval($days_active_raw) + 1);
        }

        $entry_streak = 0;
        if (!empty($entry_days)) {
            $entry_days_lookup = array_fill_keys(array_map('strval', $entry_days), true);
            $cursor = current_time('timestamp');
            while ($cursor) {
                $key = wp_date('Y-m-d', $cursor);
                if (empty($entry_days_lookup[$key])) {
                    break;
                }
                $entry_streak++;
                $cursor -= DAY_IN_SECONDS;
            }
        }

        $goals_raw = get_user_meta($current_user->ID, 'myavana_hair_goals_structured', true);
        if (!is_array($goals_raw)) {
            $goals_raw = [];
        }

        foreach ($goals_raw as $goal) {
            $title = trim((string) ($goal['title'] ?? ($goal['goal_title'] ?? '')));
            if ($title === '') {
                continue;
            }

            $progress = max(0, min(100, intval($goal['progress'] ?? ($goal['progress_percent'] ?? 0))));
            $status = strtolower(trim((string) ($goal['status'] ?? 'active')));
            if ($status === '' || ($progress < 100 && $status !== 'paused')) {
                $status = 'active';
            }
            if ($progress >= 100) {
                $status = 'completed';
            }
            if ($status === 'completed') {
                $completed_goals++;
                continue;
            }

            $active_goals[] = [
                'title' => $title,
                'category' => trim((string) ($goal['goal_category'] ?? 'Goal')) ?: 'Goal',
                'progress' => $progress,
                'target_date' => trim((string) ($goal['target_date'] ?? ($goal['end_date'] ?? ''))),
                'status' => $status,
            ];
        }

        usort($active_goals, static function ($left, $right) {
            $left_date = !empty($left['target_date']) ? strtotime($left['target_date']) : PHP_INT_MAX;
            $right_date = !empty($right['target_date']) ? strtotime($right['target_date']) : PHP_INT_MAX;
            return $left_date <=> $right_date;
        });

        $routines_raw = get_user_meta($current_user->ID, 'myavana_current_routine', true);
        if (!is_array($routines_raw)) {
            $routines_raw = [];
        }

        $routine_completion_map = get_user_meta($current_user->ID, 'myavana_routine_completions', true);
        if (!is_array($routine_completion_map)) {
            $routine_completion_map = [];
        }
        $today_key = current_time('Y-m-d');
        $today_completed_routine_ids = array_map('intval', $routine_completion_map[$today_key] ?? []);

        foreach ($routines_raw as $index => $routine) {
            $title = trim((string) ($routine['title'] ?? ($routine['routine_title'] ?? ($routine['name'] ?? ''))));
            if ($title === '') {
                continue;
            }

            $status = strtolower(trim((string) ($routine['status'] ?? 'active')));
            if ($status === '') {
                $status = 'active';
            }
            if ($status === 'paused') {
                continue;
            }

            $steps = $routine['steps'] ?? ($routine['routine_steps'] ?? []);
            if (is_string($steps)) {
                $steps = preg_split('/\r\n|\r|\n|,/', $steps);
            }
            if (!is_array($steps)) {
                $steps = [];
            }
            $steps = array_values(array_filter(array_map('trim', array_map('strval', $steps))));

            $current_routines[] = [
                'title' => $title,
                'type' => trim((string) ($routine['routine_type'] ?? 'Routine')) ?: 'Routine',
                'frequency' => trim((string) ($routine['frequency'] ?? ($routine['routine_frequency'] ?? 'Weekly'))) ?: 'Weekly',
                'time' => trim((string) ($routine['routine_time'] ?? ($routine['time'] ?? ''))),
                'steps_count' => max(1, count($steps)),
                'completed_today' => in_array((int) $index, $today_completed_routine_ids, true),
            ];
        }

        // Check if user is new (no entries and recent registration)
        $user_registered = strtotime($current_user->user_registered);
        $days_since_registration = (time() - $user_registered) / (24 * 60 * 60);
        $is_new_user = ($entries_count == 0 && $days_since_registration <= 7);

        // Check if user has completed onboarding
        $onboarding_completed = get_user_meta($current_user->ID, 'myavana_onboarding_completed', true);
        $show_onboarding = ($is_new_user && !$onboarding_completed);

        $user_stats = [
            'entries' => $entries_count ?: 0,
            'days_active' => $days_active ?: 0,
            'streak' => $entry_streak,
            'is_new_user' => $is_new_user,
            'show_onboarding' => $show_onboarding,
            'onboarding_completed' => ($onboarding_completed === 'completed') ? true : false,
            'entries_this_month' => intval($entries_this_month ?: 0),
            'active_goals' => count($active_goals),
            'current_routines' => count($current_routines),
            'completed_today' => count($today_completed_routine_ids),
            'health_score' => $user_profile && isset($user_profile->hair_health_rating) ? floatval($user_profile->hair_health_rating) : 0,
        ];
    }

    $home_urls = [
        'timeline' => home_url('/hair-journey/'),
        'goals' => home_url('/goals/'),
        'routines' => home_url('/routines/'),
        'community' => home_url('/community/'),
        'insights' => home_url('/hair-insights/'),
    ];

    $goal_preview = array_slice($active_goals, 0, 3);
    $routine_preview = array_slice($current_routines, 0, 3);
    $routine_completion_ratio = (!empty($current_routines) && !empty($user_stats['current_routines']))
        ? (int) round(($user_stats['completed_today'] / max(1, $user_stats['current_routines'])) * 100)
        : 0;
    $health_score_label = ($user_stats['health_score'] ?? 0) > 0
        ? number_format_i18n((float) $user_stats['health_score'], 1) . '/10'
        : 'Building';

    $format_home_date = static function ($date_value) {
        if (empty($date_value)) {
            return 'No target set';
        }

        $timestamp = strtotime((string) $date_value);
        if (!$timestamp) {
            return 'No target set';
        }

        return wp_date('M j, Y', $timestamp);
    };

    
    // Enqueue Luxury Home assets
    $css_version = defined('MYAVANA_NEXT_VERSION') ? MYAVANA_NEXT_VERSION : '3.2.0';
    $js_version = defined('MYAVANA_NEXT_VERSION') ? MYAVANA_NEXT_VERSION : '3.2.0';
    $next_url = defined('MYAVANA_NEXT_URL') ? MYAVANA_NEXT_URL : plugin_dir_url(dirname(dirname(dirname(__FILE__))));

    wp_enqueue_style('myavana-luxury-home', $next_url . 'assets/css/luxury-home.css', [], $css_version);
    wp_enqueue_script('myavana-luxury-home', $next_url . 'assets/js/luxury-home.js', ['jquery'], $js_version, true);

    // Localize script with AJAX data
    wp_localize_script('myavana-luxury-home', 'myavanaLuxuryData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('myavana_nonce'),
        // Hair Journey doesn't do photo/AI hair analysis itself — that's a
        // separate MYAVANA product. Single configurable destination for
        // every "explore hair analysis" link on this page, so this URL
        // never has to be hunted down and changed in multiple templates.
        'hairAnalysisUrl' => get_option('myavana_next_hair_analysis_url', 'https://www.myavana.com/pages/consumer'),
        'isLoggedIn' => $is_logged_in,
        'currentUserId' => $is_logged_in ? $current_user->ID : 0,
        'currentUserName' => $is_logged_in ? $current_user->display_name : '',
        'userStats' => $user_stats,
        'isNewUser' => $is_new_user,
        'showOnboarding' => $is_logged_in && isset($show_onboarding) ? $show_onboarding : false
    ]);

    ob_start();
    ?>

    <div class="myavana-luxury-homepage">
        <!-- Hero Section -->
        <section class="myavana-luxury-hero">
            <div class="myavana-luxury-hero-container">
                <?php if (!$is_logged_in): ?>
                    <!-- Non-logged-in Hero -->
                    <div class="myavana-luxury-hero-content">
                        <div class="myavana-luxury-hero-badge">
                            ✨ Your Personalized Hair Care Journey
                        </div>
                        <h1 class="myavana-luxury-hero-title">
                            Transform Your<br>
                            <span class="gradient-text">Hair Journey</span>
                        </h1>
                        <h2 class="myavana-luxury-hero-subtitle">
                            Professional Hair Care, Personalized for You
                        </h2>
                        <p class="myavana-luxury-hero-description">
                            Join thousands of women who've transformed their hair health with routines, tracking, and
                            guidance built around their journey. Document your progress, follow a routine that fits
                            your goals, and connect with a supportive community.
                        </p>
                        <div class="myavana-luxury-hero-actions">
                            <a href="#" data-open-auth="signup" class="myavana-luxury-btn-primary">
                                <i class="fas fa-sparkles"></i>
                                Start Your Journey
                            </a>
                            <button type="button" class="myavana-luxury-btn-secondary" onclick="scrollToSection('features')">
                                <i class="fas fa-play"></i>
                                See How It Works
                            </button>
                        </div>
                    </div>

                    <div class="myavana-luxury-hero-visual">
                        <div class="myavana-luxury-hero-image">
                        <div class="hero-image"></div>
                        </div>

                        <!-- Global Stats -->
                        <div class="myavana-luxury-global-stats">
                            <div class="myavana-luxury-stat">
                                <span class="myavana-luxury-stat-number">50K+</span>
                                <span class="myavana-luxury-stat-label">Hair Journeys</span>
                            </div>
                            <div class="myavana-luxury-stat">
                                <span class="myavana-luxury-stat-number">98%</span>
                                <span class="myavana-luxury-stat-label">Satisfaction Rate</span>
                            </div>
                            <div class="myavana-luxury-stat">
                                <span class="myavana-luxury-stat-number">Daily</span>
                                <span class="myavana-luxury-stat-label">Personalized Guidance</span>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Logged-in Hero -->
                    <div class="myavana-luxury-hero-content">
                        <?php if ($is_new_user && $user_stats['entries'] == 0): ?>
                            <!-- New User with No Entries -->
                            <div class="myavana-luxury-hero-badge">
                                Welcome to MYAVANA, <?php echo esc_html($current_user->display_name); ?>! 🎉
                            </div>
                            <h1 class="myavana-luxury-hero-title">
                                Let's Start Your<br>
                                <span class="gradient-text">Hair Journey</span>
                            </h1>
                            <h2 class="myavana-luxury-hero-subtitle">
                                Ready to start your personalized hair journey?
                            </h2>
                            <p class="myavana-luxury-hero-description">
                                Let's get you started with a quick setup to understand your hair goals and create your first entry.
                                This will help us provide personalized recommendations just for you.
                            </p>
                            <div class="myavana-luxury-hero-actions">
                                <!-- <button class="myavana-luxury-btn-primary" onclick="MyavanaLuxuryHomepage.startOnboarding()">
                                    <i class="fas fa-rocket"></i>
                                    Start My Journey
                                </button> -->
                                <a class="myavana-luxury-btn-primary" href="<?php echo esc_url($home_urls['timeline']); ?>">
                                    <i class="fas fa-rocket"></i>
                                    Start My Journey
                                </a>
                                <!-- <button class="myavana-luxury-btn-secondary" onclick="showMyavanaModal('new-entry')">
                                    <i class="fas fa-camera"></i>
                                    Quick Entry
                                </button> -->
                            </div>
                        <?php else: ?>
                            <!-- Existing User -->
                            <div class="myavana-luxury-hero-badge">
                                Welcome back, <?php echo esc_html($current_user->display_name); ?>! ✨
                            </div>
                            <h1 class="myavana-luxury-hero-title">
                                Your Hair<br>
                                <span class="gradient-text">Journey Continues</span>
                            </h1>
                            <h2 class="myavana-luxury-hero-subtitle">
                                <?php
                                $greeting_messages = [
                                    "Ready to add today's progress?",
                                    "Your hair transformation awaits!",
                                    "Let's capture your beautiful journey!",
                                    "Time to document your hair evolution!"
                                ];
                                echo $greeting_messages[array_rand($greeting_messages)];
                                ?>
                            </h2>
                            <p class="myavana-luxury-hero-description">
                                <?php if ($user_profile && isset($user_profile->hair_health_rating)): ?>
                                    Your current hair health score: <strong><?php echo esc_html(number_format_i18n((float) $user_profile->hair_health_rating, 1)); ?>/10</strong>,
                                    with <strong><?php echo esc_html($user_stats['active_goals']); ?></strong> active goals and
                                    <strong><?php echo esc_html($user_stats['current_routines']); ?></strong> current routines.
                                    <br>Keep building consistency so your timeline can surface stronger patterns and insights.
                                <?php else: ?>
                                    You have <strong><?php echo esc_html($user_stats['entries']); ?></strong> entries,
                                    <strong><?php echo esc_html($user_stats['active_goals']); ?></strong> active goals,
                                    and <strong><?php echo esc_html($user_stats['current_routines']); ?></strong> routines in motion.
                                    <br>Keep documenting your progress to turn daily care into visible transformation.
                                <?php endif; ?>
                            </p>
                            <div class="myavana-luxury-hero-actions">
                                <a class="myavana-luxury-btn-primary" href="<?php echo esc_url($home_urls['timeline']); ?>">
                                    <i class="fas fa-camera"></i>
                                    My Hair Timeline
                                </a>
                                <button type="button" class="myavana-luxury-btn-secondary btn-open-kommunicate">
                                    <i class="fas fa-comment-dots"></i>
                                    Chat with Concierge
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="myavana-luxury-hero-visual">
                        <!-- Personal Stats -->
                        <div class="myavana-luxury-personal-stats">
                            <div class="myavana-luxury-stat">
                                <span class="myavana-luxury-stat-number"><?php echo $user_stats['entries']; ?></span>
                                <span class="myavana-luxury-stat-label">Entries</span>
                            </div>
                            <div class="myavana-luxury-stat">
                                <span class="myavana-luxury-stat-number"><?php echo $user_stats['streak']; ?></span>
                                <span class="myavana-luxury-stat-label">Day Streak</span>
                            </div>
                            <div class="myavana-luxury-stat">
                                <span class="myavana-luxury-stat-number"><?php echo $user_stats['active_goals']; ?></span>
                                <span class="myavana-luxury-stat-label">Active Goals</span>
                            </div>
                        </div>

                        <!-- Quick Actions Dashboard -->
                        <div class="myavana-luxury-quick-dashboard">
                            <a class="quick-action-card" href="<?php echo esc_url($home_urls['timeline']); ?>">
                                <div class="action-icon">
                                    <i class="fas fa-timeline"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Timeline</h4>
                                    <p>Review your latest entries</p>
                                </div>
                            </a>
                            <a class="quick-action-card" href="<?php echo esc_url($home_urls['routines']); ?>">
                                <div class="action-icon">
                                    <i class="fas fa-repeat"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Routines</h4>
                                    <p>Stay consistent with your plan</p>
                                </div>
                            </a>
                            <a class="quick-action-card" href="<?php echo esc_url($home_urls['goals']); ?>">
                                <div class="action-icon">
                                    <i class="fas fa-bullseye"></i>
                                </div>
                                <div class="action-content">
                                    <h4>Goals</h4>
                                    <p>Adjust what you're working toward</p>
                                </div>
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <?php if ($is_logged_in): ?>
            <section class="myavana-luxury-member-hub" id="member-hub">
                <div class="myavana-luxury-member-hub-container">
                    <div class="myavana-luxury-member-overview">
                        <article class="myavana-luxury-member-highlight">
                            <div class="myavana-luxury-member-highlight-head">
                                <span class="myavana-luxury-section-badge">Your day, considered</span>
                                <h2 class="myavana-luxury-member-title">A calm view of what moves your journey forward today.</h2>
                            </div>
                            <p class="myavana-luxury-member-description">
                                You logged <strong><?php echo esc_html($user_stats['entries_this_month']); ?></strong> entries in the last 30 days,
                                completed <strong><?php echo esc_html($user_stats['completed_today']); ?></strong> routines today,
                                and have <strong><?php echo esc_html($completed_goals); ?></strong> goals already completed.
                            </p>
                            <div class="myavana-luxury-member-metrics">
                                <div class="myavana-luxury-member-metric">
                                    <span class="value"><?php echo esc_html($health_score_label); ?></span>
                                    <span class="label">Hair Health</span>
                                </div>
                                <div class="myavana-luxury-member-metric">
                                    <span class="value"><?php echo esc_html($user_stats['days_active']); ?></span>
                                    <span class="label">Active Days</span>
                                </div>
                                <div class="myavana-luxury-member-metric">
                                    <span class="value"><?php echo esc_html($routine_completion_ratio); ?>%</span>
                                    <span class="label">Routine Pace</span>
                                </div>
                            </div>
                            <div class="myavana-luxury-member-actions">
                                <a class="myavana-luxury-btn-primary" href="<?php echo esc_url($home_urls['community']); ?>">
                                    <i class="fas fa-users"></i>
                                    Community
                                </a>
                                <a class="myavana-luxury-btn-secondary" href="<?php echo esc_url($home_urls['insights']); ?>">
                                    <i class="fas fa-chart-line"></i>
                                    Hair Insights
                                </a>
                            </div>
                        </article>

                        <article class="myavana-luxury-member-card">
                            <div class="myavana-luxury-member-card-head">
                                <div>
                                    <span class="eyebrow">Goals</span>
                                    <h3>Active goals</h3>
                                </div>
                                <a href="<?php echo esc_url($home_urls['goals']); ?>">View all</a>
                            </div>

                            <?php if (!empty($goal_preview)): ?>
                                <div class="myavana-luxury-member-list">
                                    <?php foreach ($goal_preview as $goal): ?>
                                        <article class="myavana-luxury-member-item">
                                            <div class="myavana-luxury-member-item-row">
                                                <div>
                                                    <span class="item-type"><?php echo esc_html($goal['category']); ?></span>
                                                    <h4><?php echo esc_html($goal['title']); ?></h4>
                                                </div>
                                                <span class="item-pill"><?php echo esc_html($goal['progress']); ?>%</span>
                                            </div>
                                            <div class="myavana-luxury-progress-bar" aria-hidden="true">
                                                <span style="width: <?php echo esc_attr($goal['progress']); ?>%;"></span>
                                            </div>
                                            <div class="myavana-luxury-member-meta">
                                                <span>Target <?php echo esc_html($format_home_date($goal['target_date'])); ?></span>
                                                <span><?php echo esc_html(ucfirst($goal['status'])); ?></span>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="myavana-luxury-member-empty">
                                    <h4>No active goals yet</h4>
                                    <p>Create a goal so your timeline and routines have a clear target to work toward.</p>
                                </div>
                            <?php endif; ?>

                            <div class="myavana-luxury-member-card-actions">
                                <a class="myavana-luxury-btn-secondary" href="<?php echo esc_url($home_urls['goals']); ?>?create=goal">Add Goal</a>
                            </div>
                        </article>

                        <article class="myavana-luxury-member-card">
                            <div class="myavana-luxury-member-card-head">
                                <div>
                                    <span class="eyebrow">Routines</span>
                                    <h3>Current routines</h3>
                                </div>
                                <a href="<?php echo esc_url($home_urls['routines']); ?>">View all</a>
                            </div>

                            <?php if (!empty($routine_preview)): ?>
                                <div class="myavana-luxury-member-list">
                                    <?php foreach ($routine_preview as $routine): ?>
                                        <article class="myavana-luxury-member-item">
                                            <div class="myavana-luxury-member-item-row">
                                                <div>
                                                    <span class="item-type"><?php echo esc_html($routine['type']); ?></span>
                                                    <h4><?php echo esc_html($routine['title']); ?></h4>
                                                </div>
                                                <span class="item-pill <?php echo $routine['completed_today'] ? 'is-success' : ''; ?>">
                                                    <?php echo $routine['completed_today'] ? 'Done' : 'Up next'; ?>
                                                </span>
                                            </div>
                                            <div class="myavana-luxury-member-meta">
                                                <span><?php echo esc_html($routine['steps_count']); ?> steps</span>
                                                <span><?php echo esc_html($routine['frequency']); ?></span>
                                                <?php if (!empty($routine['time'])): ?>
                                                    <span><?php echo esc_html($routine['time']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="myavana-luxury-member-empty">
                                    <h4>No routines in progress</h4>
                                    <p>Add a routine to turn your products and habits into a repeatable plan.</p>
                                </div>
                            <?php endif; ?>

                            <div class="myavana-luxury-member-card-actions">
                                <a class="myavana-luxury-btn-secondary" href="<?php echo esc_url($home_urls['routines']); ?>?create=routine">Add Routine</a>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        <?php endif; ?>



            <!-- Features Section (rendered for both logged-in and logged-out visitors) -->
            <section class="myavana-luxury-features" id="features">
                <div class="myavana-luxury-features-container">
                    <div class="myavana-luxury-section-header">
                        <div class="myavana-luxury-section-badge">Why MYAVANA</div>
                        <h2 class="myavana-luxury-section-title">
                            Everything You Need for<br>
                            <span class="gradient-text">Beautiful Hair</span>
                        </h2>
                        <p class="myavana-luxury-section-description">
                            Our comprehensive platform combines thoughtful tracking with expert knowledge
                            to give you personalized hair care like never before.
                        </p>
                    </div>

                    <div class="myavana-luxury-features-grid">
                        <div class="myavana-luxury-feature-card">
                            <div class="myavana-luxury-feature-icon">
                                <i class="fas fa-book-open"></i>
                            </div>
                            <h3 class="myavana-luxury-feature-title">Daily Hair Journal</h3>
                            <p class="myavana-luxury-feature-description">
                                Log quick daily check-ins or detailed wash-day notes in seconds, with photos when you
                                want them, so you always know what you actually tried.
                            </p>
                            <a href="#" class="myavana-luxury-feature-link" onclick="showMyavanaModal('register')">
                                Start Journaling <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="myavana-luxury-feature-card">
                            <div class="myavana-luxury-feature-icon">
                                <i class="fas fa-calendar-check"></i>
                            </div>
                            <h3 class="myavana-luxury-feature-title">Journey Tracking</h3>
                            <p class="myavana-luxury-feature-description">
                                Document your hair transformation with photos, notes, and progress tracking.
                                See your beautiful journey unfold over time.
                            </p>
                            <a href="#" class="myavana-luxury-feature-link" onclick="showMyavanaModal('register')">
                                Start Tracking <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="myavana-luxury-feature-card">
                            <div class="myavana-luxury-feature-icon">
                                <i class="fas fa-user-md"></i>
                            </div>
                            <h3 class="myavana-luxury-feature-title">Personalized Routines</h3>
                            <p class="myavana-luxury-feature-description">
                                Receive custom hair care routines tailored to your specific hair type, goals,
                                and lifestyle preferences.
                            </p>
                            <a href="#" class="myavana-luxury-feature-link" onclick="showMyavanaModal('register')">
                                Get Routine <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="myavana-luxury-feature-card">
                            <div class="myavana-luxury-feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <h3 class="myavana-luxury-feature-title">Community Support</h3>
                            <p class="myavana-luxury-feature-description">
                                Connect with thousands of women on similar journeys. Share experiences,
                                get advice, and celebrate wins together.
                            </p>
                            <a href="#community" class="myavana-luxury-feature-link">
                                Join Community <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="myavana-luxury-feature-card">
                            <div class="myavana-luxury-feature-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="myavana-luxury-feature-title">Progress Analytics</h3>
                            <p class="myavana-luxury-feature-description">
                                Detailed insights and analytics to track your hair health improvements,
                                routine effectiveness, and goal achievement.
                            </p>
                            <a href="#" class="myavana-luxury-feature-link" onclick="showMyavanaModal('register')">
                                View Analytics <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>

                        <div class="myavana-luxury-feature-card">
                            <div class="myavana-luxury-feature-icon">
                                <i class="fas fa-mobile-alt"></i>
                            </div>
                            <h3 class="myavana-luxury-feature-title">Mobile Experience</h3>
                            <p class="myavana-luxury-feature-description">
                                Access your hair journey anywhere with our responsive design and
                                progressive web app capabilities.
                            </p>
                            <a href="#" class="myavana-luxury-feature-link" onclick="showMyavanaModal('register')">
                                Get Started <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </section>

            <!-- How It Works Section -->
            <section class="myavana-luxury-how-it-works" id="how-it-works">
                <div class="myavana-luxury-how-it-works-container">
                    <div class="myavana-luxury-section-header">
                        <div class="myavana-luxury-section-badge">Simple Process</div>
                        <h2 class="myavana-luxury-section-title">
                            How It Works
                        </h2>
                        <p class="myavana-luxury-section-description">
                            Get started with your hair transformation in just 3 simple steps.
                        </p>
                    </div>

                    <div class="myavana-luxury-steps-grid">
                        <div class="myavana-luxury-step">
                            <div class="myavana-luxury-step-number">1</div>
                            <div class="myavana-luxury-step-icon">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <h3 class="myavana-luxury-step-title">Create Your Profile</h3>
                            <p class="myavana-luxury-step-description">
                                Sign up and tell us about your hair type, goals, and current routine.
                                This helps us personalize your experience from day one.
                            </p>
                        </div>

                        <div class="myavana-luxury-step">
                            <div class="myavana-luxury-step-number">2</div>
                            <div class="myavana-luxury-step-icon">
                                <i class="fas fa-camera"></i>
                            </div>
                            <h3 class="myavana-luxury-step-title">Log Your First Entry</h3>
                            <p class="myavana-luxury-step-description">
                                Snap a photo, note how your hair feels, and log what you used. That's it —
                                one entry starts your timeline.
                            </p>
                        </div>

                        <div class="myavana-luxury-step">
                            <div class="myavana-luxury-step-number">3</div>
                            <div class="myavana-luxury-step-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <h3 class="myavana-luxury-step-title">Track & Transform</h3>
                            <p class="myavana-luxury-step-description">
                                Follow your personalized routine, document your progress, and watch
                                your hair transform over time with detailed analytics.
                            </p>
                        </div>
                    </div>

                    <div class="myavana-luxury-cta-center">
                        <button class="myavana-luxury-btn-primary" onclick="showMyavanaModal('register')">
                            <i class="fas fa-rocket"></i>
                            Start Your Transformation
                        </button>
                    </div>
                </div>
            </section>

            <!-- Call to Action Section -->
            <section class="myavana-luxury-final-cta">
                <div class="myavana-luxury-final-cta-container">
                    <h2 class="myavana-luxury-final-cta-title">
                        Ready to Transform<br>
                        <span class="gradient-text">Your Hair Journey?</span>
                    </h2>
                    <p class="myavana-luxury-final-cta-description">
                        Join thousands of women who've already started their hair transformation with MYAVANA.
                        Your beautiful hair journey starts here.
                    </p>
                    <div class="myavana-luxury-final-cta-actions">
                        <a href="#" data-open-auth="signup" class="myavana-luxury-btn-primary">
                            <i class="fas fa-sparkles"></i>
                            Start Free Today
                        </a>
                        <a href="#" data-open-auth="signin" class="myavana-luxury-btn-secondary">
                            <i class="fas fa-sign-in-alt"></i>
                            Sign In
                        </a>
                    </div>

                    <!-- Trust Indicators -->
                    <div class="myavana-luxury-trust-indicators">
                        <div class="trust-item">
                            <i class="fas fa-shield-alt"></i>
                            <span>100% Secure</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-gift"></i>
                            <span>Free to Start</span>
                        </div>
                        <div class="trust-item">
                            <i class="fas fa-heart"></i>
                            <span>50K+ Happy Users</span>
                        </div>
                    </div>

                    <p class="myavana-luxury-analysis-link">
                        Want a deeper hair analysis?
                        <a href="<?php echo esc_url(get_option('myavana_next_hair_analysis_url', 'https://www.myavana.com/pages/consumer')); ?>" target="_blank" rel="noopener noreferrer">
                            Explore MYAVANA Hair Analysis <i class="fas fa-arrow-right"></i>
                        </a>
                    </p>
                </div>
            </section>
            <!-- Entry Form Modal -->
            <div class="myavana-modal-overlay" id="entryModal" style="display: none;">
                <div class="mya-modal">
                    <div class="mya-modal-header">
                        <h2 class="mya-modal-title" id="modalTitle">Add Hair Journey Entry</h2>
                        <button class="myavana-close-btn" id="closeModalBtn">×</button>
                    </div>
                    <div class="mya-modal-body">
                        <form id="entryForm" class="myavana-entry-form">
                            <input type="hidden" id="entryId" name="entry_id" value="">
                            <input type="hidden" id="entryDate" name="entry_date" value="">

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="entryTitle">Entry Title *</label>
                                <input type="text" id="entryTitle" name="title" class="myavana-form-input"
                                    placeholder="e.g., Wash day with new products" required>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="entryType">Entry Type *</label>
                                <select id="entryType" name="entry_type" class="myavana-form-select" required>
                                    <option value="">Select entry type</option>
                                    <option value="wash">Wash Day</option>
                                    <option value="treatment">Treatment</option>
                                    <option value="styling">Styling</option>
                                    <option value="progress">Progress Photo</option>
                                    <option value="general">General</option>
                                </select>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="entryDescription">Description</label>
                                <textarea id="entryDescription" name="description" class="myavana-form-textarea"
                                        rows="4" placeholder="Describe your hair journey moment..."></textarea>
                            </div>

                            <div class="myavana-form-row">
                                <div class="myavana-form-group">
                                    <label class="myavana-form-label" for="healthRating">Hair Health (1-10)</label>
                                    <div class="myavana-rating-input">
                                        <input type="range" id="healthRating" name="health_rating"
                                            min="1" max="10" value="5" class="myavana-range-input">
                                        <div class="myavana-rating-display">
                                            <span id="ratingValue">5</span>/10
                                        </div>
                                    </div>
                                </div>

                                <div class="myavana-form-group">
                                    <label class="myavana-form-label" for="moodRating">How You Feel</label>
                                    <select id="moodRating" name="mood" class="myavana-form-select">
                                        <option value="excited">😊 Excited</option>
                                        <option value="happy">😄 Happy</option>
                                        <option value="content">😌 Content</option>
                                        <option value="neutral">😐 Neutral</option>
                                        <option value="concerned">😟 Concerned</option>
                                        <option value="frustrated">😤 Frustrated</option>
                                    </select>
                                </div>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="productsUsed">Products Used</label>
                                <input type="text" id="productsUsed" name="products" class="myavana-form-input"
                                    placeholder="e.g., Moisturizing shampoo, leave-in conditioner">
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="entryNotes">Notes & Observations</label>
                                <textarea id="entryNotes" name="notes" class="myavana-form-textarea"
                                        rows="3" placeholder="Any additional notes or observations..."></textarea>
                            </div>

                            <div class="myavana-form-group">
                                <label class="myavana-form-label" for="entryPhoto">Upload Photo</label>
                                <div class="myavana-file-upload">
                                    <input type="file" id="entryPhoto" name="photo" accept="image/*" class="myavana-file-input">
                                    <label for="entryPhoto" class="myavana-file-label">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                                            <circle cx="8.5" cy="8.5" r="1.5"/>
                                            <polyline points="21,15 16,10 5,21"/>
                                        </svg>
                                        <span>Choose photo or drag & drop</span>
                                    </label>
                                    <div class="myavana-file-preview" id="photoPreview" style="display: none;"></div>
                                </div>
                            </div>

                            <div class="myavana-form-actions">
                                <button type="button" class="myavana-btn-secondary" id="cancelBtn">Cancel</button>
                                <button type="submit" class="myavana-btn-primary" id="saveBtn">
                                    <span class="myavana-btn-text">Save Entry</span>
                                    <span class="myavana-btn-loading" style="display: none;">
                                        <svg class="myavana-spinner" width="16" height="16" viewBox="0 0 24 24">
                                            <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                                            <path d="M12,2 A10,10 0 0,1 22,12" stroke="currentColor" stroke-width="4" fill="none"/>
                                        </svg>
                                        Saving...
                                    </span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
    </div>

    <script>
    // Luxury Homepage JavaScript
    document.addEventListener('DOMContentLoaded', function() {
        // Smooth scrolling for navigation links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Navigation scroll effect
        const nav = document.querySelector('.myavana-luxury-nav');
        let lastScrollY = window.scrollY;

        window.addEventListener('scroll', () => {
            if (window.scrollY > 100) {
                nav.classList.add('scrolled');
            } else {
                nav.classList.remove('scrolled');
            }

            // Hide/show nav on scroll
            if (window.scrollY > lastScrollY && window.scrollY > 100) {
                nav.style.transform = 'translateY(-100%)';
            } else {
                nav.style.transform = 'translateY(0)';
            }
            lastScrollY = window.scrollY;
        });

        // Animate elements on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-in');
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.myavana-luxury-feature-card, .myavana-luxury-step, .myavana-luxury-stat').forEach(el => {
            observer.observe(el);
        });

        // Stats counter animation
        const animateCounter = (element, target, duration = 2000) => {
            const start = 0;
            const increment = target / (duration / 16);
            let current = start;

            const timer = setInterval(() => {
                current += increment;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                element.textContent = Math.floor(current).toLocaleString();
            }, 16);
        };

        // Animate counters when they come into view
        const statNumbers = document.querySelectorAll('.myavana-luxury-stat-number');
        statNumbers.forEach(stat => {
            observer.observe(stat);
            stat.addEventListener('animateIn', () => {
                const value = stat.textContent.replace(/[^\d]/g, '');
                if (value) {
                    animateCounter(stat, parseInt(value));
                }
            });
        });
    });

    // Global functions
    function scrollToSection(sectionId) {
        const element = document.getElementById(sectionId);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth' });
        }
    }
  
    

    function toggleProfileDropdown(e) {
        e.stopPropagation();
        let menu = document.querySelector('.myavana-luxury-profile-dropdown-menu');
        
        if (!menu) {
            // Create dropdown if it doesn't exist
            const dropdownHTML = `
                <div class="myavana-luxury-profile-dropdown-menu">
                    <a href="/members/<?php echo $current_user->user_login; ?>/profile/">View Profile</a>
                    <a href="/hair-journey/">My Timeline</a>
                    <a href="<?php echo wp_logout_url(home_url()); ?>" class="logout">Logout</a>
                </div>
            `;
            document.querySelector('.myavana-luxury-profile-dropdown').insertAdjacentHTML('beforeend', dropdownHTML);
            menu = document.querySelector('.myavana-luxury-profile-dropdown-menu');
        }
        
        menu.classList.toggle('show');

        // Close when clicking outside
        const closeDropdown = (e) => {
            if (!e.target.closest('.myavana-luxury-profile-dropdown')) {
                menu.classList.remove('show');
                document.removeEventListener('click', closeDropdown);
            }
        };
        setTimeout(() => document.addEventListener('click', closeDropdown), 0);
    }

    // Modal integration (assumes modal system exists)
    if (typeof showMyavanaModal !== 'function') {
        window.showMyavanaModal = function(modalType) {
            console.log('Opening modal:', modalType);
            // Fallback behavior if modal system isn't loaded
            if (modalType === 'register') {
                alert('Registration modal would open here');
            } else if (modalType === 'login') {
                alert('Login modal would open here');
            } else if (modalType === 'ai-analysis') {
                window.location.href = 'https://www.myavana.com/pages/consumer';
            }
        };
    }
    </script>
    <?php
    return ob_get_clean();
}
?>
