<?php
/**
 * My Hair Timeline — Timeline / Story view
 *
 * Ported from the "My Hair Timeline" Claude Design mockup. Static shell
 * here; assets/js/modules/journey.js fetches /journal/workspace and renders
 * the dynamic sections (timeline groups, sparkline, goals/routines rails,
 * story slides).
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$currentUser = wp_get_current_user();
$avatarUrl = get_avatar_url($currentUser->ID, ['size' => 104]);
?>
<section class="myavana-next-view" id="view-journey" aria-label="<?php esc_attr_e('My Hair Timeline', 'myavana-hair-journey-next'); ?>" style="display:none;">

    <div class="myavana-journey-header">
        <div>
            <p class="myavana-eyebrow"><?php esc_html_e('MYAVANA', 'myavana-hair-journey-next'); ?></p>
            <h1><?php esc_html_e('My Hair Timeline', 'myavana-hair-journey-next'); ?></h1>
            <p class="myavana-journey-subtitle" id="journey-subtitle"></p>
        </div>
        <div class="myavana-journey-identity">
            <div class="myavana-journey-identity-text">
                <strong><?php echo esc_html($currentUser->display_name ?: $currentUser->user_login); ?></strong>
                <span id="journey-hair-type"></span>
            </div>
            <img src="<?php echo esc_url($avatarUrl); ?>" alt="" class="myavana-journey-avatar" />
        </div>
    </div>

    <div class="myavana-journey-stats" id="journey-stats"></div>

    <div class="myavana-journey-toolbar">
        <div class="myavana-auth-toggle" role="tablist">
            <button type="button" class="myavana-auth-toggle-btn active" id="journey-tab-timeline" role="tab" aria-selected="true">
                <?php esc_html_e('Timeline', 'myavana-hair-journey-next'); ?>
            </button>
            <button type="button" class="myavana-auth-toggle-btn" id="journey-tab-story" role="tab" aria-selected="false">
                <?php esc_html_e('Story view', 'myavana-hair-journey-next'); ?>
            </button>
        </div>
        <button type="button" class="myavana-btn myavana-btn-dark btn-open-smart-entry">
            <?php esc_html_e('+ Add entry', 'myavana-hair-journey-next'); ?>
        </button>
    </div>

    <!-- TIMELINE PANE -->
    <div class="myavana-journey-pane active" id="journey-pane-timeline">
        <div class="myavana-journey-layout">
            <div class="myavana-journey-main">
                <div class="myavana-journey-filters" id="journey-filters"></div>
                <div class="myavana-timeline-rail" id="journey-timeline-rail">
                    <p class="myavana-entry-hint"><?php esc_html_e('Loading your timeline…', 'myavana-hair-journey-next'); ?></p>
                </div>
            </div>

            <aside class="myavana-journey-sidebar">
                <div class="myavana-card" id="journey-goals-card">
                    <h3><?php esc_html_e('Hair goals', 'myavana-hair-journey-next'); ?></h3>
                    <div id="journey-goals-list"></div>
                </div>

                <div class="myavana-card" id="journey-sparkline-card" style="display:none;">
                    <div class="myavana-journey-sparkline-head">
                        <h3><?php esc_html_e('Length growth', 'myavana-hair-journey-next'); ?></h3>
                        <span id="journey-sparkline-checks"></span>
                    </div>
                    <div class="myavana-journey-sparkline-value">
                        <strong id="journey-sparkline-current"></strong>
                        <span id="journey-sparkline-gain"></span>
                    </div>
                    <svg viewBox="-4 -6 262 92" class="myavana-journey-sparkline-svg" id="journey-sparkline-svg">
                        <path id="journey-sparkline-area" fill="var(--myavana-light-coral)"></path>
                        <polyline id="journey-sparkline-line" fill="none" stroke="var(--myavana-coral)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></polyline>
                        <circle id="journey-sparkline-dot" r="4.5" fill="var(--myavana-onyx)"></circle>
                    </svg>
                    <div class="myavana-journey-sparkline-range">
                        <span id="journey-sparkline-first"></span>
                        <span id="journey-sparkline-last"></span>
                    </div>
                </div>

                <div class="myavana-card" id="journey-routines-card">
                    <h3><?php esc_html_e('Routines', 'myavana-hair-journey-next'); ?></h3>
                    <div id="journey-routines-list"></div>
                </div>
            </aside>
        </div>
    </div>

    <!-- STORY PANE -->
    <div class="myavana-journey-pane" id="journey-pane-story" style="display:none;">
        <div class="myavana-journey-story-toolbar">
            <div class="myavana-auth-toggle" role="tablist">
                <button type="button" class="myavana-auth-toggle-btn active" id="story-mode-single"><?php esc_html_e('Single', 'myavana-hair-journey-next'); ?></button>
                <button type="button" class="myavana-auth-toggle-btn" id="story-mode-compare"><?php esc_html_e('Compare', 'myavana-hair-journey-next'); ?></button>
            </div>
            <span class="myavana-entry-hint" id="story-compare-hint" style="display:none;"></span>
        </div>

        <div class="myavana-journey-story-segments" id="story-segments"></div>

        <div class="myavana-journey-story-slide" id="story-slide-single">
            <div class="myavana-journey-story-bg" id="story-slide-bg"></div>
            <div class="myavana-journey-story-overlay"></div>
            <div class="myavana-journey-story-content">
                <div class="myavana-journey-story-meta">
                    <span class="myavana-journey-story-kicker" id="story-kicker"></span>
                    <span id="story-date"></span>
                    <span class="myavana-pill" id="story-mood" style="display:none;"></span>
                </div>
                <h2 id="story-headline"></h2>
                <p id="story-caption"></p>
                <div class="myavana-journey-story-actions">
                    <button type="button" class="myavana-btn myavana-btn-primary" id="story-view-entry"><?php esc_html_e('View entry', 'myavana-hair-journey-next'); ?></button>
                    <button type="button" class="myavana-btn myavana-btn-outline myavana-journey-story-outline" id="story-share"><?php esc_html_e('Share', 'myavana-hair-journey-next'); ?></button>
                </div>
            </div>
            <button type="button" class="myavana-journey-story-nav myavana-journey-story-prev" id="story-prev" aria-label="<?php esc_attr_e('Previous', 'myavana-hair-journey-next'); ?>">‹</button>
            <button type="button" class="myavana-journey-story-nav myavana-journey-story-next" id="story-next" aria-label="<?php esc_attr_e('Next', 'myavana-hair-journey-next'); ?>">›</button>
            <span class="myavana-journey-story-position" id="story-position"></span>
        </div>

        <div class="myavana-journey-story-compare" id="story-compare" style="display:none;">
            <div class="myavana-journey-story-compare-grid" id="story-compare-grid"></div>
        </div>

        <div class="myavana-journey-story-thumbs" id="story-thumbs"></div>
        <p class="myavana-entry-hint" style="text-align:center;"><?php esc_html_e('Use the arrow keys to move through your journey', 'myavana-hair-journey-next'); ?></p>
    </div>

    <!-- SHARE MODAL -->
    <div class="myavana-modal-backdrop" id="journey-share-modal" style="display:none;">
        <div class="myavana-modal myavana-journey-share-modal">
            <button type="button" class="myavana-modal-close" id="journey-share-close" aria-label="<?php esc_attr_e('Close', 'myavana-hair-journey-next'); ?>">✕</button>
            <p class="myavana-entry-step-label"><?php esc_html_e('Share', 'myavana-hair-journey-next'); ?></p>
            <h2 id="journey-share-title"></h2>

            <div class="myavana-journey-share-card" id="journey-share-preview"></div>

            <div class="myavana-form-group">
                <label class="myavana-label"><?php esc_html_e('Caption', 'myavana-hair-journey-next'); ?></label>
                <textarea class="myavana-textarea" id="journey-share-caption" rows="2"></textarea>
            </div>

            <div class="myavana-form-group">
                <label class="myavana-label"><?php esc_html_e('Send to', 'myavana-hair-journey-next'); ?></label>
                <div class="myavana-journey-share-destinations" id="journey-share-destinations"></div>
            </div>

            <p class="myavana-entry-hint"><?php esc_html_e('Entries stay private by default. Sharing never includes your scan data or measurements you leave out.', 'myavana-hair-journey-next'); ?></p>
        </div>
    </div>
</section>
