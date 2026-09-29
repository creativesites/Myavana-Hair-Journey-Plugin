<?php
/**
 * Monthly Recap — "Your September in hair": the month's photos, first and
 * last side by side, what she logged, and one-tap sharing to Community.
 *
 * Built only from her entries; nothing is inferred or invented.
 *
 * @package Myavana\Next\Application
 */

namespace Myavana\Next\Application;

use Myavana\Next\Domain\Goals\GoalRepository;
use Myavana\Next\Domain\Journal\JournalRepository;

if (!defined('ABSPATH')) {
    exit;
}

class RecapService {
    /**
     * @param string $month "YYYY-MM"
     * @return array|null Null when the month has no entries.
     */
    public function forMonth(int $userId, string $month): ?array {
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return null;
        }
        $start = strtotime($month . '-01 00:00:00');
        $end = strtotime('+1 month', $start);

        $all = (new JournalRepository())->getEntries($userId, ['perPage' => 200])['items'] ?? [];
        $entries = array_values(array_filter($all, static function ($e) use ($start, $end) {
            $ts = strtotime((string) $e['date']);
            return $ts >= $start && $ts < $end;
        }));
        if (empty($entries)) {
            return null;
        }
        usort($entries, static fn($a, $b) => strtotime((string) $a['date']) <=> strtotime((string) $b['date']));

        $photos = [];
        $weeks = [];
        $goalIds = [];
        foreach ($entries as $e) {
            $weeks[date('o-W', strtotime((string) $e['date']))] = true;
            if (!empty($e['goalId'])) {
                $goalIds[(string) $e['goalId']] = true;
            }
            $list = !empty($e['photos']) ? (array) $e['photos'] : (!empty($e['featuredImage']) ? [$e['featuredImage']] : []);
            foreach ($list as $url) {
                if ($url) {
                    $photos[] = [
                        'url' => (string) $url,
                        'date' => substr((string) $e['date'], 0, 10),
                        'title' => (string) ($e['title'] ?? ''),
                        'entryId' => (int) $e['id'],
                    ];
                }
            }
        }

        $goalsTouched = [];
        if ($goalIds) {
            foreach ((new GoalRepository())->getGoals($userId) as $goal) {
                $key = (string) ($goal['id'] ?? $goal['goal_key'] ?? '');
                if ($key !== '' && isset($goalIds[$key])) {
                    $goalsTouched[] = trim((string) ($goal['title'] ?? $goal['goal_title'] ?? ''));
                }
            }
        }

        $count = count($entries);
        $label = date_i18n('F', $start);
        $summary = sprintf(
            /* translators: 1: entries, 2: weeks, 3: photos */
            __('%1$s across %2$s, with %3$s.', 'myavana-hair-journey-next'),
            sprintf(_n('%d entry', '%d entries', $count, 'myavana-hair-journey-next'), $count),
            sprintf(_n('%d week', '%d weeks', count($weeks), 'myavana-hair-journey-next'), count($weeks)),
            sprintf(_n('%d photo', '%d photos', count($photos), 'myavana-hair-journey-next'), count($photos))
        );

        return [
            'month' => $month,
            'label' => $label,
            'year' => date_i18n('Y', $start),
            'entries' => $count,
            'weeks' => count($weeks),
            'photos' => array_slice($photos, 0, 12),
            'first' => $photos[0] ?? null,
            'last' => count($photos) > 1 ? $photos[count($photos) - 1] : null,
            'goalsTouched' => array_values(array_filter(array_unique($goalsTouched))),
            'summary' => $summary,
            'titles' => array_values(array_filter(array_map(static fn($e) => (string) ($e['title'] ?? ''), $entries))),
        ];
    }

    /**
     * The most recent finished month with entries, offered on Today during
     * the first ten days of a new month.
     */
    public function offerForToday(int $userId): ?array {
        if ((int) current_time('j') > 10) {
            return null;
        }
        $prev = date('Y-m', strtotime('first day of last month', strtotime(current_time('Y-m-d'))));
        $recap = $this->forMonth($userId, $prev);
        return ($recap && ($recap['entries'] >= 2 || count($recap['photos']) >= 1)) ? $recap : null;
    }

    /**
     * Share the recap to Community as one post with the month's last photo.
     *
     * @return int|\WP_Error New community post id.
     */
    public function share(int $userId, string $month, string $caption = '') {
        global $wpdb;
        $recap = $this->forMonth($userId, $month);
        if (!$recap) {
            return new \WP_Error('empty_month', __('There is nothing logged for that month yet.', 'myavana-hair-journey-next'));
        }
        $table = $wpdb->prefix . 'myavana_community_posts';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) {
            return new \WP_Error('community_unavailable', __('Community is not available right now.', 'myavana-hair-journey-next'));
        }

        $image = $recap['last']['url'] ?? ($recap['first']['url'] ?? '');
        $content = trim($caption) !== '' ? $caption : $recap['summary'];
        $ok = $wpdb->insert($table, [
            'user_id' => $userId,
            'title' => sprintf(__('My %s in hair', 'myavana-hair-journey-next'), $recap['label']),
            'content' => sanitize_textarea_field($content),
            'image_url' => esc_url_raw($image),
            'post_type' => 'progress',
            'privacy_level' => 'public',
            'created_at' => current_time('mysql'),
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s']);
        if (!$ok) {
            return new \WP_Error('db_error', __('We could not share your recap. Please try again.', 'myavana-hair-journey-next'));
        }
        return (int) $wpdb->insert_id;
    }
}
