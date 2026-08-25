<?php
/**
 * MYAVANA Goal and Routine Update Handlers
 * Handles CRUD operations for hair goals and routines
 * @version 2.3.8
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('myavana_update_goal')) {

if (!function_exists('myavana_goal_or_routine_key')) {
    function myavana_goal_or_routine_key($prefix, $item = []) {
        if (!empty($item['goal_key'])) {
            return sanitize_key($item['goal_key']);
        }

        if (!empty($item['routine_key'])) {
            return sanitize_key($item['routine_key']);
        }

        return sanitize_key($prefix . '_' . wp_generate_uuid4());
    }
}

if (!function_exists('myavana_normalize_routine_reminder_days')) {
    /**
     * Store reminder days in one predictable format so the schedule has no silent failures.
     * Returns WP_Error when a member supplied an unrecognised day.
     */
    function myavana_normalize_routine_reminder_days($raw_days) {
        $raw_days = trim((string) $raw_days);
        if ($raw_days === '') {
            return '';
        }

        $map = [
            'mon' => 'Mon', 'monday' => 'Mon',
            'tue' => 'Tue', 'tues' => 'Tue', 'tuesday' => 'Tue',
            'wed' => 'Wed', 'wednesday' => 'Wed',
            'thu' => 'Thu', 'thur' => 'Thu', 'thurs' => 'Thu', 'thursday' => 'Thu',
            'fri' => 'Fri', 'friday' => 'Fri',
            'sat' => 'Sat', 'saturday' => 'Sat',
            'sun' => 'Sun', 'sunday' => 'Sun',
        ];
        $days = [];
        foreach (preg_split('/\s*,\s*/', strtolower($raw_days)) as $day) {
            if ($day === '') {
                continue;
            }
            if (!isset($map[$day])) {
                return new \WP_Error('invalid_reminder_day', 'Use day names such as Mon, Wed, Fri for reminder days.');
            }
            $days[$map[$day]] = true;
        }

        return implode(', ', array_keys($days));
    }
}

if (!function_exists('myavana_award_progress_milestones')) {
    function myavana_award_progress_milestones($user_id, $entity_type, $entity_key, $previous_progress, $current_progress, $reference_id = null) {
        $milestones = [25, 50, 75, 100];

        foreach ($milestones as $milestone) {
            if ($previous_progress >= $milestone || $current_progress < $milestone) {
                continue;
            }

            $reward_key = $milestone === 100 ? 'goal_completed' : 'goal_milestone';
            myavana_award_points(
                $user_id,
                Myavana_Gamification::get_reward_value($reward_key, $milestone === 100 ? 35 : 12),
                sprintf('%s milestone reached (%d%%)', ucfirst($entity_type), $milestone),
                $entity_type,
                $reference_id,
                sprintf('%s_milestone:%s:%d', $entity_type, $entity_key, $milestone),
                [
                    'milestone' => $milestone,
                    'entity_key' => $entity_key,
                ]
            );
        }
    }
}

/**
 * Update existing goal
 */
