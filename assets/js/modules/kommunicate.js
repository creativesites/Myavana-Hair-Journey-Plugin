/**
 * Kommunicate Chatbot Deprecated Shim for MYAVANA Next
 *
 * Fully decommissioned. Any legacy calls route directly to native Mya.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Kommunicate = (function() {
    'use strict';

    function open() {
        if (window.MyavanaWidget && typeof window.MyavanaWidget.open === 'function') {
            window.MyavanaWidget.open();
            return true;
        }
        if (window.MyavanaNext && window.MyavanaNext.Mya && typeof window.MyavanaNext.Mya.open === 'function') {
            window.MyavanaNext.Mya.open();
            return true;
        }
        return false;
    }

    return {
        open: open,
    };
})();
