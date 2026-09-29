<?php
/**
 * Full-width canvas for the team's admin portal page, so the portal isn't
 * squeezed into the theme's blog layout next to sidebar widgets.
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <?php wp_head(); ?>
    <style>body.myavana-portal-canvas{margin:0;background:#f6f1ee;}body.myavana-portal-canvas .myavana-portal-canvas-main{max-width:1440px;margin:0 auto;padding:24px 16px;}</style>
</head>
<body <?php body_class('myavana-portal-canvas'); ?>>
<?php wp_body_open(); ?>
<main class="myavana-portal-canvas-main">
    <?php echo do_shortcode('[myavana_admin_portal]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</main>
<?php wp_footer(); ?>
</body>
</html>
