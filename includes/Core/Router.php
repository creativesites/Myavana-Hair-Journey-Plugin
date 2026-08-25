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
            && (has_shortcode((string) $post->post_content, self::SHORTCODE_TAG) || has_shortcode((string) $post->post_content, 'myavana_luxury_home'));
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
