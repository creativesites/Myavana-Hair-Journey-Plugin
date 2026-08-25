/**
 * Kommunicate Chatbot Adapter for MYAVANA Next
 *
 * Connects the Next UI help triggers to the site's existing Kommunicate chatbot integration.
 * NOTE: For future personalization and hair profile context handoff, this module
 * can pass user hair texture and porosity to Kommunicate via its user update API.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Kommunicate = (function() {
    'use strict';

    /**
     * Open the Kommunicate conversation widget
     */
    function open() {
        // Method 1: Check window.Kommunicate official API
        if (window.Kommunicate && typeof window.Kommunicate.launchConversation === 'function') {
            try {
                window.Kommunicate.launchConversation();
                return true;
            } catch (e) {
                console.warn('[Kommunicate] launchConversation error:', e);
            }
        }

        // Method 2: Check window.kommunicate function
        if (typeof window.kommunicate === 'function') {
            try {
                window.kommunicate('open');
                return true;
            } catch (e) {
                console.warn('[Kommunicate] kommunicate(open) error:', e);
            }
        }

        // Method 3: Trigger the real widget launcher button in DOM
        const launcherBtn = document.querySelector('#kommunicate-widget-iframe, .km-chat-widget-button, #chat-popup-widget-container, .km-custom-widget-launcher');
        if (launcherBtn) {
            launcherBtn.click();
            return true;
        }

        // Method 4: Check if iframe exists and try to trigger show
        const iframe = document.getElementById('kommunicate-widget-iframe');
        if (iframe) {
            iframe.style.display = 'block';
            iframe.contentWindow?.postMessage({ action: 'open' }, '*');
            return true;
        }

        // Fallback message if Kommunicate is not loaded yet or blocked
        if (MyavanaNext.API && typeof MyavanaNext.API.showToast === 'function') {
            MyavanaNext.API.showToast('Connecting to MYAVANA Hair-Care Support...', 'info');
        }

        return false;
    }

    return {
        open,
    };
})();
