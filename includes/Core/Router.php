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
