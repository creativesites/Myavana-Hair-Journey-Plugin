<?php
/**
 * Routines Management Page Shortcode (Redesigned)
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('myavana_routines_page_shortcode')) {
function myavana_routines_page_shortcode($atts = [], $content = null) {
    if (!is_user_logged_in()) {
        return '<div class="hair-journey-container"><div class="calendar-empty-hjn"><h2>Please sign in to manage your routines</h2></div></div>';
    }

    $css_path = MYAVANA_NEXT_PATH . 'assets/css/routines-page-redesign.css';
    $js_path = MYAVANA_NEXT_PATH . 'assets/js/routines-page-redesign.js';
    $asset_version = (string) max(
        file_exists($css_path) ? filemtime($css_path) : time(),
        file_exists($js_path) ? filemtime($js_path) : time()
    );
    wp_enqueue_style(
        'myavana-goal-routine-pages',
        MYAVANA_NEXT_URL . 'assets/css/goal-routine-pages.css',
        [],
        $asset_version
    );
    wp_enqueue_style(
        'myavana-routines-page-redesign',
        MYAVANA_NEXT_URL . 'assets/css/routines-page-redesign.css',
        ['myavana-goal-routine-pages'],
        $asset_version
    );
    wp_enqueue_script(
        'myavana-lucide',
        'https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js',
        [],
        '0.469.0',
        true
    );
    wp_enqueue_script(
        'myavana-routines-page-redesign',
        MYAVANA_NEXT_URL . 'assets/js/routines-page-redesign.js',
        ['myavana-lucide', 'jquery'],
        $asset_version,
        true
    );

    $user_id = get_current_user_id();
    $today = current_time('Y-m-d');
    $today_ts = strtotime($today . ' 00:00:00');

    $routines = get_user_meta($user_id, 'myavana_current_routine', true);
    if (!is_array($routines)) {
        $routines = [];
    }

    $routine_completion_map = get_user_meta($user_id, 'myavana_routine_completions', true);
    if (!is_array($routine_completion_map)) {
        $routine_completion_map = [];
    }

    global $wpdb;
    $profile_row = $wpdb->get_row($wpdb->prepare(
        "SELECT hair_type, hair_health_rating, hair_journey_stage, hair_goals
         FROM {$wpdb->prefix}myavana_profiles
         WHERE user_id = %d
         LIMIT 1",
        $user_id
    ), ARRAY_A);
    $profile_row = is_array($profile_row) ? $profile_row : [];
    $profile_hair_type = trim((string) ($profile_row['hair_type'] ?? ''));
    if ($profile_hair_type === '') {
        $profile_hair_type = trim((string) get_user_meta($user_id, 'myavana_hair_type', true));
    }
    if ($profile_hair_type === '') {
        $profile_hair_type = trim((string) get_user_meta($user_id, 'hair_type', true));
    }

    $profile_porosity = trim((string) get_user_meta($user_id, 'hair_porosity', true));
    if ($profile_porosity === '') {
        $profile_porosity = trim((string) get_user_meta($user_id, 'myavana_hair_porosity', true));
    }
    $profile_concerns = get_user_meta($user_id, 'myavana_up_hair_concerns', true);
    if (!is_array($profile_concerns)) {
        $profile_concerns = array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', (string) $profile_concerns))));
    }
    $goals_for_ai = get_user_meta($user_id, 'myavana_hair_goals_structured', true);
    $goals_for_ai = is_array($goals_for_ai) ? $goals_for_ai : [];
    $active_goal = null;
    foreach ($goals_for_ai as $goal_item) {
        $goal_progress = (int) ($goal_item['progress'] ?? ($goal_item['progress_percent'] ?? 0));
        $goal_status = strtolower(trim((string) ($goal_item['status'] ?? 'active')));
        if ($goal_progress >= 100 || $goal_status === 'completed') {
            continue;
        }
        $active_goal = [
            'title' => trim((string) ($goal_item['title'] ?? ($goal_item['goal_title'] ?? ''))),
            'category' => trim((string) ($goal_item['goal_category'] ?? '')),
        ];
        break;
    }

    $hair_type_lc = strtolower($profile_hair_type);
    $porosity_lc = strtolower($profile_porosity);
    $concerns_lc = array_map('strtolower', array_map('strval', $profile_concerns));
    $goal_title_ai = trim((string) ($active_goal['title'] ?? ''));
    $goal_category_ai = strtolower(trim((string) ($active_goal['category'] ?? '')));

    $ai_focus = 'hydration';
    $ai_template_type = 'Daily Care';
    $ai_frequency = 'Weekly';
    $ai_duration = '25';
    $ai_phase = 'Evening';
    $ai_time = '19:30';
    $ai_reminder_days = 'Sun, Wed';
    $ai_label = 'AI Profile';

    if (strpos($goal_category_ai, 'length') !== false || strpos(strtolower($goal_title_ai), 'growth') !== false || strpos(strtolower($goal_title_ai), 'retention') !== false) {
        $ai_focus = 'retention';
        $ai_template_type = 'Night Routine';
        $ai_frequency = 'Daily';
        $ai_duration = '15';
        $ai_phase = 'Night';
        $ai_time = '21:00';
        $ai_reminder_days = 'Mon, Tue, Wed, Thu, Fri, Sat, Sun';
    } elseif (strpos($goal_category_ai, 'scalp') !== false || in_array('scalp', $concerns_lc, true)) {
        $ai_focus = 'scalp';
        $ai_template_type = 'Scalp Care';
        $ai_frequency = 'Weekly';
        $ai_duration = '20';
        $ai_phase = 'Night';
        $ai_time = '20:00';
        $ai_reminder_days = 'Tue';
    } elseif (strpos($goal_category_ai, 'strength') !== false || in_array('breakage', $concerns_lc, true) || in_array('damage', $concerns_lc, true)) {
        $ai_focus = 'strength';
        $ai_template_type = 'Deep Conditioning';
        $ai_frequency = 'Weekly';
        $ai_duration = '40';
        $ai_phase = 'Afternoon';
        $ai_time = '14:00';
        $ai_reminder_days = 'Sat';
    } elseif (strpos($hair_type_lc, '4') !== false || strpos($hair_type_lc, 'coily') !== false || strpos($hair_type_lc, 'curly') !== false) {
        $ai_focus = 'moisture';
        $ai_template_type = 'Wash Day';
        $ai_frequency = 'Weekly';
        $ai_duration = '60';
        $ai_phase = 'Morning';
        $ai_time = '09:00';
        $ai_reminder_days = 'Sun';
    }

    $ai_products = ['Leave-in conditioner', 'Lightweight oil'];
    $ai_steps = [
        'Mist or lightly dampen the hair before product application.',
        'Apply your main treatment evenly through the driest sections.',
        'Seal or style in a way that protects the ends.',
        'Check in with your hair after the routine and adjust next time if needed.',
    ];
    $ai_expected_result = 'A more consistent routine that better matches your profile and supports cleaner progress tracking.';
    $ai_notes = 'This starter is personalized from your current profile and can be edited before saving.';

    if ($ai_focus === 'retention') {
        $ai_products = ['Leave-in milk', 'Sealing oil', 'Satin wrap or bonnet'];
        $ai_steps = [
            'Lightly moisturize mid-lengths and ends.',
            'Seal moisture where the hair feels driest.',
            'Set the hair in a low-tension protective style for sleep.',
            'Wrap or cover with satin before bed.',
        ];
        $ai_expected_result = 'Better overnight moisture retention and less friction-related breakage.';
    } elseif ($ai_focus === 'scalp') {
        $ai_products = ['Scalp serum', 'Lightweight oil blend'];
        $ai_steps = [
            'Part the hair into workable sections.',
            'Apply the scalp treatment directly to exposed scalp areas.',
            'Massage gently for several minutes without scratching.',
            'Keep the rest of the routine light to avoid buildup.',
        ];
        $ai_expected_result = 'A calmer scalp routine with more consistent treatment follow-through.';
    } elseif ($ai_focus === 'strength') {
        $ai_products = ['Strengthening mask', 'Deep conditioner', 'Leave-in conditioner'];
        $ai_steps = [
            'Apply the strengthening or repair treatment section by section.',
            'Leave it on for the recommended treatment window.',
            'Rinse and follow with a moisture-balancing step.',
            'Style gently and avoid high-tension manipulation after treatment.',
        ];
        $ai_expected_result = 'A better moisture-strength balance and less breakage during styling.';
    } elseif ($ai_focus === 'moisture') {
        $ai_products = ['Hydrating cleanser', 'Deep conditioner', 'Leave-in conditioner', 'Sealant'];
        $ai_steps = [
            'Cleanse with a focus on removing buildup without stripping.',
            'Deep condition and detangle in sections.',
            'Layer leave-in moisture and seal where needed.',
            'Style in a way that preserves hydration through the week.',
        ];
        $ai_expected_result = 'A more hydration-forward weekly rhythm with easier manageability between wash days.';
    }

    if ($porosity_lc === 'high') {
        $ai_products[] = 'Protein-support treatment';
        $ai_notes = 'Built with high-porosity support in mind, so it leans toward moisture plus structure.';
    } elseif ($porosity_lc === 'low') {
        $ai_products[] = 'Lightweight heat cap step';
        $ai_notes = 'Built with low-porosity support in mind, so it favors lightweight layering and better product absorption.';
    }

    $profile_summary_parts = array_filter([
        $profile_hair_type !== '' ? $profile_hair_type : '',
        $profile_porosity !== '' ? $profile_porosity . ' porosity' : '',
        $goal_title_ai !== '' ? 'goal: ' . $goal_title_ai : '',
    ]);
    $ai_description = !empty($profile_summary_parts)
        ? 'AI-generated from your current profile: ' . implode(' · ', $profile_summary_parts) . '.'
        : 'AI-generated from your current hair profile and in-app activity.';
    $ai_title = $goal_title_ai !== ''
        ? 'AI Routine for ' . $goal_title_ai
        : 'AI Profile Routine Starter';

    $routine_templates = [
        [
            'key' => 'ai-profile',
            'label' => $ai_label,
            'icon' => 'bot',
            'title' => $ai_title,
            'description' => $ai_description,
            'type' => $ai_template_type,
            'frequency' => $ai_frequency,
            'duration' => $ai_duration,
            'phase' => $ai_phase,
            'difficulty' => 'Personalized',
            'time' => $ai_time,
            'reminder_days' => $ai_reminder_days,
            'products' => array_values(array_unique($ai_products)),
            'tools' => 'Wide-tooth comb' . "\n" . 'Satin wrap or bonnet',
            'steps' => $ai_steps,
            'expected_result' => $ai_expected_result,
            'notes' => $ai_notes,
            'auto_track' => true,
        ],
        [
            'key' => 'wash-reset',
            'label' => 'Wash Reset',
            'icon' => 'droplets',
            'title' => 'Wash Day Reset Routine',
            'description' => 'A full reset routine for cleanse, moisture, detangling, and styling on wash day.',
            'type' => 'Wash Day',
            'frequency' => 'Weekly',
            'duration' => '90',
            'phase' => 'Morning',
            'difficulty' => 'Beginner',
            'time' => '09:00',
            'reminder_days' => 'Sun',
            'products' => ['Clarifying shampoo', 'Deep conditioner', 'Leave-in conditioner', 'Styling cream'],
            'tools' => 'Wide-tooth comb' . "\n" . 'Microfiber towel',
            'steps' => ['Cleanse scalp thoroughly', 'Apply deep conditioner and detangle in sections', 'Rinse and layer leave-in moisture', 'Style and seal ends'],
            'expected_result' => 'A clean scalp, hydrated strands, and a smoother styling base for the week.',
            'notes' => 'Adjust cleansing intensity based on buildup and keep detangling gentle around fragile areas.',
            'auto_track' => true,
        ],
        [
            'key' => 'midweek-moisture',
            'label' => 'Moisture Boost',
            'icon' => 'droplets',
            'title' => 'Midweek Moisture Refresh',
            'description' => 'A quick hydration routine for reviving softness between full wash days.',
            'type' => 'Daily Care',
            'frequency' => 'As Needed',
            'duration' => '15',
            'phase' => 'Evening',
            'difficulty' => 'Beginner',
            'time' => '18:30',
            'reminder_days' => 'Wed, Fri',
            'products' => ['Water-based refresher spray', 'Leave-in conditioner', 'Light sealing oil'],
            'tools' => 'Spray bottle',
            'steps' => ['Lightly mist hair section by section', 'Smooth in leave-in conditioner', 'Seal moisture on ends and dry areas'],
            'expected_result' => 'Hair feels softer, more flexible, and easier to manage without a full reset.',
            'notes' => 'Use a lighter hand near the roots if your scalp gets oily quickly.',
            'auto_track' => true,
        ],
        [
            'key' => 'scalp-care',
            'label' => 'Scalp Focus',
            'icon' => 'sparkles',
            'title' => 'Scalp Care Reset',
            'description' => 'A scalp-first routine to reduce buildup and support a healthier growth environment.',
            'type' => 'Scalp Care',
            'frequency' => 'Weekly',
            'duration' => '20',
            'phase' => 'Night',
            'difficulty' => 'Beginner',
            'time' => '20:00',
            'reminder_days' => 'Tue',
            'products' => ['Scalp serum', 'Lightweight oil blend'],
            'tools' => 'Scalp massager',
            'steps' => ['Part hair into workable sections', 'Apply scalp serum directly to the scalp', 'Massage gently for 3 to 5 minutes', 'Seal lightly if your scalp tolerates oils'],
            'expected_result' => 'Less scalp tension and a more consistent treatment habit.',
            'notes' => 'Skip heavy oiling if you notice congestion or itching after treatment.',
            'auto_track' => true,
        ],
        [
            'key' => 'protective-style',
            'label' => 'Protective Care',
            'icon' => 'shield',
            'title' => 'Protective Style Maintenance',
            'description' => 'A light upkeep routine for keeping protective styles neat without overwhelming the hair.',
            'type' => 'Protective Style',
            'frequency' => 'Weekly',
            'duration' => '20',
            'phase' => 'Evening',
            'difficulty' => 'Intermediate',
            'time' => '19:00',
            'reminder_days' => 'Thu',
            'products' => ['Scalp cleanser', 'Light mousse', 'Edge serum'],
            'tools' => 'Soft brush' . "\n" . 'Satin scarf',
            'steps' => ['Cleanse scalp lines and exposed parts', 'Refresh frizz-prone sections with mousse', 'Smooth edges lightly and wrap with a satin scarf'],
            'expected_result' => 'A fresher style with less frizz and less tension-related stress.',
            'notes' => 'Focus on comfort. If the style feels tight, reduce manipulation and prioritize scalp relief.',
            'auto_track' => true,
        ],
        [
            'key' => 'night-retention',
            'label' => 'Night Care',
            'icon' => 'moon',
            'title' => 'Nighttime Retention Routine',
            'description' => 'A low-effort evening routine that protects moisture and reduces overnight breakage.',
            'type' => 'Night Routine',
            'frequency' => 'Daily',
            'duration' => '10',
            'phase' => 'Night',
            'difficulty' => 'Beginner',
            'time' => '21:30',
            'reminder_days' => 'Mon, Tue, Wed, Thu, Fri, Sat, Sun',
            'products' => ['Leave-in milk', 'Light oil or butter'],
            'tools' => 'Satin bonnet' . "\n" . 'Satin pillowcase',
            'steps' => ['Moisturize dry areas lightly', 'Seal ends with a small amount of oil or butter', 'Pineapple, braid, or wrap hair before bed'],
            'expected_result' => 'Better moisture retention overnight and fewer dry, snag-prone ends.',
            'notes' => 'Keep this routine light so it stays sustainable every night.',
            'auto_track' => true,
        ],
        [
            'key' => 'deep-treatment',
            'label' => 'Repair',
            'icon' => 'flask-conical',
            'title' => 'Deep Conditioning Recovery',
            'description' => 'A recovery routine when your hair needs softness, slip, and a more intentional treatment window.',
            'type' => 'Deep Conditioning',
            'frequency' => 'Weekly',
            'duration' => '45',
            'phase' => 'Afternoon',
            'difficulty' => 'Intermediate',
            'time' => '14:00',
            'reminder_days' => 'Sat',
            'products' => ['Deep conditioner', 'Heat protectant cap treatment', 'Leave-in conditioner'],
            'tools' => 'Heat cap',
            'steps' => ['Apply deep conditioner generously in sections', 'Use gentle heat to help penetration', 'Rinse and follow with leave-in hydration'],
            'expected_result' => 'Hair feels softer, more elastic, and easier to detangle after treatment.',
            'notes' => 'If your strands feel mushy, alternate with protein-focused care instead of over-moisturizing.',
            'auto_track' => true,
        ],
    ];

    $normalize_list = static function ($value) {
        if (is_array($value)) {
            $items = array_map('trim', array_map('strval', $value));
        } else {
            $items = preg_split('/\r\n|\r|\n|,/', (string) $value);
            $items = array_map('trim', array_map('strval', $items));
        }
        return array_values(array_filter($items, static function ($item) {
            return $item !== '';
        }));
    };

    $normalize_frequency = static function ($value) {
        $raw = strtolower(trim((string) $value));
        if ($raw === '') {
            return 'weekly';
        }
        if (strpos($raw, 'every other') !== false) {
            return 'every_other_day';
        }
        if (strpos($raw, 'twice') !== false) {
            return 'twice_week';
        }
        if (strpos($raw, 'bi') !== false) {
            return 'bi_weekly';
        }
        if (strpos($raw, 'month') !== false) {
            return 'monthly';
        }
        if (strpos($raw, 'daily') !== false) {
            return 'daily';
        }
        if (strpos($raw, 'needed') !== false) {
            return 'as_needed';
        }
        return 'weekly';
    };

    $frequency_label = static function ($normalized) {
        $labels = [
            'daily' => 'Daily',
            'every_other_day' => 'Every Other Day',
            'twice_week' => 'Twice a Week',
            'weekly' => 'Weekly',
            'bi_weekly' => 'Bi-Weekly',
            'monthly' => 'Monthly',
            'as_needed' => 'As Needed',
        ];
        return $labels[$normalized] ?? 'Weekly';
    };

    $frequency_group = static function ($normalized) {
        if (in_array($normalized, ['daily', 'every_other_day', 'twice_week'], true)) {
            return 'daily';
        }
        if (in_array($normalized, ['weekly', 'bi_weekly'], true)) {
            return 'weekly';
        }
        if ($normalized === 'monthly') {
            return 'monthly';
        }
        if ($normalized === 'as_needed') {
            return 'as_needed';
        }
        return 'weekly';
    };

    $parse_reminder_days = static function ($raw) {
        $parts = preg_split('/\s*,\s*/', strtolower((string) $raw));
        $valid = [];
        $map = [
            'mon' => 1,
            'monday' => 1,
            'tue' => 2,
            'tues' => 2,
            'tuesday' => 2,
            'wed' => 3,
            'wednesday' => 3,
            'thu' => 4,
            'thur' => 4,
            'thurs' => 4,
            'thursday' => 4,
            'fri' => 5,
            'friday' => 5,
            'sat' => 6,
            'saturday' => 6,
            'sun' => 0,
            'sunday' => 0,
        ];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part !== '' && isset($map[$part])) {
                $valid[] = $map[$part];
            }
        }

        return array_values(array_unique($valid));
    };

    $is_due_today = static function ($frequency, $created_ts, $today_ts_value, $reminder_days = []) {
        $today_weekday = (int) gmdate('w', $today_ts_value);
        $today_dom = (int) gmdate('j', $today_ts_value);
        $created_weekday = (int) gmdate('w', $created_ts);
        $created_dom = (int) gmdate('j', $created_ts);
        $days_since = max(0, (int) floor(($today_ts_value - $created_ts) / DAY_IN_SECONDS));

        switch ($frequency) {
            case 'daily':
                return true;
            case 'every_other_day':
                return $days_since % 2 === 0;
            case 'twice_week':
                if (!empty($reminder_days)) {
                    return in_array($today_weekday, $reminder_days, true);
                }
                return in_array($today_weekday, [2, 5], true);
            case 'weekly':
                if (!empty($reminder_days)) {
                    return in_array($today_weekday, $reminder_days, true);
                }
                return $today_weekday === $created_weekday;
            case 'bi_weekly':
                if (!empty($reminder_days) && !in_array($today_weekday, $reminder_days, true)) {
                    return false;
                }
                if (empty($reminder_days) && $today_weekday !== $created_weekday) {
                    return false;
                }
                $weeks_since = (int) floor($days_since / 7);
                return $weeks_since % 2 === 0;
            case 'monthly':
                return $today_dom === $created_dom;
            case 'as_needed':
            default:
                return false;
        }
    };

    $expected_30d = static function ($frequency) {
        switch ($frequency) {
            case 'daily':
                return 30;
            case 'every_other_day':
                return 15;
            case 'twice_week':
                return 8;
            case 'weekly':
                return 4;
            case 'bi_weekly':
                return 2;
            case 'monthly':
                return 1;
            default:
                return 1;
        }
    };

    $routine_dates = [];
    foreach ($routine_completion_map as $date_key => $ids) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_key) || !is_array($ids)) {
            continue;
        }
        foreach ($ids as $rid) {
            $rid = (int) $rid;
            if (!isset($routine_dates[$rid])) {
                $routine_dates[$rid] = [];
            }
            $routine_dates[$rid][] = (string) $date_key;
        }
    }

    foreach ($routine_dates as $rid => $dates) {
        $dates = array_values(array_unique($dates));
        rsort($dates);
        $routine_dates[$rid] = $dates;
    }

    $normalized_routines = [];
    $group_counts = [
        'daily' => 0,
        'weekly' => 0,
        'monthly' => 0,
        'paused' => 0,
        'as_needed' => 0,
    ];
    $total_expected_30d = 0;
    $total_completed_30d = 0;

    foreach ($routines as $idx => $routine) {
        $title = trim((string) ($routine['title'] ?? ($routine['routine_title'] ?? ($routine['name'] ?? 'Untitled Routine'))));
        $description = trim((string) ($routine['description'] ?? ($routine['notes'] ?? '')));
        $type = trim((string) ($routine['routine_type'] ?? 'Routine'));
        $frequency_raw = (string) ($routine['frequency'] ?? ($routine['routine_frequency'] ?? 'Weekly'));
        $frequency = $normalize_frequency($frequency_raw);
        $frequency_text = $frequency_label($frequency);
        $group = $frequency_group($frequency);
        $time = trim((string) ($routine['routine_time'] ?? ($routine['time'] ?? '')));
        $duration_raw = trim((string) ($routine['routine_duration'] ?? ($routine['duration'] ?? '')));
        $goal_link = trim((string) ($routine['routine_goal_link'] ?? ''));
        $status = strtolower(trim((string) ($routine['status'] ?? 'active')));
        if ($status !== 'paused' && $status !== 'active') {
            $status = 'active';
        }

        $products = $normalize_list($routine['products'] ?? ($routine['routine_products'] ?? []));
        $steps = $normalize_list($routine['steps'] ?? ($routine['routine_steps'] ?? []));
        $reminder_days = $parse_reminder_days($routine['routine_reminder_days'] ?? '');

        $created_at_raw = (string) ($routine['created_at'] ?? '');
        $created_ts = strtotime($created_at_raw ?: $today . ' 00:00:00');
        if (!$created_ts) {
            $created_ts = $today_ts;
        }

        $due_today = $status !== 'paused' && $is_due_today($frequency, $created_ts, $today_ts, $reminder_days);
        $completed_dates = $routine_dates[(int) $idx] ?? [];
        $completed_today = in_array($today, $completed_dates, true);
        $last_completed = !empty($completed_dates) ? $completed_dates[0] : '';

        $sessions_30d = 0;
        $cutoff_ts = strtotime('-30 days', $today_ts);
        foreach ($completed_dates as $completed_date) {
            $date_ts = strtotime($completed_date . ' 00:00:00');
            if ($date_ts && $date_ts >= $cutoff_ts) {
                $sessions_30d++;
            }
        }

        $expected = $expected_30d($frequency);
        $completion_rate = min(100, (int) round(($sessions_30d / max(1, $expected)) * 100));

        if (isset($group_counts[$group])) {
            $group_counts[$group]++;
        }
        if ($status === 'paused') {
            $group_counts['paused']++;
        }

        $total_expected_30d += $expected;
        $total_completed_30d += $sessions_30d;

        $duration_display = $duration_raw;
        if ($duration_display !== '' && preg_match('/^\d+$/', $duration_display)) {
            $duration_display .= ' min';
        }

        $search_blob = strtolower(implode(' ', [
            $title,
            $description,
            $frequency_text,
            $type,
            $goal_link,
            implode(' ', $products),
            implode(' ', $steps),
        ]));

        $normalized_routines[] = [
            'id' => (int) $idx,
            'title' => $title !== '' ? $title : 'Untitled Routine',
            'description' => $description,
            'type' => $type !== '' ? $type : 'Routine',
            'frequency' => $frequency,
            'frequency_text' => $frequency_text,
            'group' => $group,
            'time' => $time,
            'duration' => $duration_display,
            'products' => $products,
            'steps' => $steps,
            'goal_link' => $goal_link,
            'status' => $status,
            'due_today' => $due_today,
            'completed_today' => $completed_today,
            'total_sessions' => count($completed_dates),
            'completion_rate' => $completion_rate,
            'last_completed' => $last_completed,
            'history' => array_slice($completed_dates, 0, 8),
            'search' => $search_blob,
        ];
    }

    usort($normalized_routines, static function ($a, $b) {
        if ($a['completed_today'] !== $b['completed_today']) {
            return $a['completed_today'] ? 1 : -1;
        }
        if ($a['due_today'] !== $b['due_today']) {
            return $a['due_today'] ? -1 : 1;
        }
        return strcasecmp($a['title'], $b['title']);
    });

    $due_today_routines = array_values(array_filter($normalized_routines, static function ($routine) {
        return !empty($routine['due_today']);
    }));

    // Do not present an unscheduled routine as due. A member's wash day or treatment
    // should feel intentional, not like an unexpected task on every visit.
    $today_routines = $due_today_routines;

    $today_total = count($today_routines);
    $today_completed = count(array_filter($today_routines, static function ($routine) {
        return !empty($routine['completed_today']);
    }));
    $today_progress = $today_total > 0 ? (int) round(($today_completed / $today_total) * 100) : 0;

    $global_streak = 0;
    $cursor = $today_ts;
    while ($cursor > 0) {
        $date_key = gmdate('Y-m-d', $cursor);
        $entries = $routine_completion_map[$date_key] ?? [];
        if (!is_array($entries) || empty($entries)) {
            break;
        }
        $global_streak++;
        $cursor -= DAY_IN_SECONDS;
    }

    $overall_completion_rate = $total_expected_30d > 0
        ? min(100, (int) round(($total_completed_30d / $total_expected_30d) * 100))
        : 0;

    $summary_counts = [
        'all' => count($normalized_routines),
        'daily' => $group_counts['daily'],
        'weekly' => $group_counts['weekly'],
        'monthly' => $group_counts['monthly'],
        'paused' => $group_counts['paused'],
        'as_needed' => $group_counts['as_needed'],
    ];

    $routines_json = wp_json_encode(array_values($normalized_routines));
    $icon = static function ($name, $class = '') {
        $class_attr = trim('myavana-rv2-lucide ' . $class);
        return '<i data-lucide="' . esc_attr($name) . '" class="' . esc_attr($class_attr) . '"></i>';
    };

    ob_start();
    ?>
    <div class="hair-journey-container myavana-routines-v2-page" data-today="<?php echo esc_attr($today); ?>">
        <div class="myavana-rv2" id="myavanaRoutinesV2Root">
            <div class="myavana-rv2-hero-stats">
                <article class="myavana-rv2-stat-card">
                    <div class="myavana-rv2-stat-icon coral"><?php echo $icon('repeat-2', 'is-stat'); ?></div>
                    <div>
                        <div class="myavana-rv2-stat-value"><?php echo esc_html(count($normalized_routines)); ?></div>
                        <div class="myavana-rv2-stat-label">Total Routines</div>
                    </div>
                </article>
                <article class="myavana-rv2-stat-card">
                    <div class="myavana-rv2-stat-icon sand"><?php echo $icon('calendar-days', 'is-stat'); ?></div>
                    <div>
                        <div class="myavana-rv2-stat-value"><?php echo esc_html(count($due_today_routines)); ?></div>
                        <div class="myavana-rv2-stat-label">Due Today</div>
                    </div>
                </article>
                <article class="myavana-rv2-stat-card">
                    <div class="myavana-rv2-stat-icon green"><?php echo $icon('flame', 'is-stat'); ?></div>
                    <div>
                        <div class="myavana-rv2-stat-value"><?php echo esc_html($global_streak); ?></div>
                        <div class="myavana-rv2-stat-label">Day Streak</div>
                    </div>
                </article>
                <article class="myavana-rv2-stat-card">
                    <div class="myavana-rv2-stat-icon bb"><?php echo $icon('bar-chart-3', 'is-stat'); ?></div>
                    <div>
                        <div class="myavana-rv2-stat-value"><?php echo esc_html($overall_completion_rate); ?>%</div>
                        <div class="myavana-rv2-stat-label">30-Day Completion</div>
                    </div>
                </article>
            </div>

            <section class="myavana-rv2-today">
                <div class="myavana-rv2-today-head">
                    <div>
                        <p class="myavana-rv2-today-kicker">A gentle plan for <?php echo esc_html(wp_date('l')); ?></p>
                        <div class="myavana-rv2-today-title-row">
                            <h2 class="myavana-rv2-today-title">Care for your hair today</h2>
                        </div>
                    </div>
                    <div class="myavana-rv2-today-progress-label" id="myavanaRv2TodayLabel"><?php echo esc_html($today_completed); ?> / <?php echo esc_html($today_total); ?> complete</div>
                </div>
                <div class="myavana-rv2-today-progress-wrap">
                    <div class="myavana-rv2-today-progress-track">
                        <div class="myavana-rv2-today-progress-fill" id="myavanaRv2TodayBar" style="width: <?php echo esc_attr($today_progress); ?>%"></div>
                    </div>
                    <div class="myavana-rv2-today-sub" id="myavanaRv2TodaySub">
                        <?php if ($today_total > 0 && $today_completed < $today_total) : ?>
                            A little care counts. Start with the one routine that feels most useful right now.
                        <?php elseif ($today_total > 0) : ?>
                            All done for today. Great consistency.
                        <?php else : ?>
                            Nothing is scheduled for today. Use your library whenever you want, or add reminder days to make a routine part of your weekly flow.
                        <?php endif; ?>
                    </div>
                </div>

                <div class="myavana-rv2-today-list" id="myavanaRv2TodayList">
                    <?php if (empty($today_routines)) : ?>
                        <div class="myavana-rv2-empty-inline">
                            <?php if (empty($normalized_routines)) : ?>
                                <p>Build your first routine and start tracking the care that works for your hair.</p>
                                <button type="button" class="myavana-rv2-pill-btn" data-rv2-open-create><?php echo $icon('plus', 'is-btn'); ?>Create Routine</button>
                            <?php else : ?>
                                <p>Nothing is scheduled today. Need a little care anyway? Choose a routine from your library when it feels right.</p>
                                <button type="button" class="myavana-rv2-inline-btn" data-rv2-open-detail="<?php echo esc_attr($normalized_routines[0]['id']); ?>">Choose a routine</button>
                            <?php endif; ?>
                        </div>
                    <?php else : ?>
                        <?php foreach ($today_routines as $t_index => $routine) : ?>
                            <div class="myavana-rv2-today-item <?php echo $routine['completed_today'] ? 'is-done' : ''; ?>" data-rv2-routine="<?php echo esc_attr($routine['id']); ?>">
                                <button
                                    type="button"
                                    class="myavana-rv2-check <?php echo $routine['completed_today'] ? 'done' : ''; ?>"
                                    data-rv2-toggle="<?php echo esc_attr($routine['id']); ?>"
                                    aria-label="Toggle completion for <?php echo esc_attr($routine['title']); ?>"
                                    aria-pressed="<?php echo $routine['completed_today'] ? 'true' : 'false'; ?>">
                                    <?php echo $routine['completed_today'] ? $icon('check', 'is-xs') : ''; ?>
                                </button>
                                <div class="myavana-rv2-today-info">
                                    <div class="myavana-rv2-today-name <?php echo $routine['completed_today'] ? 'is-done' : ''; ?>"><?php echo esc_html($routine['title']); ?></div>
                                    <div class="myavana-rv2-today-meta">
                                        <?php echo esc_html($routine['time'] !== '' ? $routine['time'] : 'Flexible'); ?> ·
                                        <?php echo esc_html(max(1, count($routine['steps']))); ?> step<?php echo count($routine['steps']) === 1 ? '' : 's'; ?>
                                        <?php if ($routine['duration'] !== '') : ?> · <?php echo esc_html($routine['duration']); ?><?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($routine['completed_today']) : ?>
                                    <span class="myavana-rv2-done-badge"><?php echo $icon('check', 'is-xs'); ?>Done</span>
                                <?php else : ?>
                                    <button type="button" class="myavana-rv2-start-btn" data-rv2-start="<?php echo esc_attr($routine['id']); ?>"><?php echo $icon('play', 'is-xs'); ?>Start</button>
                                <?php endif; ?>
                            </div>
                            <?php if ($t_index < count($today_routines) - 1) : ?>
                                <div class="myavana-rv2-divider"></div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="myavana-rv2-template-hub">
                <div class="myavana-rv2-template-head">
                    <div>
                        <h2 class="myavana-rv2-library-title">Routine Templates</h2>
                        <p class="myavana-rv2-library-sub">Start with a proven structure, then adjust every field, product, and step to fit your hair needs.</p>
                    </div>
                    <div class="myavana-rv2-library-actions">
                        <button type="button" class="myavana-rv2-ghost-btn" data-rv2-open-create><?php echo $icon('pencil-line', 'is-btn'); ?>Start from Scratch</button>
                    </div>
                </div>

                <div class="myavana-rv2-template-strip">
                    <?php foreach ($routine_templates as $template) : ?>
                        <article class="myavana-rv2-template-card">
                            <div class="myavana-rv2-template-card__top">
                                <span class="myavana-rv2-template-pill"><?php echo $icon($template['icon'], 'is-xs'); ?><?php echo esc_html($template['label']); ?></span>
                                <span class="myavana-rv2-template-meta"><?php echo esc_html($template['frequency']); ?> · <?php echo esc_html($template['duration']); ?> min</span>
                            </div>
                            <h3><?php echo esc_html($template['title']); ?></h3>
                            <p><?php echo esc_html($template['description']); ?></p>
                            <div class="myavana-rv2-template-tags">
                                <span><?php echo esc_html($template['type']); ?></span>
                                <span><?php echo esc_html($template['phase']); ?></span>
                                <span><?php echo esc_html($template['difficulty']); ?></span>
                            </div>
                            <div class="myavana-rv2-template-steps">
                                <?php foreach (array_slice($template['steps'], 0, 3) as $step_index => $step_text) : ?>
                                    <div class="myavana-rv2-template-step">
                                        <strong><?php echo esc_html($step_index + 1); ?></strong>
                                        <span><?php echo esc_html($step_text); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="myavana-rv2-template-card__foot">
                                <div class="myavana-rv2-template-products"><?php echo esc_html(count($template['products'])); ?> products</div>
                                <div class="myavana-rv2-template-actions">
                                    <button type="button" class="myavana-rv2-inline-btn" data-rv2-preview-template="<?php echo esc_attr($template['key']); ?>">Preview</button>
                                    <button type="button" class="myavana-rv2-pill-btn" data-rv2-template="<?php echo esc_attr($template['key']); ?>"><?php echo $icon('arrow-up-right', 'is-xs'); ?>Use Template</button>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="myavana-rv2-library">
                <div class="myavana-rv2-library-head">
                    <div>
                        <h2 class="myavana-rv2-library-title">My Routine Library</h2>
                        <p class="myavana-rv2-library-sub">Your personal care rhythms — wash days, refreshes, treatments, styling, and anything else that supports your hair.</p>
                    </div>
                    <div class="myavana-rv2-library-actions">
                        <button type="button" class="myavana-rv2-ghost-btn" data-rv2-open-template-picker><?php echo $icon('plus', 'is-btn'); ?>Create Routine</button>
                    </div>
                </div>

                <div class="myavana-rv2-search-wrap">
                    <input type="search" id="myavanaRv2Search" class="myavana-rv2-search" placeholder="Search routines by title, frequency, products, or focus..." />
                </div>

                <div class="myavana-rv2-filters" id="myavanaRv2Filters">
                    <button class="myavana-rv2-filter is-active" type="button" data-filter="all">All (<?php echo esc_html($summary_counts['all']); ?>)</button>
                    <button class="myavana-rv2-filter" type="button" data-filter="daily">Daily (<?php echo esc_html($summary_counts['daily']); ?>)</button>
                    <button class="myavana-rv2-filter" type="button" data-filter="weekly">Weekly (<?php echo esc_html($summary_counts['weekly']); ?>)</button>
                    <button class="myavana-rv2-filter" type="button" data-filter="monthly">Monthly (<?php echo esc_html($summary_counts['monthly']); ?>)</button>
                    <button class="myavana-rv2-filter" type="button" data-filter="as_needed">As Needed (<?php echo esc_html($summary_counts['as_needed']); ?>)</button>
                    <button class="myavana-rv2-filter" type="button" data-filter="paused">Paused (<?php echo esc_html($summary_counts['paused']); ?>)</button>
                </div>

                <div class="myavana-rv2-grid" id="myavanaRv2Grid">
                    <?php if (empty($normalized_routines)) : ?>
                        <div class="myavana-rv2-empty-card">
                            <h3>Start your first routine</h3>
                            <p>Create a repeatable ritual and keep your goals moving every week.</p>
                            <button type="button" class="myavana-rv2-pill-btn" data-rv2-open-create>Create Routine</button>
                        </div>
                    <?php else : ?>
                        <?php foreach ($normalized_routines as $routine) :
                            $card_badge_class = 'weekly';
                            if ($routine['group'] === 'daily') {
                                $card_badge_class = 'daily';
                            } elseif ($routine['group'] === 'monthly') {
                                $card_badge_class = 'monthly';
                            } elseif ($routine['group'] === 'as_needed') {
                                $card_badge_class = 'asneeded';
                            }
                            $status_dot_class = $routine['status'] === 'paused' ? 'paused' : ($routine['completed_today'] ? 'done' : 'active');
                        ?>
                            <article
                                class="myavana-rv2-card <?php echo $routine['completed_today'] ? 'is-completed' : ''; ?>"
                                data-rv2-card
                                data-routine-id="<?php echo esc_attr($routine['id']); ?>"
                                data-search="<?php echo esc_attr($routine['search']); ?>"
                                data-filter-group="<?php echo esc_attr($routine['group']); ?>"
                                data-filter-status="<?php echo esc_attr($routine['status']); ?>"
                                data-title="<?php echo esc_attr($routine['title']); ?>"
                                data-description="<?php echo esc_attr($routine['description']); ?>"
                                data-type="<?php echo esc_attr($routine['type']); ?>"
                                data-frequency="<?php echo esc_attr($routine['frequency_text']); ?>"
                                data-time="<?php echo esc_attr($routine['time']); ?>"
                                data-duration="<?php echo esc_attr($routine['duration']); ?>"
                                data-goal="<?php echo esc_attr($routine['goal_link']); ?>"
                                data-products="<?php echo esc_attr(wp_json_encode($routine['products'])); ?>"
                                data-steps="<?php echo esc_attr(wp_json_encode($routine['steps'])); ?>"
                                data-history="<?php echo esc_attr(wp_json_encode($routine['history'])); ?>"
                                data-completion-rate="<?php echo esc_attr($routine['completion_rate']); ?>"
                                data-total-sessions="<?php echo esc_attr($routine['total_sessions']); ?>"
                                data-last-completed="<?php echo esc_attr($routine['last_completed']); ?>"
                                data-completed-today="<?php echo $routine['completed_today'] ? '1' : '0'; ?>"
                                data-due-today="<?php echo $routine['due_today'] ? '1' : '0'; ?>">
                                <div class="myavana-rv2-card-top" data-rv2-open-detail="<?php echo esc_attr($routine['id']); ?>">
                                    <div class="myavana-rv2-card-badges">
                                        <span class="myavana-rv2-type-badge <?php echo esc_attr($card_badge_class); ?>">
                                            <?php
                                            if ($routine['group'] === 'daily') {
                                                echo $icon('sun', 'is-xs');
                                            } elseif ($routine['group'] === 'monthly') {
                                                echo $icon('calendar', 'is-xs');
                                            } elseif ($routine['group'] === 'as_needed') {
                                                echo $icon('leaf', 'is-xs');
                                            } else {
                                                echo $icon('calendar-days', 'is-xs');
                                            }
                                            ?>
                                            <?php echo esc_html($routine['frequency_text']); ?>
                                        </span>
                                        <span class="myavana-rv2-status-dot <?php echo esc_attr($status_dot_class); ?>"></span>
                                    </div>

                                    <h3 class="myavana-rv2-card-title"><?php echo esc_html($routine['title']); ?></h3>
                                    <div class="myavana-rv2-card-sub">
                                        <span><?php echo esc_html($routine['type']); ?></span>
                                        <?php if ($routine['time'] !== '') : ?>
                                            <span>· <?php echo esc_html($routine['time']); ?></span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="myavana-rv2-card-stats">
                                        <div class="item">
                                            <div class="val"><?php echo esc_html(max(1, count($routine['steps']))); ?></div>
                                            <div class="lbl">Steps</div>
                                        </div>
                                        <div class="item">
                                            <div class="val"><?php echo esc_html(max(0, count($routine['products']))); ?></div>
                                            <div class="lbl">Products</div>
                                        </div>
                                        <div class="item">
                                            <div class="val"><?php echo esc_html($routine['duration'] !== '' ? $routine['duration'] : '-'); ?></div>
                                            <div class="lbl">Duration</div>
                                        </div>
                                    </div>

                                    <?php if ($routine['goal_link'] !== '') : ?>
                                        <div class="myavana-rv2-goal-chip"><?php echo $icon('target', 'is-xs'); ?><?php echo esc_html($routine['goal_link']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="myavana-rv2-card-bottom">
                                    <div>
                                        <div class="myavana-rv2-last">
                                            <?php if ($routine['last_completed'] !== '') : ?>
                                                Last: <?php echo esc_html(date_i18n('M j, Y', strtotime($routine['last_completed']))); ?>
                                            <?php else : ?>
                                                Not completed yet
                                            <?php endif; ?>
                                        </div>
                                        <div class="myavana-rv2-streak"><?php echo $icon('trending-up', 'is-xs'); ?><?php echo esc_html($routine['completion_rate']); ?>% 30-day completion</div>
                                    </div>
                                    <div class="myavana-rv2-card-actions">
                                        <button type="button" class="myavana-rv2-inline-btn" data-rv2-edit="<?php echo esc_attr($routine['id']); ?>">Edit</button>
                                        <button type="button" class="myavana-rv2-inline-btn" data-rv2-delete="<?php echo esc_attr($routine['id']); ?>">Delete</button>
                                        <button type="button" class="myavana-rv2-inline-btn primary" data-rv2-start="<?php echo esc_attr($routine['id']); ?>"><?php echo $icon('play', 'is-xs'); ?>Start</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>

                        <article class="myavana-rv2-create-card" data-rv2-open-template-picker>
                            <div class="myavana-rv2-create-icon"><?php echo $icon('plus', 'is-create'); ?></div>
                            <h3>Create New Routine</h3>
                            <p>Build a step-by-step routine and link it to a goal.</p>
                        </article>
                    <?php endif; ?>
                </div>
            </section>

            <button type="button" class="myavana-rv2-fab" data-rv2-open-template-picker aria-label="Create routine"><?php echo $icon('plus', 'is-fab'); ?></button>

            <div class="myavana-rv2-overlay" id="myavanaRv2Overlay"></div>

            <aside class="myavana-rv2-drawer" id="myavanaRv2Drawer" aria-hidden="true">
                <header class="myavana-rv2-drawer-head">
                    <h3>Routine Detail</h3>
                    <div class="myavana-rv2-drawer-head-actions">
                        <div class="myavana-rv2-drawer-tabs" id="myavanaRv2DrawerTabs">
                            <button type="button" class="tab is-active" data-tab="overview">Overview</button>
                            <button type="button" class="tab" data-tab="steps">Steps</button>
                            <button type="button" class="tab" data-tab="history">History</button>
                        </div>
                        <button type="button" class="myavana-rv2-drawer-close" data-rv2-close-detail aria-label="Close"><?php echo $icon('x', 'is-close'); ?></button>
                    </div>
                </header>

                <div class="myavana-rv2-drawer-body">
                    <section class="myavana-rv2-pane is-active" data-pane="overview">
                        <div class="myavana-rv2-hero-block">
                            <div class="myavana-rv2-hero-badge-row">
                                <span class="myavana-rv2-type-badge weekly" id="myavanaRv2DetailFrequency"><?php echo $icon('calendar-days', 'is-xs'); ?>Weekly</span>
                                <span class="myavana-rv2-status-dot active" id="myavanaRv2DetailStatus"></span>
                            </div>
                            <h4 id="myavanaRv2DetailTitle">Routine title</h4>
                            <p id="myavanaRv2DetailDesc">Routine summary</p>
                            <div class="myavana-rv2-meta-pills" id="myavanaRv2DetailMeta"></div>
                        </div>
                        <div class="myavana-rv2-pane-content">
                            <div class="myavana-rv2-sec" id="myavanaRv2GoalSection">
                                <div class="label">Linked Goal</div>
                                <div class="myavana-rv2-goal-chip" id="myavanaRv2DetailGoal">No goal linked</div>
                            </div>
                            <div class="myavana-rv2-sec">
                                <div class="label">Products Required</div>
                                <div class="myavana-rv2-tags" id="myavanaRv2DetailProducts"></div>
                            </div>
                            <div class="myavana-rv2-sec">
                                <div class="label">Quick Stats</div>
                                <div class="myavana-rv2-mini-grid" id="myavanaRv2DetailStats"></div>
                            </div>
                        </div>
                    </section>

                    <section class="myavana-rv2-pane" data-pane="steps">
                        <div class="myavana-rv2-pane-content">
                            <div class="label">Routine Steps</div>
                            <div id="myavanaRv2DetailSteps" class="myavana-rv2-step-list"></div>
                        </div>
                    </section>

                    <section class="myavana-rv2-pane" data-pane="history">
                        <div class="myavana-rv2-pane-content">
                            <div class="label">Recent Sessions</div>
                            <div id="myavanaRv2DetailHistory" class="myavana-rv2-history-list"></div>
                        </div>
                    </section>
                </div>

                <footer class="myavana-rv2-drawer-foot">
                    <button type="button" class="myavana-rv2-pill-btn" id="myavanaRv2StartSessionBtn"><?php echo $icon('play', 'is-xs'); ?>Start Session</button>
                    <button type="button" class="myavana-rv2-pill-btn ghost" id="myavanaRv2EditBtn"><?php echo $icon('pencil', 'is-xs'); ?>Edit</button>
                    <button type="button" class="myavana-rv2-pill-btn danger" id="myavanaRv2DeleteBtn"><?php echo $icon('trash-2', 'is-xs'); ?>Delete</button>
                </footer>
            </aside>

            <?php include __DIR__ . '/partials/view-routine-session.php'; ?>

            <div class="myavana-rv2-picker" id="myavanaRv2Picker" aria-hidden="true">
                <div class="myavana-rv2-session-backdrop" data-rv2-close-picker></div>
                <div class="myavana-rv2-picker-sheet">
                    <div class="myavana-rv2-handle"></div>
                    <h3>Create a Routine</h3>
                    <p>Choose a template to start faster.</p>
                    <div class="myavana-rv2-template-grid">
                        <?php foreach ($routine_templates as $template) : ?>
                            <button type="button" data-rv2-preview-template="<?php echo esc_attr($template['key']); ?>">
                                <?php echo $icon($template['icon'], 'is-xs'); ?>
                                <span><?php echo esc_html($template['title']); ?></span>
                            </button>
                        <?php endforeach; ?>
                        <button type="button" data-rv2-open-create>
                            <?php echo $icon('wand-sparkles', 'is-xs'); ?>
                            <span>Start from Scratch</span>
                        </button>
                    </div>
                </div>
            </div>

            <div class="myavana-rv2-template-preview" id="myavanaRv2TemplatePreview" aria-hidden="true">
                <div class="myavana-rv2-session-backdrop" data-rv2-close-template-preview></div>
                <div class="myavana-rv2-template-preview-sheet">
                    <div class="myavana-rv2-handle"></div>
                    <header class="myavana-rv2-template-preview-head">
                        <div>
                            <div class="myavana-rv2-template-preview-kicker" id="myavanaRv2TemplatePreviewKicker">Template</div>
                            <h3 id="myavanaRv2TemplatePreviewTitle">Routine Template</h3>
                            <p id="myavanaRv2TemplatePreviewDesc">Template summary</p>
                        </div>
                        <button type="button" class="myavana-rv2-drawer-close" data-rv2-close-template-preview aria-label="Close"><?php echo $icon('x', 'is-close'); ?></button>
                    </header>
                    <div class="myavana-rv2-template-preview-body">
                        <div class="myavana-rv2-template-preview-meta" id="myavanaRv2TemplatePreviewMeta"></div>
                        <div class="myavana-rv2-template-preview-section">
                            <div class="label">Steps</div>
                            <div class="myavana-rv2-step-list" id="myavanaRv2TemplatePreviewSteps"></div>
                        </div>
                        <div class="myavana-rv2-template-preview-section">
                            <div class="label">Products</div>
                            <div class="myavana-rv2-tags" id="myavanaRv2TemplatePreviewProducts"></div>
                        </div>
                        <div class="myavana-rv2-template-preview-section" id="myavanaRv2TemplatePreviewToolsWrap">
                            <div class="label">Tools</div>
                            <div class="myavana-rv2-template-preview-copy" id="myavanaRv2TemplatePreviewTools"></div>
                        </div>
                        <div class="myavana-rv2-template-preview-section" id="myavanaRv2TemplatePreviewOutcomeWrap">
                            <div class="label">Expected Outcome</div>
                            <div class="myavana-rv2-template-preview-copy" id="myavanaRv2TemplatePreviewOutcome"></div>
                        </div>
                        <div class="myavana-rv2-template-preview-section" id="myavanaRv2TemplatePreviewNotesWrap">
                            <div class="label">Notes</div>
                            <div class="myavana-rv2-template-preview-copy" id="myavanaRv2TemplatePreviewNotes"></div>
                        </div>
                    </div>
                    <footer class="myavana-rv2-template-preview-foot">
                        <button type="button" class="myavana-rv2-pill-btn ghost" data-rv2-open-create><?php echo $icon('pencil-line', 'is-xs'); ?>Start from Scratch</button>
                        <button type="button" class="myavana-rv2-pill-btn" id="myavanaRv2TemplatePreviewUse"><?php echo $icon('arrow-up-right', 'is-xs'); ?>Use Template</button>
                    </footer>
                </div>
            </div>
        </div>

        <script type="application/json" id="myavanaRoutinesV2Data"><?php echo wp_json_encode([
            'today' => $today,
            'routines' => $normalized_routines,
            'templates' => $routine_templates,
        ]); ?></script>

        <?php
        $partials_dir = __DIR__ . '/partials';
        // This screen owns its dedicated routine detail drawer above. Do not load the
        // generic timeline viewer here: it contains unrelated analysis, entry and goal
        // modals that can surface without the correct styling or context.
        if (file_exists($partials_dir . '/create-offcanvas.php')) {
            $myavana_hjn_context = 'routines';
            include $partials_dir . '/create-offcanvas.php';
            unset($myavana_hjn_context);
        }
        ?>
    </div>
    <?php

    wp_add_inline_script(
        'myavana-routines-page-redesign',
        'window.myavanaRoutinesV2Boot = ' . ($routines_json ?: '[]') . ';',
        'before'
    );

    return ob_get_clean();
}
}
