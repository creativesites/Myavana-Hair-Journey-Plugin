<?php
/**
 * Shortcode & Route Handler
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Router {
    /**
     * Main shortcode tag
     */
    public const SHORTCODE_TAG = 'myavana_journey_next';

    /**
     * Initialize router
     */
    public static function init(): void {
        add_shortcode(self::SHORTCODE_TAG, [__CLASS__, 'renderShortcode']);

        // Legacy shortcode aliases from previous plugin versions (HJ-014)
        add_shortcode('myavana_hair-journey-page', [__CLASS__, 'renderLegacyJourneyPage']);
        add_shortcode('myavana_hair_journey_page', [__CLASS__, 'renderLegacyJourneyPage']);
        add_shortcode('myavana_hair_journey', [__CLASS__, 'renderLegacyJourneyPage']);
        add_shortcode('myavana_apk_download', [__CLASS__, 'renderApkDownloadShortcode']);

        // Intercept /download/, /download-apk/, /app/ routes for APK Download Page
        add_action('template_redirect', [__CLASS__, 'handleDownloadRedirect'], 5);

        // Intercept /onboarding/ route so it doesn't 404 (HJ-015)
        add_action('template_redirect', [__CLASS__, 'handleOnboardingRedirect']);

        // Intercept legacy routes and redirect to main app
        add_action('template_redirect', [__CLASS__, 'handleLegacyRouteRedirects']);
    }

    /**
     * Whether the current singular page hosts the Next app or Luxury Home shortcode.
     */
    public static function isCurrentAppPage(): bool {
        global $post;
        if (is_front_page() || is_home()) {
            return true;
        }
        return $post instanceof \WP_Post
            && (has_shortcode((string) $post->post_content, self::SHORTCODE_TAG)
                || has_shortcode((string) $post->post_content, 'myavana_hair-journey-page')
                || has_shortcode((string) $post->post_content, 'myavana_hair_journey_page')
                || has_shortcode((string) $post->post_content, 'myavana_hair_journey')
                || has_shortcode((string) $post->post_content, 'myavana_luxury_home'));
    }

    /**
     * Legacy shortcode handler for previous plugin compatibility
     */
    public static function renderLegacyJourneyPage($atts = []): string {
        $atts = is_array($atts) ? $atts : [];
        if (!isset($atts['tab'])) {
            $atts['tab'] = 'journey';
        }
        return self::renderShortcode($atts);
    }

    /**
     * Intercept /download/, /download-apk/, and /app/ routes to serve APK download page
     */
    public static function handleDownloadRedirect(): void {
        $uri = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
        $downloadRoutes = ['download', 'download-app', 'get-app', 'preview', 'app', 'apk'];
        if (in_array($uri, $downloadRoutes, true)) {
            self::renderApkDownloadPage();
            exit;
        }
    }

    /**
     * Render the APK download page template
     */
    public static function renderApkDownloadPage(): void {
        $templatePath = MYAVANA_NEXT_PATH . 'templates/pages/apk-download.php';
        if (file_exists($templatePath)) {
            status_header(200);
            header("Cache-Control: no-cache, no-store, must-revalidate, max-age=0");
            header("Pragma: no-cache");
            header("Expires: 0");
            header("Surrogate-Control: no-store");
            header("CDN-Cache-Control: no-store");
            header("Cloudflare-CDN-Cache-Control: no-store");
            include $templatePath;
        } else {
            status_header(404);
            echo 'Download page template missing.';
        }
    }

    /**
     * Shortcode handler for [myavana_apk_download]
     */
    public static function renderApkDownloadShortcode($atts = []): string {
        ob_start();
        self::renderApkDownloadPage();
        return ob_get_clean();
    }

    /**
     * Handle /onboarding/ redirect cleanly to SPA onboarding
     */
    public static function handleOnboardingRedirect(): void {
        $uri = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
        if ($uri === 'onboarding') {
            if (is_user_logged_in()) {
                wp_safe_redirect(home_url('/#today'));
            } else {
                wp_safe_redirect(home_url('/#auth'));
            }
            exit;
        }
    }

    /**
     * Handle legacy route redirects (hair-profile, diary) to main app
     */
    public static function handleLegacyRouteRedirects(): void {
        $uri = isset($_SERVER['REQUEST_URI']) ? trim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/') : '';
        $redirects = [
            'hair-profile' => '/#profile',
            'diary' => '/#journey',
        ];

        foreach ($redirects as $path => $hash) {
            if ($uri === $path) {
                wp_safe_redirect(home_url($hash));
                exit;
            }
        }
    }

    /**
     * Render the shortcode
     *
     * @param array|string $atts
     * @return string
     */
    public static function renderShortcode($atts = []): string {
        // Enqueue all scoped assets
        Assets::enqueue();

        $defaultTab = isset($atts['tab']) ? sanitize_key($atts['tab']) : 'today';
        $allowedTabs = ['home', 'today', 'journey', 'routine', 'community', 'profile'];
        if (!in_array($defaultTab, $allowedTabs, true)) {
            $defaultTab = 'today';
        }

        // Protect authenticated-only routes: today, journey, routine, community, profile
        $protectedTabs = ['today', 'journey', 'routine', 'community', 'profile'];
        if (in_array($defaultTab, $protectedTabs, true) && !Permissions::isAuthenticated()) {
            wp_safe_redirect(home_url('/#auth'));
            exit;
        }

        ob_start();
        $isLoggedIn = Permissions::isAuthenticated();
        $currentUserId = Permissions::getCurrentUserId();

        $templatePath = MYAVANA_NEXT_PATH . 'templates/app-shell.php';
        if (file_exists($templatePath)) {
            include $templatePath;
        } else {
            echo '<div class="myavana-next error"><p>App shell template missing.</p></div>';
        }

        return ob_get_clean();
    }
}