function myavana_update_goal() {
    // Verify nonce
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_update_goal');
    } elseif (isset($_POST['myavana_nonce'])) {
        $nonce_verified = wp_verify_nonce($_POST['myavana_nonce'], 'myavana_add_goal');
    }

    if (!$nonce_verified) {
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    $goal_index = isset($_POST['goal_id']) ? intval($_POST['goal_id']) : -1;

    error_log(sprintf('[MYAVANA] update_goal: goal_index=%d, user_id=%d', $goal_index, $user_id));

    if (!$user_id || $goal_index < 0) {
        wp_send_json_error('Invalid request');
        return;
    }

    // Goals are stored in user meta, not as posts
    $goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
    if (!is_array($goals)) {
        $goals = [];
    }

    if (!isset($goals[$goal_index])) {
        error_log(sprintf('[MYAVANA] update_goal: Goal index %d not found in user goals', $goal_index));
        wp_send_json_error('Goal not found or access denied');
        return;
    }

    // Validate required fields
    $title_input = isset($_POST['title']) ? wp_unslash($_POST['title']) : ($_POST['goal_title'] ?? '');
    if (empty($title_input)) {
        wp_send_json_error('Title is required');
        return;
    }

    // Sanitize input data
    $title = sanitize_text_field($title_input);
    $description = sanitize_textarea_field($_POST['description'] ?? ($_POST['goal_description'] ?? ''));
    $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : 'active';
    $start_date = isset($_POST['start_date']) ? sanitize_text_field($_POST['start_date']) : sanitize_text_field($_POST['goal_start_date'] ?? '');
    $target_date = isset($_POST['target_date']) ? sanitize_text_field($_POST['target_date']) : sanitize_text_field($_POST['goal_end_date'] ?? '');
    $progress = isset($_POST['progress'])
        ? max(0, min(100, intval($_POST['progress'])))
        : max(0, min(100, intval($_POST['goal_progress'] ?? 0)));

    $goal_category = sanitize_text_field($_POST['goal_category'] ?? ($goals[$goal_index]['goal_category'] ?? ''));
    $goal_target = sanitize_text_field($_POST['goal_target'] ?? ($goals[$goal_index]['goal_target'] ?? ''));
    $goal_priority = sanitize_text_field($_POST['goal_priority'] ?? ($_POST['priority'] ?? ($goals[$goal_index]['goal_priority'] ?? 'Medium')));
    $goal_checkin_frequency = sanitize_text_field($_POST['goal_checkin_frequency'] ?? ($goals[$goal_index]['goal_checkin_frequency'] ?? 'Weekly'));
    $goal_motivation = sanitize_textarea_field($_POST['goal_motivation'] ?? ($goals[$goal_index]['goal_motivation'] ?? ''));
    $goal_blockers = sanitize_textarea_field($_POST['goal_blockers'] ?? ($goals[$goal_index]['goal_blockers'] ?? ''));
    $goal_baseline_value = isset($_POST['goal_baseline_value']) && $_POST['goal_baseline_value'] !== ''
        ? floatval($_POST['goal_baseline_value'])
        : ($goals[$goal_index]['goal_baseline_value'] ?? '');
    $goal_target_value = isset($_POST['goal_target_value']) && $_POST['goal_target_value'] !== ''
        ? floatval($_POST['goal_target_value'])
        : ($goals[$goal_index]['goal_target_value'] ?? '');
    $goal_measure_unit = sanitize_text_field($_POST['goal_measure_unit'] ?? ($goals[$goal_index]['goal_measure_unit'] ?? ''));
    $goal_reward = sanitize_text_field($_POST['goal_reward'] ?? ($goals[$goal_index]['goal_reward'] ?? ''));
    $goal_success_criteria = sanitize_textarea_field($_POST['goal_success_criteria'] ?? ($goals[$goal_index]['goal_success_criteria'] ?? ''));
    $milestones_input = $_POST['goal_milestones'] ?? [];
    if (is_string($milestones_input)) {
        $decoded = json_decode(wp_unslash($milestones_input), true);
        if (is_array($decoded)) {
            $milestones_input = $decoded;
        } else {
            $milestones_input = array_filter(array_map('trim', explode(',', wp_unslash($milestones_input))));
        }
    }

    $milestones = [];
    if (is_array($milestones_input)) {
        foreach ($milestones_input as $milestone_item) {
            if (is_array($milestone_item) && isset($milestone_item['text'])) {
                $text = sanitize_text_field($milestone_item['text']);
                $achieved = !empty($milestone_item['achieved']);
            } else {
                $text = sanitize_text_field((string) $milestone_item);
                $achieved = false;
            }
            if ($text !== '') {
                $milestones[] = [
                    'text' => $text,
                    'achieved' => $achieved,
                ];
            }
        }
    }

    $existing_progress = intval($goals[$goal_index]['progress'] ?? 0);
    $goal_key = myavana_goal_or_routine_key('goal', $goals[$goal_index]);
    $progress_note = sanitize_textarea_field($_POST['progress_note'] ?? '');
    $progress_history = isset($goals[$goal_index]['progress_history']) && is_array($goals[$goal_index]['progress_history'])
        ? $goals[$goal_index]['progress_history']
        : [];
    $progress_text = isset($goals[$goal_index]['progress_text']) && is_array($goals[$goal_index]['progress_text'])
        ? $goals[$goal_index]['progress_text']
        : [];

    if ($existing_progress !== $progress) {
        $progress_history[] = [
            'progress' => $progress,
            'date' => current_time('mysql'),
            'change' => $progress - $existing_progress,
        ];
    }

    if ($progress_note !== '') {
        $progress_text[] = [
            'text' => $progress_note,
            'date' => current_time('mysql'),
        ];
    }

    // Update the goal in the array
    $goals[$goal_index] = array_merge($goals[$goal_index], [
        'title' => $title,
        'goal_title' => $title,
        'description' => $description,
        'notes' => $description,
        'status' => $status,
        'start_date' => $start_date,
        'start' => $start_date,
        'target_date' => $target_date,
        'end_date' => $target_date,
        'end' => $target_date,
        'progress' => $progress,
        'progress_percent' => $progress,
        'goal_category' => $goal_category,
        'goal_target' => $goal_target,
        'goal_priority' => $goal_priority,
        'goal_checkin_frequency' => $goal_checkin_frequency,
        'goal_motivation' => $goal_motivation,
        'goal_blockers' => $goal_blockers,
        'goal_baseline_value' => $goal_baseline_value,
        'goal_target_value' => $goal_target_value,
        'goal_measure_unit' => $goal_measure_unit,
        'goal_reward' => $goal_reward,
        'goal_success_criteria' => $goal_success_criteria,
        'goal_key' => $goal_key,
        'milestones' => $milestones,
        'progress_history' => $progress_history,
        'progress_text' => $progress_text,
        'updated_at' => current_time('mysql')
    ]);

    // Save back to user meta
    $updated = update_user_meta($user_id, 'myavana_hair_goals_structured', $goals);

    if ($updated === false) {
        error_log(sprintf('[MYAVANA] update_goal: Failed to update user meta for user %d', $user_id));
        wp_send_json_error('Failed to update goal');
        return;
    }

    error_log(sprintf('[MYAVANA] Goal updated: Index=%d, User=%d, Title=%s', $goal_index, $user_id, $title));

    if ($progress > $existing_progress) {
        myavana_award_progress_milestones($user_id, 'goal', $goal_key, $existing_progress, $progress, $goal_index);
    }

    if (function_exists('myavana_check_badge_unlocks')) {
        myavana_check_badge_unlocks($user_id);
    }

    wp_send_json_success([
        'message' => 'Goal updated successfully!',
        'goal_id' => $goal_index,
        'goal' => $goals[$goal_index]
    ]);
}
add_action('wp_ajax_myavana_update_goal', 'myavana_update_goal');

/**
 * Get goal details for editing
 */
