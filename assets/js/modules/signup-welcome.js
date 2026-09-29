/**
 * Post-signup welcome pop-up (name + hair goals), restored from the original
 * Hair Journey plugin. Markup: templates/components/signup-welcome.php.
 */
window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.SignupWelcome = (function() {
    'use strict';

    let overlay = null;
    let step = 1;
    const TOTAL_STEPS = 2;
    const goals = new Set();

    function init() {
        overlay = document.getElementById('myaSignupOverlay');
        if (!overlay || !(window.myavanaNextData && window.myavanaNextData.showSignupWelcome)) return;

        overlay.querySelectorAll('.mya-signup-chip').forEach((chip) => {
            chip.addEventListener('click', () => {
                const value = chip.dataset.value;
                const on = !goals.has(value);
                on ? goals.add(value) : goals.delete(value);
                chip.classList.toggle('selected', on);
                chip.setAttribute('aria-pressed', String(on));
            });
        });
        overlay.querySelector('#myaSignupNext').addEventListener('click', next);
        overlay.querySelector('#myaSignupBack').addEventListener('click', () => goTo(step - 1));
        overlay.querySelector('#myaSignupSkip').addEventListener('click', () => finish('skipped'));

        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
        window.setTimeout(() => overlay.querySelector('#mya-signup-name')?.focus(), 250);
    }

    function goTo(target) {
        step = Math.max(1, Math.min(TOTAL_STEPS, target));
        overlay.querySelectorAll('.mya-signup-step').forEach((el) => el.classList.toggle('active', Number(el.dataset.step) === step));
        overlay.querySelectorAll('.mya-signup-dot').forEach((dot) => {
            const n = Number(dot.dataset.step);
            dot.classList.toggle('active', n === step);
            dot.classList.toggle('completed', n < step);
        });
        overlay.querySelector('#myaSignupBack').style.visibility = step > 1 ? 'visible' : 'hidden';
        overlay.querySelector('#myaSignupNext').textContent = step === TOTAL_STEPS ? 'Start my journey' : 'Continue';
    }

    function next() {
        if (step < TOTAL_STEPS) {
            goTo(step + 1);
            return;
        }
        finish('completed');
    }

    async function finish(status) {
        const button = overlay.querySelector('#myaSignupNext');
        button.classList.add('loading');
        button.disabled = true;
        try {
            await MyavanaNext.API.post('profile/onboarding', {
                status,
                name: (overlay.querySelector('#mya-signup-name')?.value || '').trim(),
                goals: Array.from(goals),
            });
        } catch (e) {
            // Closing is still right: the pop-up must never trap a member.
        }
        // Reflect the name she just gave everywhere it's already on screen.
        const chosenName = (overlay.querySelector('#mya-signup-name')?.value || '').trim();
        if (chosenName) {
            document.querySelectorAll('.myavana-next-avatar-name, #profile-name').forEach((el) => { el.textContent = chosenName; });
        }
        if (MyavanaNext.Today && typeof MyavanaNext.Today.refresh === 'function') MyavanaNext.Today.refresh();
        overlay.classList.remove('show');
        document.body.style.overflow = '';
        if (status === 'completed' && MyavanaNext.Today) MyavanaNext.Today.refresh();
    }

    return { init };
})();
