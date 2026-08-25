<?php
/**
 * Full-Page Sign In / Sign Up View (for logged-out visitors)
 *
 * Lives inside the app shell as a sibling of discovery.php, toggled by
 * assets/js/modules/auth.js. Not a modal — the app shell is a single-page
 * shell, so auth is a state within it rather than an overlay on top of it.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="myavana-next-view myavana-auth-view" id="view-auth" aria-label="<?php esc_attr_e('Sign in or create an account', 'myavana-hair-journey-next'); ?>" style="display:none;">
    <div class="myavana-auth-wrap">
        <div class="myavana-card myavana-auth-card">
            <div class="myavana-auth-toggle" role="tablist">
                <button type="button" class="myavana-auth-toggle-btn active" id="myavana-auth-tab-signin" role="tab" aria-selected="true" aria-controls="myavana-auth-signin-panel">
                    <?php esc_html_e('Sign In', 'myavana-hair-journey-next'); ?>
                </button>
                <button type="button" class="myavana-auth-toggle-btn" id="myavana-auth-tab-signup" role="tab" aria-selected="false" aria-controls="myavana-auth-signup-panel">
                    <?php esc_html_e('Sign Up', 'myavana-hair-journey-next'); ?>
                </button>
            </div>

            <div class="myavana-auth-message" id="myavana-auth-message" role="alert" style="display:none;"></div>

            <!-- Sign In Panel -->
            <form class="myavana-auth-panel active" id="myavana-auth-signin-panel" role="tabpanel" aria-labelledby="myavana-auth-tab-signin">
                <div class="myavana-google-auth-wrap">
                    <div id="myavana-google-signin-slot" class="myavana-google-auth-slot"></div>
                </div>
                <div class="myavana-auth-divider"><span><?php esc_html_e('or continue with email', 'myavana-hair-journey-next'); ?></span></div>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="myavana-auth-signin-login"><?php esc_html_e('Email or Username', 'myavana-hair-journey-next'); ?></label>
                    <input type="text" class="myavana-input" id="myavana-auth-signin-login" name="login" autocomplete="username" required>
                </div>

                <div class="myavana-form-group">
                    <div class="myavana-auth-label-row">
                        <label class="myavana-label" for="myavana-auth-signin-password"><?php esc_html_e('Password', 'myavana-hair-journey-next'); ?></label>
                        <a href="#" class="myavana-auth-inline-link" id="myavana-auth-forgot-link"><?php esc_html_e('Forgot password?', 'myavana-hair-journey-next'); ?></a>
                    </div>
                    <input type="password" class="myavana-input" id="myavana-auth-signin-password" name="password" autocomplete="current-password" required>
                </div>

                <label class="myavana-auth-checkbox">
                    <input type="checkbox" id="myavana-auth-remember" name="remember" value="1">
                    <?php esc_html_e('Remember me for 30 days', 'myavana-hair-journey-next'); ?>
                </label>

                <button type="submit" class="myavana-btn myavana-btn-primary myavana-btn-block myavana-btn-lg">
                    <?php esc_html_e('Sign In to MYAVANA', 'myavana-hair-journey-next'); ?>
                </button>
            </form>

            <!-- Sign Up Panel -->
            <form class="myavana-auth-panel" id="myavana-auth-signup-panel" role="tabpanel" aria-labelledby="myavana-auth-tab-signup" style="display:none;">
                <div class="myavana-google-auth-wrap">
                    <div id="myavana-google-signup-slot" class="myavana-google-auth-slot"></div>
                </div>
                <div class="myavana-auth-divider"><span><?php esc_html_e('or continue with email', 'myavana-hair-journey-next'); ?></span></div>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="myavana-auth-signup-name"><?php esc_html_e('Full Name', 'myavana-hair-journey-next'); ?></label>
                    <input type="text" class="myavana-input" id="myavana-auth-signup-name" name="name" autocomplete="name" required>
                </div>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="myavana-auth-signup-email"><?php esc_html_e('Email Address', 'myavana-hair-journey-next'); ?></label>
                    <input type="email" class="myavana-input" id="myavana-auth-signup-email" name="email" autocomplete="email" required>
                </div>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="myavana-auth-signup-password"><?php esc_html_e('Create Password', 'myavana-hair-journey-next'); ?></label>
                    <input type="password" class="myavana-input" id="myavana-auth-signup-password" name="password" autocomplete="new-password" minlength="8" required>
                    <div class="myavana-password-meter" id="myavana-password-meter" aria-live="polite">
                        <div class="myavana-password-meter-bar"><span id="myavana-password-meter-fill"></span></div>
                        <ul class="myavana-password-meter-rules">
                            <li data-rule="length"><?php esc_html_e('At least 8 characters', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="upper"><?php esc_html_e('One uppercase letter', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="lower"><?php esc_html_e('One lowercase letter', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="number"><?php esc_html_e('One number', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="special"><?php esc_html_e('One special character (!@#$%^&*)', 'myavana-hair-journey-next'); ?></li>
                        </ul>
                    </div>
                </div>

                <label class="myavana-auth-checkbox">
                    <input type="checkbox" id="myavana-auth-terms" name="terms" value="1" required>
                    <?php
                    printf(
                        /* translators: 1: Terms of Service link, 2: Privacy Policy link */
                        esc_html__('I agree to the %1$s and %2$s', 'myavana-hair-journey-next'),
                        '<a href="' . esc_url(home_url('/terms/')) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Terms of Service', 'myavana-hair-journey-next') . '</a>',
                        '<a href="' . esc_url(home_url('/privacy/')) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Privacy Policy', 'myavana-hair-journey-next') . '</a>'
                    );
                    ?>
                </label>

                <button type="submit" class="myavana-btn myavana-btn-primary myavana-btn-block myavana-btn-lg">
                    <?php esc_html_e('Create My MYAVANA Account', 'myavana-hair-journey-next'); ?>
                </button>
            </form>

            <!-- Forgot Password Panel -->
            <form class="myavana-auth-panel" id="myavana-auth-forgot-panel" role="tabpanel" style="display:none;">
                <p class="myavana-auth-panel-intro"><?php esc_html_e("Enter the email on your account and we'll send you a link to reset your password.", 'myavana-hair-journey-next'); ?></p>

                <div class="myavana-form-group">
                    <label class="myavana-label" for="myavana-auth-forgot-email"><?php esc_html_e('Email Address', 'myavana-hair-journey-next'); ?></label>
                    <input type="email" class="myavana-input" id="myavana-auth-forgot-email" name="email" autocomplete="email" required>
                </div>

                <button type="submit" class="myavana-btn myavana-btn-primary myavana-btn-block myavana-btn-lg">
                    <?php esc_html_e('Send Reset Link', 'myavana-hair-journey-next'); ?>
                </button>
                <button type="button" class="myavana-auth-link-btn" id="myavana-auth-back-to-signin">
                    <?php esc_html_e('← Back to Sign In', 'myavana-hair-journey-next'); ?>
                </button>
            </form>

            <!-- Reset Password Panel (only reachable via the emailed link) -->
            <form class="myavana-auth-panel" id="myavana-auth-reset-panel" role="tabpanel" style="display:none;">
                <p class="myavana-auth-panel-intro"><?php esc_html_e('Choose a new password for your account.', 'myavana-hair-journey-next'); ?></p>
                <input type="hidden" id="myavana-auth-reset-uid" value="">
                <input type="hidden" id="myavana-auth-reset-token" value="">

                <div class="myavana-form-group">
                    <label class="myavana-label" for="myavana-auth-reset-password"><?php esc_html_e('New Password', 'myavana-hair-journey-next'); ?></label>
                    <input type="password" class="myavana-input" id="myavana-auth-reset-password" name="password" autocomplete="new-password" minlength="8" required>
                    <div class="myavana-password-meter" id="myavana-reset-password-meter" aria-live="polite">
                        <div class="myavana-password-meter-bar"><span id="myavana-reset-password-meter-fill"></span></div>
                        <ul class="myavana-password-meter-rules">
                            <li data-rule="length"><?php esc_html_e('At least 8 characters', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="upper"><?php esc_html_e('One uppercase letter', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="lower"><?php esc_html_e('One lowercase letter', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="number"><?php esc_html_e('One number', 'myavana-hair-journey-next'); ?></li>
                            <li data-rule="special"><?php esc_html_e('One special character (!@#$%^&*)', 'myavana-hair-journey-next'); ?></li>
                        </ul>
                    </div>
                </div>

                <button type="submit" class="myavana-btn myavana-btn-primary myavana-btn-block myavana-btn-lg">
                    <?php esc_html_e('Set New Password', 'myavana-hair-journey-next'); ?>
                </button>
            </form>
        </div>
    </div>
</section>
