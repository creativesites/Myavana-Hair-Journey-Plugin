<?php
/**
 * Feature Flags & Rollout Manager
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class FeatureFlags {
    /**
     * Option key
     */
    private const OPTION_KEY = 'myavana_next_feature_flags';

    /**
     * Default flag values
     */
    private const DEFAULTS = [
        'today_hub' => true,
        'journey_workspace' => true,
        'routine_workspace' => true,
        'community_feed' => true,
        'profile_privacy' => true,
        'smart_entry' => true,
        'ai_concierge' => true,
        'compare_slider' => true,
        'beta_cohort_only' => false,
    ];

    /**
     * Check if a feature is enabled
     *
     * @param string $flagName
     * @param int|null $userId
     * @return bool
     */
    public static function isEnabled(string $flagName, ?int $userId = null): bool {
        $flags = self::getAll();
        $enabled = $flags[$flagName] ?? (self::DEFAULTS[$flagName] ?? false);

        if (!$enabled) {
            return false;
        }

        // Cohort check if restricted
        if (!empty($flags['beta_cohort_only'])) {
            $currentUserId = $userId ?: get_current_user_id();
            if (!$currentUserId) {
                return false;
            }
            // Admins always have access, or users with beta flag
            if (current_user_can('manage_options')) {
                return true;
            }
            return (bool) get_user_meta($currentUserId, 'myavana_next_beta_tester', true);
        }

        return true;
    }

    /**
     * Get all flags
     *
     * @return array
     */
    public static function getAll(): array {
        $stored = get_option(self::OPTION_KEY, []);
        return wp_parse_args($stored, self::DEFAULTS);
    }

    /**
     * Update flag value
     *
     * @param string $flagName
     * @param bool $value
     * @return bool
     */
    public static function setFlag(string $flagName, bool $value): bool {
        $flags = self::getAll();
        $flags[$flagName] = $value;
        return update_option(self::OPTION_KEY, $flags);
    }
}
