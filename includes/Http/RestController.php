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
     * Get current user ID with fallback
     *
     * @return int
     */
    protected function getUserId(): int {
        return Permissions::getCurrentUserId();
    }
}
