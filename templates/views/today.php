<?php
/**
 * Today — the app's center of gravity: one routine to finish, one
 * AI-generated insight, quick actions, and a light-touch week/goals rail.
 *
 * Ported from the "Today" Claude Design mockup, with the fabricated
 * weather widget replaced by the real InsightEngine, and "picked for you"
 * products replaced by real products already in the member's routines.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$currentUser = wp_get_current_user();
$avatarUrl = get_avatar_url($currentUser->ID, ['size' => 92]);
?>
<section class="myavana-next-view active" id="view-today" aria-label="<?php esc_attr_e('Today', 'myavana-hair-journey-next'); ?>">

    <div class="myavana-today-header">
        <div>
            <p class="myavana-eyebrow" id="today-date-line"></p>
            <h1 class="today-greeting-text"><?php esc_html_e('Welcome back', 'myavana-hair-journey-next'); ?></h1>
            <p class="today-greeting-subtext"><?php esc_html_e("Let's take care of your hair today.", 'myavana-hair-journey-next'); ?></p>
        </div>
        <div class="myavana-today-identity">
            <div class="myavana-today-identity-text">
                <strong><?php echo esc_html($currentUser->display_name ?: $currentUser->user_login); ?></strong>
                <span id="today-day-count"></span>
            </div>
            <img src="<?php echo esc_url($avatarUrl); ?>" alt="" class="myavana-today-avatar" />
        </div>
    </div>

    <div class="myavana-today-quick-row">
        <button type="button" class="myavana-btn myavana-btn-dark btn-open-smart-entry"><?php esc_html_e('+ Log an entry', 'myavana-hair-journey-next'); ?></button>
        <button type="button" class="myavana-btn myavana-btn-outline btn-open-kommunicate"><?php esc_html_e('Ask an expert', 'myavana-hair-journey-next'); ?></button>
    </div>

    <div class="myavana-today-layout">
        <div class="myavana-today-main">

            <!-- Today's Routine -->
            <section class="myavana-card myavana-today-routine-card" aria-labelledby="today-care-title">
                <div class="myavana-section-heading">
                    <div>
                        <p class="myavana-eyebrow"><?php esc_html_e("Today's routine", 'myavana-hair-journey-next'); ?></p>
                        <h2 id="today-care-title"><?php esc_html_e('A few small steps are enough', 'myavana-hair-journey-next'); ?></h2>
                    </div>
                    <span class="myavana-pill" id="today-checklist-percent" hidden></span>
                </div>
                <div id="today-checklist-items" class="myavana-today-checklist" aria-live="polite">
                    <div class="myavana-today-skeleton" aria-hidden="true">
                        <div class="myavana-today-skeleton-row"></div>
                        <div class="myavana-today-skeleton-row"></div>
                        <div class="myavana-today-skeleton-row"></div>
                    </div>
                </div>
            </section>

            <!-- MYAVANA Insight — AI-generated when a provider is configured (see
                 IntelligenceOrchestrator/GeminiProvider), otherwise the
                 rule-based InsightEngine fallback. Today never shows a raw
                 provider error; an insight is always present once this is visible. -->
            <section class="myavana-today-insight-card" id="today-insight-card" style="display:none;">
                <p class="myavana-today-insight-eyebrow"><?php esc_html_e('MYAVANA Insight', 'myavana-hair-journey-next'); ?></p>
                <h3 class="myavana-today-insight-title" id="today-insight-title"></h3>
                <p class="myavana-today-insight-observation" id="today-insight-observation"></p>
                <p class="myavana-today-insight-action" id="today-insight-action"></p>
                <button type="button" class="myavana-today-insight-why" id="today-insight-why-toggle" aria-expanded="false">
                    <?php esc_html_e('Why am I seeing this?', 'myavana-hair-journey-next'); ?>
                </button>
                <ul class="myavana-today-insight-signals" id="today-insight-signals" hidden></ul>
            </section>

            <!-- From your routines -->
            <section class="myavana-card" id="today-products-card" style="display:none;">
                <div class="myavana-section-heading">
                    <div>
                        <h2 style="font-size:15px;"><?php esc_html_e('From your routines', 'myavana-hair-journey-next'); ?></h2>
                        <p class="myavana-entry-hint" style="margin:3px 0 0;"><?php esc_html_e('Products already in your saved routines', 'myavana-hair-journey-next'); ?></p>
                    </div>
                </div>
                <div class="myavana-today-products-grid" id="today-products-grid"></div>
            </section>

            <!-- Latest progress -->
            <section class="myavana-card myavana-today-progress-card" aria-labelledby="today-progress-title">
                <div class="myavana-section-heading">
                    <div>
                        <p class="myavana-eyebrow"><?php esc_html_e('Your latest progress', 'myavana-hair-journey-next'); ?></p>
                        <h2 id="today-progress-title"><?php esc_html_e('Keep your story close', 'myavana-hair-journey-next'); ?></h2>
                    </div>
                </div>
                <div id="today-latest-entry" aria-live="polite"></div>
                <button type="button" class="myavana-text-action" id="today-open-timeline">
                    <?php esc_html_e('View my timeline', 'myavana-hair-journey-next'); ?> <span aria-hidden="true">→</span>
                </button>
            </section>
        </div>

        <aside class="myavana-today-sidebar">
            <div class="myavana-card">
                <div class="myavana-section-heading">
                    <h2 style="font-size:15px;"><?php esc_html_e('This week', 'myavana-hair-journey-next'); ?></h2>
                </div>
                <div class="myavana-today-week-strip" id="today-week-strip"></div>
                <div id="today-goals-list" class="myavana-today-goals-list"></div>
            </div>

            <div class="myavana-card" id="today-upcoming-card" style="display:none;">
                <h2 style="font-size:15px; margin-bottom:12px;"><?php esc_html_e('Coming up', 'myavana-hair-journey-next'); ?></h2>
                <div id="today-upcoming-list"></div>
            </div>

            <div class="myavana-today-memory-card" id="today-memory-card" style="display:none;">
                <div class="myavana-today-memory-bg" id="today-memory-bg"></div>
                <div class="myavana-today-memory-overlay"></div>
                <div class="myavana-today-memory-content">
                    <p class="myavana-today-memory-eyebrow"><?php esc_html_e('One year ago today', 'myavana-hair-journey-next'); ?></p>
                    <p id="today-memory-title"></p>
                    <button type="button" class="myavana-today-memory-link" id="today-memory-link"><?php esc_html_e('See that entry ›', 'myavana-hair-journey-next'); ?></button>
                </div>
            </div>

            <aside class="myavana-today-help" aria-label="Hair care support">
                <div>
                    <strong><?php esc_html_e('Need hair-care help?', 'myavana-hair-journey-next'); ?></strong>
                    <p><?php esc_html_e('Chat with the MYAVANA team whenever you need a little guidance.', 'myavana-hair-journey-next'); ?></p>
                </div>
                <button type="button" class="myavana-btn myavana-btn-outline btn-open-kommunicate">
                    <?php esc_html_e('Chat with us', 'myavana-hair-journey-next'); ?>
                </button>
            </aside>
        </aside>
    </div>
</section>
