/**
 * MYAVANA Next - REST API Client & Toast Notification System
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.API = (function() {
    'use strict';

    // The only endpoints a logged-out visitor is expected to call. A 401/403
    // from any of these is a normal domain error (wrong password, weak
    // password, ...) — every other endpoint requires an existing session, so
    // a 401/403 there specifically means that session is gone.
    const PUBLIC_ENDPOINTS = ['auth/login', 'auth/register', 'auth/google', 'auth/forgot-password', 'auth/reset-password'];
    let sessionExpiredHandled = false;
    const REQUEST_TIMEOUT_MS = 60000;
    const UPLOAD_TIMEOUT_MS = 120000;
    const READ_RETRY_DELAYS_MS = [500, 1500, 3000];
    const RETRYABLE_STATUSES = [502, 503, 504];
    const OFFLINE_WAIT_MS = 10000;

    // Phones resuming from the background often report offline for a moment
    // while the radio wakes, so an offline retry waits for 'online' instead
    // of burning its attempts on a connection that isn't back yet.
    function waitBeforeRetry(ms) {
        return new Promise(resolve => {
            if (navigator.onLine !== false) {
                window.setTimeout(resolve, ms);
                return;
            }
            const done = () => {
                window.removeEventListener('online', done);
                window.clearTimeout(timer);
                resolve();
            };
            const timer = window.setTimeout(done, OFFLINE_WAIT_MS);
            window.addEventListener('online', done);
        });
    }

    function isPublicEndpoint(endpoint) {
        return PUBLIC_ENDPOINTS.some((p) => endpoint === p || endpoint.startsWith(p + '?'));
    }

    /**
     * A WordPress session can go stale mid-visit — the REST nonce expires,
     * or the login cookie itself does — while this already-rendered page
     * keeps looking fully logged in. Without this, every action just fails
     * with a generic "An error occurred" toast, repeatedly, with no way
     * back short of a manual refresh nobody's told to do. Reloading is the
     * simplest fix in this architecture: the server re-renders the actual
     * (logged-out) state and the visitor lands on sign-in naturally.
     */
    function handleSessionExpired() {
        if (sessionExpiredHandled) return;
        sessionExpiredHandled = true;
        showToast('Your session has expired — please sign in again.', 'error');
        window.setTimeout(() => window.location.reload(), 1600);
    }

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

        // The public auth endpoints are registered with permission_callback
        // __return_true and need no nonce. Sending one is actively harmful:
        // WordPress core's rest_cookie_check_errors() runs BEFORE any route's
        // own permission_callback, so a stale nonce rejects the request with
        // "Cookie check failed" (403) even on a route that requires no auth.
        //
        // The homepage is edge-cached for up to 31 days, so the nonce baked
        // into that HTML is routinely older than WordPress's ~24h nonce
        // lifetime. Every new visitor therefore received the same expired
        // nonce and could not register, log in, or use Google sign-in.
        //
        // isPublicEndpoint() already existed for the 401/403 retry path below;
        // it simply was never applied here.
        const headers = {
            'Accept': 'application/json',
            ...(isPublicEndpoint(endpoint) ? {} : { 'X-WP-Nonce': getNonce() }),
            ...(options.headers || {})
        };

        const isUpload = options.body instanceof FormData;
        if (options.body && !isUpload) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(options.body);
        }

        // A request that never settles would leave its view on a loading
        // skeleton forever; abort it so the caller's error state can show.
        const controller = new AbortController();
        const timer = window.setTimeout(() => controller.abort(), isUpload ? UPLOAD_TIMEOUT_MS : REQUEST_TIMEOUT_MS);

        // Reads are safe to repeat, so a dropped connection or a gateway
        // hiccup is retried quietly instead of surfacing as a load error.
        const isRead = (options.method || 'GET') === 'GET';

        try {
            let res;
            for (let attempt = 0; ; attempt++) {
                const canRetry = isRead && attempt < READ_RETRY_DELAYS_MS.length;
                try {
                    res = await fetch(url.toString(), {
                        ...options,
                        headers,
                        credentials: 'same-origin',
                        signal: controller.signal,
                    });
                } catch (networkErr) {
                    if (networkErr.name === 'AbortError') {
                        throw new Error('The request took too long. Please check your connection and try again.');
                    }
                    if (!canRetry) throw networkErr;
                    await waitBeforeRetry(READ_RETRY_DELAYS_MS[attempt]);
                    continue;
                }
                if (canRetry && RETRYABLE_STATUSES.includes(res.status)) {
                    await waitBeforeRetry(READ_RETRY_DELAYS_MS[attempt]);
                    continue;
                }
                break;
            }

            const json = await res.json();

            if (!res.ok || json.success === false) {
                console.warn('[MYAVANA API Response]', { status: res.status, endpoint, json });
                if ((res.status === 401 || res.status === 403) && !isPublicEndpoint(endpoint)) {
                    handleSessionExpired();
                    const expiredErr = new Error('Your session has expired — please sign in again.');
                    Object.assign(expiredErr, json, { sessionExpired: true });
                    throw expiredErr;
                }

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
            console.error('[MYAVANA API Error]', { endpoint, error: err.message, details: err });
            throw err;
        } finally {
            window.clearTimeout(timer);
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
