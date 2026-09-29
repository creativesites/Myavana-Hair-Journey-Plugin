<?php
/**
 * Admin Settings Page for MYAVANA Next
 *
 * @package Myavana\Next\Admin
 */

namespace Myavana\Next\Admin;

use Myavana\Next\Core\FeatureFlags;
use Myavana\Next\Http\Routes\ClientLogRoutes;

if (!defined('ABSPATH')) {
    exit;
}

class SettingsPage {
    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'registerMenu']);
        add_action('admin_init', [__CLASS__, 'registerSettings']);
    }

    public static function registerMenu(): void {
        add_submenu_page(
            'options-general.php',
            __('MYAVANA Next Settings', 'myavana-hair-journey-next'),
            __('MYAVANA Next', 'myavana-hair-journey-next'),
            'manage_options',
            'myavana-next-settings',
            [__CLASS__, 'renderPage']
        );
    }

    public static function registerSettings(): void {
        register_setting('myavana_next_options_group', 'myavana_gemini_api_key');
        register_setting('myavana_next_options_group', 'myavana_next_feature_flags');
        register_setting('myavana_next_options_group', 'myavana_next_google_client_id');
        register_setting('myavana_next_options_group', 'myavana_next_google_auth_enabled');
        register_setting('myavana_next_options_group', 'myavana_next_hair_analysis_url', [
            'sanitize_callback' => static fn($v) => esc_url_raw(trim((string) $v)),
        ]);
        register_setting('myavana_next_options_group', 'myavana_next_ai_provider');
        register_setting('myavana_next_options_group', \Myavana\Next\Application\WeeklyNoteService::ENABLED_OPTION, [
            'type' => 'boolean',
            'sanitize_callback' => static fn($v) => !empty($v) ? 1 : 0,
        ]);
    }

    public static function renderPage(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $flags = FeatureFlags::getAll();
        $apiKey = get_option('myavana_gemini_api_key', '');
        $googleClientId = get_option('myavana_next_google_client_id', '');
        $googleAuthEnabled = get_option('myavana_next_google_auth_enabled', true);
        // Older releases stored an in-app hash ("#routine"), which the url
        // field rejects, blocking every save on this page. Show the URL
        // actually in use instead.
        $hairAnalysisUrl = \Myavana\Next\Core\LaunchScope::hairAiUrl();
        $aiProvider = get_option('myavana_next_ai_provider', 'gemini');

        if ($apiKey === '' && !defined('MYAVANA_GEMINI_API_KEY')) {
            $intelligenceStatus = ['label' => __('Off — no Gemini API key configured, Today shows rule-based insights', 'myavana-hair-journey-next'), 'color' => '#6e6e73', 'bg' => '#f5f5f7'];
        } else {
            $intelligenceStatus = ['label' => __('Live — Today insights are AI-generated and cached', 'myavana-hair-journey-next'), 'color' => '#3f7d5c', 'bg' => '#e2efe8'];
        }

        if (!$googleAuthEnabled) {
            $googleStatus = ['label' => __('Disabled', 'myavana-hair-journey-next'), 'color' => '#6e6e73', 'bg' => '#f5f5f7'];
        } elseif ($googleClientId === '') {
            $googleStatus = ['label' => __('Enabled, but no Client ID set — the button will not appear to visitors', 'myavana-hair-journey-next'), 'color' => '#8a6414', 'bg' => '#f7ecd3'];
        } else {
            $googleStatus = ['label' => __('Live — button renders for logged-out visitors', 'myavana-hair-journey-next'), 'color' => '#3f7d5c', 'bg' => '#e2efe8'];
        }
        ?>
        <div class="wrap" style="max-width: 840px;">
            <h1 style="display:flex; align-items:center; gap:10px;">
                <span style="display:inline-block; width:12px; height:12px; border-radius:50%; background:#e7a690;"></span>
                <?php esc_html_e('MYAVANA Hair Journey Next — Control Center', 'myavana-hair-journey-next'); ?>
            </h1>
            <p><?php esc_html_e('Configure server-side AI integration, feature rollout toggles, and review app status.', 'myavana-hair-journey-next'); ?></p>

            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                <h2><?php esc_html_e('Live Shortcode & Integration', 'myavana-hair-journey-next'); ?></h2>
                <p><?php esc_html_e('Embed the new parallel unified app shell on any WordPress page with:', 'myavana-hair-journey-next'); ?></p>
                <code style="font-size:16px; padding:6px 12px; background:#f5f5f7; display:inline-block; border-radius:4px; border:1px solid #e0e0e0;">[myavana_journey_next]</code>
                <p style="margin-top:10px; font-size:13px; color:#666;">
                    <?php esc_html_e('Note: All legacy shortcodes (e.g. [myavana_luxury_home], [myavana_hair-journey-page]) remain fully functional.', 'myavana-hair-journey-next'); ?>
                </p>
            </div>

            <?php $reports = get_option(ClientLogRoutes::OPTION, []); ?>
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                <h2><?php esc_html_e('Load problem reports', 'myavana-hair-journey-next'); ?></h2>
                <p class="description"><?php esc_html_e('Sent automatically by members\' browsers when the app fails to start or a view fails to load. Newest first; the last 100 are kept.', 'myavana-hair-journey-next'); ?></p>
                <?php if (empty($reports) || !is_array($reports)) : ?>
                    <p><em><?php esc_html_e('No reports yet.', 'myavana-hair-journey-next'); ?></em></p>
                <?php else : ?>
                    <div style="max-height:420px; overflow:auto;">
                        <table class="widefat striped" style="font-size:12px;">
                            <thead><tr><th>Time</th><th>Member</th><th>What</th><th>Status</th><th>Details</th><th>Browser</th></tr></thead>
                            <tbody>
                            <?php foreach ($reports as $r) : ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?php echo esc_html($r['time'] ?? ''); ?></td>
                                    <td><?php echo esc_html($r['who'] ?? ''); ?></td>
                                    <td><?php echo esc_html(($r['endpoint'] ?? '') . ' #' . ($r['attempt'] ?? 0) . (($r['online'] ?? 'yes') === 'no' ? ' (offline)' : '')); ?></td>
                                    <td><?php echo esc_html((string) ($r['status'] ?? '')); ?></td>
                                    <td style="word-break:break-all;"><code style="font-size:11px;"><?php echo esc_html($r['reason'] ?? ''); ?></code></td>
                                    <td style="word-break:break-all; color:#666;"><?php echo esc_html($r['ua'] ?? ''); ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php
            $weeklyEnabled = \Myavana\Next\Application\WeeklyNoteService::isEnabled();
            $weeklyNext = wp_next_scheduled(\Myavana\Next\Application\WeeklyNoteService::HOOK);
            $weeklyDue = count(\Myavana\Next\Application\WeeklyNoteService::recipients(wp_date('o-W'), 500));
            $weeklyTest = isset($_GET['myavana_weekly_test']) ? sanitize_key(wp_unslash($_GET['myavana_weekly_test'])) : '';
            ?>
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e('Weekly note (Sunday email)', 'myavana-hair-journey-next'); ?></h2>
                <p style="max-width:720px;"><?php esc_html_e('One short email each Sunday at 9:00 to members who logged an entry in the last 30 days and have care reminders on: their latest photo, their weekly rhythm, and a "Log this week" button. Each email has a one-click unsubscribe. Sent with the site\'s current mail setup.', 'myavana-hair-journey-next'); ?></p>
                <?php if ($weeklyTest === 'sent') : ?>
                    <div class="notice notice-success inline"><p><?php esc_html_e('Test email sent to your address. Check your inbox (and spam) to see how it looks.', 'myavana-hair-journey-next'); ?></p></div>
                <?php elseif ($weeklyTest === 'failed') : ?>
                    <div class="notice notice-error inline"><p><?php esc_html_e('The test email could not be sent. Check the site\'s mail setup.', 'myavana-hair-journey-next'); ?></p></div>
                <?php endif; ?>
                <p>
                    <strong><?php esc_html_e('Status:', 'myavana-hair-journey-next'); ?></strong>
                    <?php echo $weeklyEnabled ? esc_html__('On', 'myavana-hair-journey-next') : esc_html__('Off (turn on below after checking a test)', 'myavana-hair-journey-next'); ?>
                    &nbsp;·&nbsp;
                    <strong><?php esc_html_e('Next send:', 'myavana-hair-journey-next'); ?></strong>
                    <?php echo $weeklyNext ? esc_html(wp_date('D, M j, g:i a', $weeklyNext)) : '—'; ?>
                    &nbsp;·&nbsp;
                    <strong><?php esc_html_e('Members it would reach now:', 'myavana-hair-journey-next'); ?></strong>
                    <?php echo esc_html((string) $weeklyDue); ?>
                </p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="margin:0;">
                    <input type="hidden" name="action" value="myavana_weekly_note_test">
                    <?php wp_nonce_field('myavana_weekly_note_test'); ?>
                    <?php submit_button(__('Send me a test', 'myavana-hair-journey-next'), 'secondary', 'submit', false); ?>
                </form>
            </div>

            <form method="post" action="options.php">
                <?php
                settings_fields('myavana_next_options_group');
                do_settings_sections('myavana_next_options_group');
                ?>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e('Weekly note', 'myavana-hair-journey-next'); ?></h2>
                    <label>
                        <input type="checkbox" name="<?php echo esc_attr(\Myavana\Next\Application\WeeklyNoteService::ENABLED_OPTION); ?>" value="1" <?php checked(\Myavana\Next\Application\WeeklyNoteService::isEnabled()); ?>>
                        <?php esc_html_e('Send the weekly note every Sunday', 'myavana-hair-journey-next'); ?>
                    </label>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                    <h2><?php esc_html_e('MYAVANA Intelligence', 'myavana-hair-journey-next'); ?></h2>
                    <p style="margin:0 0 16px;">
                        <span style="display:inline-block; padding:4px 12px; border-radius:999px; font-size:12.5px; font-weight:600; color:<?php echo esc_attr($intelligenceStatus['color']); ?>; background:<?php echo esc_attr($intelligenceStatus['bg']); ?>;">
                            <?php echo esc_html($intelligenceStatus['label']); ?>
                        </span>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="myavana_next_ai_provider"><?php esc_html_e('AI Provider', 'myavana-hair-journey-next'); ?></label></th>
                            <td>
                                <select name="myavana_next_ai_provider" id="myavana_next_ai_provider">
                                    <option value="gemini" <?php selected($aiProvider, 'gemini'); ?>><?php esc_html_e('Google Gemini', 'myavana-hair-journey-next'); ?></option>
                                    <option value="openai" disabled><?php esc_html_e('OpenAI (not yet available)', 'myavana-hair-journey-next'); ?></option>
                                    <option value="claude" disabled><?php esc_html_e('Claude (not yet available)', 'myavana-hair-journey-next'); ?></option>
                                    <option value="deepseek" disabled><?php esc_html_e('DeepSeek (not yet available)', 'myavana-hair-journey-next'); ?></option>
                                </select>
                                <p class="description"><?php esc_html_e('Gemini is the only provider implemented today. The others are reserved so switching providers later never requires touching Today or any other feature that requests an insight.', 'myavana-hair-journey-next'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myavana_gemini_api_key"><?php esc_html_e('Gemini API Key', 'myavana-hair-journey-next'); ?></label></th>
                            <td>
                                <input type="password" name="myavana_gemini_api_key" id="myavana_gemini_api_key" value="<?php echo esc_attr($apiKey); ?>" class="regular-text" style="width:100%; max-width:480px;" />
                                <p class="description"><?php esc_html_e('Used securely server-side for AI consultations and Today insights. Never exposed to browser JavaScript. Leave blank to run Today on rule-based insights only.', 'myavana-hair-journey-next'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                    <h2><?php esc_html_e('Hair Analysis Product Link', 'myavana-hair-journey-next'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="myavana_next_hair_analysis_url"><?php esc_html_e('Destination URL', 'myavana-hair-journey-next'); ?></label></th>
                            <td>
                                <input type="url" name="myavana_next_hair_analysis_url" id="myavana_next_hair_analysis_url" value="<?php echo esc_attr($hairAnalysisUrl); ?>" class="regular-text" style="width:100%; max-width:480px;" />
                                <p class="description"><?php esc_html_e('Hair Journey tracks routines and progress — it does not perform AI hair analysis itself. This is the single place that URL is configured; every "Explore MYAVANA Hair Analysis" link on the site points here.', 'myavana-hair-journey-next'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                    <h2><?php esc_html_e('Google Sign-In', 'myavana-hair-journey-next'); ?></h2>
                    <p style="margin:0 0 16px;">
                        <span style="display:inline-block; padding:4px 12px; border-radius:999px; font-size:12.5px; font-weight:600; color:<?php echo esc_attr($googleStatus['color']); ?>; background:<?php echo esc_attr($googleStatus['bg']); ?>;">
                            <?php echo esc_html($googleStatus['label']); ?>
                        </span>
                    </p>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Google Sign-In', 'myavana-hair-journey-next'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="myavana_next_google_auth_enabled" value="1" <?php checked(!empty($googleAuthEnabled)); ?> />
                                    <?php esc_html_e('Show "Sign in / up with Google" as the primary option on the auth view', 'myavana-hair-journey-next'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="myavana_next_google_client_id"><?php esc_html_e('Google OAuth Client ID', 'myavana-hair-journey-next'); ?></label></th>
                            <td>
                                <input type="text" name="myavana_next_google_client_id" id="myavana_next_google_client_id" value="<?php echo esc_attr($googleClientId); ?>" class="regular-text" style="width:100%; max-width:480px;" />
                                <p class="description"><?php esc_html_e('Same Client ID already authorized in Google Cloud Console for this site — Client IDs are public, safe to share between the legacy and Next plugins.', 'myavana-hair-journey-next'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px; margin-bottom:20px;">
                    <h2><?php esc_html_e('Feature Flags & Rollout Cohorts', 'myavana-hair-journey-next'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Beta Cohort Restriction', 'myavana-hair-journey-next'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="myavana_next_feature_flags[beta_cohort_only]" value="1" <?php checked(!empty($flags['beta_cohort_only'])); ?> />
                                    <?php esc_html_e('Restrict Next experience to administrators and tagged beta users only', 'myavana-hair-journey-next'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Enabled Destinations', 'myavana-hair-journey-next'); ?></th>
                            <td>
                                <fieldset>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[today_hub]" value="1" <?php checked(!empty($flags['today_hub'])); ?> /> <?php esc_html_e('Today Habit Hub', 'myavana-hair-journey-next'); ?></label><br>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[journey_workspace]" value="1" <?php checked(!empty($flags['journey_workspace'])); ?> /> <?php esc_html_e('Journey Workspace (Timeline, Compare, Heatmap)', 'myavana-hair-journey-next'); ?></label><br>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[routine_workspace]" value="1" <?php checked(!empty($flags['routine_workspace'])); ?> /> <?php esc_html_e('Routine & Goals Workspace', 'myavana-hair-journey-next'); ?></label><br>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[community_feed]" value="1" <?php checked(!empty($flags['community_feed'])); ?> /> <?php esc_html_e('Community & Hair Twins Feed', 'myavana-hair-journey-next'); ?></label><br>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[profile_privacy]" value="1" <?php checked(!empty($flags['profile_privacy'])); ?> /> <?php esc_html_e('Profile, Rewards & Privacy Center', 'myavana-hair-journey-next'); ?></label><br>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[ai_concierge]" value="1" <?php checked(!empty($flags['ai_concierge'])); ?> /> <?php esc_html_e('AI Hair Concierge Drawer', 'myavana-hair-journey-next'); ?></label><br>
                                    <label><input type="checkbox" name="myavana_next_feature_flags[onboarding_wizard]" value="1" <?php checked(!empty($flags['onboarding_wizard'])); ?> /> <?php esc_html_e('Post-Signup Onboarding Wizard (hair type, porosity, concerns & goals)', 'myavana-hair-journey-next'); ?></label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <?php submit_button(__('Save Settings', 'myavana-hair-journey-next')); ?>
            </form>
        </div>
        <?php
    }
}
