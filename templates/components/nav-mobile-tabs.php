<?php
/**
 * Mobile Bottom Navigation 5-Tab Bar with Central FAB
 *
 * @package Myavana\Next
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<nav class="myavana-next-mobile-nav" aria-label="Mobile Navigation">
    <!-- Tab 0: Home -->
    <a href="#home" class="myavana-next-tab-item" data-tab="home">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z" stroke-linecap="round" stroke-linejoin="round"/>
            <polyline points="9 22 9 12 15 12 15 22" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?php esc_html_e('Home', 'myavana-hair-journey-next'); ?></span>
    </a>

    <!-- Tab 1: Today -->
    <a href="#today" class="myavana-next-tab-item active" data-tab="today">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?php esc_html_e('Today', 'myavana-hair-journey-next'); ?></span>
    </a>

    <!-- Tab 2: Timeline -->
    <a href="#journey" class="myavana-next-tab-item" data-tab="journey">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <rect x="3" y="3" width="18" height="18" rx="4" />
            <circle cx="8.5" cy="8.5" r="1.5" />
            <polyline points="21 15 16 10 5 21" />
        </svg>
        <span><?php esc_html_e('Timeline', 'myavana-hair-journey-next'); ?></span>
    </a>

    <!-- Central Floating Action Button (FAB): Check-In -->
    <button type="button" class="myavana-next-tab-fab" aria-label="<?php esc_attr_e('Add Hair Update', 'myavana-hair-journey-next'); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <line x1="12" y1="5" x2="12" y2="19" stroke-linecap="round" />
            <line x1="5" y1="12" x2="19" y2="12" stroke-linecap="round" />
        </svg>
    </button>

    <!-- Tab 3: Routine -->
    <a href="#routine" class="myavana-next-tab-item" data-tab="routine">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M9 11l3 3L22 4" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span><?php esc_html_e('Routine', 'myavana-hair-journey-next'); ?></span>
    </a>

    <!-- Tab 4: Community -->
    <a href="#community" class="myavana-next-tab-item" data-tab="community">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 00-3-3.87" />
            <path d="M16 3.13a4 4 0 010 7.75" />
        </svg>
        <span><?php esc_html_e('Community', 'myavana-hair-journey-next'); ?></span>
    </a>
</nav>
