<?php
/**
 * Desktop application footer. Mobile keeps its focused bottom-tab experience.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<footer class="myavana-next-footer" aria-label="MYAVANA site footer">
    <div class="myavana-next-container myavana-next-footer-grid">
        <div class="myavana-next-footer-brand">
            <img src="<?php echo esc_url(MYAVANA_NEXT_URL . 'assets/images/myavana-primary-logo.png'); ?>" alt="MYAVANA" class="myavana-next-footer-logo">
            <p><?php esc_html_e('Personalized hair care, made practical for your real life.', 'myavana-hair-journey-next'); ?></p>
        </div>

        <?php if (is_user_logged_in()) : ?>
            <nav class="myavana-next-footer-links" aria-label="Explore your journey">
                <span class="myavana-next-footer-label"><?php esc_html_e('Your journey', 'myavana-hair-journey-next'); ?></span>
                <a href="#today" data-tab="today" data-home-nav><?php esc_html_e('Today', 'myavana-hair-journey-next'); ?></a>
                <a href="#journey" data-tab="journey" data-home-nav><?php esc_html_e('Timeline', 'myavana-hair-journey-next'); ?></a>
                <a href="#routine" data-tab="routine" data-routine-panel="routine" data-home-nav><?php esc_html_e('Routines', 'myavana-hair-journey-next'); ?></a>
                <a href="#routine" data-tab="routine" data-routine-panel="goals" data-home-nav><?php esc_html_e('Goals', 'myavana-hair-journey-next'); ?></a>
                <a href="#community" data-tab="community" data-home-nav><?php esc_html_e('Community', 'myavana-hair-journey-next'); ?></a>
            </nav>
        <?php else : ?>
            <div class="myavana-next-footer-links">
                <span class="myavana-next-footer-label"><?php esc_html_e('Begin your journey', 'myavana-hair-journey-next'); ?></span>
                <a href="#auth" data-open-auth="signup"><?php esc_html_e('Create an account', 'myavana-hair-journey-next'); ?></a>
                <a href="#auth" data-open-auth="signin"><?php esc_html_e('Sign in', 'myavana-hair-journey-next'); ?></a>
            </div>
        <?php endif; ?>

        <div class="myavana-next-footer-support">
            <span class="myavana-next-footer-label"><?php esc_html_e('Need a hand?', 'myavana-hair-journey-next'); ?></span>
            <button type="button" class="myavana-next-footer-chat btn-open-mya btn-open-kommunicate" data-action="open-mya-chat"><?php esc_html_e('Chat with Mya', 'myavana-hair-journey-next'); ?> <span aria-hidden="true">↗</span></button>
            <div class="myavana-next-footer-legal">
                <a href="<?php echo esc_url(home_url('/privacy/')); ?>"><?php esc_html_e('Privacy', 'myavana-hair-journey-next'); ?></a>
                <a href="<?php echo esc_url(home_url('/terms/')); ?>"><?php esc_html_e('Terms', 'myavana-hair-journey-next'); ?></a>
            </div>
        </div>
    </div>
    <div class="myavana-next-footer-bottom"><div class="myavana-next-container">© <?php echo esc_html(wp_date('Y')); ?> MYAVANA. <?php esc_html_e('All rights reserved.', 'myavana-hair-journey-next'); ?></div></div>
</footer>
