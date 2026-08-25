<?php
/**
 * Routines and Goals workspace.
 *
 * These are the complete, ported experiences—not simplified replacements.
 * They remain inside the Next app shell while their existing records,
 * interactions, templates, statistics, and tracking behaviour are retained.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="myavana-next-view myavana-next-legacy-workspace" id="view-routine" aria-label="Routines and goals" style="display:none;">
    <!-- On mobile this is the only way to reach Goals — the bottom tab bar
         only has a "Routine" slot, and this view hosts both panels. -->
    <div class="myavana-routine-subnav" role="tablist" aria-label="<?php esc_attr_e('Routines or Goals', 'myavana-hair-journey-next'); ?>">
        <button type="button" class="myavana-routine-subnav-btn active" data-routine-subtab="routine" role="tab" aria-selected="true">
            <?php esc_html_e('Routines', 'myavana-hair-journey-next'); ?>
        </button>
        <button type="button" class="myavana-routine-subnav-btn" data-routine-subtab="goals" role="tab" aria-selected="false">
            <?php esc_html_e('Goals', 'myavana-hair-journey-next'); ?>
        </button>
    </div>

    <div class="myavana-next-legacy-panel" data-next-legacy-panel="routine">
        <?php
        if (function_exists('myavana_routines_page_shortcode')) {
            echo myavana_routines_page_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </div>

    <div class="myavana-next-legacy-panel" data-next-legacy-panel="goals" hidden>
        <?php
        if (function_exists('myavana_goals_page_shortcode')) {
            echo myavana_goals_page_shortcode(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </div>
</section>
