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
    <div style="max-width:860px; margin:0 auto; text-align:center; padding:var(--space-2xl) 0;">
        <span class="myavana-pill myavana-pill-coral" style="font-size:13px; margin-bottom:16px;">
            ✨ <?php esc_html_e('Personalized Hair Care', 'myavana-hair-journey-next'); ?>
        </span>

        <h1 style="font-size:36px; line-height:1.2; margin-bottom:16px;">
            <?php esc_html_e('Understand Your Hair Better and Track Your Care', 'myavana-hair-journey-next'); ?>
        </h1>

        <p style="font-size:16px; color:var(--myavana-muted); max-width:640px; margin:0 auto var(--space-2xl); line-height:1.6;">
            <?php esc_html_e('Record quick daily updates, track your wash-day routines, and see side-by-side progress photos of your hair over time.', 'myavana-hair-journey-next'); ?>
        </p>

        <!-- Fast Action Box -->
        <div class="myavana-card" style="padding:var(--space-2xl); margin-bottom:var(--space-3xl); background:var(--myavana-white); box-shadow:var(--shadow-medium); text-align:left;">
            <h3 style="font-size:20px; margin-bottom:8px; text-align:center;"><?php esc_html_e('Select Your Hair Texture', 'myavana-hair-journey-next'); ?></h3>
            <p style="text-align:center; font-size:13px; margin-bottom:24px; color:var(--myavana-muted);"><?php esc_html_e('Get started with care tips personalized for your curl pattern.', 'myavana-hair-journey-next'); ?></p>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(140px, 1fr)); gap:12px; margin-bottom:24px;" id="myavana-discovery-type-grid">
                <button type="button" class="discovery-type-card" data-hair-family="2">
                    <span style="font-size:24px; display:block; margin-bottom:6px;">〰️</span>
                    <strong style="font-size:13px;">Type 2 (Wavy)</strong>
                </button>
                <button type="button" class="discovery-type-card" data-hair-family="3">
                    <span style="font-size:24px; display:block; margin-bottom:6px;">➰</span>
                    <strong style="font-size:13px;">Type 3 (Curly)</strong>
                </button>
                <button type="button" class="discovery-type-card" data-hair-family="4">
                    <span style="font-size:24px; display:block; margin-bottom:6px;">🌀</span>
                    <strong style="font-size:13px;">Type 4 (Coily)</strong>
                </button>
            </div>
            <p class="myavana-onboarding-hint" style="margin:-12px 0 24px; text-align:center;"><?php esc_html_e("We'll use this to start your profile once you sign up.", 'myavana-hair-journey-next'); ?></p>

            <div style="text-align:center;">
                <a href="#" data-open-auth="signup" class="myavana-btn myavana-btn-primary myavana-btn-lg">
                    <?php esc_html_e('Get Started with MYAVANA →', 'myavana-hair-journey-next'); ?>
                </a>
            </div>

            <div style="display:flex; justify-content:center; align-items:center; gap:8px; margin-top:16px; font-size:12px; color:var(--myavana-muted);">
                <span>🔒 <?php esc_html_e('Private by Default • Easy Care Tracking', 'myavana-hair-journey-next'); ?></span>
            </div>
        </div>

        <!-- 3 Feature Pillars -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:20px; text-align:left;">
            <div class="myavana-card">
                <span style="font-size:28px; display:block; margin-bottom:12px;">⚡</span>
                <h4 style="margin-bottom:6px;"><?php esc_html_e('Quick Updates & Wash-Days', 'myavana-hair-journey-next'); ?></h4>
                <p style="font-size:13px; color:var(--myavana-muted);"><?php esc_html_e('15-second daily check-ins, or detailed wash-day notes to learn what products work best.', 'myavana-hair-journey-next'); ?></p>
            </div>
            <div class="myavana-card">
                <span style="font-size:28px; display:block; margin-bottom:12px;">↔️</span>
                <h4 style="margin-bottom:6px;"><?php esc_html_e('Side-by-Side Photo Comparison', 'myavana-hair-journey-next'); ?></h4>
                <p style="font-size:13px; color:var(--myavana-muted);"><?php esc_html_e('Compare your uploaded timeline photos to see length retention and curl definition over time.', 'myavana-hair-journey-next'); ?></p>
            </div>
            <div class="myavana-card">
                <span style="font-size:28px; display:block; margin-bottom:12px;">💬</span>
                <h4 style="margin-bottom:6px;"><?php esc_html_e('Specialist Hair Guidance', 'myavana-hair-journey-next'); ?></h4>
                <p style="font-size:13px; color:var(--myavana-muted);"><?php esc_html_e('Get answers about curl maintenance, porosity, and routine steps whenever you need help.', 'myavana-hair-journey-next'); ?></p>
            </div>
        </div>
    </div>
</section>
