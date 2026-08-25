<?php
/**
 * Permissions & Security Helpers
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Permissions {
    /**
     * Nonce action key
     */
    public const NONCE_ACTION = 'wp_rest';

    /**
     * Check if user is authenticated
     *
     * @return bool
     */
    public static function isAuthenticated(): bool {
        return is_user_logged_in() && get_current_user_id() > 0;
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    public static function getCurrentUserId(): int {
        return get_current_user_id();
    }

    /**
     * Check if user can access record
     *
     * @param int $ownerId User ID who owns the record.
     * @return bool
     */
    public static function canAccessUserRecord(int $ownerId): bool {
        $currentId = self::getCurrentUserId();
        if ($currentId <= 0) {
            return false;
        }

        if ($currentId === $ownerId) {
            return true;
        }

        return current_user_can('manage_options');
    }

    /**
     * Permission callback for REST API (authenticated user)
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function restUserCheck(\WP_REST_Request $request) {
        if (!self::isAuthenticated()) {
            return new \WP_Error(
                'rest_forbidden',
                __('You must be logged in to access this resource.', 'myavana-hair-journey-next'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Permission callback for admin REST routes
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function restAdminCheck(\WP_REST_Request $request) {
        if (!current_user_can('manage_options')) {
            return new \WP_Error(
                'rest_forbidden',
                __('Administrator privileges required.', 'myavana-hair-journey-next'),
                ['status' => 403]
            );
        }

        return true;
    }
}
