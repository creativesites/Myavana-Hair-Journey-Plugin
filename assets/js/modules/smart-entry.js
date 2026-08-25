/**
 * Global "New Entry" Composer — 3-step flow (type+photos / details / review)
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.SmartEntry = (function() {
    'use strict';

    const TOTAL_STEPS = 3;

    let modalBackdrop = null;
    let isSubmitting = false;
    let goalsLoaded = false;

    let state = defaultState();

    function defaultState() {
        return {
            step: 1,
            type: 'wash_day',
            photos: [],
            date: new Date().toISOString().slice(0, 10),
            mood: '',
            goalId: '',
            goals: [],
            hairLength: '',
            hairLengthPoint: '',
            title: '',
            changeDescription: '',
            notes: '',
            products: '',
            visibility: 'private',
        };
    }

    function init() {
        modalBackdrop = document.querySelector('#myavana-smart-entry-modal');
        if (!modalBackdrop) return;

        bindEvents();
    }

    function open() {
        if (!modalBackdrop) return;
        state.photos.forEach((p) => URL.revokeObjectURL(p.previewUrl));
        state = defaultState();

        renderTypeSelection();
        renderPhotoGrid();
        renderMood();
        renderVisibility();
        goToStep(1);

        if (!goalsLoaded) {
            loadGoals();
        }

        modalBackdrop.style.display = 'block';
        modalBackdrop.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function close() {
        if (!modalBackdrop) return;
        modalBackdrop.style.display = 'none';
        modalBackdrop.classList.remove('active');
        document.body.style.overflow = '';
    }

    function bindEvents() {
        modalBackdrop.querySelector('.myavana-modal-close')?.addEventListener('click', close);
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target === modalBackdrop) close();
        });

        // Type cards
        modalBackdrop.querySelectorAll('[data-entry-type]').forEach((btn) => {
            btn.addEventListener('click', () => {
                state.type = btn.getAttribute('data-entry-type');
                renderTypeSelection();
            });
        });

        // Photo picker
        const fileInput = modalBackdrop.querySelector('#smart-entry-file-input');
        modalBackdrop.addEventListener('click', (e) => {
            if (e.target.closest('#entry-photo-add')) fileInput?.click();
        });
        fileInput?.addEventListener('change', (e) => {
            const files = Array.from(e.target.files || []);
            fileInput.value = '';
            files.forEach(addPhoto);
        });
        modalBackdrop.addEventListener('click', (e) => {
            const removeBtn = e.target.closest('.myavana-entry-photo-remove');
            if (removeBtn) removePhoto(removeBtn.getAttribute('data-local-id'));

            const retryBtn = e.target.closest('.myavana-entry-photo-retry');
            if (retryBtn) retryPhoto(retryBtn.getAttribute('data-local-id'));
        });

        // Mood, goal, length-point, visibility pill groups (event delegation)
        modalBackdrop.addEventListener('click', (e) => {
            const moodBtn = e.target.closest('[data-mood]');
            if (moodBtn) { state.mood = moodBtn.getAttribute('data-mood'); renderMood(); }

            const goalBtn = e.target.closest('[data-goal-id]');
            if (goalBtn) { state.goalId = state.goalId === goalBtn.getAttribute('data-goal-id') ? '' : goalBtn.getAttribute('data-goal-id'); renderGoals(); }

            const pointBtn = e.target.closest('[data-length-point]');
            if (pointBtn) { state.hairLengthPoint = pointBtn.getAttribute('data-length-point'); renderLengthPoints(); }

            const visBtn = e.target.closest('[data-visibility]');
            if (visBtn) { state.visibility = visBtn.getAttribute('data-visibility'); renderVisibility(); }
        });

        // Navigation
        modalBackdrop.querySelector('#entry-back-btn')?.addEventListener('click', () => goToStep(state.step - 1));
        modalBackdrop.querySelector('#entry-continue-btn')?.addEventListener('click', () => {
            if (!validateStep(state.step)) return;
            goToStep(state.step + 1);
        });

        modalBackdrop.querySelector('#smart-entry-form')?.addEventListener('submit', handleSubmit);
    }

    // =========================
    // STEP NAVIGATION
    // =========================

    function goToStep(step) {
        step = Math.max(1, Math.min(TOTAL_STEPS, step));
        captureStepInputs(state.step);
        state.step = step;

        modalBackdrop.querySelectorAll('.myavana-entry-step').forEach((el) => {
            el.style.display = parseInt(el.getAttribute('data-step'), 10) === step ? 'block' : 'none';
        });

        modalBackdrop.querySelectorAll('[data-step-bar]').forEach((bar) => {
            const barStep = parseInt(bar.getAttribute('data-step-bar'), 10);
            bar.classList.toggle('active', barStep === step);
            bar.classList.toggle('done', barStep < step);
        });

        const label = modalBackdrop.querySelector('#entry-step-label');
        if (label) label.textContent = `Step ${step} of ${TOTAL_STEPS}`;

        modalBackdrop.querySelector('#entry-back-btn').style.visibility = step === 1 ? 'hidden' : 'visible';
        modalBackdrop.querySelector('#entry-continue-btn').style.display = step === TOTAL_STEPS ? 'none' : 'inline-flex';
        modalBackdrop.querySelector('#entry-submit-btn').style.display = step === TOTAL_STEPS ? 'inline-flex' : 'none';

        if (step === 2) {
            updateConditionalFields();
            prefillStep2Inputs();
        }
        if (step === 3) renderReview();
    }

    /** Pull raw <input>/<textarea> values into state before leaving a step. */
    function captureStepInputs(step) {
        if (step === 2) {
            state.date = modalBackdrop.querySelector('#entry-date')?.value || state.date;
            state.hairLength = modalBackdrop.querySelector('#entry-length')?.value || '';
            state.title = modalBackdrop.querySelector('#entry-title')?.value || '';
            state.changeDescription = modalBackdrop.querySelector('#entry-change')?.value || '';
            state.notes = modalBackdrop.querySelector('#entry-notes')?.value || '';
            state.products = modalBackdrop.querySelector('#entry-products')?.value || '';
        }
    }

    function prefillStep2Inputs() {
        const dateInput = modalBackdrop.querySelector('#entry-date');
        if (dateInput) dateInput.value = state.date;
        const lengthInput = modalBackdrop.querySelector('#entry-length');
        if (lengthInput) lengthInput.value = state.hairLength;
        const titleInput = modalBackdrop.querySelector('#entry-title');
        if (titleInput) titleInput.value = state.title;
        const changeInput = modalBackdrop.querySelector('#entry-change');
        if (changeInput) changeInput.value = state.changeDescription;
        const notesInput = modalBackdrop.querySelector('#entry-notes');
        if (notesInput) notesInput.value = state.notes;
        const productsInput = modalBackdrop.querySelector('#entry-products');
        if (productsInput) productsInput.value = state.products;
        renderLengthPoints();
        renderGoals();
    }

    function updateConditionalFields() {
        modalBackdrop.querySelectorAll('[data-type-field]').forEach((el) => {
            el.style.display = el.getAttribute('data-type-field') === state.type ? 'block' : 'none';
        });
    }

    function validateStep(step) {
        if (step === 2) {
            captureStepInputs(2);
            if (state.type === 'length_check' && (!state.hairLength || parseFloat(state.hairLength) <= 0)) {
                MyavanaNext.API.showToast('Add a measurement to continue.', 'error');
                return false;
            }
            if (state.type === 'milestone' && !state.title.trim()) {
                MyavanaNext.API.showToast('Give this milestone a name.', 'error');
                return false;
            }
            if (state.type === 'setback' && !state.changeDescription.trim()) {
                MyavanaNext.API.showToast('Say what you’re changing.', 'error');
                return false;
            }
        }
        return true;
    }

    // =========================
    // RENDER HELPERS
    // =========================

    function renderTypeSelection() {
        modalBackdrop.querySelectorAll('[data-entry-type]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-entry-type') === state.type);
        });

        const requiredMark = modalBackdrop.querySelector('#entry-title-required-mark');
        if (requiredMark) requiredMark.hidden = state.type !== 'milestone';

        const titleInput = modalBackdrop.querySelector('#entry-title');
        if (titleInput) {
            titleInput.placeholder = state.type === 'milestone'
                ? 'e.g. First silk press since the chop'
                : 'e.g. Wash day with new deep conditioner';
        }
    }

    function renderMood() {
        modalBackdrop.querySelectorAll('[data-mood]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-mood') === state.mood);
        });
    }

    function renderLengthPoints() {
        modalBackdrop.querySelectorAll('[data-length-point]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-length-point') === state.hairLengthPoint);
        });
    }

    function renderVisibility() {
        modalBackdrop.querySelectorAll('[data-visibility]').forEach((btn) => {
            btn.classList.toggle('active', btn.getAttribute('data-visibility') === state.visibility);
        });
    }

    async function loadGoals() {
        try {
            const data = await MyavanaNext.API.get('goals');
            // Some older goals were saved with a legacy `goal_key` instead
            // of `id` (GoalRepository still accepts either when matching).
            // Normalize here so every goal has a usable identifier.
            state.goals = ((data && data.active) || []).map((g) => ({ ...g, id: g.id || g.goal_key || '' })).filter((g) => g.id);
            goalsLoaded = true;
            renderGoals();
        } catch (err) {
            console.error('[SmartEntry] Failed to load goals', err);
            const group = modalBackdrop.querySelector('#entry-goal-group');
            if (group) group.innerHTML = '<span class="myavana-entry-hint">Couldn’t load your goals right now.</span>';
        }
    }

    function renderGoals() {
        const group = modalBackdrop.querySelector('#entry-goal-group');
        if (!group) return;

        if (!goalsLoaded) return;

        if (state.goals.length === 0) {
            group.innerHTML = '<span class="myavana-entry-hint">No active goals yet — set one on the Routine tab.</span>';
            return;
        }

        group.innerHTML = state.goals.map((g) => `
            <button type="button" class="myavana-entry-pill${g.id === state.goalId ? ' active' : ''}" data-goal-id="${escapeHtml(g.id)}">${escapeHtml(g.title)}</button>
        `).join('');
    }

    const MAX_PHOTO_BYTES = 15 * 1024 * 1024;
    const MAX_PHOTOS = 6;
    let photoSeq = 0;

    /**
     * Adds a photo the instant it's picked — a local preview (object URL)
     * renders immediately, independent of whether the background upload to
     * the server succeeds, fails, or is still in flight. This is the fix
     * for "no preview shows": previously the thumbnail only ever appeared
     * *after* a successful round trip to /journal/upload, so a slow
     * connection, a rejected file, or any server-side hiccup meant nothing
     * ever appeared at all with no indication why.
     */
    function addPhoto(file) {
        if (state.photos.length >= MAX_PHOTOS) {
            MyavanaNext.API.showToast(`You can add up to ${MAX_PHOTOS} photos per entry.`, 'error');
            return;
        }
        if (!file.type || !file.type.startsWith('image/')) {
            MyavanaNext.API.showToast(`"${file.name}" isn't an image file.`, 'error');
            return;
        }
        if (file.size > MAX_PHOTO_BYTES) {
            MyavanaNext.API.showToast(`"${file.name}" is over the 15MB limit.`, 'error');
            return;
        }

        const photo = {
            localId: 'p' + (++photoSeq),
            previewUrl: URL.createObjectURL(file),
            status: 'uploading', // 'uploading' | 'uploaded' | 'failed'
            url: null,
            attachmentId: null,
            error: '',
            file,
        };
        state.photos.push(photo);
        renderPhotoGrid();
        runUpload(photo);
    }

    async function runUpload(photo) {
        try {
            const result = await MyavanaNext.API.upload(photo.file);
            if (!result || !result.url) {
                throw new Error("Upload didn't return an image URL.");
            }

            // The photo may have been removed while this upload was still
            // in flight — nothing left to update.
            const current = state.photos.find((p) => p.localId === photo.localId);
            if (!current) return;

            current.status = 'uploaded';
            current.url = result.url;
            current.attachmentId = result.attachmentId;
        } catch (err) {
            console.error('[SmartEntry] Photo upload failed', err);
            const current = state.photos.find((p) => p.localId === photo.localId);
            if (!current) return;
            current.status = 'failed';
            current.error = err.message || 'Upload failed';
        } finally {
            renderPhotoGrid();
        }
    }

    function retryPhoto(localId) {
        const photo = state.photos.find((p) => p.localId === localId);
        if (!photo) return;
        photo.status = 'uploading';
        photo.error = '';
        renderPhotoGrid();
        runUpload(photo);
    }

    function removePhoto(localId) {
        const idx = state.photos.findIndex((p) => p.localId === localId);
        if (idx === -1) return;
        URL.revokeObjectURL(state.photos[idx].previewUrl);
        state.photos.splice(idx, 1);
        renderPhotoGrid();
    }

    function renderPhotoGrid() {
        const grid = modalBackdrop.querySelector('#entry-photo-grid');
        const countLabel = modalBackdrop.querySelector('#entry-photo-count');
        if (!grid) return;

        if (countLabel) countLabel.textContent = state.photos.length ? `${state.photos.length}/${MAX_PHOTOS}` : '';

        const thumbs = state.photos.map((p) => `
            <div class="myavana-entry-photo-thumb is-${p.status}">
                <img src="${escapeHtml(p.previewUrl)}" alt="" />
                ${p.status === 'uploading' ? '<div class="myavana-entry-photo-spinner" aria-label="Uploading"></div>' : ''}
                ${p.status === 'failed' ? `
                    <div class="myavana-entry-photo-error">
                        <span>${escapeHtml(p.error || "Couldn't upload")}</span>
                        <button type="button" class="myavana-entry-photo-retry" data-local-id="${p.localId}">Retry</button>
                    </div>` : ''}
                <button type="button" class="myavana-entry-photo-remove" data-local-id="${p.localId}" aria-label="Remove photo">✕</button>
            </div>
        `).join('');

        const addTile = state.photos.length < MAX_PHOTOS ? `
            <button type="button" class="myavana-entry-photo-add" id="entry-photo-add">
                <span class="myavana-entry-photo-add-icon" aria-hidden="true">+</span>
                <span class="myavana-entry-photo-add-label">${state.photos.length ? 'Add more' : 'Add photo'}</span>
            </button>` : '';

        grid.innerHTML = thumbs + addTile;
    }

    const TYPE_LABELS = { wash_day: 'Wash day', length_check: 'Length check', milestone: 'Milestone', setback: 'Setback' };

    function renderReview() {
        const el = modalBackdrop.querySelector('#entry-review');
        if (!el) return;

        const rows = [
            ['Type', TYPE_LABELS[state.type] || state.type],
            ['Date', state.date],
        ];
        if (state.title.trim()) {
            rows.push(['Title', state.title.trim()]);
        }
        if (state.type === 'length_check' && state.hairLength) {
            rows.push(['Length', `${state.hairLength}" (${state.hairLengthPoint || 'overall'})`]);
        }
        const uploadedCount = state.photos.filter((p) => p.status === 'uploaded').length;
        const pendingCount = state.photos.length - uploadedCount;
        rows.push(['Photos', state.photos.length
            ? `${uploadedCount} ready${pendingCount ? `, ${pendingCount} still uploading/failed` : ''}`
            : 'None']);
        if (state.goalId) {
            const goal = state.goals.find((g) => g.id === state.goalId);
            if (goal) rows.push(['Goal', goal.title]);
        }

        el.innerHTML = rows.map(([label, value]) => `
            <div class="myavana-entry-review-row"><span>${escapeHtml(label)}</span><span>${escapeHtml(value)}</span></div>
        `).join('');
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML;
    }

    // =========================
    // SUBMIT
    // =========================

    async function handleSubmit(e) {
        e.preventDefault();
        if (isSubmitting) return;

        captureStepInputs(2);

        if (state.type === 'milestone' && !state.title.trim()) {
            MyavanaNext.API.showToast('Give this milestone a name.', 'error');
            goToStep(2);
            return;
        }

        // Photos upload in the background from the moment they're picked —
        // don't let a save race ahead of one that's still in flight and
        // silently drop it from the entry. A failed upload doesn't block
        // saving (the member may have already retried and given up); an
        // in-progress one does, briefly, since it's about to succeed.
        if (state.photos.some((p) => p.status === 'uploading')) {
            MyavanaNext.API.showToast('Still uploading your photos — one moment…', 'info');
            return;
        }

        const submitBtn = modalBackdrop.querySelector('#entry-submit-btn');
        const uploadedPhotos = state.photos.filter((p) => p.status === 'uploaded');
        const failedCount = state.photos.length - uploadedPhotos.length;

        const payload = {
            entryType: state.type,
            title: state.title.trim(),
            date: state.date,
            mood: state.mood,
            goalId: state.goalId,
            notes: state.notes,
            productsUsed: state.products.split(',').map((s) => s.trim()).filter(Boolean),
            photos: uploadedPhotos.map((p) => ({ url: p.url, attachmentId: p.attachmentId })),
            visibility: state.visibility,
        };

        if (state.type === 'length_check') {
            payload.hairLength = state.hairLength;
            payload.hairLengthPoint = state.hairLengthPoint;
        }
        if (state.type === 'setback') {
            payload.changeDescription = state.changeDescription;
        }

        try {
            isSubmitting = true;
            if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Saving...'; }

            const result = await MyavanaNext.API.post('journal/entries', payload);

            MyavanaNext.API.showToast(result.message || 'Entry saved ✨', 'success');
            if (failedCount > 0) {
                MyavanaNext.API.showToast(`${failedCount} photo${failedCount > 1 ? 's' : ''} couldn't be uploaded and ${failedCount > 1 ? "weren't" : "wasn't"} included.`, 'error');
            }
            if (result.updatedGoal) {
                MyavanaNext.API.showToast(`${result.updatedGoal.title}: ${result.updatedGoal.progress}% progress`, 'info');
            }

            close();

            if (MyavanaNext.Today) MyavanaNext.Today.refresh();
            if (MyavanaNext.Journey) MyavanaNext.Journey.refresh();
        } catch (err) {
            console.error('[SmartEntry] Submit failed', err);
        } finally {
            isSubmitting = false;
            if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Save entry'; }
        }
    }

    return {
        init,
        open,
        close,
    };
})();
