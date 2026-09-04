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
<section class="myavana-next-view active" id="view-discovery" aria-label="Welcome">
    <div class="myavana-discovery-wrap">
        <span class="myavana-pill myavana-pill-coral myavana-discovery-eyebrow">
            <?php esc_html_e('Personalized Hair Care', 'myavana-hair-journey-next'); ?>
        </span>

        <h1 class="myavana-discovery-title">
            <?php esc_html_e('Understand Your Hair Better and Track Your Care', 'myavana-hair-journey-next'); ?>
        </h1>

        <p class="myavana-discovery-subtitle">
            <?php esc_html_e('Record quick daily updates, track your wash-day routines, and see side-by-side progress photos of your hair over time.', 'myavana-hair-journey-next'); ?>
        </p>

        <!-- Fast Action Box -->
        <div class="myavana-card myavana-discovery-action-card">
            <h3 class="myavana-discovery-action-title"><?php esc_html_e('Select Your Hair Texture', 'myavana-hair-journey-next'); ?></h3>
            <p class="myavana-discovery-action-sub"><?php esc_html_e('Get started with care tips personalized for your curl pattern.', 'myavana-hair-journey-next'); ?></p>

            <div class="myavana-discovery-type-grid">
                <button type="button" class="discovery-type-card" data-type="2">
                    <span class="myavana-discovery-icon-circle" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12c2-4.5 4-4.5 6 0s4 4.5 6 0 4-4.5 6 0"/></svg>
                    </span>
                    <strong>Type 2 (Wavy)</strong>
                </button>
                <button type="button" class="discovery-type-card" data-type="3">
                    <span class="myavana-discovery-icon-circle" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3a9 9 0 1 0 6.36 15.36"/></svg>
                    </span>
                    <strong>Type 3 (Curly)</strong>
                </button>
                <button type="button" class="discovery-type-card" data-type="4">
                    <span class="myavana-discovery-icon-circle" aria-hidden="true">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4a4.5 4.5 0 1 1-4.5 4.5A2.75 2.75 0 1 1 10.25 11.25"/></svg>
                    </span>
                    <strong>Type 4 (Coily)</strong>
                </button>
            </div>

            <div class="myavana-discovery-action-cta">
                <a href="#auth" data-open-auth="signup" class="myavana-btn myavana-btn-primary myavana-btn-lg">
                    <?php esc_html_e('Get Started with MYAVANA →', 'myavana-hair-journey-next'); ?>
                </a>
            </div>

            <div class="myavana-discovery-trust-line">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                <span><?php esc_html_e('Private by Default • Easy Care Tracking', 'myavana-hair-journey-next'); ?></span>
            </div>
        </div>

        <!-- 3 Feature Pillars -->
        <div class="myavana-discovery-feature-grid">
            <div class="myavana-card myavana-discovery-feature-card">
                <span class="myavana-discovery-icon-circle myavana-discovery-icon-circle-lg" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" stroke="none"><path d="M13 2 3 14h7l-1 8 11-14h-7l1-6z"/></svg>
                </span>
                <h4><?php esc_html_e('Quick Updates & Wash-Days', 'myavana-hair-journey-next'); ?></h4>
                <p><?php esc_html_e('15-second daily check-ins, or detailed wash-day notes to learn what products work best.', 'myavana-hair-journey-next'); ?></p>
            </div>
            <div class="myavana-card myavana-discovery-feature-card">
                <span class="myavana-discovery-icon-circle myavana-discovery-icon-circle-lg" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 3 4 7l4 4M4 7h16M16 21l4-4-4-4M20 17H4"/></svg>
                </span>
                <h4><?php esc_html_e('Side-by-Side Photo Comparison', 'myavana-hair-journey-next'); ?></h4>
                <p><?php esc_html_e('Compare your uploaded timeline photos to see length retention and curl definition over time.', 'myavana-hair-journey-next'); ?></p>
            </div>
            <div class="myavana-card myavana-discovery-feature-card">
                <span class="myavana-discovery-icon-circle myavana-discovery-icon-circle-lg" aria-hidden="true">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                </span>
                <h4><?php esc_html_e('Specialist Hair Guidance', 'myavana-hair-journey-next'); ?></h4>
                <p><?php esc_html_e('Get answers about curl maintenance, porosity, and routine steps whenever you need help.', 'myavana-hair-journey-next'); ?></p>
            </div>
        </div>
    </div>
</section>
