<?php
/**
 * Today — a personal, calm start to the member's day: who she is on this
 * journey, how her hair feels, her latest moment, and one note from MYAVANA.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}

$currentUser = wp_get_current_user();
$customAvatar = get_user_meta($currentUser->ID, 'myavana_custom_avatar_url', true);
$displayName = $currentUser->first_name ?: ($currentUser->display_name ?: $currentUser->user_login);
$initial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($displayName, 0, 1)) : strtoupper(substr($displayName, 0, 1));
?>
<section class="myavana-next-view active myavana-today" id="view-today" aria-label="<?php esc_attr_e('Today', 'myavana-hair-journey-next'); ?>">

    <header class="myavana-today-hero">
        <div class="myavana-today-hero-text">
            <p class="myavana-today-date" id="today-date-line"></p>
            <h1 class="today-greeting-text"><?php esc_html_e('Welcome back', 'myavana-hair-journey-next'); ?></h1>
            <p class="today-greeting-subtext"><?php esc_html_e("Let's take care of your hair today.", 'myavana-hair-journey-next'); ?></p>
            <p class="myavana-today-focus" id="today-focus" hidden></p>
            <div class="myavana-today-hero-actions">
                <button type="button" class="myavana-btn myavana-btn-dark btn-open-smart-entry"><?php esc_html_e("Log today's hair", 'myavana-hair-journey-next'); ?></button>
                <button type="button" class="myavana-btn myavana-today-btn-soft btn-open-mya"><?php esc_html_e('Ask Mya', 'myavana-hair-journey-next'); ?></button>
            </div>
        </div>
        <div class="myavana-today-portrait-wrap">
            <div class="myavana-today-portrait" id="today-portrait" data-initial="<?php echo esc_attr($initial); ?>">
                <?php if ($customAvatar) : ?>
                    <img src="<?php echo esc_url($customAvatar); ?>" alt="" />
                <?php else : ?>
                    <span class="myavana-today-portrait-initial" aria-hidden="true"><?php echo esc_html($initial); ?></span>
                <?php endif; ?>
            </div>
            <span class="myavana-today-daypill" id="today-day-count"></span>
        </div>
    </header>

    <div class="myavana-today-layout">
        <div class="myavana-today-main">

            <section class="myavana-today-feel" aria-labelledby="today-feel-title">
                <h2 id="today-feel-title"><?php esc_html_e('How does your hair feel today?', 'myavana-hair-journey-next'); ?></h2>
                <div class="myavana-today-feel-options">
                    <button type="button" data-feel="happy"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l1.9 5.1L19 10l-5.1 1.9L12 17l-1.9-5.1L5 10l5.1-1.9z"/><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"/></svg><?php esc_html_e('Great', 'myavana-hair-journey-next'); ?></button>
                    <button type="button" data-feel="neutral"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19c0-8 5-13 14-14-1 9-6 14-14 14z"/><path d="M5 19l8-8"/></svg><?php esc_html_e('Normal', 'myavana-hair-journey-next'); ?></button>
                    <button type="button" data-feel="dry"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5c3.5 4.2 6 7.6 6 10.5a6 6 0 01-12 0c0-2.9 2.5-6.3 6-10.5z"/><path d="M8 20L18 8"/></svg><?php esc_html_e('Dry', 'myavana-hair-journey-next'); ?></button>
                    <button type="button" data-feel="itchy"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20s-7-4.4-7-10a4 4 0 017-2.6A4 4 0 0119 10c0 5.6-7 10-7 10z"/></svg><?php esc_html_e('Sensitive', 'myavana-hair-journey-next'); ?></button>
                </div>
            </section>

            <?php if (\Myavana\Next\Core\LaunchScope::ROUTINES_ENABLED) : ?>
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
            <?php endif; ?>

            <!-- A note from MYAVANA: AI-generated when a provider is configured,
                 otherwise the rule-based InsightEngine fallback. -->
            <section class="myavana-today-insight-card" id="today-insight-card" style="display:none;">
                <p class="myavana-today-insight-eyebrow"><?php esc_html_e('A note for you', 'myavana-hair-journey-next'); ?></p>
                <h3 class="myavana-today-insight-title" id="today-insight-title"></h3>
                <p class="myavana-today-insight-observation" id="today-insight-observation"></p>
                <p class="myavana-today-insight-action" id="today-insight-action"></p>
                <button type="button" class="myavana-today-insight-why" id="today-insight-why-toggle" aria-expanded="false">
                    <?php esc_html_e('Why am I seeing this?', 'myavana-hair-journey-next'); ?>
                </button>
                <ul class="myavana-today-insight-signals" id="today-insight-signals" hidden></ul>
            </section>

            <?php if (\Myavana\Next\Core\LaunchScope::ROUTINES_ENABLED) : ?>
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
            <?php endif; ?>

            <section class="myavana-today-story" aria-labelledby="today-progress-title">
                <div class="myavana-today-section-head">
                    <div>
                        <p class="myavana-today-eyebrow"><?php esc_html_e('Your story', 'myavana-hair-journey-next'); ?></p>
                        <h2 id="today-progress-title"><?php esc_html_e('Your latest moment', 'myavana-hair-journey-next'); ?></h2>
                    </div>
                    <button type="button" class="myavana-text-action" id="today-open-timeline">
                        <?php esc_html_e('View my timeline', 'myavana-hair-journey-next'); ?> <span aria-hidden="true">→</span>
                    </button>
                </div>
                <div id="today-latest-entry" aria-live="polite">
                    <div class="myavana-today-skeleton" aria-hidden="true">
                        <div class="myavana-today-skeleton-row"></div>
                        <div class="myavana-today-skeleton-row"></div>
                    </div>
                </div>
            </section>
        </div>

        <aside class="myavana-today-sidebar">
            <section class="myavana-today-side-card">
                <p class="myavana-today-eyebrow"><?php esc_html_e('Your week', 'myavana-hair-journey-next'); ?></p>
                <div class="myavana-today-week-strip" id="today-week-strip"></div>
                <div id="today-goals-list" class="myavana-today-goals-list"></div>
            </section>

            <section class="myavana-today-side-card" id="today-upcoming-card" style="display:none;">
                <p class="myavana-today-eyebrow"><?php esc_html_e('Coming up', 'myavana-hair-journey-next'); ?></p>
                <div id="today-upcoming-list"></div>
            </section>

            <div class="myavana-today-memory-card" id="today-memory-card" style="display:none;">
                <div class="myavana-today-memory-bg" id="today-memory-bg"></div>
                <div class="myavana-today-memory-overlay"></div>
                <div class="myavana-today-memory-content">
                    <p class="myavana-today-memory-eyebrow"><?php esc_html_e('One year ago today', 'myavana-hair-journey-next'); ?></p>
                    <p id="today-memory-title"></p>
                    <button type="button" class="myavana-today-memory-link" id="today-memory-link"><?php esc_html_e('See that entry ›', 'myavana-hair-journey-next'); ?></button>
                </div>
            </div>

            <aside class="myavana-today-help" aria-label="<?php esc_attr_e('Hair care support', 'myavana-hair-journey-next'); ?>">
                <span class="myavana-today-help-mark" aria-hidden="true">M</span>
                <div>
                    <strong><?php esc_html_e('Mya is here for you', 'myavana-hair-journey-next'); ?></strong>
                    <p><?php esc_html_e('Ask anything about your hair, any time.', 'myavana-hair-journey-next'); ?></p>
                </div>
                <button type="button" class="myavana-btn myavana-today-btn-soft btn-open-mya"><?php esc_html_e('Chat', 'myavana-hair-journey-next'); ?></button>
            </aside>
        </aside>
    </div>
</section>
