<?php
/**
 * Global app navbar rendered once via plugin hooks.
 *
 * Expected variables from caller:
 * - $active_page
 * - $is_logged_in
 * - $current_user
 * - $home_url
 * - $journey_url
 * - $goals_url
 * - $routines_url
 * - $community_url
 * - $profile_url
 * - $logout_url
 * - $ai_tool_url
 * - $admin_portal_url
 * - $can_access_admin_portal
 */
$profile_display_name = '';
if ($is_logged_in && !empty($current_user) && !empty($current_user->ID)) {
    $profile_display_name = $current_user->display_name ?: $current_user->user_login;
}

$request_path = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$is_goals_page = strpos($request_path, 'goals') !== false;
$is_routines_page = strpos($request_path, 'routines') !== false;
$is_journey_page = $active_page === 'journey' && !$is_goals_page && !$is_routines_page;
?>
<nav class="myavana-luxury-nav myavana-global-navbar" id="myavanaGlobalNavbar">
    <div class="myavana-luxury-nav-container">
        <a href="<?php echo esc_url($home_url); ?>" class="myavana-luxury-logo">
            <img src="<?php echo esc_url(MYAVANA_URL . 'assets/images/myavana-primary-logo.png'); ?>" alt="Myavana" class="myavana-logo" />
        </a>

        <?php if (!$is_logged_in): ?>
            <div class="myavana-luxury-nav-menu">
                <a href="#features" class="myavana-luxury-nav-link">Features</a>
                <a href="#how-it-works" class="myavana-luxury-nav-link">How It Works</a>
            </div>

            <div class="myavana-luxury-nav-actions">
                <button type="button" class="myavana-luxury-btn-secondary" onclick="showMyavanaModal('login')">Sign In</button>
                <button type="button" class="myavana-luxury-btn-primary" onclick="showMyavanaModal('register')">Get Started</button>
            </div>
        <?php else: ?>
            <div class="myavana-luxury-nav-menu">
                <a href="<?php echo esc_url($journey_url); ?>" class="myavana-luxury-nav-link <?php echo $is_journey_page ? 'is-active' : ''; ?>">My Timeline</a>
                <a href="<?php echo esc_url($goals_url); ?>" class="myavana-luxury-nav-link <?php echo $is_goals_page ? 'is-active' : ''; ?>">Goals</a>
                <a href="<?php echo esc_url($routines_url); ?>" class="myavana-luxury-nav-link <?php echo $is_routines_page ? 'is-active' : ''; ?>">Routines</a>
                <a href="<?php echo esc_url($community_url); ?>" class="myavana-luxury-nav-link <?php echo $active_page === 'community' ? 'is-active' : ''; ?>">Community</a>
                <a href="<?php echo esc_url($profile_url); ?>" class="myavana-luxury-nav-link <?php echo $active_page === 'profile' ? 'is-active' : ''; ?>">Profile</a>
                <?php if (!empty($can_access_admin_portal)): ?>
                <a href="<?php echo esc_url($admin_portal_url); ?>" class="myavana-luxury-nav-link">Admin Portal</a>
                <?php endif; ?>
                
                <div style="display: flex; gap: 4px; margin-left: 12px; padding-left: 12px; border-left: 1px solid rgba(0,0,0,0.1);">
                    <a href="#" class="myavana-luxury-nav-link myavana-nav-utility-link" onclick="createGoal(); return false;">+ Goal</a>
                    <a href="#" class="myavana-luxury-nav-link myavana-nav-utility-link myavana-nav-smart-entry" onclick="createEntry();">+ New Entry</a>
                </div>
            </div>

            <div class="myavana-luxury-nav-actions myavana-global-nav-user-actions">
                <button
                    type="button"
                    class="myavana-mobile-header-toggle"
                    id="myavanaMobileHeaderToggle"
                    aria-expanded="false"
                    aria-label="Expand header"
                >
                    <span class="myavana-toggle-glyph" aria-hidden="true"></span>
                </button>
                <a href="<?php echo esc_url($logout_url); ?>" class="myavana-nav-logout-link">Logout</a>
                <div class="myavana-app-profile-dropdown">
                    <button
                        type="button"
                        class="myavana-app-avatar-btn myavana-app-profile-toggle"
                        aria-haspopup="true"
                        aria-expanded="false"
                        aria-label="Open profile menu"
                    >
                        <img src="<?php echo esc_url(get_avatar_url($current_user->ID)); ?>" class="myavana-app-avatar-img" alt="<?php echo esc_attr($profile_display_name ?: 'Profile'); ?>">
                    </button>
                    <div class="myavana-app-profile-menu" role="menu" aria-label="Profile">
                        <div class="myavana-app-profile-menu-header">
                            <div class="myavana-app-profile-name"><?php echo esc_html($profile_display_name ?: 'Myavana Member'); ?></div>
                            <div class="myavana-app-profile-subtitle">Manage your account</div>
                        </div>
                        <a href="<?php echo esc_url($profile_url); ?>" role="menuitem">My Profile</a>
                        <a href="<?php echo esc_url($journey_url); ?>" role="menuitem">My Timeline</a>
                        <a href="<?php echo esc_url($goals_url); ?>" role="menuitem">Goals</a>
                        <a href="<?php echo esc_url($routines_url); ?>" role="menuitem">Routines</a>
                        <a href="<?php echo esc_url($community_url); ?>" role="menuitem">Community</a>
                        <?php if (!empty($can_access_admin_portal)): ?>
                        <a href="<?php echo esc_url($admin_portal_url); ?>" role="menuitem">Admin Portal</a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url($logout_url); ?>" class="logout" role="menuitem">Logout</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</nav>
