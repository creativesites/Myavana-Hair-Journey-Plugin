<?php
/**
 * Master App Shell Template for [myavana_journey_next]
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$isLoggedIn = \Myavana\Next\Core\Permissions::isAuthenticated();
?>
<div class="myavana-next myavana-next-shell" id="myavana-next-root" data-default-tab="<?php echo esc_attr($defaultTab ?? 'today'); ?>">
    <!-- Nav Progress Bar: the first visible response to tapping a nav item,
         before that destination's own loading/empty state has a chance to
         render. See app.js navigate(). -->
    <div class="myavana-nav-progress" id="myavana-nav-progress" aria-hidden="true"></div>

    <!-- Top Navigation Bar -->
    <?php \Myavana\Next\Core\SafeRender::section('nav-header', function () { include MYAVANA_NEXT_PATH . 'templates/components/nav-header.php'; }); ?>

    <?php if ($isLoggedIn) : ?>
        <!-- Unverified Email Reminder -->
        <?php \Myavana\Next\Core\SafeRender::section('verify-email-banner', function () { include MYAVANA_NEXT_PATH . 'templates/components/verify-email-banner.php'; }); ?>
    <?php endif; ?>

    <!-- Main Views Container -->
    <main class="myavana-next-main" id="myavana-main-content">
        <div class="myavana-next-container">
            <!-- Destination 0: Luxury Home (Both Logged-In & Logged-Out views) -->
            <div class="myavana-next-view" id="view-home" style="display:none;">
                <?php
                \Myavana\Next\Core\SafeRender::section('luxury-home', function () {
                    if (function_exists('myavana_luxury_home_view')) {
                        echo myavana_luxury_home_view();
                    }
                });
                ?>
            </div>

            <?php if ($isLoggedIn) : ?>
                <!-- Destination 1: Today Habit Hub -->
                <?php \Myavana\Next\Core\SafeRender::section('today', function () { include MYAVANA_NEXT_PATH . 'templates/views/today.php'; }); ?>

                <!-- Destination 2: Timeline Workspace (Timeline, Real Compare, Heatmap) -->
                <?php \Myavana\Next\Core\SafeRender::section('journey', function () { include MYAVANA_NEXT_PATH . 'templates/views/journey.php'; }); ?>

                <!-- Destination 3: Routine & Goals Workspace -->
                <?php \Myavana\Next\Core\SafeRender::section('routine', function () { include MYAVANA_NEXT_PATH . 'templates/views/routine.php'; }); ?>

                <!-- Destination 4: Community -->
                <?php \Myavana\Next\Core\SafeRender::section('community', function () { include MYAVANA_NEXT_PATH . 'templates/views/community.php'; }); ?>

                <!-- Destination 5: Profile & Privacy Center -->
                <?php \Myavana\Next\Core\SafeRender::section('profile', function () { include MYAVANA_NEXT_PATH . 'templates/views/profile.php'; }); ?>
            <?php else : ?>
                <!-- Community is a public window into the MYAVANA experience. -->
                <?php \Myavana\Next\Core\SafeRender::section('community', function () { include MYAVANA_NEXT_PATH . 'templates/views/community.php'; }); ?>

                <!-- Sign In / Sign Up (hidden until opened via data-open-auth triggers) -->
                <?php \Myavana\Next\Core\SafeRender::section('auth', function () { include MYAVANA_NEXT_PATH . 'templates/views/auth.php'; }); ?>

                <!-- A light, one-time welcome prompt — not a separate destination. -->
                <?php \Myavana\Next\Core\SafeRender::section('discovery', function () { include MYAVANA_NEXT_PATH . 'templates/views/discovery.php'; }); ?>
            <?php endif; ?>
        </div>
    </main>

    <?php \Myavana\Next\Core\SafeRender::section('site-footer', function () { include MYAVANA_NEXT_PATH . 'templates/components/site-footer.php'; }); ?>

    <!-- Mobile Bottom Navigation -->
    <?php \Myavana\Next\Core\SafeRender::section('nav-mobile-tabs', function () { include MYAVANA_NEXT_PATH . 'templates/components/nav-mobile-tabs.php'; }); ?>

    <?php if ($isLoggedIn) : ?>
        <!-- Two-Speed Smart Entry Modal -->
        <?php \Myavana\Next\Core\SafeRender::section('smart-entry-modal', function () { include MYAVANA_NEXT_PATH . 'templates/components/smart-entry-modal.php'; }); ?>

        <!-- Post-Signup Onboarding Wizard (shown once; see Assets::enqueue()) -->
        <?php \Myavana\Next\Core\SafeRender::section('onboarding-wizard', function () { include MYAVANA_NEXT_PATH . 'templates/components/onboarding-wizard.php'; }); ?>
    <?php endif; ?>

    <!-- Global Toast Notifications Container -->
    <?php \Myavana\Next\Core\SafeRender::section('toast-notifications', function () { include MYAVANA_NEXT_PATH . 'templates/components/toast-notifications.php'; }); ?>
</div>
