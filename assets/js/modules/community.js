/**
 * Community Tab Bridge Controller (Ported exact shortcode integration)
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Community = (function() {
    'use strict';

    function init() {
        // Legacy social-feed.js auto-initializes on DOM ready with window.myavanaCommunitySettings
    }

    function refresh() {
        if (window.MyavanaSocialFeed && typeof window.MyavanaSocialFeed.reload === 'function') {
            window.MyavanaSocialFeed.reload();
        }
    }

    return {
        init,
        refresh,
    };
})();
