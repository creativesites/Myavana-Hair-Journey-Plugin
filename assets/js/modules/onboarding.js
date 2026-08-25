/**
 * Post-Signup Onboarding Wizard + Discovery texture-card preselection.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Onboarding = (function() {
    'use strict';

    const TOTAL_STEPS = 3;
    const PRESELECT_KEY = 'myavana_preselected_hair_family';

    let modal = null;
    let currentStep = 1;
    const selections = { hairType: '', porosity: '', density: '', length: '', concerns: [], goals: [] };

    function init() {
        bindDiscoveryTypeCards();

        modal = document.querySelector('#myavana-onboarding-wizard');
        if (!modal) return;

        bindTypeCards();
        bindSelects();
        bindChips();
        bindNav();

        const settings = window.myavanaNextData || {};
        if (settings.showOnboardingWizard) {
            applyPreselection();
            open();
        }
    }

    // =========================
    // DISCOVERY SCREEN PREVIEW
    // =========================

    /**
     * The logged-out Discovery screen's texture cards used to be purely
     * decorative — no click handler, nothing saved. Give the pick a real
     * destination: it seeds the real wizard's step 1 once the visitor
     * signs up, instead of asking the same question twice.
     */
    function bindDiscoveryTypeCards() {
        const grid = document.querySelector('#myavana-discovery-type-grid');
        if (!grid) return;

        grid.querySelectorAll('[data-hair-family]').forEach((card) => {
            card.addEventListener('click', () => {
                grid.querySelectorAll('[data-hair-family]').forEach((c) => c.classList.remove('selected'));
                card.classList.add('selected');
                try { sessionStorage.setItem(PRESELECT_KEY, card.dataset.hairFamily); } catch (e) { /* no-op */ }
            });
        });
    }

    function applyPreselection() {
        let family = '';
        try { family = sessionStorage.getItem(PRESELECT_KEY) || ''; } catch (e) { /* no-op */ }
        if (!family) return;

        // The Discovery card only captures a family (2/3/4); default to
        // that family's "B" (medium) subtype as a starting point the user
        // can immediately see and correct rather than a bare guess.
        const card = document.querySelector('#myavana-onboarding-type-grid [data-hair-type="' + family + 'B"]');
        if (card) selectHairType(card);
    }

    // =========================
    // WIZARD: STEP 1 — HAIR TYPE
    // =========================

    function bindTypeCards() {
        modal.querySelectorAll('#myavana-onboarding-type-grid [data-hair-type]').forEach((card) => {
            card.addEventListener('click', () => selectHairType(card));
        });
    }

    function selectHairType(card) {
        modal.querySelectorAll('#myavana-onboarding-type-grid [data-hair-type]').forEach((c) => c.classList.remove('active'));
        card.classList.add('active');
        selections.hairType = card.dataset.hairType;
    }

    // =========================
    // WIZARD: STEP 2 — POROSITY / DENSITY / LENGTH
    // =========================

    function bindSelects() {
        const map = { porosity: '#myavana-onboarding-porosity', density: '#myavana-onboarding-density', length: '#myavana-onboarding-length' };
        Object.keys(map).forEach((key) => {
            const el = modal.querySelector(map[key]);
            if (!el) return;
            el.addEventListener('change', () => { selections[key] = el.value; });
        });
    }

    // =========================
    // WIZARD: STEP 3 — CONCERNS / GOALS
    // =========================

    function bindChips() {
        bindChipGroup('#myavana-onboarding-concerns', 'concern', selections.concerns);
        bindChipGroup('#myavana-onboarding-goals', 'goal', selections.goals);
    }

    function bindChipGroup(containerId, dataAttr, targetArray) {
        const container = modal.querySelector(containerId);
        if (!container) return;

        container.querySelectorAll('[data-' + dataAttr + ']').forEach((chip) => {
            chip.addEventListener('click', () => {
                const value = chip.dataset[dataAttr];
                const index = targetArray.indexOf(value);
                if (index === -1) {
                    targetArray.push(value);
                    chip.classList.add('active');
                } else {
                    targetArray.splice(index, 1);
                    chip.classList.remove('active');
                }
            });
        });
    }

    // =========================
    // NAVIGATION
    // =========================

    function bindNav() {
        modal.querySelector('#myavana-onboarding-next')?.addEventListener('click', () => {
            if (currentStep < TOTAL_STEPS) {
                goToStep(currentStep + 1);
            } else {
                finish();
            }
        });

        modal.querySelector('#myavana-onboarding-back')?.addEventListener('click', () => {
            if (currentStep > 1) goToStep(currentStep - 1);
        });

        modal.querySelector('#myavana-onboarding-skip')?.addEventListener('click', () => skip());
    }

    function goToStep(step) {
        currentStep = step;

        modal.querySelectorAll('[data-onboarding-step]').forEach((el) => {
            el.classList.toggle('active', Number(el.dataset.onboardingStep) === step);
        });
        modal.querySelectorAll('[data-onboarding-bar]').forEach((el) => {
            const barStep = Number(el.dataset.onboardingBar);
            el.classList.toggle('done', barStep < step);
            el.classList.toggle('active', barStep <= step);
        });

        const label = modal.querySelector('#onboarding-step-label');
        if (label) label.textContent = 'Step ' + step + ' of ' + TOTAL_STEPS;

        const backBtn = modal.querySelector('#myavana-onboarding-back');
        if (backBtn) backBtn.style.visibility = step === 1 ? 'hidden' : 'visible';

        const nextBtn = modal.querySelector('#myavana-onboarding-next');
        if (nextBtn) nextBtn.textContent = step === TOTAL_STEPS ? 'Finish Setup' : 'Continue';
    }

    function open() {
        modal.style.display = 'flex';
        modal.classList.add('active');
        currentStep = 1;
        goToStep(1);
    }

    function close() {
        modal.classList.remove('active');
        window.setTimeout(() => { modal.style.display = 'none'; }, 250);
    }

    async function finish() {
        const nextBtn = modal.querySelector('#myavana-onboarding-next');
        if (nextBtn) nextBtn.disabled = true;

        try {
            await MyavanaNext.API.post('profile', {
                hairType: selections.hairType,
                porosity: selections.porosity,
                density: selections.density,
                length: selections.length,
                concerns: selections.concerns,
                goals: selections.goals,
            });
            await MyavanaNext.API.post('profile/onboarding', { status: 'completed' });
            MyavanaNext.API?.showToast?.("You're all set — welcome to your hair journey!", 'success');
        } catch (err) {
            MyavanaNext.API?.showToast?.('Saved what we could — you can finish this anytime from your profile.', 'info');
        } finally {
            if (nextBtn) nextBtn.disabled = false;
            close();
        }
    }

    async function skip() {
        close();
        try {
            await MyavanaNext.API.post('profile/onboarding', { status: 'skipped' });
        } catch (err) { /* not worth blocking the UI over */ }
    }

    return { init, open };
})();
