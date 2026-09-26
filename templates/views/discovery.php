<?php
/**
 * Public First-Time Value Discovery View (for logged-out visitors)
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="myavana-welcome-modal" id="myavana-welcome-modal" hidden role="dialog" aria-modal="true" aria-labelledby="myavana-welcome-title">
    <div class="myavana-welcome-modal-card">
        <button type="button" class="myavana-welcome-close" data-close-welcome aria-label="<?php esc_attr_e('Continue browsing', 'myavana-hair-journey-next'); ?>">×</button>
        <span class="myavana-pill myavana-pill-coral"><?php esc_html_e('A more personal starting point', 'myavana-hair-journey-next'); ?></span>
        <h2 id="myavana-welcome-title"><?php esc_html_e('What would you like your hair care to do for you?', 'myavana-hair-journey-next'); ?></h2>
        <p><?php esc_html_e('Choose a focus and see how MYAVANA turns everyday care into a plan you can follow and measure.', 'myavana-hair-journey-next'); ?></p>
        <div class="myavana-welcome-goals" role="group" aria-label="<?php esc_attr_e('Choose a hair goal', 'myavana-hair-journey-next'); ?>">
            <button type="button" data-welcome-goal="Stronger, healthier hair">Stronger hair</button>
            <button type="button" data-welcome-goal="More moisture and definition">Moisture &amp; definition</button>
            <button type="button" data-welcome-goal="A consistent growth routine">Growth routine</button>
        </div>
        <div class="myavana-welcome-preview" aria-live="polite"><strong><?php esc_html_e('Your MYAVANA plan', 'myavana-hair-journey-next'); ?></strong><span><?php esc_html_e('A tailored routine, private photo timeline, and guidance that evolves with your entries.', 'myavana-hair-journey-next'); ?></span></div>
        <a href="#auth" data-open-auth="signup" class="myavana-btn myavana-btn-primary myavana-btn-lg"><?php esc_html_e('Create my free plan', 'myavana-hair-journey-next'); ?></a>
        <button type="button" class="myavana-welcome-later" data-close-welcome><?php esc_html_e('I’ll explore first', 'myavana-hair-journey-next'); ?></button>
    </div>
</div>
