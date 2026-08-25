<?php
/**
 * Profile — identity, HairID, milestones, goals, and preferences.
 *
 * Ported from the "Profile" Claude Design mockup. The product-verdict
 * shelf, stylist-messaging card, and subscription-plan card were omitted —
 * there is no rating/verdict system, no stylist system, and no billing
 * system in this codebase to back them with real data. Everything shown
 * here is wired to real data: ProfileRepository, JournalRepository,
 * GoalRepository/GoalService, GamificationRepository.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$currentUser = wp_get_current_user();
$displayName = $currentUser->display_name ?: $currentUser->user_login;
$currentUserId = get_current_user_id();
$customAvatar = get_user_meta($currentUserId, 'myavana_custom_avatar_url', true);
$avatarUrl = !empty($customAvatar) ? $customAvatar : get_avatar_url($currentUserId, ['size' => 160]);
?>
<section class="myavana-next-view" id="view-profile" aria-label="<?php esc_attr_e('Profile', 'myavana-hair-journey-next'); ?>" style="display:none;">

    <!-- Header -->
    <div class="myavana-profile-header">
        <div class="myavana-profile-header-main">
            <div class="myavana-profile-avatar-wrap">
                <img id="profile-avatar" src="<?php echo esc_url($avatarUrl); ?>" alt="<?php echo esc_attr($displayName); ?>" />
                <button type="button" class="myavana-profile-avatar-btn" id="btn-open-avatar-picker" title="<?php esc_attr_e('Change photo', 'myavana-hair-journey-next'); ?>">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path>
                        <circle cx="12" cy="13" r="4"></circle>
                    </svg>
                </button>
            </div>
            <div class="myavana-profile-header-text">
                <h1 id="profile-name"><?php echo esc_html($displayName); ?></h1>
                <p class="myavana-profile-meta-line">
                    <span id="profile-username">@<?php echo esc_html($currentUser->user_login); ?></span>
                    <span class="myavana-profile-meta-dot" id="profile-location-dot" style="display:none;">·</span>
                    <span id="profile-location" style="display:none;"></span>
                    <span class="myavana-profile-meta-dot">·</span>
                    <span id="profile-join-date"></span>
                    <span class="myavana-profile-meta-dot">·</span>
                    <span id="profile-day-count"></span>
                </p>
                <div class="myavana-profile-tags" id="profile-hair-tags"></div>
            </div>
            <div class="myavana-profile-header-actions">
                <button type="button" class="myavana-btn myavana-btn-outline" id="btn-open-edit-drawer">
                    <?php esc_html_e('Edit Profile', 'myavana-hair-journey-next'); ?>
                </button>
                <button type="button" class="myavana-btn myavana-btn-ghost" id="btn-copy-profile-link" title="<?php esc_attr_e('Copy Profile Link', 'myavana-hair-journey-next'); ?>">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"></path>
                        <path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"></path>
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Stat row -->
    <div class="myavana-profile-stats-row">
        <div class="myavana-profile-stat-card">
            <span class="stat-label"><?php esc_html_e('Current length', 'myavana-hair-journey-next'); ?></span>
            <strong id="profile-stat-length">—</strong>
        </div>
        <div class="myavana-profile-stat-card">
            <span class="stat-label"><?php esc_html_e('Length gain', 'myavana-hair-journey-next'); ?></span>
            <strong id="profile-stat-gain">—</strong>
        </div>
        <div class="myavana-profile-stat-card">
            <span class="stat-label"><?php esc_html_e('Journey entries', 'myavana-hair-journey-next'); ?></span>
            <strong id="profile-stat-entries">0</strong>
        </div>
        <div class="myavana-profile-stat-card">
            <span class="stat-label"><?php esc_html_e('Health score', 'myavana-hair-journey-next'); ?></span>
            <strong id="profile-stat-health">0</strong>
        </div>
    </div>

    <div class="myavana-profile-layout">
        <div class="myavana-profile-main">

            <!-- HairID -->
            <section class="myavana-card" id="profile-hairid-card">
                <div class="myavana-section-heading">
                    <div>
                        <p class="myavana-eyebrow"><?php esc_html_e('Your HairID', 'myavana-hair-journey-next'); ?></p>
                        <h2><?php esc_html_e('What makes your hair, yours', 'myavana-hair-journey-next'); ?></h2>
                    </div>
                    <button type="button" class="btn-card-edit" data-edit="hair-profile" title="<?php esc_attr_e('Edit', 'myavana-hair-journey-next'); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                    </button>
                </div>
                <div class="myavana-profile-hairid-grid" id="profile-hairid-grid"></div>
                <p class="myavana-profile-hairid-note" id="profile-hairid-note"></p>
            </section>

            <!-- Milestones -->
            <section class="myavana-card" id="profile-milestones-card">
                <div class="myavana-section-heading">
                    <div>
                        <p class="myavana-eyebrow"><?php esc_html_e('Milestones', 'myavana-hair-journey-next'); ?></p>
                        <h2><?php esc_html_e('Moments worth remembering', 'myavana-hair-journey-next'); ?></h2>
                    </div>
                </div>
                <div class="myavana-profile-milestones-grid" id="profile-milestones-grid"></div>
            </section>

            <!-- Recent updates -->
            <section class="myavana-card" id="profile-recent-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;"><?php esc_html_e('Recent updates', 'myavana-hair-journey-next'); ?></h2>
                    <button type="button" class="myavana-text-action" id="profile-view-timeline">
                        <?php esc_html_e('View my timeline', 'myavana-hair-journey-next'); ?> <span aria-hidden="true">→</span>
                    </button>
                </div>
                <div id="profile-recent-list" aria-live="polite"></div>
            </section>
        </div>

        <aside class="myavana-profile-sidebar">
            <!-- Goals -->
            <section class="myavana-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;"><?php esc_html_e('Goals', 'myavana-hair-journey-next'); ?></h2>
                    <button type="button" class="btn-card-edit" data-action="manage-goals" title="<?php esc_attr_e('Manage Goals', 'myavana-hair-journey-next'); ?>">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"></line>
                            <line x1="5" y1="12" x2="19" y2="12"></line>
                        </svg>
                    </button>
                </div>
                <p class="myavana-profile-sidebar-label"><?php esc_html_e('Active', 'myavana-hair-journey-next'); ?></p>
                <div id="profile-active-goals" class="myavana-profile-goal-list"></div>
                <p class="myavana-profile-sidebar-label" id="profile-achieved-label" style="display:none;"><?php esc_html_e('Achieved', 'myavana-hair-journey-next'); ?></p>
                <div id="profile-achieved-goals" class="myavana-profile-goal-list"></div>
            </section>

            <!-- Level & Rewards -->
            <section class="myavana-card sidebar-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;">🏆 <?php esc_html_e('Level & Rewards', 'myavana-hair-journey-next'); ?></h2>
                </div>
                <div class="xp-level-badge">
                    <strong id="profile-level-title">Hair Care Explorer</strong>
                    <span id="profile-level-points">0 XP</span>
                </div>
                <div class="xp-progress-wrap">
                    <div class="xp-progress-bar" id="profile-xp-bar" style="width:0%;"></div>
                </div>
                <div class="badges-mini-grid" id="profile-badges-grid"></div>
            </section>

            <!-- Community snapshot -->
            <section class="myavana-card" id="profile-community-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;"><?php esc_html_e('Community', 'myavana-hair-journey-next'); ?></h2>
                    <button type="button" class="myavana-text-action" id="profile-view-community" style="margin:0;">
                        <?php esc_html_e('View', 'myavana-hair-journey-next'); ?> <span aria-hidden="true">→</span>
                    </button>
                </div>
                <div class="myavana-profile-community-stats">
                    <div><strong id="profile-comm-posts">0</strong><span><?php esc_html_e('Posts', 'myavana-hair-journey-next'); ?></span></div>
                    <div><strong id="profile-comm-likes">0</strong><span><?php esc_html_e('Likes', 'myavana-hair-journey-next'); ?></span></div>
                    <div><strong id="profile-comm-followers">0</strong><span><?php esc_html_e('Followers', 'myavana-hair-journey-next'); ?></span></div>
                </div>
            </section>

            <!-- Preferences -->
            <section class="myavana-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;">⚙️ <?php esc_html_e('Preferences', 'myavana-hair-journey-next'); ?></h2>
                </div>
                <form id="profile-settings-form" class="settings-form">
                    <div class="setting-item">
                        <div class="setting-text">
                            <strong><?php esc_html_e('Measurement unit', 'myavana-hair-journey-next'); ?></strong>
                            <p><?php esc_html_e('Used for your length stats and growth entries.', 'myavana-hair-journey-next'); ?></p>
                        </div>
                        <div class="myavana-unit-toggle" id="profile-unit-toggle">
                            <button type="button" class="unit-toggle-btn active" data-unit="in"><?php esc_html_e('in', 'myavana-hair-journey-next'); ?></button>
                            <button type="button" class="unit-toggle-btn" data-unit="cm"><?php esc_html_e('cm', 'myavana-hair-journey-next'); ?></button>
                        </div>
                        <input type="hidden" name="measurementUnit" id="settings-measurement-unit" value="in" />
                    </div>

                    <div class="setting-item">
                        <div class="setting-text">
                            <strong><?php esc_html_e('Default entry privacy', 'myavana-hair-journey-next'); ?></strong>
                            <p><?php esc_html_e('Who can see your hair profile and journey updates.', 'myavana-hair-journey-next'); ?></p>
                        </div>
                        <select id="settings-visibility" name="profileVisibility" class="myavana-form-control setting-select">
                            <option value="public"><?php esc_html_e('Public', 'myavana-hair-journey-next'); ?></option>
                            <option value="followers"><?php esc_html_e('Followers Only', 'myavana-hair-journey-next'); ?></option>
                            <option value="private"><?php esc_html_e('Private', 'myavana-hair-journey-next'); ?></option>
                        </select>
                    </div>

                    <div class="setting-item">
                        <div class="setting-text">
                            <strong><?php esc_html_e('Show Activity Status', 'myavana-hair-journey-next'); ?></strong>
                        </div>
                        <label class="myavana-switch">
                            <input type="checkbox" id="settings-activity-status" name="showActivityStatus" checked />
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-text">
                            <strong><?php esc_html_e('Email Care Reminders', 'myavana-hair-journey-next'); ?></strong>
                        </div>
                        <label class="myavana-switch">
                            <input type="checkbox" id="settings-email-notifications" name="emailNotifications" checked />
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="setting-item">
                        <div class="setting-text">
                            <strong><?php esc_html_e('Community Notifications', 'myavana-hair-journey-next'); ?></strong>
                        </div>
                        <label class="myavana-switch">
                            <input type="checkbox" id="settings-comm-notifications" name="communityNotifications" checked />
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="settings-actions-bar">
                        <button type="submit" class="myavana-btn myavana-btn-primary">
                            <?php esc_html_e('Save Preferences', 'myavana-hair-journey-next'); ?>
                        </button>
                    </div>
                </form>
            </section>

            <!-- Data & Account -->
            <section class="myavana-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;">🔒 <?php esc_html_e('Data & Account', 'myavana-hair-journey-next'); ?></h2>
                </div>
                <div class="data-center-row">
                    <div class="data-center-text">
                        <strong><?php esc_html_e('Export Archive', 'myavana-hair-journey-next'); ?></strong>
                        <p><?php esc_html_e('Download your journey logs, routines, and goals as JSON.', 'myavana-hair-journey-next'); ?></p>
                    </div>
                    <button type="button" id="btn-export-user-data" class="myavana-btn myavana-btn-outline btn-sm">
                        📥
                    </button>
                </div>
                <div class="data-center-row">
                    <div class="data-center-text">
                        <strong><?php esc_html_e('Log Out', 'myavana-hair-journey-next'); ?></strong>
                        <p><?php esc_html_e('Sign out of MYAVANA on this device.', 'myavana-hair-journey-next'); ?></p>
                    </div>
                    <a href="<?php echo esc_url(wp_logout_url(home_url('/'))); ?>" class="myavana-btn myavana-btn-outline btn-sm">
                        <?php esc_html_e('Log Out', 'myavana-hair-journey-next'); ?>
                    </a>
                </div>
            </section>
        </aside>
    </div>

    <!-- Edit Profile Drawer -->
    <div class="myavana-profile-drawer" id="profile-edit-drawer" aria-hidden="true">
        <div class="drawer-overlay" id="profile-drawer-overlay"></div>
        <div class="drawer-panel">
            <div class="drawer-header">
                <div>
                    <h2><?php esc_html_e('Edit Profile', 'myavana-hair-journey-next'); ?></h2>
                    <p><?php esc_html_e('Update your profile details and hair care parameters.', 'myavana-hair-journey-next'); ?></p>
                </div>
                <button type="button" class="btn-drawer-close" id="btn-close-edit-drawer" aria-label="Close">✕</button>
            </div>

            <form id="drawer-profile-form" class="drawer-body">
                <div class="drawer-section avatar-section">
                    <div class="avatar-preview-wrap">
                        <img id="drawer-avatar-preview" src="<?php echo esc_url($avatarUrl); ?>" alt="<?php echo esc_attr($displayName); ?>" />
                    </div>
                    <div class="avatar-actions">
                        <input type="file" id="drawer-avatar-file-input" accept="image/*" style="display:none;" />
                        <button type="button" class="myavana-btn myavana-btn-secondary btn-sm" id="btn-choose-avatar">
                            <?php esc_html_e('Upload Photo', 'myavana-hair-journey-next'); ?>
                        </button>
                    </div>
                </div>

                <div class="drawer-section">
                    <h4 class="section-title"><?php esc_html_e('Basic Information', 'myavana-hair-journey-next'); ?></h4>
                    <div class="myavana-form-group">
                        <label for="drawer-input-name"><?php esc_html_e('Display Name', 'myavana-hair-journey-next'); ?></label>
                        <input type="text" id="drawer-input-name" name="displayName" class="myavana-form-control" value="<?php echo esc_attr($displayName); ?>" required />
                    </div>

                    <div class="myavana-form-group">
                        <label for="drawer-input-bio"><?php esc_html_e('Bio', 'myavana-hair-journey-next'); ?></label>
                        <textarea id="drawer-input-bio" name="bio" rows="3" class="myavana-form-control" maxlength="200" placeholder="<?php esc_attr_e('Share a brief note about your hair journey...', 'myavana-hair-journey-next'); ?>"></textarea>
                        <span class="char-count" id="drawer-bio-char-count">0 / 200</span>
                    </div>

                    <div class="form-row-2">
                        <div class="myavana-form-group">
                            <label for="drawer-input-location"><?php esc_html_e('Location', 'myavana-hair-journey-next'); ?></label>
                            <input type="text" id="drawer-input-location" name="location" class="myavana-form-control" placeholder="e.g. Atlanta, GA" />
                        </div>
                        <div class="myavana-form-group">
                            <label for="drawer-input-website"><?php esc_html_e('Website', 'myavana-hair-journey-next'); ?></label>
                            <input type="url" id="drawer-input-website" name="website" class="myavana-form-control" placeholder="https://" />
                        </div>
                    </div>
                </div>

                <div class="drawer-section">
                    <h4 class="section-title"><?php esc_html_e('Hair Characteristics', 'myavana-hair-journey-next'); ?></h4>
                    <div class="form-row-2">
                        <div class="myavana-form-group">
                            <label for="drawer-input-hair-type"><?php esc_html_e('Hair Type', 'myavana-hair-journey-next'); ?></label>
                            <select id="drawer-input-hair-type" name="hairType" class="myavana-form-control">
                                <option value=""><?php esc_html_e('Select Type', 'myavana-hair-journey-next'); ?></option>
                                <option value="1A">1A - Straight Fine</option>
                                <option value="1B">1B - Straight Medium</option>
                                <option value="1C">1C - Straight Coarse</option>
                                <option value="2A">2A - Wavy Fine</option>
                                <option value="2B">2B - Wavy Medium</option>
                                <option value="2C">2C - Wavy Coarse</option>
                                <option value="3A">3A - Loose Curls</option>
                                <option value="3B">3B - Medium Curls</option>
                                <option value="3C">3C - Tight Curls</option>
                                <option value="4A">4A - Soft Coils</option>
                                <option value="4B">4B - Z-Pattern Coils</option>
                                <option value="4C">4C - Tight Kinky Coils</option>
                            </select>
                        </div>
                        <div class="myavana-form-group">
                            <label for="drawer-input-porosity"><?php esc_html_e('Porosity', 'myavana-hair-journey-next'); ?></label>
                            <select id="drawer-input-porosity" name="porosity" class="myavana-form-control">
                                <option value=""><?php esc_html_e('Select Porosity', 'myavana-hair-journey-next'); ?></option>
                                <option value="Low">Low Porosity</option>
                                <option value="Medium">Medium Porosity</option>
                                <option value="High">High Porosity</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="myavana-form-group">
                            <label for="drawer-input-density"><?php esc_html_e('Density', 'myavana-hair-journey-next'); ?></label>
                            <select id="drawer-input-density" name="density" class="myavana-form-control">
                                <option value=""><?php esc_html_e('Select Density', 'myavana-hair-journey-next'); ?></option>
                                <option value="Low">Low Density (Fine/Thin)</option>
                                <option value="Medium">Medium Density</option>
                                <option value="High">High Density (Thick/Full)</option>
                            </select>
                        </div>
                        <div class="myavana-form-group">
                            <label for="drawer-input-length"><?php esc_html_e('Current Length', 'myavana-hair-journey-next'); ?></label>
                            <select id="drawer-input-length" name="length" class="myavana-form-control">
                                <option value=""><?php esc_html_e('Select Length', 'myavana-hair-journey-next'); ?></option>
                                <option value="Short">Short (Ear/Jaw)</option>
                                <option value="Medium">Medium (Shoulder/Collarbone)</option>
                                <option value="Long">Long (Armpit/Mid-Back)</option>
                                <option value="Extra Long">Extra Long (Waist+)</option>
                            </select>
                        </div>
                    </div>

                    <div class="myavana-form-group">
                        <label for="drawer-input-health"><?php esc_html_e('Self Health Rating (1 - 10)', 'myavana-hair-journey-next'); ?></label>
                        <input type="number" id="drawer-input-health" name="hairHealthRating" min="1" max="10" class="myavana-form-control" placeholder="8" />
                    </div>
                </div>

                <div class="drawer-section">
                    <h4 class="section-title"><?php esc_html_e('Hair Concerns', 'myavana-hair-journey-next'); ?></h4>
                    <div class="concerns-chip-picker" id="drawer-concerns-picker">
                        <?php
                        $availableConcerns = [
                            'Dryness & Moisture', 'Breakage & Shedding', 'Length Retention',
                            'Scalp Care & Dandruff', 'Frizz Control', 'Curl Definition',
                            'Heat Damage Recovery', 'Color Treated Care', 'Volume & Thinning'
                        ];
                        foreach ($availableConcerns as $c):
                        ?>
                        <label class="concern-pill-check">
                            <input type="checkbox" name="concerns[]" value="<?php echo esc_attr($c); ?>" />
                            <span><?php echo esc_html($c); ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="drawer-footer">
                    <button type="button" class="myavana-btn myavana-btn-ghost" id="btn-cancel-drawer">
                        <?php esc_html_e('Cancel', 'myavana-hair-journey-next'); ?>
                    </button>
                    <button type="submit" class="myavana-btn myavana-btn-primary" id="btn-save-drawer">
                        <?php esc_html_e('Save Changes', 'myavana-hair-journey-next'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

</section>
