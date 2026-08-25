window.MyavanaNext = window.MyavanaNext || {};

/*
 * The original routines/goals modules are intentionally retained. This small
 * controller only decides which complete ported workspace is visible inside
 * the Next shell; it never replaces or reduces their feature set.
 */
MyavanaNext.Routine = (function() {
    'use strict';
    let container = null;

    function init() {
        container = document.querySelector('#view-routine');
        if (container) selectTab('routine');
    }

    function refresh() {
        // Legacy modules own their own live data refresh and event handling.
        // Keep this method for the app-shell navigation contract.
    }

    function selectTab(tab) {
        if (!container) return;
        const active = tab === 'goals' ? 'goals' : 'routine';
        container.querySelectorAll('[data-next-legacy-panel]').forEach(panel => {
            panel.hidden = panel.dataset.nextLegacyPanel !== active;
        });
    }

    return { init, refresh, selectTab };
})();
