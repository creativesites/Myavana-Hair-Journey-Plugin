<?php
/**
 * Uninstall MYAVANA Hair Journey Next
 *
 * Preserves user data and records by default to protect hair journey history.
 *
 * @package Myavana\Next
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Data is preserved to ensure compatibility with parallel fallback plugins.
