<?php
/**
 * Header and Sidebar Partial for Hair Journey Page
 *
 * This partial expects $shared_data to be passed from the main shortcode.
 * All data is fetched ONCE by Myavana_Data_Manager to avoid redundant queries.
 *
 * Available variables from $shared_data:
 * - $user_id, $profile, $typeform_data, $hair_goals, $about_me
 * - $analysis_history, $current_routine, $user_stats, $analytics_data
 * - $analysis_limit_info (limit, count, can_analyze, remaining)
 */

// These variables are already available from parent scope (hair-journey.php)
// No need to re-fetch them - they're passed via $shared_data

$is_owner = true; // Current user viewing their own journey

// Extract analysis snapshots from profile
$snapshots = $user_profile->hair_analysis_snapshots ? json_decode($user_profile->hair_analysis_snapshots, true) : [];
usort($snapshots, function($a, $b) {
    return strtotime($b['timestamp']) <=> strtotime($a['timestamp']);
});

// Get analytics data (already cached in $shared_data)
$analytics_data = $shared_data['analytics'];

// Generate dynamic AI insight (already calculated)
$ai_insight = myavana_generate_ai_insights_new($user_id);

// Calculate total entries for display
$total_entries = $analytics_data['total_entries'];

// Hair profile details (already available from $shared_data)
$hair_porosity = $shared_data['hair_porosity'];
$hair_length = $shared_data['hair_length'];
global $wpdb;
$table_name = $wpdb->prefix . 'myavana_profiles';
$profile = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));

$journey_total_entries = isset($analytics_data['total_entries']) ? intval($analytics_data['total_entries']) : 0;
$journey_total_goals = is_array($hair_goals) ? count($hair_goals) : 0;
$journey_total_routines = is_array($current_routine) ? count($current_routine) : 0;
$journey_gamification = $shared_data['gamification'] ?? [];
$journey_streak_days = isset($journey_gamification['current_streak']) ? intval($journey_gamification['current_streak']) : intval($user_stats['streak'] ?? 0);
$journey_points_total = isset($journey_gamification['total_points']) ? intval($journey_gamification['total_points']) : intval($user_stats['total_points'] ?? 0);
$journey_level = isset($journey_gamification['level']) ? intval($journey_gamification['level']) : 1;
$journey_badges = isset($journey_gamification['badges_earned']) ? intval($journey_gamification['badges_earned']) : 0;
$journey_xp_pct = isset($journey_gamification['xp_progress_percent']) ? intval($journey_gamification['xp_progress_percent']) : 0;
$journey_points_to_next = isset($journey_gamification['points_to_next_level']) ? intval($journey_gamification['points_to_next_level']) : 100;
$journey_checked_in = !empty($journey_gamification['checked_in_today']);
$journey_next_badge = $journey_gamification['next_badge'] ?? null;
$journey_recent_badges = !empty($journey_gamification['recent_badges']) && is_array($journey_gamification['recent_badges'])
    ? array_slice($journey_gamification['recent_badges'], 0, 3)
    : [];
$journey_daily_quests = !empty($journey_gamification['daily_quests']) && is_array($journey_gamification['daily_quests'])
    ? $journey_gamification['daily_quests']
    : [];
$journey_weekly_quests = !empty($journey_gamification['weekly_quests']) && is_array($journey_gamification['weekly_quests'])
    ? $journey_gamification['weekly_quests']
    : [];
$journey_recent_rewards = !empty($journey_gamification['recent_rewards']) && is_array($journey_gamification['recent_rewards'])
    ? $journey_gamification['recent_rewards']
    : [];
$journey_active_challenges = !empty($journey_gamification['active_challenges']) && is_array($journey_gamification['active_challenges'])
    ? $journey_gamification['active_challenges']
    : [];
$journey_daily_completed = count(array_filter($journey_daily_quests, static function ($quest) {
    return !empty($quest['current']) && intval($quest['current']) >= intval($quest['target'] ?? 1);
}));
$journey_weekly_completed = count(array_filter($journey_weekly_quests, static function ($quest) {
    return !empty($quest['current']) && intval($quest['current']) >= intval($quest['target'] ?? 1);
}));
$journey_hour = (int) current_time('G');
$journey_greeting = $journey_hour < 12 ? 'Good Morning' : ($journey_hour < 18 ? 'Good Afternoon' : 'Good Evening');

$journey_routine_completion_map = get_user_meta($user_id, 'myavana_routine_completions', true);
if (!is_array($journey_routine_completion_map)) {
    $journey_routine_completion_map = [];
}
$journey_today_date = current_time('Y-m-d');
$journey_completed_today_routines = isset($journey_routine_completion_map[$journey_today_date]) && is_array($journey_routine_completion_map[$journey_today_date])
    ? array_values(array_map('intval', $journey_routine_completion_map[$journey_today_date]))
    : [];

