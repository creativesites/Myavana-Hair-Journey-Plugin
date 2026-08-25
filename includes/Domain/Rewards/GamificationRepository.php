<?php
/**
 * Gamification Repository - Persistence for check-ins, streaks, points, and badges
 *
 * @package Myavana\Next\Domain\Rewards
 */

namespace Myavana\Next\Domain\Rewards;

if (!defined('ABSPATH')) {
    exit;
}

class GamificationRepository {
    /**
     * Get gamification stats for user
     *
     * @param int $userId
     * @return array
     */
    public function getStats(int $userId): array {
        global $wpdb;

        $stats = [
            'userId' => $userId,
            'currentStreak' => 0,
            'longestStreak' => 0,
            'totalPoints' => 0,
            'totalCheckins' => 0,
            'lastCheckinDate' => null,
            'level' => 1,
            'levelTitle' => 'Hair Care Explorer',
            'nextLevelPoints' => 100,
        ];

        // Check wp_myavana_gamification_stats table
        $table = $wpdb->prefix . 'myavana_gamification_stats';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d LIMIT 1", $userId));
            if ($row) {
                $stats['currentStreak'] = (int) ($row->current_streak ?? 0);
                $stats['longestStreak'] = (int) ($row->longest_streak ?? 0);
                $stats['totalPoints'] = (int) ($row->total_points ?? 0);
                $stats['totalCheckins'] = (int) ($row->total_checkins ?? 0);
                $stats['lastCheckinDate'] = $row->last_checkin_date ?? null;
            }
        }

        // Usermeta fallback/sync
        if ($stats['totalPoints'] === 0) {
            $metaPoints = (int) get_user_meta($userId, 'myavana_total_points', true);
            if ($metaPoints > 0) {
                $stats['totalPoints'] = $metaPoints;
                $stats['currentStreak'] = (int) get_user_meta($userId, 'myavana_current_streak', true);
                $stats['longestStreak'] = (int) get_user_meta($userId, 'myavana_longest_streak', true);
                $stats['totalCheckins'] = (int) get_user_meta($userId, 'myavana_total_checkins', true);
                $stats['lastCheckinDate'] = get_user_meta($userId, 'myavana_last_checkin_date', true);
            }
        }

        // Compute level from points
        $pts = $stats['totalPoints'];
        if ($pts >= 1000) {
            $stats['level'] = 5;
            $stats['levelTitle'] = 'Crown Icon';
            $stats['nextLevelPoints'] = 2000;
        } elseif ($pts >= 500) {
            $stats['level'] = 4;
            $stats['levelTitle'] = 'Hydration Virtuoso';
            $stats['nextLevelPoints'] = 1000;
        } elseif ($pts >= 250) {
            $stats['level'] = 3;
            $stats['levelTitle'] = 'Texture Specialist';
            $stats['nextLevelPoints'] = 500;
        } elseif ($pts >= 100) {
            $stats['level'] = 2;
            $stats['levelTitle'] = 'Routine Devotee';
            $stats['nextLevelPoints'] = 250;
        } else {
            $stats['level'] = 1;
            $stats['levelTitle'] = 'Hair Care Explorer';
            $stats['nextLevelPoints'] = 100;
        }

        return $stats;
    }

    /**
     * Record a check-in and update points/streaks
     *
     * @param int $userId
     * @param int $pointsAwarded
     * @param string $actionType
     * @return array Updated stats
     */
    public function recordCheckin(int $userId, int $pointsAwarded = 10, string $actionType = 'quick_checkin'): array {
        global $wpdb;
        $today = current_time('Y-m-d');
        $stats = $this->getStats($userId);

        $lastDate = $stats['lastCheckinDate'];
        $streak = $stats['currentStreak'];

        if ($lastDate === $today) {
            // Already checked in today, just add points if any
        } elseif ($lastDate === date('Y-m-d', strtotime('-1 day', strtotime($today)))) {
            // Consecutive day: increment streak
            $streak++;
        } else {
            // Broken streak: restart
            $streak = 1;
        }

        $longest = max($stats['longestStreak'], $streak);
        $totalPoints = $stats['totalPoints'] + $pointsAwarded;
        $totalCheckins = $stats['totalCheckins'] + 1;

        // Save to usermeta
        update_user_meta($userId, 'myavana_total_points', $totalPoints);
        update_user_meta($userId, 'myavana_current_streak', $streak);
        update_user_meta($userId, 'myavana_longest_streak', $longest);
        update_user_meta($userId, 'myavana_total_checkins', $totalCheckins);
        update_user_meta($userId, 'myavana_last_checkin_date', $today);

        // Update database table if exists
        $table = $wpdb->prefix . 'myavana_gamification_stats';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $userId));
            $row = [
                'user_id' => $userId,
                'current_streak' => $streak,
                'longest_streak' => $longest,
                'total_points' => $totalPoints,
                'total_checkins' => $totalCheckins,
                'last_checkin_date' => $today,
                'updated_at' => current_time('mysql'),
            ];

            if ($existing) {
                $wpdb->update($table, $row, ['user_id' => $userId]);
            } else {
                $row['created_at'] = current_time('mysql');
                $wpdb->insert($table, $row);
            }
        }

        return $this->getStats($userId);
    }

    /**
     * Get user badges
     *
     * @param int $userId
     * @return array
     */
    public function getBadges(int $userId): array {
        $stats = $this->getStats($userId);

        $badgeDefs = [
            [
                'id' => 'badge_first_step',
                'name' => 'First Step',
                'description' => 'Logged your first hair journey entry',
                'icon' => '🌱',
                'unlocked' => $stats['totalCheckins'] >= 1,
            ],
            [
                'id' => 'badge_3_day_streak',
                'name' => 'Habit Builder',
                'description' => 'Maintained a 3-day consistency streak',
                'icon' => '🔥',
                'unlocked' => $stats['longestStreak'] >= 3,
            ],
            [
                'id' => 'badge_7_day_streak',
                'name' => 'Consistency Queen',
                'description' => 'Maintained a 7-day consistency streak',
                'icon' => '⚡',
                'unlocked' => $stats['longestStreak'] >= 7,
            ],
            [
                'id' => 'badge_wash_day_master',
                'name' => 'Wash Day Pro',
                'description' => 'Completed 3 guided wash day regimens',
                'icon' => '✨',
                'unlocked' => $stats['totalPoints'] >= 150,
            ],
            [
                'id' => 'badge_century_club',
                'name' => 'Century Club',
                'description' => 'Earned over 100 hair care health points',
                'icon' => '👑',
                'unlocked' => $stats['totalPoints'] >= 100,
            ],
            [
                'id' => 'badge_twin_connector',
                'name' => 'Twin Connector',
                'description' => 'Engaged with hair twins in the community',
                'icon' => '🤝',
                'unlocked' => true,
            ],
        ];

        return $badgeDefs;
    }
}
