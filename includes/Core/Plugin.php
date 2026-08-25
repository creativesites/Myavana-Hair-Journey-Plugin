<?php
/**
 * Main Plugin Coordinator Singleton
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

use Myavana\Next\Admin\SettingsPage;
use Myavana\Next\Http\Routes\InitRoutes;
use Myavana\Next\Http\Routes\TodayRoutes;
use Myavana\Next\Http\Routes\JournalRoutes;
use Myavana\Next\Http\Routes\RoutineRoutes;
use Myavana\Next\Http\Routes\GoalRoutes;
use Myavana\Next\Http\Routes\CommunityRoutes;
use Myavana\Next\Http\Routes\ProfileRoutes;
use Myavana\Next\Http\Routes\AiRoutes;
use Myavana\Next\Http\Routes\AuthRoutes;
use Myavana\Next\Domain\Auth\AuthService;

if (!defined('ABSPATH')) {
    exit;
}

class Plugin {
    /**
     * Singleton instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Get singleton instance
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize plugin hooks
     */
    public function init(): void {
        // Load legacy community shortcode & AJAX handlers exactly as is
        $this->loadCommunityFeatures();

        // Post type registration
        add_action('init', [$this, 'registerPostTypes']);

        // REST API registration
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Email verification link handler (visited from the confirmation
        // email, not a REST call — needs a real browser redirect).
        add_action('template_redirect', [$this, 'handleVerifyEmailLink']);

        // Password reset link handler (visited from the reset email) —
        // forwards uid/token onto the app page so the JS can open the
        // "set a new password" panel; the token itself is only consumed by
        // the REST /auth/reset-password call once the user submits it.
        add_action('template_redirect', [$this, 'handleResetPasswordLink']);

        // Router & Shortcode initialization
        Router::init();

        // Load app assets in the document head. Rendering the shortcode happens
        // after wp_head, which is too late to replace the theme header cleanly.
        add_action('wp_enqueue_scripts', [$this, 'enqueueAppAssets']);

        // Give the Next app the same full-page treatment as the original
        // MYAVANA experience, without affecting any other site page.
        add_filter('body_class', [$this, 'addNextAppBodyClass']);

        // Admin settings
        if (is_admin()) {
            SettingsPage::init();
        }
    }

    /**
     * Load ported community social features and shortcodes
     */
    private function loadCommunityFeatures(): void {
        if (file_exists(MYAVANA_NEXT_PATH . 'includes/Domain/Community/SocialFeatures.php')) {
            require_once MYAVANA_NEXT_PATH . 'includes/Domain/Community/SocialFeatures.php';
            if (class_exists('Myavana_Social_Features')) {
                new \Myavana_Social_Features();
            }
        }

        if (file_exists(MYAVANA_NEXT_PATH . 'includes/Domain/Community/CommunitySharingHandlers.php')) {
            require_once MYAVANA_NEXT_PATH . 'includes/Domain/Community/CommunitySharingHandlers.php';
        }

        if (file_exists(MYAVANA_NEXT_PATH . 'includes/Domain/Community/CommunityCiHandlers.php')) {
            require_once MYAVANA_NEXT_PATH . 'includes/Domain/Community/CommunityCiHandlers.php';
        }

        if (file_exists(MYAVANA_NEXT_PATH . 'templates/pages/community-feed.php')) {
            require_once MYAVANA_NEXT_PATH . 'templates/pages/community-feed.php';
            if (function_exists('myavana_community_feed_shortcode') && !shortcode_exists('myavana_community_feed')) {
                add_shortcode('myavana_community_feed', 'myavana_community_feed_shortcode');
            }
        }

        // Load Luxury Home Page & Shortcode
        if (file_exists(MYAVANA_NEXT_PATH . 'templates/pages/home/luxury-home.php')) {
            require_once MYAVANA_NEXT_PATH . 'templates/pages/home/luxury-home.php';
            if (function_exists('myavana_luxury_home_shortcode') && !shortcode_exists('myavana_luxury_home')) {
                add_shortcode('myavana_luxury_home', 'myavana_luxury_home_shortcode');
            }
        }

        // Load Goals & Routines Handlers & Shortcodes
        if (file_exists(MYAVANA_NEXT_PATH . 'includes/Domain/Goals/GoalRoutineHandlers.php')) {
            require_once MYAVANA_NEXT_PATH . 'includes/Domain/Goals/GoalRoutineHandlers.php';
        }

        if (file_exists(MYAVANA_NEXT_PATH . 'templates/pages/routines.php')) {
            require_once MYAVANA_NEXT_PATH . 'templates/pages/routines.php';
            if (function_exists('myavana_routines_page_shortcode') && !shortcode_exists('myavana_routines_page')) {
                add_shortcode('myavana_routines_page', 'myavana_routines_page_shortcode');
            }
        }

        if (file_exists(MYAVANA_NEXT_PATH . 'templates/pages/goals.php')) {
            require_once MYAVANA_NEXT_PATH . 'templates/pages/goals.php';
            if (function_exists('myavana_goals_page_shortcode') && !shortcode_exists('myavana_goals_page')) {
                add_shortcode('myavana_goals_page', 'myavana_goals_page_shortcode');
            }
        }

    }

    /**
     * Register post type if not already declared by legacy plugin
     */
    public function registerPostTypes(): void {
        if (!post_type_exists('hair_journey_entry')) {
            register_post_type('hair_journey_entry', [
                'labels' => [
                    'name' => __('Hair Journey Entries', 'myavana-hair-journey-next'),
                    'singular_name' => __('Hair Journey Entry', 'myavana-hair-journey-next'),
                ],
                'public' => false,
                'show_ui' => true,
                'supports' => ['title', 'editor', 'author', 'thumbnail', 'custom-fields'],
                'show_in_rest' => true,
                'capability_type' => 'post',
                'map_meta_cap' => true,
            ]);
        }
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void {
        (new InitRoutes())->registerRoutes();
        (new TodayRoutes())->registerRoutes();
        (new JournalRoutes())->registerRoutes();
        (new RoutineRoutes())->registerRoutes();
        (new GoalRoutes())->registerRoutes();
        (new CommunityRoutes())->registerRoutes();
        (new ProfileRoutes())->registerRoutes();
        (new AiRoutes())->registerRoutes();
        (new AuthRoutes())->registerRoutes();
    }

    /**
     * Handle a click on the "Confirm My Email" link from the verification
     * email. Redirects back into the app shell with a status flag the
     * frontend can show a toast for.
     */
    public function handleVerifyEmailLink(): void {
        if (empty($_GET['myavana_next_verify_email'])) {
            return;
        }

        $userId = absint($_GET['uid'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));

        $verified = (new AuthService())->verifyEmailToken($userId, $token);

        wp_safe_redirect(add_query_arg('myavana_email_verified', $verified ? '1' : '0', $this->findAppPageUrl()));
        exit;
    }

    /**
     * Handle a click on the "Reset My Password" link from the reset email.
     * The email link points at home_url() so it works regardless of where
     * the app shell page lives; this just forwards uid/token onto that page.
     */
    public function handleResetPasswordLink(): void {
        if (empty($_GET['myavana_next_reset_password'])) {
            return;
        }

        $userId = absint($_GET['uid'] ?? 0);
        $token = sanitize_text_field(wp_unslash($_GET['token'] ?? ''));
        $appPageUrl = $this->findAppPageUrl();

        // Already on the app page (or no dedicated page was found, so the
        // "app page" IS the home URL) — redirecting to the same path with
        // the same trigger query arg would loop forever. Nothing to do;
        // the frontend reads uid/token straight off the current URL.
        if (wp_parse_url($appPageUrl, PHP_URL_PATH) === wp_parse_url(home_url($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH)) {
            return;
        }

        wp_safe_redirect(add_query_arg([
            'myavana_next_reset_password' => '1',
            'uid' => $userId,
            'token' => $token,
        ], $appPageUrl));
        exit;
    }

    /**
     * URL of the page hosting the [myavana_journey_next] shortcode, so
     * redirects (e.g. after email verification) land back in the app
     * instead of the bare site homepage.
     */
    private function findAppPageUrl(): string {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            's' => '[' . Router::SHORTCODE_TAG,
            'posts_per_page' => 1,
            'fields' => 'ids',
        ]);

        if (!empty($pages)) {
            $url = get_permalink($pages[0]);
            if ($url) {
                return $url;
            }
        }

        return home_url('/');
    }

    /**
     * Activation logic
     */
    public function activate(): void {
        $this->registerPostTypes();
        flush_rewrite_rules();
    }

    /**
     * Deactivation logic
     */
    public function deactivate(): void {
        flush_rewrite_rules();
    }

    /**
     * Mark the page hosting our app before the theme prints its header.
     */
    public function addNextAppBodyClass(array $classes): array {
        if (Router::isCurrentAppPage()) {
            $classes[] = 'myavana-next-app-page';
        }

        return $classes;
    }

    public function enqueueAppAssets(): void {
        if (Router::isCurrentAppPage()) {
            Assets::enqueue();
        }
    }
}
