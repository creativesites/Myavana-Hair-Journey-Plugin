<?php
/**
 * Renders app-shell sections in isolation. A PHP error in one section (e.g.
 * from one member's unusual data) drops only that section, instead of
 * truncating the page before the footer scripts and leaving the whole app
 * unable to start. Errors are recorded with the client load reports.
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

use Myavana\Next\Http\Routes\ClientLogRoutes;

if (!defined('ABSPATH')) {
    exit;
}

class SafeRender {
    private static $currentSection = null;
    private static $shutdownRegistered = false;

    /**
     * @param string|null $viewId When the section is an app view, a placeholder
     *                            view with this id is rendered on failure so
     *                            its tab shows a message instead of a blank page.
     */
    public static function section(string $name, callable $render, ?string $viewId = null): void {
        self::registerShutdownLogger();

        $level = ob_get_level();
        ob_start();
        self::$currentSection = $name;
        try {
            $render();
            $html = ob_get_clean();
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            self::log($name, get_class($e) . ': ' . $e->getMessage(), $e->getFile(), $e->getLine());
            $html = '<!-- myavana: section "' . esc_attr($name) . '" could not be rendered -->';
            if ($viewId !== null) {
                $html .= '<section class="myavana-next-view" id="' . esc_attr($viewId) . '" style="display:none;">'
                    . '<div class="myavana-calm-empty"><p>'
                    . esc_html__('This section couldn\'t load right now. Your data is safe, and we\'ve been notified.', 'myavana-hair-journey-next')
                    . '</p></div></section>';
            }
        }
        self::$currentSection = null;

        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Fatal errors (memory, timeouts) can't be caught; record which section
     * was rendering when the request died.
     */
    private static function registerShutdownLogger(): void {
        if (self::$shutdownRegistered) {
            return;
        }
        self::$shutdownRegistered = true;
        register_shutdown_function(function () {
            $error = error_get_last();
            if (self::$currentSection === null || !$error || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return;
            }
            self::log(self::$currentSection, 'Fatal: ' . $error['message'], $error['file'], $error['line']);
        });
    }

    private static function log(string $section, string $message, string $file, int $line): void {
        $userId = get_current_user_id();
        $user = $userId > 0 ? get_userdata($userId) : null;
        $where = str_replace(wp_normalize_path(ABSPATH), '', wp_normalize_path($file)) . ':' . $line;
        $report = [
            'time' => current_time('mysql'),
            'who' => $user ? $user->user_login . ' (#' . $userId . ')' : 'guest',
            'endpoint' => 'server:' . $section,
            'status' => 500,
            'attempt' => 0,
            'online' => 'yes',
            'reason' => substr($message, 0, 800) . ' @ ' . $where,
            'ua' => substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160),
        ];

        error_log('[MYAVANA server] ' . $report['who'] . ' section=' . $section . ' ' . $report['reason']);

        $recent = get_option(ClientLogRoutes::OPTION, []);
        $recent = is_array($recent) ? $recent : [];
        array_unshift($recent, $report);
        update_option(ClientLogRoutes::OPTION, array_slice($recent, 0, 100), false);
    }
}