function myavana_get_goal_details() {
    // Flexible nonce verification - accept multiple nonce types
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        // Try multiple nonce actions that might be used
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_get_goal_details') ||
                         wp_verify_nonce($_POST['security'], 'myavana_get_entry_details') ||
                         wp_verify_nonce($_POST['security'], 'myavana-timeline-nonce') ||
                         wp_verify_nonce($_POST['security'], 'myavana_timeline_nonce');
    }

    if (!$nonce_verified) {
        error_log('[MYAVANA] get_goal_details nonce verification failed');
        wp_send_json_error('Security check failed');
        return;
    }

    $goal_index = isset($_POST['goal_id']) ? intval($_POST['goal_id']) : -1;
    $user_id = get_current_user_id();

    error_log(sprintf('[MYAVANA] get_goal_details: goal_index=%d, user_id=%d', $goal_index, $user_id));

    if ($goal_index < 0) {
        error_log('[MYAVANA] get_goal_details: Invalid goal index');
        wp_send_json_error('Invalid goal ID');
        return;
    }

    // Goals are stored in user meta, not as posts
    $goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
    if (!is_array($goals)) {
        $goals = [];
    }

    error_log(sprintf('[MYAVANA] get_goal_details: Found %d goals in user meta', count($goals)));

    if (!isset($goals[$goal_index])) {
        error_log(sprintf('[MYAVANA] get_goal_details: Goal index %d not found in user goals', $goal_index));
        wp_send_json_error('Goal not found or access denied');
        return;
    }

    $goal = $goals[$goal_index];
    error_log(sprintf('[MYAVANA] get_goal_details: Found goal at index %d: %s', $goal_index, json_encode($goal)));

    // Prepare goal data for response
    $goal_data = [
        'id' => $goal_index,
        'goal_id' => $goal_index,
        'title' => $goal['title'] ?? $goal['goal_title'] ?? 'Untitled Goal',
        'goal_title' => $goal['title'] ?? $goal['goal_title'] ?? 'Untitled Goal',
        'description' => $goal['description'] ?? $goal['notes'] ?? '',
        'goal_description' => $goal['description'] ?? $goal['notes'] ?? '',
        'start_date' => $goal['start_date'] ?? $goal['start'] ?? '',
        'goal_start_date' => $goal['start_date'] ?? $goal['start'] ?? '',
        'target_date' => $goal['end_date'] ?? $goal['end'] ?? $goal['target_date'] ?? '',
        'goal_end_date' => $goal['end_date'] ?? $goal['end'] ?? $goal['target_date'] ?? '',
        'status' => $goal['status'] ?? 'active',
        'progress' => isset($goal['progress']) ? intval($goal['progress']) : (isset($goal['progress_percent']) ? intval($goal['progress_percent']) : 0),
        'goal_category' => $goal['goal_category'] ?? '',
        'goal_target' => $goal['goal_target'] ?? '',
        'goal_priority' => $goal['goal_priority'] ?? ($goal['priority'] ?? 'Medium'),
        'goal_checkin_frequency' => $goal['goal_checkin_frequency'] ?? 'Weekly',
        'goal_motivation' => $goal['goal_motivation'] ?? '',
        'goal_blockers' => $goal['goal_blockers'] ?? '',
        'goal_baseline_value' => $goal['goal_baseline_value'] ?? '',
        'goal_target_value' => $goal['goal_target_value'] ?? '',
        'goal_measure_unit' => $goal['goal_measure_unit'] ?? '',
        'goal_reward' => $goal['goal_reward'] ?? '',
        'goal_success_criteria' => $goal['goal_success_criteria'] ?? '',
        'milestones' => isset($goal['milestones']) && is_array($goal['milestones']) ? $goal['milestones'] : [],
        'progress_history' => isset($goal['progress_history']) && is_array($goal['progress_history']) ? $goal['progress_history'] : [],
        'progress_text' => isset($goal['progress_text']) && is_array($goal['progress_text']) ? $goal['progress_text'] : [],
    ];

    error_log(sprintf('[MYAVANA] get_goal_details: Returning goal data: %s', json_encode($goal_data)));
    wp_send_json_success($goal_data);
}
add_action('wp_ajax_myavana_get_goal_details', 'myavana_get_goal_details');

/**
 * Update existing routine
 */
