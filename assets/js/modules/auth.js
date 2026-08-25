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
        initVerifyBanner();

        authView = document.querySelector('#view-auth');
        if (!authView) return;

        discoveryView = document.querySelector('#view-discovery');

        bindOpenTriggers();
        bindTabToggle();
        bindForms();
        bindForgotPasswordFlow();
        initPasswordMeter('myavana-auth-signup-password', 'myavana-password-meter');
        initPasswordMeter('myavana-auth-reset-password', 'myavana-reset-password-meter');
        initGoogle();
        showVerificationStatusFromUrl();
        openResetPanelFromUrl();
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
        switchTab(ALL_PANEL_IDS.includes(mode) ? mode : 'signin');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function bindTabToggle() {
        const signinTab = document.querySelector('#myavana-auth-tab-signin');
        const signupTab = document.querySelector('#myavana-auth-tab-signup');

        if (signinTab) signinTab.addEventListener('click', () => switchTab('signin'));
        if (signupTab) signupTab.addEventListener('click', () => switchTab('signup'));
    }

    const ALL_PANEL_IDS = ['signin', 'signup', 'forgot', 'reset'];

    function switchTab(mode) {
        const isSignup = mode === 'signup';
        const isTabbedMode = mode === 'signin' || mode === 'signup';

        const toggle = document.querySelector('.myavana-auth-toggle');
        if (toggle) toggle.style.display = isTabbedMode ? 'flex' : 'none';

        document.querySelector('#myavana-auth-tab-signin')?.classList.toggle('active', !isSignup);
        document.querySelector('#myavana-auth-tab-signup')?.classList.toggle('active', isSignup);
        document.querySelector('#myavana-auth-tab-signin')?.setAttribute('aria-selected', String(!isSignup));
        document.querySelector('#myavana-auth-tab-signup')?.setAttribute('aria-selected', String(isSignup));

        ALL_PANEL_IDS.forEach((id) => {
            const panel = document.querySelector('#myavana-auth-' + id + '-panel');
            if (!panel) return;
            const show = id === mode;
            panel.style.display = show ? 'block' : 'none';
            panel.classList.toggle('active', show);
        });

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
            if (err.showForgot) appendForgotPasswordPrompt();
        } finally {
            setLoading(btn, false);
        }
    }

    function appendForgotPasswordPrompt() {
        const el = document.querySelector('#myavana-auth-message');
        if (!el) return;
        const link = document.createElement('a');
        link.href = '#';
        link.className = 'myavana-auth-message-action';
        link.textContent = 'Reset your password →';
        link.addEventListener('click', (e) => {
            e.preventDefault();
            switchTab('forgot');
        });
        el.appendChild(link);
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

    // =========================
    // FORGOT / RESET PASSWORD
    // =========================

    function bindForgotPasswordFlow() {
        document.querySelector('#myavana-auth-forgot-link')?.addEventListener('click', (e) => {
            e.preventDefault();
            switchTab('forgot');
        });

        document.querySelector('#myavana-auth-back-to-signin')?.addEventListener('click', () => {
            switchTab('signin');
        });

        const forgotForm = document.querySelector('#myavana-auth-forgot-panel');
        if (forgotForm) {
            forgotForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitForgotPassword(forgotForm);
            });
        }

        const resetForm = document.querySelector('#myavana-auth-reset-panel');
        if (resetForm) {
            resetForm.addEventListener('submit', (e) => {
                e.preventDefault();
                submitResetPassword(resetForm);
            });
        }
    }

    async function submitForgotPassword(form) {
        clearMessage();
        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        try {
            const data = await MyavanaNext.API.post('auth/forgot-password', {
                email: form.querySelector('#myavana-auth-forgot-email').value.trim(),
            });
            showMessage(data.message || "If an account exists for that email, we've sent a reset link.", 'success');
            form.reset();
        } catch (err) {
            showMessage(err.message || 'Unable to send a reset link right now. Please try again.', 'error');
        } finally {
            setLoading(btn, false);
        }
    }

    async function submitResetPassword(form) {
        clearMessage();
        const btn = form.querySelector('button[type="submit"]');
        setLoading(btn, true);

        try {
            const data = await MyavanaNext.API.post('auth/reset-password', {
                userId: form.querySelector('#myavana-auth-reset-uid').value,
                token: form.querySelector('#myavana-auth-reset-token').value,
                password: form.querySelector('#myavana-auth-reset-password').value,
            });
            onAuthSuccess(data.message);
        } catch (err) {
            showMessage(err.message || 'Unable to reset your password. The link may have expired — request a new one.', 'error');
        } finally {
            setLoading(btn, false);
        }
    }

    /**
     * A reset-password email link lands here as
     * ?myavana_next_reset_password=1&uid=X&token=Y (see
     * Plugin::handleResetPasswordLink). Open straight into the reset panel
     * instead of the default sign-in tab when those params are present.
     */
    function openResetPanelFromUrl() {
        const params = new URLSearchParams(window.location.search);
        if (params.get('myavana_next_reset_password') !== '1') return;

        const uid = params.get('uid') || '';
        const token = params.get('token') || '';
        const uidInput = document.querySelector('#myavana-auth-reset-uid');
        const tokenInput = document.querySelector('#myavana-auth-reset-token');
        if (uidInput) uidInput.value = uid;
        if (tokenInput) tokenInput.value = token;

        open('reset');

        params.delete('myavana_next_reset_password');
        params.delete('uid');
        params.delete('token');
        const newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '') + window.location.hash;
        window.history.replaceState(null, '', newUrl);
    }

    // =========================
    // PASSWORD STRENGTH METER
    // =========================

    const PASSWORD_RULES = [
        ['length', (v) => v.length >= 8],
        ['upper', (v) => /[A-Z]/.test(v)],
        ['lower', (v) => /[a-z]/.test(v)],
        ['number', (v) => /[0-9]/.test(v)],
        ['special', (v) => /[!@#$%^&*(),.?":{}|<>]/.test(v)],
    ];

    /**
     * Mirrors AuthService::validatePasswordStrength() rule-for-rule so the
     * live checklist never disagrees with what the server will accept.
     */
    function initPasswordMeter(inputId, meterId) {
        const input = document.querySelector('#' + inputId);
        const meter = document.querySelector('#' + meterId);
        if (!input || !meter) return;

        const fill = meter.querySelector('.myavana-password-meter-bar span');

        input.addEventListener('input', () => {
            const value = input.value;
            let passed = 0;

            PASSWORD_RULES.forEach(([key, test]) => {
                const ok = test(value);
                if (ok) passed++;
                meter.querySelector('[data-rule="' + key + '"]')?.classList.toggle('is-met', ok);
            });

            meter.classList.toggle('is-visible', value.length > 0);
            meter.dataset.strength = value ? String(passed) : '0';
            if (fill) fill.style.width = (value ? Math.max(20, (passed / PASSWORD_RULES.length) * 100) : 0) + '%';
        });
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
            // Silent to end users by design (Google Sign-In is an optional
            // extra, not a broken feature) but not to whoever's debugging
            // "why is the button missing" — name the exact option that's unset.
            if (!settings.googleAuthEnabled) {
                console.warn('[MYAVANA] Google Sign-In is off — enable it under Settings → MYAVANA Next → Google Sign-In (myavana_next_google_auth_enabled).');
            } else {
                console.warn('[MYAVANA] Google Sign-In has no Client ID set — add one under Settings → MYAVANA Next → Google Sign-In (myavana_next_google_client_id).');
            }
            return;
        }

        loadGoogleScript(
            () => {
                if (googleInitialized || !window.google?.accounts?.id) {
                    console.warn('[MYAVANA] Google Identity Services script loaded but window.google.accounts.id is unavailable.');
                    showGoogleFallback();
                    return;
                }
                googleInitialized = true;

                window.google.accounts.id.initialize({
                    client_id: settings.googleClientId,
                    callback: handleGoogleCredential,
                });

                renderGoogleButton('myavana-google-signin-slot', 'signin_with');
                renderGoogleButton('myavana-google-signup-slot', 'signup_with');
            },
            () => {
                console.warn('[MYAVANA] Failed to load https://accounts.google.com/gsi/client — check network access / content blockers.');
                showGoogleFallback();
            }
        );
    }

    function renderGoogleButton(slotId, text) {
        const slot = document.getElementById(slotId);
        if (!slot || !window.google?.accounts?.id) return;

        try {
            window.google.accounts.id.renderButton(slot, {
                theme: 'outline',
                size: 'large',
                width: '100%',
                text: text,
                shape: 'pill',
            });
        } catch (err) {
            console.warn('[MYAVANA] Google renderButton threw:', err);
        }

        // Google's script swallows most failures internally (e.g. an
        // unauthorized JS origin) rather than throwing or rejecting, so the
        // only reliable signal is whether it actually populated the slot.
        window.setTimeout(() => {
            if (!slot.hasChildNodes()) {
                console.warn('[MYAVANA] Google did not render a button into #' + slotId + ' — likely an unauthorized JavaScript origin for this OAuth Client ID in Google Cloud Console.');
                showGoogleFallback(slot);
            }
        }, 2500);
    }

    function showGoogleFallback(slot) {
        const slots = slot ? [slot] : [
            document.getElementById('myavana-google-signin-slot'),
            document.getElementById('myavana-google-signup-slot'),
        ].filter(Boolean);

        slots.forEach((el) => {
            if (el.hasChildNodes() || el.dataset.fallbackShown) return;
            el.dataset.fallbackShown = '1';
            el.classList.add('myavana-google-auth-fallback');
            el.textContent = 'Google sign-in is temporarily unavailable — continue with email below.';
        });
    }

    function loadGoogleScript(onload, onerror) {
        if (window.google?.accounts?.id) {
            onload();
            return;
        }

        const existing = document.querySelector('script[src="https://accounts.google.com/gsi/client"]');
        if (existing) {
            existing.addEventListener('load', onload);
            existing.addEventListener('error', onerror);
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://accounts.google.com/gsi/client';
        script.async = true;
        script.defer = true;
        script.onload = onload;
        script.onerror = onerror;
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
    // UNVERIFIED EMAIL BANNER
    // =========================

    const VERIFY_BANNER_DISMISS_KEY = 'myavana_verify_banner_dismissed';

    function initVerifyBanner() {
        const settings = window.myavanaNextData || {};
        const banner = document.querySelector('#myavana-verify-banner');
        if (!banner || !settings.isLoggedIn || !settings.currentUser) return;
        if (settings.currentUser.emailVerified !== false) return;

        let dismissed = false;
        try {
            dismissed = sessionStorage.getItem(VERIFY_BANNER_DISMISS_KEY) === '1';
        } catch (e) { /* storage unavailable (private mode, etc.) — just don't remember the dismissal */ }
        if (dismissed) return;

        banner.style.display = 'block';

        document.querySelector('#myavana-verify-banner-dismiss')?.addEventListener('click', () => {
            banner.style.display = 'none';
            try { sessionStorage.setItem(VERIFY_BANNER_DISMISS_KEY, '1'); } catch (e) { /* no-op */ }
        });

        document.querySelector('#myavana-verify-banner-resend')?.addEventListener('click', async (e) => {
            const btn = e.currentTarget;
            setLoading(btn, true);
            try {
                const data = await MyavanaNext.API.post('auth/resend-verification', {});
                MyavanaNext.API?.showToast?.(data.message || 'Verification email sent!', 'success');
            } catch (err) {
                // MyavanaNext.API already surfaces a toast for the failure.
            } finally {
                setLoading(btn, false);
            }
        });
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
