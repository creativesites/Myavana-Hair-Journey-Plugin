<?php
/**
 * Unverified Email Banner
 *
 * Shown to a logged-in user whose account was created through this
 * plugin's own sign-up flow and hasn't confirmed their email yet. Hidden
 * by JS (auth.js) for anyone whose myavanaNextData.currentUser.emailVerified
 * is true, and dismissible for the rest of the session via sessionStorage.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="myavana-verify-banner" id="myavana-verify-banner" role="status" style="display:none;">
    <div class="myavana-next-container myavana-verify-banner-inner">
        <span class="myavana-verify-banner-text">
            <?php esc_html_e('Please confirm your email address so your hair journey and progress stay safe.', 'myavana-hair-journey-next'); ?>
        </span>
        <div class="myavana-verify-banner-actions">
            <button type="button" class="myavana-verify-banner-resend" id="myavana-verify-banner-resend">
                <?php esc_html_e('Resend email', 'myavana-hair-journey-next'); ?>
            </button>
            <button type="button" class="myavana-verify-banner-dismiss" id="myavana-verify-banner-dismiss" aria-label="<?php esc_attr_e('Dismiss', 'myavana-hair-journey-next'); ?>">&times;</button>
        </div>
    </div>
</div>
