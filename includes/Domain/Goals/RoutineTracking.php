<?php
/**
 * Shared routine tracking helpers.
 *
 * Keeps calendar, routines, profile, and gamification aligned on one
 * schedule/completion/reminder model.
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('myavana_normalize_routine_frequency')) {
    function myavana_normalize_routine_frequency($frequency) {
        $value = strtolower(trim((string) $frequency));
        $value = str_replace(['_', '  '], ['-', ' '], $value);

        if (in_array($value, ['every day', 'daily'], true)) {
            return 'daily';
        }

        if (in_array($value, ['weekly', 'week'], true)) {
            return 'weekly';
        }

        if (in_array($value, ['bi-weekly', 'biweekly', 'every other week'], true)) {
            return 'bi-weekly';
        }

        if (in_array($value, ['monthly', 'month'], true)) {
            return 'monthly';
        }

        if (in_array($value, ['as needed', 'as-needed', 'asneeded'], true)) {
            return 'as-needed';
        }

        return $value !== '' ? $value : 'weekly';
    }
}

if (!function_exists('myavana_parse_routine_reminder_days')) {
    function myavana_parse_routine_reminder_days($raw_value) {
        $value = trim((string) $raw_value);
        if ($value === '') {
            return [];
        }

        $parts = preg_split('/[,\/\s]+/', $value);
        $days = [];
        foreach ((array) $parts as $part) {
            $day = strtolower(substr(trim((string) $part), 0, 3));
            if ($day !== '') {
                $days[] = $day;
            }
        }

        return array_values(array_unique(array_filter($days)));
    }
}

if (!function_exists('myavana_normalize_routine_time')) {
    function myavana_normalize_routine_time($routine) {
        $routine = function_exists('myavana_normalize_routine_tracking_item')
            ? myavana_normalize_routine_tracking_item($routine)
            : (is_array($routine) ? $routine : []);

        $value = trim((string) (
            $routine['time'] ??
            $routine['time_of_day'] ??
            $routine['routine_time'] ??
            ''
        ));

        if (preg_match('/^\d{1,2}:\d{2}/', $value)) {
            $parts = explode(':', $value);
            $hour = max(0, min(23, intval($parts[0] ?? 8)));
            $minute = max(0, min(59, intval($parts[1] ?? 0)));
            return sprintf('%02d:%02d', $hour, $minute);
        }

        $lower = strtolower($value);
        if (strpos($lower, 'morning') !== false) {
            return '08:00';
        }
        if (strpos($lower, 'afternoon') !== false) {
            return '13:00';
        }
        if (strpos($lower, 'evening') !== false) {
            return '18:00';
        }
        if (strpos($lower, 'night') !== false) {
            return '21:00';
        }

        return '08:00';
    }
}

if (!function_exists('myavana_get_routine_created_date')) {
    function myavana_get_routine_created_date($routine, $fallback_date = '') {
        $routine = function_exists('myavana_normalize_routine_tracking_item')
            ? myavana_normalize_routine_tracking_item($routine)
            : (is_array($routine) ? $routine : []);

        $raw = (string) (
            $routine['created_at'] ??
            $routine['created_on'] ??
            $routine['date_created'] ??
            $routine['start_date'] ??
            ''
        );

        $timestamp = strtotime($raw);
        if (!$timestamp) {
            $timestamp = $fallback_date ? strtotime($fallback_date . ' 00:00:00') : current_time('timestamp');
        }

        return date('Y-m-d', $timestamp);
    }
}

if (!function_exists('myavana_normalize_routine_tracking_item')) {
    function myavana_normalize_routine_tracking_item($routine) {
        if (is_array($routine)) {
            return $routine;
        }

        if (is_object($routine)) {
            return (array) $routine;
        }

        if (is_string($routine)) {
            $trimmed = trim($routine);
            if ($trimmed === '') {
                return [];
            }

            $decoded = json_decode($trimmed, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            $unserialized = maybe_unserialize($trimmed);
            if (is_array($unserialized)) {
                return $unserialized;
            }
        }

        return [];
    }
}

if (!function_exists('myavana_get_routine_record_for_date')) {
    function myavana_get_routine_record_for_date($records, $routine_id, $date_str) {
        if (!isset($records[$date_str]) || !is_array($records[$date_str])) {
            return null;
        }

        $key = (string) intval($routine_id);
        return isset($records[$date_str][$key]) && is_array($records[$date_str][$key])
            ? $records[$date_str][$key]
            : null;
    }
}

if (!function_exists('myavana_get_routine_tracking_records')) {
    function myavana_get_routine_tracking_records($user_id) {
        $records = get_user_meta($user_id, 'myavana_routine_completion_records', true);
        if (!is_array($records)) {
            $records = [];
        }

        $normalized = [];
        foreach ($records as $date_key => $items) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_key) || !is_array($items)) {
                continue;
            }

            foreach ($items as $routine_id => $record) {
                $routine_key = (string) intval($routine_id);

                if (is_array($record)) {
                    $status = sanitize_key($record['status'] ?? '');
                    $logged_at = sanitize_text_field($record['logged_at'] ?? '');
                    $source = sanitize_key($record['source'] ?? 'manual');
                    $snoozed_until = sanitize_text_field($record['snoozed_until'] ?? '');
                } else {
                    $status = !empty($record) ? 'completed' : '';
                    $logged_at = '';
                    $source = 'legacy';
                    $snoozed_until = '';
                }

                if (!in_array($status, ['completed', 'skipped', 'snoozed'], true)) {
                    continue;
                }

                $normalized[$date_key][$routine_key] = [
                    'status' => $status,
                    'logged_at' => $logged_at,
                    'source' => $source,
                    'snoozed_until' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $snoozed_until) ? $snoozed_until : '',
                ];
            }
        }

        if (!empty($normalized)) {
            return $normalized;
        }

        $legacy_map = get_user_meta($user_id, 'myavana_routine_completions', true);
        if (!is_array($legacy_map)) {
            return [];
        }

        foreach ($legacy_map as $date_key => $routine_ids) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $date_key) || !is_array($routine_ids)) {
                continue;
            }

            foreach ($routine_ids as $routine_id) {
                $normalized[$date_key][(string) intval($routine_id)] = [
                    'status' => 'completed',
                    'logged_at' => '',
                    'source' => 'legacy',
                    'snoozed_until' => '',
                ];
            }
        }

        if (!empty($normalized)) {
            update_user_meta($user_id, 'myavana_routine_completion_records', $normalized);
        }

        return $normalized;
    }
}

if (!function_exists('myavana_save_routine_tracking_records')) {
    function myavana_save_routine_tracking_records($user_id, $records) {
        return update_user_meta($user_id, 'myavana_routine_completion_records', $records);
    }
}

if (!function_exists('myavana_sync_legacy_routine_completion_map')) {
    function myavana_sync_legacy_routine_completion_map($user_id, $records) {
        $completion_map = [];

        foreach ((array) $records as $date_key => $items) {
            if (!is_array($items)) {
                continue;
            }

            foreach ($items as $routine_id => $record) {
                if (!is_array($record) || ($record['status'] ?? '') !== 'completed') {
                    continue;
                }

                $completion_map[$date_key][] = intval($routine_id);
            }
        }

        foreach ($completion_map as $date_key => $routine_ids) {
            $completion_map[$date_key] = array_values(array_unique(array_map('intval', $routine_ids)));
            sort($completion_map[$date_key]);
        }

        update_user_meta($user_id, 'myavana_routine_completions', $completion_map);

        return $completion_map;
    }
}

if (!function_exists('myavana_routine_matches_date')) {
    function myavana_routine_matches_date($routine, $date_str) {
        $routine = myavana_normalize_routine_tracking_item($routine);
        if (empty($routine)) {
            return false;
        }

        $target_ts = strtotime($date_str . ' 00:00:00');
        if (!$target_ts) {
            return false;
        }

        $status = strtolower(trim((string) ($routine['status'] ?? 'active')));
        if ($status === 'paused') {
            return false;
        }

        $frequency = myavana_normalize_routine_frequency($routine['frequency'] ?? ($routine['routine_frequency'] ?? 'weekly'));
        if ($frequency === 'as-needed') {
            return false;
        }

        $reminder_days = myavana_parse_routine_reminder_days($routine['routine_reminder_days'] ?? '');
        $created_date = myavana_get_routine_created_date($routine, $date_str);
        $created_ts = strtotime($created_date . ' 00:00:00');
        if (!$created_ts) {
            $created_ts = $target_ts;
        }

        $day_short = strtolower(date('D', $target_ts));
        $created_day_short = strtolower(date('D', $created_ts));
        $day_number = intval(date('j', $target_ts));
        $created_day_number = intval(date('j', $created_ts));
        $diff_days = intval(floor(abs($target_ts - $created_ts) / DAY_IN_SECONDS));

        if (!empty($reminder_days)) {
            if (!in_array(substr($day_short, 0, 3), $reminder_days, true)) {
                return false;
            }

            if ($frequency === 'bi-weekly') {
                return $diff_days % 14 === 0;
            }

            return true;
        }

        switch ($frequency) {
            case 'daily':
                return true;
            case 'weekly':
                return substr($day_short, 0, 3) === substr($created_day_short, 0, 3);
            case 'bi-weekly':
                return substr($day_short, 0, 3) === substr($created_day_short, 0, 3) && ($diff_days % 14 === 0);
            case 'monthly':
                return $day_number === $created_day_number;
            default:
                return substr($day_short, 0, 3) === substr($created_day_short, 0, 3);
        }
    }
}

if (!function_exists('myavana_get_routine_due_dates_between')) {
    function myavana_get_routine_due_dates_between($routine, $start_date, $end_date, $limit = 90) {
        $dates = [];
        $cursor = strtotime($start_date . ' 00:00:00');
        $end_ts = strtotime($end_date . ' 00:00:00');
        if (!$cursor || !$end_ts) {
            return $dates;
        }

        $steps = 0;
        while ($cursor <= $end_ts && $steps < $limit) {
            $date_key = date('Y-m-d', $cursor);
            if (myavana_routine_matches_date($routine, $date_key)) {
                $dates[] = $date_key;
            }
            $cursor += DAY_IN_SECONDS;
            $steps++;
        }

        return $dates;
    }
}

if (!function_exists('myavana_get_routine_next_due_date')) {
    function myavana_get_routine_next_due_date($routine, $from_date = '') {
        $from_date = $from_date ?: current_time('Y-m-d');
        $from_ts = strtotime($from_date . ' 00:00:00');
        if (!$from_ts) {
            return '';
        }

        for ($i = 0; $i < 60; $i++) {
            $candidate = date('Y-m-d', $from_ts + ($i * DAY_IN_SECONDS));
            if (myavana_routine_matches_date($routine, $candidate)) {
                return $candidate;
            }
        }

        return '';
    }
}

if (!function_exists('myavana_get_routine_completed_dates')) {
    function myavana_get_routine_completed_dates($records, $routine_id) {
        $dates = [];
        $routine_key = (string) intval($routine_id);

        foreach ((array) $records as $date_key => $items) {
            if (!isset($items[$routine_key]) || !is_array($items[$routine_key])) {
                continue;
            }

            if (($items[$routine_key]['status'] ?? '') === 'completed') {
                $dates[] = (string) $date_key;
            }
        }

        rsort($dates);

        return array_values(array_unique($dates));
    }
}

if (!function_exists('myavana_calculate_routine_streak')) {
    function myavana_calculate_routine_streak($routine, $records, $routine_id, $reference_date = '') {
        $routine = myavana_normalize_routine_tracking_item($routine);
        if (empty($routine)) {
            return 0;
        }

        $reference_date = $reference_date ?: current_time('Y-m-d');
        $due_dates = myavana_get_routine_due_dates_between($routine, date('Y-m-d', strtotime('-90 days', strtotime($reference_date . ' 00:00:00'))), $reference_date, 120);
        if (empty($due_dates)) {
            return 0;
        }

        rsort($due_dates);
        $streak = 0;

        foreach ($due_dates as $date_key) {
            $record = myavana_get_routine_record_for_date($records, $routine_id, $date_key);
            if (($record['status'] ?? '') === 'completed') {
                $streak++;
                continue;
            }

            break;
        }

        return $streak;
    }
}

if (!function_exists('myavana_calculate_routine_30d_adherence')) {
    function myavana_calculate_routine_30d_adherence($routine, $records, $routine_id, $reference_date = '') {
        $routine = myavana_normalize_routine_tracking_item($routine);
        if (empty($routine)) {
            return 0;
        }

        $reference_date = $reference_date ?: current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime('-29 days', strtotime($reference_date . ' 00:00:00')));
        $due_dates = myavana_get_routine_due_dates_between($routine, $start_date, $reference_date, 45);
        if (empty($due_dates)) {
            return 0;
        }

        $completed = 0;
        foreach ($due_dates as $date_key) {
            $record = myavana_get_routine_record_for_date($records, $routine_id, $date_key);
            if (($record['status'] ?? '') === 'completed') {
                $completed++;
            }
        }

        return (int) round(($completed / max(1, count($due_dates))) * 100);
    }
}

if (!function_exists('myavana_calculate_global_routine_streak')) {
    function myavana_calculate_global_routine_streak($records, $reference_date = '') {
        $reference_date = $reference_date ?: current_time('Y-m-d');
        $cursor = strtotime($reference_date . ' 00:00:00');
        $streak = 0;

        for ($i = 0; $i < 60; $i++) {
            $date_key = date('Y-m-d', $cursor - ($i * DAY_IN_SECONDS));
            $items = isset($records[$date_key]) && is_array($records[$date_key]) ? $records[$date_key] : [];
            $completed = false;
            foreach ($items as $record) {
                if (is_array($record) && ($record['status'] ?? '') === 'completed') {
                    $completed = true;
                    break;
                }
            }

            if (!$completed) {
                break;
            }

            $streak++;
        }

        return $streak;
    }
}

if (!function_exists('myavana_award_routine_streak_bonus')) {
    function myavana_award_routine_streak_bonus($user_id, $routine_id, $routine_key, $streak) {
        if (!in_array(intval($streak), [3, 7, 14, 30], true)) {
            return;
        }

        // The points/badges gamification subsystem (Myavana_Gamification,
        // myavana_award_points) hasn't been ported to this plugin yet —
        // skip the bonus rather than fatal-erroring the whole routine
        // completion request over a nice-to-have.
        if (!function_exists('myavana_award_points') || !class_exists('Myavana_Gamification')) {
            return;
        }

        $defaults = [
            3 => 15,
            7 => 30,
            14 => 60,
            30 => 120,
        ];

        myavana_award_points(
            $user_id,
            Myavana_Gamification::get_reward_value('routine_streak_bonus_' . intval($streak), $defaults[intval($streak)]),
            sprintf('Routine streak milestone (%d)', intval($streak)),
            'routine',
            $routine_id,
            sprintf('routine_streak:%s:%d', sanitize_key($routine_key), intval($streak)),
            [
                'routine_key' => sanitize_key($routine_key),
                'streak' => intval($streak),
            ]
        );
    }
}

if (!function_exists('myavana_get_routine_tracking_context')) {
    function myavana_get_routine_tracking_context($user_id, $routines = []) {
        $today = current_time('Y-m-d');
        $today_ts = strtotime($today . ' 00:00:00');
        $records = myavana_get_routine_tracking_records($user_id);
        $routines = is_array($routines) ? $routines : [];

        $summary = [
            'total' => count($routines),
            'due_today' => 0,
            'completed_today' => 0,
            'pending_today' => 0,
            'overdue' => 0,
            'upcoming' => 0,
            'adherence_30d' => 0,
            'global_streak' => myavana_calculate_global_routine_streak($records, $today),
            'best_routine_streak' => 0,
        ];

        $notifications = [];
        $routines_by_id = [];
        $adherence_total = 0;
        $adherence_count = 0;

        foreach ($routines as $idx => $routine) {
            $routine = myavana_normalize_routine_tracking_item($routine);
            if (empty($routine)) {
                continue;
            }

            $routine_id = intval($routine['id'] ?? $routine['routine_id'] ?? $idx);
            $title = trim((string) ($routine['title'] ?? $routine['routine_title'] ?? $routine['name'] ?? 'Routine'));
            $time = myavana_normalize_routine_time($routine);
            $due_today = myavana_routine_matches_date($routine, $today);
            $today_record = myavana_get_routine_record_for_date($records, $routine_id, $today);
            $today_status = $today_record['status'] ?? '';

            $last_due_date = '';
            for ($i = 1; $i <= 14; $i++) {
                $date_key = date('Y-m-d', $today_ts - ($i * DAY_IN_SECONDS));
                if (myavana_routine_matches_date($routine, $date_key)) {
                    $last_due_date = $date_key;
                    break;
                }
            }

            $last_due_record = $last_due_date ? myavana_get_routine_record_for_date($records, $routine_id, $last_due_date) : null;
            $overdue = !$due_today && $last_due_date && (($last_due_record['status'] ?? '') !== 'completed') && (($last_due_record['status'] ?? '') !== 'skipped');

            $next_due = myavana_get_routine_next_due_date($routine, $today);
            $current_streak = myavana_calculate_routine_streak($routine, $records, $routine_id, $today);
            $adherence = myavana_calculate_routine_30d_adherence($routine, $records, $routine_id, $today);
            $completed_dates = myavana_get_routine_completed_dates($records, $routine_id);

            $summary['best_routine_streak'] = max($summary['best_routine_streak'], $current_streak);
            $adherence_total += $adherence;
            $adherence_count++;

            if ($due_today) {
                $summary['due_today']++;
                if ($today_status === 'completed') {
                    $summary['completed_today']++;
                } elseif ($today_status === '') {
                    $summary['pending_today']++;
                }
            }

            if ($overdue) {
                $summary['overdue']++;
            }

            if ($next_due !== '' && $next_due > $today && $next_due <= date('Y-m-d', strtotime('+7 days', $today_ts))) {
                $summary['upcoming']++;
            }

            $notification_type = '';
            $notification_date = '';
            $notification_copy = '';
            if ($overdue) {
                $notification_type = 'overdue';
                $notification_date = $last_due_date;
                $notification_copy = 'Missed last due date. Log it, skip it, or reset the reminder.';
            } elseif ($due_today && $today_status === '') {
                $notification_type = 'due';
                $notification_date = $today;
                $notification_copy = 'Scheduled for today. Mark it done from your calendar when you finish.';
            } elseif ($next_due !== '' && $next_due > $today && $next_due <= date('Y-m-d', strtotime('+3 days', $today_ts))) {
                $notification_type = 'upcoming';
                $notification_date = $next_due;
                $notification_copy = 'Coming up soon. Keep products ready so you stay on streak.';
            }

            if ($notification_type !== '') {
                $notifications[] = [
                    'routine_id' => $routine_id,
                    'title' => $title !== '' ? $title : 'Routine',
                    'type' => $notification_type,
                    'date' => $notification_date,
                    'time' => $time,
                    'message' => $notification_copy,
                    'status' => $today_status,
                ];
            }

            $routines_by_id[$routine_id] = [
                'id' => $routine_id,
                'title' => $title !== '' ? $title : 'Routine',
                'time' => $time,
                'due_today' => $due_today,
                'today_status' => $today_status,
                'overdue' => $overdue,
                'last_due_date' => $last_due_date,
                'next_due_date' => $next_due,
                'last_completed' => !empty($completed_dates) ? $completed_dates[0] : '',
                'completion_rate_30d' => $adherence,
                'current_streak' => $current_streak,
                'completed_dates' => array_slice($completed_dates, 0, 30),
                'schedule_days' => myavana_parse_routine_reminder_days($routine['routine_reminder_days'] ?? ''),
            ];
        }

        $summary['adherence_30d'] = $adherence_count > 0
            ? (int) round($adherence_total / $adherence_count)
            : 0;

        usort($notifications, static function ($a, $b) {
            $priority = ['overdue' => 0, 'due' => 1, 'upcoming' => 2];
            $a_priority = $priority[$a['type']] ?? 99;
            $b_priority = $priority[$b['type']] ?? 99;
            if ($a_priority !== $b_priority) {
                return $a_priority <=> $b_priority;
            }

            return strcmp((string) ($a['date'] ?? ''), (string) ($b['date'] ?? ''));
        });

        return [
            'summary' => $summary,
            'notifications' => array_slice($notifications, 0, 8),
            'records' => $records,
            'routines_by_id' => $routines_by_id,
        ];
    }
}
