window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Today = (function() {
    'use strict';
    let container = null;
    let hasRendered = false;
    let inFlight = null;
    const FIRST_LOAD_RETRY_DELAYS_MS = [3000, 6000, 12000];

    function init() {
        container = document.querySelector('#view-today');
        if (!container) return;
        bindEvents();
        refresh();
    }

    function refresh() {
        if (!container) return Promise.resolve();
        if (!inFlight) {
            inFlight = load().finally(() => { inFlight = null; });
        }
        return inFlight;
    }

    async function load() {
        let data;
        for (let attempt = 0; ; attempt++) {
            try {
                data = await MyavanaNext.API.get('today');
                break;
            } catch (error) {
                if (error.sessionExpired) return;
                // A failed background refresh keeps the view already on screen.
                if (hasRendered) return;
                // First load: keep the skeleton and retry on our own, as the
                // member would; the error state is for a genuine outage only.
                if (attempt >= FIRST_LOAD_RETRY_DELAYS_MS.length) {
                    renderLoadError();
                    return;
                }
                await new Promise(resolve => window.setTimeout(resolve, FIRST_LOAD_RETRY_DELAYS_MS[attempt]));
            }
        }
        MyavanaNext.Store.set('today', data);
        render(data);
        hasRendered = true;
    }

    function renderLoadError() {
        // The checklist card isn't rendered while Routines is switched off.
        const list = container.querySelector('#today-checklist-items') || container.querySelector('#today-latest-entry');
        if (!list) return;
        list.innerHTML = `
            <div class="myavana-today-error-state">
                <p>We couldn't load today's view. Your journey data is still safe.</p>
                <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" id="today-retry-btn">Try again</button>
            </div>`;
        list.querySelector('#today-retry-btn')?.addEventListener('click', refresh);
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
        if (dayCount) dayCount.textContent = `Day ${data.dayCount || 1} of your journey`;
        renderFocus(data.focus || [], data.focusGoals || []);
        renderPortrait(data.latestEntry || (data.recentEntries || [])[0] || null);

        renderChecklist(data.checklist || {});
        renderInsight(data.insight || null);
        renderProducts(data.routineProducts || []);
        renderRecentEntries(data.recentEntries && data.recentEntries.length ? data.recentEntries : (data.latestEntry ? [data.latestEntry] : []));
        renderWeek(data.week || []);
        renderGoals(data.goals || []);
        renderUpcoming(data.upcomingGoals || []);
        renderMemory(data.memory || null);
    }

    // The goals she chose at signup, in her own words: "You're focused on
    // more moisture and hair growth."
    function renderFocus(focus, goalTitles) {
        const el = container.querySelector('#today-focus');
        if (!el) return;
        const items = focus.length ? focus : goalTitles;
        if (!items.length) { el.hidden = true; return; }
        const parts = items.map(f => `<strong>${escapeHtml(f)}</strong>`);
        const list = parts.length > 1 ? `${parts.slice(0, -1).join(', ')} and ${parts[parts.length - 1]}` : parts[0];
        el.innerHTML = focus.length ? `You're focused on ${list}.` : `You're working toward ${list}.`;
        el.hidden = false;
    }

    // Her latest hair photo in the hero, unless she has chosen a profile photo.
    function renderPortrait(entry) {
        const portrait = container.querySelector('#today-portrait');
        if (!portrait || portrait.dataset.custom === '1' || portrait.querySelector('img:not([data-from-entry])')) return;
        const image = entry && (entry.featuredImage || (entry.photos && entry.photos[0]));
        if (!image) return;
        portrait.innerHTML = `<img src="${escapeHtml(image)}" alt="" data-from-entry="1" />`;
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

    function renderRecentEntries(entries) {
        const target = container.querySelector('#today-latest-entry');
        if (!target) return;
        if (!entries.length) {
            target.innerHTML = `<div class="myavana-calm-empty"><p>Your timeline starts with one small update.</p><button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-open-entry>Add your first update</button></div>`;
            target.querySelector('[data-open-entry]')?.addEventListener('click', () => MyavanaNext.SmartEntry.open());
            return;
        }

        const [latest, ...rest] = entries;
        const latestImage = latest.featuredImage || (latest.photos && latest.photos[0]);

        target.innerHTML = `
            <article class="myavana-latest-entry">
                ${latestImage ? `<img src="${escapeHtml(latestImage)}" alt="" />` : '<div class="myavana-latest-entry-placeholder" aria-hidden="true">✦</div>'}
                <div><span>${escapeHtml(formatEntryDate(latest.date))}</span><strong>${escapeHtml(latest.title || 'Hair update')}</strong>${latest.notes ? `<p>${escapeHtml(latest.notes)}</p>` : ''}</div>
            </article>
            ${rest.length ? `
                <div class="myavana-today-story-strip" role="list" aria-label="More recent entries">
                    ${rest.map(entry => {
                        const image = entry.featuredImage || (entry.photos && entry.photos[0]);
                        return `
                            <button type="button" class="myavana-today-story-item" role="listitem" data-entry-id="${escapeHtml(String(entry.id || ''))}" aria-label="${escapeHtml(entry.title || 'Hair update')}, ${escapeHtml(formatEntryDate(entry.date))}">
                                <span class="myavana-today-story-ring">
                                    ${image ? `<img src="${escapeHtml(image)}" alt="" />` : '<span class="myavana-today-story-placeholder" aria-hidden="true">✦</span>'}
                                </span>
                                <span class="myavana-today-story-label">${escapeHtml(formatEntryDate(entry.date, isWithinWeek(entry.date)))}</span>
                            </button>`;
                    }).join('')}
                </div>
            ` : ''}
        `;

        target.querySelectorAll('.myavana-today-story-item[data-entry-id]').forEach(btn => {
            btn.addEventListener('click', () => {
                if (MyavanaNext.Journey && typeof MyavanaNext.Journey.focusEntry === 'function') {
                    MyavanaNext.Journey.focusEntry(btn.dataset.entryId);
                }
                MyavanaNext.App.navigate('journey');
            });
        });
    }

    /**
     * Entry dates arrive as raw MySQL datetimes from the REST payload — turn
     * them into something a story strip can show in a couple of characters
     * (short = weekday only, e.g. "Wed") or a friendlier full label.
     */
    function isWithinWeek(mysqlDate) {
        const parsed = new Date(String(mysqlDate || '').replace(' ', 'T'));
        return !isNaN(parsed.getTime()) && (Date.now() - parsed.getTime()) < 6 * 86400000;
    }

    function formatEntryDate(mysqlDate, short = false) {
        if (!mysqlDate) return '';
        const parsed = new Date(String(mysqlDate).replace(' ', 'T'));
        if (isNaN(parsed.getTime())) return short ? '' : String(mysqlDate);
        return parsed.toLocaleDateString('en-US', short ? { weekday: 'short' } : { month: 'short', day: 'numeric' });
    }

    function renderWeek(week) {
        const el = container.querySelector('#today-week-strip');
        if (!el) return;
        el.innerHTML = week.map(d => `
            <div class="myavana-today-week-day${d.isToday ? ' is-today' : ''}">
                <span>${escapeHtml(d.label)}</span>
                <span class="myavana-today-week-mark${d.hasEntry ? ' has-entry' : ''}" aria-label="${d.hasEntry ? 'Logged' : 'No entry'}">${d.hasEntry ? '✓' : ''}</span>
            </div>
        `).join('');
    }

    function renderGoals(goals) {
        const el = container.querySelector('#today-goals-list');
        if (!el) return;
        const valid = goals.filter(g => g.title || g.goal_title);
        if (!valid.length) { el.innerHTML = ''; return; }
        el.innerHTML = valid.map(g => {
            const pct = Math.max(0, Math.min(100, Math.round(Number(g.progress) || 0)));
            return `
            <div class="myavana-today-goal-row">
                <div class="myavana-today-goal-top"><span>${escapeHtml(g.title || g.goal_title)}</span><span>${pct}%</span></div>
                <div class="myavana-today-goal-bar"><div style="width:${pct}%;"></div></div>
            </div>`;
        }).join('');
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
                <div><strong>${escapeHtml(g.title)}</strong><span>${escapeHtml(formatTarget(g.target_date))}</span></div>
            </div>
        `).join('');
    }

    function formatTarget(value) {
        const date = new Date(`${value}T00:00:00`);
        if (Number.isNaN(date.getTime())) return '';
        const days = Math.round((date - new Date(new Date().toDateString())) / 86400000);
        const label = date.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: date.getFullYear() === new Date().getFullYear() ? undefined : 'numeric' });
        if (days === 0) return 'Target: today';
        if (days <= 30) return `Target: ${label} · ${days} day${days === 1 ? '' : 's'} to go`;
        return `Target: ${label}`;
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
        container.querySelectorAll('[data-feel]').forEach(btn => btn.addEventListener('click', () => {
            if (MyavanaNext.SmartEntry) MyavanaNext.SmartEntry.open({ mood: btn.dataset.feel });
        }));
        container.querySelector('#today-open-timeline')?.addEventListener('click', () => MyavanaNext.App.navigate('journey'));
    }

    function escapeHtml(value) { const node = document.createElement('div'); node.textContent = value ?? ''; return node.innerHTML; }
    return { init, refresh };
})();
