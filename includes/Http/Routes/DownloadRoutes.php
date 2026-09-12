<?php
/**
 * APK Download & Telemetry Routes
 *
 * @package Myavana\Next\Http\Routes
 */

namespace Myavana\Next\Http\Routes;

use Myavana\Next\Http\RestController;

if (!defined('ABSPATH')) {
    exit;
}

class DownloadRoutes extends RestController {
    /**
     * Option key for total download count
     */
    public const OPTION_COUNT = 'myavana_apk_download_count';

    /**
     * Option key for download telemetry logs
     */
    public const OPTION_LOG = 'myavana_apk_download_log';

    /**
     * Register REST API routes
     */
    public function registerRoutes(): void {
        // Direct download endpoint (increments counter and redirects 302 to file)
        register_rest_route(self::NAMESPACE, '/download/apk', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'handleApkDownload'],
            'permission_callback' => '__return_true',
        ]);

        // Public stats endpoint for live counter on frontend
        register_rest_route(self::NAMESPACE, '/download/stats', [
            'methods' => \WP_REST_Server::READABLE,
            'callback' => [$this, 'getDownloadStats'],
            'permission_callback' => '__return_true',
        ]);

        // Client-side beacon tracking endpoint
        register_rest_route(self::NAMESPACE, '/download/track', [
            'methods' => \WP_REST_Server::CREATABLE,
            'callback' => [$this, 'trackDownloadBeacon'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Get target public APK file URL
     */
    public static function getApkUrl(): string {
        $uploads = wp_upload_dir();
        return trailingslashit($uploads['baseurl']) . 'apk/myavana-mya-preview-build28.apk';
    }

    /**
     * Increment download count and record telemetry log
     */
    public static function recordDownload(string $source = 'direct'): int {
        $current = (int) get_option(self::OPTION_COUNT, 0);
        $newCount = $current + 1;
        update_option(self::OPTION_COUNT, $newCount, true);

        // Sanitize client info
        $ip = sanitize_text_field($_SERVER['REMOTE_ADDR'] ?? '');
        $ua = sanitize_text_field(substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255));
        $ref = sanitize_text_field($_SERVER['HTTP_REFERER'] ?? '');

        $logEntry = [
            'time' => current_time('mysql'),
            'ip' => $ip,
            'ua' => $ua,
            'ref' => $ref,
            'source' => sanitize_key($source),
        ];

        $logs = get_option(self::OPTION_LOG, []);
        if (!is_array($logs)) {
            $logs = [];
        }

        // Prepend and keep latest 200 entries
        array_unshift($logs, $logEntry);
        if (count($logs) > 200) {
            $logs = array_slice($logs, 0, 200);
        }

        update_option(self::OPTION_LOG, $logs, false);

        return $newCount;
    }

    /**
     * Handle GET /myavana/v1/download/apk
     */
    public function handleApkDownload(\WP_REST_Request $request) {
        $source = sanitize_key($request->get_param('src') ?: 'direct');
        $count = self::recordDownload($source);
        $apkUrl = self::getApkUrl();

        // If JSON response requested
        if ($request->get_param('format') === 'json' || $request->get_param('json') === '1') {
            return $this->respondSuccess([
                'url' => $apkUrl,
                'count' => $count,
                'message' => 'Download recorded successfully.',
            ]);
        }

        // Send no-cache headers and redirect directly to APK download
        nocache_headers();
        header('Location: ' . $apkUrl, true, 302);
        exit;
    }

    /**
     * Handle GET /myavana/v1/download/stats
     */
    public function getDownloadStats(\WP_REST_Request $request): \WP_REST_Response {
        nocache_headers();
        $count = (int) get_option(self::OPTION_COUNT, 0);
        $logs = get_option(self::OPTION_LOG, []);
        $lastDownload = !empty($logs) && is_array($logs) ? ($logs[0]['time'] ?? null) : null;

        $uploads = wp_upload_dir();
        $apkPath = trailingslashit($uploads['basedir']) . 'apk/myavana-mya-preview-build26.apk';
        $fileSize = file_exists($apkPath) ? size_format(filesize($apkPath)) : '293 MB';
        $apkUrl = self::getApkUrl();

        return $this->respondSuccess([
            'count' => $count,
            'last_download' => $lastDownload,
            'version' => '1.2.0-preview',
            'build' => 26,
            'file_size' => $fileSize,
            'download_url' => home_url('/wp-json/' . self::NAMESPACE . '/download/apk'),
            'direct_file_url' => $apkUrl,
            'file_name' => 'myavana-mya-preview-build26.apk',
            'release_date' => '2026-09-12',
            'min_android' => 'Android 8.0+ (API 26)',
        ]);
    }

    /**
     * Handle POST /myavana/v1/download/track
     */
    public function trackDownloadBeacon(\WP_REST_Request $request): \WP_REST_Response {
        $source = sanitize_key($request->get_param('source') ?: 'beacon');
        $count = self::recordDownload($source);

        return $this->respondSuccess([
            'count' => $count,
            'recorded' => true,
        ]);
    }
}
