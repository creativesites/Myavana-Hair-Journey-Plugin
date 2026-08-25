window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Today = (function() {
    'use strict';
    let container = null;

    function init() {
        container = document.querySelector('#view-today');
        if (!container) return;
        bindEvents();
        refresh();
    }

    async function refresh() {
        if (!container) return;
        try {
            const data = await MyavanaNext.API.get('today');
            MyavanaNext.Store.set('today', data);
            render(data);
        } catch (error) {
            console.error('[Today Refresh Error]', error);
        }
    }

    function render(data) {
        const dateLine = container.querySelector('#today-date-line');
        if (dateLine) {
            dateLine.textContent = new Date().toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });
        }
        const greeting = container.querySelector('.today-greeting-text');
        const greetingSubtext = container.querySelector('.today-greeting-subtext');
        if (greeting) greeting.textContent = data.greeting || 'Welcome back';
        if (greetingSubtext) greetingSubtext.textContent = data.greetingSubtext || "Let's take care of your hair today.";
        const dayCount = container.querySelector('#today-day-count');
        if (dayCount) dayCount.textContent = `Day ${data.dayCount || 1}`;

        renderChecklist(data.checklist || {});
        renderInsight(data.insight || null);
        renderProducts(data.routineProducts || []);
        renderLatestEntry(data.latestEntry || null);
        renderWeek(data.week || []);
        renderGoals(data.goals || []);
        renderUpcoming(data.upcomingGoals || []);
        renderMemory(data.memory || null);
    }

    function renderChecklist(checklist) {
        const list = container.querySelector('#today-checklist-items');
        const percent = container.querySelector('#today-checklist-percent');
        const items = Array.isArray(checklist.items) ? checklist.items : [];
        if (!list) return;

        if (!items.length) {
            if (percent) percent.hidden = true;
            list.innerHTML = `<div class="myavana-calm-empty"><p>No hair-care steps planned for today.</p><button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-open-routine>Set up a routine</button></div>`;
            list.querySelector('[data-open-routine]')?.addEventListener('click', () => MyavanaNext.App.navigate('routine'));
            return;
        }

        if (percent) {
            percent.hidden = false;
            percent.textContent = `${checklist.completionPercent || 0}% complete`;
        }
        list.innerHTML = items.map(item => `
            <div class="myavana-today-care-item ${item.isCompleted ? 'is-complete' : ''}">
                <button type="button" class="myavana-care-check" data-step-id="${escapeHtml(item.id)}" aria-label="${item.isCompleted ? 'Mark incomplete' : 'Mark complete'}">
                    ${item.isCompleted ? '✓' : ''}
                </button>
                <div><strong>${escapeHtml(item.name)}</strong><span>${escapeHtml(item.routineTitle || '')}${item.duration ? ` · ${escapeHtml(item.duration)}` : ''}</span></div>
            </div>`).join('');
        list.querySelectorAll('[data-step-id]').forEach(button => button.addEventListener('click', () => toggleStep(button.dataset.stepId)));
    }

    function renderInsight(insight) {
        const card = container.querySelector('#today-insight-card');
        if (!card) return;
        if (!insight || !insight.title) { card.style.display = 'none'; return; }

        card.style.display = 'block';
        container.querySelector('#today-insight-title').textContent = insight.title || 'Today’s insight';
        container.querySelector('#today-insight-observation').textContent = insight.summary || '';
        container.querySelector('#today-insight-action').textContent = insight.recommendation || '';

        const signals = Array.isArray(insight.supporting_signals) ? insight.supporting_signals : [];
        const signalsList = container.querySelector('#today-insight-signals');
        const whyToggle = container.querySelector('#today-insight-why-toggle');
        if (signalsList) {
            signalsList.innerHTML = signals.map(s => `<li>${escapeHtml(s)}</li>`).join('');
            signalsList.hidden = true;
        }
        if (whyToggle) {
            whyToggle.style.display = signals.length ? 'inline-flex' : 'none';
            whyToggle.setAttribute('aria-expanded', 'false');
            whyToggle.onclick = () => {
                const expanded = whyToggle.getAttribute('aria-expanded') === 'true';
                whyToggle.setAttribute('aria-expanded', String(!expanded));
                if (signalsList) signalsList.hidden = expanded;
            };
        }
    }

    function renderProducts(products) {
        const card = container.querySelector('#today-products-card');
        const grid = container.querySelector('#today-products-grid');
        if (!card || !grid) return;

        if (!products.length) { card.style.display = 'none'; return; }
        card.style.display = 'block';
        grid.innerHTML = products.map(p => `
            <div class="myavana-today-product">
                <strong>${escapeHtml(p.name)}</strong>
                <span>${escapeHtml(p.routine || p.category || '')}</span>
            </div>
        `).join('');
    }

    function renderLatestEntry(entry) {
        const target = container.querySelector('#today-latest-entry');
        if (!target) return;
        if (!entry) {
            target.innerHTML = `<div class="myavana-calm-empty"><p>Your timeline starts with one small update.</p><button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-open-entry>Add your first update</button></div>`;
            target.querySelector('[data-open-entry]')?.addEventListener('click', () => MyavanaNext.SmartEntry.open());
            return;
        }
        const image = entry.featuredImage || (entry.photos && entry.photos[0]);
        target.innerHTML = `<article class="myavana-latest-entry">${image ? `<img src="${escapeHtml(image)}" alt="" />` : '<div class="myavana-latest-entry-placeholder" aria-hidden="true">✦</div>'}<div><span>${escapeHtml(entry.date || '')}</span><strong>${escapeHtml(entry.title || 'Hair update')}</strong>${entry.notes ? `<p>${escapeHtml(entry.notes)}</p>` : ''}</div></article>`;
    }

    function renderWeek(week) {
        const el = container.querySelector('#today-week-strip');
        if (!el) return;
        el.innerHTML = week.map(d => `
            <div class="myavana-today-week-day">
                <span>${escapeHtml(d.label)}</span>
                <span class="myavana-today-week-mark" style="background:${d.hasEntry ? 'var(--myavana-coral)' : (d.isToday ? 'var(--myavana-onyx)' : 'var(--myavana-white)')};border:1px solid ${d.hasEntry || d.isToday ? 'transparent' : 'var(--myavana-border)'};color:${d.hasEntry ? '#fff' : (d.isToday ? '#fff' : 'var(--myavana-muted)')};">${d.hasEntry ? '✓' : (d.isToday ? '•' : '')}</span>
            </div>
        `).join('');
    }

    function renderGoals(goals) {
        const el = container.querySelector('#today-goals-list');
        if (!el) return;
        const valid = goals.filter(g => g.id || g.goal_key);
        if (!valid.length) { el.innerHTML = ''; return; }
        el.innerHTML = valid.map(g => `
            <div class="myavana-today-goal-row">
                <div class="myavana-today-goal-top"><span>${escapeHtml(g.title)}</span><span>${escapeHtml(g.progress || 0)}%</span></div>
                <div class="myavana-today-goal-bar"><div style="width:${Math.max(0, Math.min(100, g.progress || 0))}%;"></div></div>
            </div>
        `).join('');
    }

    function renderUpcoming(goals) {
        const card = container.querySelector('#today-upcoming-card');
        const list = container.querySelector('#today-upcoming-list');
        if (!card || !list) return;

        if (!goals.length) { card.style.display = 'none'; return; }
        card.style.display = 'block';
        list.innerHTML = goals.map(g => `
            <div class="myavana-today-upcoming-item">
                <span class="myavana-today-upcoming-dot"></span>
                <div><strong>${escapeHtml(g.title)}</strong><span>Target: ${escapeHtml(g.target_date)}</span></div>
            </div>
        `).join('');
    }

    function renderMemory(memory) {
        const card = container.querySelector('#today-memory-card');
        if (!card) return;
        if (!memory) { card.style.display = 'none'; return; }

        card.style.display = 'block';
        const image = memory.featuredImage || (memory.photos && memory.photos[0]);
        const bg = container.querySelector('#today-memory-bg');
        if (bg) bg.style.backgroundImage = image ? `url('${image}')` : 'none';
        container.querySelector('#today-memory-title').textContent = memory.title || `${memory.date} entry`;
        container.querySelector('#today-memory-link')?.addEventListener('click', () => MyavanaNext.App.navigate('journey'));
    }

    async function toggleStep(stepId) {
        try {
            await MyavanaNext.API.post('routine/toggle-step', { stepId });
            refresh();
        } catch (error) {
            MyavanaNext.API.showToast('We could not update that step. Please try again.', 'error');
        }
    }

    function bindEvents() {
        container.querySelector('#today-open-timeline')?.addEventListener('click', () => MyavanaNext.App.navigate('journey'));
    }

    function escapeHtml(value) { const node = document.createElement('div'); node.textContent = value || ''; return node.innerHTML; }
    return { init, refresh };
})();
