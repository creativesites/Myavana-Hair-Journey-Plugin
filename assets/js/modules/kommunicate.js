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

    const MAX_RETRIES = 6;
    const RETRY_DELAY_MS = 500;

    /**
     * Open the Kommunicate conversation widget.
     *
     * The SDK loads async and can genuinely still be mid-load on a slow
     * mobile connection when someone taps "Ask an expert" — especially
     * right after the page first renders. A single immediate attempt would
     * fail every method below and fall straight to the toast, which reads
     * as "the button doesn't work." Retry for a few seconds before giving
     * up so a slow load isn't mistaken for a broken button.
     */
    function open(attempt) {
        attempt = attempt || 0;

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

        if (attempt < MAX_RETRIES) {
            if (attempt === 0 && MyavanaNext.API?.showToast) {
                MyavanaNext.API.showToast('Connecting to chat support…', 'info');
            }
            window.setTimeout(() => open(attempt + 1), RETRY_DELAY_MS);
            return false;
        }

        // Genuinely unavailable after ~3s of retrying — say so plainly
        // instead of leaving the first "Connecting…" toast as the last
        // word with nothing actually happening.
        if (MyavanaNext.API?.showToast) {
            MyavanaNext.API.showToast("Chat support isn't available right now. Please try again shortly.", 'error');
        }

        return false;
    }

    return {
        open: () => open(0),
    };
})();
