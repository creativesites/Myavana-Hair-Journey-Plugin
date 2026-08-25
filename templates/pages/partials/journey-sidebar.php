<?php
/**
 * Journey Sidebar Partial
 * 
 * Uses shared_data passed from hair-journey.php
 */
if (!isset($user_id) || !$user_id) {
    return;
}

// Gamification / Leveling data
$journey_gamification = $shared_data['gamification'] ?? [];
$journey_level = isset($journey_gamification['level']) ? intval($journey_gamification['level']) : 1;
$journey_xp_pct = isset($journey_gamification['xp_progress_percent']) ? intval($journey_gamification['xp_progress_percent']) : 0;
$journey_points_to_next = isset($journey_gamification['points_to_next_level']) ? intval($journey_gamification['points_to_next_level']) : 100;
$journey_checked_in = !empty($journey_gamification['checked_in_today']);

// Goals
$hair_goals = $shared_data['hair_goals'] ?? [];
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
}

// Routines
$current_routine = $shared_data['current_routine'] ?? [];
$journey_routine_completion_map = get_user_meta($user_id, 'myavana_routine_completions', true);
if (!is_array($journey_routine_completion_map)) {
    $journey_routine_completion_map = [];
}
$journey_today_date = current_time('Y-m-d');
$journey_completed_today_routines = isset($journey_routine_completion_map[$journey_today_date]) && is_array($journey_routine_completion_map[$journey_today_date])
    ? array_values(array_map('intval', $journey_routine_completion_map[$journey_today_date]))
    : [];

$journey_today_routines = array_slice(is_array($current_routine) ? $current_routine : [], 0, 4, true);
$journey_today_total = count($journey_today_routines);
$journey_today_done = 0;
foreach ($journey_today_routines as $routine_index => $routine_item) {
    if (in_array(intval($routine_index), $journey_completed_today_routines, true)) {
        $journey_today_done++;
    }
}

// Mini Calendar calculations
$mini_cal_month = date('n');
$mini_cal_year = date('Y');
$mini_cal_day = date('j');
$mini_cal_first_day = mktime(0, 0, 0, $mini_cal_month, 1, $mini_cal_year);
$mini_cal_days_in_month = date('t', $mini_cal_first_day);
$mini_cal_start_day = (date('w', $mini_cal_first_day) == 0) ? 7 : date('w', $mini_cal_first_day);
$mini_cal_offset = $mini_cal_start_day - 1;
?>

