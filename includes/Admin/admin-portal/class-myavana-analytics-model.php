<?php
/**
 * Analytics Model for Myavana Admin Dashboard
 *
 * Handles heavy SQL queries, aggregation, and caching for the intelligence dashboard.
 *
 * @package Myavana_Hair_Journey
 * @since 2.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Myavana_Analytics_Model
{

    /**
     * Cache duration in seconds (1 hour for heavy analytics)
     */
    const CACHE_DURATION = 3600;

    /**
     * Get dashboard overview metrics
     * 
     * @param string $date_from
     * @param string $date_to
     * @param string $role
     * @return array
     */
    public static function get_overview_metrics($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_overview_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;

        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day'));

        $role_filter = self::build_role_filter_clause($role, 'u');
        $analytics_role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        // Core user metrics
        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        $analytics_table_exists = (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $analytics_table));

        // 1. Total Users
        $total_users_sql = "SELECT COUNT(*) FROM {$users_table} u WHERE 1=1" . $role_filter['sql'];
        $total_users = (int)$wpdb->get_var(self::prepare_sql($total_users_sql, $role_filter['params']));

        // 2. New Users in range
        $new_users_sql = "SELECT COUNT(*) FROM {$users_table} u
                          WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
        $new_users = (int)$wpdb->get_var(self::prepare_sql($new_users_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));

        // 3. Analysis Users (Total)
        $analysis_users_sql = "SELECT COUNT(DISTINCT u.ID)
                               FROM {$users_table} u
                               INNER JOIN {$wpdb->usermeta} um
                                       ON um.user_id = u.ID
                                      AND um.meta_key = 'myavana_hair_analysis_history'
                                      AND um.meta_value <> ''
                                      AND um.meta_value <> 'a:0:{}'
                               WHERE 1=1" . $role_filter['sql'];
        $analysis_users = (int)$wpdb->get_var(self::prepare_sql($analysis_users_sql, $role_filter['params']));

        // 4. AI Entries in range
        $ai_entries_sql = "SELECT COUNT(DISTINCT p.ID)
                           FROM {$posts_table} p
                           INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
                           INNER JOIN {$users_table} u ON u.ID = p.post_author
                           WHERE p.post_type = 'hair_journey_entry'
                             AND p.post_status = 'publish'
                             AND p.post_date >= %s
                             AND p.post_date < %s
                             AND pm.meta_key = 'entry_type'
                             AND pm.meta_value = 'ai_analysis'" . $role_filter['sql'];
        $ai_entries = (int)$wpdb->get_var(self::prepare_sql($ai_entries_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));

        // Analytics Table Metrics
        $total_visits = 0;
        $unique_visitors = 0;
        $bounce_rate = 0.0;
        $section_usage = ['home' => 0, 'journey' => 0, 'community' => 0, 'profile' => 0];

        if ($analytics_table_exists) {
            // Visits
            $total_visits_sql = "SELECT COUNT(*) FROM {$analytics_table} sa
                                 WHERE sa.visited_at >= %s
                                   AND sa.visited_at < %s" . $analytics_role_filter['sql'];
            $total_visits = (int)$wpdb->get_var(self::prepare_sql($total_visits_sql, array_merge([$date_from_sql, $date_to_sql], $analytics_role_filter['params'])));

            // Unique Visitors
            $unique_visitors_sql = "SELECT COUNT(DISTINCT sa.session_id) FROM {$analytics_table} sa
                                    WHERE sa.visited_at >= %s
                                      AND sa.visited_at < %s" . $analytics_role_filter['sql'];
            $unique_visitors = (int)$wpdb->get_var(self::prepare_sql($unique_visitors_sql, array_merge([$date_from_sql, $date_to_sql], $analytics_role_filter['params'])));

            // Bounce Rate
            $bounce_sql = "SELECT (SUM(CASE WHEN sa.time_spent_seconds <= 10 THEN 1 ELSE 0 END) / COUNT(*)) * 100
                           FROM {$analytics_table} sa
                           WHERE sa.visited_at >= %s
                             AND sa.visited_at < %s" . $analytics_role_filter['sql'];
            $bounce_rate = (float)$wpdb->get_var(self::prepare_sql($bounce_sql, array_merge([$date_from_sql, $date_to_sql], $analytics_role_filter['params'])));

            // Section Usage
            $section_sql = "SELECT
                                SUM(CASE WHEN sa.path LIKE '/hair-journey%' THEN 1 ELSE 0 END) AS journey,
                                SUM(CASE WHEN sa.path LIKE '/community%' THEN 1 ELSE 0 END) AS community,
                                SUM(CASE WHEN sa.path LIKE '/profile%' THEN 1 ELSE 0 END) AS profile,
                                SUM(CASE WHEN sa.path = '/' OR sa.path LIKE '/home%' THEN 1 ELSE 0 END) AS home
                            FROM {$analytics_table} sa
                            WHERE sa.visited_at >= %s
                              AND sa.visited_at < %s" . $analytics_role_filter['sql'];
            $section_row = (array)$wpdb->get_row(self::prepare_sql($section_sql, array_merge([$date_from_sql, $date_to_sql], $analytics_role_filter['params'])), ARRAY_A);
            $section_usage = [
                'home' => (int)($section_row['home'] ?? 0),
                'journey' => (int)($section_row['journey'] ?? 0),
                'community' => (int)($section_row['community'] ?? 0),
                'profile' => (int)($section_row['profile'] ?? 0),
            ];
        }

        $data = [
            'total_users' => $total_users,
            'new_users' => $new_users,
            'analysis_users' => $analysis_users,
            'ai_entries' => $ai_entries,
            'total_visits' => $total_visits,
            'unique_visitors' => $unique_visitors,
            'bounce_rate' => $bounce_rate,
            'section_usage' => $section_usage,
        ];

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get Funnel Data
     */
    public static function get_funnel_data($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_funnel_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;

        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day'));
        $role_filter = self::build_role_filter_clause($role, 'u');

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;
        $community_posts_table = $wpdb->prefix . 'myavana_community_posts';
        $community_posts_exists = (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $community_posts_table));

        $signup_sql = "SELECT COUNT(*) FROM {$users_table} u
                       WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
        $signup_count = (int)$wpdb->get_var(self::prepare_sql($signup_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));

        $onboarding_sql = "SELECT COUNT(DISTINCT u.ID)
                           FROM {$users_table} u
                           INNER JOIN {$wpdb->usermeta} om
                                ON om.user_id = u.ID
                               AND om.meta_key = 'myavana_onboarding_status'
                               AND om.meta_value = 'completed'
                           WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
        $onboarding_count = (int)$wpdb->get_var(self::prepare_sql($onboarding_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));

        $entry_sql = "SELECT COUNT(DISTINCT u.ID)
                      FROM {$users_table} u
                      INNER JOIN {$posts_table} p
                              ON p.post_author = u.ID
                             AND p.post_type = 'hair_journey_entry'
                             AND p.post_status = 'publish'
                      WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
        $entry_count = (int)$wpdb->get_var(self::prepare_sql($entry_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));

        $analysis_sql = "SELECT COUNT(DISTINCT u.ID)
                         FROM {$users_table} u
                         INNER JOIN {$wpdb->usermeta} am
                                 ON am.user_id = u.ID
                                AND am.meta_key = 'myavana_hair_analysis_history'
                                AND am.meta_value <> ''
                                AND am.meta_value <> 'a:0:{}'
                         WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
        $analysis_count = (int)$wpdb->get_var(self::prepare_sql($analysis_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));

        $community_count = 0;
        if ($community_posts_exists) {
            $community_sql = "SELECT COUNT(DISTINCT u.ID)
                              FROM {$users_table} u
                              INNER JOIN {$community_posts_table} cp ON cp.user_id = u.ID
                              WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
            $community_count = (int)$wpdb->get_var(self::prepare_sql($community_sql, array_merge([$date_from_sql, $date_to_sql], $role_filter['params'])));
        }

        $steps = [
            ['label' => 'Signup', 'count' => $signup_count],
            ['label' => 'Onboarding Completed', 'count' => $onboarding_count],
            ['label' => 'First Hair Entry', 'count' => $entry_count],
            ['label' => 'AI Analysis Used', 'count' => $analysis_count],
            ['label' => 'Community Shared', 'count' => $community_count],
        ];

        // Calculate drop-offs
        $previous = null;
        foreach ($steps as $index => $step) {
            $count = (int)$step['count'];
            $drop_off = 0.0;

            if ($previous !== null && $previous > 0) {
                $drop_off = round((($previous - $count) / $previous) * 100, 1);
                if ($drop_off < 0)
                    $drop_off = 0.0;
            }

            $conversion = $signup_count > 0 ? round(($count / $signup_count) * 100, 1) : 0.0;

            $steps[$index]['drop_off'] = $drop_off;
            $steps[$index]['conversion'] = $conversion;
            $previous = $count;
        }

        set_transient($cache_key, $steps, self::CACHE_DURATION);
        return $steps;
    }

    /**
     * Get Cohort Retention
     */
    public static function get_cohort_retention($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_cohort_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        $analytics_table_exists = (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $analytics_table));

        $role_filter = self::build_role_filter_clause($role, 'u');

        $start = new DateTimeImmutable($date_from);
        $start = $start->modify('monday this week');
        $end = new DateTimeImmutable($date_to);
        $end = $end->modify('monday next week');

        $cohorts = [];

        for ($cursor = $start; $cursor <= $end; $cursor = $cursor->modify('+1 week')) {
            $cohort_start = $cursor->format('Y-m-d 00:00:00');
            $cohort_end = $cursor->modify('+1 week')->format('Y-m-d 00:00:00');

            $cohort_sql = "SELECT u.ID
                           FROM {$users_table} u
                           WHERE u.user_registered >= %s AND u.user_registered < %s" . $role_filter['sql'];
            $cohort_user_ids = $wpdb->get_col(self::prepare_sql($cohort_sql, array_merge([$cohort_start, $cohort_end], $role_filter['params'])));

            $cohort_size = count($cohort_user_ids);
            $retention_week_1 = 0.0;
            $retention_week_4 = 0.0;

            if ($cohort_size > 0) {
                $id_list = implode(',', array_map('intval', $cohort_user_ids));

                $w1_start = $cursor->modify('+1 week')->format('Y-m-d 00:00:00');
                $w1_end = $cursor->modify('+2 week')->format('Y-m-d 00:00:00');
                $w4_start = $cursor->modify('+4 week')->format('Y-m-d 00:00:00');
                $w4_end = $cursor->modify('+5 week')->format('Y-m-d 00:00:00');

                if ($analytics_table_exists) {
                    $active_w1 = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT user_id) FROM {$analytics_table}
                         WHERE user_id IN ({$id_list}) AND visited_at >= %s AND visited_at < %s",
                        $w1_start, $w1_end
                    ));
                    $active_w4 = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT user_id) FROM {$analytics_table}
                         WHERE user_id IN ({$id_list}) AND visited_at >= %s AND visited_at < %s",
                        $w4_start, $w4_end
                    ));
                }
                else {
                    // Fallback to post activity
                    $active_w1 = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT post_author) FROM {$posts_table}
                         WHERE post_author IN ({$id_list}) AND post_type = 'hair_journey_entry'
                         AND post_status = 'publish' AND post_date >= %s AND post_date < %s",
                        $w1_start, $w1_end
                    ));
                    $active_w4 = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(DISTINCT post_author) FROM {$posts_table}
                         WHERE post_author IN ({$id_list}) AND post_type = 'hair_journey_entry'
                         AND post_status = 'publish' AND post_date >= %s AND post_date < %s",
                        $w4_start, $w4_end
                    ));
                }

                $retention_week_1 = round(($active_w1 / $cohort_size) * 100, 1);
                $retention_week_4 = round(($active_w4 / $cohort_size) * 100, 1);
            }

            $cohorts[] = [
                'label' => $cursor->format('M d'),
                'cohort_size' => $cohort_size,
                'week_1' => $retention_week_1,
                'week_4' => $retention_week_4,
            ];
        }

        $data = array_slice($cohorts, -12);
        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get Engagement Metrics
     */
    public static function get_engagement_metrics($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_engagement_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [
                'avg_time_site' => 0,
                'session_depth' => 0,
                'returning_session_rate' => 0
            ];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');
        $params = array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params']);

        // Avg Time
        $avg_time_sql = "SELECT AVG(sa.time_spent_seconds) FROM {$analytics_table} sa
                         WHERE sa.visited_at >= %s AND sa.visited_at < %s" . $role_filter['sql'];
        $avg_time_site = (float)$wpdb->get_var(self::prepare_sql($avg_time_sql, $params));

        // Session rollup
        $rollup_sql = "SELECT sa.session_id, COUNT(*) AS session_pages
                       FROM {$analytics_table} sa
                       WHERE sa.visited_at >= %s AND sa.visited_at < %s" . $role_filter['sql'] . "
                       GROUP BY sa.session_id";

        // We can't easily do subqueries with prepare logic if we want to be clean, 
        // but we can query the rollup stats directly if we restructure the query.
        // Or just run the complex query.

        $session_depth_sql = "SELECT AVG(session_pages) FROM ($rollup_sql) AS rollup";
        $session_depth = (float)$wpdb->get_var(self::prepare_sql($session_depth_sql, $params));

        $returning_sql = "SELECT (SUM(CASE WHEN session_pages > 1 THEN 1 ELSE 0 END) / COUNT(*)) * 100 
                          FROM ($rollup_sql) AS rollup";
        $returning_session_rate = (float)$wpdb->get_var(self::prepare_sql($returning_sql, $params));

        $data = [
            'avg_time_site' => $avg_time_site,
            'session_depth' => $session_depth,
            'returning_session_rate' => $returning_session_rate
        ];
        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get top pages by visits in selected range.
     */
    public static function get_top_pages($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_top_pages_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        $sql = "SELECT sa.path,
                       COUNT(*) AS visits,
                       AVG(sa.time_spent_seconds) AS avg_time,
                       MAX(COALESCE(sa.last_seen_at, sa.visited_at)) AS last_seen
                FROM {$analytics_table} sa
                WHERE sa.visited_at >= %s
                  AND sa.visited_at < %s" . $role_filter['sql'] . "
                GROUP BY sa.path
                ORDER BY visits DESC, avg_time DESC
                LIMIT 20";

        $data = $wpdb->get_results(self::prepare_sql($sql, array_merge([
            $date_from . ' 00:00:00',
            wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day')),
        ], $role_filter['params'])));

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get active users snapshot for selected range.
     */
    public static function get_active_users($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_active_users_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;

        $users_table = $wpdb->users;
        $posts_table = $wpdb->posts;
        $profiles_table = $wpdb->prefix . 'myavana_profiles';
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';

        $profiles_exists = (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $profiles_table));
        $analytics_exists = (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $analytics_table));

        $role_filter = self::build_role_filter_clause($role, 'u');
        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day'));

        $profile_select = $profiles_exists ? "COALESCE(pf.hair_type, '') AS hair_type" : "'' AS hair_type";
        $profile_join = $profiles_exists ? "LEFT JOIN {$profiles_table} pf ON pf.user_id = u.ID" : '';

        $entries_subquery = "SELECT p.post_author AS user_id, COUNT(*) AS entries_count
                             FROM {$posts_table} p
                             WHERE p.post_type = 'hair_journey_entry'
                               AND p.post_status = 'publish'
                               AND p.post_date >= %s
                               AND p.post_date < %s
                             GROUP BY p.post_author";

        $params = [$date_from_sql, $date_to_sql];
        $visit_join = '';
        $visit_select = '0 AS visits_in_range';

        if ($analytics_exists) {
            $visits_subquery = "SELECT sa.user_id AS user_id, COUNT(*) AS visits_in_range
                                FROM {$analytics_table} sa
                                WHERE sa.user_id IS NOT NULL
                                  AND sa.user_id > 0
                                  AND sa.visited_at >= %s
                                  AND sa.visited_at < %s
                                GROUP BY sa.user_id";

            $visit_join = "LEFT JOIN ({$visits_subquery}) visits_rollup ON visits_rollup.user_id = u.ID";
            $visit_select = "COALESCE(visits_rollup.visits_in_range, 0) AS visits_in_range";
            $params[] = $date_from_sql;
            $params[] = $date_to_sql;
        }

        $sql = "SELECT u.ID,
                       u.display_name,
                       u.user_email,
                       {$profile_select},
                       COALESCE(entries_rollup.entries_count, 0) AS entries_count,
                       {$visit_select}
                FROM {$users_table} u
                {$profile_join}
                LEFT JOIN ({$entries_subquery}) entries_rollup ON entries_rollup.user_id = u.ID
                {$visit_join}
                WHERE 1=1" . $role_filter['sql'] . "
                ORDER BY visits_in_range DESC, entries_count DESC, u.user_registered DESC
                LIMIT 50";

        $data = $wpdb->get_results(self::prepare_sql($sql, array_merge($params, $role_filter['params'])));

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get Active User Trends (DAU/WAU/MAU)
     */
    public static function get_active_user_trends($role = 'all')
    {
        $cache_key = 'myavana_analytics_trends_' . md5($role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [
                'dau' => 0, 'wau' => 0, 'mau' => 0, 'stickiness' => 0
            ];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        $dau_sql = "SELECT COUNT(DISTINCT sa.user_id) FROM {$analytics_table} sa
                    WHERE sa.user_id IS NOT NULL AND sa.user_id > 0
                    AND sa.visited_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)" . $role_filter['sql'];
        $dau = (int)$wpdb->get_var(self::prepare_sql($dau_sql, $role_filter['params']));

        $wau_sql = "SELECT COUNT(DISTINCT sa.user_id) FROM {$analytics_table} sa
                    WHERE sa.user_id IS NOT NULL AND sa.user_id > 0
                    AND sa.visited_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)" . $role_filter['sql'];
        $wau = (int)$wpdb->get_var(self::prepare_sql($wau_sql, $role_filter['params']));

        $mau_sql = "SELECT COUNT(DISTINCT sa.user_id) FROM {$analytics_table} sa
                    WHERE sa.user_id IS NOT NULL AND sa.user_id > 0
                    AND sa.visited_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)" . $role_filter['sql'];
        $mau = (int)$wpdb->get_var(self::prepare_sql($mau_sql, $role_filter['params']));

        $stickiness = ($mau > 0) ? round(($dau / $mau) * 100, 1) : 0.0;

        $data = [
            'dau' => $dau,
            'wau' => $wau,
            'mau' => $mau,
            'stickiness' => $stickiness
        ];
        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get daily visits trend.
     */
    public static function get_daily_visits($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_daily_visits_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        $sql = "SELECT DATE(sa.visited_at) AS visit_date, COUNT(*) AS visits
                FROM {$analytics_table} sa
                WHERE sa.visited_at >= %s
                  AND sa.visited_at < %s" . $role_filter['sql'] . "
                GROUP BY DATE(sa.visited_at)
                ORDER BY visit_date ASC";

        $data = $wpdb->get_results(self::prepare_sql($sql, array_merge([
            $date_from . ' 00:00:00',
            wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day')),
        ], $role_filter['params'])));

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get Device Mix
     */
    public static function get_device_mix($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_device_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        $sql = "SELECT COALESCE(NULLIF(sa.device_type, ''), 'unknown') AS device_type, COUNT(*) AS total
                FROM {$analytics_table} sa
                WHERE sa.visited_at >= %s
                  AND sa.visited_at < %s" . $role_filter['sql'] . "
                GROUP BY COALESCE(NULLIF(sa.device_type, ''), 'unknown')
                ORDER BY total DESC";

        return $wpdb->get_results(self::prepare_sql($sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params'])));
    }

    /**
     * Get Referrers
     */
    public static function get_referrers($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_referrers_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        $sql = "SELECT sa.referrer, COUNT(*) AS total
                FROM {$analytics_table} sa
                WHERE sa.visited_at >= %s
                  AND sa.visited_at < %s
                  AND sa.referrer IS NOT NULL
                  AND sa.referrer <> ''" . $role_filter['sql'] . "
                GROUP BY sa.referrer
                ORDER BY total DESC
                LIMIT 10";

        return $wpdb->get_results(self::prepare_sql($sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params'])));
    }

    /**
     * Get Page Engagement
     */
    public static function get_page_engagement($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_pages_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $analytics_table = $wpdb->prefix . 'myavana_site_analytics';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$analytics_table'"))
            return [];

        $role_filter = self::build_analytics_role_filter_clause($role, 'sa');

        $sql = "SELECT sa.path, COUNT(*) AS visits, AVG(sa.time_spent_seconds) AS avg_time
                FROM {$analytics_table} sa
                WHERE sa.visited_at >= %s
                  AND sa.visited_at < %s" . $role_filter['sql'] . "
                GROUP BY sa.path
                HAVING visits >= 2
                ORDER BY avg_time DESC
                LIMIT 12";

        return $wpdb->get_results(self::prepare_sql($sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params'])));
    }

    /**
     * Get Signup Trend
     */
    public static function get_signup_trend($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_signups_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $users_table = $wpdb->users;
        $role_filter = self::build_role_filter_clause($role, 'u');

        $sql = "SELECT DATE(u.user_registered) AS signup_date, COUNT(*) AS total
                FROM {$users_table} u
                WHERE u.user_registered >= %s
                  AND u.user_registered < %s" . $role_filter['sql'] . "
                GROUP BY DATE(u.user_registered)
                ORDER BY signup_date ASC";

        return $wpdb->get_results(self::prepare_sql($sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params'])));
    }

    /**
     * Get AI Entry Trend
     */
    public static function get_ai_entry_trend($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_ai_trend_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $posts_table = $wpdb->posts;
        $postmeta_table = $wpdb->postmeta;
        $users_table = $wpdb->users;
        $role_filter = self::build_role_filter_clause($role, 'u');

        $sql = "SELECT DATE(p.post_date) AS entry_date, COUNT(*) AS total
                FROM {$posts_table} p
                INNER JOIN {$postmeta_table} pm ON p.ID = pm.post_id
                INNER JOIN {$users_table} u ON u.ID = p.post_author
                WHERE p.post_type = 'hair_journey_entry'
                  AND p.post_status = 'publish'
                  AND p.post_date >= %s
                  AND p.post_date < %s
                  AND pm.meta_key = 'entry_type'
                  AND pm.meta_value = 'ai_analysis'" . $role_filter['sql'] . "
                GROUP BY DATE(p.post_date)
                ORDER BY entry_date ASC";

        return $wpdb->get_results(self::prepare_sql($sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params'])));
    }

    /**
     * Get community-level stats and top creators.
     */
    public static function get_community_stats($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_community_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;

        $posts_table = $wpdb->prefix . 'myavana_community_posts';
        $users_table = $wpdb->users;

        if (!$wpdb->get_var("SHOW TABLES LIKE '$posts_table'")) {
            return [
                'posts_count' => 0,
                'video_count' => 0,
                'interactions' => 0,
                'top_creators' => [],
            ];
        }

        $role_filter = self::build_role_filter_clause($role, 'u');
        $date_from_sql = $date_from . ' 00:00:00';
        $date_to_sql = wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day'));
        $base_params = array_merge([$date_from_sql, $date_to_sql], $role_filter['params']);

        $base_clause = " FROM {$posts_table} cp
                         INNER JOIN {$users_table} u ON u.ID = cp.user_id
                         WHERE cp.created_at >= %s
                           AND cp.created_at < %s" . $role_filter['sql'];

        $posts_count_sql = "SELECT COUNT(*)" . $base_clause;
        $posts_count = (int)$wpdb->get_var(self::prepare_sql($posts_count_sql, $base_params));

        $video_count_sql = "SELECT COUNT(*)" . $base_clause . "
                            AND (cp.media_type = 'video' OR (cp.video_url IS NOT NULL AND cp.video_url <> ''))";
        $video_count = (int)$wpdb->get_var(self::prepare_sql($video_count_sql, $base_params));

        $interactions_sql = "SELECT COALESCE(SUM(COALESCE(cp.likes_count, 0) + COALESCE(cp.comments_count, 0) + COALESCE(cp.shares_count, 0)), 0)" . $base_clause;
        $interactions = (int)$wpdb->get_var(self::prepare_sql($interactions_sql, $base_params));

        $top_creators_sql = "SELECT u.ID,
                                    u.display_name,
                                    COUNT(cp.id) AS posts_count,
                                    COALESCE(SUM(COALESCE(cp.likes_count, 0) + COALESCE(cp.comments_count, 0) + COALESCE(cp.shares_count, 0)), 0) AS engagement
                             " . $base_clause . "
                             GROUP BY u.ID, u.display_name
                             ORDER BY engagement DESC, posts_count DESC
                             LIMIT 10";
        $top_creators = $wpdb->get_results(self::prepare_sql($top_creators_sql, $base_params));

        $data = [
            'posts_count' => $posts_count,
            'video_count' => $video_count,
            'interactions' => $interactions,
            'top_creators' => $top_creators,
        ];

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get Hair Type Distribution
     */
    public static function get_hair_type_distribution($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_analytics_hair_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false)
            return $cached;

        global $wpdb;
        $profiles_table = $wpdb->prefix . 'myavana_profiles';
        $users_table = $wpdb->users;
        $role_filter = self::build_role_filter_clause($role, 'u');

        $sql = "SELECT pf.hair_type, COUNT(*) AS total
                FROM {$profiles_table} pf
                INNER JOIN {$users_table} u ON u.ID = pf.user_id
                WHERE pf.hair_type IS NOT NULL
                  AND pf.hair_type <> ''
                  AND u.user_registered >= %s
                  AND u.user_registered < %s" . $role_filter['sql'] . "
                GROUP BY pf.hair_type
                ORDER BY total DESC
                LIMIT 15";

        return $wpdb->get_results(self::prepare_sql($sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_filter['params'])));
    }

    /**
     * Get Role Distribution
     */
    public static function get_role_distribution($date_from, $date_to, $available_roles)
    {
        global $wpdb;
        $users_table = $wpdb->users;
        $role_distribution = [];

        foreach ($available_roles as $role_key => $role_meta) {
            $role_only_filter = self::build_role_filter_clause($role_key, 'u');
            $role_count_sql = "SELECT COUNT(*) FROM {$users_table} u
                               WHERE u.user_registered >= %s
                                 AND u.user_registered < %s" . $role_only_filter['sql'];
            $role_count = (int)$wpdb->get_var(self::prepare_sql($role_count_sql, array_merge([$date_from . ' 00:00:00', wp_date('Y-m-d', strtotime($date_to . ' +1 day'))], $role_only_filter['params'])));

            if ($role_count > 0) {
                $role_distribution[] = [
                    'key' => $role_key,
                    'label' => translate_user_role($role_meta['name']),
                    'count' => $role_count,
                ];
            }
        }
        return $role_distribution;
    }

    /**
     * Get behavioral intelligence from the site events stream.
     */
    public static function get_behavior_metrics($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_behavior_metrics_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $events_table = $wpdb->prefix . 'myavana_site_events';
        if (!$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table))) {
            return [
                'total_events' => 0,
                'tracked_sessions' => 0,
                'tracked_actions' => 0,
                'avg_section_dwell' => 0,
                'top_sections' => [],
                'top_actions' => [],
            ];
        }

        $role_filter = self::build_analytics_role_filter_clause($role, 'se');
        $params = array_merge([
            $date_from . ' 00:00:00',
            wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day')),
        ], $role_filter['params']);

        $range_sql = " FROM {$events_table} se
                       WHERE se.created_at >= %s
                         AND se.created_at < %s" . $role_filter['sql'];

        $total_events = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(*)" . $range_sql, $params));
        $tracked_sessions = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(DISTINCT se.session_id)" . $range_sql, $params));
        $tracked_actions = (int) $wpdb->get_var(self::prepare_sql(
            "SELECT COUNT(*)" . $range_sql . " AND se.event_type IN ('click', 'form_submit', 'ai_component')",
            $params
        ));
        $avg_section_dwell = (float) $wpdb->get_var(self::prepare_sql(
            "SELECT AVG(se.dwell_seconds)" . $range_sql . " AND se.event_type = 'section_dwell' AND se.dwell_seconds > 0",
            $params
        ));

        $top_sections = $wpdb->get_results(self::prepare_sql(
            "SELECT COALESCE(NULLIF(se.section_key, ''), se.page_context) AS section_key,
                    COUNT(*) AS views,
                    SUM(se.dwell_seconds) AS total_dwell
             {$range_sql}
               AND se.event_type IN ('section_view', 'section_dwell')
             GROUP BY COALESCE(NULLIF(se.section_key, ''), se.page_context)
             ORDER BY total_dwell DESC, views DESC
             LIMIT 8",
            $params
        ), ARRAY_A);

        $top_actions = $wpdb->get_results(self::prepare_sql(
            "SELECT se.event_name, COUNT(*) AS total
             {$range_sql}
               AND se.event_type IN ('click', 'form_submit', 'ai_component')
             GROUP BY se.event_name
             ORDER BY total DESC
             LIMIT 8",
            $params
        ), ARRAY_A);

        $data = [
            'total_events' => $total_events,
            'tracked_sessions' => $tracked_sessions,
            'tracked_actions' => $tracked_actions,
            'avg_section_dwell' => $avg_section_dwell,
            'top_sections' => is_array($top_sections) ? $top_sections : [],
            'top_actions' => is_array($top_actions) ? $top_actions : [],
        ];

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get AI component generation and interaction metrics.
     */
    public static function get_ai_component_metrics($date_from, $date_to, $role = 'all')
    {
        $cache_key = 'myavana_ai_component_metrics_' . md5($date_from . $date_to . $role);
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return $cached;
        }

        global $wpdb;
        $components_table = $wpdb->prefix . 'myavana_ai_components';
        $events_table = $wpdb->prefix . 'myavana_site_events';

        $components_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $components_table));
        $events_exists = (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $events_table));

        if (!$components_exists) {
            return [
                'generated' => 0,
                'active' => 0,
                'saved' => 0,
                'dismissed' => 0,
                'applied' => 0,
                'impressions' => 0,
                'top_pages' => [],
                'top_types' => [],
                'recent_components' => [],
            ];
        }

        $role_filter = self::build_analytics_role_filter_clause($role, 'ac');
        $params = array_merge([
            $date_from . ' 00:00:00',
            wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day')),
        ], $role_filter['params']);

        $range_sql = " FROM {$components_table} ac
                       WHERE ac.generated_at >= %s
                         AND ac.generated_at < %s" . $role_filter['sql'];

        $generated = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(*)" . $range_sql, $params));
        $active = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(*)" . $range_sql . " AND ac.status = 'active'", $params));
        $saved = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(*)" . $range_sql . " AND ac.status = 'saved'", $params));
        $dismissed = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(*)" . $range_sql . " AND ac.status = 'dismissed'", $params));
        $applied = (int) $wpdb->get_var(self::prepare_sql("SELECT COUNT(*)" . $range_sql . " AND ac.status = 'applied'", $params));

        $impressions = 0;
        if ($events_exists) {
            $event_role_filter = self::build_analytics_role_filter_clause($role, 'se');
            $event_params = array_merge([
                $date_from . ' 00:00:00',
                wp_date('Y-m-d 00:00:00', strtotime($date_to . ' +1 day')),
            ], $event_role_filter['params']);

            $impressions = (int) $wpdb->get_var(self::prepare_sql(
                "SELECT COUNT(*)
                 FROM {$events_table} se
                 WHERE se.created_at >= %s
                   AND se.created_at < %s" . $event_role_filter['sql'] . "
                   AND se.event_type = 'ai_component'
                   AND se.event_name = 'impression'",
                $event_params
            ));
        }

        $top_pages = $wpdb->get_results(self::prepare_sql(
            "SELECT ac.page_context, COUNT(*) AS total
             {$range_sql}
             GROUP BY ac.page_context
             ORDER BY total DESC
             LIMIT 6",
            $params
        ), ARRAY_A);

        $top_types = $wpdb->get_results(self::prepare_sql(
            "SELECT ac.component_type, COUNT(*) AS total
             {$range_sql}
             GROUP BY ac.component_type
             ORDER BY total DESC
             LIMIT 6",
            $params
        ), ARRAY_A);

        $recent_components = $wpdb->get_results(self::prepare_sql(
            "SELECT ac.title, ac.component_type, ac.page_context, ac.status, ac.generated_at
             {$range_sql}
             ORDER BY ac.generated_at DESC, ac.id DESC
             LIMIT 10",
            $params
        ), ARRAY_A);

        $data = [
            'generated' => $generated,
            'active' => $active,
            'saved' => $saved,
            'dismissed' => $dismissed,
            'applied' => $applied,
            'impressions' => $impressions,
            'top_pages' => is_array($top_pages) ? $top_pages : [],
            'top_types' => is_array($top_types) ? $top_types : [],
            'recent_components' => is_array($recent_components) ? $recent_components : [],
        ];

        set_transient($cache_key, $data, self::CACHE_DURATION);
        return $data;
    }

    /**
     * Get Moderation Reports
     */
    public static function get_moderation_reports()
    {
        global $wpdb;
        $reports_table = $wpdb->prefix . 'myavana_ci_content_reports';
        if (!$wpdb->get_var("SHOW TABLES LIKE '$reports_table'"))
            return ['pending' => 0, 'recent' => []];

        $pending = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$reports_table} WHERE status = 'pending'");
        $recent = $wpdb->get_results(
            "SELECT id, content_type, content_id, reason, status, created_at
             FROM {$reports_table}
             ORDER BY created_at DESC
             LIMIT 10"
        );
        return ['pending' => $pending, 'recent' => $recent];
    }

    /**
     * Helpers
     */

    private static function prepare_sql($sql, $params = [])
    {
        global $wpdb;
        if (empty($params))
            return $sql;
        $prepared_args = array_merge([$sql], $params);
        return call_user_func_array([$wpdb, 'prepare'], $prepared_args);
    }

    private static function build_role_filter_clause($selected_role, $user_alias = 'u')
    {
        global $wpdb;

        if ($selected_role === 'all' || $selected_role === '') {
            return ['sql' => '', 'params' => []];
        }

        $cap_meta_key = $wpdb->prefix . 'capabilities';
        $role_like = '%' . $wpdb->esc_like('"' . $selected_role . '"') . '%';

        return [
            'sql' => " AND EXISTS (
                SELECT 1 FROM {$wpdb->usermeta} role_um
                WHERE role_um.user_id = {$user_alias}.ID
                  AND role_um.meta_key = %s
                  AND role_um.meta_value LIKE %s
            )",
            'params' => [$cap_meta_key, $role_like],
        ];
    }

    private static function build_analytics_role_filter_clause($selected_role, $analytics_alias = 'sa')
    {
        global $wpdb;

        if ($selected_role === 'all' || $selected_role === '') {
            return ['sql' => '', 'params' => []];
        }

        $cap_meta_key = $wpdb->prefix . 'capabilities';
        $role_like = '%' . $wpdb->esc_like('"' . $selected_role . '"') . '%';

        return [
            'sql' => " AND {$analytics_alias}.user_id IN (
                SELECT role_um.user_id
                FROM {$wpdb->usermeta} role_um
                WHERE role_um.meta_key = %s
                AND role_um.meta_value LIKE %s
            )",
            'params' => [$cap_meta_key, $role_like],
        ];
    }
}
