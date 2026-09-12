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
        return (is_user_logged_in() && get_current_user_id() > 0) || self::getBearerUserId() > 0;
    }

    /**
     * Get current user ID
     *
     * @return int
     */
    public static function getCurrentUserId(): int {
        $id = get_current_user_id();
        if ($id > 0) {
            return $id;
        }
        return self::getBearerUserId();
    }

    /**
     * Extract authenticated user ID from signed token
     *
     * @return int
     */
    public static function getBearerUserId(): int {
        $token = '';
        if (!empty($_SERVER['HTTP_X_MYAVANA_TOKEN'])) {
            $token = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_MYAVANA_TOKEN']));
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $token = trim($matches[1]);
        }
        if (empty($token) || strpos($token, '.') === false) {
            return 0;
        }

        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return 0;
        }
        list($b64, $sig) = $parts;

        $secret = defined('MYAVANA_CHAT_SECRET') ? MYAVANA_CHAT_SECRET : wp_salt('auth');
        $json = base64_decode($b64);
        if (!$json) {
            return 0;
        }

        $expectedSig = hash_hmac('sha256', $json, $secret);
        if (!hash_equals($expectedSig, $sig)) {
            return 0;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            return 0;
        }

        if (!empty($payload['exp']) && $payload['exp'] < time()) {
            return 0;
        }

        return (int) ($payload['userId'] ?? 0);
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
     * Check if request carries a valid internal service key
     *
     * @return bool
     */
    public static function hasValidServiceKey(): bool {
        $expectedKey = defined('MYAVANA_SERVICE_KEY') && !empty(MYAVANA_SERVICE_KEY)
            ? MYAVANA_SERVICE_KEY
            : 'myavana_secret_service_key_2026';
        $providedKey = '';
        if (!empty($_SERVER['HTTP_X_MYAVANA_SERVICE_KEY'])) {
            $providedKey = sanitize_text_field(wp_unslash($_SERVER['HTTP_X_MYAVANA_SERVICE_KEY']));
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(.*)$/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
            $providedKey = trim($matches[1]);
        }
        return !empty($providedKey) && hash_equals($expectedKey, $providedKey);
    }

    /**
     * Permission callback for REST API (authenticated user or authorized service)
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    public static function restUserCheck(\WP_REST_Request $request) {
        if (self::hasValidServiceKey()) {
            return true;
        }

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