$journey_active_goal_index = null;
$journey_active_goal = null;
if (is_array($hair_goals)) {
    foreach ($hair_goals as $goal_index => $goal_item) {
        $goal_progress = intval($goal_item['progress'] ?? ($goal_item['progress_percent'] ?? 0));
        $goal_status = strtolower((string)($goal_item['status'] ?? 'active'));
        if ($goal_status !== 'completed' && $goal_progress < 100) {
            $journey_active_goal_index = intval($goal_index);
            $journey_active_goal = $goal_item;
            break;
        }
    }
    if ($journey_active_goal === null && !empty($hair_goals)) {
        $journey_active_goal_index = 0;
        $journey_active_goal = $hair_goals[0];
    }
}

$resolve_journey_page_url = static function ($shortcode, $fallback_path) use ($wpdb) {
    $shortcode_candidates = [$shortcode];
    if (strpos($shortcode, '-') !== false) {
        $shortcode_candidates[] = str_replace('-', '_', $shortcode);
    }

    foreach ($shortcode_candidates as $candidate) {
        $page_id = $wpdb->get_var($wpdb->prepare(
            "SELECT ID
             FROM {$wpdb->posts}
             WHERE post_type = 'page'
               AND post_status = 'publish'
               AND post_content LIKE %s
             ORDER BY ID ASC
             LIMIT 1",
            '%[' . $wpdb->esc_like($candidate) . '%'
        ));

        if ($page_id) {
            $permalink = get_permalink(intval($page_id));
            if ($permalink) {
                return $permalink;
            }
        }
    }

    return home_url($fallback_path);
};

$journey_goals_page_url = $resolve_journey_page_url('myavana_goals_page', '/goals/');
$journey_routines_page_url = $resolve_journey_page_url('myavana_routines_page', '/routines/');