function myavana_update_routine() {
    // Verify nonce
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_update_routine');
    } elseif (isset($_POST['myavana_nonce'])) {
        $nonce_verified = wp_verify_nonce($_POST['myavana_nonce'], 'myavana_add_routine');
    }

    if (!$nonce_verified) {
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    $routine_index = isset($_POST['routine_id']) ? intval($_POST['routine_id']) : -1;

    error_log(sprintf('[MYAVANA] update_routine: routine_index=%d, user_id=%d', $routine_index, $user_id));

    if (!$user_id || $routine_index < 0) {
        wp_send_json_error('Invalid request');
        return;
    }

    // Routines are stored in user meta, not as posts
    $routines = get_user_meta($user_id, 'myavana_current_routine', true);
    if (!is_array($routines)) {
        $routines = [];
    }

    if (!isset($routines[$routine_index])) {
        error_log(sprintf('[MYAVANA] update_routine: Routine index %d not found in user routines', $routine_index));
        wp_send_json_error('Routine not found or access denied');
        return;
    }

    // Validate required fields
    $title_input = isset($_POST['title']) ? wp_unslash($_POST['title']) : ($_POST['routine_title'] ?? ($_POST['routine_name'] ?? ''));
    if (empty($title_input)) {
        wp_send_json_error('Title is required');
        return;
    }

    // Sanitize input data
    $title = sanitize_text_field($title_input);
    $title_length = function_exists('mb_strlen') ? mb_strlen($title) : strlen($title);
    if ($title === '' || $title_length > 100) {
        wp_send_json_error('Routine names must be between 1 and 100 characters.');
        return;
    }
    $description = sanitize_textarea_field($_POST['description'] ?? ($_POST['routine_notes'] ?? ''));
    $frequency = isset($_POST['frequency']) ? sanitize_text_field($_POST['frequency']) : sanitize_text_field($_POST['routine_frequency'] ?? 'Weekly');
    $allowed_frequencies = ['Daily', 'Weekly', 'Bi-weekly', 'Monthly', 'As Needed'];
    if (!in_array($frequency, $allowed_frequencies, true)) {
        wp_send_json_error('Please choose a valid routine frequency.');
        return;
    }
    $products = $_POST['products'] ?? ($_POST['routine_products'] ?? '');
    if (is_array($products)) {
        $products = implode(', ', array_map('sanitize_text_field', $products));
    } else {
        $products = sanitize_textarea_field((string) $products);
    }

    $duration = isset($_POST['duration']) ? sanitize_text_field($_POST['duration']) : sanitize_text_field($_POST['routine_duration'] ?? '');
    $routine_type = sanitize_text_field($_POST['routine_type'] ?? ($routines[$routine_index]['routine_type'] ?? ''));
    $routine_time = sanitize_text_field($_POST['routine_time'] ?? ($routines[$routine_index]['routine_time'] ?? ''));
    $routine_focus = sanitize_text_field($_POST['routine_focus'] ?? ($routines[$routine_index]['routine_focus'] ?? ''));
    $routine_difficulty = sanitize_text_field($_POST['routine_difficulty'] ?? ($routines[$routine_index]['routine_difficulty'] ?? 'Beginner'));
    $routine_reminder_days = myavana_normalize_routine_reminder_days(wp_unslash($_POST['routine_reminder_days'] ?? ($routines[$routine_index]['routine_reminder_days'] ?? '')));
    if (is_wp_error($routine_reminder_days)) {
        wp_send_json_error($routine_reminder_days->get_error_message());
        return;
    }
    $routine_expected_result = sanitize_textarea_field($_POST['routine_expected_result'] ?? ($routines[$routine_index]['routine_expected_result'] ?? ''));
    $routine_phase = sanitize_text_field($_POST['routine_phase'] ?? ($routines[$routine_index]['routine_phase'] ?? 'Anytime'));
    $routine_goal_link = sanitize_text_field($_POST['routine_goal_link'] ?? ($routines[$routine_index]['routine_goal_link'] ?? ''));
    $routine_tools = sanitize_textarea_field($_POST['routine_tools'] ?? ($routines[$routine_index]['routine_tools'] ?? ''));
    $routine_auto_track = !empty($_POST['routine_auto_track']) ? 1 : (!empty($routines[$routine_index]['routine_auto_track']) ? 1 : 0);
    $routine_key = myavana_goal_or_routine_key('routine', $routines[$routine_index]);

    $steps_input = $_POST['routine_steps'] ?? [];
    if (is_string($steps_input)) {
        $decoded_steps = json_decode(wp_unslash($steps_input), true);
        if (is_array($decoded_steps)) {
            $steps_input = $decoded_steps;
        } else {
            $steps_input = preg_split('/\r\n|\r|\n/', wp_unslash($steps_input));
        }
    }
    $steps = [];
    if (is_array($steps_input)) {
        foreach ($steps_input as $step_item) {
            $step_text = sanitize_text_field((string) $step_item);
            if ($step_text !== '') {
                $step_length = function_exists('mb_strlen') ? mb_strlen($step_text) : strlen($step_text);
                if ($step_length > 500) {
                    wp_send_json_error('Each routine step must be 500 characters or fewer.');
                    return;
                }
                $steps[] = $step_text;
            }
        }
    }
    if (empty($steps)) {
        wp_send_json_error('Add at least one routine step before saving.');
        return;
    }
    if (count($steps) > 20) {
        wp_send_json_error('A routine can have up to 20 steps.');
        return;
    }

    // Update the routine in the array
    $routines[$routine_index] = array_merge($routines[$routine_index], [
        'title' => $title,
        'routine_title' => $title,
        'description' => $description,
        'notes' => $description,
        'steps' => $steps,
        'frequency' => $frequency,
        'routine_frequency' => $frequency,
        'products' => $products,
        'routine_products' => $products,
        'duration' => $duration,
        'routine_duration' => $duration,
        'routine_type' => $routine_type,
        'routine_time' => $routine_time,
        'routine_focus' => $routine_focus,
        'routine_difficulty' => $routine_difficulty,
        'routine_reminder_days' => $routine_reminder_days,
        'routine_expected_result' => $routine_expected_result,
        'routine_phase' => $routine_phase,
        'routine_goal_link' => $routine_goal_link,
        'routine_tools' => $routine_tools,
        'routine_auto_track' => $routine_auto_track,
        'routine_key' => $routine_key,
        'updated_at' => current_time('mysql')
    ]);

    // Save back to user meta
    $updated = update_user_meta($user_id, 'myavana_current_routine', $routines);

    if ($updated === false) {
        error_log(sprintf('[MYAVANA] update_routine: Failed to update user meta for user %d', $user_id));
        wp_send_json_error('Failed to update routine');
        return;
    }

    error_log(sprintf('[MYAVANA] Routine updated: Index=%d, User=%d, Title=%s', $routine_index, $user_id, $title));

    wp_send_json_success([
        'message' => 'Routine updated successfully!',
        'routine_id' => $routine_index,
        'routine' => $routines[$routine_index]
    ]);
}
add_action('wp_ajax_myavana_update_routine', 'myavana_update_routine');

/**
 * Get routine details for editing
 */
function myavana_get_routine_details() {
    // Flexible nonce verification - accept multiple nonce types
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        // Try multiple nonce actions that might be used
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_get_routine_details') ||
                         wp_verify_nonce($_POST['security'], 'myavana_get_entry_details') ||
                         wp_verify_nonce($_POST['security'], 'myavana-timeline-nonce') ||
                         wp_verify_nonce($_POST['security'], 'myavana_timeline_nonce');
    }

    if (!$nonce_verified) {
        error_log('[MYAVANA] get_routine_details nonce verification failed');
        wp_send_json_error('Security check failed');
        return;
    }

    $routine_index = isset($_POST['routine_id']) ? intval($_POST['routine_id']) : -1;
    $user_id = get_current_user_id();

    error_log(sprintf('[MYAVANA] get_routine_details: routine_index=%d, user_id=%d', $routine_index, $user_id));

    if (!$user_id || $routine_index < 0) {
        wp_send_json_error('Invalid routine ID');
        return;
    }

    // Routines are stored in user meta, not as posts
    $routines = get_user_meta($user_id, 'myavana_current_routine', true);
    if (!is_array($routines)) {
        $routines = [];
    }

    if (!isset($routines[$routine_index])) {
        error_log(sprintf('[MYAVANA] get_routine_details: Routine index %d not found in user routines', $routine_index));
        wp_send_json_error('Routine not found or access denied');
        return;
    }

    $routine = $routines[$routine_index];

    // Gather routine data
    $routine_data = [
        'id' => $routine_index,
        'routine_id' => $routine_index,
        'title' => $routine['title'] ?? $routine['routine_title'] ?? 'Untitled Routine',
        'description' => $routine['description'] ?? $routine['notes'] ?? '',
        'routine_notes' => $routine['notes'] ?? ($routine['description'] ?? ''),
        'frequency' => $routine['frequency'] ?? 'Weekly',
        'routine_frequency' => $routine['routine_frequency'] ?? ($routine['frequency'] ?? 'Weekly'),
        'products' => $routine['products'] ?? '',
        'routine_products' => $routine['routine_products'] ?? ($routine['products'] ?? ''),
        'duration' => $routine['duration'] ?? '',
        'routine_duration' => $routine['routine_duration'] ?? ($routine['duration'] ?? ''),
        'routine_type' => $routine['routine_type'] ?? '',
        'routine_time' => $routine['routine_time'] ?? '',
        'routine_focus' => $routine['routine_focus'] ?? '',
        'routine_difficulty' => $routine['routine_difficulty'] ?? 'Beginner',
        'routine_reminder_days' => $routine['routine_reminder_days'] ?? '',
        'routine_expected_result' => $routine['routine_expected_result'] ?? '',
        'routine_phase' => $routine['routine_phase'] ?? 'Anytime',
        'routine_goal_link' => $routine['routine_goal_link'] ?? '',
        'routine_tools' => $routine['routine_tools'] ?? '',
        'routine_auto_track' => !empty($routine['routine_auto_track']) ? 1 : 0,
        'steps' => isset($routine['steps']) ? $routine['steps'] : [],
        'routine_steps' => isset($routine['steps']) ? $routine['steps'] : [],
    ];

    error_log(sprintf('[MYAVANA] get_routine_details: Returning routine data: %s', json_encode($routine_data)));
    wp_send_json_success($routine_data);
}
add_action('wp_ajax_myavana_get_routine_details', 'myavana_get_routine_details');

