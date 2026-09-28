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
    public const OPTION = 'myavana_client_load_reports';
    private const MAX_REPORTS_PER_HOUR = 30;
    private const KEEP_REPORTS = 100;

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

        $user = $userId > 0 ? get_userdata($userId) : null;
        $report = [
            'time' => current_time('mysql'),
            'who' => $user ? $user->user_login . ' (#' . $userId . ')' : $who,
            'endpoint' => $clean($request->get_param('endpoint'), 80),
            'status' => (int) $request->get_param('status'),
            'attempt' => (int) $request->get_param('attempt'),
            'online' => $request->get_param('online') === false ? 'no' : 'yes',
            'reason' => $clean($request->get_param('reason'), 1000),
            'ua' => $clean($_SERVER['HTTP_USER_AGENT'] ?? '', 160),
        ];

        error_log(sprintf(
            '[MYAVANA client] %s endpoint=%s status=%d attempt=%d online=%s reason=%s ua=%s',
            $report['who'],
            $report['endpoint'],
            $report['status'],
            $report['attempt'],
            $report['online'],
            $report['reason'],
            $report['ua']
        ));

        // Also kept in the database so admins can read them in Settings →
        // MYAVANA Next without needing access to the server's error log.
        $recent = get_option(self::OPTION, []);
        $recent = is_array($recent) ? $recent : [];
        array_unshift($recent, $report);
        update_option(self::OPTION, array_slice($recent, 0, self::KEEP_REPORTS), false);

        return $this->respondSuccess(['logged' => true]);
    }
}
