<?php
/**
 * Assets Enqueueing & Localization Manager
 *
 * @package Myavana\Next\Core
 */

namespace Myavana\Next\Core;

if (!defined('ABSPATH')) {
    exit;
}

class Assets {
    /** Prevent repeated localization when the Next shell is enqueued both early and by its shortcode. */
    private static bool $goalRoutineSettingsLocalized = false;

    /**
     * Cache-busting version for a plugin-local asset, based on the file's
     * own mtime rather than the static MYAVANA_NEXT_VERSION constant — so a
     * saved edit is picked up on the next request instead of needing a
     * manual version bump (and fighting the browser's cache in the
     * meantime).
     */
    private static function ver(string $relativePath): string {
        $path = MYAVANA_NEXT_PATH . $relativePath;
        return file_exists($path) ? (string) filemtime($path) : MYAVANA_NEXT_VERSION;
    }

    /**
     * Enqueue frontend assets for MYAVANA Next
     */
    public static function enqueue(): void {
        // Enqueue Google Fonts
        wp_enqueue_style(
            'myavana-next-fonts',
            'https://fonts.googleapis.com/css2?family=Archivo:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400&family=Archivo+Expanded:wght@600;700;800;900&display=swap',
            [],
            null
        );

        // Core Scoped Styles
        wp_enqueue_style(
            'myavana-next-tokens',
            MYAVANA_NEXT_URL . 'assets/css/tokens.css',
            [],
            self::ver('assets/css/tokens.css')
        );

        wp_enqueue_style(
            'myavana-next-base',
            MYAVANA_NEXT_URL . 'assets/css/base.css',
            ['myavana-next-tokens'],
            self::ver('assets/css/base.css')
        );

        wp_enqueue_style(
            'myavana-next-layout',
            MYAVANA_NEXT_URL . 'assets/css/layout.css',
            ['myavana-next-base'],
            self::ver('assets/css/layout.css')
        );

        wp_enqueue_style(
            'myavana-next-components',
            MYAVANA_NEXT_URL . 'assets/css/components.css',
            ['myavana-next-layout'],
            self::ver('assets/css/components.css')
        );

        wp_enqueue_style(
            'myavana-next-animations',
            MYAVANA_NEXT_URL . 'assets/css/animations.css',
            ['myavana-next-components'],
            self::ver('assets/css/animations.css')
        );

        // View Specific Styles
        $views = ['today', 'journey', 'routine', 'community', 'profile', 'auth'];
        foreach ($views as $view) {
            wp_enqueue_style(
                "myavana-next-view-{$view}",
                MYAVANA_NEXT_URL . "assets/css/views/{$view}.css",
                ['myavana-next-components'],
                self::ver("assets/css/views/{$view}.css")
            );
        }

        // Enqueue Community Social Feed Assets
        wp_enqueue_style(
            'myavana-social-feed-css',
            MYAVANA_NEXT_URL . 'assets/css/social-feed.css',
            ['myavana-next-components'],
            self::ver('assets/css/social-feed.css')
        );

        wp_enqueue_script(
            'myavana-social-feed-js',
            MYAVANA_NEXT_URL . 'assets/js/social-feed.js',
            ['jquery'],
            self::ver('assets/js/social-feed.js'),
            true
        );

        wp_enqueue_script(
            'myavana-share-to-community-js',
            MYAVANA_NEXT_URL . 'assets/js/share-to-community.js',
            ['jquery', 'myavana-social-feed-js'],
            self::ver('assets/js/share-to-community.js'),
            true
        );
        wp_enqueue_style('myavana-entry-selector-css', MYAVANA_NEXT_URL . 'assets/css/entry-selector.css', ['myavana-social-feed-css'], self::ver('assets/css/entry-selector.css'));
        wp_enqueue_script('myavana-entry-selector-js', MYAVANA_NEXT_URL . 'assets/js/entry-selector.js', ['jquery', 'myavana-social-feed-js'], self::ver('assets/js/entry-selector.js'), true);

        // Complete ported Routine and Goals experiences. These assets are
        // deliberately loaded in the head so no legacy capability is lost
        // when rendered inside the Next app shell.
        wp_enqueue_style('myavana-goal-routine-pages', MYAVANA_NEXT_URL . 'assets/css/goal-routine-pages.css', ['myavana-next-components'], self::ver('assets/css/goal-routine-pages.css'));
        wp_enqueue_style('myavana-routines-page-redesign', MYAVANA_NEXT_URL . 'assets/css/routines-page-redesign.css', ['myavana-goal-routine-pages'], self::ver('assets/css/routines-page-redesign.css'));
        wp_enqueue_style('myavana-routine-composer', MYAVANA_NEXT_URL . 'assets/css/routine-composer.css', ['myavana-routines-page-redesign'], self::ver('assets/css/routine-composer.css'));
        wp_enqueue_style('myavana-goals-page-redesign', MYAVANA_NEXT_URL . 'assets/css/goals-page-redesign.css', ['myavana-goal-routine-pages'], self::ver('assets/css/goals-page-redesign.css'));
        wp_enqueue_style('myavana-goal-composer', MYAVANA_NEXT_URL . 'assets/css/goal-composer.css', ['myavana-goals-page-redesign'], self::ver('assets/css/goal-composer.css'));
        wp_enqueue_script('myavana-lucide', 'https://unpkg.com/lucide@0.469.0/dist/umd/lucide.min.js', [], '0.469.0', true);
        wp_enqueue_script('myavana-routines-page-redesign', MYAVANA_NEXT_URL . 'assets/js/routines-page-redesign.js', ['jquery', 'myavana-lucide'], self::ver('assets/js/routines-page-redesign.js'), true);
        wp_enqueue_script('myavana-goals-page-redesign', MYAVANA_NEXT_URL . 'assets/js/goals-page-redesign.js', ['jquery', 'myavana-lucide'], self::ver('assets/js/goals-page-redesign.js'), true);
        wp_enqueue_script('myavana-premium-goal-form', MYAVANA_NEXT_URL . 'assets/js/premium-goal-form.js', ['jquery'], self::ver('assets/js/premium-goal-form.js'), true);

        // The ported goals and routines screens still use WordPress AJAX. Keep every
        // action-specific nonce available in the Next shell so CRUD never silently
        // falls back to an invalid generic token.
        if (!self::$goalRoutineSettingsLocalized) {
            wp_localize_script('myavana-routines-page-redesign', 'myavanaTimelineSettings', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'ajaxurl' => admin_url('admin-ajax.php'),
                'getGoalDetailsNonce' => wp_create_nonce('myavana_get_goal_details'),
                'getRoutineDetailsNonce' => wp_create_nonce('myavana_get_routine_details'),
                'updateGoalNonce' => wp_create_nonce('myavana_update_goal'),
                'addGoalNonce' => wp_create_nonce('myavana_add_goal'),
                'deleteGoalNonce' => wp_create_nonce('myavana_delete_goal'),
                'updateRoutineNonce' => wp_create_nonce('myavana_update_routine'),
                'addRoutineNonce' => wp_create_nonce('myavana_add_routine'),
                'deleteRoutineNonce' => wp_create_nonce('myavana_delete_routine'),
                'toggleRoutineNonce' => wp_create_nonce('myavana_toggle_routine_completion'),
                'nonce' => wp_create_nonce('myavana_nonce'),
            ]);
            self::$goalRoutineSettingsLocalized = true;
        }

