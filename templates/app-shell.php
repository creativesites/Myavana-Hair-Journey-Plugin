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
    <?php include MYAVANA_NEXT_PATH . 'templates/components/nav-header.php'; ?>

    <?php if ($isLoggedIn) : ?>
        <!-- Unverified Email Reminder -->
        <?php include MYAVANA_NEXT_PATH . 'templates/components/verify-email-banner.php'; ?>
    <?php endif; ?>

    <!-- Main Views Container -->
    <main class="myavana-next-main" id="myavana-main-content">
        <div class="myavana-next-container">
            <!-- Destination 0: Luxury Home (Both Logged-In & Logged-Out views) -->
            <div class="myavana-next-view" id="view-home" style="display:none;">
                <?php
                if (function_exists('myavana_luxury_home_view')) {
                    echo myavana_luxury_home_view();
                }
                ?>
            </div>

            <?php if ($isLoggedIn) : ?>
                <!-- Destination 1: Today Habit Hub -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/today.php'; ?>

                <!-- Destination 2: Timeline Workspace (Timeline, Real Compare, Heatmap) -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/journey.php'; ?>

                <!-- Destination 3: Routine & Goals Workspace -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/routine.php'; ?>

                <!-- Destination 4: Community -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/community.php'; ?>

                <!-- Destination 5: Profile & Privacy Center -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/profile.php'; ?>
            <?php else : ?>
                <!-- Public Discovery for First-Time Value -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/discovery.php'; ?>

                <!-- Sign In / Sign Up (hidden until opened via data-open-auth triggers) -->
                <?php include MYAVANA_NEXT_PATH . 'templates/views/auth.php'; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Mobile Bottom Navigation -->
    <?php include MYAVANA_NEXT_PATH . 'templates/components/nav-mobile-tabs.php'; ?>

    <?php if ($isLoggedIn) : ?>
        <!-- Two-Speed Smart Entry Modal -->
        <?php include MYAVANA_NEXT_PATH . 'templates/components/smart-entry-modal.php'; ?>

        <!-- Post-Signup Onboarding Wizard (shown once; see Assets::enqueue()) -->
        <?php include MYAVANA_NEXT_PATH . 'templates/components/onboarding-wizard.php'; ?>
    <?php endif; ?>

    <!-- Global Toast Notifications Container -->
    <?php include MYAVANA_NEXT_PATH . 'templates/components/toast-notifications.php'; ?>
</div>
