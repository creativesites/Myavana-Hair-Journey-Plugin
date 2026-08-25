/**
 * MYAVANA Next - REST API Client & Toast Notification System
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.API = (function() {
    'use strict';

    function getBaseUrl() {
        return (window.myavanaNextData && window.myavanaNextData.restUrl) || '/wp-json/myavana/v1/';
    }

    function getNonce() {
        return (window.myavanaNextData && window.myavanaNextData.nonce) || '';
    }

    async function request(endpoint, options = {}) {
        const url = new URL(endpoint, getBaseUrl());

        if (options.params) {
            Object.keys(options.params).forEach(k => {
                if (options.params[k] !== undefined && options.params[k] !== null) {
                    url.searchParams.append(k, options.params[k]);
                }
            });
        }

        const headers = {
            'X-WP-Nonce': getNonce(),
            'Accept': 'application/json',
            ...(options.headers || {})
        };

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        try {
            const res = await fetch(url.toString(), {
                ...options,
                headers,
                credentials: 'same-origin',
            });

            const json = await res.json();

            if (!res.ok || json.success === false) {
                const msg = json.message || 'An error occurred while processing your request.';
                showToast(msg, 'error');
                // Carry the rest of the error payload (field, showForgot,
                // attemptsRemaining, code, ...) onto the thrown Error so
                // callers can react to it without re-parsing the response.
                const err = new Error(msg);
                Object.assign(err, json);
                throw err;
            }

            return json.data;
        } catch (err) {
            console.error('[MYAVANA API Error]', err);
            throw err;
        }
    }

    function showToast(message, type = 'info') {
        let container = document.querySelector('.myavana-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'myavana-toast-container';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `myavana-toast myavana-toast-${type}`;
        toast.innerHTML = `
            <span>${type === 'error' ? '⚠️' : (type === 'success' ? '✨' : 'ℹ️')}</span>
            <span>${escapeHtml(message)}</span>
        `;

        container.appendChild(toast);

        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateY(-10px)';
            setTimeout(() => toast.remove(), 200);
        }, 3500);
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    return {
        get: (endpoint, params) => request(endpoint, { method: 'GET', params }),
        post: (endpoint, body) => request(endpoint, { method: 'POST', body }),
        put: (endpoint, body) => request(endpoint, { method: 'PUT', body }),
        delete: (endpoint) => request(endpoint, { method: 'DELETE' }),
        upload: async (file) => {
            const formData = new FormData();
            formData.append('file', file);
            return request('journal/upload', {
                method: 'POST',
                body: formData,
            });
        },
        showToast,
    };
})();