if (!function_exists('myavana_record_routine_tracking_status')) {
    function myavana_record_routine_tracking_status($user_id, $routine_id, $date, $status, $source = 'manual') {
        $allowed_statuses = ['completed', 'skipped', 'snoozed', 'clear'];
        $status = sanitize_key($status);
        if (!in_array($status, $allowed_statuses, true)) {
            return new WP_Error('invalid_status', 'Invalid routine status');
        }

        $routines = get_user_meta($user_id, 'myavana_current_routine', true);
        if (!is_array($routines) || !isset($routines[$routine_id])) {
            return new WP_Error('invalid_routine', 'Routine not found');
        }

        $records = myavana_get_routine_tracking_records($user_id);
        $routine_key = (string) intval($routine_id);
        $previous_record = myavana_get_routine_record_for_date($records, $routine_id, $date);
        $previous_status = $previous_record['status'] ?? '';

        if ($status === 'clear') {
            if (isset($records[$date][$routine_key])) {
                unset($records[$date][$routine_key]);
            }
            if (isset($records[$date]) && empty($records[$date])) {
                unset($records[$date]);
            }
        } else {
            $records[$date][$routine_key] = [
                'status' => $status,
                'logged_at' => current_time('mysql'),
                'source' => sanitize_key($source),
                'snoozed_until' => $status === 'snoozed' ? date('Y-m-d', strtotime($date . ' +1 day')) : '',
            ];
        }

        myavana_save_routine_tracking_records($user_id, $records);
        $completion_map = myavana_sync_legacy_routine_completion_map($user_id, $records);

        $routine_item = function_exists('myavana_normalize_routine_tracking_item')
            ? myavana_normalize_routine_tracking_item($routines[$routine_id])
            : (is_array($routines[$routine_id]) ? $routines[$routine_id] : []);
        if (empty($routine_item)) {
            return new WP_Error('invalid_routine', 'Routine data is invalid');
        }

        $routine_entity_key = myavana_goal_or_routine_key('routine', $routine_item);
        if (empty($routine_item['routine_key'])) {
            $routines[$routine_id]['routine_key'] = $routine_entity_key;
            update_user_meta($user_id, 'myavana_current_routine', $routines);
        }

        if ($status === 'completed' && $previous_status !== 'completed') {
            myavana_award_points(
                $user_id,
                Myavana_Gamification::get_reward_value('routine_completed', 10),
                'Routine completed',
                'routine_completion',
                $routine_id,
                'routine_completed:' . $routine_entity_key . ':' . $date,
                ['date' => $date]
            );

            $records = myavana_get_routine_tracking_records($user_id);
            $streak = myavana_calculate_routine_streak($routine_item, $records, $routine_id, $date);
            myavana_award_routine_streak_bonus($user_id, $routine_id, $routine_entity_key, $streak);

            if (function_exists('myavana_check_badge_unlocks')) {
                myavana_check_badge_unlocks($user_id);
            }
        }

        $tracking_context = myavana_get_routine_tracking_context($user_id, $routines);
        $tracking_routine = $tracking_context['routines_by_id'][$routine_id] ?? [];

        return [
            'status' => $status,
            'previous_status' => $previous_status,
            'routine' => $tracking_routine,
            'summary' => $tracking_context['summary'] ?? [],
            'notifications' => $tracking_context['notifications'] ?? [],
            'records' => $tracking_context['records'] ?? [],
            'completed_count_for_date' => isset($completion_map[$date]) && is_array($completion_map[$date]) ? count($completion_map[$date]) : 0,
        ];
    }
}

function myavana_update_routine_tracking_status() {
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_toggle_routine_completion') ||
            wp_verify_nonce($_POST['security'], 'myavana_add_routine') ||
            wp_verify_nonce($_POST['security'], 'myavana_nonce');
    }

    if (!$nonce_verified) {
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    $routine_id = isset($_POST['routine_id']) ? intval($_POST['routine_id']) : -1;
    $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';
    $raw_date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date) ? $raw_date : current_time('Y-m-d');

    if ($routine_id < 0) {
        wp_send_json_error('Invalid routine ID');
        return;
    }

    $result = myavana_record_routine_tracking_status($user_id, $routine_id, $date, $status, 'calendar');
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
        return;
    }

    $messages = [
        'completed' => 'Routine marked complete',
        'skipped' => 'Routine skipped for this date',
        'snoozed' => 'Routine reminder snoozed until tomorrow',
        'clear' => 'Routine status cleared',
    ];

    wp_send_json_success([
        'message' => $messages[$result['status']] ?? 'Routine updated',
        'routine_id' => $routine_id,
        'date' => $date,
        'status' => $result['status'],
        'summary' => $result['summary'],
        'routine' => $result['routine'],
        'notifications' => $result['notifications'],
        'records' => $result['records'],
        'completed_count_for_date' => $result['completed_count_for_date'],
    ]);
}
add_action('wp_ajax_myavana_update_routine_tracking_status', 'myavana_update_routine_tracking_status');

