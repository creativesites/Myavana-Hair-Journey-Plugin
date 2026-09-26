<?php
/**
 * Base REST Controller
 *
 * @package Myavana\Next\Http
 */

namespace Myavana\Next\Http;

use Myavana\Next\Core\Permissions;

if (!defined('ABSPATH')) {
    exit;
}

abstract class RestController {
    /**
     * REST namespace
     */
    protected const NAMESPACE = 'myavana/v1';

    /**
     * Register REST routes
     */
    abstract public function registerRoutes(): void;

    /**
     * Helper for standardized success responses
     *
     * @param mixed $data
     * @param int $status
     * @return \WP_REST_Response
     */
    protected function respondSuccess($data = null, int $status = 200): \WP_REST_Response {
        return new \WP_REST_Response([
            'success' => true,
            'data' => $data,
        ], $status);
    }

    /**
     * Helper for standardized error responses
     *
     * @param string $message
     * @param string $code
     * @param int $status
     * @return \WP_REST_Response
     */
    protected function respondError(string $message, string $code = 'error', int $status = 400): \WP_REST_Response {
        return new \WP_REST_Response([
            'success' => false,
            'code' => $code,
            'message' => $message,
        ], $status);
    }

    /**
     * Get current user ID with fallback and impersonation support for authorized service calls
     *
     * @return int
     */
    protected function getUserId(): int {
        // If an authorized administrator or internal service credentials provide X-On-Behalf-Of header
        if ((Permissions::hasValidServiceKey() || current_user_can('manage_options')) && !empty($_SERVER['HTTP_X_ON_BEHALF_OF'])) {
            $val = trim((string) $_SERVER['HTTP_X_ON_BEHALF_OF']);
            if (is_numeric($val) && (int) $val > 0) {
                return (int) $val;
            }
            $user = get_user_by('login', $val) ?: get_user_by('email', $val) ?: get_user_by('slug', $val);
            if ($user) {
                return (int) $user->ID;
            }
        }
        return Permissions::getCurrentUserId();
    }
}
