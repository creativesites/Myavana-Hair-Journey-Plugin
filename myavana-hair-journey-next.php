<?php
/**
 * Plugin Name: MYAVANA Hair Journey Next
 * Plugin URI: https://myavana.com
 * Description: The next-generation MYAVANA Hair Journey experience. Unified single-page application shell featuring Today Habit Hub, Two-Speed Smart Entry, Journey Workspace, Routine & Goals, Community, and transparent AI Hair Concierge.
 * Version: 3.2.3
 * Author: MYAVANA
 * Author URI: https://myavana.com
 * Text Domain: myavana-hair-journey-next
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 *
 * @package Myavana\Next
 */

namespace Myavana\Next;

if (!defined('ABSPATH')) {
    exit;
}

// Define Plugin Constants
define('MYAVANA_NEXT_VERSION', '3.2.3');
define('MYAVANA_NEXT_FILE', __FILE__);
define('MYAVANA_NEXT_PATH', plugin_dir_path(__FILE__));
define('MYAVANA_NEXT_URL', plugin_dir_url(__FILE__));
define('MYAVANA_NEXT_BASENAME', plugin_basename(__FILE__));

// Require Autoloader
require_once MYAVANA_NEXT_PATH . 'includes/Core/Autoloader.php';

// Register PSR-4 Autoloader
Core\Autoloader::register();

/**
 * Plugin Activation Callback
 */
function activate_myavana_next() {
    Core\Plugin::instance()->activate();
}
register_activation_hook(__FILE__, __NAMESPACE__ . '\\activate_myavana_next');

/**
 * Plugin Deactivation Callback
 */
function deactivate_myavana_next() {
    Core\Plugin::instance()->deactivate();
}
register_deactivation_hook(__FILE__, __NAMESPACE__ . '\\deactivate_myavana_next');

/**
 * Bootstrap Plugin
 */
add_action('plugins_loaded', function() {
    Core\Plugin::instance()->init();
});