/**
 * Toggle routine completion for a specific date.
 */
function myavana_toggle_routine_completion() {
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_toggle_routine_completion') ||
            wp_verify_nonce($_POST['security'], 'myavana_add_routine') ||
            wp_verify_nonce($_POST['security'], 'myavana_nonce');
    }

    if (!$nonce_verified) {
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    $routine_id = isset($_POST['routine_id']) ? intval($_POST['routine_id']) : -1;
    if ($routine_id < 0) {
        wp_send_json_error('Invalid routine ID');
        return;
    }

    $raw_date = isset($_POST['date']) ? sanitize_text_field(wp_unslash($_POST['date'])) : '';
    $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw_date) ? $raw_date : current_time('Y-m-d');
    $records = myavana_get_routine_tracking_records($user_id);
    $existing_record = myavana_get_routine_record_for_date($records, $routine_id, $date);
    $next_status = (($existing_record['status'] ?? '') === 'completed') ? 'clear' : 'completed';

    $result = myavana_record_routine_tracking_status($user_id, $routine_id, $date, $next_status, 'toggle');
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
        return;
    }

    wp_send_json_success([
        'message' => $next_status === 'completed' ? 'Routine marked complete for today' : 'Routine marked incomplete for today',
        'completed' => $next_status === 'completed',
        'routine_id' => $routine_id,
        'date' => $date,
        'status' => $result['status'],
        'summary' => $result['summary'],
        'routine' => $result['routine'],
        'notifications' => $result['notifications'],
        'records' => $result['records'],
        'completed_count_for_date' => $result['completed_count_for_date'],
    ]);
}
add_action('wp_ajax_myavana_toggle_routine_completion', 'myavana_toggle_routine_completion');

/**
 * Add new goal
 */
function myavana_add_goal() {
    // Verify nonce
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_add_goal');
    }

    if (!$nonce_verified) {
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    $goal_title_input = isset($_POST['goal_title']) ? wp_unslash($_POST['goal_title']) : ($_POST['title'] ?? '');

    // Validate required fields
    if (empty($goal_title_input)) {
        wp_send_json_error('Goal title is required');
        return;
    }

    // Sanitize input data
    $title = sanitize_text_field($goal_title_input);
    $description = sanitize_textarea_field($_POST['goal_description'] ?? ($_POST['description'] ?? ''));
    $start_date = sanitize_text_field($_POST['goal_start_date'] ?? ($_POST['start_date'] ?? current_time('Y-m-d')));
    $target_date = sanitize_text_field($_POST['goal_end_date'] ?? ($_POST['target_date'] ?? ''));
    $priority = isset($_POST['goal_priority']) ? sanitize_text_field($_POST['goal_priority']) : sanitize_text_field($_POST['priority'] ?? 'Medium');
    $category = sanitize_text_field($_POST['goal_category'] ?? '');
    $target_metric = sanitize_text_field($_POST['goal_target'] ?? '');
    $checkin_frequency = sanitize_text_field($_POST['goal_checkin_frequency'] ?? 'Weekly');
    $motivation = sanitize_textarea_field($_POST['goal_motivation'] ?? '');
    $blockers = sanitize_textarea_field($_POST['goal_blockers'] ?? '');
    $goal_baseline_value = isset($_POST['goal_baseline_value']) && $_POST['goal_baseline_value'] !== ''
        ? floatval($_POST['goal_baseline_value'])
        : '';
    $goal_target_value = isset($_POST['goal_target_value']) && $_POST['goal_target_value'] !== ''
        ? floatval($_POST['goal_target_value'])
        : '';
    $goal_measure_unit = sanitize_text_field($_POST['goal_measure_unit'] ?? '');
    $goal_reward = sanitize_text_field($_POST['goal_reward'] ?? '');
    $goal_success_criteria = sanitize_textarea_field($_POST['goal_success_criteria'] ?? '');
    $progress = max(0, min(100, intval($_POST['goal_progress'] ?? ($_POST['progress'] ?? 0))));

    $milestones_input = $_POST['goal_milestones'] ?? [];
    if (is_string($milestones_input)) {
        $decoded = json_decode(wp_unslash($milestones_input), true);
        if (is_array($decoded)) {
            $milestones_input = $decoded;
        } else {
            $milestones_input = array_filter(array_map('trim', explode(',', wp_unslash($milestones_input))));
        }
    }

    $milestones = [];
    if (is_array($milestones_input)) {
        foreach ($milestones_input as $milestone_item) {
            if (is_array($milestone_item) && isset($milestone_item['text'])) {
                $text = sanitize_text_field($milestone_item['text']);
                $achieved = !empty($milestone_item['achieved']);
            } else {
                $text = sanitize_text_field((string) $milestone_item);
                $achieved = false;
            }
            if ($text !== '') {
                $milestones[] = [
                    'text' => $text,
                    'achieved' => $achieved,
                ];
            }
        }
    }

    // Get existing goals
    $goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
    if (!is_array($goals)) {
        $goals = [];
    }

    // Create new goal
    $new_goal = [
        'goal_key' => sanitize_key('goal_' . wp_generate_uuid4()),
        'title' => $title,
        'goal_title' => $title,
        'description' => $description,
        'notes' => $description,
        'start_date' => $start_date,
        'start' => $start_date,
        'target_date' => $target_date,
        'end_date' => $target_date,
        'end' => $target_date,
        'goal_category' => $category,
        'goal_target' => $target_metric,
        'priority' => $priority,
        'goal_priority' => $priority,
        'goal_checkin_frequency' => $checkin_frequency,
        'goal_motivation' => $motivation,
        'goal_blockers' => $blockers,
        'goal_baseline_value' => $goal_baseline_value,
        'goal_target_value' => $goal_target_value,
        'goal_measure_unit' => $goal_measure_unit,
        'goal_reward' => $goal_reward,
        'goal_success_criteria' => $goal_success_criteria,
        'status' => 'active',
        'progress' => $progress,
        'progress_percent' => $progress,
        'milestones' => $milestones,
        'progress_history' => [],
        'progress_text' => [],
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];

    // Add to goals array
    $goals[] = $new_goal;

    // Save to user meta
    $updated = update_user_meta($user_id, 'myavana_hair_goals_structured', $goals);

    if ($updated === false) {
        wp_send_json_error('Failed to save goal');
        return;
    }

    error_log(sprintf('[MYAVANA] Goal added: User=%d, Title=%s', $user_id, $title));

    $goal_index = count($goals) - 1;
    myavana_award_points(
        $user_id,
        Myavana_Gamification::get_reward_value('goal_created', 20),
        'Hair goal created',
        'goal',
        $goal_index,
        'goal_created:' . $new_goal['goal_key']
    );
    if ($progress > 0) {
        myavana_award_progress_milestones($user_id, 'goal', $new_goal['goal_key'], 0, $progress, $goal_index);
    }
    if (function_exists('myavana_check_badge_unlocks')) {
        myavana_check_badge_unlocks($user_id);
    }

    wp_send_json_success([
        'message' => 'Goal added successfully!',
        'goal_id' => $goal_index,
        'goal' => $new_goal
    ]);
}
add_action('wp_ajax_myavana_add_goal', 'myavana_add_goal');