$journey_routine_icon_svgs = [
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6 19c8 0 12-8 12-14C10 5 6 11 6 19z"/><path d="M6 19c-1.2-3.6.5-6.6 4-8.5"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v6"/><path d="M12 15v6"/><path d="M3 12h6"/><path d="M15 12h6"/><circle cx="12" cy="12" r="3.5"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2v20"/><path d="M5 8h14"/><path d="M7 16h10"/></svg>',
    '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h14"/><path d="M12 5l7 7-7 7"/></svg>',
];
$journey_today_routines = array_slice(is_array($current_routine) ? $current_routine : [], 0, 4, true);
$journey_today_total = count($journey_today_routines);
$journey_today_done = 0;
foreach ($journey_today_routines as $routine_index => $routine_item) {
    if (in_array(intval($routine_index), $journey_completed_today_routines, true)) {
        $journey_today_done++;
    }
}
$journey_today_pct = $journey_today_total > 0 ? round(($journey_today_done / $journey_today_total) * 100) : 0;
$journey_analysis_remaining = max(0, intval($analysis_limit_info['remaining'] ?? 0));
$journey_active_goal_title = $journey_active_goal['title'] ?? $journey_active_goal['goal'] ?? 'Set your first goal';
$journey_active_goal_progress = intval($journey_active_goal['progress'] ?? ($journey_active_goal['progress_percent'] ?? 0));
?>
<div>
    <section class="journey-header-shell-hjn" style="margin-bottom:0.5rem;">
        <header class="dashboard-header dashboard-header-compact-hjn" style="display:flex; justify-content:space-between; align-items:center; padding-bottom:1rem;">
            <div class="journey-command-copy-hjn">
                <h1 class="welcome-title" style="margin-bottom:0;">Timeline Feed</h1>
                <p class="welcome-subtitle" style="margin-top:0.25rem;">Your comprehensive hair history</p>
            </div>
            
            <div class="action-buttons">
                <button type="button" class="journey-rewards-toggle-hjn" id="journeyRewardsToggle" style="margin:0;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.3L12 17l-6.2 4.2 2.4-7.3L2 9.4h7.6z"/></svg>
                    Quests &amp; Rewards
                    <span class="toggle-arrow">▼</span>
                </button>
            </div>
        </header>

    <!-- Rewards / Progress Drawer (collapsed by default) -->
    <div class="journey-rewards-drawer-hjn" id="journeyRewardsDrawer">
    <section class="journey-rewards-grid-hjn">
        <article class="journey-reward-panel-hjn">
            <div class="journey-reward-panel-head-hjn">
                <div>
                    <span class="journey-reward-panel-kicker-hjn">Today</span>
                    <h3>Daily Quests</h3>
                </div>
                <span class="journey-reward-panel-meta-hjn" data-gamification-quest-meta="daily"><?php echo esc_html($journey_daily_completed); ?>/<?php echo esc_html(count($journey_daily_quests)); ?></span>
            </div>
            <div class="journey-quest-list-hjn" data-gamification-quest-group="daily">
                <?php foreach ($journey_daily_quests as $quest): ?>
                <?php
                $quest_current = intval($quest['current'] ?? 0);
                $quest_target = max(1, intval($quest['target'] ?? 1));
                $quest_done = $quest_current >= $quest_target;
                $quest_pct = min(100, intval(round(($quest_current / $quest_target) * 100)));
                ?>
                <div class="journey-quest-item-hjn<?php echo $quest_done ? ' is-complete' : ''; ?>">
                    <div class="journey-quest-copy-hjn">
                        <strong><?php echo esc_html($quest['label'] ?? 'Quest'); ?></strong>
                        <span><?php echo esc_html($quest['description'] ?? ''); ?></span>
                    </div>
                    <div class="journey-quest-progress-hjn">
                        <span class="journey-quest-count-hjn"><?php echo esc_html($quest_current); ?>/<?php echo esc_html($quest_target); ?></span>
                        <div class="journey-quest-bar-hjn"><span style="width:<?php echo esc_attr($quest_pct); ?>%"></span></div>
                        <small><?php echo esc_html($quest['reward_label'] ?? ''); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="journey-reward-panel-hjn">
            <div class="journey-reward-panel-head-hjn">
                <div>
                    <span class="journey-reward-panel-kicker-hjn">This Week</span>
                    <h3>Momentum Goals</h3>
                </div>
                <span class="journey-reward-panel-meta-hjn" data-gamification-quest-meta="weekly"><?php echo esc_html($journey_weekly_completed); ?>/<?php echo esc_html(count($journey_weekly_quests)); ?></span>
            </div>
            <div class="journey-quest-list-hjn" data-gamification-quest-group="weekly">
                <?php foreach ($journey_weekly_quests as $quest): ?>
                <?php
                $quest_current = intval($quest['current'] ?? 0);
                $quest_target = max(1, intval($quest['target'] ?? 1));
                $quest_done = $quest_current >= $quest_target;
                $quest_pct = min(100, intval(round(($quest_current / $quest_target) * 100)));
                ?>
                <div class="journey-quest-item-hjn<?php echo $quest_done ? ' is-complete' : ''; ?>">
                    <div class="journey-quest-copy-hjn">
                        <strong><?php echo esc_html($quest['label'] ?? 'Quest'); ?></strong>
                        <span><?php echo esc_html($quest['description'] ?? ''); ?></span>
                    </div>
                    <div class="journey-quest-progress-hjn">
                        <span class="journey-quest-count-hjn"><?php echo esc_html($quest_current); ?>/<?php echo esc_html($quest_target); ?></span>
                        <div class="journey-quest-bar-hjn"><span style="width:<?php echo esc_attr($quest_pct); ?>%"></span></div>
                        <small><?php echo esc_html($quest['reward_label'] ?? ''); ?></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </article>

        <article class="journey-reward-panel-hjn">
            <div class="journey-reward-panel-head-hjn">
                <div>
                    <span class="journey-reward-panel-kicker-hjn">Live</span>
                    <h3>Challenges</h3>
                </div>
            </div>
            <div class="journey-quest-list-hjn" data-gamification-active-challenges>
                <?php if (!empty($journey_active_challenges)): ?>
                    <?php foreach (array_slice($journey_active_challenges, 0, 3) as $challenge): ?>
                    <div class="journey-quest-item-hjn<?php echo !empty($challenge['completed']) ? ' is-complete' : ''; ?>">
                        <div class="journey-quest-copy-hjn">
                            <strong><?php echo esc_html($challenge['title'] ?? 'Challenge'); ?></strong>
                            <span><?php echo esc_html($challenge['description'] ?? ''); ?><?php echo !empty($challenge['window_label']) ? ' · ' . esc_html($challenge['window_label']) : ''; ?></span>
                        </div>
                        <div class="journey-quest-progress-hjn">
                            <span class="journey-quest-count-hjn"><?php echo esc_html(intval($challenge['current'] ?? 0)); ?>/<?php echo esc_html(intval($challenge['target'] ?? 1)); ?></span>
                            <div class="journey-quest-bar-hjn"><span style="width:<?php echo esc_attr(intval($challenge['progress_percent'] ?? 0)); ?>%"></span></div>
                            <small>+<?php echo esc_html(intval($challenge['reward_points'] ?? 0)); ?> pts</small>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="journey-reward-empty-hjn">No active challenges configured yet.</div>
                <?php endif; ?>
            </div>
        </article>

        <article class="journey-reward-panel-hjn">
            <div class="journey-reward-panel-head-hjn">
                <div>
                    <span class="journey-reward-panel-kicker-hjn">Rewards</span>
                    <h3>Recent Wins</h3>
                </div>
            </div>
            <div class="journey-reward-feed-hjn" data-gamification-recent-rewards>
                <?php if (!empty($journey_recent_rewards)): ?>
                    <?php foreach ($journey_recent_rewards as $reward): ?>
                    <div class="journey-reward-feed-item-hjn">
                        <div>
                            <strong><?php echo esc_html($reward['reason'] ?? 'Reward earned'); ?></strong>
                            <span><?php echo esc_html($reward['relative_time'] ?? ''); ?></span>
                        </div>
                        <em>+<?php echo esc_html(intval($reward['points_change'] ?? 0)); ?></em>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="journey-reward-empty-hjn">Complete actions to unlock your first rewards.</div>
                <?php endif; ?>
            </div>
        </article>
    </section>
    </div><!-- /.journey-rewards-drawer-hjn -->

    </section>

    <div class="main-content">
       

        <!-- Compare Analysis Modal -->
        <div id="compareAnalysisModal" class="modal">
            <div class="modal-content" style="max-width: 1200px;">
                <div class="modal-header">
                    <h2 style="font-family: 'Archivo Black', sans-serif; color: var(--myavana-onyx); margin: 0;">Compare Hair Analyses</h2>
                    <span class="modal-close" onclick="closeCompareModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="compare-selection" style="margin-bottom: 2rem;">
                        <p style="color: var(--myavana-blueberry); margin-bottom: 1rem;">Select two analyses to compare:</p>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                            <div>
                                <label style="font-family: 'Archivo', sans-serif; font-weight: 600; color: var(--myavana-onyx); display: block; margin-bottom: 0.5rem;">First Analysis</label>
                                <select id="compareAnalysis1" class="compare-select" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: 'Archivo', sans-serif;">
                                    <option value="">Select an analysis...</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-family: 'Archivo', sans-serif; font-weight: 600; color: var(--myavana-onyx); display: block; margin-bottom: 0.5rem;">Second Analysis</label>
                                <select id="compareAnalysis2" class="compare-select" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border-color); border-radius: 8px; font-family: 'Archivo', sans-serif;">
                                    <option value="">Select an analysis...</option>
                                </select>
                            </div>
                        </div>
                        <button id="startComparison" onclick="generateComparison()" style="margin-top: 1.5rem; padding: 0.75rem 2rem; background: var(--myavana-coral); color: var(--myavana-white); border: none; border-radius: 8px; font-family: 'Archivo', sans-serif; font-weight: 600; cursor: pointer; transition: all 0.3s;">
                            Compare Selected Analyses
                        </button>
                    </div>
                    <div id="comparisonResults" style="display: none;">
                        <!-- Comparison results will be populated here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Edit Offcanvas -->
        <div class="offcanvas-overlay-hjn profile-edit-overlay"></div>
        <div class="offcanvas-hjn profile-edit">
            <div class="offcanvas-header-hjn">
                <h3 class="offcanvas-title-hjn">Edit Profile</h3>
                <button class="offcanvas-close-hjn">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="offcanvas-body-hjn">
                <form id="sidebar-profile-edit-form">
                    <!-- Bio Section -->
                    <div class="sidebar-edit-section">
                        <h4 class="sidebar-edit-title">About Me</h4>
                        <textarea
                            id="sidebar-bio"
                            name="bio"
                            class="sidebar-edit-textarea"
                            rows="3"
                            placeholder="Tell us about your hair journey..."><?php echo esc_textarea($about_me); ?></textarea>
                    </div>

                    <!-- Hair Profile Section -->
                    <div class="sidebar-edit-section">
                        <h4 class="sidebar-edit-title">Hair Profile</h4>
                        <div class="sidebar-edit-grid">
                            <div class="sidebar-edit-field">
                                <label for="sidebar-hair-type" class="sidebar-edit-label">Hair Type</label>
                                <select id="sidebar-hair-type" name="hair_type" class="sidebar-edit-select">
                                    <option value="">Select type...</option>
                                    <option value="1A" <?php selected($profile->hair_type, '1A'); ?>>1A - Straight, Fine</option>
                                    <option value="1B" <?php selected($profile->hair_type, '1B'); ?>>1B - Straight, Medium</option>
                                    <option value="1C" <?php selected($profile->hair_type, '1C'); ?>>1C - Straight, Coarse</option>
                                    <option value="2A" <?php selected($profile->hair_type, '2A'); ?>>2A - Wavy, Fine</option>
                                    <option value="2B" <?php selected($profile->hair_type, '2B'); ?>>2B - Wavy, Medium</option>
                                    <option value="2C" <?php selected($profile->hair_type, '2C'); ?>>2C - Wavy, Coarse</option>
                                    <option value="3A" <?php selected($profile->hair_type, '3A'); ?>>3A - Curly, Loose</option>
                                    <option value="3B" <?php selected($profile->hair_type, '3B'); ?>>3B - Curly, Tight</option>
                                    <option value="3C" <?php selected($profile->hair_type, '3C'); ?>>3C - Curly, Corkscrew</option>
                                    <option value="4A" <?php selected($profile->hair_type, '4A'); ?>>4A - Coily, Soft</option>
                                    <option value="4B" <?php selected($profile->hair_type, '4B'); ?>>4B - Coily, Wiry</option>
                                    <option value="4C" <?php selected($profile->hair_type, '4C'); ?>>4C - Coily, Very Wiry</option>
                                </select>
                            </div>

                            <div class="sidebar-edit-field">
                                <label for="sidebar-hair-porosity" class="sidebar-edit-label">Porosity</label>
                                <select id="sidebar-hair-porosity" name="hair_porosity" class="sidebar-edit-select">
                                    <option value="">Select...</option>
                                    <option value="Low" <?php selected($hair_porosity, 'Low'); ?>>Low</option>
                                    <option value="Medium" <?php selected($hair_porosity, 'Medium'); ?>>Medium</option>
                                    <option value="High" <?php selected($hair_porosity, 'High'); ?>>High</option>
                                </select>
                            </div>

                            <div class="sidebar-edit-field">
                                <label for="sidebar-hair-length" class="sidebar-edit-label">Length</label>
                                <select id="sidebar-hair-length" name="hair_length" class="sidebar-edit-select">
                                    <option value="">Select...</option>
                                    <option value="Ear Length" <?php selected($hair_length, 'Ear Length'); ?>>Ear Length</option>
                                    <option value="Chin Length" <?php selected($hair_length, 'Chin Length'); ?>>Chin Length</option>
                                    <option value="Shoulder Length" <?php selected($hair_length, 'Shoulder Length'); ?>>Shoulder Length</option>
                                    <option value="Mid-Back" <?php selected($hair_length, 'Mid-Back'); ?>>Mid-Back</option>
                                    <option value="Waist Length" <?php selected($hair_length, 'Waist Length'); ?>>Waist Length</option>
                                    <option value="Hip Length" <?php selected($hair_length, 'Hip Length'); ?>>Hip Length</option>
                                </select>
                            </div>

                            <div class="sidebar-edit-field">
                                <label for="sidebar-journey-stage" class="sidebar-edit-label">Journey Stage</label>
                                <select id="sidebar-journey-stage" name="journey_stage" class="sidebar-edit-select">
                                    <option value="Not set" <?php selected($profile->hair_journey_stage, 'Not set'); ?>>Not set</option>
                                    <option value="Postpartum haircare" <?php selected($profile->hair_journey_stage, 'Postpartum haircare'); ?>>Postpartum haircare</option>
                                    <option value="Nourishing and growing" <?php selected($profile->hair_journey_stage, 'Nourishing and growing'); ?>>Nourishing and growing</option>
                                    <option value="Experimenting" <?php selected($profile->hair_journey_stage, 'Experimenting'); ?>>Experimenting</option>
                                    <option value="Bored/Stuck" <?php selected($profile->hair_journey_stage, 'Bored/Stuck'); ?>>Bored/Stuck</option>
                                    <option value="Repairing and restoring" <?php selected($profile->hair_journey_stage, 'Repairing and restoring'); ?>>Repairing and restoring</option>
                                    <option value="Desperate for a change" <?php selected($profile->hair_journey_stage, 'Desperate for a change'); ?>>Desperate for a change</option>
                                    <option value="Trying something new" <?php selected($profile->hair_journey_stage, 'Trying something new'); ?>>Trying something new</option>
                                    <option value="Loving my recent hairstyle change" <?php selected($profile->hair_journey_stage, 'Loving my recent hairstyle change'); ?>>Loving my recent hairstyle change</option>
                                </select>
                            </div>
                            <div class="sidebar-edit-field" style="margin-top: 8px;">
                                <label for="birthday" class="form-label">Birthday</label>
                                <input type="date" id="birthday" name="birthday" value="<?php echo esc_attr($profile->birthday ?? ''); ?>" class="form-input">
                            </div>
                            <div class="sidebar-edit-field">
                                <label for="location" class="form-label">Location (City, State)</label>
                                <input type="text" id="location" name="location" value="<?php echo esc_attr($profile->location ?? ''); ?>" class="form-input" placeholder="e.g., Atlanta, GA">
                            </div>
                            <div class="sidebar-edit-field">
                                <label for="life_journey_stage" class="form-label">Life Journey Stage</label>
                                <input type="text" id="life_journey_stage" name="life_journey_stage" value="<?php echo esc_attr($profile->life_journey_stage ?? ''); ?>" class="form-input" placeholder="e.g., New mother, Career change">
                            </div>
                            <div class="sidebar-edit-field" style="margin-top: 8px;">
                                <label for="hair_health_rating" class="form-label">Hair Health Rating (1-5)</label>
                                <input type="number" id="hair_health_rating" name="hair_health_rating" min="1" max="5" step="1" value="<?php echo esc_attr($profile->hair_health_rating ?? 5); ?>" class="form-input" disabled required>
                            </div>
                        </div>
                    </div>

                    <!-- Goals Section -->
                    <div class="sidebar-edit-section">
                        <div class="sidebar-edit-section-header">
                            <h4 class="sidebar-edit-title">Hair Goals</h4>
                            <button type="button" class="sidebar-add-btn add-goal-btn-sidebar">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Add Goal
                            </button>
                        </div>
                        <div id="sidebar-goals-edit-list" class="sidebar-edit-list">
                            <?php
                            $hair_goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true) ?: [];
                            if (!empty($hair_goals)) {
                                foreach ($hair_goals as $index => $goal) {
                                    ?>
                                    <div class="sidebar-goal-edit-item">
                                        <span class="sidebar-goal-edit-text"><?php echo esc_html($goal['title']); ?></span>
                                        <button type="button" class="sidebar-goal-remove-btn remove-goal-chip" data-index="<?php echo $index; ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Routine Section -->
                    <div class="sidebar-edit-section">
                        <div class="sidebar-edit-section-header">
                            <h4 class="sidebar-edit-title">Current Routine</h4>
                            <button type="button" class="sidebar-add-btn add-routine-btn-sidebar">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="12" y1="5" x2="12" y2="19"></line>
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                </svg>
                                Add Step
                            </button>
                        </div>
                        <div id="sidebar-routine-edit-list" class="sidebar-edit-list">
                            <?php
                            $routine = get_user_meta($user_id, 'current_routine', true);
                            if (!empty($routine) && is_array($routine)) {
                                foreach ($routine as $index => $step) {
                                    $step_name = is_array($step) ? ($step['name'] ?? '') : $step;
                                    $step_frequency = is_array($step) ? ($step['frequency'] ?? 'daily') : 'daily';
                                    $freq_label = [
                                        'daily' => 'Daily',
                                        'weekly' => 'Weekly',
                                        'biweekly' => 'Bi-weekly',
                                        'monthly' => 'Monthly',
                                        'asneeded' => 'As Needed'
                                    ][$step_frequency] ?? ucfirst($step_frequency);
                                    ?>
                                    <div class="sidebar-routine-edit-item">
                                        <div class="sidebar-routine-edit-info">
                                            <span class="sidebar-routine-edit-name"><?php echo esc_html($step_name); ?></span>
                                            <span class="sidebar-routine-edit-freq"><?php echo esc_html($freq_label); ?></span>
                                        </div>
                                        <button type="button" class="sidebar-routine-remove-btn remove-routine-item" data-index="<?php echo $index; ?>">
                                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                                <line x1="6" y1="6" x2="18" y2="18"></line>
                                            </svg>
                                        </button>
                                    </div>
                                    <?php
                                }
                            }
                            ?>
                        </div>
                    </div>
                </form>
            </div>
            <div class="offcanvas-footer-hjn">
                <button type="button" class="btn-secondary-hjn" id="cancel-profile-edit">
                    Cancel
                </button>
                <button type="submit" form="sidebar-profile-edit-form" class="btn-primary-hjn" id="save-profile-edit">
                    Save Changes
                </button>
            </div>
        </div>

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

    

    <script>
        /**
         * MYAVANA Timeline - UI State Management Module
         * Handles dark mode, sidebar, theme persistence, and responsive behavior
         *
         * @package Myavana_Hair_Journey
         * @version 2.3.5
         */

        // Initialize namespace if not exists
        window.MyavanaTimeline = window.MyavanaTimeline || {};

        // UI State Module
        MyavanaTimeline.UI = (function() {
            'use strict';

            /**
             * Toggle dark mode theme
             */
            function toggleDarkMode() {
                const container = document.querySelector('.hair-journey-container');
                const sunIcon = document.querySelector('.sun-icon');
                const moonIcon = document.querySelector('.moon-icon');

                if (!container) {
                    console.error('[Dark Mode] Container .hair-journey-container not found');
                    return;
                }

                if (!sunIcon || !moonIcon) {
                    console.error('[Dark Mode] Sun or moon icon not found');
                    return;
                }

                const currentTheme = container.getAttribute('data-theme') || 'light';
                const newTheme = currentTheme === 'light' ? 'dark' : 'light';

                console.log(`[Dark Mode] Switching from ${currentTheme} to ${newTheme}`);

                container.setAttribute('data-theme', newTheme);

                if (newTheme === 'dark') {
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'block';
                } else {
                    sunIcon.style.display = 'block';
                    moonIcon.style.display = 'none';
                }

                // Save preference to localStorage
                localStorage.setItem('theme', newTheme);
                console.log('[Dark Mode] Theme saved to localStorage:', newTheme);
            }

            /**
             * Load theme preference from localStorage
             */
            function loadTheme() {
                const savedTheme = localStorage.getItem('theme') || 'light';
                const container = document.querySelector('.hair-journey-container');
                const sunIcon = document.querySelector('.sun-icon');
                const moonIcon = document.querySelector('.moon-icon');

                if (!container) {
                    console.error('[Load Theme] Container .hair-journey-container not found');
                    return;
                }

                if (!sunIcon || !moonIcon) {
                    console.error('[Load Theme] Sun or moon icon not found');
                    return;
                }

                console.log('[Load Theme] Loading saved theme:', savedTheme);

                container.setAttribute('data-theme', savedTheme);

                if (savedTheme === 'dark') {
                    sunIcon.style.display = 'none';
                    moonIcon.style.display = 'block';
                } else {
                    sunIcon.style.display = 'block';
                    moonIcon.style.display = 'none';
                }
            }

            /**
             * Toggle sidebar collapse (desktop only)
             */
            function toggleSidebar() {
                // Disable collapsing on mobile (screens <= 1024px)
                if (window.innerWidth <= 1024) {
                    return;
                }

                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebarToggle');

                sidebar.classList.toggle('collapsed');

                if (sidebar.classList.contains('collapsed')) {
                    toggle.innerHTML = '›';
                    toggle.style.left = '10px';
                    // Save collapsed state
                    localStorage.setItem('sidebarCollapsed', 'true');
                } else {
                    toggle.innerHTML = '‹';
                    toggle.style.left = '400px';
                    // Save expanded state
                    localStorage.setItem('sidebarCollapsed', 'false');
                }
            }

            /**
             * Switch between sidebar tabs
             */
            function switchSidebarTab(tabName) {
                // Update tab buttons
                document.querySelectorAll('.sidebar-tab').forEach(tab => {
                    tab.classList.remove('active');
                });
                document.querySelector(`.sidebar-tab[data-tab="${tabName}"]`).classList.add('active');

                // Update tab content
                document.querySelectorAll('.sidebar-tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabName + 'Tab').classList.add('active');

                // Save active tab preference
                localStorage.setItem('activeSidebarTab', tabName);
            }

            /**
             * Load sidebar state from localStorage
             */
            function loadSidebarState() {
                const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                const activeSidebarTab = localStorage.getItem('activeSidebarTab') || 'insights';

                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebarToggle');

                // Only restore collapsed state on desktop (> 1024px)
                if (window.innerWidth > 1024) {
                    if (sidebarCollapsed) {
                        sidebar.classList.add('collapsed');
                        toggle.innerHTML = '›';
                        toggle.style.left = '10px';
                    } else {
                        toggle.style.left = '400px';
                    }
                } else {
                    // On mobile, always show sidebar expanded
                    sidebar.classList.remove('collapsed');
                }

                // Restore active tab
                switchSidebarTab(activeSidebarTab);
            }

            /**
             * Handle window resize events
             */
            function handleResize() {
                const sidebar = document.getElementById('sidebar');
                const toggle = document.getElementById('sidebarToggle');

                if (window.innerWidth <= 1024) {
                    // On mobile, remove desktop collapsed class
                    sidebar.classList.remove('collapsed');
                    // Restore mobile collapsed state
                    const mobileCollapsed = localStorage.getItem('mobileSidebarCollapsed') === 'true';
                    if (mobileCollapsed) {
                        sidebar.classList.add('mobile-collapsed');
                    } else {
                        sidebar.classList.remove('mobile-collapsed');
                    }
                } else {
                    // On desktop, remove mobile collapsed class
                    sidebar.classList.remove('mobile-collapsed');
                    // Restore desktop collapsed state from localStorage
                    const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
                    if (sidebarCollapsed) {
                        sidebar.classList.add('collapsed');
                        toggle.innerHTML = '›';
                        toggle.style.left = '10px';
                    } else {
                        sidebar.classList.remove('collapsed');
                        toggle.innerHTML = '‹';
                        toggle.style.left = '400px';
                    }
                }
            }

            /**
             * Reset sidebar state (for debugging)
             */
            function resetSidebar() {
                localStorage.removeItem('sidebarCollapsed');
                localStorage.removeItem('mobileSidebarCollapsed');
                localStorage.removeItem('activeSidebarTab');
                location.reload();
            }

            /**
             * Toggle mobile sidebar (accordion style)
             */
            function toggleMobileSidebar() {
                console.log('toggleMobileSidebar in timeline-ui-state.js');
                // Only work on mobile/tablet screens
                if (window.innerWidth > 1024) {
                    return;
                }

                const sidebar = document.getElementById('sidebar');
                const icon = document.getElementById('mobileSidebarIcon');
                console.log('sidebar.classList.toggle("mobile-collapsed")', sidebar.classList.toggle('mobile-collapsed'));

                sidebar.classList.toggle('mobile-collapsed');
                console.log('sidebar.classList.toggle("mobile-collapsed")', sidebar.classList.toggle('mobile-collapsed'));
                // Save mobile collapsed state
                const isCollapsed = sidebar.classList.contains('mobile-collapsed');
                localStorage.setItem('mobileSidebarCollapsed', isCollapsed);
            }

            /**
             * Load mobile sidebar state from localStorage
             */
            function loadMobileSidebarState() {
                if (window.innerWidth <= 1024) {
                    const mobileCollapsed = localStorage.getItem('mobileSidebarCollapsed') === 'true';
                    const sidebar = document.getElementById('sidebar');

                    if (mobileCollapsed) {
                        sidebar.classList.add('mobile-collapsed');
                    }
                }
            }

            /**
             * Edit profile handler
             */
            function editProfile() {
                alert('Edit Profile: Opens modal to edit hair type, porosity, goals, and regimen details!');
            }

            /**
             * Initialize UI state module
             */
            function init() {
                loadTheme();
                loadSidebarState();
                loadMobileSidebarState();

                // Setup resize handler
                window.addEventListener('resize', handleResize);
            }

            // Public API
            return {
                init: init,
                toggleDarkMode: toggleDarkMode,
                loadTheme: loadTheme,
                toggleSidebar: toggleSidebar,
                switchSidebarTab: switchSidebarTab,
                loadSidebarState: loadSidebarState,
                handleResize: handleResize,
                resetSidebar: resetSidebar,
                toggleMobileSidebar: toggleMobileSidebar,
                loadMobileSidebarState: loadMobileSidebarState,
                editProfile: editProfile
            };
        })();

        // Expose global functions for backward compatibility
        window.toggleDarkMode = MyavanaTimeline.UI.toggleDarkMode;
        window.toggleSidebar = MyavanaTimeline.UI.toggleSidebar;
        window.switchSidebarTab = MyavanaTimeline.UI.switchSidebarTab;
        window.toggleMobileSidebar = MyavanaTimeline.UI.toggleMobileSidebar;
        window.editProfile = MyavanaTimeline.UI.editProfile;
        window.resetSidebar = MyavanaTimeline.UI.resetSidebar;

        function updateJourneyHubRoutineProgress() {
            var routineList = document.getElementById('journeyHubRoutineList');
            var progressLabel = document.getElementById('journeyHubRoutineProgressLabel');
            var progressBar = document.getElementById('journeyHubRoutineProgressBar');
            if (!routineList || !progressLabel || !progressBar) {
                return;
            }

            var totalButtons = routineList.querySelectorAll('[data-routine-complete-toggle]').length;
            if (!totalButtons) {
                return;
            }
            var doneButtons = routineList.querySelectorAll('[data-routine-complete-toggle].is-complete').length;
            var pct = Math.round((doneButtons / totalButtons) * 100);

            progressLabel.textContent = doneButtons + ' of ' + totalButtons + ' done';
            progressBar.style.width = pct + '%';
        }

        document.addEventListener('DOMContentLoaded', updateJourneyHubRoutineProgress);
        document.addEventListener('click', function(event) {
            var toggleBtn = event.target.closest('[data-routine-complete-toggle]');
            if (!toggleBtn) {
                return;
            }
            setTimeout(updateJourneyHubRoutineProgress, 120);
        });

        // === Compact Redesign: Hub Drawer & Rewards Toggle ===
        (function () {
            // Hub Side Drawer removed in sidebar redesign

            // Rewards / Progress Drawer Toggle
            var rewardsToggle = document.getElementById('journeyRewardsToggle');
            var rewardsDrawer = document.getElementById('journeyRewardsDrawer');

            if (rewardsToggle && rewardsDrawer) {
                rewardsToggle.addEventListener('click', function () {
                    var isOpen = rewardsDrawer.classList.toggle('is-open');
                    rewardsToggle.classList.toggle('is-open', isOpen);
                });
            }
        })();

    </script>
