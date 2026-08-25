(function() {
    'use strict';

    const root = document.getElementById('myavanaGoalsV2Root');
    if (!root) {
        return;
    }

    const state = {
        goals: [],
        byId: {},
        statusFilter: 'all',
        categoryFilter: 'all',
        currentGoalId: null,
        checkinGoalId: null,
        mood: 'ok',
        rating: 4,
    };

    const els = {
        statusTabs: document.getElementById('myavanaGv2StatusTabs'),
        categoryRow: document.getElementById('myavanaGv2CategoryRow'),
        grid: document.getElementById('myavanaGv2Grid'),
        overlay: document.getElementById('myavanaGv2Overlay'),
        drawer: document.getElementById('myavanaGv2Drawer'),
        drawerTabs: document.getElementById('myavanaGv2DrawerTabs'),
        detailStrip: document.getElementById('myavanaGv2DetailStrip'),
        detailBadges: document.getElementById('myavanaGv2DetailBadges'),
        detailTitle: document.getElementById('myavanaGv2DetailTitle'),
        detailDescription: document.getElementById('myavanaGv2DetailDescription'),
        detailPct: document.getElementById('myavanaGv2DetailPct'),
        detailProgLbl: document.getElementById('myavanaGv2DetailProgLbl'),
        detailStatus: document.getElementById('myavanaGv2DetailStatus'),
        detailFill: document.getElementById('myavanaGv2DetailFill'),
        detailTimeline: document.getElementById('myavanaGv2DetailTimeline'),
        detailValues: document.getElementById('myavanaGv2DetailValues'),
        detailCriteriaSec: document.getElementById('myavanaGv2DetailCriteriaSec'),
        detailCriteria: document.getElementById('myavanaGv2DetailCriteria'),
        detailRewardSec: document.getElementById('myavanaGv2DetailRewardSec'),
        detailReward: document.getElementById('myavanaGv2DetailReward'),
        detailMilestones: document.getElementById('myavanaGv2DetailMilestones'),
        detailCheckins: document.getElementById('myavanaGv2DetailCheckins'),
        detailRoutines: document.getElementById('myavanaGv2DetailRoutines'),
        detailImpact: document.getElementById('myavanaGv2DetailImpact'),
        detailCheckinBtn: document.getElementById('myavanaGv2DetailCheckinBtn'),
        detailEditBtn: document.getElementById('myavanaGv2DetailEditBtn'),
        detailPauseBtn: document.getElementById('myavanaGv2DetailPauseBtn'),
        detailDeleteBtn: document.getElementById('myavanaGv2DetailDeleteBtn'),
        checkinModal: document.getElementById('myavanaGv2CheckinModal'),
        checkinSub: document.getElementById('myavanaGv2CheckinGoalSub'),
        checkinMeasureInput: document.getElementById('myavanaGv2MeasureInput'),
        checkinMeasureUnit: document.getElementById('myavanaGv2MeasureUnit'),
        checkinMoodRow: document.getElementById('myavanaGv2MoodRow'),
        checkinStarRow: document.getElementById('myavanaGv2StarRow'),
        checkinNote: document.getElementById('myavanaGv2CheckinNote'),
        saveCheckinBtn: document.getElementById('myavanaGv2SaveCheckinBtn'),
        pickerOverlay: document.getElementById('myavanaGv2PickerOverlay'),
        picker: document.getElementById('myavanaGv2Picker'),
    };

    function icon(name, cls) {
        const c = cls ? ' class="' + cls + '"' : '';
        return '<i data-lucide="' + name + '"' + c + '></i>';
    }

    function runLucide() {
        if (window.lucide && typeof window.lucide.createIcons === 'function') {
            window.lucide.createIcons({ attrs: { 'stroke-width': 1.9 } });
        }
    }

    function showFeedback(message, type) {
        if (window.HJN && typeof window.HJN.toast === 'function') {
            window.HJN.toast(message, type || 'success');
        }
    }

    async function parseAjaxResponse(response) {
        const raw = await response.text();
        try { return JSON.parse(raw); }
        catch (error) { throw new Error(raw.replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim().slice(0, 220) || 'Unexpected server response.'); }
    }

    function requestGoalDeleteConfirmation(title) {
        return new Promise((resolve) => {
            const dialog = document.createElement('div');
            dialog.className = 'myavana-gv2-confirm';
            dialog.setAttribute('role', 'dialog');
            dialog.setAttribute('aria-modal', 'true');
            dialog.innerHTML = '<div class="myavana-gv2-confirm-backdrop"></div><div class="myavana-gv2-confirm-card">'
                + '<h3>Delete goal?</h3><p><strong>' + String(title || 'This goal').replace(/[&<>"']/g, '') + '</strong> will be removed with its progress history. This cannot be undone.</p>'
                + '<div class="myavana-gv2-confirm-actions"><button type="button" data-gv2-cancel>Keep goal</button><button type="button" data-gv2-delete>Delete goal</button></div></div>';
            document.body.appendChild(dialog);
            const close = (confirmed) => { document.removeEventListener('keydown', onKeydown); dialog.remove(); resolve(confirmed); };
            const onKeydown = (event) => { if (event.key === 'Escape') close(false); };
            document.addEventListener('keydown', onKeydown);
            dialog.querySelector('.myavana-gv2-confirm-backdrop').addEventListener('click', () => close(false));
            dialog.querySelector('[data-gv2-cancel]').addEventListener('click', () => close(false));
            dialog.querySelector('[data-gv2-delete]').addEventListener('click', () => close(true));
            window.setTimeout(() => dialog.querySelector('[data-gv2-cancel]').focus(), 0);
        });
    }

    function movePortalLayersToBody() {
        const portalHost = root.parentNode || document.body;
        [
            els.overlay,
            els.drawer,
            els.checkinModal,
            els.pickerOverlay,
            els.picker,
        ].forEach((node) => {
            if (node && node.parentNode !== portalHost) {
                portalHost.appendChild(node);
            }
        });
    }

    function parseData() {
        const node = document.getElementById('myavanaGoalsV2Data');
        if (!node) {
            return [];
        }
        try {
            const parsed = JSON.parse(node.textContent || '{}');
            return Array.isArray(parsed.goals) ? parsed.goals : [];
        } catch (error) {
            console.error('[Goals V2] Failed parsing data', error);
            return [];
        }
    }

    function hydrate() {
        state.goals = parseData();
        state.byId = {};
        state.goals.forEach((goal) => {
            const id = Number(goal.id);
            if (!Number.isNaN(id)) {
                state.byId[id] = goal;
            }
        });
    }

    function formatDate(dateStr, monthStyle) {
        if (!dateStr) return 'Not set';
        const date = new Date(dateStr + 'T00:00:00');
        if (Number.isNaN(date.getTime())) return dateStr;
        const opts = monthStyle === 'short'
            ? { month: 'short', day: 'numeric', year: 'numeric' }
            : { month: 'long', year: 'numeric' };
        return date.toLocaleDateString(undefined, opts);
    }

    function statusLabel(statusGroup) {
        if (statusGroup === 'on_track') return 'On Track';
        if (statusGroup === 'at_risk') return 'At Risk';
        if (statusGroup === 'paused') return 'Paused';
        if (statusGroup === 'completed') return 'Completed';
        return 'Active';
    }

    function statusColor(statusGroup) {
        if (statusGroup === 'at_risk') return 'var(--gv2-amber)';
        if (statusGroup === 'paused') return 'var(--gv2-dim)';
        if (statusGroup === 'completed') return 'var(--gv2-green)';
        return 'var(--gv2-green)';
    }

    function stripStyle(goal) {
        const palette = String(goal.palette || 'coral');
        return 'strip-' + palette;
    }

    function applyFilters() {
        const cards = root.querySelectorAll('[data-gv2-card]');
        cards.forEach((card) => {
            const statusGroup = String(card.getAttribute('data-status-group') || '');
            const categoryId = String(card.getAttribute('data-category-id') || '');

            const statusPass = state.statusFilter === 'all' || statusGroup === state.statusFilter;
            const categoryPass = state.categoryFilter === 'all' || categoryId === state.categoryFilter;

            card.style.display = (statusPass && categoryPass) ? '' : 'none';
        });
    }

    function setStatusTab(status) {
        state.statusFilter = status;
        if (!els.statusTabs) return;
        els.statusTabs.querySelectorAll('[data-status]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-status') === status);
        });
        applyFilters();
    }

    function setCategory(category) {
        state.categoryFilter = category;
        if (!els.categoryRow) return;
        els.categoryRow.querySelectorAll('[data-category]').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-category') === category);
        });
        applyFilters();
    }

    function setDrawerTab(tabName) {
        if (els.drawerTabs) {
            els.drawerTabs.querySelectorAll('[data-tab]').forEach((tab) => {
                tab.classList.toggle('is-active', tab.getAttribute('data-tab') === tabName);
            });
        }
        if (els.drawer) {
            els.drawer.querySelectorAll('.pane').forEach((pane) => {
                pane.classList.toggle('is-active', pane.getAttribute('data-pane') === tabName);
            });
        }
    }

    function openDetail(goalId) {
        const id = Number(goalId);
        if (Number.isNaN(id) || !state.byId[id] || !els.overlay || !els.drawer) return;
        state.currentGoalId = id;
        renderDetail(id);
        setDrawerTab('overview');
        els.overlay.classList.add('is-open');
        els.drawer.classList.add('is-open');
        els.drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeDetail() {
        if (!els.overlay || !els.drawer) return;
        els.overlay.classList.remove('is-open');
        els.drawer.classList.remove('is-open');
        els.drawer.setAttribute('aria-hidden', 'true');
        state.currentGoalId = null;
        if (!els.checkinModal?.classList.contains('is-open') && !els.picker?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function renderDetail(goalId) {
        const goal = state.byId[goalId];
        if (!goal) return;

        if (els.detailStrip) {
            els.detailStrip.className = 'strip ' + stripStyle(goal);
        }

        if (els.detailBadges) {
            const categoryBadgeClass = 'cb-' + (goal.palette || 'coral');
            const status = statusLabel(goal.status_group);
            const statusClass = goal.status_group === 'at_risk' ? 'cb-amber' : (goal.status_group === 'paused' ? 'cb-sand' : 'cb-green');
            els.detailBadges.innerHTML = [
                '<span class="badge ' + categoryBadgeClass + '">' + goal.category + '</span>',
                '<span class="badge cb-sand">' + goal.priority + ' Priority</span>',
                '<span class="badge ' + statusClass + '">' + status + '</span>'
            ].join('');
        }

        if (els.detailTitle) els.detailTitle.textContent = goal.title || 'Goal';
        if (els.detailDescription) {
            els.detailDescription.textContent = goal.description || 'No description added yet.';
        }

        if (els.detailPct) els.detailPct.textContent = String(goal.progress || 0) + '%';
        if (els.detailProgLbl) {
            const current = goal.current_display || '-';
            const target = goal.target_display || '-';
            const remaining = goal.weeks_remaining !== null && goal.weeks_remaining !== undefined
                ? goal.weeks_remaining + ' weeks remaining'
                : 'No target timeline set';
            els.detailProgLbl.textContent = current + ' of ' + target + ' target · ' + remaining;
        }

        if (els.detailStatus) {
            els.detailStatus.textContent = statusLabel(goal.status_group);
            els.detailStatus.style.color = statusColor(goal.status_group);
        }

        if (els.detailFill) {
            els.detailFill.style.width = String(Math.max(0, Math.min(100, Number(goal.progress || 0)))) + '%';
        }

        if (els.detailTimeline) {
            const items = [];
            items.push('<span class="item">' + icon('calendar-days', 'myavana-gv2-lucide is-xxs') + 'Started ' + (goal.start_date ? formatDate(goal.start_date, 'short') : 'Not set') + '</span>');
            items.push('<span class="sep"></span>');
            items.push('<span class="item">' + icon('map-pin', 'myavana-gv2-lucide is-xxs') + (goal.weeks_in || 0) + ' weeks in</span>');
            items.push('<span class="sep"></span>');
            items.push('<span class="item">' + icon('flag', 'myavana-gv2-lucide is-xxs') + (goal.target_date ? formatDate(goal.target_date, 'short') : 'No target date') + '</span>');
            items.push('<span class="sep"></span>');
            items.push('<span class="item">' + icon('repeat-2', 'myavana-gv2-lucide is-xxs') + (goal.checkin_frequency || 'Weekly') + '</span>');
            els.detailTimeline.innerHTML = items.join('');
        }

        if (els.detailValues) {
            els.detailValues.innerHTML = [
                '<div class="v"><div class="num">' + (goal.baseline_display || '-') + '</div><div class="l">Baseline</div></div>',
                '<div class="v current"><div class="num">' + (goal.current_display || '-') + '</div><div class="l">Current</div></div>',
                '<div class="v"><div class="num">' + (goal.target_display || '-') + '</div><div class="l">Target</div></div>'
            ].join('');
        }

        if (els.detailCriteriaSec && els.detailCriteria) {
            const criteria = String(goal.goal_success_criteria || '').trim();
            if (criteria) {
                els.detailCriteriaSec.style.display = '';
                els.detailCriteria.textContent = criteria;
            } else {
                els.detailCriteriaSec.style.display = 'none';
            }
        }

        if (els.detailRewardSec && els.detailReward) {
            const reward = String(goal.goal_reward || '').trim();
            if (reward) {
                els.detailRewardSec.style.display = '';
                els.detailReward.innerHTML = icon('gift', 'myavana-gv2-lucide is-xs') + reward;
            } else {
                els.detailRewardSec.style.display = 'none';
            }
        }

        if (els.detailMilestones) {
            const milestones = Array.isArray(goal.milestones) ? goal.milestones : [];
            if (!milestones.length) {
                els.detailMilestones.innerHTML = '<div class="milestone-item"><div class="ms-icon future">' + icon('flag', 'myavana-gv2-lucide is-xs') + '</div><div class="ms-content"><div class="ms-title">No milestones yet</div><div class="ms-sub">Edit this goal to add milestone checkpoints.</div></div></div>';
            } else {
                els.detailMilestones.innerHTML = milestones.map((milestone, idx) => {
                    const achieved = !!milestone.achieved;
                    const cls = achieved ? 'hit' : (idx === goal.milestones_hit ? 'next' : 'future');
                    const badgeCls = achieved ? 'hit' : (idx === goal.milestones_hit ? 'next' : 'future');
                    const badgeText = achieved ? 'Hit' : (idx === goal.milestones_hit ? 'Next' : 'Future');
                    return '<div class="milestone-item">'
                        + '<div class="ms-icon ' + cls + '">' + (achieved ? icon('check', 'myavana-gv2-lucide is-xs') : icon('target', 'myavana-gv2-lucide is-xs')) + '</div>'
                        + '<div class="ms-content"><div class="ms-title">' + milestone.text + '</div><div class="ms-sub">Milestone ' + (idx + 1) + ' of ' + milestones.length + '</div></div>'
                        + '<span class="ms-badge ' + badgeCls + '">' + badgeText + '</span>'
                        + '</div>';
                }).join('');
            }
        }

        if (els.detailCheckins) {
            const checkins = Array.isArray(goal.checkins) ? goal.checkins : [];
            if (!checkins.length) {
                els.detailCheckins.innerHTML = '<div class="checkin-item"><div class="ck-icon">' + icon('notebook-pen', 'myavana-gv2-lucide is-xs') + '</div><div class="ck-content"><div class="ck-note">No check-ins yet</div><div class="ck-sub">Use weekly check-ins to keep this goal moving.</div></div></div>';
            } else {
                els.detailCheckins.innerHTML = checkins.map((checkin) => {
                    return '<div class="checkin-item">'
                        + '<div class="ck-icon">' + icon('notebook-pen', 'myavana-gv2-lucide is-xs') + '</div>'
                        + '<div class="ck-content"><div class="ck-note">' + checkin.note + '</div><div class="ck-sub">' + (checkin.date ? formatDate(checkin.date, 'short') : 'Date not recorded') + '</div></div>'
                        + '</div>';
                }).join('');
            }
        }

        if (els.detailRoutines && els.detailImpact) {
            const routines = Array.isArray(goal.linked_routines) ? goal.linked_routines : [];
            if (!routines.length) {
                els.detailRoutines.innerHTML = '<div class="routine-item"><div class="rt-icon">' + icon('repeat-2', 'myavana-gv2-lucide is-xs') + '</div><div class="rt-content"><div class="rt-name">No linked routines</div><div class="rt-sub">Link routines in routine setup to tie execution to this goal.</div></div></div>';
                els.detailImpact.style.display = 'none';
            } else {
                els.detailRoutines.innerHTML = routines.map((routine) => {
                    const duration = routine.duration ? (' · ' + routine.duration) : '';
                    const streakText = routine.streak > 0 ? (' · ' + routine.streak + '-day streak') : '';
                    return '<div class="routine-item" data-gv2-open-routine="' + routine.id + '">'
                        + '<div class="rt-icon">' + icon('repeat-2', 'myavana-gv2-lucide is-xs') + '</div>'
                        + '<div class="rt-content"><div class="rt-name">' + routine.title + '</div><div class="rt-sub">' + routine.steps_count + ' steps' + duration + streakText + '</div></div>'
                        + '<div class="rt-sub">' + routine.frequency + '</div>'
                        + '</div>';
                }).join('');

                els.detailImpact.style.display = '';
                els.detailImpact.innerHTML = '<strong style="color:var(--gv2-blueberry)">Routine Impact:</strong> '
                    + 'This goal has ' + routines.length + ' linked routine' + (routines.length === 1 ? '' : 's')
                    + ' driving measurable consistency.';
            }
        }

        if (els.detailPauseBtn) {
            if (goal.status_group === 'completed') {
                els.detailPauseBtn.style.display = 'none';
            } else {
                els.detailPauseBtn.style.display = 'inline-flex';
                const paused = goal.status_raw === 'paused' || goal.status_group === 'paused';
                els.detailPauseBtn.textContent = paused ? 'Resume' : 'Pause';
            }
        }

        runLucide();
    }

    function openCheckin(goalId) {
        const id = Number(goalId);
        const goal = state.byId[id];
        if (Number.isNaN(id) || !goal || !els.checkinModal) return;

        state.checkinGoalId = id;
        state.mood = 'ok';
        state.rating = 4;

        if (els.checkinSub) {
            els.checkinSub.textContent = (goal.title || 'Goal') + ' · Record your progress this period';
        }

        if (els.checkinMeasureInput) {
            const current = goal.current_value;
            els.checkinMeasureInput.value = (current !== null && current !== undefined && current !== '') ? String(Number(current).toFixed(1)) : '';
        }

        if (els.checkinMeasureUnit) {
            const unit = goal.measure_unit || 'value';
            els.checkinMeasureUnit.textContent = unit;
        }

        if (els.checkinNote) {
            els.checkinNote.value = '';
        }

        if (els.checkinMoodRow) {
            els.checkinMoodRow.querySelectorAll('[data-mood]').forEach((btn) => {
                btn.classList.toggle('is-selected', btn.getAttribute('data-mood') === 'ok');
            });
        }

        if (els.checkinStarRow) {
            els.checkinStarRow.querySelectorAll('[data-rating]').forEach((btn) => {
                const value = Number(btn.getAttribute('data-rating'));
                btn.classList.toggle('is-selected', value <= 4);
            });
        }

        els.checkinModal.classList.add('is-open');
        els.checkinModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        runLucide();
    }

    function closeCheckin() {
        if (!els.checkinModal) return;
        els.checkinModal.classList.remove('is-open');
        els.checkinModal.setAttribute('aria-hidden', 'true');
        state.checkinGoalId = null;
        if (!els.drawer?.classList.contains('is-open') && !els.picker?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function buildUpdatePayload(goal, updates) {
        const payload = new URLSearchParams();
        const settings = window.myavanaTimelineSettings || {};
        const nonce = settings.updateGoalNonce || settings.addGoalNonce || settings.nonce || '';

        payload.set('action', 'myavana_update_goal');
        payload.set('security', nonce);
        payload.set('goal_id', String(goal.id));
        payload.set('title', String(goal.title || 'Goal'));
        payload.set('description', String(goal.description || ''));
        payload.set('status', String(updates.status || goal.status_raw || 'active'));
        payload.set('start_date', String(goal.start_date || ''));
        payload.set('target_date', String(goal.target_date || ''));
        payload.set('progress', String(updates.progress));
        payload.set('goal_category', String(goal.category || ''));
        payload.set('goal_priority', String(goal.priority || 'Medium'));
        payload.set('goal_checkin_frequency', String(goal.checkin_frequency || 'Weekly'));
        payload.set('goal_baseline_value', goal.baseline_value !== null && goal.baseline_value !== undefined ? String(goal.baseline_value) : '');
        payload.set('goal_target_value', goal.target_value !== null && goal.target_value !== undefined ? String(goal.target_value) : '');
        payload.set('goal_measure_unit', String(goal.measure_unit || ''));
        payload.set('goal_reward', String(goal.goal_reward || ''));
        payload.set('goal_success_criteria', String(goal.goal_success_criteria || ''));
        payload.set('goal_milestones', JSON.stringify(Array.isArray(goal.milestones) ? goal.milestones : []));

        if (updates.progressNote) {
            payload.set('progress_note', updates.progressNote);
        }

        return payload;
    }

    async function saveCheckin() {
        const id = Number(state.checkinGoalId);
        const goal = state.byId[id];
        if (Number.isNaN(id) || !goal) {
            return;
        }

        const measurementRaw = els.checkinMeasureInput ? String(els.checkinMeasureInput.value || '').trim() : '';
        const note = els.checkinNote ? String(els.checkinNote.value || '').trim() : '';
        const mood = state.mood;
        const rating = state.rating;

        let nextProgress = Number(goal.progress || 0);

        if (measurementRaw !== '' && !Number.isNaN(Number(measurementRaw))
            && goal.baseline_value !== null && goal.baseline_value !== undefined
            && goal.target_value !== null && goal.target_value !== undefined
            && Number(goal.target_value) !== Number(goal.baseline_value)) {
            const measurement = Number(measurementRaw);
            const ratio = (measurement - Number(goal.baseline_value)) / (Number(goal.target_value) - Number(goal.baseline_value));
            nextProgress = Math.max(0, Math.min(100, Math.round(ratio * 100)));
        }

        const noteParts = [];
        noteParts.push('Mood: ' + mood);
        noteParts.push('Rating: ' + rating + '/5');
        if (measurementRaw !== '') {
            noteParts.push('Measurement: ' + measurementRaw + (goal.measure_unit ? ' ' + goal.measure_unit : ''));
        }
        if (note !== '') {
            noteParts.push(note);
        }

        const payload = buildUpdatePayload(goal, {
            progress: nextProgress,
            status: goal.status_raw === 'completed' ? 'completed' : (goal.status_raw || 'active'),
            progressNote: noteParts.join(' · '),
        });

        const settings = window.myavanaTimelineSettings || {};
        const endpoint = settings.ajaxUrl || settings.ajaxurl || '/wp-admin/admin-ajax.php';

        if (els.saveCheckinBtn) {
            els.saveCheckinBtn.disabled = true;
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: payload,
            });
            const data = await parseAjaxResponse(response);
            if (!data || !data.success) {
                throw new Error((data && data.data) || 'Failed to save check-in');
            }
            try { window.sessionStorage.setItem('myavanaGoalFeedback', 'Check-in saved.'); } catch (error) {}
            window.location.reload();
        } catch (error) {
            console.error('[Goals V2] Check-in save failed', error);
            showFeedback(error.message || 'Unable to save check-in.', 'error');
        } finally {
            if (els.saveCheckinBtn) {
                els.saveCheckinBtn.disabled = false;
            }
        }
    }

    async function togglePauseCurrentGoal() {
        const id = Number(state.currentGoalId);
        const goal = state.byId[id];
        if (Number.isNaN(id) || !goal || goal.status_group === 'completed') {
            return;
        }

        const paused = goal.status_raw === 'paused' || goal.status_group === 'paused';
        const nextStatus = paused ? 'active' : 'paused';

        const payload = buildUpdatePayload(goal, {
            progress: Number(goal.progress || 0),
            status: nextStatus,
        });

        const settings = window.myavanaTimelineSettings || {};
        const endpoint = settings.ajaxUrl || settings.ajaxurl || '/wp-admin/admin-ajax.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: payload,
            });
            const data = await parseAjaxResponse(response);
            if (!data || !data.success) {
                throw new Error((data && data.data) || 'Unable to update goal status');
            }
            try { window.sessionStorage.setItem('myavanaGoalFeedback', nextStatus === 'paused' ? 'Goal paused.' : 'Goal resumed.'); } catch (error) {}
            window.location.reload();
        } catch (error) {
            console.error('[Goals V2] Toggle pause failed', error);
            showFeedback(error.message || 'Unable to update goal status.', 'error');
        }
    }

    async function deleteGoal(goalId) {
        const id = Number(goalId);
        const goal = state.byId[id];
        if (Number.isNaN(id) || !goal) {
            return;
        }

        const confirmed = await requestGoalDeleteConfirmation(goal.title || 'this goal');
        if (!confirmed) {
            return;
        }

        const settings = window.myavanaTimelineSettings || {};
        const endpoint = settings.ajaxUrl || settings.ajaxurl || '/wp-admin/admin-ajax.php';
        const nonce = settings.deleteGoalNonce || settings.addGoalNonce || settings.updateGoalNonce || settings.nonce || '';
        const payload = new URLSearchParams();

        payload.set('action', 'myavana_delete_goal');
        payload.set('security', nonce);
        payload.set('goal_id', String(id));

        if (els.detailDeleteBtn) {
            els.detailDeleteBtn.disabled = true;
        }

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                credentials: 'same-origin',
                body: payload,
            });
            const data = await parseAjaxResponse(response);
            if (!data || !data.success) {
                throw new Error((data && data.data) || 'Unable to delete goal');
            }
            try { window.sessionStorage.setItem('myavanaGoalFeedback', 'Goal deleted.'); } catch (error) {}
            window.location.reload();
        } catch (error) {
            console.error('[Goals V2] Delete failed', error);
            showFeedback(error.message || 'Unable to delete goal.', 'error');
            if (els.detailDeleteBtn) {
                els.detailDeleteBtn.disabled = false;
            }
        }
    }

    function openGoalComposer(prefill) {
        const payload = prefill && typeof prefill === 'object' ? prefill : {};

        if (typeof window.openOffcanvas === 'function') {
            window.openOffcanvas('goal', payload);
        } else {
            showFeedback('Goal creation is loading. Please try again in a moment.', 'error');
            return;
        }

        window.setTimeout(() => {
            const titleInput = document.getElementById('goal_title');
            const descInput = document.getElementById('goal_description');
            const targetDate = document.getElementById('goal_end_date');
            const categoryHidden = document.getElementById('goal_category_hidden');
            const pills = document.querySelectorAll('#goalCategoryPills .tag-pill-hjn');

            if (payload.category && categoryHidden) {
                categoryHidden.value = payload.category;
                pills.forEach((pill) => {
                    const value = String(pill.getAttribute('data-value') || '').toLowerCase();
                    pill.classList.toggle('is-active', value === String(payload.category).toLowerCase());
                });
            }

            if (payload.title && titleInput) {
                titleInput.value = payload.title;
            }

            if (payload.description && descInput) {
                descInput.value = payload.description;
            }

            if (payload.target_date && targetDate) {
                targetDate.value = payload.target_date;
            }

            titleInput?.focus();
        }, 120);
    }

    function closePicker() {
        if (!els.picker || !els.pickerOverlay) return;
        els.picker.classList.remove('is-open');
        els.pickerOverlay.classList.remove('is-open');
        els.picker.setAttribute('aria-hidden', 'true');
        if (!els.drawer?.classList.contains('is-open') && !els.checkinModal?.classList.contains('is-open')) {
            document.body.style.overflow = '';
        }
    }

    function applyGoalTemplate(category) {
        closePicker();
        openGoalComposer({
            category: category,
            title: category + ' Goal'
        });
    }

    function bindEvents() {
        root.addEventListener('click', (event) => {
            const target = event.target;

            const dismissAlert = target.closest('[data-gv2-dismiss-alert]');
            if (dismissAlert) {
                const alert = document.getElementById('myavanaGv2Alert');
                if (alert) alert.style.display = 'none';
                return;
            }

            const openDetailTrigger = target.closest('[data-gv2-open-detail]');
            if (openDetailTrigger) {
                openDetail(openDetailTrigger.getAttribute('data-gv2-open-detail'));
                return;
            }

            const closeDetailTrigger = target.closest('[data-gv2-close-detail]');
            if (closeDetailTrigger) {
                closeDetail();
                return;
            }

            const openCheckinTrigger = target.closest('[data-gv2-open-checkin]');
            if (openCheckinTrigger) {
                openCheckin(openCheckinTrigger.getAttribute('data-gv2-open-checkin'));
                return;
            }

            const openCurrentCheckin = target.closest('[data-gv2-open-checkin-current]');
            if (openCurrentCheckin) {
                if (state.currentGoalId !== null) {
                    openCheckin(state.currentGoalId);
                }
                return;
            }

            const closeCheckinTrigger = target.closest('[data-gv2-close-checkin]');
            if (closeCheckinTrigger) {
                closeCheckin();
                return;
            }

            const openPickerTrigger = target.closest('[data-gv2-open-picker]');
            if (openPickerTrigger) {
                openGoalComposer();
                return;
            }

            const closePickerTrigger = target.closest('[data-gv2-close-picker]');
            if (closePickerTrigger) {
                closePicker();
                return;
            }

            const categoryTemplateBtn = target.closest('#myavanaGv2Picker [data-category]');
            if (categoryTemplateBtn) {
                applyGoalTemplate(String(categoryTemplateBtn.getAttribute('data-category') || 'Other'));
                return;
            }

            const categoryChip = target.closest('#myavanaGv2CategoryRow [data-category]');
            if (categoryChip) {
                setCategory(String(categoryChip.getAttribute('data-category') || 'all'));
                return;
            }

            const statusTab = target.closest('#myavanaGv2StatusTabs [data-status]');
            if (statusTab) {
                setStatusTab(String(statusTab.getAttribute('data-status') || 'all'));
                return;
            }

            const openRoutine = target.closest('[data-gv2-open-routine]');
            if (openRoutine) {
                const routineId = openRoutine.getAttribute('data-gv2-open-routine');
                if (typeof window.openViewOffcanvas === 'function') {
                    window.openViewOffcanvas('routine', Number(routineId));
                }
                return;
            }
        });

        if (els.overlay) {
            els.overlay.addEventListener('click', closeDetail);
        }

        if (els.drawerTabs) {
            els.drawerTabs.addEventListener('click', (event) => {
                const tab = event.target.closest('[data-tab]');
                if (!tab) return;
                setDrawerTab(tab.getAttribute('data-tab'));
            });
        }

        if (els.detailCheckinBtn) {
            els.detailCheckinBtn.addEventListener('click', () => {
                if (state.currentGoalId !== null) {
                    openCheckin(state.currentGoalId);
                }
            });
        }

        if (els.detailEditBtn) {
            els.detailEditBtn.addEventListener('click', () => {
                if (state.currentGoalId !== null) {
                    openGoalComposer(state.byId[state.currentGoalId]);
                }
            });
        }

        if (els.detailPauseBtn) {
            els.detailPauseBtn.addEventListener('click', togglePauseCurrentGoal);
        }

        if (els.detailDeleteBtn) {
            els.detailDeleteBtn.addEventListener('click', () => {
                if (state.currentGoalId !== null) {
                    deleteGoal(state.currentGoalId);
                }
            });
        }

        if (els.saveCheckinBtn) {
            els.saveCheckinBtn.addEventListener('click', saveCheckin);
        }

        // The check-in surface is portaled outside the root for reliable stacking,
        // so it needs its own close listener rather than root event delegation.
        els.checkinModal?.addEventListener('click', (event) => {
            if (event.target.closest('[data-gv2-close-checkin]')) closeCheckin();
        });
        els.pickerOverlay?.addEventListener('click', closePicker);

        if (els.checkinMoodRow) {
            els.checkinMoodRow.addEventListener('click', (event) => {
                const moodBtn = event.target.closest('[data-mood]');
                if (!moodBtn) return;
                state.mood = String(moodBtn.getAttribute('data-mood') || 'ok');
                els.checkinMoodRow.querySelectorAll('[data-mood]').forEach((btn) => {
                    btn.classList.toggle('is-selected', btn === moodBtn);
                });
            });
        }

        if (els.checkinStarRow) {
            els.checkinStarRow.addEventListener('click', (event) => {
                const starBtn = event.target.closest('[data-rating]');
                if (!starBtn) return;
                const rating = Number(starBtn.getAttribute('data-rating'));
                if (Number.isNaN(rating)) return;
                state.rating = rating;
                els.checkinStarRow.querySelectorAll('[data-rating]').forEach((btn) => {
                    const val = Number(btn.getAttribute('data-rating'));
                    btn.classList.toggle('is-selected', val <= rating);
                });
            });
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            if (els.picker?.classList.contains('is-open')) {
                closePicker();
                return;
            }

            if (els.checkinModal?.classList.contains('is-open')) {
                closeCheckin();
                return;
            }

            if (els.drawer?.classList.contains('is-open')) {
                closeDetail();
            }
        });
    }

    function init() {
        hydrate();
        movePortalLayersToBody();
        window.myavanaOpenGoalForm = openGoalComposer;
        window.deleteGoal = deleteGoal;
        bindEvents();
        applyFilters();
        runLucide();

        try {
            const feedback = window.sessionStorage.getItem('myavanaGoalFeedback');
            if (feedback) {
                window.sessionStorage.removeItem('myavanaGoalFeedback');
                window.setTimeout(() => showFeedback(feedback, 'success'), 80);
            }
        } catch (error) {}

        const params = new URLSearchParams(window.location.search);
        if (params.get('create') === 'goal') {
            openGoalComposer({
                category: params.get('category') || '',
                title: params.get('title') || ''
            });
        }
    }

    init();
})();
