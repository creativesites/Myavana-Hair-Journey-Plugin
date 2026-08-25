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