/**
 * Add new routine
 */
function myavana_add_routine() {
    // Verify nonce
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_add_routine');
    }

    if (!$nonce_verified) {
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    $routine_title_input = isset($_POST['routine_name']) ? wp_unslash($_POST['routine_name']) : ($_POST['routine_title'] ?? ($_POST['title'] ?? ''));

    // Validate required fields
    if (empty($routine_title_input)) {
        wp_send_json_error('Routine name is required');
        return;
    }

    $frequency_input = $_POST['frequency'] ?? ($_POST['routine_frequency'] ?? '');
    if (empty($frequency_input)) {
        wp_send_json_error('Frequency is required');
        return;
    }

    // Sanitize input data
    $name = sanitize_text_field($routine_title_input);
    $name_length = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
    if ($name === '' || $name_length > 100) {
        wp_send_json_error('Routine names must be between 1 and 100 characters.');
        return;
    }
    $frequency = sanitize_text_field($frequency_input);
    $allowed_frequencies = ['Daily', 'Weekly', 'Bi-weekly', 'Monthly', 'As Needed'];
    if (!in_array($frequency, $allowed_frequencies, true)) {
        wp_send_json_error('Please choose a valid routine frequency.');
        return;
    }
    $notes = isset($_POST['notes']) ? sanitize_textarea_field($_POST['notes']) : sanitize_textarea_field($_POST['routine_notes'] ?? ($_POST['description'] ?? ''));
    $routine_type = sanitize_text_field($_POST['routine_type'] ?? '');
    $routine_time = sanitize_text_field($_POST['routine_time'] ?? '');
    $routine_duration = sanitize_text_field($_POST['routine_duration'] ?? ($_POST['duration'] ?? ''));
    $routine_focus = sanitize_text_field($_POST['routine_focus'] ?? '');
    $routine_difficulty = sanitize_text_field($_POST['routine_difficulty'] ?? 'Beginner');
    $routine_reminder_days = myavana_normalize_routine_reminder_days(wp_unslash($_POST['routine_reminder_days'] ?? ''));
    if (is_wp_error($routine_reminder_days)) {
        wp_send_json_error($routine_reminder_days->get_error_message());
        return;
    }
    $routine_expected_result = sanitize_textarea_field($_POST['routine_expected_result'] ?? '');
    $routine_phase = sanitize_text_field($_POST['routine_phase'] ?? 'Anytime');
    $routine_goal_link = sanitize_text_field($_POST['routine_goal_link'] ?? '');
    $routine_tools = sanitize_textarea_field($_POST['routine_tools'] ?? '');
    $routine_auto_track = !empty($_POST['routine_auto_track']) ? 1 : 0;

    $products_input = $_POST['routine_products'] ?? ($_POST['products'] ?? '');
    if (is_array($products_input)) {
        $products = array_values(array_filter(array_map('sanitize_text_field', $products_input)));
    } else {
        $products = array_values(array_filter(array_map('sanitize_text_field', preg_split('/\r\n|\r|\n|,/', (string) $products_input))));
    }

    $steps_input = $_POST['routine_steps'] ?? ($_POST['steps'] ?? []);
    if (is_string($steps_input)) {
        $decoded_steps = json_decode(wp_unslash($steps_input), true);
        if (is_array($decoded_steps)) {
            $steps_input = $decoded_steps;
        } else {
            $steps_input = preg_split('/\r\n|\r|\n|,/', wp_unslash($steps_input));
        }
    }

    $steps = [];
    if (is_array($steps_input)) {
        foreach ($steps_input as $step_item) {
            $step_text = sanitize_text_field((string) $step_item);
            if ($step_text !== '') {
                $step_length = function_exists('mb_strlen') ? mb_strlen($step_text) : strlen($step_text);
                if ($step_length > 500) {
                    wp_send_json_error('Each routine step must be 500 characters or fewer.');
                    return;
                }
                $steps[] = $step_text;
            }
        }
    }
    if (empty($steps)) {
        wp_send_json_error('Add at least one routine step before saving.');
        return;
    }
    if (count($steps) > 20) {
        wp_send_json_error('A routine can have up to 20 steps.');
        return;
    }

    // Get existing routines
    $routines = get_user_meta($user_id, 'myavana_current_routine', true);
    if (!is_array($routines)) {
        $routines = [];
    }

    // Create new routine
    $new_routine = [
        'routine_key' => sanitize_key('routine_' . wp_generate_uuid4()),
        'title' => $name,
        'routine_title' => $name,
        'description' => $notes,
        'notes' => $notes,
        'routine_type' => $routine_type,
        'frequency' => $frequency,
        'routine_frequency' => $frequency,
        'routine_time' => $routine_time,
        'duration' => $routine_duration,
        'routine_duration' => $routine_duration,
        'products' => $products,
        'routine_products' => $products,
        'routine_focus' => $routine_focus,
        'routine_difficulty' => $routine_difficulty,
        'routine_reminder_days' => $routine_reminder_days,
        'routine_expected_result' => $routine_expected_result,
        'routine_phase' => $routine_phase,
        'routine_goal_link' => $routine_goal_link,
        'routine_tools' => $routine_tools,
        'routine_auto_track' => $routine_auto_track,
        'steps' => $steps,
        'created_at' => current_time('mysql'),
        'updated_at' => current_time('mysql')
    ];

    // Add to routines array
    $routines[] = $new_routine;

    // Save to user meta
    $updated = update_user_meta($user_id, 'myavana_current_routine', $routines);

    if ($updated === false) {
        wp_send_json_error('Failed to save routine');
        return;
    }

    error_log(sprintf('[MYAVANA] Routine added: User=%d, Name=%s', $user_id, $name));

    $routine_index = count($routines) - 1;
    myavana_award_points(
        $user_id,
        Myavana_Gamification::get_reward_value('routine_created', 15),
        'Hair routine created',
        'routine',
        $routine_index,
        'routine_created:' . $new_routine['routine_key']
    );

    wp_send_json_success([
        'message' => 'Routine added successfully!',
        'routine_id' => $routine_index,
        'routine' => $new_routine
    ]);
}
add_action('wp_ajax_myavana_add_routine', 'myavana_add_routine');

