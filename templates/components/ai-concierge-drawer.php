<?php
/**
 * AI Concierge Slide-out Drawer Component
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<!-- Drawer Backdrop -->
<div class="myavana-drawer-backdrop" id="myavana-ai-drawer-backdrop"></div>

<!-- Drawer Panel -->
<aside class="myavana-drawer" id="myavana-ai-drawer" aria-label="MYAVANA AI Hair Concierge">
    <div class="myavana-drawer-header">
        <div style="display:flex; align-items:center; gap:8px;">
            <span style="font-size:18px;">✨</span>
            <div>
                <h3 style="font-size:16px; margin:0;"><?php esc_html_e('AI Hair Concierge', 'myavana-hair-journey-next'); ?></h3>
                <span style="font-size:11px; color:var(--myavana-coral-dark); font-weight:600;"><?php esc_html_e('Personalized Hair Science', 'myavana-hair-journey-next'); ?></span>
            </div>
        </div>
        <button type="button" class="myavana-modal-close" id="ai-drawer-close-btn" style="position:static;" aria-label="<?php esc_attr_e('Close Concierge', 'myavana-hair-journey-next'); ?>">✕</button>
    </div>

    <div class="myavana-drawer-body" id="ai-drawer-chat-body">
        <div class="myavana-chat-msg bot">
            <div style="font-size:11px; font-weight:700; opacity:0.8; margin-bottom:4px;">✨ MYAVANA Concierge</div>
            <div>
                <?php esc_html_e('Hello! I am your MYAVANA Hair Science Concierge. Ask me anything about your curl pattern, moisture balancing, wash-day routines, or ingredient recommendations.', 'myavana-hair-journey-next'); ?>
            </div>
            <div style="font-size:10px; opacity:0.7; margin-top:6px; border-top:1px dashed rgba(0,0,0,0.15); padding-top:4px;">
                <?php esc_html_e('Evidence-Based Regimen Model', 'myavana-hair-journey-next'); ?>
            </div>
        </div>
    </div>

    <!-- Suggested Quick Prompts -->
    <div style="padding:8px 16px; background:var(--myavana-stone); border-top:1px solid var(--myavana-border); display:flex; gap:6px; overflow-x:auto;">
        <button type="button" class="myavana-pill ai-suggestion-chip" style="white-space:nowrap; cursor:pointer;">
            <?php esc_html_e('How to boost moisture retention?', 'myavana-hair-journey-next'); ?>
        </button>
        <button type="button" class="myavana-pill ai-suggestion-chip" style="white-space:nowrap; cursor:pointer;">
            <?php esc_html_e('Recommend wash day steps', 'myavana-hair-journey-next'); ?>
        </button>
        <button type="button" class="myavana-pill ai-suggestion-chip" style="white-space:nowrap; cursor:pointer;">
            <?php esc_html_e('Scalp circulation tips', 'myavana-hair-journey-next'); ?>
        </button>
    </div>

    <div class="myavana-drawer-footer">
        <form id="ai-drawer-form" style="display:flex; gap:8px;">
            <input type="text" class="myavana-input" id="ai-drawer-input" placeholder="<?php esc_attr_e('Ask about your hair...', 'myavana-hair-journey-next'); ?>" autocomplete="off" />
            <button type="submit" class="myavana-btn myavana-btn-primary" id="ai-drawer-send-btn" style="padding:10px 18px;">
                <span>➤</span>
            </button>
        </form>
    </div>
</aside>
