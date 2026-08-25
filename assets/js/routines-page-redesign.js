document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const root = document.querySelector('.myavana-routines-v2-page') || document.body;
    if (!root) {
        return;
    }

    const state = {
        routines: [],
        byId: {},
        templates: [],
        activeFilter: 'all',
        query: '',
        currentDetailId: null,
        currentTemplateKey: null,
        session: {
            routineId: null,
            steps: [],
            done: [],
            timer: null,
            seconds: 0,
        },
    };

    const els = {
        search: document.getElementById('myavanaRv2Search'),
        filters: document.getElementById('myavanaRv2Filters'),
        grid: document.getElementById('myavanaRv2Grid'),
        todayLabel: document.getElementById('myavanaRv2TodayLabel'),
        todayBar: document.getElementById('myavanaRv2TodayBar'),
        todaySub: document.getElementById('myavanaRv2TodaySub'),
        overlay: document.getElementById('myavanaRv2Overlay'),
        drawer: document.getElementById('myavanaRv2Drawer'),
        drawerTabs: document.getElementById('myavanaRv2DrawerTabs'),
        detailTitle: document.getElementById('myavanaRv2DetailTitle'),
        detailDesc: document.getElementById('myavanaRv2DetailDesc'),
        detailFrequency: document.getElementById('myavanaRv2DetailFrequency'),
        detailStatus: document.getElementById('myavanaRv2DetailStatus'),
        detailMeta: document.getElementById('myavanaRv2DetailMeta'),
        detailGoalSection: document.getElementById('myavanaRv2GoalSection'),
        detailGoal: document.getElementById('myavanaRv2DetailGoal'),
        detailProducts: document.getElementById('myavanaRv2DetailProducts'),
        detailStats: document.getElementById('myavanaRv2DetailStats'),
        detailSteps: document.getElementById('myavanaRv2DetailSteps'),
        detailHistory: document.getElementById('myavanaRv2DetailHistory'),
        startSessionBtn: document.getElementById('myavanaRv2StartSessionBtn'),
        editBtn: document.getElementById('myavanaRv2EditBtn'),
        deleteBtn: document.getElementById('myavanaRv2DeleteBtn'),
        sessionWrap: document.getElementById('myavanaRv2Session'),
        sessionTitle: document.getElementById('myavanaRv2SessionTitle'),
        sessionSub: document.getElementById('myavanaRv2SessionSub'),
        sessionTimer: document.getElementById('myavanaRv2SessionTimer'),
        sessionBar: document.getElementById('myavanaRv2SessionBar'),
        sessionLabel: document.getElementById('myavanaRv2SessionLabel'),
        sessionSteps: document.getElementById('myavanaRv2SessionSteps'),
        sessionDoneBtn: document.getElementById('myavanaRv2SessionDoneBtn'),
        sessionLogEntryBtn: document.getElementById('myavanaRv2LogEntryBtn'),
        pickerWrap: document.getElementById('myavanaRv2Picker'),
        templatePreviewWrap: document.getElementById('myavanaRv2TemplatePreview'),
        templatePreviewKicker: document.getElementById('myavanaRv2TemplatePreviewKicker'),
        templatePreviewTitle: document.getElementById('myavanaRv2TemplatePreviewTitle'),
        templatePreviewDesc: document.getElementById('myavanaRv2TemplatePreviewDesc'),
        templatePreviewMeta: document.getElementById('myavanaRv2TemplatePreviewMeta'),
        templatePreviewSteps: document.getElementById('myavanaRv2TemplatePreviewSteps'),
        templatePreviewProducts: document.getElementById('myavanaRv2TemplatePreviewProducts'),
        templatePreviewToolsWrap: document.getElementById('myavanaRv2TemplatePreviewToolsWrap'),
        templatePreviewTools: document.getElementById('myavanaRv2TemplatePreviewTools'),
        templatePreviewOutcomeWrap: document.getElementById('myavanaRv2TemplatePreviewOutcomeWrap'),
        templatePreviewOutcome: document.getElementById('myavanaRv2TemplatePreviewOutcome'),
        templatePreviewNotesWrap: document.getElementById('myavanaRv2TemplatePreviewNotesWrap'),
        templatePreviewNotes: document.getElementById('myavanaRv2TemplatePreviewNotes'),
        templatePreviewUse: document.getElementById('myavanaRv2TemplatePreviewUse'),
    };

    function icon(name, cls) {
        const className = cls ? ' class="' + cls + '"' : '';
        return '<i data-lucide="' + name + '"' + className + '></i>';
    }

    function runLucide() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({
                attrs: {
                    'stroke-width': 1.9,
                },
            });
        }
    }

    function frequencyIconName(routine) {
        const group = routine.group || '';
        if (group === 'daily') return 'sun';
        if (group === 'monthly') return 'calendar';
        if (group === 'as_needed') return 'leaf';
        return 'calendar-days';
    }

    function parsePageData() {
        const node = document.getElementById('myavanaRoutinesV2Data');
        if (!node) {
            return {};
        }
        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            console.error('[Routines V2] Failed to parse page data', error);
            return {};
        }
    }

    function hydrate() {
        const pageData = parsePageData();
        state.routines = Array.isArray(pageData.routines) ? pageData.routines : [];
        state.templates = Array.isArray(pageData.templates) ? pageData.templates : [];
        state.byId = {};
        state.routines.forEach((routine) => {
            const id = Number(routine.id);
            if (!Number.isNaN(id)) {
                state.byId[id] = routine;
            }
        });
    }

    function frequencyBadgeClass(routine) {
        const group = routine.group || '';
        if (group === 'daily') return 'daily';
        if (group === 'monthly') return 'monthly';
        if (group === 'as_needed') return 'asneeded';
        return 'weekly';
    }

    function statusDotClass(routine) {
        if ((routine.status || 'active') === 'paused') return 'paused';
        return routine.completed_today ? 'done' : 'active';
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return 'Not completed yet';
        const date = new Date(dateStr + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return dateStr;
        return date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function applyFilters() {
        const cards = root.querySelectorAll('[data-rv2-card]');
        cards.forEach((card) => {
            const group = String(card.getAttribute('data-filter-group') || '');
            const status = String(card.getAttribute('data-filter-status') || 'active');
            const search = String(card.getAttribute('data-search') || '').toLowerCase();

            const passFilter = state.activeFilter === 'all'
                || (state.activeFilter === 'paused' ? status === 'paused' : group === state.activeFilter);
            const passQuery = !state.query || search.includes(state.query);

            card.style.display = passFilter && passQuery ? '' : 'none';
        });
    }

    function refreshTodaySummary() {
        const rows = root.querySelectorAll('#myavanaRv2TodayList .myavana-rv2-today-item');
        const total = rows.length;
        const completed = root.querySelectorAll('#myavanaRv2TodayList .myavana-rv2-check.done').length;
        const pct = total > 0 ? Math.round((completed / total) * 100) : 0;

        if (els.todayLabel) {
            els.todayLabel.textContent = completed + ' of ' + total + ' complete';
        }
        if (els.todayBar) {
            els.todayBar.style.width = pct + '%';
        }
        if (els.todaySub) {
            if (total === 0) {
                els.todaySub.textContent = 'Add routines to get your daily flow going.';
            } else if (completed === total) {
                els.todaySub.textContent = 'All done for today. Great consistency.';
            } else {
                const left = total - completed;
                els.todaySub.textContent = 'Keep going - ' + left + ' routine' + (left === 1 ? '' : 's') + ' left for today';
            }
        }
    }

    function updateRoutineUI(routineId, completed) {
        const routine = state.byId[routineId];
        if (!routine) {
            return;
        }
        routine.completed_today = !!completed;

        const card = root.querySelector('[data-rv2-card][data-routine-id="' + routineId + '"]');
        if (card) {
            card.classList.toggle('is-completed', !!completed);
            card.setAttribute('data-completed-today', completed ? '1' : '0');
            const dot = card.querySelector('.myavana-rv2-status-dot');
            if (dot) {
                dot.classList.remove('done', 'active', 'paused');
                dot.classList.add(statusDotClass(routine));
            }
        }

        const todayItem = root.querySelector('.myavana-rv2-today-item[data-rv2-routine="' + routineId + '"]');
        if (todayItem) {
            todayItem.classList.toggle('is-done', !!completed);
            const check = todayItem.querySelector('.myavana-rv2-check');
            const name = todayItem.querySelector('.myavana-rv2-today-name');
            if (check) {
                check.classList.toggle('done', !!completed);
                check.innerHTML = completed ? icon('check', 'myavana-rv2-lucide is-xs') : '';
                check.setAttribute('aria-pressed', completed ? 'true' : 'false');
            }
            if (name) {
                name.classList.toggle('is-done', !!completed);
            }

            const existingStartBtn = todayItem.querySelector('.myavana-rv2-start-btn');
            const existingDoneBadge = todayItem.querySelector('.myavana-rv2-done-badge');
            if (completed) {
                if (existingStartBtn) {
                    existingStartBtn.replaceWith(Object.assign(document.createElement('span'), {
                        className: 'myavana-rv2-done-badge',
                        innerHTML: icon('check', 'myavana-rv2-lucide is-xs') + 'Done'
                    }));
                }
            } else if (existingDoneBadge) {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'myavana-rv2-start-btn';
                btn.setAttribute('data-rv2-start', String(routineId));
                btn.innerHTML = icon('play', 'myavana-rv2-lucide is-xs') + 'Start';
                existingDoneBadge.replaceWith(btn);
            }
        }

        refreshTodaySummary();
        runLucide();

        if (state.currentDetailId === routineId) {
            renderDetail(routineId);
        }
    }

    async function parseRoutineAjaxResponse(response) {
        const raw = await response.text();

        try {
            return JSON.parse(raw);
        } catch (error) {
            const fallbackMessage = raw
                .replace(/<[^>]+>/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .slice(0, 220);
            throw new Error(fallbackMessage || 'Unexpected server response.');
        }
    }

    function notify(message, type) {
        if (window.HJN && typeof window.HJN.toast === 'function') {
            window.HJN.toast(message, type || 'success');
            return;
        }
        if (window.MyavanaNext && window.MyavanaNext.API && typeof window.MyavanaNext.API.showToast === 'function') {
            window.MyavanaNext.API.showToast(message, type || 'success');
        }
    }

    function setActionBusy(control, busy, busyLabel) {
        if (!control) return;
        if (busy) {
            control.dataset.rv2OriginalLabel = control.innerHTML;
            control.disabled = true;
            control.setAttribute('aria-busy', 'true');
            if (busyLabel) control.textContent = busyLabel;
            return;
        }
        control.disabled = false;
        control.removeAttribute('aria-busy');
        if (control.dataset.rv2OriginalLabel) {
            control.innerHTML = control.dataset.rv2OriginalLabel;
            delete control.dataset.rv2OriginalLabel;
            runLucide();
        }
    }

    function requestDeleteConfirmation(title) {
        return new Promise((resolve) => {
            const dialog = document.createElement('div');
            dialog.className = 'myavana-rv2-confirm';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.setAttribute('aria-labelledby', 'myavanaRv2ConfirmTitle');
            dialog.innerHTML = '<div class="myavana-rv2-confirm-backdrop"></div>'
                + '<div class="myavana-rv2-confirm-card">'
                + '<h3 id="myavanaRv2ConfirmTitle">Delete routine?</h3>'
                + '<p><strong>' + escapeHtml(title) + '</strong> will be removed from your library and schedule. This cannot be undone.</p>'
                + '<div class="myavana-rv2-confirm-actions">'
                + '<button type="button" class="myavana-rv2-pill-btn ghost" data-rv2-confirm-cancel>Keep routine</button>'
                + '<button type="button" class="myavana-rv2-pill-btn danger" data-rv2-confirm-delete>Delete routine</button>'
                + '</div></div>';
            document.body.appendChild(dialog);
            const cancel = dialog.querySelector('[data-rv2-confirm-cancel]');
            const close = (confirmed) => {
                document.removeEventListener('keydown', onKeydown);
                dialog.remove();
                resolve(confirmed);
            };
            const onKeydown = (event) => {
                if (event.key === 'Escape') close(false);
            };
            document.addEventListener('keydown', onKeydown);
            dialog.querySelector('.myavana-rv2-confirm-backdrop').addEventListener('click', () => close(false));
            cancel.addEventListener('click', () => close(false));
            dialog.querySelector('[data-rv2-confirm-delete]').addEventListener('click', () => close(true));
            window.setTimeout(() => cancel.focus(), 0);
        });
    }

    async function toggleRoutineCompletion(routineId, forceComplete, control) {
        const id = Number(routineId);
        if (Number.isNaN(id)) {
            return false;
        }
        const routine = state.byId[id];
        if (!routine) {
            return false;
        }

        if (typeof forceComplete === 'boolean' && !!routine.completed_today === forceComplete) {
            return true;
        }

        const settings = window.myavanaTimelineSettings || {};
        const nonce = settings.toggleRoutineNonce || settings.addRoutineNonce || settings.nonce || '';
        const today = root.closest('.myavana-routines-v2-page')?.getAttribute('data-today') || new Date().toISOString().slice(0, 10);

        try {
            setActionBusy(control, true, 'Saving…');
            const response = await fetch(settings.ajaxUrl || settings.ajaxurl || '/wp-admin/admin-ajax.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: new URLSearchParams({
                    action: 'myavana_toggle_routine_completion',
                    security: nonce,
                    routine_id: String(id),
                    date: today,
                }),
            });
            const payload = await parseRoutineAjaxResponse(response);
            if (!payload || !payload.success || !payload.data) {
                throw new Error((payload && payload.data) || 'Unable to update routine');
            }
            updateRoutineUI(id, !!payload.data.completed);
            notify(payload.data.completed ? 'Routine marked complete for today.' : 'Routine reopened for today.', 'success');
            return true;
        } catch (error) {
            console.error('[Routines V2] toggle routine failed', error);
            notify(error.message || 'Unable to update routine completion.', 'error');
            return false;
        } finally {
            setActionBusy(control, false);
        }
    }

    function setDrawerTab(name) {
        if (!els.drawerTabs || !els.drawer) return;
        els.drawerTabs.querySelectorAll('.tab').forEach((tab) => {
            tab.classList.toggle('is-active', tab.getAttribute('data-tab') === name);
        });
        els.drawer.querySelectorAll('.myavana-rv2-pane').forEach((pane) => {
            pane.classList.toggle('is-active', pane.getAttribute('data-pane') === name);
        });
    }

    function openDetail(routineId) {
        const id = Number(routineId);
        if (Number.isNaN(id) || !state.byId[id] || !els.drawer || !els.overlay) return;
        state.currentDetailId = id;
        renderDetail(id);
        setDrawerTab('overview');
        els.overlay.classList.add('is-open');
        els.drawer.classList.add('is-open');
        els.drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeDetail() {
        if (!els.drawer || !els.overlay) return;
        els.drawer.classList.remove('is-open');
        els.overlay.classList.remove('is-open');
        els.drawer.setAttribute('aria-hidden', 'true');
        state.currentDetailId = null;
        if (!els.sessionWrap?.classList.contains('is-open') && !els.pickerWrap?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function renderDetail(routineId) {
        const routine = state.byId[routineId];
        if (!routine) return;

        if (els.detailTitle) els.detailTitle.textContent = routine.title || 'Routine';
        if (els.detailDesc) els.detailDesc.textContent = routine.description || 'A structured routine to support your hair goals.';
        if (els.detailFrequency) {
            els.detailFrequency.innerHTML = icon(frequencyIconName(routine), 'myavana-rv2-lucide is-xs') + escapeHtml(routine.frequency_text || 'Weekly');
            els.detailFrequency.classList.remove('daily', 'weekly', 'monthly', 'asneeded');
            els.detailFrequency.classList.add(frequencyBadgeClass(routine));
        }
        if (els.detailStatus) {
            els.detailStatus.classList.remove('active', 'done', 'paused');
            els.detailStatus.classList.add(statusDotClass(routine));
        }

        if (els.detailMeta) {
            const pills = [];
            pills.push('<span class="pill">' + icon('repeat-2', 'myavana-rv2-lucide is-xxs') + escapeHtml(routine.frequency_text || 'Weekly') + '</span>');
            if (routine.time) pills.push('<span class="pill">' + icon('clock', 'myavana-rv2-lucide is-xxs') + escapeHtml(routine.time) + '</span>');
            if (routine.duration) pills.push('<span class="pill">' + icon('timer', 'myavana-rv2-lucide is-xxs') + escapeHtml(routine.duration) + '</span>');
            pills.push('<span class="pill">' + icon('list', 'myavana-rv2-lucide is-xxs') + (Array.isArray(routine.steps) ? routine.steps.length : 0) + ' steps</span>');
            els.detailMeta.innerHTML = pills.join('');
        }

        if (els.detailGoalSection && els.detailGoal) {
            if (routine.goal_link) {
                els.detailGoalSection.style.display = '';
                els.detailGoal.innerHTML = icon('target', 'myavana-rv2-lucide is-xs') + escapeHtml(routine.goal_link);
            } else {
                els.detailGoalSection.style.display = 'none';
            }
        }

        if (els.detailProducts) {
            const products = Array.isArray(routine.products) ? routine.products : [];
            if (!products.length) {
                els.detailProducts.innerHTML = '<span class="tag">No products listed</span>';
            } else {
                els.detailProducts.innerHTML = products.map((product) => '<span class="tag">' + escapeHtml(product) + '</span>').join('');
            }
        }

        if (els.detailStats) {
            const statItems = [
                { value: routine.total_sessions || 0, label: 'Total Sessions' },
                { value: (routine.completion_rate || 0) + '%', label: '30-Day Completion' },
                { value: (Array.isArray(routine.steps) ? routine.steps.length : 0), label: 'Steps' },
                { value: routine.last_completed ? formatDate(routine.last_completed) : 'Not yet', label: 'Last Completed' },
            ];
            els.detailStats.innerHTML = statItems.map((item) => {
                return '<div class="mini"><div class="val">' + escapeHtml(item.value) + '</div><div class="lbl">' + escapeHtml(item.label) + '</div></div>';
            }).join('');
        }

        if (els.detailSteps) {
            const steps = Array.isArray(routine.steps) ? routine.steps : [];
            if (!steps.length) {
                els.detailSteps.innerHTML = '<div class="myavana-rv2-step-item"><div class="myavana-rv2-step-num">1</div><div class="myavana-rv2-step-content"><div class="myavana-rv2-step-title">No steps yet</div><div class="myavana-rv2-step-sub">Edit this routine to add a step sequence.</div></div></div>';
            } else {
                els.detailSteps.innerHTML = steps.map((step, idx) => {
                    return '<div class="myavana-rv2-step-item"><div class="myavana-rv2-step-num">' + (idx + 1) + '</div><div class="myavana-rv2-step-content"><div class="myavana-rv2-step-title">' + escapeHtml(step) + '</div></div></div>';
                }).join('');
            }
        }

        if (els.detailHistory) {
            const history = Array.isArray(routine.history) ? routine.history : [];
            if (!history.length) {
                els.detailHistory.innerHTML = '<div class="myavana-rv2-history-item"><div class="myavana-rv2-history-ico">' + icon('leaf', 'myavana-rv2-lucide is-xs') + '</div><div class="myavana-rv2-history-content"><div class="myavana-rv2-history-date">No completed sessions yet</div><div class="myavana-rv2-history-sub">Run this routine to start building history.</div></div></div>';
            } else {
                els.detailHistory.innerHTML = history.map((dateStr) => {
                    return '<div class="myavana-rv2-history-item"><div class="myavana-rv2-history-ico">' + icon('leaf', 'myavana-rv2-lucide is-xs') + '</div><div class="myavana-rv2-history-content"><div class="myavana-rv2-history-date">' + escapeHtml(formatDate(dateStr)) + '</div><div class="myavana-rv2-history-sub">Routine completed</div></div></div>';
                }).join('');
            }
        }

        runLucide();
    }

    function openSession(routineId) {
        const id = Number(routineId);
        const routine = state.byId[id];
        if (Number.isNaN(id) || !routine || !els.sessionWrap) return;

        const steps = Array.isArray(routine.steps) && routine.steps.length ? routine.steps : ['Complete your routine steps'];

        state.session.routineId = id;
        state.session.steps = steps.slice();
        state.session.done = steps.map(() => false);
        state.session.seconds = 0;
        if (state.session.timer) {
            clearInterval(state.session.timer);
            state.session.timer = null;
        }

        if (els.sessionTitle) els.sessionTitle.textContent = routine.title;
        renderSessionSteps();
        updateSessionProgress();

        if (els.sessionTimer) {
            els.sessionTimer.textContent = '00:00';
        }

        state.session.timer = window.setInterval(() => {
            state.session.seconds += 1;
            const minutes = String(Math.floor(state.session.seconds / 60)).padStart(2, '0');
            const seconds = String(state.session.seconds % 60).padStart(2, '0');
            if (els.sessionTimer) {
                els.sessionTimer.textContent = minutes + ':' + seconds;
            }
        }, 1000);

        els.sessionWrap.classList.add('is-open');
        els.sessionWrap.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeSession() {
        if (!els.sessionWrap) return;
        els.sessionWrap.classList.remove('is-open');
        els.sessionWrap.setAttribute('aria-hidden', 'true');
        if (state.session.timer) {
            clearInterval(state.session.timer);
            state.session.timer = null;
        }
        if (!els.drawer?.classList.contains('is-open') && !els.pickerWrap?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function renderSessionSteps() {
        if (!els.sessionSteps) return;

        const steps = state.session.steps;
        const nextIndex = state.session.done.findIndex((done) => !done);

        els.sessionSteps.innerHTML = steps.map((step, idx) => {
            const done = state.session.done[idx];
            const current = !done && idx === nextIndex;
            const classes = ['myavana-rv2-session-step'];
            if (done) classes.push('done');
            if (current) classes.push('current');
            return '<div class="' + classes.join(' ') + '" data-rv2-session-step="' + idx + '">'
                + '<div class="num">' + (done ? '✓' : (idx + 1)) + '</div>'
                + '<div class="content"><div class="title">' + escapeHtml(step) + '</div><div class="sub">Step ' + (idx + 1) + ' of ' + steps.length + '</div></div>'
                + '</div>';
        }).join('');

        runLucide();
    }

    function updateSessionProgress() {
        const doneCount = state.session.done.filter(Boolean).length;
        const total = state.session.steps.length || 1;
        const pct = Math.round((doneCount / total) * 100);

        if (els.sessionSub) {
            const current = Math.min(doneCount + 1, total);
            els.sessionSub.textContent = 'Step ' + current + ' of ' + total + ' · In progress';
        }
        if (els.sessionBar) {
            els.sessionBar.style.width = pct + '%';
        }
        if (els.sessionLabel) {
            els.sessionLabel.textContent = doneCount + '/' + total + ' done';
        }
        if (els.sessionDoneBtn) {
            els.sessionDoneBtn.innerHTML = icon('check', 'myavana-rv2-lucide is-xs') + (doneCount >= total ? 'Complete Session' : 'Mark Step Done');
        }
    }

    async function markSessionStepDone() {
        const nextIndex = state.session.done.findIndex((done) => !done);
        if (nextIndex === -1) {
            const completed = await toggleRoutineCompletion(state.session.routineId, true, els.sessionDoneBtn);
            if (completed) closeSession();
            return;
        }

        const isFinalStep = state.session.done.filter(Boolean).length + 1 === state.session.steps.length;
        state.session.done[nextIndex] = true;
        renderSessionSteps();
        updateSessionProgress();

        // Completing the final step is the session completion action. Requiring a
        // second tap after a member has finished is easy to miss and made sessions
        // look as though they had not saved.
        if (isFinalStep) {
            const completed = await toggleRoutineCompletion(state.session.routineId, true, els.sessionDoneBtn);
            if (completed) closeSession();
        }
    }

    function openRoutineComposer(prefill) {
        const payload = prefill && typeof prefill === 'object' ? prefill : {};

        if (typeof window.openOffcanvas === 'function') {
            window.openOffcanvas('routine', payload);
        } else {
            notify('Routine creation is loading. Please try again in a moment.', 'error');
        }

        window.setTimeout(() => {
            const title = document.getElementById('routine_title');
            const frequency = document.getElementById('routine_frequency');
            const duration = document.getElementById('routine_duration');
            const notes = document.getElementById('routine_notes');
            const phase = document.getElementById('routine_phase');
            const difficulty = document.getElementById('routine_difficulty');
            const time = document.getElementById('routine_time');
            const reminderDays = document.getElementById('routine_reminder_days');
            const products = document.getElementById('routine_products');
            const tools = document.getElementById('routine_tools');
            const expectedResult = document.getElementById('routine_expected_result');
            const autoTrack = document.getElementById('routine_auto_track');
            const typeHidden = document.getElementById('routine_type_hidden');
            const pills = document.querySelectorAll('#routineTypePills .tag-pill-hjn');

            if (payload.title && title && !String(title.value || '').trim()) {
                title.value = payload.title;
            }
            if (payload.frequency && frequency) {
                frequency.value = payload.frequency;
            }
            if (payload.duration && duration) {
                duration.value = payload.duration;
            }
            if (payload.notes && notes && !String(notes.value || '').trim()) {
                notes.value = payload.notes;
            }
            if (payload.phase && phase) {
                phase.value = payload.phase;
            }
            if (payload.difficulty && difficulty) {
                difficulty.value = payload.difficulty;
            }
            if (payload.time && time) {
                time.value = payload.time;
            }
            if (payload.reminder_days && reminderDays) {
                reminderDays.value = payload.reminder_days;
            }
            if (payload.products && products && !String(products.value || '').trim()) {
                products.value = Array.isArray(payload.products) ? payload.products.join('\n') : String(payload.products);
            }
            if (payload.tools && tools && !String(tools.value || '').trim()) {
                tools.value = payload.tools;
            }
            if (payload.expected_result && expectedResult && !String(expectedResult.value || '').trim()) {
                expectedResult.value = payload.expected_result;
            }
            if (autoTrack && typeof payload.auto_track !== 'undefined') {
                autoTrack.checked = !!payload.auto_track;
            }
            if (payload.type && typeHidden) {
                typeHidden.value = payload.type;
                pills.forEach((pill) => {
                    const isActive = String(pill.getAttribute('data-value') || '') === payload.type;
                    pill.classList.toggle('is-active', isActive);
                });
            }

            title?.focus();
        }, 120);
    }

    function openPicker() {
        if (!els.pickerWrap) {
            openRoutineComposer();
            return;
        }
        els.pickerWrap.classList.add('is-open');
        els.pickerWrap.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        runLucide();
    }

    function closePicker() {
        if (!els.pickerWrap) return;
        els.pickerWrap.classList.remove('is-open');
        els.pickerWrap.setAttribute('aria-hidden', 'true');
        if (!els.drawer?.classList.contains('is-open') && !els.sessionWrap?.classList.contains('is-open') && !els.templatePreviewWrap?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function renderTemplatePreview(template) {
        if (!template) {
            return;
        }

        if (els.templatePreviewKicker) els.templatePreviewKicker.textContent = template.label || 'Template';
        if (els.templatePreviewTitle) els.templatePreviewTitle.textContent = template.title || 'Routine Template';
        if (els.templatePreviewDesc) els.templatePreviewDesc.textContent = template.description || 'A structured routine template you can edit before saving.';

        if (els.templatePreviewMeta) {
            const meta = [];
            if (template.type) meta.push('<span class="pill">' + icon('sparkles', 'myavana-rv2-lucide is-xxs') + escapeHtml(template.type) + '</span>');
            if (template.frequency) meta.push('<span class="pill">' + icon('repeat-2', 'myavana-rv2-lucide is-xxs') + escapeHtml(template.frequency) + '</span>');
            if (template.duration) meta.push('<span class="pill">' + icon('timer', 'myavana-rv2-lucide is-xxs') + escapeHtml(template.duration) + ' min</span>');
            if (template.phase) meta.push('<span class="pill">' + icon('clock-3', 'myavana-rv2-lucide is-xxs') + escapeHtml(template.phase) + '</span>');
            if (template.difficulty) meta.push('<span class="pill">' + icon('badge-check', 'myavana-rv2-lucide is-xxs') + escapeHtml(template.difficulty) + '</span>');
            els.templatePreviewMeta.innerHTML = meta.join('');
        }

        if (els.templatePreviewSteps) {
            const steps = Array.isArray(template.steps) ? template.steps : [];
            els.templatePreviewSteps.innerHTML = steps.map((step, idx) => {
                return '<div class="myavana-rv2-step-item"><div class="myavana-rv2-step-num">' + (idx + 1) + '</div><div class="myavana-rv2-step-content"><div class="myavana-rv2-step-title">' + escapeHtml(step) + '</div></div></div>';
            }).join('') || '<div class="myavana-rv2-callout-lite">No steps defined yet.</div>';
        }

        if (els.templatePreviewProducts) {
            const products = Array.isArray(template.products) ? template.products : [];
            els.templatePreviewProducts.innerHTML = products.map((product) => '<span class="tag">' + escapeHtml(product) + '</span>').join('') || '<span class="tag">No products listed</span>';
        }

        if (els.templatePreviewToolsWrap && els.templatePreviewTools) {
            const hasTools = !!String(template.tools || '').trim();
            els.templatePreviewToolsWrap.style.display = hasTools ? '' : 'none';
            if (hasTools) {
                els.templatePreviewTools.innerHTML = escapeHtml(String(template.tools || '')).replace(/\n/g, '<br>');
            }
        }

        if (els.templatePreviewOutcomeWrap && els.templatePreviewOutcome) {
            const hasOutcome = !!String(template.expected_result || '').trim();
            els.templatePreviewOutcomeWrap.style.display = hasOutcome ? '' : 'none';
            if (hasOutcome) {
                els.templatePreviewOutcome.textContent = template.expected_result;
            }
        }

        if (els.templatePreviewNotesWrap && els.templatePreviewNotes) {
            const hasNotes = !!String(template.notes || '').trim();
            els.templatePreviewNotesWrap.style.display = hasNotes ? '' : 'none';
            if (hasNotes) {
                els.templatePreviewNotes.textContent = template.notes;
            }
        }

        runLucide();
    }

    function openTemplatePreview(key) {
        const template = state.templates.find((item) => String(item.key) === String(key));
        if (!template || !els.templatePreviewWrap) {
            if (template) {
                openRoutineComposer(template);
            }
            return;
        }

        state.currentTemplateKey = String(template.key);
        renderTemplatePreview(template);
        if (els.pickerWrap?.classList.contains('is-open')) {
            els.pickerWrap.classList.remove('is-open');
            els.pickerWrap.setAttribute('aria-hidden', 'true');
        }
        els.templatePreviewWrap.classList.add('is-open');
        els.templatePreviewWrap.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeTemplatePreview() {
        if (!els.templatePreviewWrap) return;
        els.templatePreviewWrap.classList.remove('is-open');
        els.templatePreviewWrap.setAttribute('aria-hidden', 'true');
        state.currentTemplateKey = null;
        if (!els.drawer?.classList.contains('is-open') && !els.sessionWrap?.classList.contains('is-open') && !els.pickerWrap?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function applyRoutineTemplate(key) {
        const tpl = state.templates.find((template) => String(template.key) === key) || {
            title: '',
            type: '',
            frequency: 'Weekly',
            duration: '',
        };
        closeTemplatePreview();
        closePicker();
        openRoutineComposer(tpl);
    }

    async function deleteRoutineById(routineId, control) {
        const id = Number(routineId);
        if (Number.isNaN(id)) {
            return;
        }

        const routine = state.byId[id];
        const title = routine && routine.title ? routine.title : 'this routine';
        if (!await requestDeleteConfirmation(title)) {
            return;
        }

        const settings = window.myavanaTimelineSettings || {};
        const endpoint = settings.ajaxUrl || settings.ajaxurl || '/wp-admin/admin-ajax.php';
        const nonce = settings.deleteRoutineNonce || settings.addRoutineNonce || settings.nonce || '';

        try {
            setActionBusy(control, true, 'Deleting…');
            const response = await fetch(endpoint, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({
                    action: 'myavana_delete_routine',
                    security: nonce,
                    routine_id: String(id),
                }),
            });
            const payload = await response.json();
            if (!payload || !payload.success) {
                throw new Error((payload && payload.data) || 'Unable to delete routine.');
            }

            if (state.currentDetailId === id) {
                closeDetail();
            }

            notify('Routine deleted.', 'success');
            try { window.sessionStorage.setItem('myavanaRoutineFeedback', 'Routine deleted.'); } catch (error) {}
            window.setTimeout(() => window.location.reload(), 350);
        } catch (error) {
            notify(error.message || 'Unable to delete routine.', 'error');
            setActionBusy(control, false);
        }
    }

    function bindEvents() {
        root.addEventListener('click', (event) => {
            const target = event.target;

            const filterBtn = target.closest('.myavana-rv2-filter');
            if (filterBtn) {
                state.activeFilter = String(filterBtn.getAttribute('data-filter') || 'all');
                root.querySelectorAll('.myavana-rv2-filter').forEach((btn) => {
                    btn.classList.toggle('is-active', btn === filterBtn);
                });
                applyFilters();
                return;
            }

            const openCreate = target.closest('[data-rv2-open-create]');
            if (openCreate) {
                closeTemplatePreview();
                closePicker();
                openRoutineComposer();
                return;
            }

            const openPickerTrigger = target.closest('[data-rv2-open-template-picker]');
            if (openPickerTrigger) {
                openPicker();
                return;
            }

            const previewTemplateBtn = target.closest('[data-rv2-preview-template]');
            if (previewTemplateBtn) {
                openTemplatePreview(String(previewTemplateBtn.getAttribute('data-rv2-preview-template') || ''));
                return;
            }

            const templateBtn = target.closest('[data-rv2-template]');
            if (templateBtn) {
                applyRoutineTemplate(String(templateBtn.getAttribute('data-rv2-template') || 'custom'));
                return;
            }

            const closePickerTrigger = target.closest('[data-rv2-close-picker]');
            if (closePickerTrigger) {
                closePicker();
                return;
            }

            const closeTemplatePreviewTrigger = target.closest('[data-rv2-close-template-preview]');
            if (closeTemplatePreviewTrigger) {
                closeTemplatePreview();
                return;
            }

            const openDetailTrigger = target.closest('[data-rv2-open-detail]');
            if (openDetailTrigger) {
                openDetail(openDetailTrigger.getAttribute('data-rv2-open-detail'));
                return;
            }

            const closeDetailTrigger = target.closest('[data-rv2-close-detail]');
            if (closeDetailTrigger) {
                closeDetail();
                return;
            }

            const startTrigger = target.closest('[data-rv2-start]');
            if (startTrigger) {
                openSession(startTrigger.getAttribute('data-rv2-start'));
                return;
            }

            const toggleTrigger = target.closest('[data-rv2-toggle]');
            if (toggleTrigger) {
                toggleRoutineCompletion(toggleTrigger.getAttribute('data-rv2-toggle'), undefined, toggleTrigger);
                return;
            }

            const editTrigger = target.closest('[data-rv2-edit]');
            if (editTrigger) {
                openRoutineComposer(state.byId[Number(editTrigger.getAttribute('data-rv2-edit'))]);
                return;
            }

            const deleteTrigger = target.closest('[data-rv2-delete]');
            if (deleteTrigger) {
                deleteRoutineById(deleteTrigger.getAttribute('data-rv2-delete'), deleteTrigger);
                return;
            }

            const closeSessionTrigger = target.closest('[data-rv2-close-session]');
            if (closeSessionTrigger) {
                closeSession();
                return;
            }

            const sessionStep = target.closest('[data-rv2-session-step]');
            if (sessionStep) {
                const idx = Number(sessionStep.getAttribute('data-rv2-session-step'));
                if (!Number.isNaN(idx)) {
                    state.session.done[idx] = !state.session.done[idx];
                    renderSessionSteps();
                    updateSessionProgress();
                }
            }
        });

        if (els.search) {
            els.search.addEventListener('input', () => {
                state.query = String(els.search.value || '').trim().toLowerCase();
                applyFilters();
            });
        }

        if (els.drawerTabs) {
            els.drawerTabs.addEventListener('click', (event) => {
                const tab = event.target.closest('[data-tab]');
                if (!tab) return;
                setDrawerTab(tab.getAttribute('data-tab'));
            });
        }

        if (els.overlay) {
            els.overlay.addEventListener('click', closeDetail);
        }

        if (els.startSessionBtn) {
            els.startSessionBtn.addEventListener('click', () => {
                if (state.currentDetailId !== null) {
                    openSession(state.currentDetailId);
                }
            });
        }

        if (els.editBtn) {
            els.editBtn.addEventListener('click', () => {
                if (state.currentDetailId !== null) {
                    openRoutineComposer(state.byId[state.currentDetailId]);
                }
            });
        }

        if (els.deleteBtn) {
            els.deleteBtn.addEventListener('click', () => {
                if (state.currentDetailId !== null) {
                    deleteRoutineById(state.currentDetailId, els.deleteBtn);
                }
            });
        }

        if (els.templatePreviewUse) {
            els.templatePreviewUse.addEventListener('click', () => {
                if (state.currentTemplateKey) {
                    applyRoutineTemplate(state.currentTemplateKey);
                }
            });
        }

        if (els.sessionDoneBtn) {
            els.sessionDoneBtn.addEventListener('click', markSessionStepDone);
        }

        if (els.sessionLogEntryBtn) {
            els.sessionLogEntryBtn.addEventListener('click', () => {
                if (typeof window.openOffcanvas === 'function') {
                    window.openOffcanvas('entry');
                }
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (els.pickerWrap?.classList.contains('is-open')) {
                closePicker();
                return;
            }

            if (els.templatePreviewWrap?.classList.contains('is-open')) {
                closeTemplatePreview();
                return;
            }

            if (els.sessionWrap?.classList.contains('is-open')) {
                closeSession();
                return;
            }

            if (els.drawer?.classList.contains('is-open')) {
                closeDetail();
            }
        });
    }

    window.MyavanaRoutineSession = {
        open: function (routineObj) {
            if (!routineObj || typeof routineObj !== 'object' || !routineObj.id) return;
            const id = Number(routineObj.id);
            if (!state.byId[id]) {
                state.byId[id] = routineObj;
            }
            openSession(id);
        }
    };

    function init() {
        hydrate();
        window.myavanaOpenRoutineForm = openRoutineComposer;
        window.deleteRoutine = deleteRoutineById;
        bindEvents();
        applyFilters();
        refreshTodaySummary();
        runLucide();

        try {
            const feedback = window.sessionStorage.getItem('myavanaRoutineFeedback');
            if (feedback) {
                window.sessionStorage.removeItem('myavanaRoutineFeedback');
                window.setTimeout(() => notify(feedback, 'success'), 80);
            }
        } catch (error) {}

        const params = new URLSearchParams(window.location.search);
        if (params.get('create') === 'routine') {
            const template = params.get('template');
            if (template) {
                applyRoutineTemplate(template);
                return;
            }

            openRoutineComposer({
                type: params.get('type') || '',
                title: params.get('title') || '',
                frequency: params.get('frequency') || '',
                duration: params.get('duration') || ''
            });
        }
    }

    init();
});
