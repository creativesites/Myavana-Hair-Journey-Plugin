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
use Myavana\Next\Http\Routes\ClientLogRoutes;
use Myavana\Next\Http\Routes\JournalRoutes;
use Myavana\Next\Http\Routes\RoutineRoutes;
use Myavana\Next\Http\Routes\GoalRoutes;
use Myavana\Next\Http\Routes\CommunityRoutes;
use Myavana\Next\Http\Routes\ProfileRoutes;
use Myavana\Next\Http\Routes\AiRoutes;
use Myavana\Next\Http\Routes\AuthRoutes;
use Myavana\Next\Http\Routes\DownloadRoutes;
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
        $this->loadAdminPortal();

        // Post type registration
        add_action('init', [$this, 'registerPostTypes']);

        // REST API registration
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // The host's edge cache stores any 200 response to a request without
        // a WordPress login cookie, so token- or service-key-authenticated
        // responses would otherwise be served to other visitors.
        add_filter('rest_post_dispatch', [$this, 'preventRestResponseCaching'], 10, 3);

        // Email verification link handler (visited from the confirmation
        // email, not a REST call — needs a real browser redirect).
        add_action('template_redirect', [$this, 'handleVerifyEmailLink']);

        // Password reset link handler (visited from the reset email).
        add_action('template_redirect', [$this, 'handleResetPasswordLink']);

        // Router & Shortcode initialization
        Router::init();

        // Load app assets in the document head. Rendering the shortcode happens
        // after wp_head, which is too late to replace the theme header cleanly.
        add_action('wp_enqueue_scripts', [$this, 'enqueueAppAssets']);
        add_action('wp_head', [$this, 'printBootWatchdog'], 2);

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
    /**
     * The team's front-end admin portal (/admin-portal/, shortcode
     * [myavana_admin_portal]). Skipped if the original plugin is active and
     * already defines it.
     */
    private function loadAdminPortal(): void {
        if (class_exists('Myavana_Admin_Portal')) {
            return;
        }
        $dir = MYAVANA_NEXT_PATH . 'includes/Admin/admin-portal/';
        require_once $dir . 'class-myavana-admin-portal-permissions.php';
        require_once $dir . 'class-myavana-admin-portal-audit-log.php';
        if (!class_exists('Myavana_Analytics_Model')) {
            require_once $dir . 'class-myavana-analytics-model.php';
        }
        require_once $dir . 'class-myavana-admin-portal.php';
        new \Myavana_Admin_Portal();
    }

    private function loadCommunityFeatures(): void {
        require_once MYAVANA_NEXT_PATH . 'includes/Domain/Community/CommunityIntegration.php';
        require_once MYAVANA_NEXT_PATH . 'includes/Domain/Community/CommunityDatabase.php';

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

        // Shared routine tracking helpers (schedule matching, streaks,
        // completion records) — GoalRoutineHandlers.php's AJAX handlers
        // call directly into these, so this must load first.
        if (file_exists(MYAVANA_NEXT_PATH . 'includes/Domain/Goals/RoutineTracking.php')) {
            require_once MYAVANA_NEXT_PATH . 'includes/Domain/Goals/RoutineTracking.php';
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

    public function preventRestResponseCaching($response, $server, $request) {
        if ($response instanceof \WP_REST_Response && strpos($request->get_route(), '/myavana/v1/') === 0) {
            foreach (wp_get_nocache_headers() as $name => $value) {
                if ($value !== false) {
                    $response->header($name, $value);
                }
            }
        }
        return $response;
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void {
        (new InitRoutes())->registerRoutes();
        (new TodayRoutes())->registerRoutes();
        (new ClientLogRoutes())->registerRoutes();
        (new JournalRoutes())->registerRoutes();
        (new RoutineRoutes())->registerRoutes();
        (new GoalRoutes())->registerRoutes();
        (new CommunityRoutes())->registerRoutes();
        (new ProfileRoutes())->registerRoutes();
        (new AiRoutes())->registerRoutes();
        (new AuthRoutes())->registerRoutes();
        (new DownloadRoutes())->registerRoutes();
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
     * The key isn't validated (or consumed) here — that happens once, at
     * actual submit time via AuthService::resetPassword() — this just
     * carries login+key through to the app shell so auth.js can render the
     * "choose a new password" form.
     */
    public function handleResetPasswordLink(): void {
        if (empty($_GET['myavana_next_reset_password'])) {
            return;
        }

        $login = sanitize_text_field(wp_unslash($_GET['login'] ?? ''));
        $key = sanitize_text_field(wp_unslash($_GET['key'] ?? ''));

        $url = add_query_arg([
            'myavana_reset_login' => rawurlencode($login),
            'myavana_reset_key' => rawurlencode($key),
        ], $this->findAppPageUrl());

        wp_safe_redirect($url);
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

    /**
     * Inline (so no network fetch can block it) check that the app actually
     * started. If it hasn't within 15s, or Today is still on its skeleton,
     * it reports pending/slow scripts and early errors to the client log.
     */
    public function printBootWatchdog(): void {
        if (!is_user_logged_in() || !Router::isCurrentAppPage()) {
            return;
        }
        $endpoint = esc_url_raw(rest_url('myavana/v1/client-log'));
        $nonce = wp_create_nonce('wp_rest');
        ?>
<script id="myavana-boot-watchdog">
(function () {
    var errors = [];
    window.addEventListener('error', function (e) {
        var t = e.target;
        if (t && t !== window && (t.src || t.href)) {
            var url = String(t.src || t.href);
            if ((t.tagName === 'SCRIPT' || t.tagName === 'LINK') && url.indexOf('http://fonts.googleapis.com') !== 0) errors.push('load-failed ' + url.split('?')[0]);
        } else {
            errors.push('js ' + (e.message || '') + ' @' + String(e.filename || '').split('/').pop() + ':' + (e.lineno || ''));
        }
    }, true);
    window.addEventListener('unhandledrejection', function (e) {
        errors.push('rejection ' + String((e.reason && e.reason.message) || e.reason));
    });
    function shortName(url) {
        return String(url).split('?')[0].split('/').slice(-2).join('/');
    }
    function report(kind) {
        try {
            var durations = {};
            (performance.getEntriesByType('resource') || []).forEach(function (r) { durations[r.name] = Math.round(r.duration); });
            var pending = [], slow = [];
            [].forEach.call(document.scripts, function (s) {
                if (!s.src || s.src.indexOf(location.origin) !== 0) return;
                if (!(s.src in durations)) pending.push(shortName(s.src));
                else if (durations[s.src] > 4000) slow.push(shortName(s.src) + '=' + durations[s.src] + 'ms');
            });
            fetch(<?php echo wp_json_encode($endpoint); ?>, {
                method: 'POST',
                credentials: 'same-origin',
                keepalive: true,
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': <?php echo wp_json_encode($nonce); ?> },
                body: JSON.stringify({
                    endpoint: 'boot:' + kind,
                    status: 0,
                    attempt: 0,
                    online: navigator.onLine,
                    reason: JSON.stringify({ ready: document.readyState, boot: window.MyavanaNextBoot || 'app.js-not-run', appTag: !!document.getElementById('myavana-next-app-js'), footer: !!document.querySelector('.myavana-next-shell footer'), lastSection: (function () { var v = document.querySelectorAll('.myavana-next-view'); return v.length ? v[v.length - 1].id : null; })(), pending: pending.slice(0, 8), slow: slow.slice(0, 6), errors: errors.slice(0, 6) })
                })
            }).catch(function () {});
        } catch (e) {}
    }
    window.setTimeout(function () {
        var app = window.MyavanaNext && window.MyavanaNext.App;
        if (!app || !app.isBooted || !app.isBooted()) {
            report('stalled');
        } else if (document.querySelector('#view-today.active #today-checklist-items .myavana-today-skeleton')) {
            report('today-stuck');
        }
    }, 15000);
})();
</script>
        <?php
    }
}