<aside class="journey-sidebar-hjn">
    <!-- Profile Snapshot -->
    <div class="sidebar-profile-hjn">
        <div class="sidebar-avatar-wrap-hjn">
            <?php 
            $avatar_html = get_avatar($user_id, 80);
            if ($avatar_html) {
                echo $avatar_html;
            } else {
                echo esc_html(strtoupper(substr(get_user_by('id', $user_id)->display_name ?? 'U', 0, 1)));
            }
            ?>
        </div>
        <h2 class="sidebar-profile-name-hjn"><?php echo esc_html(get_user_by('id', $user_id)->display_name ?? 'User'); ?></h2>
        <div class="sidebar-profile-level-hjn">Level <?php echo esc_html($journey_level); ?></div>
        
        <div class="journey-xp-card-hjn" style="margin-top:1rem; text-align:left;">
            <div class="journey-xp-row-hjn">
                <span class="journey-xp-label-hjn">Level Progress</span>
                <span class="journey-xp-meta-hjn"><?php echo esc_html($journey_xp_pct); ?>%</span>
            </div>
            <div class="journey-xp-track-hjn">
                <div class="journey-xp-fill-hjn" style="width:<?php echo esc_attr($journey_xp_pct); ?>%"></div>
            </div>
            <div style="font-size:0.75rem; color:var(--myavana-subtle); margin-top:0.3rem;">
                <?php echo esc_html($journey_points_to_next); ?> points to next level
            </div>
        </div>
    </div>

    <!-- Primary Action -->
    <div class="sidebar-section-hjn" style="border:none; padding-top:0;">
        <button class="sidebar-action-btn-hjn" onclick="createEntry()">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add New Entry
        </button>
        <?php if (!$journey_checked_in): ?>
        <button type="button" class="sidebar-action-btn-hjn" id="myavana-checkin-btn" style="background:var(--myavana-onyx); margin-top:0.75rem;">
            Check In Today
        </button>
        <?php endif; ?>
    </div>

    <!-- Mini Calendar -->
    <div class="sidebar-section-hjn sidebar-mini-calendar-hjn">
        <div class="mini-calendar-header-hjn">
            <span><?php echo date('F Y'); ?></span>
         </div>
        <div class="mini-calendar-grid-hjn">
            <div class="mini-calendar-weekday">Mo</div>
            <div class="mini-calendar-weekday">Tu</div>
            <div class="mini-calendar-weekday">We</div>
            <div class="mini-calendar-weekday">Th</div>
            <div class="mini-calendar-weekday">Fr</div>
            <div class="mini-calendar-weekday">Sa</div>
            <div class="mini-calendar-weekday">Su</div>
            
            <?php for ($i = 0; $i < $mini_cal_offset; $i++): ?>
                <div></div>
            <?php endfor; ?>
            
            <?php for ($d = 1; $d <= $mini_cal_days_in_month; $d++): 
                $is_today = ($d == $mini_cal_day) ? 'is-today' : '';
            ?>
                <div class="mini-calendar-day-hjn <?php echo $is_today; ?>"><?php echo $d; ?></div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Active Goal (At a glance) -->
    <div class="sidebar-section-hjn">
        <h4 class="sidebar-section-title-hjn">Active Goal</h4>
        <?php if ($journey_active_goal): 
            $goal_title = $journey_active_goal['title'] ?? $journey_active_goal['goal_title'] ?? 'Untitled Goal';
            $goal_progress = intval($journey_active_goal['progress'] ?? ($journey_active_goal['progress_percent'] ?? 0));
        ?>
            <div class="journey-hub-goal-title-hjn"><?php echo esc_html($goal_title); ?></div>
            <div class="journey-hub-goal-progress-row-hjn" style="margin-top:0.5rem;">
                <div class="journey-hub-goal-progress-track-hjn" style="flex:1;">
                    <div class="journey-hub-goal-progress-fill-hjn" style="width:<?php echo esc_attr(max(0, min(100, $goal_progress))); ?>%"></div>
                </div>
                <div class="journey-hub-goal-progress-val-hjn" style="margin-left:8px; font-weight:bold; font-size:0.85rem;"><?php echo esc_html($goal_progress); ?>%</div>
            </div>
            <button class="journey-spotlight-btn-hjn" onclick="createEntry()" style="margin-top:1rem; width:100%;">Log Progress</button>
        <?php else: ?>
            <p style="font-size:0.85rem; color:var(--myavana-subtle);">Set your first goal to start measuring progress.</p>
            <button class="journey-spotlight-btn-hjn is-link" onclick="createGoal()" style="margin-top:0.5rem; width:100%;">+ Create Goal</button>
        <?php endif; ?>
    </div>

    <!-- Today's Routines (At a glance) -->
    <div class="sidebar-section-hjn">
        <h4 class="sidebar-section-title-hjn">Today's Routines (<?php echo $journey_today_done; ?>/<?php echo $journey_today_total; ?>)</h4>
        <?php if (!empty($journey_today_routines)): ?>
            <div class="journey-hub-routine-list-hjn">
                <?php foreach ($journey_today_routines as $routine_index => $routine_item):
                    $routine_title = $routine_item['title'] ?? $routine_item['routine_title'] ?? 'Untitled Routine';
                    $routine_done = in_array(intval($routine_index), $journey_completed_today_routines, true);
                ?>
                <div class="journey-hub-routine-row-hjn" style="padding:0.5rem 0; border:none;">
                    <button type="button" class="journey-hub-action-btn-hjn <?php echo $routine_done ? 'is-complete' : ''; ?>"
                        data-routine-complete-toggle
                        data-routine-id="<?php echo esc_attr(intval($routine_index)); ?>"
                        data-date="<?php echo esc_attr($journey_today_date); ?>"
                        onclick="event.stopPropagation(); toggleRoutineCompletion(<?php echo intval($routine_index); ?>, '<?php echo esc_js($journey_today_date); ?>', this)" 
                        style="padding:4px 8px; font-size:0.75rem; margin-right:8px;">
                        <?php echo $routine_done ? '✓' : 'Mark'; ?>
                    </button>
                    <div class="journey-hub-routine-copy-hjn" style="flex:1;">
                        <div class="journey-hub-routine-name-hjn" style="font-size:0.85rem;"><?php echo esc_html($routine_title); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="font-size:0.85rem; color:var(--myavana-subtle);">No routines scheduled for today.</p>
            <button class="journey-spotlight-btn-hjn is-link" onclick="createRoutine()" style="margin-top:0.5rem; width:100%;">+ Build Routine</button>
        <?php endif; ?>
    </div>
</aside>
