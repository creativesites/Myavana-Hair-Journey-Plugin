<?php
/**
 * Client failure reports — writes failed API reads seen in members'
 * browsers to the PHP error log so device-only failures can be diagnosed.
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;

if (!defined('ABSPATH')) {
    exit;
}

class ClientLogRoutes extends RestController {
    private const MAX_REPORTS_PER_HOUR = 30;

    public function registerRoutes(): void {
        register_rest_route(self::NAMESPACE, '/client-log', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'logClientFailure'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function logClientFailure(\WP_REST_Request $request): \WP_REST_Response {
        $userId = get_current_user_id();
        $who = $userId > 0 ? 'user:' . $userId : 'ip:' . md5((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $key = 'myavana_clientlog_' . md5($who);
        $count = (int) get_transient($key);
        if ($count >= self::MAX_REPORTS_PER_HOUR) {
            return $this->respondSuccess(['logged' => false]);
        }
        set_transient($key, $count + 1, HOUR_IN_SECONDS);

        $clean = function ($value, int $max) {
            return substr(preg_replace('/[^\x20-\x7E]/', '?', sanitize_text_field((string) $value)), 0, $max);
        };

        error_log(sprintf(
            '[MYAVANA client] %s endpoint=%s status=%d attempt=%d online=%s reason=%s ua=%s',
            $who,
            $clean($request->get_param('endpoint'), 80),
            (int) $request->get_param('status'),
            (int) $request->get_param('attempt'),
            $request->get_param('online') === false ? 'no' : 'yes',
            $clean($request->get_param('reason'), 300),
            $clean($_SERVER['HTTP_USER_AGENT'] ?? '', 160)
        ));

        return $this->respondSuccess(['logged' => true]);
    }
}
