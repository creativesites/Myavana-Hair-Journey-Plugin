/**
 * Transparent AI Hair Concierge Slide-out Drawer
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.AiConcierge = (function() {
    'use strict';

    let backdrop = null;
    let drawer = null;
    let isConsulting = false;

    function init() {
        backdrop = document.querySelector('#myavana-ai-drawer-backdrop');
        drawer = document.querySelector('#myavana-ai-drawer');
        if (!backdrop || !drawer) return;

        bindEvents();
    }

    function open() {
        if (!backdrop || !drawer) return;
        backdrop.classList.add('active');
        drawer.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        if (!backdrop || !drawer) return;
        backdrop.classList.remove('active');
        drawer.classList.remove('active');
        document.body.style.overflow = '';
    }

    function appendMessage(sender, text, meta = null) {
        const chatBody = drawer.querySelector('#ai-drawer-chat-body');
        if (!chatBody) return;

        const msg = document.createElement('div');
        msg.className = `myavana-chat-msg ${sender}`;
        msg.innerHTML = `
            <div style="font-size:11px; font-weight:700; opacity:0.8; margin-bottom:4px;">
                ${sender === 'user' ? 'You' : '✨ MYAVANA Concierge'}
            </div>
            <div>${escapeHtml(text)}</div>
            ${meta ? `
                <div style="font-size:10px; opacity:0.7; margin-top:6px; border-top:1px dashed rgba(0,0,0,0.15); padding-top:4px;">
                    ${escapeHtml(meta.source || 'AI Hair Science Engine')}
                </div>
            ` : ''}
        `;

        chatBody.appendChild(msg);
        chatBody.scrollTop = chatBody.scrollHeight;
    }

    async function sendConsultation(text) {
        if (!text || !text.trim() || isConsulting) return;

        const chatInput = drawer.querySelector('#ai-drawer-input');
        const sendBtn = drawer.querySelector('#ai-drawer-send-btn');

        appendMessage('user', text);
        if (chatInput) chatInput.value = '';

        try {
            isConsulting = true;
            if (sendBtn) sendBtn.disabled = true;

            const loadingId = 'ai-loading-bubble';
            const chatBody = drawer.querySelector('#ai-drawer-chat-body');
            const loadingEl = document.createElement('div');
            loadingEl.id = loadingId;
            loadingEl.className = 'myavana-chat-msg bot';
            loadingEl.innerHTML = '<span class="myavana-skeleton" style="display:inline-block; width:120px; height:16px;"></span>';
            chatBody.appendChild(loadingEl);
            chatBody.scrollTop = chatBody.scrollHeight;

            const result = await MyavanaNext.API.post('ai/consult', { message: text });
            document.getElementById(loadingId)?.remove();

            appendMessage('bot', result.reply, result);
        } catch (err) {
            console.error('[AI Consult Error]', err);
            appendMessage('bot', 'I apologize, but I encountered a momentary connection issue. Please try again shortly.');
        } finally {
            isConsulting = false;
            if (sendBtn) sendBtn.disabled = false;
        }
    }

    function bindEvents() {
        // Close Drawer
        drawer.querySelector('#ai-drawer-close-btn')?.addEventListener('click', close);
        backdrop.addEventListener('click', close);

        // Header Open Button
        document.querySelectorAll('.btn-open-ai-concierge').forEach(btn => {
            btn.addEventListener('click', open);
        });

        // Form Submit
        const form = drawer.querySelector('#ai-drawer-form');
        form?.addEventListener('submit', (e) => {
            e.preventDefault();
            const input = drawer.querySelector('#ai-drawer-input');
            sendConsultation(input?.value);
        });

        // Quick Suggestion Chips
        drawer.querySelectorAll('.ai-suggestion-chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                const text = e.currentTarget.textContent.trim();
                sendConsultation(text);
            });
        });
    }

    function escapeHtml(str) {
        if (!str) return '';
        const d = document.createElement('div');
        d.textContent = str;
        return d.innerHTML;
    }

    return {
        init,
        open,
        close,
        send: sendConsultation,
    };
})();