/**
 * Delete a goal
 */
function myavana_delete_goal() {
    // Verify nonce - be more flexible with verification
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        // Try multiple nonce actions
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_delete_goal') ||
                         wp_verify_nonce($_POST['security'], 'myavana_nonce') ||
                         wp_verify_nonce($_POST['security'], 'myavana_add_goal');
    }

    if (!$nonce_verified) {
        error_log('[MYAVANA] Delete goal nonce verification failed. Nonce: ' . (isset($_POST['security']) ? substr($_POST['security'], 0, 10) . '...' : 'not set'));
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Validate goal_id
    if (!isset($_POST['goal_id']) || $_POST['goal_id'] === '') {
        wp_send_json_error('Goal ID is required');
        return;
    }

    $goal_id = intval($_POST['goal_id']);

    // Get existing goals
    $goals = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
    if (!is_array($goals)) {
        wp_send_json_error('No goals found');
        return;
    }

    // Check if goal exists
    if (!isset($goals[$goal_id])) {
        wp_send_json_error('Goal not found');
        return;
    }

    // Remove the goal
    array_splice($goals, $goal_id, 1);

    // Save updated goals array
    $updated = update_user_meta($user_id, 'myavana_hair_goals_structured', $goals);

    if ($updated === false) {
        wp_send_json_error('Failed to delete goal');
        return;
    }

    error_log(sprintf('[MYAVANA] Goal deleted: User=%d, GoalID=%d', $user_id, $goal_id));

    wp_send_json_success([
        'message' => 'Goal deleted successfully!',
        'goal_id' => $goal_id
    ]);
}
add_action('wp_ajax_myavana_delete_goal', 'myavana_delete_goal');

/**
 * Delete a routine
 */
function myavana_delete_routine() {
    // Verify nonce - be more flexible with verification
    $nonce_verified = false;
    if (isset($_POST['security'])) {
        // Try multiple nonce actions
        $nonce_verified = wp_verify_nonce($_POST['security'], 'myavana_delete_routine') ||
                         wp_verify_nonce($_POST['security'], 'myavana_nonce') ||
                         wp_verify_nonce($_POST['security'], 'myavana_add_routine');
    }

    if (!$nonce_verified) {
        error_log('[MYAVANA] Delete routine nonce verification failed. Nonce: ' . (isset($_POST['security']) ? substr($_POST['security'], 0, 10) . '...' : 'not set'));
        wp_send_json_error('Security check failed');
        return;
    }

    $user_id = get_current_user_id();
    if (!$user_id) {
        wp_send_json_error('User not logged in');
        return;
    }

    // Validate routine_id
    if (!isset($_POST['routine_id']) || $_POST['routine_id'] === '') {
        wp_send_json_error('Routine ID is required');
        return;
    }

    $routine_id = intval($_POST['routine_id']);

    // Get existing routines
    $routines = get_user_meta($user_id, 'myavana_current_routine', true);
    if (!is_array($routines)) {
        wp_send_json_error('No routines found');
        return;
    }

    // Check if routine exists
    if (!isset($routines[$routine_id])) {
        wp_send_json_error('Routine not found');
        return;
    }

    // Remove the routine
    array_splice($routines, $routine_id, 1);

    // Re-map completion history because routine indices shift after delete.
    $completion_map = get_user_meta($user_id, 'myavana_routine_completions', true);
    if (!is_array($completion_map)) {
        $completion_map = [];
    }
    if (!empty($completion_map)) {
        foreach ($completion_map as $date_key => $routine_ids) {
            if (!is_array($routine_ids)) {
                unset($completion_map[$date_key]);
                continue;
            }

            $reindexed_ids = [];
            foreach ($routine_ids as $stored_id) {
                $stored_id = intval($stored_id);
                if ($stored_id === $routine_id) {
                    // Drop deleted routine completion entries.
                    continue;
                }
                if ($stored_id > $routine_id) {
                    $stored_id--;
                }
                $reindexed_ids[] = $stored_id;
            }

            $reindexed_ids = array_values(array_unique($reindexed_ids));
            if (empty($reindexed_ids)) {
                unset($completion_map[$date_key]);
            } else {
                sort($reindexed_ids);
                $completion_map[$date_key] = $reindexed_ids;
            }
        }
    }

    // Save updated routines array
    $updated = update_user_meta($user_id, 'myavana_current_routine', $routines);

    if ($updated === false) {
        wp_send_json_error('Failed to delete routine');
        return;
    }
    update_user_meta($user_id, 'myavana_routine_completions', $completion_map);

    error_log(sprintf('[MYAVANA] Routine deleted: User=%d, RoutineID=%d', $user_id, $routine_id));

    wp_send_json_success([
        'message' => 'Routine deleted successfully!',
        'routine_id' => $routine_id
    ]);
}
add_action('wp_ajax_myavana_delete_routine', 'myavana_delete_routine');
}