        // Scripts: Modules
        $modules = [
            'store' => 'assets/js/store.js',
            'api' => 'assets/js/api.js',
            'mod-kommunicate' => 'assets/js/modules/kommunicate.js',
            'mod-smart-entry' => 'assets/js/modules/smart-entry.js',
            'mod-compare-slider' => 'assets/js/modules/compare-slider.js',
            'mod-today' => 'assets/js/modules/today.js',
            'mod-timeline' => 'assets/js/modules/timeline.js',
            'mod-journey' => 'assets/js/modules/journey.js',
            'mod-routine' => 'assets/js/modules/routine.js',
            'mod-community' => 'assets/js/modules/community.js',
            'mod-profile' => 'assets/js/modules/profile.js',
            'mod-auth' => 'assets/js/modules/auth.js',
            'app' => 'assets/js/app.js',
        ];

        // Register and enqueue scripts in sequence
        $prevHandle = ['jquery', 'myavana-social-feed-js'];
        foreach ($modules as $handleKey => $relPath) {
            $handle = "myavana-next-{$handleKey}";
            wp_enqueue_script(
                $handle,
                MYAVANA_NEXT_URL . $relPath,
                $prevHandle,
                self::ver($relPath),
                true
            );
            $prevHandle = [$handle];
        }

        // Localize App Data on the main app script
        $currentUserId = get_current_user_id();
        $userData = null;
        if ($currentUserId > 0) {
            $user = get_userdata($currentUserId);
            $avatarUrl = get_avatar_url($currentUserId, ['size' => 96]);
            $userData = [
                'id' => $currentUserId,
                'name' => $user ? ($user->display_name ?: $user->user_login) : '',
                'email' => $user ? $user->user_email : '',
                'avatar' => $avatarUrl,
                // Only Next-plugin signups ever get this meta explicitly set
                // to 'no' pending verification; legacy/admin-created accounts
                // have no meta at all and should never see the banner, so
                // "verified" is the default and 'no' is the sole opt-in.
                'emailVerified' => get_user_meta($currentUserId, 'myavana_email_verified', true) !== 'no',
            ];
        }

        $googleAuth = new \Myavana\Next\Domain\Auth\GoogleAuthService();

        wp_localize_script('myavana-next-app', 'myavanaNextData', [
            'restUrl' => esc_url_raw(rest_url('myavana/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'isLoggedIn' => is_user_logged_in(),
            'currentUser' => $userData,
            'flags' => FeatureFlags::getAll(),
            'pluginUrl' => MYAVANA_NEXT_URL,
            'loginUrl' => wp_login_url(get_permalink()),
            'registerUrl' => wp_registration_url(),
            'googleAuthEnabled' => $googleAuth->isEnabled(),
            'googleClientId' => $googleAuth->getClientId(),
        ]);
    }
}
