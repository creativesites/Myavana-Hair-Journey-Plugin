/**
 * Post-Signup Onboarding Wizard + Discovery texture-card preselection.
 *
 * Welcome -> hair type -> porosity/density/length -> concerns/goals ->
 * starting routine -> completion, matching the product roadmap's onboarding
 * shape. "Skip for now" bails out from any step; every question step is
 * itself optional (Continue always works), so this never turns into a long
 * questionnaire.
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Onboarding = (function() {
    'use strict';

    // The step machine: 'welcome' and 'complete' are bookend screens with
    // no progress bar / step counter; '1'-'4' are the numbered questions.
    const STEP_ORDER = ['welcome', '1', '2', '3', '4', 'complete'];
    const LAST_QUESTION_STEP = '4';
    const PRESELECT_KEY = 'myavana_preselected_hair_family';

    const ROUTINE_PRESETS = {
        wash_day: { title: 'Wash Day Basics', category: 'Wash Day', frequency: 'Weekly', steps: ['Cleanse & clarify', 'Deep condition', 'Detangle gently', 'Style & seal ends'] },
        daily_refresh: { title: 'Daily Moisture Refresh', category: 'Daily Care', frequency: 'Daily', steps: ['Mist with water or leave-in', 'Seal with a light oil', 'Refresh edges/style'] },
        protective_style: { title: 'Protective Style Maintenance', category: 'Protective Style', frequency: 'Weekly', steps: ['Moisturize scalp', 'Refresh style edges', 'Check for tension or breakage'] },
    };

    let modal = null;
    let currentStep = 'welcome';
    const selections = { hairType: '', porosity: '', density: '', length: '', concerns: [], goals: [], routine: '' };

    function init() {
        bindDiscoveryTypeCards();

        modal = document.querySelector('#myavana-onboarding-wizard');
        if (!modal) return;

        bindTypeCards();
        bindSelects();
        bindChips();
        bindRoutinePresets();
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
    // WIZARD: STEP 4 — STARTING ROUTINE (optional)
    // =========================

    function bindRoutinePresets() {
        const container = modal.querySelector('#myavana-onboarding-routines');
        if (!container) return;

        container.querySelectorAll('[data-routine-preset]').forEach((card) => {
            card.addEventListener('click', () => {
                const value = card.dataset.routinePreset;
                const alreadySelected = selections.routine === value;
                container.querySelectorAll('[data-routine-preset]').forEach((c) => c.classList.remove('active'));
                // Clicking the already-selected preset deselects it — picking
                // a starting routine stays as optional as every other step.
                selections.routine = alreadySelected ? '' : value;
                if (!alreadySelected) card.classList.add('active');
            });
        });
    }

    // =========================
    // NAVIGATION
    // =========================

    function bindNav() {
        modal.querySelector('#myavana-onboarding-next')?.addEventListener('click', () => {
            if (currentStep === LAST_QUESTION_STEP) {
                finish();
            } else {
                goToStep(STEP_ORDER[STEP_ORDER.indexOf(currentStep) + 1]);
            }
        });

        modal.querySelector('#myavana-onboarding-back')?.addEventListener('click', () => {
            const prev = STEP_ORDER[STEP_ORDER.indexOf(currentStep) - 1];
            if (prev && prev !== 'welcome') goToStep(prev);
        });

        modal.querySelector('#myavana-onboarding-skip')?.addEventListener('click', () => skip());
        modal.querySelector('#myavana-onboarding-go-to-today')?.addEventListener('click', () => close());
    }

    function goToStep(step) {
        currentStep = step;
        const isQuestionStep = step !== 'welcome' && step !== 'complete';
        const stepNum = isQuestionStep ? Number(step) : 0;

        modal.querySelectorAll('[data-onboarding-step]').forEach((el) => {
            el.classList.toggle('active', el.dataset.onboardingStep === step);
        });
        modal.querySelectorAll('[data-onboarding-bar]').forEach((el) => {
            const barStep = Number(el.dataset.onboardingBar);
            el.classList.toggle('done', barStep < stepNum);
            el.classList.toggle('active', barStep <= stepNum);
        });

        const header = modal.querySelector('.myavana-onboarding-header');
        if (header) header.style.display = isQuestionStep ? 'block' : 'none';

        const footer = modal.querySelector('.myavana-onboarding-footer');
        if (footer) footer.style.display = step === 'complete' ? 'none' : 'flex';

        const skipBtn = modal.querySelector('#myavana-onboarding-skip');
        if (skipBtn) skipBtn.style.display = step === 'complete' ? 'none' : 'block';

        const label = modal.querySelector('#onboarding-step-label');
        if (label && isQuestionStep) label.textContent = 'Step ' + stepNum + ' of ' + LAST_QUESTION_STEP;

        const backBtn = modal.querySelector('#myavana-onboarding-back');
        if (backBtn) backBtn.style.visibility = (step === 'welcome' || step === '1') ? 'hidden' : 'visible';

        const nextBtn = modal.querySelector('#myavana-onboarding-next');
        if (nextBtn) {
            nextBtn.textContent = step === 'welcome' ? "Let's get started" : (step === LAST_QUESTION_STEP ? 'Finish Setup' : 'Continue');
        }
    }

    function open() {
        modal.style.display = 'flex';
        modal.classList.add('active');
        goToStep('welcome');
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

            if (selections.routine && ROUTINE_PRESETS[selections.routine]) {
                await MyavanaNext.API.post('routine', ROUTINE_PRESETS[selections.routine]);
            }

            await MyavanaNext.API.post('profile/onboarding', { status: 'completed' });
        } catch (err) {
            MyavanaNext.API?.showToast?.('Saved what we could — you can finish this anytime from your profile.', 'info');
        } finally {
            if (nextBtn) nextBtn.disabled = false;
            goToStep('complete');
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
