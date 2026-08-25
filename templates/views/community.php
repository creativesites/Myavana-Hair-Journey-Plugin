<?php
/**
 * Community View Partial - Renders myavana_community_feed_shortcode exactly as is
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<section class="myavana-next-view" id="view-community" aria-label="Community Feed" style="display:none;">
    <?php
    if (function_exists('myavana_community_feed_shortcode')) {
        echo myavana_community_feed_shortcode();
    }
    ?>
</section>
