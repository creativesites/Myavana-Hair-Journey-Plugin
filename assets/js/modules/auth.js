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

    const PANEL_IDS = {
        signin: 'myavana-auth-signin-panel',
        signup: 'myavana-auth-signup-panel',
        forgot: 'myavana-auth-forgot-panel',
        reset: 'myavana-auth-reset-panel',
    };

    function init() {
        authView = document.querySelector('#view-auth');
        if (!authView) return;

        discoveryView = document.querySelector('#view-discovery');

        bindOpenTriggers();
        bindTabToggle();
        bindForgotLinks();
        bindForms();
        initGoogle();
        showVerificationStatusFromUrl();

        // A guest who followed the "Reset My Password" email link straight
        // in (no "Get Started" click first) still needs the auth view
        // opened and the reset panel shown, not just present-but-hidden.
        if (authView.getAttribute('data-initial-mode') === 'reset') {
            open('reset');
        }
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
        const home = document.querySelector('#view-home');
        if (home) {
            home.style.display = 'none';
            home.classList.remove('active');
        }
        if (discoveryView) {
            discoveryView.style.display = 'none';
            discoveryView.classList.remove('active');
        }
        authView.style.display = 'block';
        authView.classList.add('active');
        switchTab(PANEL_IDS[mode] ? mode : 'signin');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function bindTabToggle() {
        const signinTab = document.querySelector('#myavana-auth-tab-signin');
        const signupTab = document.querySelector('#myavana-auth-tab-signup');

        if (signinTab) signinTab.addEventListener('click', () => switchTab('signin'));
        if (signupTab) signupTab.addEventListener('click', () => switchTab('signup'));
    }

    function bindForgotLinks() {
        document.querySelector('#myavana-auth-open-forgot')?.addEventListener('click', () => switchTab('forgot'));
        document.querySelector('#myavana-auth-forgot-back')?.addEventListener('click', () => switchTab('signin'));
    }

    // mode is one of PANEL_IDS's keys: signin, signup, forgot, reset.
    function switchTab(mode) {
        const isMainTab = mode === 'signin' || mode === 'signup';
        const mainToggle = document.querySelector('#myavana-auth-main-toggle');
        if (mainToggle) mainToggle.style.display = isMainTab ? '' : 'none';

        document.querySelector('#myavana-auth-tab-signin')?.classList.toggle('active', mode === 'signin');
        document.querySelector('#myavana-auth-tab-signup')?.classList.toggle('active', mode === 'signup');
        document.querySelector('#myavana-auth-tab-signin')?.setAttribute('aria-selected', String(mode === 'signin'));
        document.querySelector('#myavana-auth-tab-signup')?.setAttribute('aria-selected', String(mode === 'signup'));

        Object.entries(PANEL_IDS).forEach(([key, id]) => {
            const panel = document.getElementById(id);
            if (!panel) return;
            const isActive = key === mode;
            panel.style.display = isActive ? 'block' : 'none';
            panel.classList.toggle('active', isActive);
        });

        clearMessage();
    }

    function bindForms() {
        const signinForm = document.querySelector('#myavana-auth-signin-panel');
        const signupForm = document.querySelector('#myavana-auth-signup-panel');
        const forgotForm = document.querySelector('#myavana-auth-forgot-panel');
        const resetForm = document.querySelector('#myavana-auth-reset-panel');

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

        if (forgotForm) {
            forgotForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitForgotPassword(forgotForm);
            });
        }

        if (resetForm) {
            resetForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitResetPassword(resetForm);
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

    async function submitForgotPassword(form) {
        clearMessage();
        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        try {
            const data = await MyavanaNext.API.post('auth/forgot-password', {
                login: form.querySelector('#myavana-auth-forgot-email').value.trim(),
            });
            showMessage(data.message || "If an account exists for that email, we've sent a password reset link.", 'success');
            form.reset();
        } catch (err) {
            showMessage(err.message || 'Unable to send a reset link right now. Please try again.', 'error');
        } finally {
            setLoading(btn, false);
        }
    }

    async function submitResetPassword(form) {
        clearMessage();

        const password = form.querySelector('#myavana-auth-reset-password').value;
        const confirm = form.querySelector('#myavana-auth-reset-password-confirm').value;
        if (password !== confirm) {
            showMessage("Those passwords don't match. Please try again.", 'error');
            return;
        }

        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        try {
            const data = await MyavanaNext.API.post('auth/reset-password', {
                login: form.querySelector('#myavana-auth-reset-login').value,
                key: form.querySelector('#myavana-auth-reset-key').value,
                password,
            });
            onAuthSuccess(data.message);
        } catch (err) {
            showMessage(err.message || 'Unable to reset your password. The link may have expired — please request a new one.', 'error');
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
