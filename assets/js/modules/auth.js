/**
 * Auth Controller - Sign In / Sign Up full-page view + Google Identity
 * Services integration.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Auth = (function() {
    'use strict';

    let authView = null;
    let discoveryView = null;
    let googleInitialized = false;

    function init() {
        authView = document.querySelector('#view-auth');
        if (!authView) return;

        discoveryView = document.querySelector('#view-discovery');

        bindOpenTriggers();
        bindTabToggle();
        bindForms();
        initGoogle();
        showVerificationStatusFromUrl();
    }

    function bindOpenTriggers() {
        document.querySelectorAll('[data-open-auth]').forEach((el) => {
            el.addEventListener('click', (e) => {
                e.preventDefault();
                open(el.getAttribute('data-open-auth') || 'signin');
            });
        });
    }

    function open(mode) {
        if (discoveryView) {
            discoveryView.style.display = 'none';
            discoveryView.classList.remove('active');
        }
        authView.style.display = 'block';
        authView.classList.add('active');
        switchTab(mode === 'signup' ? 'signup' : 'signin');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function bindTabToggle() {
        const signinTab = document.querySelector('#myavana-auth-tab-signin');
        const signupTab = document.querySelector('#myavana-auth-tab-signup');

        if (signinTab) signinTab.addEventListener('click', () => switchTab('signin'));
        if (signupTab) signupTab.addEventListener('click', () => switchTab('signup'));
    }

    function switchTab(mode) {
        const isSignup = mode === 'signup';

        document.querySelector('#myavana-auth-tab-signin')?.classList.toggle('active', !isSignup);
        document.querySelector('#myavana-auth-tab-signup')?.classList.toggle('active', isSignup);
        document.querySelector('#myavana-auth-tab-signin')?.setAttribute('aria-selected', String(!isSignup));
        document.querySelector('#myavana-auth-tab-signup')?.setAttribute('aria-selected', String(isSignup));

        const signinPanel = document.querySelector('#myavana-auth-signin-panel');
        const signupPanel = document.querySelector('#myavana-auth-signup-panel');
        if (signinPanel) {
            signinPanel.style.display = isSignup ? 'none' : 'block';
            signinPanel.classList.toggle('active', !isSignup);
        }
        if (signupPanel) {
            signupPanel.style.display = isSignup ? 'block' : 'none';
            signupPanel.classList.toggle('active', isSignup);
        }

        clearMessage();
    }

    function bindForms() {
        const signinForm = document.querySelector('#myavana-auth-signin-panel');
        const signupForm = document.querySelector('#myavana-auth-signup-panel');

        if (signinForm) {
            signinForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitSignin(signinForm);
            });
        }

        if (signupForm) {
            signupForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitSignup(signupForm);
            });
        }
    }

    async function submitSignin(form) {
        clearMessage();
        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        try {
            const data = await MyavanaNext.API.post('auth/login', {
                login: form.querySelector('#myavana-auth-signin-login').value.trim(),
                password: form.querySelector('#myavana-auth-signin-password').value,
                remember: form.querySelector('#myavana-auth-remember').checked,
            });
            onAuthSuccess(data.message);
        } catch (err) {
            showMessage(err.message || 'Unable to sign in. Please try again.', 'error');
        } finally {
            setLoading(btn, false);
        }
    }

    async function submitSignup(form) {
        clearMessage();
        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        try {
            const data = await MyavanaNext.API.post('auth/register', {
                name: form.querySelector('#myavana-auth-signup-name').value.trim(),
                email: form.querySelector('#myavana-auth-signup-email').value.trim(),
                password: form.querySelector('#myavana-auth-signup-password').value,
                terms: form.querySelector('#myavana-auth-terms').checked,
            });
            onAuthSuccess(data.message);
        } catch (err) {
            showMessage(err.message || 'Unable to create your account. Please try again.', 'error');
        } finally {
            setLoading(btn, false);
        }
    }

    function onAuthSuccess(message) {
        showMessage(message || 'Success! Taking you in...', 'success');
        setTimeout(() => window.location.reload(), 900);
    }

    function setLoading(btn, isLoading) {
        if (!btn) return;
        btn.disabled = isLoading;
        btn.style.opacity = isLoading ? '0.7' : '';
    }

    function showMessage(text, type) {
        const el = document.querySelector('#myavana-auth-message');
        if (!el) return;
        el.textContent = text;
        el.className = 'myavana-auth-message is-' + type;
        el.style.display = 'block';
    }

    function clearMessage() {
        const el = document.querySelector('#myavana-auth-message');
        if (!el) return;
        el.style.display = 'none';
        el.textContent = '';
    }

    // =========================
    // GOOGLE SIGN-IN
    // =========================

    function initGoogle() {
        const settings = window.myavanaNextData || {};
        if (!settings.googleAuthEnabled || !settings.googleClientId) {
            return;
        }

        loadGoogleScript(() => {
            if (googleInitialized || !window.google?.accounts?.id) return;
            googleInitialized = true;

            window.google.accounts.id.initialize({
                client_id: settings.googleClientId,
                callback: handleGoogleCredential,
            });

            renderGoogleButton('myavana-google-signin-slot', 'signin_with');
            renderGoogleButton('myavana-google-signup-slot', 'signup_with');
        });
    }

    function renderGoogleButton(slotId, text) {
        const slot = document.getElementById(slotId);
        if (!slot || !window.google?.accounts?.id) return;

        window.google.accounts.id.renderButton(slot, {
            theme: 'outline',
            size: 'large',
            width: '100%',
            text: text,
            shape: 'pill',
        });
    }

    function loadGoogleScript(callback) {
        if (window.google?.accounts?.id) {
            callback();
            return;
        }

        const existing = document.querySelector('script[src="https://accounts.google.com/gsi/client"]');
        if (existing) {
            existing.addEventListener('load', callback);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        script.onload = callback;
        document.head.appendChild(script);
    }

    async function handleGoogleCredential(response) {
        const credential = response && response.credential;
        if (!credential) {
            showMessage('Google sign-in did not return a valid credential.', 'error');
            return;
        }

        try {
            const data = await MyavanaNext.API.post('auth/google', { credential });
            onAuthSuccess(data.message);
        } catch (err) {
            showMessage(err.message || 'Google sign-in failed. Please try again.', 'error');
        }
    }

    // =========================
    // EMAIL VERIFICATION TOAST
    // =========================

    function showVerificationStatusFromUrl() {
        const params = new URLSearchParams(window.location.search);
        if (!params.has('myavana_email_verified')) return;

        const verified = params.get('myavana_email_verified') === '1';
        if (MyavanaNext.API?.showToast) {
            MyavanaNext.API.showToast(
                verified ? 'Email confirmed! Thanks for verifying.' : 'That verification link is invalid or already used.',
                verified ? 'success' : 'error'
            );
        }

        params.delete('myavana_email_verified');
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        window.history.replaceState(null, '', newUrl);
    }

    return { init, open };
})();
