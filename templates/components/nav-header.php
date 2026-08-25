<?php
/**
 * Desktop Top Bar & Mobile Header
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$currentUser = wp_get_current_user();
$avatarUrl = get_avatar_url($currentUser->ID, ['size' => 64]);
$displayName = $currentUser->display_name ?: $currentUser->user_login;
?>
<header class="myavana-next-header">
    <div class="myavana-next-container">
        <div class="myavana-next-header-inner">
            <!-- Brand Logo (Sleek, properly sized) -->
            <a href="#home" class="myavana-next-brand myavana-next-nav-link" data-tab="home" aria-label="MYAVANA Home">
                <img src="<?php echo esc_url(MYAVANA_NEXT_URL . 'assets/images/myavana-primary-logo.png'); ?>" alt="MYAVANA" class="myavana-next-logo" />
            </a>

            <!-- Desktop Navigation Links: mirrors the established MYAVANA app header. -->
            <nav class="myavana-next-nav-desktop" aria-label="Primary Navigation">
                <a href="#home" class="myavana-next-nav-link" data-tab="home">
                    <?php esc_html_e('Home', 'myavana-hair-journey-next'); ?>
                </a>
                <a href="#today" class="myavana-next-nav-link active" data-tab="today">
                    <?php esc_html_e('Today', 'myavana-hair-journey-next'); ?>
                </a>
                <a href="#journey" class="myavana-next-nav-link" data-tab="journey">
                    <?php esc_html_e('My Timeline', 'myavana-hair-journey-next'); ?>
                </a>
                <a href="#routine" class="myavana-next-nav-link" data-tab="routine" data-routine-panel="routine">
                    <?php esc_html_e('Routines', 'myavana-hair-journey-next'); ?>
                </a>
                <a href="#routine" class="myavana-next-nav-link" data-tab="routine" data-routine-panel="goals">
                    <?php esc_html_e('Goals', 'myavana-hair-journey-next'); ?>
                </a>
                <a href="#community" class="myavana-next-nav-link" data-tab="community">
                    <?php esc_html_e('Community', 'myavana-hair-journey-next'); ?>
                </a>
                <a href="#profile" class="myavana-next-nav-link" data-tab="profile">
                    <?php esc_html_e('Profile', 'myavana-hair-journey-next'); ?>
                </a>
            </nav>

            <!-- Header Utilities -->
            <div class="myavana-next-header-actions">
                <!-- Chat Support Trigger (Launches Kommunicate) -->
                <button type="button" class="myavana-next-concierge-btn btn-open-kommunicate" aria-label="<?php esc_attr_e('Open Hair Care Chat', 'myavana-hair-journey-next'); ?>">
                    <span class="myavana-next-concierge-badge"></span>
                    <span><?php esc_html_e('Chat with us', 'myavana-hair-journey-next'); ?></span>
                </button>

                <?php if (is_user_logged_in()) : ?>
                    <div class="myavana-next-account-menu">
                        <button type="button" class="myavana-next-avatar-btn" id="myavana-account-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="myavana-account-dropdown" title="<?php echo esc_attr($displayName); ?>">
                            <img src="<?php echo esc_url($avatarUrl); ?>" alt="<?php echo esc_attr($displayName); ?>" class="myavana-next-avatar-img" />
                            <span class="myavana-next-avatar-name"><?php echo esc_html($displayName); ?></span>
                        </button>

                        <div class="myavana-account-dropdown" id="myavana-account-dropdown" role="menu" hidden>
                            <div class="myavana-account-dropdown-header">
                                <img src="<?php echo esc_url($avatarUrl); ?>" alt="<?php echo esc_attr($displayName); ?>" class="myavana-account-dropdown-avatar" />
                                <div class="myavana-account-dropdown-identity">
                                    <strong><?php echo esc_html($displayName); ?></strong>
                                    <span><?php echo esc_html($currentUser->user_email); ?></span>
                                </div>
                            </div>
                            <a href="#profile" class="myavana-account-dropdown-item" data-tab="profile" role="menuitem">
                                <?php esc_html_e('View Profile', 'myavana-hair-journey-next'); ?>
                            </a>
                            <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="myavana-account-dropdown-item myavana-account-dropdown-item-danger" role="menuitem">
                                <?php esc_html_e('Log Out', 'myavana-hair-journey-next'); ?>
                            </a>
                        </div>
                    </div>
                <?php else : ?>
                    <a href="#" data-open-auth="signin" class="myavana-btn myavana-btn-outline myavana-btn-sm">
                        <?php esc_html_e('Sign In', 'myavana-hair-journey-next'); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>
