/**
 * My Hair Timeline — Timeline / Story view controller
 *
 * @package Myavana\Next
 */

window.MyavanaNext = window.MyavanaNext || {};

MyavanaNext.Journey = (function() {
    'use strict';

    let container = null;
    let data = null;
    let view = 'timeline';
    let activeFilter = 'all';
    let storyIndex = 0;
    let compareOn = false;
    let compareSlot = 'A';
    let compareA = null;
    let compareB = null;
    let shareContext = null;
    let pendingFocusEntryId = null;
    let inFlight = null;
    const FIRST_LOAD_RETRY_DELAYS_MS = [3000, 6000, 12000];

    const TYPE_LABELS = { wash_day: 'Wash day', length_check: 'Length check', milestone: 'Milestone', setback: 'Setback', quick_checkin: 'Check-in', standard: 'Entry', ai_analysis: 'AI analysis', progress: 'Progress', treatment: 'Treatment', style: 'Style', general: 'Entry' };
    const typeLabel = (type) => TYPE_LABELS[type] || (type ? String(type).replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase()) : 'Entry');
    const MOOD_LABELS = { happy: '✨ Great', neutral: '🌿 Normal', dry: '🍂 Dry', itchy: '💆 Sensitive' };

    function init() {
        container = document.querySelector('#view-journey');
        if (!container) return;

        bindTabs();
        bindStoryControls();
        bindShareModal();
        bindTimelineActions();
    }

    function refresh() {
        if (!container) return Promise.resolve();
        if (!inFlight) {
            inFlight = load().finally(() => { inFlight = null; });
        }
        return inFlight;
    }

    async function load() {
        let fresh;
        for (let attempt = 0; ; attempt++) {
            try {
                fresh = await MyavanaNext.API.get('journal/workspace');
                break;
            } catch (err) {
                if (err.sessionExpired) return;
                // Keep an already-rendered timeline rather than replacing it.
                if (data) return;
                if (attempt >= FIRST_LOAD_RETRY_DELAYS_MS.length) {
                    renderLoadError();
                    return;
                }
                await new Promise(resolve => window.setTimeout(resolve, FIRST_LOAD_RETRY_DELAYS_MS[attempt]));
            }
        }
        data = fresh;
        storyIndex = 0;
        renderAll();
    }

    function renderLoadError() {
        const rail = container.querySelector('#journey-timeline-rail');
        if (!rail) return;
        rail.innerHTML = `
            <div class="myavana-calm-empty">
                <p>We couldn't load your timeline. Your entries are still safe.</p>
                <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" id="journey-retry-btn">Try again</button>
            </div>`;
        rail.querySelector('#journey-retry-btn')?.addEventListener('click', refresh);
    }

    function renderAll() {
        renderHeader();
        renderStats();
        renderFilters();
        renderTimeline();
        renderGoals();
        renderSparkline();
        renderRoutines();
        renderStorySegments();
        renderStoryThumbs();
        renderStorySlide();
        applyPendingFocus();
    }

    /**
     * Called from Today's story strip: switch to the timeline view (a
     * single entry has no dedicated detail screen of its own) and, once
     * this refresh finishes rendering, scroll to and briefly highlight the
     * matching card so the tap visibly landed somewhere.
     */
    function focusEntry(entryId) {
        pendingFocusEntryId = String(entryId || '');
        switchView('timeline');
    }

    function applyPendingFocus() {
        if (!pendingFocusEntryId) return;
        const id = pendingFocusEntryId;
        pendingFocusEntryId = null;

        const card = container.querySelector(`.myavana-timeline-card[data-entry-id="${id}"]`);
        if (!card) return;
        window.requestAnimationFrame(() => {
            card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            card.classList.add('is-focused');
            window.setTimeout(() => card.classList.remove('is-focused'), 2200);
        });
    }

    // =========================
    // HEADER + STATS
    // =========================

    function renderHeader() {
        const dayCount = data.dayCount || 1;
        const subtitle = container.querySelector('#journey-subtitle');
        if (subtitle) subtitle.textContent = `Day ${dayCount} of your journey`;
    }

    function renderStats() {
        const el = container.querySelector('#journey-stats');
        if (!el) return;
        const s = data.stats;
        el.innerHTML = [
            statCard(s.currentLength ? `${s.currentLength}"` : '—', 'Current length'),
            statCard(`${s.entriesThisMonth || 0}`, 'Entries this month', true),
            statCard(`${s.totalEntries}`, 'Journey entries'),
            statCard(`${s.photoCount}`, 'Photos logged'),
        ].join('');
    }

    function statCard(value, label, accent) {
        return `<div class="myavana-journey-stat${accent ? ' accent' : ''}"><strong>${escapeHtml(value)}</strong><span>${escapeHtml(label)}</span></div>`;
    }

    // =========================
    // TIMELINE
    // =========================

    function renderFilters() {
        const el = container.querySelector('#journey-filters');
        if (!el) return;
        // Only offer filters for kinds of entry she has actually logged.
        const present = new Set((data.timeline.items || []).map((e) => e.entryType));
        const filters = [['all', 'All']].concat([
            ['wash_day', 'Wash days'], ['length_check', 'Length'], ['milestone', 'Milestones'],
            ['ai_analysis', 'AI analysis'], ['setback', 'Setbacks'], ['quick_checkin', 'Check-ins'],
        ].filter(([key]) => present.has(key)));
        if (filters.length <= 2) { el.innerHTML = ''; return; }
        el.innerHTML = filters.map(([key, label]) => `
            <button type="button" class="myavana-entry-pill${key === activeFilter ? ' active' : ''}" data-filter="${key}">${escapeHtml(label)}</button>
        `).join('');
        el.querySelectorAll('[data-filter]').forEach((btn) => {
            btn.addEventListener('click', () => {
                activeFilter = btn.getAttribute('data-filter');
                renderFilters();
                renderTimeline();
            });
        });
    }

    function renderTimeline() {
        const rail = container.querySelector('#journey-timeline-rail');
        if (!rail) return;

        const groups = (data.groups || [])
            .map((g) => ({ ...g, entries: activeFilter === 'all' ? g.entries : g.entries.filter((e) => e.entryType === activeFilter) }))
            .filter((g) => g.entries.length > 0);

        if (groups.length === 0) {
            rail.innerHTML = `<div class="myavana-calm-empty"><p>No entries yet. Log your first wash day, length check, or milestone to start your timeline.</p></div>`;
            return;
        }

        rail.innerHTML = groups.map((group) => `
            <div class="myavana-timeline-month">
                <div class="myavana-timeline-month-head"><strong>${escapeHtml(group.month)}</strong><span>${escapeHtml(group.summary)}</span>${group.monthKey && group.monthKey < new Date().toISOString().slice(0, 7) ? `<button type="button" class="myavana-timeline-recap-link" data-recap-month="${escapeHtml(group.monthKey)}">Month recap</button>` : ''}</div>
                ${group.entries.map(renderEntryCard).join('')}
            </div>
        `).join('') + renderOriginNode();

        bindCardActions(rail);
    }

    // Quiet markers for things that really happened (first photo, one month
    // in, goal reached). "Where it started" has its own node at the end.
    function momentsFor(entry) {
        const all = (data && data.moments && data.moments[entry.id]) || [];
        return all.filter((m) => m.key !== 'first_entry');
    }

    function renderEntryCard(entry) {
        const tags = [];
        if (entry.mood && MOOD_LABELS[entry.mood]) tags.push(MOOD_LABELS[entry.mood]);
        if (entry.goalId) {
            const goal = (data.goals || []).find((g) => g.id === entry.goalId || g.goal_key === entry.goalId);
            if (goal) tags.push(goal.title);
        }

        return `
        <div class="myavana-timeline-entry">
            <div class="myavana-timeline-node"></div>
            ${entry.showGap ? `<div class="myavana-timeline-gap">${escapeHtml(entry.gap)}</div>` : ''}
            <div class="myavana-timeline-card${momentsFor(entry).length ? ' has-moment' : ''}" data-entry-id="${entry.id}">
                ${momentsFor(entry).map((m) => `<div class="myavana-timeline-moment" data-moment="${escapeHtml(m.key)}"><span class="myavana-timeline-moment-mark" aria-hidden="true"></span>${escapeHtml(m.label)}</div>`).join('')}
                <div class="myavana-timeline-card-head">
                    <div class="myavana-timeline-card-kicker">
                        <strong>${escapeHtml(typeLabel(entry.entryType))}</strong>
                        <span>${escapeHtml(entry.displayDate || entry.date)}</span>
                    </div>
                    <div class="myavana-timeline-card-tags">${tags.map((t) => `<span>${escapeHtml(t)}</span>`).join('')}</div>
                </div>

                ${entry.title ? `<h3>${escapeHtml(entry.title)}</h3>` : ''}

                ${entry.hairLength ? `
                <div class="myavana-timeline-card-length">
                    <strong>${escapeHtml(entry.hairLength)}"</strong>
                    <span>${escapeHtml(entry.hairLengthPoint || 'overall')}</span>
                </div>` : ''}

                ${entry.notes ? `<p data-role="notes-display">${escapeHtml(entry.notes)}</p>` : ''}

                ${(() => {
                    // Older entries used WordPress's post-thumbnail mechanism
                    // instead of the entry_photos meta array — fall back to
                    // it so those entries still show their photo here.
                    // A video-only entry's featuredImage is its poster; the
                    // video tile already shows it.
                    const videos = entry.videos || [];
                    const cardPhotos = (entry.photos || []).length ? entry.photos : (entry.featuredImage && !videos.length ? [entry.featuredImage] : []);
                    return (cardPhotos.length || videos.length) ? `
                <div class="myavana-timeline-card-photos${videos.length ? ' has-video' : ''}">
                    ${videos.map((v) => MyavanaNext.Media.videoTile(v)).join('')}
                    ${cardPhotos.map((p) => `<img src="${escapeHtml(p)}" alt="" loading="lazy" />`).join('')}
                </div>` : '';
                })()}

                ${entry.changeDescription ? `
                <div class="myavana-timeline-card-change">
                    <strong>What I changed</strong>
                    <span>${escapeHtml(entry.changeDescription)}</span>
                </div>` : ''}

                ${(entry.productsUsed || []).length ? `
                <div class="myavana-timeline-card-tags">
                    <span style="background:none;padding:0;color:var(--myavana-muted);font-size:10.5px;letter-spacing:.08em;text-transform:uppercase;">Used</span>
                    ${entry.productsUsed.map((p) => `<span>${escapeHtml(p)}</span>`).join('')}
                </div>` : ''}

                <div class="myavana-timeline-card-actions">
                    <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-action="edit">Edit</button>
                    <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-action="share">Share</button>
                    <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm myavana-btn-danger-outline" data-action="delete">Delete</button>
                </div>
            </div>
        </div>`;
    }

    function shortDate(value) {
        const d = new Date(String(value || '').replace(' ', 'T'));
        return Number.isNaN(d.getTime()) ? String(value || '') : d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    }

    function formatLongDate(value) {
        const d = new Date(String(value || '').replace(' ', 'T'));
        return Number.isNaN(d.getTime()) ? '' : d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
    }

    function renderOriginNode() {
        const all = data.timeline.items;
        if (!all.length) return '';
        const first = all[all.length - 1];
        const firstDate = new Date(String(first.date).replace(' ', 'T'));
        const firstLabel = Number.isNaN(firstDate.getTime()) ? '' : firstDate.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
        return `
        <div class="myavana-timeline-entry myavana-timeline-origin">
            <div class="myavana-timeline-node" style="background:var(--myavana-onyx);box-shadow:0 0 0 2px var(--myavana-onyx);"></div>
            <strong>Where it started</strong>
            <h4>${escapeHtml(first.title || 'Your first entry')}</h4>
            ${firstLabel ? `<span class="myavana-timeline-origin-date">${escapeHtml(firstLabel)}</span>` : ''}
            <p>${escapeHtml(first.notes || 'Everything above is what came after.')}</p>
        </div>`;
    }

    function bindCardActions(scope) {
        scope.querySelectorAll('.myavana-timeline-card').forEach((card) => {
            const entryId = parseInt(card.getAttribute('data-entry-id'), 10);
            const entry = findEntryById(entryId);
            if (!entry) return;

            card.querySelector('[data-action="edit"]')?.addEventListener('click', () => toggleInlineEdit(card, entry));
            card.querySelector('[data-action="share"]')?.addEventListener('click', () => openShareModal(entry));
            card.querySelector('[data-action="delete"]')?.addEventListener('click', () => deleteEntry(entry));
        });
    }

    async function deleteEntry(entry) {
        const label = entry.title || TYPE_LABELS[entry.entryType] || 'this entry';
        if (!window.confirm(`Delete "${label}"? This can't be undone.`)) return;

        try {
            await MyavanaNext.API.delete(`journal/entries/${entry.id}`);
            MyavanaNext.API.showToast('Entry deleted', 'success');
            refresh();
        } catch (err) {
            console.error('[Journey] Delete failed', err);
        }
    }

    function toggleInlineEdit(card, entry) {
        const display = card.querySelector('[data-role="notes-display"]');
        const existing = card.querySelector('[data-role="notes-edit"]');
        if (existing) return;

        const editWrap = document.createElement('div');
        editWrap.setAttribute('data-role', 'notes-edit');
        editWrap.style.display = 'flex';
        editWrap.style.flexDirection = 'column';
        editWrap.style.gap = '8px';
        editWrap.innerHTML = `
            <textarea class="myavana-textarea" rows="3">${escapeHtml(entry.notes || '')}</textarea>
            <div style="display:flex;gap:8px;">
                <button type="button" class="myavana-btn myavana-btn-primary myavana-btn-sm" data-role="save">Save</button>
                <button type="button" class="myavana-btn myavana-btn-outline myavana-btn-sm" data-role="cancel">Cancel</button>
            </div>
        `;

        if (display) display.style.display = 'none';
        card.querySelector('.myavana-timeline-card-actions').before(editWrap);

        editWrap.querySelector('[data-role="cancel"]').addEventListener('click', () => {
            editWrap.remove();
            if (display) display.style.display = '';
        });

        editWrap.querySelector('[data-role="save"]').addEventListener('click', async () => {
            const newNotes = editWrap.querySelector('textarea').value;
            try {
                await MyavanaNext.API.put(`journal/entries/${entry.id}`, { notes: newNotes });
                MyavanaNext.API.showToast('Entry updated', 'success');
                refresh();
            } catch (err) {
                console.error('[Journey] Save failed', err);
            }
        });
    }

    function bindTimelineActions() {
        // Reserved for future delegated bindings outside per-card scope.
    }

    function findEntryById(id) {
        return (data.timeline.items || []).find((e) => e.id === id);
    }

    // =========================
    // SIDEBAR: GOALS / SPARKLINE / ROUTINES
    // =========================

    function renderGoals() {
        const el = container.querySelector('#journey-goals-list');
        if (!el) return;
        const goals = (data.goals || []).filter((g) => g.title || g.goal_title);

        if (!goals.length) {
            el.innerHTML = '<p class="myavana-entry-hint">No active goals yet.</p>';
            return;
        }

        el.innerHTML = goals.map((g) => {
            const pct = Math.max(0, Math.min(100, Math.round(Number(g.progress) || 0)));
            return `
            <div class="myavana-journey-goal-row">
                <div class="myavana-journey-goal-top"><span>${escapeHtml(g.title)}</span><span>${pct}%</span></div>
                <div class="myavana-journey-goal-bar"><div style="width:${pct}%;"></div></div>
            </div>`;
        }).join('');
    }

    function renderSparkline() {
        const card = container.querySelector('#journey-sparkline-card');
        if (!card) return;
        const sp = data.sparkline;

        if (!sp) {
            card.style.display = 'none';
            return;
        }
        card.style.display = 'block';

        container.querySelector('#journey-sparkline-checks').textContent = sp.checks;
        container.querySelector('#journey-sparkline-current').textContent = sp.current;
        container.querySelector('#journey-sparkline-gain').textContent = sp.gain;
        container.querySelector('#journey-sparkline-first').textContent = sp.first;
        container.querySelector('#journey-sparkline-last').textContent = sp.last;
        container.querySelector('#journey-sparkline-area').setAttribute('d', sp.area);
        container.querySelector('#journey-sparkline-line').setAttribute('points', sp.points);
        container.querySelector('#journey-sparkline-dot').setAttribute('cx', sp.lastX);
        container.querySelector('#journey-sparkline-dot').setAttribute('cy', sp.lastY);
    }

    function renderRoutines() {
        const el = container.querySelector('#journey-routines-list');
        if (!el) return;
        const card = container.querySelector('#journey-routines-card');
        if (card) card.style.display = data.routinesEnabled === false ? 'none' : '';
        if (data.routinesEnabled === false) return;
        const routines = data.routines || [];

        if (!routines.length) {
            el.innerHTML = '<p class="myavana-entry-hint">No routines set up yet.</p>';
            return;
        }

        el.innerHTML = routines.map((r) => `
            <div class="myavana-journey-routine-item">
                <strong>${escapeHtml(r.title)}</strong>
                <span>${escapeHtml(r.frequency)} · ${(r.steps || []).length} steps</span>
            </div>
        `).join('');
    }

    // =========================
    // TABS
    // =========================

    function bindTabs() {
        container.querySelector('#journey-tab-timeline')?.addEventListener('click', () => switchView('timeline'));
        container.querySelector('#journey-tab-story')?.addEventListener('click', () => switchView('story'));
    }

    function switchView(next) {
        view = next;
        container.querySelector('#journey-tab-timeline')?.classList.toggle('active', view === 'timeline');
        container.querySelector('#journey-tab-story')?.classList.toggle('active', view === 'story');
        container.querySelector('#journey-pane-timeline').style.display = view === 'timeline' ? 'block' : 'none';
        container.querySelector('#journey-pane-story').style.display = view === 'story' ? 'block' : 'none';
    }

    // =========================
    // STORY MODE
    // =========================

    function storyPhotos() {
        return data.photoEntries || [];
    }

    function bindStoryControls() {
        container.querySelector('#story-prev')?.addEventListener('click', () => moveStory(-1));
        container.querySelector('#story-next')?.addEventListener('click', () => moveStory(1));
        container.querySelector('#story-mode-single')?.addEventListener('click', () => setCompareMode(false));
        container.querySelector('#story-mode-compare')?.addEventListener('click', () => setCompareMode(true));
        container.querySelector('#story-view-entry')?.addEventListener('click', () => {
            const photo = storyPhotos()[storyIndex];
            if (!photo) return;
            const entry = findEntryById(photo.id);
            if (entry) openShareModal(entry, false);
        });
        container.querySelector('#story-share')?.addEventListener('click', () => {
            const photo = storyPhotos()[storyIndex];
            if (!photo) return;
            const entry = findEntryById(photo.id);
            if (entry) openShareModal(entry);
        });

        document.addEventListener('keydown', (e) => {
            if (view !== 'story' || compareOn) return;
            if (e.key === 'ArrowLeft') moveStory(-1);
            if (e.key === 'ArrowRight') moveStory(1);
        });
    }

    function moveStory(delta) {
        const photos = storyPhotos();
        if (!photos.length) return;
        storyIndex = (storyIndex + delta + photos.length) % photos.length;
        renderStorySlide();
        renderStoryThumbs();
        renderStorySegments();
    }

    function setCompareMode(on) {
        compareOn = on;
        container.querySelector('#story-mode-single')?.classList.toggle('active', !on);
        container.querySelector('#story-mode-compare')?.classList.toggle('active', on);
        container.querySelector('#story-slide-single').style.display = on ? 'none' : 'block';
        container.querySelector('#story-compare').style.display = on ? 'block' : 'none';
        container.querySelector('#story-segments').style.display = on ? 'none' : 'flex';
        const hint = container.querySelector('#story-compare-hint');
        if (hint) {
            hint.style.display = on ? 'inline' : 'none';
            hint.textContent = compareSlot === 'A' ? 'Tap a photo to set A' : 'Tap a photo to set B';
        }
        if (on) renderCompare();
    }

    function renderStorySegments() {
        const el = container.querySelector('#story-segments');
        if (!el) return;
        const photos = storyPhotos();
        el.innerHTML = photos.map((_, i) => `<span class="${i === storyIndex ? 'active' : ''}" data-idx="${i}"></span>`).join('');
        el.querySelectorAll('[data-idx]').forEach((seg) => {
            seg.addEventListener('click', () => {
                storyIndex = parseInt(seg.getAttribute('data-idx'), 10);
                renderStorySlide();
                renderStoryThumbs();
                renderStorySegments();
            });
        });
    }

    function renderStoryThumbs() {
        const el = container.querySelector('#story-thumbs');
        if (!el) return;
        const photos = storyPhotos();
        el.innerHTML = photos.map((p, i) => `
            <div class="${i === storyIndex ? 'active' : ''}" data-idx="${i}">
                <img src="${escapeHtml(p.imageUrl)}" alt="" loading="lazy" />
                <span>${escapeHtml(shortDate(p.date))}</span>
            </div>
        `).join('');
        el.querySelectorAll('[data-idx]').forEach((thumb) => {
            thumb.addEventListener('click', () => {
                const idx = parseInt(thumb.getAttribute('data-idx'), 10);
                if (compareOn) {
                    setComparePhoto(photos[idx]);
                } else {
                    storyIndex = idx;
                    renderStorySlide();
                    renderStoryThumbs();
                    renderStorySegments();
                }
            });
        });
    }

    function renderStorySlide() {
        const photos = storyPhotos();
        const photo = photos[storyIndex];
        if (!photo) {
            container.querySelector('#story-slide-single').style.display = 'none';
            return;
        }

        const entry = findEntryById(photo.id);
        container.querySelector('#story-slide-bg').style.backgroundImage = `url('${photo.imageUrl}')`;
        const slide = container.querySelector('#story-slide-single');
        let play = slide.querySelector('.myavana-story-play');
        if (photo.video && photo.video.url) {
            if (!play) {
                play = document.createElement('button');
                play.type = 'button';
                play.className = 'myavana-story-play';
                play.setAttribute('aria-label', 'Play video');
                play.innerHTML = '<svg viewBox="0 0 24 24" width="26" height="26"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5Z" fill="currentColor"/></svg>';
                slide.appendChild(play);
            }
            play.setAttribute('data-play-video', photo.video.url);
            play.setAttribute('data-poster', photo.video.poster || photo.imageUrl || '');
            play.hidden = false;
        } else if (play) {
            play.hidden = true;
        }
        container.querySelector('#story-kicker').textContent = typeLabel(photo.entryType);
        container.querySelector('#story-date').textContent = formatLongDate(photo.date);

        const moodEl = container.querySelector('#story-mood');
        if (photo.mood && MOOD_LABELS[photo.mood]) {
            moodEl.style.display = 'inline-block';
            moodEl.textContent = MOOD_LABELS[photo.mood];
        } else {
            moodEl.style.display = 'none';
        }

        container.querySelector('#story-headline').textContent = photo.title || (entry && entry.title) || 'Hair journey update';
        container.querySelector('#story-caption').textContent = photo.caption || '';
        container.querySelector('#story-position').textContent = `${storyIndex + 1} / ${photos.length}`;
    }

    function setComparePhoto(photo) {
        if (compareSlot === 'A') {
            compareA = photo;
            compareSlot = 'B';
        } else {
            compareB = photo;
            compareSlot = 'A';
        }
        const hint = container.querySelector('#story-compare-hint');
        if (hint) hint.textContent = compareSlot === 'A' ? 'Tap a photo to set A' : 'Tap a photo to set B';
        renderCompare();
    }

    function renderCompare() {
        const grid = container.querySelector('#story-compare-grid');
        if (!grid) return;

        const photos = storyPhotos();
        if (!compareA) compareA = photos[photos.length - 1] || null;
        if (!compareB) compareB = photos[0] || null;

        grid.innerHTML = [compareA, compareB].map((p, i) => p ? `
            <div class="myavana-journey-story-compare-pane">
                <img src="${escapeHtml(p.imageUrl)}" alt="" />
                <strong>${escapeHtml(shortDate(p.date))}</strong>
                <span>${i === 0 ? 'A' : 'B'} · ${escapeHtml(typeLabel(p.entryType))}</span>
            </div>` : '<div class="myavana-journey-story-compare-pane"></div>'
        ).join('');
    }

    // =========================
    // SHARE MODAL
    // =========================

    function openShareModal(entry) {
        shareContext = entry;
        const modal = container.querySelector('#journey-share-modal');
        if (!modal) return;

        container.querySelector('#journey-share-title').textContent = entry.title || TYPE_LABELS[entry.entryType] || 'Hair update';
        container.querySelector('#journey-share-caption').value = entry.notes || '';
        const preview = container.querySelector('#journey-share-preview');
        const img = entry.featuredImage || (entry.photos && entry.photos[0]) || '';
        preview.style.backgroundImage = img ? `url('${img}')` : 'none';

        const destinations = [
            ['Copy link', 'copy_link', false],
            ['Download image', 'download', false],
            ['MYAVANA community', 'community', true],
            ['Instagram story', 'instagram', true],
            ['Send to my stylist', 'stylist', true],
        ];
        container.querySelector('#journey-share-destinations').innerHTML = destinations.map(([label, key, comingSoon]) => `
            <button type="button" data-dest="${key}"${comingSoon ? ' disabled aria-disabled="true"' : ''}>${escapeHtml(label)}<span>${comingSoon ? 'Soon' : '›'}</span></button>
        `).join('');
        container.querySelectorAll('[data-dest]:not(:disabled)').forEach((btn) => {
            btn.addEventListener('click', () => handleShareDestination(btn.getAttribute('data-dest')));
        });

        modal.style.display = 'block';
        modal.classList.add('active');
    }

    function closeShareModal() {
        const modal = container.querySelector('#journey-share-modal');
        if (!modal) return;
        modal.style.display = 'none';
        modal.classList.remove('active');
        shareContext = null;
    }

    function bindShareModal() {
        const modal = container.querySelector('#journey-share-modal');
        if (!modal) return;
        container.querySelector('#journey-share-close')?.addEventListener('click', closeShareModal);
        modal.addEventListener('click', (e) => { if (e.target === modal) closeShareModal(); });
    }

    async function handleShareDestination(key) {
        if (!shareContext) return;
        const img = shareContext.featuredImage || (shareContext.photos && shareContext.photos[0]) || '';

        if (key === 'copy_link') {
            const url = `${window.location.origin}${window.location.pathname}#journey`;
            try {
                await navigator.clipboard.writeText(url);
                MyavanaNext.API.showToast('Link copied', 'success');
            } catch (err) {
                MyavanaNext.API.showToast('Could not copy link', 'error');
            }
            return;
        }

        if (key === 'download') {
            if (!img) { MyavanaNext.API.showToast('No photo on this entry to download', 'error'); return; }
            if (navigator.share && navigator.canShare) {
                try {
                    const resp = await fetch(img);
                    const blob = await resp.blob();
                    const file = new File([blob], 'myavana-entry.jpg', { type: blob.type || 'image/jpeg' });
                    if (navigator.canShare({ files: [file] })) {
                        await navigator.share({ files: [file], title: 'My hair journey', text: shareContext.notes || '' });
                        return;
                    }
                } catch (err) {
                    // fall through to plain download
                }
            }
            const a = document.createElement('a');
            a.href = img;
            a.download = 'myavana-entry.jpg';
            a.target = '_blank';
            a.click();
        }
    }

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str == null ? '' : String(str);
        return div.innerHTML.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    return { init, refresh, focusEntry };
})();
